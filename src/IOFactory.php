<?php

namespace MkGrow\ContentControl;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory as PHPWordIOFactory;
use PhpOffice\PhpWord\Writer\WriterInterface;
use MkGrow\ContentControl\Exception\ContentControlException;
use MkGrow\ContentControl\Exception\ZipArchiveException;
use MkGrow\ContentControl\Exception\DocumentNotFoundException;
use MkGrow\ContentControl\Exception\TemporaryFileException;

/**
 * Factory para criar Writers com suporte a Content Controls
 * 
 * Esta classe não estende a IOFactory do PHPWord nem registra
 * Writers customizados no core. Em vez disso, utiliza a IOFactory
 * padrão do PHPWord e um workaround baseado em manipulação de ZIP
 * para injetar Content Controls no document.xml de arquivos .docx.
 * 
 * ## Design Decision: ZIP Manipulation Workaround
 * 
 * **Rationale**: PHPWord não possui suporte nativo para Content Controls (Structured Document Tags)
 * conforme ISO/IEC 29500-1:2016 §17.5.2. Estender o core do PHPWord requer fork ou patch upstream,
 * que quebra compatibilidade e dificulta atualizações. Esta biblioteca opta por injeção pós-geração
 * via manipulação do arquivo DOCX (que é um ZIP contendo XMLs).
 * 
 * **Workflow**:
 * 1. PHPWord gera .docx padrão (sem Content Controls)
 * 2. Arquivo temporário é aberto como ZipArchive
 * 3. word/document.xml é extraído e parseado
 * 4. XML dos Content Controls é injetado antes de `</w:body>`
 * 5. document.xml atualizado é reescrito no ZIP
 * 6. Arquivo final é movido para destino
 * 
 * **Tradeoffs**:
 * - ✅ Mantém compatibilidade total com PHPWord (sem fork)
 * - ✅ Suporta atualizações do PHPWord sem modificação
 * - ✅ Content Controls conformes com ISO/IEC 29500-1
 * - ⚠️  Overhead de I/O (arquivo temporário + ZIP manipulation)
 * - ⚠️  Depende de ext-zip (já requerida por PHPWord)
 * - ❌ Não integra com Writers nativos do PHPWord
 * 
 * **Limitações**:
 * - Content Controls devem ser criados separadamente e injetados via `saveWithContentControls()`
 * - Não suporta serialização incremental (todo documento gerado antes da injeção)
 * - Posição dos Content Controls é sempre antes de `</w:body>` (final do documento)
 * 
 * @since 2.0.0
 */
class IOFactory
{
    /**
     * Cria Writer para documento PHPWord com suporte a Content Controls
     * 
     * @param PhpWord $phpWord Documento PHPWord
     * @param string $format Formato do documento (padrão: 'Word2007')
     * @return WriterInterface Writer configurado
     * @throws \PhpOffice\PhpWord\Exception\Exception
     * 
     * @example
     * ```php
     * $phpWord = new PhpWord();
     * // ... adicionar conteúdo e Content Controls
     * 
     * $writer = IOFactory::createWriter($phpWord);
     * $writer->save('documento.docx');
     * ```
     */
    public static function createWriter(PhpWord $phpWord, string $format = 'Word2007'): WriterInterface
    {
        // Por ora, usar IOFactory padrão do PHPWord
        // Em versão futura, podemos registrar Writers customizados via reflexão
        return PHPWordIOFactory::createWriter($phpWord, $format);
    }
    
    /**
     * Registra Writers customizados no sistema PHPWord
     * 
     * NOTA: Esta funcionalidade requer modificação do PHPWord core
     * ou uso de workaround com manipulação direta do ZIP.
     * 
     * @return void
     * @internal
     * @deprecated Use IOFactory::saveWithContentControls() instead
     */
    public static function registerCustomWriters(): void
    {
        trigger_error(
            'ContentControl: IOFactory::registerCustomWriters() is deprecated and will be removed. Use IOFactory::saveWithContentControls() instead.',
            E_USER_DEPRECATED
        );
        // Placeholder para futura implementação
        // Requer extensão do PHPWord ou approach alternativo
    }
    
    /**
     * Salva documento PHPWord com Content Controls via manipulação ZIP
     * 
     * Workaround que injeta Content Controls diretamente no document.xml
     * do arquivo .docx gerado, contornando limitação do PHPWord.
     * 
     * @param PhpWord $phpWord Documento PHPWord base
     * @param mixed[] $contentControls Content Controls a adicionar. Elementos que não são instâncias de ContentControl são silenciosamente ignorados.
     * @param string $filename Caminho do arquivo de saída
     * @return void
     * @throws \PhpOffice\PhpWord\Exception\Exception Exceção propagada da biblioteca PHPWord ao criar o Writer ou salvar o documento base
     * @throws \RuntimeException If target directory is not writable or file move fails
     * @throws ZipArchiveException If ZIP operations fail
     * @throws DocumentNotFoundException If word/document.xml is missing
     * @throws TemporaryFileException If temporary file cleanup fails
     * 
     * @example
     * ```php
     * $phpWord = new PhpWord();
     * $section = $phpWord->addSection();
     * $section->addText('Conteúdo');
     * 
     * $control = new ContentControl($section, ['alias' => 'Campo']);
     * 
     * IOFactory::saveWithContentControls(
     *     $phpWord,
     *     [$control],
     *     'documento.docx'
     * );
     * ```
     */
    public static function saveWithContentControls(
        PhpWord $phpWord,
        array $contentControls,
        string $filename
    ): void {
        // Verificar se o diretório de destino existe
        $targetDir = dirname($filename);
        if (!is_dir($targetDir) || !is_writable($targetDir)) {
            throw new \RuntimeException(
                'ContentControl: Target directory not writable: ' . dirname($filename)
            );
        }
        
        // 1. Verificar se há Content Controls antes de fazer operações de ZIP
        $hasContentControls = false;
        foreach ($contentControls as $control) {
            if ($control instanceof ContentControl) {
                $hasContentControls = true;
                break;
            }
        }
        
        // 2. Salvar documento base
        $tempFile = sys_get_temp_dir() . '/phpword_' . uniqid() . '.docx';
        
        try {
            $writer = self::createWriter($phpWord);
            $writer->save($tempFile);
            
            // Se não há Content Controls, apenas copiar o arquivo e retornar
            if (!$hasContentControls) {
                if (!rename($tempFile, $filename)) {
                    throw new \RuntimeException(
                        'ContentControl: Failed to move file from ' . $tempFile . ' to ' . $filename
                    );
                }
                return;
            }
            
            // 3. Abrir como ZIP
            $zip = new \ZipArchive();
            $openResult = $zip->open($tempFile);
            if ($openResult !== true) {
                throw new ZipArchiveException($openResult, $tempFile);
            }
            
            // 4. Ler document.xml
            $documentXml = $zip->getFromName('word/document.xml');
            if ($documentXml === false) {
                $zip->close();
                throw new DocumentNotFoundException('word/document.xml', $tempFile);
            }
            
            // 5. Gerar XML dos Content Controls
            $contentControlsXml = '';
            foreach ($contentControls as $control) {
                if ($control instanceof ContentControl) {
                    $contentControlsXml .= $control->getXml();
                }
            }
            
            // 6. Injetar antes de </w:body>
            $bodyClosePos = strpos($documentXml, '</w:body>');
            if ($bodyClosePos !== false) {
                $documentXml = substr_replace(
                    $documentXml,
                    $contentControlsXml,
                    $bodyClosePos,
                    0
                );
            }
            
            // 7. Atualizar document.xml
            $zip->deleteName('word/document.xml');
            $zip->addFromString('word/document.xml', $documentXml);
            $zip->close();
            
            // 8. Mover para destino
            if (!rename($tempFile, $filename)) {
                throw new \RuntimeException(
                    'ContentControl: Failed to move file from ' . $tempFile . ' to ' . $filename
                );
            }
        } finally {
            // Limpar arquivo temporário se ainda existir
            if (file_exists($tempFile)) {
                self::unlinkWithRetry($tempFile);
            }
        }
    }

    /**
     * Tenta deletar arquivo com múltiplas tentativas
     * 
     * Em Windows, arquivos podem estar brevemente bloqueados após operações de ZIP.
     * Esta função tenta deletar com retries e clearstatcache.
     * 
     * @param string $filePath Caminho do arquivo a deletar
     * @param int $maxAttempts Número máximo de tentativas
     * @return void
     * @throws TemporaryFileException Se todas tentativas falharem
     */
    private static function unlinkWithRetry(string $filePath, int $maxAttempts = 3): void
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            clearstatcache(true, $filePath);
            
            if (@unlink($filePath)) {
                return; // Sucesso
            }
            
            if (!file_exists($filePath)) {
                return; // Arquivo já não existe
            }
            
            // Esperar antes de próxima tentativa (exceto na última)
            if ($attempt < $maxAttempts) {
                usleep(100000); // 100ms
            }
        }
        
        // Todas tentativas falharam
        throw new TemporaryFileException($filePath);
    }
}

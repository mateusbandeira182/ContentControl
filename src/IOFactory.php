<?php

namespace MkGrow\ContentControl;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory as PHPWordIOFactory;
use PhpOffice\PhpWord\Writer\WriterInterface;

/**
 * Factory para criar Writers com suporte a Content Controls
 * 
 * Esta classe não estende a IOFactory do PHPWord nem registra
 * Writers customizados no core. Em vez disso, utiliza a IOFactory
 * padrão do PHPWord e um workaround baseado em manipulação de ZIP
 * para injetar Content Controls no document.xml de arquivos .docx.
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
     */
    public static function registerCustomWriters(): void
    {
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
     * @param mixed[] $contentControls Content Controls a adicionar
     * @param string $filename Caminho do arquivo de saída
     * @return bool Sucesso da operação
     * @throws \PhpOffice\PhpWord\Exception\Exception Se falhar ao criar Writer ou salvar documento base
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
    ): bool {
        // Verificar se o diretório de destino existe
        $targetDir = dirname($filename);
        if (!is_dir($targetDir) || !is_writable($targetDir)) {
            return false;
        }
        
        // 1. Salvar documento base
        $tempFile = sys_get_temp_dir() . '/phpword_' . uniqid() . '.docx';
        $success = false;
        
        try {
            $writer = self::createWriter($phpWord);
            $writer->save($tempFile);
            
            // 2. Abrir como ZIP
            $zip = new \ZipArchive();
            if ($zip->open($tempFile) !== true) {
                return false;
            }
            
            // 3. Ler document.xml
            $documentXml = $zip->getFromName('word/document.xml');
            if ($documentXml === false) {
                $zip->close();
                return false;
            }
            
            // 4. Gerar XML dos Content Controls
            $contentControlsXml = '';
            foreach ($contentControls as $control) {
                if ($control instanceof ContentControl) {
                    $contentControlsXml .= $control->getXml();
                }
            }
            
            // 5. Injetar antes de </w:body>
            $bodyClosePos = strpos($documentXml, '</w:body>');
            if ($bodyClosePos !== false) {
                $documentXml = substr_replace(
                    $documentXml,
                    $contentControlsXml,
                    $bodyClosePos,
                    0
                );
            }
            
            // 6. Atualizar document.xml
            $zip->deleteName('word/document.xml');
            $zip->addFromString('word/document.xml', $documentXml);
            $zip->close();
            
            // 7. Mover para destino
            $success = rename($tempFile, $filename);
            
            return $success;
        } finally {
            // Limpar arquivo temporário se ainda existir
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }
}

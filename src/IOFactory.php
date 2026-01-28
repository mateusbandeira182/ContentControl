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
     * @deprecated 2.0.0 Use ContentControl::save() instead
     * @param PhpWord $phpWord Documento PHPWord base
     * @param mixed[] $contentControls Content Controls a adicionar
     * @param string $filename Caminho do arquivo de saída
     * @return void
     */
    public static function saveWithContentControls(
        PhpWord $phpWord,
        array $contentControls,
        string $filename
    ): void {
        trigger_error(
            'IOFactory::saveWithContentControls() is deprecated. Use ContentControl::save() instead.',
            E_USER_DEPRECATED
        );
        
        // Esta funcionalidade foi movida para ContentControl::save()
        // Mantida por compatibilidade temporária
        throw new \BadMethodCallException(
            'IOFactory::saveWithContentControls() is deprecated. Use ContentControl::save() instead.'
        );
    }
}

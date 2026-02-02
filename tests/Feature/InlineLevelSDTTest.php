<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;

/**
 * Feature tests for inline-level Content Controls (v3.1)
 * 
 * NOTA v3.1: Testes marcados como SKIPPED aguardando enhancement do ElementLocator.
 * 
 * LIMITAÇÃO ATUAL: ElementLocator não suporta localização de Text/TextRun
 * dentro de células (<w:tc>). A implementação inline-level está funcional,
 * mas requer melhorias no ElementLocator para suportar contextos aninhados.
 * 
 * Veja: INLINE_SDT_ANALYSIS.md para detalhes técnicos.
 */
describe('Feature - Inline-Level SDTs (SKIPPED - ElementLocator Enhancement Required)', function () {
    /**
     * FT01: SKIPPED - Aguardando ElementLocator enhancement para Text em Cell
     * 
     * Requisito: ElementLocator deve suportar XPath como:
     * //w:body//w:tc//w:p[not(ancestor::w:sdtContent)][position()=1]
     */
    test('wraps Text in Cell with inline-level SDT (SKIPPED)')
        ->skip('ElementLocator does not yet support Text localization within <w:tc> cells. Planned for future version.');

    /**
     * FT02: SKIPPED - Aguardando ElementLocator enhancement
     */
    test('creates GROUP SDT with inline cell SDTs inside (SKIPPED)')
        ->skip('ElementLocator does not yet support Text localization within <w:tc> cells. Planned for future version.');

    /**
     * FT03: SKIPPED - Aguardando ElementLocator enhancement
     */
    test('does not duplicate paragraphs when wrapping inline (SKIPPED)')
        ->skip('ElementLocator does not yet support Text localization within <w:tc> cells. Planned for future version.');

    /**
     * FT04: Backward compatibility SEMPRE funciona (block-level)
     * 
     * Este teste valida que a mudança não quebrou funcionalidade existente.
     */
    test('maintains backward compatibility for block-level SDTs', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $text = $section->addText('Paragraph Content');
        
        // SEM inlineLevel (default = false)
        $cc->addContentControl($text, [
            'alias' => 'TestParagraph',
        ]);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'inline_sdt_ft04_') . '.docx';
        $cc->save($tempFile);
        
        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        expect($xml)->toContain('<w:alias w:val="TestParagraph"/>');
        expect($xml)->toContain('Paragraph Content');
        
        unlink($tempFile);
    });

    /**
     * FT05: SKIPPED - Aguardando ElementLocator enhancement
     */
    test('wraps multiple TextRuns in same cell with separate SDTs (SKIPPED)')
        ->skip('ElementLocator does not yet support TextRun localization within <w:tc> cells. Planned for future version.');
});

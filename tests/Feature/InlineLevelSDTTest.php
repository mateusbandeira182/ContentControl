<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;

/**
 * Feature tests for inline-level Content Controls
 * 
 * ElementLocator now supports locating Text/TextRun inside cells (<w:tc>).
 * 
 * See: INLINE_SDT_ANALYSIS.md for technical details.
 */
describe('Feature - Inline-Level SDTs', function () {
    /**
     * FT01: Wrap Text in Cell with inline-level SDT
     * 
     * ElementLocator supports XPath:
     * //w:body//w:tbl//w:tc/w:p[not(ancestor::w:sdtContent)][1]
     */
    test('wraps Text in Cell with inline-level SDT', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $table = $section->addTable();
        $row = $table->addRow();
        $cell = $row->addCell();
        
        $text = $cell->addText('Editable Content');
        
        // WITH inlineLevel = true
        $cc->addContentControl($text, [
            'alias' => 'EditableField',
            'inlineLevel' => true,
        ]);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'inline_sdt_ft01_') . '.docx';
        $cc->save($tempFile);
        
        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        // Verify inline SDT (inside <w:tc>)
        expect($xml)->toContain('<w:alias w:val="EditableField"/>');
        expect($xml)->toContain('Editable Content');
        
        // Verify that SDT is INSIDE <w:tc> (inline)
        expect($xml)->toMatch('/<w:tc>.*<w:sdt>.*<w:alias w:val="EditableField".*<\/w:sdt>.*<\/w:tc>/s');
        
        safeUnlink($tempFile);
    });

    /**
     * FT02: Creates GROUP SDT with inline cell SDTs inside
     */
    test('creates GROUP SDT with inline cell SDTs inside', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $table = $section->addTable();
        
        // Editable cells inside table (create BEFORE registering GROUP)
        $row = $table->addRow();
        $cell1 = $row->addCell();
        $text1 = $cell1->addText('Item 1');
        
        // First register inline SDTs
        $cc->addContentControl($text1, [
            'alias' => 'ItemName',
            'inlineLevel' => true,
        ]);
        
        // Then register GROUP SDT wrapping table
        $cc->addContentControl($table, [
            'alias' => 'InvoiceTable',
            'type' => ContentControl::TYPE_GROUP,
            'lockType' => ContentControl::LOCK_SDT_LOCKED,
        ]);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'inline_sdt_ft02_') . '.docx';
        $cc->save($tempFile);
        
        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        // Verify GROUP SDT
        expect($xml)->toContain('<w:alias w:val="InvoiceTable"/>');
        expect($xml)->toContain('<w:group/>');
        
        // Verify inline SDT INSIDE GROUP
        expect($xml)->toContain('<w:alias w:val="ItemName"/>');
        
        safeUnlink($tempFile);
    });

    /**
     * FT03: Does not duplicate paragraphs when wrapping inline
     */
    test('does not duplicate paragraphs when wrapping inline', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $table = $section->addTable();
        $row = $table->addRow();
        $cell = $row->addCell();
        
        $text = $cell->addText('Unique Content');
        
        $cc->addContentControl($text, [
            'alias' => 'UniqueField',
            'inlineLevel' => true,
        ]);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'inline_sdt_ft03_') . '.docx';
        $cc->save($tempFile);
        
        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        // Verify content appears exactly once
        $count = substr_count($xml, 'Unique Content');
        expect($count)->toBe(1, 'Content should appear exactly once (no duplication)');
        
        safeUnlink($tempFile);
    });

    /**
     * FT04: Backward compatibility ALWAYS works (block-level)
     * 
     * This test validates that the change didn't break existing functionality.
     */
    test('maintains backward compatibility for block-level SDTs', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $text = $section->addText('Paragraph Content');
        
        // WITHOUT inlineLevel (default = false)
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
        
        safeUnlink($tempFile);
    });

    /**
     * FT05: Wraps multiple TextRuns in same cell with separate SDTs
     */
    test('wraps multiple TextRuns in same cell with separate SDTs', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $table = $section->addTable();
        $row = $table->addRow();
        $cell = $row->addCell();
        
        $textRun1 = $cell->addTextRun();
        $textRun1->addText('First Run');
        
        $textRun2 = $cell->addTextRun();
        $textRun2->addText('Second Run');
        
        $cc->addContentControl($textRun1, [
            'alias' => 'FirstRun',
            'inlineLevel' => true,
        ]);
        
        $cc->addContentControl($textRun2, [
            'alias' => 'SecondRun',
            'inlineLevel' => true,
        ]);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'inline_sdt_ft05_') . '.docx';
        $cc->save($tempFile);
        
        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        // Verify both inline SDTs
        expect($xml)->toContain('<w:alias w:val="FirstRun"/>');
        expect($xml)->toContain('<w:alias w:val="SecondRun"/>');
        expect($xml)->toContain('First Run');
        expect($xml)->toContain('Second Run');
        
        safeUnlink($tempFile);
    });
});

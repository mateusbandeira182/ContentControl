<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use PhpOffice\PhpWord\IOFactory as PHPWordIOFactory;

describe('No Duplication (DOM Inline Wrapping)', function () {
    test('does not duplicate content when wrapping Text with SDT', function () {
        // Create ContentControl
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Unique text that should not duplicate');
        
        // Add Content Control to Text element
        $cc->addContentControl($text, [
            'id' => '12345678',
            'alias' => 'Main Text',
            'tag' => 'main-text'
        ]);
        
        // Save DOCX
        $outputPath = sys_get_temp_dir() . '/test_no_duplication_text.docx';
        $cc->save($outputPath);
        
        // Verify DOCX structure
        $zip = new ZipArchive();
        $zip->open($outputPath);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        expect($documentXml)->toBeString();
        assert(is_string($documentXml));
        
        // Count text occurrences
        $textOccurrences = substr_count($documentXml, 'Unique text that should not duplicate');
        expect($textOccurrences)->toBe(1, 'Text should appear exactly once in document.xml');
        
        // Verify SDT presence
        expect($documentXml)->toContain('<w:sdt>');
        expect($documentXml)->toContain('<w:sdtPr>');
        expect($documentXml)->toContain('w:val="12345678"');
        
        // Cleanup file
        @safeUnlink($outputPath);
    });

    test('does not duplicate content when wrapping Table with SDT', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Cell R0C0');
        $table->addCell(2000)->addText('Cell R0C1');
        
        // Add Content Control to table
        $cc->addContentControl($table, [
            'id' => '87654321',
            'alias' => 'Main Table'
        ]);
        
        $outputPath = sys_get_temp_dir() . '/test_no_duplication_table.docx';
        $cc->save($outputPath);
        
        $zip = new ZipArchive();
        $zip->open($outputPath);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        assert(is_string($documentXml));
        
        // Verify no duplication
        $cellR0C0Count = substr_count($documentXml, 'Cell R0C0');
        $cellR0C1Count = substr_count($documentXml, 'Cell R0C1');
        
        expect($cellR0C0Count)->toBe(1, 'Cell R0C0 should appear exactly once');
        expect($cellR0C1Count)->toBe(1, 'Cell R0C1 should appear exactly once');
        
        // Verify SDT
        expect($documentXml)->toContain('<w:sdt>');
        expect($documentXml)->toContain('w:val="87654321"');
        
        @safeUnlink($outputPath);
    });

    test('does not duplicate content when wrapping nested Cell', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $table = $section->addTable();
        $table->addRow();
        $cell1 = $table->addCell(2000);
        $cell1->addText('Protected cell content');
        $cell2 = $table->addCell(2000);
        $cell2->addText('Normal cell');
        
        // Add Content Control ONLY to first cell
        $cc->addContentControl($cell1, [
            'id' => '11111111',
            'alias' => 'Protected Cell',
            'lockType' => ContentControl::LOCK_SDT_LOCKED
        ]);
        
        $outputPath = sys_get_temp_dir() . '/test_no_duplication_cell.docx';
        $cc->save($outputPath);
        
        $zip = new ZipArchive();
        $zip->open($outputPath);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        assert(is_string($documentXml));
        
        // Verify no duplication
        $protectedCellCount = substr_count($documentXml, 'Protected cell content');
        $normalCellCount = substr_count($documentXml, 'Normal cell');
        
        expect($protectedCellCount)->toBe(1, 'Protected cell content should appear exactly once');
        expect($normalCellCount)->toBe(1, 'Normal cell content should appear exactly once');
        
        // Verify SDT only on protected cell
        expect($documentXml)->toContain('<w:sdt>');
        expect($documentXml)->toContain('w:val="11111111"');
        expect($documentXml)->toContain('w:val="sdtLocked"');
        
        @safeUnlink($outputPath);
    });

    test('does not duplicate when wrapping multiple different elements', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        // Add text
        $section->addText('Text before table');
        
        // Add table
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Table data');
        
        // Add another text
        $section->addText('Text after table');
        
        // Add Content Controls
        $cc->addContentControl($table, [
            'id' => '22222222',
            'alias' => 'Data Table'
        ]);
        
        $outputPath = sys_get_temp_dir() . '/test_no_duplication_multiple.docx';
        $cc->save($outputPath);
        
        $zip = new ZipArchive();
        $zip->open($outputPath);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        assert(is_string($documentXml));
        
        // Verify no duplication of ALL content
        expect(substr_count($documentXml, 'Text before table'))->toBe(1);
        expect(substr_count($documentXml, 'Table data'))->toBe(1);
        expect(substr_count($documentXml, 'Text after table'))->toBe(1);
        
        // Verify SDT
        expect($documentXml)->toContain('<w:sdt>');
        expect($documentXml)->toContain('w:val="22222222"');
        
        @safeUnlink($outputPath);
    });
});


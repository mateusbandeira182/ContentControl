<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use Tests\Fixtures\SampleElements;

describe('PHPWord Integration - Full Document', function () {
    
    test('generates valid document with Content Control', function () {
        $cc = new ContentControl();
        
        // Create section with content
        $section = $cc->addSection();
        $textElement = $section->addText('This is a functional Content Control', ['bold' => true]);
        
        // Wrap Text element in Content Control (v3.0 does not support Section)
        $cc->addContentControl($textElement, [
            'alias' => 'Test Field',
            'tag' => 'test-field',
            'type' => ContentControl::TYPE_RICH_TEXT,
            'lockType' => ContentControl::LOCK_SDT_LOCKED,
        ]);
        
        // Generate temporary file
        $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.docx';
        try {
            $cc->save($tempFile);
            
            // Validate created file
            expect(file_exists($tempFile))->toBeTrue();
            
            // Open and validate XML
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            
            expect($xml)->toBeString();
            assert(is_string($xml)); // PHPStan type narrowing
            $dom = new DOMDocument();
            expect(@$dom->loadXML($xml))->toBeTrue();
        } finally {
            if (file_exists($tempFile)) {
                safeUnlink($tempFile);
            }
        }
    });

    test('integrates with element fixtures', function () {
        // Table (complex element)
        $cc1 = new ContentControl();
        $section1 = $cc1->addSection();
        
        $table = $section1->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Cell 1');
        $table->addCell(2000)->addText('Cell 2');
        
        $cc1->addContentControl($table, ['type' => ContentControl::TYPE_GROUP]);
        
        $tempFile1 = sys_get_temp_dir() . '/test_' . uniqid() . '.docx';
        try {
            $cc1->save($tempFile1);
            
            $zip = new ZipArchive();
            $zip->open($tempFile1);
            $xml1 = $zip->getFromName('word/document.xml');
            $zip->close();
            
            // Validate Protected Table
            expect($xml1)->toContain('Cell 1');
            expect($xml1)->toContain('Cell 2');
            expect($xml1)->toContain('<w:group');
        } finally {
            if (file_exists($tempFile1)) {
                safeUnlink($tempFile1);
            }
        }
        
        // Additional test: protect only one table cell
        $cc2 = new ContentControl();
        $section2 = $cc2->addSection();
        
        $table = $section2->addTable();
        for ($r = 0; $r < 3; $r++) {
            $table->addRow();
            for ($c = 0; $c < 2; $c++) {
                $table->addCell(2000)->addText("R{$r}C{$c}");
            }
        }
        
        // Protect entire Table (not Section)
        $cc2->addContentControl($table, ['type' => ContentControl::TYPE_GROUP]);
        
        $tempFile2 = sys_get_temp_dir() . '/test_' . uniqid() . '.docx';
        try {
            $cc2->save($tempFile2);
            
            $zip = new ZipArchive();
            $zip->open($tempFile2);
            $xml2 = $zip->getFromName('word/document.xml');
            $zip->close();
            
            expect($xml2)->toContain('<w:tbl>');
            expect($xml2)->toContain('R0C0');
            expect($xml2)->toContain('R2C1');
        } finally {
            if (file_exists($tempFile2)) {
                safeUnlink($tempFile2);
            }
        }
    });
});

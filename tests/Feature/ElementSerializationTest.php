<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\SDTInjector;

describe('Element Serialization - Text', function () {
    
    test('serializes Text with <w:p> wrapper', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText('Test text');
        
        // Create temporary file to inject XML
        $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.docx';
        try {
            $cc->save($tempFile);
            
            // Open and validate XML
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            
            // Should contain <w:p> wrapping <w:r><w:t>
            expect($xml)->toContain('<w:p');
            expect($xml)->toContain('<w:t');
            expect($xml)->toContain('Test text');
        } finally {
            if (file_exists($tempFile)) {
                safeUnlink($tempFile);
            }
        }
    });
});

describe('Element Serialization - TextRun', function () {
    
    test('serializes TextRun with external <w:p> wrapper', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $textRun = $section->addTextRun();
        $textRun->addText('Part 1 ');
        $textRun->addText('Part 2', ['bold' => true]);
        
        $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.docx';
        try {
            $cc->save($tempFile);
            
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            
            // TextRun must have external <w:p>
            expect($xml)->toContain('<w:p');
            expect($xml)->toContain('Part 1');
            expect($xml)->toContain('Part 2');
        } finally {
            if (file_exists($tempFile)) {
                safeUnlink($tempFile);
            }
        }
    });
});

describe('Element Serialization - Table', function () {
    
    test('serializes Table WITHOUT <w:p> wrapper', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Cell 1');
        $table->addCell(2000)->addText('Cell 2');
        
        $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.docx';
        try {
            $cc->save($tempFile);
            
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            
            // Should contain <w:tbl>
            expect($xml)->toContain('<w:tbl>');
            expect($xml)->toContain('Cell 1');
            expect($xml)->toContain('Cell 2');
        } finally {
            if (file_exists($tempFile)) {
                safeUnlink($tempFile);
            }
        }
    });
});

describe('Element Serialization - Multiple Elements', function () {
    
    test('serializes mix of elements correctly', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        // Text (needs wrapper)
        $section->addText('Paragraph before table');
        
        // Table (without wrapper)
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Cell');
        
        // Text (needs wrapper)
        $section->addText('Paragraph after table');
        
        $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.docx';
        try {
            $cc->save($tempFile);
            
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            
            // Verify presence of all elements
            expect($xml)->toContain('Paragraph before table');
            expect($xml)->toContain('<w:tbl>');
            expect($xml)->toContain('Cell');
            expect($xml)->toContain('Paragraph after table');
        } finally {
            if (file_exists($tempFile)) {
                safeUnlink($tempFile);
            }
        }
    });
});

test('handles empty Content Control', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    // Add empty Text (v3.0 does not support empty Section)
    $emptyText = $section->addText('');
    
    $cc->addContentControl($emptyText);
    
    $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.docx';
    try {
        $cc->save($tempFile);
        
        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        // sdtContent must be present
        expect($xml)->toContain('w:sdtContent');
    } finally {
        if (file_exists($tempFile)) {
            safeUnlink($tempFile);
        }
    }
});

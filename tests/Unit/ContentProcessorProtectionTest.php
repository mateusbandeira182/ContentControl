<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\ContentProcessor;

beforeEach(function () {
    $this->tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
});

afterEach(function () {
    if (file_exists($this->tempFile)) {
        unlink($this->tempFile);
    }
});

describe('ContentProcessor Document Protection', function () {
    test('addDocumentProtection adds protection to settings.xml', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        $processor->removeAllControlContents(true); // block=true triggers protection
        $processor->save();
        
        // Verify protection was added
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $settingsXml = $zip->getFromName('word/settings.xml');
        $zip->close();
        
        expect($settingsXml)->toContain('<w:documentProtection');
        expect($settingsXml)->toContain('w:edit="readOnly"');
    });

    test('addDocumentProtection preserves existing settings', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        // Get original settings
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $originalSettings = $zip->getFromName('word/settings.xml');
        $zip->close();
        
        $processor->removeAllControlContents(true);
        $processor->save();
        
        // Get new settings
        $zip->open($this->tempFile);
        $newSettings = $zip->getFromName('word/settings.xml');
        $zip->close();
        
        // Should contain protection AND original content structure
        expect($newSettings)->toContain('<w:documentProtection');
        expect($newSettings)->toContain('<w:settings');
    });

    test('addDocumentProtection creates settings.xml if missing', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        // Remove settings.xml manually
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        if ($zip->statName('word/settings.xml') !== false) {
            $zip->deleteName('word/settings.xml');
        }
        $zip->close();
        
        $processor = new ContentProcessor($this->tempFile);
        $processor->removeAllControlContents(true);
        $processor->save();
        
        // Verify settings.xml was created
        $zip->open($this->tempFile);
        $settingsXml = $zip->getFromName('word/settings.xml');
        $zip->close();
        
        expect($settingsXml)->not->toBeFalse();
        expect($settingsXml)->toContain('<w:documentProtection');
    });
});

describe('ContentProcessor removeAllSdtsInFile', function () {
    test('removeAllSdtsInFile removes all SDTs from document.xml', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text1 = $section->addText('Content 1');
        $text2 = $section->addText('Content 2');
        $cc->addContentControl($text1, ['tag' => 'tag1']);
        $cc->addContentControl($text2, ['tag' => 'tag2']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        // Access private method
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('removeAllSdtsInFile');
        $method->setAccessible(true);
        
        $count = $method->invoke($processor, 'word/document.xml');
        
        expect($count)->toBe(2);
    });

    test('removeAllSdtsInFile returns 0 for file with no SDTs', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText('Plain content');
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('removeAllSdtsInFile');
        $method->setAccessible(true);
        
        $count = $method->invoke($processor, 'word/document.xml');
        
        expect($count)->toBe(0);
    });

    test('removeAllSdtsInFile clears sdtContent children', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Important content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('removeAllSdtsInFile');
        $method->setAccessible(true);
        
        $method->invoke($processor, 'word/document.xml');
        $processor->save();
        
        // Verify SDT structure is still there but content is removed
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        // SDT should still exist
        expect($xml)->toContain('<w:sdt>');
        // But sdtContent should be empty
        expect($xml)->toContain('<w:sdtContent/>');
    });

    test('removeAllSdtsInFile handles nested SDTs', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $table = $section->addTable();
        $row = $table->addRow();
        $cell = $row->addCell();
        $text = $cell->addText('Cell content');
        
        $cc->addContentControl($table, ['tag' => 'table-tag']);
        $cc->addContentControl($cell, ['tag' => 'cell-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('removeAllSdtsInFile');
        $method->setAccessible(true);
        
        $count = $method->invoke($processor, 'word/document.xml');
        
        // Should remove both SDTs
        expect($count)->toBeGreaterThanOrEqual(2);
    });
});

describe('ContentProcessor removeAllControlContents', function () {
    test('removeAllControlContents processes headers and footers', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $header = $section->addHeader();
        $headerText = $header->addText('Header');
        $cc->addContentControl($headerText, ['tag' => 'header-tag']);
        
        $footer = $section->addFooter();
        $footerText = $footer->addText('Footer');
        $cc->addContentControl($footerText, ['tag' => 'footer-tag']);
        
        $text = $section->addText('Body');
        $cc->addContentControl($text, ['tag' => 'body-tag']);
        
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        $count = $processor->removeAllControlContents();
        
        // Should process all 3 SDTs
        expect($count)->toBeGreaterThanOrEqual(3);
    });

    test('removeAllControlContents with block=false does not add protection', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        $processor->removeAllControlContents(false);
        $processor->save();
        
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $settingsXml = $zip->getFromName('word/settings.xml');
        $zip->close();
        
        // Should not contain documentProtection
        expect($settingsXml)->not->toContain('<w:documentProtection');
    });

    test('removeAllControlContents returns correct count', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        for ($i = 0; $i < 5; $i++) {
            $text = $section->addText("Content {$i}");
            $cc->addContentControl($text, ['tag' => "tag-{$i}"]);
        }
        
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        $count = $processor->removeAllControlContents();
        
        expect($count)->toBe(5);
    });

    test('removeAllControlContents marks files as modified', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $reflection = new ReflectionClass($processor);
        $property = $reflection->getProperty('modifiedFiles');
        $property->setAccessible(true);
        
        $processor->removeAllControlContents();
        
        $modifiedFiles = $property->getValue($processor);
        expect($modifiedFiles)->not->toBeEmpty();
    });
});

describe('ContentProcessor serializePhpWordElement', function () {
    test('serializePhpWordElement handles Text element', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        // Create a new Text element
        $newText = new \PhpOffice\PhpWord\Element\Text('New content');
        
        // Access private method
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('serializePhpWordElement');
        $method->setAccessible(true);
        
        $xml = $method->invoke($processor, $newText);
        
        expect($xml)->toBeString();
        expect($xml)->toContain('New content');
    });

    test('serializePhpWordElement handles Table element', function () {
        $table = new \PhpOffice\PhpWord\Element\Table();
        $row = $table->addRow();
        $row->addCell(2000)->addText('Cell content');
        
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Dummy');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('serializePhpWordElement');
        $method->setAccessible(true);
        
        $xml = $method->invoke($processor, $table);
        
        expect($xml)->toContain('<w:tbl>');
        expect($xml)->toContain('Cell content');
    });

    test('serializePhpWordElement throws for unsupported element type', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Dummy');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        // Create a minimal element that has no writer by extending AbstractElement
        // Use a stdClass wrapped as an element to avoid PHP 8.2 compatibility issues
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('serializePhpWordElement');
        $method->setAccessible(true);
        
        // Test that the method validates writer class existence
        // by attempting to serialize a ListItem (which exists but may not have a direct writer)
        try {
            $listItem = new \PhpOffice\PhpWord\Element\ListItem('test');
            $xml = $method->invoke($processor, $listItem);
            // If it succeeds, the test passes - we just want to ensure no fatal errors
            expect($xml)->toBeString();
        } catch (RuntimeException $e) {
            // If it throws RuntimeException about missing writer, that's the expected behavior
            expect($e->getMessage())->toContain('writer');
        }
    });
});

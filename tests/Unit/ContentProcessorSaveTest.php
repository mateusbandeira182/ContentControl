<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\ContentProcessor;
use MkGrow\ContentControl\Exception\ZipArchiveException;

beforeEach(function () {
    // Create temporary DOCX file for testing
    $this->tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
    
    $cc = new ContentControl();
    $section = $cc->addSection();
    $text = $section->addText('Sample content');
    $cc->addContentControl($text, ['tag' => 'test-tag', 'alias' => 'Test']);
    $cc->save($this->tempFile);
});

afterEach(function () {
    if (file_exists($this->tempFile)) {
        unlink($this->tempFile);
    }
});

describe('ContentProcessor Save Operations', function () {
    test('save() with outputPath creates new file', function () {
        $processor = new ContentProcessor($this->tempFile);
        
        // Modify content
        $processor->replaceContent('test-tag', 'New content');
        
        // Save to new location
        $outputFile = tempnam(sys_get_temp_dir(), 'output_') . '.docx';
        $processor->save($outputFile);
        
        expect(file_exists($outputFile))->toBeTrue();
        expect(filesize($outputFile))->toBeGreaterThan(0);
        
        // Verify new file is valid DOCX
        $zip = new ZipArchive();
        expect($zip->open($outputFile))->toBe(true);
        expect($zip->getFromName('word/document.xml'))->not->toBeFalse();
        $zip->close();
        
        unlink($outputFile);
    });

    test('save() without outputPath updates file in-place', function () {
        $originalSize = filesize($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        $processor->replaceContent('test-tag', 'Modified content that is longer than the original');
        $processor->save();
        
        // File should still exist and be modified
        expect(file_exists($this->tempFile))->toBeTrue();
        // Size likely changed due to content modification
        expect(filesize($this->tempFile))->not->toBe($originalSize);
    });

    test('save() throws exception for non-existent output directory', function () {
        $processor = new ContentProcessor($this->tempFile);
        $processor->replaceContent('test-tag', 'New content');
        
        $invalidPath = DIRECTORY_SEPARATOR . 'NonExistentDirectory' . DIRECTORY_SEPARATOR . 'output.docx';
        
        expect(fn() => $processor->save($invalidPath))
            ->toThrow(InvalidArgumentException::class, 'Output directory does not exist');
    });

    test('save() closes ZIP archive', function () {
        $processor = new ContentProcessor($this->tempFile);
        $processor->replaceContent('test-tag', 'New content');
        $processor->save();
        
        // Get zipClosed property via reflection
        $reflection = new ReflectionClass($processor);
        $property = $reflection->getProperty('zipClosed');
        $property->setAccessible(true);
        
        expect($property->getValue($processor))->toBeTrue();
    });

    test('save() handles empty outputPath correctly', function () {
        $processor = new ContentProcessor($this->tempFile);
        $processor->replaceContent('test-tag', 'Content');
        
        // Empty string should save in-place
        $processor->save('');
        
        expect(file_exists($this->tempFile))->toBeTrue();
    });

    test('save() persists multiple modifications', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text1 = $section->addText('Content 1');
        $text2 = $section->addText('Content 2');
        $cc->addContentControl($text1, ['tag' => 'tag1']);
        $cc->addContentControl($text2, ['tag' => 'tag2']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        $processor->replaceContent('tag1', 'Modified 1');
        $processor->replaceContent('tag2', 'Modified 2');
        
        $outputFile = tempnam(sys_get_temp_dir(), 'output_') . '.docx';
        $processor->save($outputFile);
        
        // Verify both modifications are saved
        $zip = new ZipArchive();
        $zip->open($outputFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        expect($xml)->toContain('Modified 1');
        expect($xml)->toContain('Modified 2');
        
        unlink($outputFile);
    });
});

describe('ContentProcessor XML Update Operations', function () {
    test('updateXmlInZip replaces existing file', function () {
        $processor = new ContentProcessor($this->tempFile);
        
        // Modify and save
        $processor->replaceContent('test-tag', 'Updated content');
        $processor->save();
        
        // Verify the file was updated
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        expect($xml)->toContain('Updated content');
    });

    test('save() removes XML declaration from updated files', function () {
        $processor = new ContentProcessor($this->tempFile);
        $processor->replaceContent('test-tag', 'Content');
        $processor->save();
        
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        // Should not contain XML declaration
        expect($xml)->not->toContain('<?xml');
    });
});

describe('ContentProcessor Error Handling in Save', function () {
    test('save() persists changes successfully', function () {
        $processor = new ContentProcessor($this->tempFile);
        $processor->replaceContent('test-tag', 'Modified Content');
        
        $processor->save();
        
        // Verify file is still valid
        expect(file_exists($this->tempFile))->toBeTrue();
        
        // Verify modification was persisted
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        expect($xml)->toContain('Modified Content');
    });
});

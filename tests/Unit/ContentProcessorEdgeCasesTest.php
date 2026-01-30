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

describe('ContentProcessor Edge Cases and Additional Coverage', function () {
    test('setValue with multiple text nodes consolidates into first', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $textRun = $section->addTextRun();
        $textRun->addText('First part');
        $textRun->addText(' Second part'); // This creates multiple <w:r> with <w:t>
        
        $cc->addContentControl($textRun, ['tag' => 'multi-text']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        $result = $processor->setValue('multi-text', 'Consolidated');
        
        expect($result)->toBeTrue();
        
        $processor->save();
        
        // Verify consolidation
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        expect($xml)->toContain('Consolidated');
    });

    test('setValue adds xml:space preserve for text with trailing space', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        $processor->setValue('test-tag', 'Value with trailing space ');
        $processor->save();
        
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        expect($xml)->toContain('xml:space="preserve"');
    });

    test('setValue adds xml:space preserve for text with leading space', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        $processor->setValue('test-tag', ' Value with leading space');
        $processor->save();
        
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        expect($xml)->toContain('xml:space="preserve"');
    });

    test('insertTextContent creates proper XML structure', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Original');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        $processor->replaceContent('test-tag', 'New text with special chars: <>&"\'');
        $processor->save();
        
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        // XML entities should be escaped
        expect($xml)->toContain('&lt;');
        expect($xml)->toContain('&gt;');
        expect($xml)->toContain('&amp;');
    });

    test('insertElementContent with Table creates proper structure', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Placeholder');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        // Create a table element
        $table = new \PhpOffice\PhpWord\Element\Table();
        $row = $table->addRow();
        $row->addCell(2000)->addText('Cell 1');
        $row->addCell(2000)->addText('Cell 2');
        
        $processor->replaceContent('test-tag', $table);
        $processor->save();
        
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        expect($xml)->toContain('<w:tbl>');
        expect($xml)->toContain('Cell 1');
        expect($xml)->toContain('Cell 2');
    });

    test('searchSdtInFile returns null for non-existent tag in file', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'existing-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        // Access private method
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('searchSdtInFile');
        $method->setAccessible(true);
        
        // Try to search for non-existent tag in existing file
        $result = $method->invoke($processor, 'word/document.xml', 'non-existent-tag');
        
        expect($result)->toBeNull();
    });

    test('findSdtByTag searches in headers when not found in body', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $header = $section->addHeader();
        $headerText = $header->addText('Header content');
        $cc->addContentControl($headerText, ['tag' => 'header-only']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        // Access private method
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('findSdtByTag');
        $method->setAccessible(true);
        
        $result = $method->invoke($processor, 'header-only');
        
        expect($result)->not->toBeNull();
        expect($result['file'])->toContain('header');
    });

    test('findSdtByTag searches in footers when not found in body or headers', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $footer = $section->addFooter();
        $footerText = $footer->addText('Footer content');
        $cc->addContentControl($footerText, ['tag' => 'footer-only']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('findSdtByTag');
        $method->setAccessible(true);
        
        $result = $method->invoke($processor, 'footer-only');
        
        expect($result)->not->toBeNull();
        expect($result['file'])->toContain('footer');
    });

    test('createXPath registers WordML namespace', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText('Content');
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $reflection = new ReflectionClass($processor);
        $domCacheProperty = $reflection->getProperty('domCache');
        $domCacheProperty->setAccessible(true);
        $domCache = $domCacheProperty->getValue($processor);
        
        $dom = $domCache['word/document.xml'];
        
        $method = $reflection->getMethod('createXPath');
        $method->setAccessible(true);
        
        $xpath = $method->invoke($processor, $dom);
        
        expect($xpath)->toBeInstanceOf(DOMXPath::class);
    });
});

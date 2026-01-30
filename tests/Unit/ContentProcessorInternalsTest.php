<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\ContentProcessor;

beforeEach(function () {
    // Create temporary DOCX file for testing
    $this->tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
});

afterEach(function () {
    if (file_exists($this->tempFile)) {
        unlink($this->tempFile);
    }
});

describe('ContentProcessor XPath Methods', function () {
    test('escapeXPathValue handles simple tags', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'simple-tag', 'alias' => 'Test']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $result = $processor->replaceContent('simple-tag', 'New content');
        expect($result)->toBeTrue();
    });

    test('escapeXPathValue handles tags with hyphens', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag-with-hyphens', 'alias' => 'Test']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $result = $processor->replaceContent('test-tag-with-hyphens', 'New content');
        expect($result)->toBeTrue();
    });

    test('escapeXPathValue handles tags with underscores', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test_tag_underscores', 'alias' => 'Test']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $result = $processor->replaceContent('test_tag_underscores', 'New content');
        expect($result)->toBeTrue();
    });
});

describe('ContentProcessor Header/Footer Discovery', function () {
    test('discoverHeaderFooterFiles finds header files', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $header = $section->addHeader();
        $header->addText('Header content');
        $section->addText('Body content');
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        // Access private method via reflection
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('discoverHeaderFooterFiles');
        $method->setAccessible(true);
        
        $headers = $method->invoke($processor, 'header');
        
        expect($headers)->toBeArray();
        expect(count($headers))->toBeGreaterThan(0);
        expect($headers[0])->toContain('header');
    });

    test('discoverHeaderFooterFiles finds footer files', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $footer = $section->addFooter();
        $footer->addText('Footer content');
        $section->addText('Body content');
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        // Access private method via reflection
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('discoverHeaderFooterFiles');
        $method->setAccessible(true);
        
        $footers = $method->invoke($processor, 'footer');
        
        expect($footers)->toBeArray();
        expect(count($footers))->toBeGreaterThan(0);
        expect($footers[0])->toContain('footer');
    });

    test('discoverHeaderFooterFiles returns empty for no headers', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText('Body content only');
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('discoverHeaderFooterFiles');
        $method->setAccessible(true);
        
        $headers = $method->invoke($processor, 'header');
        
        expect($headers)->toBeArray();
        expect($headers)->toBeEmpty();
    });

    test('discoverHeaderFooterFiles returns empty for no footers', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText('Body content only');
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('discoverHeaderFooterFiles');
        $method->setAccessible(true);
        
        $footers = $method->invoke($processor, 'footer');
        
        expect($footers)->toBeArray();
        expect($footers)->toBeEmpty();
    });
});

describe('ContentProcessor getOrLoadDom', function () {
    test('getOrLoadDom caches DOM instances', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $header = $section->addHeader();
        $text = $header->addText('Header content');
        $cc->addContentControl($text, ['tag' => 'header-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        // Access private method
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('getOrLoadDom');
        $method->setAccessible(true);
        
        // Get cache property
        $cacheProperty = $reflection->getProperty('domCache');
        $cacheProperty->setAccessible(true);
        
        $initialCacheSize = count($cacheProperty->getValue($processor));
        
        // Load a header file
        $method->invoke($processor, 'word/header1.xml');
        
        $newCacheSize = count($cacheProperty->getValue($processor));
        
        // Cache should grow
        expect($newCacheSize)->toBeGreaterThan($initialCacheSize);
    });

    test('getOrLoadDom returns cached DOM on second call', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $header = $section->addHeader();
        $header->addText('Header content');
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('getOrLoadDom');
        $method->setAccessible(true);
        
        // First call
        $dom1 = $method->invoke($processor, 'word/document.xml');
        
        // Second call should return same instance
        $dom2 = $method->invoke($processor, 'word/document.xml');
        
        expect($dom1)->toBe($dom2);
    });
});

describe('ContentProcessor markFileAsModified', function () {
    test('markFileAsModified tracks modified files', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        // Get modifiedFiles property
        $reflection = new ReflectionClass($processor);
        $property = $reflection->getProperty('modifiedFiles');
        $property->setAccessible(true);
        
        expect($property->getValue($processor))->toBeEmpty();
        
        // Modify content
        $processor->replaceContent('test-tag', 'New content');
        
        // Should be tracked now
        $modifiedFiles = $property->getValue($processor);
        expect($modifiedFiles)->not->toBeEmpty();
        expect(isset($modifiedFiles['word/document.xml']))->toBeTrue();
    });

    test('markFileAsModified is called on replaceContent', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $reflection = new ReflectionClass($processor);
        $property = $reflection->getProperty('modifiedFiles');
        $property->setAccessible(true);
        
        $processor->replaceContent('test-tag', 'Modified');
        
        expect(array_key_exists('word/document.xml', $property->getValue($processor)))->toBeTrue();
    });

    test('markFileAsModified is called on appendContent', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $reflection = new ReflectionClass($processor);
        $property = $reflection->getProperty('modifiedFiles');
        $property->setAccessible(true);
        
        $newText = new \PhpOffice\PhpWord\Element\Text('Appended');
        $processor->appendContent('test-tag', $newText);
        
        expect(array_key_exists('word/document.xml', $property->getValue($processor)))->toBeTrue();
    });

    test('markFileAsModified is called on removeContent', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $reflection = new ReflectionClass($processor);
        $property = $reflection->getProperty('modifiedFiles');
        $property->setAccessible(true);
        
        $processor->removeContent('test-tag');
        
        expect(array_key_exists('word/document.xml', $property->getValue($processor)))->toBeTrue();
    });

    test('markFileAsModified is called on setValue', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $reflection = new ReflectionClass($processor);
        $property = $reflection->getProperty('modifiedFiles');
        $property->setAccessible(true);
        
        $processor->setValue('test-tag', 'New value');
        
        expect(array_key_exists('word/document.xml', $property->getValue($processor)))->toBeTrue();
    });
});

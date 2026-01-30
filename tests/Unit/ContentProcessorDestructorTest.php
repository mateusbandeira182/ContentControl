<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\ContentProcessor;

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

describe('ContentProcessor Destructor', function () {
    test('__destruct closes ZIP archive when not explicitly closed', function () {
        $processor = new ContentProcessor($this->tempFile);
        
        // Force destructor call
        unset($processor);
        
        // If ZIP was properly closed, file should not be locked
        // Try to open it again
        $processor2 = new ContentProcessor($this->tempFile);
        expect($processor2)->toBeInstanceOf(ContentProcessor::class);
    });

    test('__destruct handles already closed ZIP gracefully', function () {
        $processor = new ContentProcessor($this->tempFile);
        $processor->save(); // This closes the ZIP
        
        // Destructor should not throw even though ZIP is already closed
        unset($processor);
        expect(true)->toBeTrue(); // Test passes if no exception thrown
    });

    test('__destruct can be called multiple times safely', function () {
        $processor = new ContentProcessor($this->tempFile);
        
        // Manual destructor call
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('__destruct');
        $method->invoke($processor);
        
        // Second call should be safe
        $method->invoke($processor);
        
        expect(true)->toBeTrue();
    });
});

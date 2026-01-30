<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentProcessor;

beforeEach(function () {
    // Create temporary DOCX file for testing
    $this->tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
});

afterEach(function () {
    if (isset($this->tempFile) && file_exists($this->tempFile)) {
        unlink($this->tempFile);
    }
});

describe('ContentProcessor findSdtByTag', function () {
    it('finds SDT by tag in document.xml', function () {
        createDocxWithSdt($this->tempFile, 'customer-name');
        $processor = new ContentProcessor($this->tempFile);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('findSdtByTag');
        $method->setAccessible(true);

        $result = $method->invoke($processor, 'customer-name');

        expect($result)->not->toBeNull()
            ->and($result['file'])->toBe('word/document.xml')
            ->and($result['sdt'])->toBeInstanceOf(\DOMElement::class)
            ->and($result['dom'])->toBeInstanceOf(\DOMDocument::class);
        
        unset($processor);
        if (PHP_OS_FAMILY === 'Windows') {
            usleep(100000);
        }
    });

    it('returns null for non-existent tag', function () {
        createDocxWithSdt($this->tempFile, 'customer-name');
        $processor = new ContentProcessor($this->tempFile);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('findSdtByTag');
        $method->setAccessible(true);

        $result = $method->invoke($processor, 'nonexistent-tag');

        expect($result)->toBeNull();
        
        unset($processor);
        if (PHP_OS_FAMILY === 'Windows') {
            usleep(100000);
        }
    });

    it('handles tags with special characters (apostrophe)', function () {
        $tag = "customer's-id";
        createDocxWithSdt($this->tempFile, $tag);
        $processor = new ContentProcessor($this->tempFile);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('findSdtByTag');
        $method->setAccessible(true);

        $result = $method->invoke($processor, $tag);

        expect($result)->not->toBeNull()
            ->and($result['file'])->toBe('word/document.xml');
        
        unset($processor);
        if (PHP_OS_FAMILY === 'Windows') {
            usleep(100000);
        }
    });

    it('handles tags with spaces', function () {
        $tag = "customer name";
        createDocxWithSdt($this->tempFile, $tag);
        $processor = new ContentProcessor($this->tempFile);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('findSdtByTag');
        $method->setAccessible(true);

        $result = $method->invoke($processor, $tag);

        expect($result)->not->toBeNull()
            ->and($result['file'])->toBe('word/document.xml');
        
        unset($processor);
        if (PHP_OS_FAMILY === 'Windows') {
            usleep(100000);
        }
    });

    it('returns first occurrence when tag is duplicated', function () {
        createDocxWithMultipleSdts($this->tempFile, ['tag1', 'tag2', 'tag1']);
        $processor = new ContentProcessor($this->tempFile);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('findSdtByTag');
        $method->setAccessible(true);

        $result = $method->invoke($processor, 'tag1');

        expect($result)->not->toBeNull()
            ->and($result['file'])->toBe('word/document.xml');
        
        unset($processor);
        if (PHP_OS_FAMILY === 'Windows') {
            usleep(100000);
        }
    });
});

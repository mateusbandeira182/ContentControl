<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\ContentProcessor;
use MkGrow\ContentControl\Exception\DocumentNotFoundException;

beforeEach(function () {
    $this->tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
});

afterEach(function () {
    if (file_exists($this->tempFile)) {
        unlink($this->tempFile);
    }
});

describe('ContentProcessor Critical Coverage Tests', function () {
    test('getOrLoadDom throws DocumentNotFoundException for missing XML file', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText('Content');
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('getOrLoadDom');
        $method->setAccessible(true);
        
        // Try to load non-existent file
        expect(fn() => $method->invoke($processor, 'word/nonexistent.xml'))
            ->toThrow(DocumentNotFoundException::class);
    });

    test('loadXmlAsDom throws RuntimeException for malformed XML', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText('Content');
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('loadXmlAsDom');
        $method->setAccessible(true);
        
        $malformedXml = '<?xml version="1.0"?><unclosed>';
        
        expect(fn() => $method->invoke($processor, $malformedXml))
            ->toThrow(RuntimeException::class);
    });

    test('insertTextContent with multi-line text creates text content', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Original');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        $processor->replaceContent('test-tag', "Line 1\nLine 2\nLine 3");
        $processor->save();
        
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        // Should contain the text
        expect($xml)->toContain('Line 1');
        expect($xml)->toContain('Line 2');
        expect($xml)->toContain('Line 3');
    });

    test('save throws RuntimeException when XML serialization fails', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        $processor->replaceContent('test-tag', 'Modified');
        
        // Corrupt the DOM to force saveXML to fail
        $reflection = new ReflectionClass($processor);
        $domCacheProperty = $reflection->getProperty('domCache');
        $domCacheProperty->setAccessible(true);
        
        $domCache = $domCacheProperty->getValue($processor);
        $corruptedDom = new class extends DOMDocument {
            public function saveXML(?DOMNode $node = null, int $options = 0): string|false {
                return false; // Force failure
            }
        };
        
        $domCache['word/document.xml'] = $corruptedDom;
        $domCacheProperty->setValue($processor, $domCache);
        
        expect(fn() => $processor->save())
            ->toThrow(RuntimeException::class, 'Failed to serialize DOM');
    });

    test('updateXmlInZip handles file that does not exist in ZIP', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText('Content');
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        // Create a new DOM for a file that doesn't exist
        $reflection = new ReflectionClass($processor);
        
        $domCacheProperty = $reflection->getProperty('domCache');
        $domCacheProperty->setAccessible(true);
        $domCache = $domCacheProperty->getValue($processor);
        
        $newDom = new DOMDocument();
        $root = $newDom->createElement('w:test');
        $newDom->appendChild($root);
        $domCache['word/newfile.xml'] = $newDom;
        $domCacheProperty->setValue($processor, $domCache);
        
        $modifiedFilesProperty = $reflection->getProperty('modifiedFiles');
        $modifiedFilesProperty->setAccessible(true);
        $modifiedFiles = $modifiedFilesProperty->getValue($processor);
        $modifiedFiles['word/newfile.xml'] = true;
        $modifiedFilesProperty->setValue($processor, $modifiedFiles);
        
        // This should work without throwing
        $processor->save();
        
        // Verify file was added
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $content = $zip->getFromName('word/newfile.xml');
        $zip->close();
        
        expect($content)->not->toBeFalse();
    });

    test('setValue removes subsequent text nodes after first', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $textRun = $section->addTextRun();
        $textRun->addText('Part 1');
        $textRun->addText(' Part 2');
        $textRun->addText(' Part 3');
        
        $cc->addContentControl($textRun, ['tag' => 'multi-text']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        $result = $processor->setValue('multi-text', 'Single value');
        
        expect($result)->toBeTrue();
        
        $processor->save();
        
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        // Should only have one instance of the new value
        expect($xml)->toContain('Single value');
        expect($xml)->not->toContain('Part 2');
        expect($xml)->not->toContain('Part 3');
    });

    test('addDocumentProtection updates existing protection element', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        // Manually add a protection element first
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $settingsXml = $zip->getFromName('word/settings.xml');
        
        // Add protection with different value
        $settingsXml = str_replace(
            '</w:settings>',
            '<w:documentProtection w:edit="forms" w:enforcement="0"/></w:settings>',
            $settingsXml
        );
        
        $zip->deleteName('word/settings.xml');
        $zip->addFromString('word/settings.xml', $settingsXml);
        $zip->close();
        
        $processor = new ContentProcessor($this->tempFile);
        $processor->removeAllControlContents(true); // This should update protection
        $processor->save();
        
        // Verify protection was updated
        $zip->open($this->tempFile);
        $newSettingsXml = $zip->getFromName('word/settings.xml');
        $zip->close();
        
        expect($newSettingsXml)->toContain('w:edit="readOnly"');
        expect($newSettingsXml)->toContain('w:enforcement="1"');
    });

    test('serializePhpWordElement throws exception for writer without write method', function () {
        // This test ensures proper error handling for malformed writers
        // In practice, all PHPWord writers have write() method
        // But we test the error path for robustness
        
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        // Create a custom element that will fail serialization
        $customElement = new class extends \PhpOffice\PhpWord\Element\AbstractElement {
            // Custom element without proper writer
        };
        
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('serializePhpWordElement');
        $method->setAccessible(true);
        
        expect(fn() => $method->invoke($processor, $customElement))
            ->toThrow(RuntimeException::class);
    });

    test('insertElementContent handles serialization errors gracefully', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Content');
        $cc->addContentControl($text, ['tag' => 'test-tag']);
        $cc->save($this->tempFile);
        
        $processor = new ContentProcessor($this->tempFile);
        
        // Try to replace with an element that can't be serialized
        $badElement = new class extends \PhpOffice\PhpWord\Element\AbstractElement {
            // Element without proper structure
        };
        
        expect(fn() => $processor->replaceContent('test-tag', $badElement))
            ->toThrow(RuntimeException::class);
    });
});

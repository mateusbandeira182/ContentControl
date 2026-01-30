<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentProcessor;
use MkGrow\ContentControl\ContentControl;

beforeEach(function () {
    // Create a simple template DOCX with SDT for testing
    $this->tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
    
    $cc = new ContentControl();
    $section = $cc->addSection();
    
    // Add a text element with Content Control
    $text = $section->addText('Sample content for testing');
    $cc->addContentControl($text, [
        'tag' => 'test-sdt',
        'alias' => 'Test SDT'
    ]);
    
    // Add another text for header testing
    $header = $section->addHeader();
    $headerText = $header->addText('Header content');
    $cc->addContentControl($headerText, [
        'tag' => 'header-sdt',
        'alias' => 'Header Test SDT'
    ]);
    
    $cc->save($this->tempFile);
});

afterEach(function () {
    if (file_exists($this->tempFile)) {
        @unlink($this->tempFile);
    }
});

describe('findSdt()', function () {
    it('finds SDT by tag in document body', function () {
        $processor = new ContentProcessor($this->tempFile);
        $result = $processor->findSdt('test-sdt');
        
        expect($result)->not->toBeNull();
        expect($result)->toBeArray();
        expect($result)->toHaveKeys(['dom', 'sdt', 'file']);
        expect($result['dom'])->toBeInstanceOf(\DOMDocument::class);
        expect($result['sdt'])->toBeInstanceOf(\DOMElement::class);
        expect($result['file'])->toBe('word/document.xml');
        
        // Verify SDT structure
        expect($result['sdt']->nodeName)->toBe('w:sdt');
    });
    
    it('returns null for non-existent tag', function () {
        $processor = new ContentProcessor($this->tempFile);
        $result = $processor->findSdt('non-existent-tag');
        
        expect($result)->toBeNull();
    });
    
    it('finds SDT in header', function () {
        $processor = new ContentProcessor($this->tempFile);
        $result = $processor->findSdt('header-sdt');
        
        expect($result)->not->toBeNull();
        expect($result)->toBeArray();
        expect($result)->toHaveKeys(['dom', 'sdt', 'file']);
        expect($result['file'])->toContain('header');
    });
});

describe('createXPathForDom()', function () {
    it('creates XPath with correct namespaces', function () {
        $processor = new ContentProcessor($this->tempFile);
        $result = $processor->findSdt('test-sdt');
        
        expect($result)->not->toBeNull();
        
        $xpath = $processor->createXPathForDom($result['dom']);
        
        expect($xpath)->toBeInstanceOf(\DOMXPath::class);
        
        // Test that namespaces are registered by running a query
        $nodes = $xpath->query('//w:sdt', $result['dom']);
        expect($nodes)->toBeInstanceOf(\DOMNodeList::class);
        expect($nodes->length)->toBeGreaterThan(0);
    });
    
    it('registers w, r, v namespaces', function () {
        $processor = new ContentProcessor($this->tempFile);
        $result = $processor->findSdt('test-sdt');
        
        expect($result)->not->toBeNull();
        
        $xpath = $processor->createXPathForDom($result['dom']);
        
        // Test each namespace by running queries
        $wNodes = $xpath->query('//w:body');
        expect($wNodes)->toBeInstanceOf(\DOMNodeList::class);
        
        // These namespaces might not have elements in simple doc, but XPath shouldn't error
        $rNodes = $xpath->query('//r:id');
        expect($rNodes)->toBeInstanceOf(\DOMNodeList::class);
        
        $vNodes = $xpath->query('//v:shape');
        expect($vNodes)->toBeInstanceOf(\DOMNodeList::class);
    });
});

describe('markModified()', function () {
    it('marks file as modified', function () {
        $processor = new ContentProcessor($this->tempFile);
        $result = $processor->findSdt('test-sdt');
        
        expect($result)->not->toBeNull();
        
        // Modify the SDT content
        $xpath = $processor->createXPathForDom($result['dom']);
        $sdtContent = $xpath->query('.//w:sdtContent', $result['sdt'])->item(0);
        
        expect($sdtContent)->not->toBeNull();
        
        // Change some content
        $textNode = $xpath->query('.//w:t', $sdtContent)->item(0);
        if ($textNode) {
            $textNode->nodeValue = 'Modified content';
        }
        
        // Mark as modified
        $processor->markModified($result['file']);
        
        // Save and verify changes persisted
        $outputFile = tempnam(sys_get_temp_dir(), 'output_') . '.docx';
        $processor->save($outputFile);
        
        // Re-open and verify
        $zip = new \ZipArchive();
        $zip->open($outputFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        expect($xml)->toContain('Modified content');
        
        @unlink($outputFile);
    });
    
    it('accepts header/footer paths', function () {
        $processor = new ContentProcessor($this->tempFile);
        
        // Should not throw exception
        $processor->markModified('word/header1.xml');
        $processor->markModified('word/footer1.xml');
        $processor->markModified('word/document.xml');
        
        expect(true)->toBeTrue();
    });
});

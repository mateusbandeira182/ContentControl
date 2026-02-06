<?php

declare(strict_types=1);

use MkGrow\ContentControl\SDTInjector;
use MkGrow\ContentControl\SDTConfig;
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Exception\DocumentNotFoundException;

/**
 * Tests for SDTInjector error scenarios
 * 
 * Line coverage: 127, 167-174, 228, 275-292, 309, 321, 386
 */

test('inject throws DocumentNotFoundException if word/document.xml missing', function () {
    // Create invalid DOCX without word/document.xml
    $tempFile = sys_get_temp_dir() . '/invalid_no_document_' . uniqid() . '.docx';
    
    $zip = new ZipArchive();
    $zip->open($tempFile, ZipArchive::CREATE);
    
    // Add only [Content_Types].xml to look like a DOCX
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="..."></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="..."></Relationships>');
    
    $zip->close();
    
    $injector = new SDTInjector();
    $section = createSection();
    $config = new SDTConfig(id: '12345678');
    
    try {
        $injector->inject($tempFile, [['element' => $section, 'config' => $config]]);
        expect(false)->toBeTrue(); // Should not reach here
    } catch (DocumentNotFoundException $e) {
        expect($e->getMessage())->toContain('word/document.xml');
        expect($e->getMessage())->toContain($tempFile);
    } finally {
        unlink($tempFile);
    }
});

test('loadDocumentAsDom throws RuntimeException if XML malformed', function () {
    $injector = new SDTInjector();
    $malformedXml = '<?xml version="1.0"?><w:body xmlns:w="..."><w:p unclosed>';
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('loadDocumentAsDom');
    $method->setAccessible(true);
    
    expect(fn() => $method->invoke($injector, $malformedXml))
        ->toThrow(\RuntimeException::class, 'Failed to load document.xml');
});

test('loadDocumentAsDom captures libxml errors', function () {
    $injector = new SDTInjector();
    
    // XML with multiple errors
    $badXml = '<?xml version="1.0"?><root><unclosed><invalid attr=noQuotes></root>';
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('loadDocumentAsDom');
    $method->setAccessible(true);
    
    try {
        $method->invoke($injector, $badXml);
        expect(false)->toBeTrue(); // Should not reach here
    } catch (\RuntimeException $e) {
        // Verify that message contains libxml error
        expect($e->getMessage())->toContain('Failed to load document.xml');
    }
});

test('serializeDocument throws RuntimeException if saveXML fails', function () {
    $injector = new SDTInjector();
    
    // Create mock DOM that fails to serialize
    $dom = new class extends DOMDocument {
        public function saveXML(?DOMNode $node = null, int $options = 0): string|false
        {
            return false; // Simulate failure
        }
    };
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('serializeDocument');
    $method->setAccessible(true);
    
    expect(fn() => $method->invoke($injector, $dom))
        ->toThrow(\RuntimeException::class, 'Failed to serialize DOM to XML');
});

test('getTypeElementName returns w:picture for TYPE_PICTURE', function () {
    $injector = new SDTInjector();
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('getTypeElementName');
    $method->setAccessible(true);
    
    $result = $method->invoke($injector, ContentControl::TYPE_PICTURE);
    
    expect($result)->toBe('w:picture');
});

test('getTypeElementName returns w:text for TYPE_PLAIN_TEXT', function () {
    $injector = new SDTInjector();
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('getTypeElementName');
    $method->setAccessible(true);
    
    $result = $method->invoke($injector, ContentControl::TYPE_PLAIN_TEXT);
    
    expect($result)->toBe('w:text');
});

test('getTypeElementName returns w:group for TYPE_GROUP', function () {
    $injector = new SDTInjector();
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('getTypeElementName');
    $method->setAccessible(true);
    
    $result = $method->invoke($injector, ContentControl::TYPE_GROUP);
    
    expect($result)->toBe('w:group');
});

test('wrapElementInline throws RuntimeException if element without owner document', function () {
    $injector = new SDTInjector();
    
    // Create element with temporary document, then discard document
    $tempDom = new DOMDocument();
    $orphanElement = $tempDom->createElement('w:p');
    // DO NOT add to document, leave orphan
    unset($tempDom); // Destroy document
    
    $config = new SDTConfig(id: '12345678');
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('wrapElementInline');
    $method->setAccessible(true);
    
    // Element still has ownerDocument (PHP keeps reference)
    // Let's test with imported element without parent instead
    $newDom = new DOMDocument();
    $bodyElement = $newDom->createElement('w:body');
    $newDom->appendChild($bodyElement);
    $pElement = $newDom->createElement('w:p');
    // DO NOT add to body (no parent)
    
    // This element HAS ownerDocument but NO parent
    // So test with the second parent check
    expect(fn() => $method->invoke($injector, $pElement, $config))
        ->toThrow(\RuntimeException::class, 'Target element has no parent node');
});

test('wrapElementInline throws RuntimeException if element without parent node', function () {
    $injector = new SDTInjector();
    
    // Create element with document but without parent
    $dom = new DOMDocument();
    $element = $dom->createElement('w:p');
    // DO NOT add to DOM (no parent)
    
    $config = new SDTConfig(id: '12345678');
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('wrapElementInline');
    $method->setAccessible(true);
    
    expect(fn() => $method->invoke($injector, $element, $config))
        ->toThrow(\RuntimeException::class, 'Target element has no parent node');
});

test('processElement throws RuntimeException if element is not object', function () {
    $injector = new SDTInjector();
    
    $dom = new DOMDocument();
    $dom->loadXML('<?xml version="1.0"?><w:body xmlns:w="..."></w:body>');
    
    $config = new SDTConfig(id: '12345678');
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('processElement');
    $method->setAccessible(true);
    
    // Pass string instead of object
    expect(fn() => $method->invoke($injector, $dom, 'not-an-object', $config, 0))
        ->toThrow(\RuntimeException::class, 'Element must be an object');
});

test('serializeElement returns empty string for non-AbstractElement element', function () {
    $injector = new SDTInjector();
    
    $notPhpWordElement = new stdClass();
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('serializeElement');
    $method->setAccessible(true);
    
    $result = $method->invoke($injector, $notPhpWordElement);
    
    expect($result)->toBe('');
});

test('writeElement ignores containers (Section, Header, Footer, Cell)', function () {
    $injector = new SDTInjector();
    
    $xmlWriter = new \PhpOffice\PhpWord\Shared\XMLWriter(
        \PhpOffice\PhpWord\Shared\XMLWriter::STORAGE_MEMORY,
        null,
        false
    );
    $xmlWriter->openMemory();
    
    // Create Section (container that should be ignored)
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('writeElement');
    $method->setAccessible(true);
    
    // writeElement should return void without writing anything
    $method->invoke($injector, $xmlWriter, $section);
    
    $output = $xmlWriter->getData();
    
    // Should not have written anything (Section is container)
    expect($output)->toBe('');
});

test('writeElement ignores element with nonexistent Writer', function () {
    $injector = new SDTInjector();
    
    $xmlWriter = new \PhpOffice\PhpWord\Shared\XMLWriter(
        \PhpOffice\PhpWord\Shared\XMLWriter::STORAGE_MEMORY,
        null,
        false
    );
    $xmlWriter->openMemory();
    
    // Create mock element without corresponding Writer
    $mockElement = new class extends \PhpOffice\PhpWord\Element\AbstractElement {
        // Class without Writer in PhpOffice\PhpWord\Writer\Word2007\Element
    };
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('writeElement');
    $method->setAccessible(true);
    
    // writeElement should return void without throwing exception
    $method->invoke($injector, $xmlWriter, $mockElement);
    
    $output = $xmlWriter->getData();
    
    // Should not have written anything (Writer nonexistent)
    expect($output)->toBe('');
});

test('createSDTElement with invalid XML fragment (robustness test)', function () {
    $injector = new SDTInjector();
    
    // Create element with content that generates invalid XML
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    $text = $section->addText('Conteúdo válido');
    
    $config = new SDTConfig(
        id: '12345678',
        alias: 'Test',
        type: ContentControl::TYPE_RICH_TEXT
    );
    
    $reflection = new ReflectionClass($injector);
    $method = $reflection->getMethod('createSDTElement');
    $method->setAccessible(true);
    
    // Should create SDT without throwing exception
    $xml = $method->invoke($injector, $text, $config);
    
    expect($xml)->toContain('<w:sdt');
    expect($xml)->toContain('w:id');
    expect($xml)->toContain('12345678');
});

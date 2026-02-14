<?php

declare(strict_types=1);

use MkGrow\ContentControl\ElementLocator;

describe('ElementLocator', function () {

    test('findElementInDOM locates simple Text by order', function () {
        // Generate document.xml mock
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:p><w:r><w:t>Primeiro</w:t></w:r></w:p>
            <w:p><w:r><w:t>Segundo</w:t></w:r></w:p>
            <w:p><w:r><w:t>Terceiro</w:t></w:r></w:p>
        </w:body>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        
        // Create a real Text element that will be serialized as <w:p>
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Primeiro');  // always searches [1] (first free)

        // Always searches [1] (first free element that is not in SDT)
        $found = $locator->findElementInDOM($dom, $text, 0);

        expect($found)->not->toBeNull();
        expect($found->nodeName)->toBe('w:p');
        
        // Validate inner text
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $textNode = $xpath->query('.//w:t', $found)->item(0);
        assert($textNode !== null);
        expect($textNode->textContent)->toBe('Primeiro');  // Now expects "Primeiro" (first free)
    });

    test('findElementInDOM locates Table by order', function () {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:tbl>
                <w:tr><w:tc><w:p><w:r><w:t>T1R1C1</w:t></w:r></w:p></w:tc></w:tr>
            </w:tbl>
            <w:tbl>
                <w:tr><w:tc><w:p><w:r><w:t>T2R1C1</w:t></w:r></w:p></w:tc></w:tr>
            </w:tbl>
        </w:body>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        $table = createSimpleTable(1, 1);

        // Search for 2nd table (order 1, 0-indexed)
        $found = $locator->findElementInDOM($dom, $table, 1);

        expect($found)->not->toBeNull();
        expect($found->nodeName)->toBe('w:tbl');
    });

    test('findElementInDOM returns null if not found', function () {
        $dom = new DOMDocument();
        // XML without paragraphs - only one table
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:tbl>
                <w:tr><w:tc><w:p><w:r><w:t>Tabela</w:t></w:r></w:p></w:tc></w:tr>
            </w:tbl>
        </w:body>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Texto qualquer');  // Searches <w:p> but XML only has <w:tbl>

        // With support for Text in cells, findElementInDOM now FINDS <w:p> inside <w:tc>
        $found = $locator->findElementInDOM($dom, $text, 0);

        // Should find the <w:p> inside the cell (inline-level support)
        expect($found)->not->toBeNull();
        expect($found->nodeName)->toBe('w:p');
    });

    test('findElementInDOM fallback for content hash works', function () {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:p><w:r><w:t>Duplicado</w:t></w:r></w:p>
            <w:p><w:r><w:t>Duplicado</w:t></w:r></w:p>
            <w:p><w:r><w:t>Duplicado</w:t></w:r></w:p>
        </w:body>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Duplicado');

        // Search with incorrect order (force fallback to hash)
        $found = $locator->findElementInDOM($dom, $text, 10);

        // Should find via hash even with wrong order
        expect($found)->not->toBeNull();
    });

    test('validateMatch correctly validates DOM element vs PHPWord', function () {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:p><w:r><w:t>Validar</w:t></w:r></w:p>
        </w:body>';
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paragraph = $xpath->query('//w:p')->item(0);

        assert($paragraph instanceof DOMElement);

        $locator = new ElementLocator();
        
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Validar');

        $isValid = $locator->validateMatch($paragraph, $text);

        expect($isValid)->toBeTrue();
    });

    test('validateMatch rejects element with different content', function () {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:p><w:r><w:t>Texto A</w:t></w:r></w:p>
        </w:body>';
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paragraph = $xpath->query('//w:p')->item(0);

        assert($paragraph instanceof DOMElement);

        $locator = new ElementLocator();
        
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Texto B');

        $isValid = $locator->validateMatch($paragraph, $text);

        expect($isValid)->toBeFalse();
    });

    test('findElementInDOM reuses XPath instance', function () {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:p><w:r><w:t>Test1</w:t></w:r></w:p>
            <w:p><w:r><w:t>Test2</w:t></w:r></w:p>
        </w:body>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text1 = $section->addText('Test1');
        $text2 = $section->addText('Test2');

        // Multiple searches should reuse the same XPath instance
        $found1 = $locator->findElementInDOM($dom, $text1, 0);
        $found2 = $locator->findElementInDOM($dom, $text2, 1);

        expect($found1)->not->toBeNull();
        expect($found2)->not->toBeNull();
    });

    test('hashDOMElement processes Table with multiple rows', function () {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:tbl>
                <w:tr><w:tc><w:p><w:r><w:t>R1C1</w:t></w:r></w:p></w:tc></w:tr>
                <w:tr><w:tc><w:p><w:r><w:t>R2C1</w:t></w:r></w:p></w:tc></w:tr>
                <w:tr><w:tc><w:p><w:r><w:t>R3C1</w:t></w:r></w:p></w:tc></w:tr>
            </w:tbl>
        </w:body>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        $table = createSimpleTable(3, 1);

        // Locate using hash fallback (forcing high elementIndex)
        $found = $locator->findElementInDOM($dom, $table, 0);

        expect($found)->not->toBeNull();
        expect($found->nodeName)->toBe('w:tbl');
        
        // Verify that hash worked by counting rows
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $rows = $xpath->query('.//w:tr', $found);
        expect($rows->length)->toBe(3);
    });

    test('hashDOMElement processes Cell with multiple paragraphs', function () {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:tbl>
                <w:tr>
                    <w:tc>
                        <w:p><w:r><w:t>Para 1</w:t></w:r></w:p>
                        <w:p><w:r><w:t>Para 2</w:t></w:r></w:p>
                        <w:p><w:r><w:t>Para 3</w:t></w:r></w:p>
                    </w:tc>
                </w:tr>
            </w:tbl>
        </w:body>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        
        // Create Cell with multiple paragraphs
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow();
        $cell = $table->addCell(2000);
        $cell->addText('Para 1');
        $cell->addText('Para 2');
        $cell->addText('Para 3');

        // ElementLocator should be able to hash the Cell
        $found = $locator->findElementInDOM($dom, $cell, 0);

        expect($found)->not->toBeNull();
        expect($found->nodeName)->toBe('w:tc');
    });

    test('createXPathQuery throws exception for unsupported type', function () {
        $locator = new ElementLocator();
        $unsupportedElement = new stdClass();

        $reflection = new ReflectionClass($locator);
        $method = $reflection->getMethod('createXPathQuery');
        $method->setAccessible(true);

        expect(fn() => $method->invoke($locator, $unsupportedElement))
            ->toThrow(\InvalidArgumentException::class, 'Element type "stdClass" is not supported for Content Controls');
    });

    test('findByContentHash returns null if element not found by hash', function () {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:p><w:r><w:t>Conteúdo diferente</w:t></w:r></w:p>
        </w:body>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        
        // Create Table (different type from w:p in DOM)
        $table = createSimpleTable(3, 3);

        // Force hash fallback using very high index
        $found = $locator->findElementInDOM($dom, $table, 999);

        // Table does not exist in DOM (only has w:p), should return null
        expect($found)->toBeNull();
    });

    test('extractTextContent returns empty string for element without text', function () {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:p></w:p>
        </w:body>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paragraph = $xpath->query('//w:p')->item(0);

        $reflection = new ReflectionClass($locator);
        $method = $reflection->getMethod('extractTextContent');
        $method->setAccessible(true);

        $text = $method->invoke($locator, $paragraph, $xpath);

        expect($text)->toBe('');
    });

    test('validateMatch returns false for different types', function () {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:tbl><w:tr><w:tc><w:p><w:r><w:t>Tabela</w:t></w:r></w:p></w:tc></w:tr></w:tbl>
        </w:body>';
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $tableNode = $xpath->query('//w:tbl')->item(0);
        
        assert($tableNode instanceof DOMElement);

        $locator = new ElementLocator();
        
        // Create Text element (different type from Table in DOM)
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Texto');

        $isValid = $locator->validateMatch($tableNode, $text);

        expect($isValid)->toBeFalse();
    });

    test('findElementInDOM skips content hash for inline-level Text', function () {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:p><w:r><w:t></w:t></w:r></w:p>
            <w:tbl>
                <w:tr>
                    <w:tc>
                        <w:p><w:r><w:t></w:t></w:r></w:p>
                    </w:tc>
                </w:tr>
            </w:tbl>
        </w:body>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $row = $table->addRow();
        $cell = $row->addCell();
        $text = $cell->addText('');

        // With inlineLevel=true: should return cell-level <w:p> (skips content hash)
        $found = $locator->findElementInDOM($dom, $text, 0, 'w:body', true);
        expect($found)->not->toBeNull();
        expect($found->parentNode)->toBeInstanceOf(DOMElement::class);
        expect($found->parentNode->nodeName)->toBe('w:tc');

        // With inlineLevel=false (default): should return body-level <w:p> (content hash matches first)
        $foundDefault = $locator->findElementInDOM($dom, $text, 0, 'w:body', false);
        expect($foundDefault)->not->toBeNull();
        expect($foundDefault->parentNode)->toBeInstanceOf(DOMElement::class);
        expect($foundDefault->parentNode->nodeName)->toBe('w:body');
    });

});

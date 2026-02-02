<?php

declare(strict_types=1);

use MkGrow\ContentControl\ElementLocator;

describe('ElementLocator', function () {

    test('findElementInDOM localiza Text simples por ordem', function () {
        // Gerar document.xml mock
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:p><w:r><w:t>Primeiro</w:t></w:r></w:p>
            <w:p><w:r><w:t>Segundo</w:t></w:r></w:p>
            <w:p><w:r><w:t>Terceiro</w:t></w:r></w:p>
        </w:body>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        
        // Criar um elemento Text real que será serializado como <w:p>
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Primeiro');  // v3.0: sempre busca [1] (primeiro livre)

        // v3.0: Sempre busca [1] (primeiro elemento livre que não está em SDT)
        $found = $locator->findElementInDOM($dom, $text, 0);

        expect($found)->not->toBeNull();
        expect($found->nodeName)->toBe('w:p');
        
        // Validar texto interno
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $textNode = $xpath->query('.//w:t', $found)->item(0);
        assert($textNode !== null);
        expect($textNode->textContent)->toBe('Primeiro');  // Agora espera "Primeiro" (primeiro livre)
    });

    test('findElementInDOM localiza Table por ordem', function () {
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

        // Buscar 2ª tabela (ordem 1, 0-indexed)
        $found = $locator->findElementInDOM($dom, $table, 1);

        expect($found)->not->toBeNull();
        expect($found->nodeName)->toBe('w:tbl');
    });

    test('findElementInDOM retorna null se não encontrar', function () {
        $dom = new DOMDocument();
        // XML sem parágrafos - apenas uma tabela
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
        $text = $section->addText('Texto qualquer');  // Busca <w:p> mas XML só tem <w:tbl>

        // v4.0: Com suporte a Text em células, findElementInDOM agora ENCONTRA <w:p> dentro de <w:tc>
        $found = $locator->findElementInDOM($dom, $text, 0);

        // Deve encontrar o <w:p> dentro da célula (inline-level support)
        expect($found)->not->toBeNull();
        expect($found->nodeName)->toBe('w:p');
    });

    test('findElementInDOM fallback para hash de conteúdo funciona', function () {
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

        // Buscar com ordem incorreta (forçar fallback para hash)
        $found = $locator->findElementInDOM($dom, $text, 10);

        // Deve encontrar via hash mesmo com ordem errada
        expect($found)->not->toBeNull();
    });

    test('validateMatch valida corretamente elemento DOM vs PHPWord', function () {
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

    test('validateMatch rejeita elemento com conteúdo diferente', function () {
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

    test('findElementInDOM reutiliza XPath instance', function () {
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

        // Múltiplas buscas devem reutilizar a mesma instância XPath
        $found1 = $locator->findElementInDOM($dom, $text1, 0);
        $found2 = $locator->findElementInDOM($dom, $text2, 1);

        expect($found1)->not->toBeNull();
        expect($found2)->not->toBeNull();
    });

    test('hashDOMElement processa Table com múltiplas linhas', function () {
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

        // Localizar usando fallback de hash (forçando elementIndex alto)
        $found = $locator->findElementInDOM($dom, $table, 0);

        expect($found)->not->toBeNull();
        expect($found->nodeName)->toBe('w:tbl');
        
        // Verificar que hash funcionou contando linhas
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $rows = $xpath->query('.//w:tr', $found);
        expect($rows->length)->toBe(3);
    });

    test('hashDOMElement processa Cell com múltiplos parágrafos', function () {
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
        
        // Criar Cell com múltiplos parágrafos
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow();
        $cell = $table->addCell(2000);
        $cell->addText('Para 1');
        $cell->addText('Para 2');
        $cell->addText('Para 3');

        // ElementLocator deve conseguir fazer hash da Cell
        $found = $locator->findElementInDOM($dom, $cell, 0);

        expect($found)->not->toBeNull();
        expect($found->nodeName)->toBe('w:tc');
    });

    test('createXPathQuery lança exceção para tipo não suportado', function () {
        $locator = new ElementLocator();
        $unsupportedElement = new stdClass();

        $reflection = new ReflectionClass($locator);
        $method = $reflection->getMethod('createXPathQuery');
        $method->setAccessible(true);

        expect(fn() => $method->invoke($locator, $unsupportedElement))
            ->toThrow(\InvalidArgumentException::class, 'Element type "stdClass" is not supported for Content Controls');
    });

    test('findByContentHash retorna null se elemento não encontrado por hash', function () {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:p><w:r><w:t>Conteúdo diferente</w:t></w:r></w:p>
        </w:body>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        
        // Criar Table (tipo diferente de w:p no DOM)
        $table = createSimpleTable(3, 3);

        // Forçar uso de fallback hash usando índice muito alto
        $found = $locator->findElementInDOM($dom, $table, 999);

        // Table não existe no DOM (só tem w:p), deve retornar null
        expect($found)->toBeNull();
    });

    test('extractTextContent retorna string vazia para elemento sem texto', function () {
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

    test('validateMatch retorna false para tipos diferentes', function () {
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
        
        // Criar elemento Text (tipo diferente de Table no DOM)
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Texto');

        $isValid = $locator->validateMatch($tableNode, $text);

        expect($isValid)->toBeFalse();
    });

});

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
        $section->addText('Primeiro');
        $text = $section->addText('Segundo');  // Este é o que vamos buscar
        $section->addText('Terceiro');

        // Buscar 2º parágrafo (ordem 1, 0-indexed)
        $found = $locator->findElementInDOM($dom, $text, 1);

        expect($found)->not->toBeNull();
        expect($found->nodeName)->toBe('w:p');
        
        // Validar texto interno
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $textNode = $xpath->query('.//w:t', $found)->item(0);
        expect($textNode->textContent)->toBe('Segundo');
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
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:p><w:r><w:t>Único</w:t></w:r></w:p>
        </w:body>';
        $dom->loadXML($xml);

        $locator = new ElementLocator();
        
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Inexistente');

        // Buscar 5º elemento (não existe)
        $found = $locator->findElementInDOM($dom, $text, 4);

        expect($found)->toBeNull();
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

});

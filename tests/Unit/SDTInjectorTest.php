<?php

declare(strict_types=1);

use MkGrow\ContentControl\SDTInjector;
use MkGrow\ContentControl\SDTConfig;
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Exception\ZipArchiveException;
use MkGrow\ContentControl\Exception\DocumentNotFoundException;
use PhpOffice\PhpWord\PhpWord;

describe('SDTInjector - SDT XML Generation', function () {
    test('creates complete <w:sdt> element', function () {
        $injector = new SDTInjector();
        $section = createSectionWithText('Test content');
        $config = new SDTConfig(
            id: '12345678',
            alias: 'Test Control',
            tag: 'test-tag',
            type: ContentControl::TYPE_RICH_TEXT
        );

        // Use reflection to call private method
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('createSDTElement');
        $method->setAccessible(true);

        $xml = $method->invoke($injector, $section, $config);

        expect($xml)->toContain('<w:sdt>');
        expect($xml)->toContain('<w:sdtPr>');
        expect($xml)->toContain('<w:sdtContent>');
    });

    test('includes ID in XML', function () {
        $injector = new SDTInjector();
        $section = createSection();
        $config = new SDTConfig(id: '87654321');

        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('createSDTElement');
        $method->setAccessible(true);

        $xml = $method->invoke($injector, $section, $config);

        expect($xml)->toContain('<w:id');
        expect($xml)->toContain('w:val="87654321"');
    });

    test('includes alias when provided', function () {
        $injector = new SDTInjector();
        $section = createSection();
        $config = new SDTConfig(id: '12345678', alias: 'Test Alias');

        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('createSDTElement');
        $method->setAccessible(true);

        $xml = $method->invoke($injector, $section, $config);

        expect($xml)->toContain('<w:alias');
        expect($xml)->toContain('w:val="Test Alias"');
    });

    test('omits alias when empty', function () {
        $injector = new SDTInjector();
        $section = createSection();
        $config = new SDTConfig(id: '12345678', alias: '');

        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('createSDTElement');
        $method->setAccessible(true);

        $xml = $method->invoke($injector, $section, $config);

        expect($xml)->not->toContain('<w:alias');
    });

    test('includes tag when provided', function () {
        $injector = new SDTInjector();
        $section = createSection();
        $config = new SDTConfig(id: '12345678', tag: 'test-tag');

        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('createSDTElement');
        $method->setAccessible(true);

        $xml = $method->invoke($injector, $section, $config);

        expect($xml)->toContain('<w:tag');
        expect($xml)->toContain('w:val="test-tag"');
    });

    test('includes richText type', function () {
        $injector = new SDTInjector();
        $section = createSection();
        $config = new SDTConfig(id: '12345678', type: ContentControl::TYPE_RICH_TEXT);

        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('createSDTElement');
        $method->setAccessible(true);

        $xml = $method->invoke($injector, $section, $config);

        expect($xml)->toContain('<w:richText');
    });

    test('includes plainText type', function () {
        $injector = new SDTInjector();
        $section = createSection();
        $config = new SDTConfig(id: '12345678', type: ContentControl::TYPE_PLAIN_TEXT);

        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('createSDTElement');
        $method->setAccessible(true);

        $xml = $method->invoke($injector, $section, $config);

        expect($xml)->toContain('<w:text');
    });

    test('includes lockType when provided', function () {
        $injector = new SDTInjector();
        $section = createSection();
        $config = new SDTConfig(id: '12345678', lockType: ContentControl::LOCK_SDT_LOCKED);

        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('createSDTElement');
        $method->setAccessible(true);

        $xml = $method->invoke($injector, $section, $config);

        expect($xml)->toContain('<w:lock');
        expect($xml)->toContain('w:val="sdtLocked"');
    });

    test('omits lock when LOCK_NONE', function () {
        $injector = new SDTInjector();
        $section = createSection();
        $config = new SDTConfig(id: '12345678', lockType: ContentControl::LOCK_NONE);

        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('createSDTElement');
        $method->setAccessible(true);

        $xml = $method->invoke($injector, $section, $config);

        expect($xml)->not->toContain('<w:lock');
    });
});

describe('SDTInjector - Element Serialization', function () {
    test('serializes Text element', function () {
        $injector = new SDTInjector();
        $section = createSectionWithText('Test text content');
        $config = new SDTConfig(id: '12345678');

        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('createSDTElement');
        $method->setAccessible(true);

        $xml = $method->invoke($injector, $section, $config);

        expect($xml)->toContain('Test text content');
        expect($xml)->toContain('<w:t');
    });

    test('serializes Table element', function () {
        $injector = new SDTInjector();
        $section = createSection();
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Cell content');

        $config = new SDTConfig(id: '12345678');

        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('createSDTElement');
        $method->setAccessible(true);

        $xml = $method->invoke($injector, $section, $config);

        expect($xml)->toContain('<w:tbl');
        expect($xml)->toContain('Cell content');
    });
});

describe('SDTInjector - Injection into DOCX', function () {
    test('injects SDT into DOCX file', function () {
        // Create base DOCX with Text element
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $textElement = $section->addText('SDT content to protect');

        $tempFile = sys_get_temp_dir() . '/test_inject_' . uniqid() . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        // Create SDT to inject (using Text element, not Section)
        $config = new SDTConfig(id: '12345678', alias: 'Test SDT');

        $tuples = [
            ['element' => $textElement, 'config' => $config]
        ];

        // Inject
        $injector = new SDTInjector();
        $injector->inject($tempFile, $tuples);

        // Verify result
        $zip = new \ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('<w:sdt>');
        expect($xml)->toContain('Test SDT');
        expect($xml)->toContain('SDT content to protect');

        unlink($tempFile);
    });

    test('injects multiple SDTs', function () {
        // Create base DOCX with multiple Text elements
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $text1 = $section->addText('SDT 1 content');
        $text2 = $section->addText('SDT 2 content');

        $tempFile = sys_get_temp_dir() . '/test_multi_inject_' . uniqid() . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        // Create multiple SDTs (using Text elements)
        $tuples = [
            [
                'element' => $text1,
                'config' => new SDTConfig(id: '12345678', alias: 'First')
            ],
            [
                'element' => $text2,
                'config' => new SDTConfig(id: '87654321', alias: 'Second')
            ],
        ];

        // Inject
        $injector = new SDTInjector();
        $injector->inject($tempFile, $tuples);

        // Verify result
        $zip = new \ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('First');
        expect($xml)->toContain('Second');
        expect($xml)->toContain('SDT 1');
        expect($xml)->toContain('SDT 2');

        // Count <w:sdt> occurrences
        expect($xml)->toBeString();
        assert(is_string($xml)); // PHPStan type narrowing
        $count = substr_count($xml, '<w:sdt>');
        expect($count)->toBe(2);

        unlink($tempFile);
    });

    test('throws ZipArchiveException if file does not exist', function () {
        $injector = new SDTInjector();

        expect(fn() => $injector->inject('/path/nonexistent.docx', []))
            ->toThrow(ZipArchiveException::class);
    });

    test('injects before </w:body>', function () {
        // Create base DOCX with Text element
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Before SDT');
        $textToProtect = $section->addText('SDT content to protect');

        $tempFile = sys_get_temp_dir() . '/test_position_' . uniqid() . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        // Inject SDT (wrapping existing element)
        $tuples = [
            [
                'element' => $textToProtect,
                'config' => new SDTConfig(id: '12345678')
            ]
        ];

        $injector = new SDTInjector();
        $injector->inject($tempFile, $tuples);

        // Verify position
        $zip = new \ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toBeString();
        assert(is_string($xml)); // PHPStan type narrowing
        $sdtPos = strpos($xml, '<w:sdt>');
        $bodyClosePos = strpos($xml, '</w:body>');

        expect($sdtPos)->not->toBeFalse();
        expect($bodyClosePos)->not->toBeFalse();
        assert(is_int($sdtPos) && is_int($bodyClosePos)); // PHPStan type narrowing
        expect($sdtPos)->toBeLessThan($bodyClosePos);

        unlink($tempFile);
    });
});

describe('SDTInjector - Methods (DOM Inline Wrapping)', function () {

    test('wrapElementInline moves element without duplicating', function () {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:p><w:r><w:t>Original</w:t></w:r></w:p>
        </w:body>';
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        
        $paragraph = $xpath->query('//w:p')->item(0);
        
        // Debug: validate that paragraph was found
        expect($paragraph)->not->toBeNull();

        $injector = new SDTInjector();
        $config = new SDTConfig(id: '12345678', alias: 'Test');

        // Call private method via Reflection
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('wrapElementInline');
        $method->setAccessible(true);
        $method->invoke($injector, $paragraph, $config);
        
        // Validate structure
        $sdt = $xpath->query('//w:sdt')->item(0);
        expect($sdt)->not->toBeNull();

        // Validate content inside SDT
        $wrappedParagraph = $xpath->query('//w:sdt/w:sdtContent/w:p')->item(0);
        expect($wrappedParagraph)->not->toBeNull();
        assert($wrappedParagraph !== null);
        expect($wrappedParagraph->textContent)->toBe('Original');

        // Validate that there is NO orphan paragraph outside SDT
        $orphanParagraphs = $xpath->query('//w:body/w:p');
        expect($orphanParagraphs->length)->toBe(0);
    });

    test('wrapElementInline creates correct SDT properties', function () {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:p><w:r><w:t>Test</w:t></w:r></w:p>
        </w:body>';
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        
        $paragraph = $xpath->query('//w:p')->item(0);

        $injector = new SDTInjector();
        $config = new SDTConfig(
            id: '87654321',
            alias: 'Alias Test',
            tag: 'tag-test',
            lockType: ContentControl::LOCK_SDT_LOCKED
        );

        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('wrapElementInline');
        $method->setAccessible(true);
        $method->invoke($injector, $paragraph, $config);

        // Validate properties
        $idNode = $xpath->query('//w:sdt/w:sdtPr/w:id')->item(0);
        assert($idNode instanceof DOMElement);
        expect($idNode->getAttribute('w:val'))->toBe('87654321');

        $aliasNode = $xpath->query('//w:sdt/w:sdtPr/w:alias')->item(0);
        assert($aliasNode instanceof DOMElement);
        expect($aliasNode->getAttribute('w:val'))->toBe('Alias Test');

        $tagNode = $xpath->query('//w:sdt/w:sdtPr/w:tag')->item(0);
        assert($tagNode instanceof DOMElement);
        expect($tagNode->getAttribute('w:val'))->toBe('tag-test');

        $lockNode = $xpath->query('//w:sdt/w:sdtPr/w:lock')->item(0);
        assert($lockNode instanceof DOMElement);
        expect($lockNode->getAttribute('w:val'))->toBe('sdtLocked');
    });

    test('isElementProcessed detects already processed elements', function () {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:p><w:r><w:t>Test</w:t></w:r></w:p>
        </w:body>';
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        
        $paragraph = $xpath->query('//w:p')->item(0);

        $injector = new SDTInjector();

        $reflection = new ReflectionClass($injector);
        
        // Mark as processed
        $markMethod = $reflection->getMethod('markElementAsProcessed');
        $markMethod->setAccessible(true);
        $markMethod->invoke($injector, $paragraph);

        // Verify
        $isProcessedMethod = $reflection->getMethod('isElementProcessed');
        $isProcessedMethod->setAccessible(true);
        $isProcessed = $isProcessedMethod->invoke($injector, $paragraph);

        expect($isProcessed)->toBeTrue();
    });

    test('wrapElementInline throws exception if element without owner document', function () {
        $injector = new SDTInjector();
        $config = new SDTConfig(id: '12345678');

        // Create document but do not add the element to the document
        $doc = new DOMDocument();
        $element = $doc->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:p');
        // DO NOT add to document, leave orphan
        
        // Element has ownerDocument but no parent
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('wrapElementInline');
        $method->setAccessible(true);

        expect(fn() => $method->invoke($injector, $element, $config))
            ->toThrow(\RuntimeException::class, 'Target element has no parent node');
    });

});
describe('SDTInjector - Depth Sorting', function () {
    test('sorts Cell before Table', function () {
        // Create PHPWord elements
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow();
        $cell = $table->addCell(2000);
        $cell->addText('Cell content');

        // Create configs
        $cellConfig = new SDTConfig(id: '11111111', alias: 'Cell Control');
        $tableConfig = new SDTConfig(id: '22222222', alias: 'Table Control');

        // Unsorted array (Table first)
        $sdtTuples = [
            ['element' => $table, 'config' => $tableConfig],
            ['element' => $cell, 'config' => $cellConfig],
        ];

        // Use reflection to call sortElementsByDepth
        $injector = new SDTInjector();
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('sortElementsByDepth');
        $method->setAccessible(true);

        $sorted = $method->invoke($injector, $sdtTuples);

        assert(is_array($sorted));
        /** @var array<int, array{element: mixed, config: SDTConfig}> $sorted */

        // Check order: Cell (depth 3) should come before Table (depth 1)
        expect($sorted[0]['element'])->toBe($cell);
        expect($sorted[1]['element'])->toBe($table);
    });

    test('getElementDepth returns correct values', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow();
        $cell = $table->addCell(2000);
        $text = $section->addText('Text');

        $injector = new SDTInjector();
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('getElementDepth');
        $method->setAccessible(true);

        expect($method->invoke($injector, $cell))->toBe(3);
        expect($method->invoke($injector, $table))->toBe(1);
        expect($method->invoke($injector, $section))->toBe(1);
        expect($method->invoke($injector, $text))->toBe(1);
    });

    test('sorts multiple elements correctly', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $table1 = $section->addTable();
        $table1->addRow();
        $cell1 = $table1->addCell(2000);
        $table2 = $section->addTable();
        $table2->addRow();
        $cell2 = $table2->addCell(2000);
        $text = $section->addText('Text');

        // Unsorted array
        $sdtTuples = [
            ['element' => $table1, 'config' => new SDTConfig(id: '11111111')],
            ['element' => $cell1, 'config' => new SDTConfig(id: '22222222')],
            ['element' => $text, 'config' => new SDTConfig(id: '33333333')],
            ['element' => $table2, 'config' => new SDTConfig(id: '44444444')],
            ['element' => $cell2, 'config' => new SDTConfig(id: '55555555')],
        ];

        $injector = new SDTInjector();
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('sortElementsByDepth');
        $method->setAccessible(true);

        $sorted = $method->invoke($injector, $sdtTuples);

        assert(is_array($sorted));
        /** @var array<int, array{element: mixed, config: SDTConfig}> $sorted */

        // First elements should be Cells (depth 3)
        expect($sorted[0]['element'])->toBeInstanceOf(\PhpOffice\PhpWord\Element\Cell::class);
        expect($sorted[1]['element'])->toBeInstanceOf(\PhpOffice\PhpWord\Element\Cell::class);
        
        // Last 3 elements should be Tables or Text (depth 1)
        // Order among them is not guaranteed (same depth)
        $depths = [
            $sorted[2]['element'] instanceof \PhpOffice\PhpWord\Element\Table ||
            $sorted[2]['element'] instanceof \PhpOffice\PhpWord\Element\Text,
            $sorted[3]['element'] instanceof \PhpOffice\PhpWord\Element\Table ||
            $sorted[3]['element'] instanceof \PhpOffice\PhpWord\Element\Text,
            $sorted[4]['element'] instanceof \PhpOffice\PhpWord\Element\Table ||
            $sorted[4]['element'] instanceof \PhpOffice\PhpWord\Element\Text,
        ];
        
        expect($depths)->each->toBeTrue();
    });
});
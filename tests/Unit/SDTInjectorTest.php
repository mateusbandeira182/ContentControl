<?php

declare(strict_types=1);

use MkGrow\ContentControl\SDTInjector;
use MkGrow\ContentControl\SDTConfig;
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Exception\ZipArchiveException;
use MkGrow\ContentControl\Exception\DocumentNotFoundException;
use PhpOffice\PhpWord\PhpWord;

describe('SDTInjector - Geração de XML SDT', function () {
    test('cria elemento <w:sdt> completo', function () {
        $injector = new SDTInjector();
        $section = createSectionWithText('Test content');
        $config = new SDTConfig(
            id: '12345678',
            alias: 'Test Control',
            tag: 'test-tag',
            type: ContentControl::TYPE_RICH_TEXT
        );

        // Usar reflexão para chamar método privado
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('createSDTElement');
        $method->setAccessible(true);

        $xml = $method->invoke($injector, $section, $config);

        expect($xml)->toContain('<w:sdt>');
        expect($xml)->toContain('<w:sdtPr>');
        expect($xml)->toContain('<w:sdtContent>');
    });

    test('inclui ID no XML', function () {
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

    test('inclui alias quando fornecido', function () {
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

    test('omite alias quando vazio', function () {
        $injector = new SDTInjector();
        $section = createSection();
        $config = new SDTConfig(id: '12345678', alias: '');

        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('createSDTElement');
        $method->setAccessible(true);

        $xml = $method->invoke($injector, $section, $config);

        expect($xml)->not->toContain('<w:alias');
    });

    test('inclui tag quando fornecida', function () {
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

    test('inclui tipo richText', function () {
        $injector = new SDTInjector();
        $section = createSection();
        $config = new SDTConfig(id: '12345678', type: ContentControl::TYPE_RICH_TEXT);

        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('createSDTElement');
        $method->setAccessible(true);

        $xml = $method->invoke($injector, $section, $config);

        expect($xml)->toContain('<w:richText');
    });

    test('inclui tipo plainText', function () {
        $injector = new SDTInjector();
        $section = createSection();
        $config = new SDTConfig(id: '12345678', type: ContentControl::TYPE_PLAIN_TEXT);

        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('createSDTElement');
        $method->setAccessible(true);

        $xml = $method->invoke($injector, $section, $config);

        expect($xml)->toContain('<w:text');
    });

    test('inclui lockType quando fornecido', function () {
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

    test('omite lock quando LOCK_NONE', function () {
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

describe('SDTInjector - Serialização de elementos', function () {
    test('serializa Text element', function () {
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

    test('serializa Table element', function () {
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

describe('SDTInjector - Injeção em DOCX', function () {
    test('injeta SDT em arquivo DOCX', function () {
        // Criar DOCX base com elemento Text
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $textElement = $section->addText('SDT content to protect');

        $tempFile = sys_get_temp_dir() . '/test_inject_' . uniqid() . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        // Criar SDT para injetar (usando elemento Text, não Section)
        $config = new SDTConfig(id: '12345678', alias: 'Test SDT');

        $tuples = [
            ['element' => $textElement, 'config' => $config]
        ];

        // Injetar
        $injector = new SDTInjector();
        $injector->inject($tempFile, $tuples);

        // Verificar resultado
        $zip = new \ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('<w:sdt>');
        expect($xml)->toContain('Test SDT');
        expect($xml)->toContain('SDT content to protect');

        unlink($tempFile);
    });

    test('injeta múltiplos SDTs', function () {
        // Criar DOCX base com múltiplos elementos Text
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $text1 = $section->addText('SDT 1 content');
        $text2 = $section->addText('SDT 2 content');

        $tempFile = sys_get_temp_dir() . '/test_multi_inject_' . uniqid() . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        // Criar múltiplos SDTs (usando elementos Text)
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

        // Injetar
        $injector = new SDTInjector();
        $injector->inject($tempFile, $tuples);

        // Verificar resultado
        $zip = new \ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('First');
        expect($xml)->toContain('Second');
        expect($xml)->toContain('SDT 1');
        expect($xml)->toContain('SDT 2');

        // Contar ocorrências de <w:sdt>
        expect($xml)->toBeString();
        assert(is_string($xml)); // PHPStan type narrowing
        $count = substr_count($xml, '<w:sdt>');
        expect($count)->toBe(2);

        unlink($tempFile);
    });

    test('lança ZipArchiveException se arquivo não existe', function () {
        $injector = new SDTInjector();

        expect(fn() => $injector->inject('/path/nonexistent.docx', []))
            ->toThrow(ZipArchiveException::class);
    });

    test('injeta antes de </w:body>', function () {
        // Criar DOCX base com elemento Text
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Before SDT');
        $textToProtect = $section->addText('SDT content to protect');

        $tempFile = sys_get_temp_dir() . '/test_position_' . uniqid() . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        // Injetar SDT (envolvendo elemento existente)
        $tuples = [
            [
                'element' => $textToProtect,
                'config' => new SDTConfig(id: '12345678')
            ]
        ];

        $injector = new SDTInjector();
        $injector->inject($tempFile, $tuples);

        // Verificar posição
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

describe('SDTInjector - Métodos v3.0 (DOM Inline Wrapping)', function () {

    test('wrapElementInline move elemento sem duplicar', function () {
        $dom = new DOMDocument();
        $xml = '<?xml version="1.0"?>
        <w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:p><w:r><w:t>Original</w:t></w:r></w:p>
        </w:body>';
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        
        $paragraph = $xpath->query('//w:p')->item(0);
        
        // Debug: validar que encontrou o parágrafo
        expect($paragraph)->not->toBeNull();

        $injector = new SDTInjector();
        $config = new SDTConfig(id: '12345678', alias: 'Test');

        // Chamar método privado via Reflection
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('wrapElementInline');
        $method->setAccessible(true);
        $method->invoke($injector, $paragraph, $config);
        
        // Validar estrutura
        $sdt = $xpath->query('//w:sdt')->item(0);
        expect($sdt)->not->toBeNull();

        // Validar conteúdo dentro de SDT
        $wrappedParagraph = $xpath->query('//w:sdt/w:sdtContent/w:p')->item(0);
        expect($wrappedParagraph)->not->toBeNull();
        assert($wrappedParagraph !== null);
        expect($wrappedParagraph->textContent)->toBe('Original');

        // Validar NÃO há parágrafo órfão fora do SDT
        $orphanParagraphs = $xpath->query('//w:body/w:p');
        expect($orphanParagraphs->length)->toBe(0);
    });

    test('wrapElementInline cria propriedades SDT corretas', function () {
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

        // Validar propriedades
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

    test('isElementProcessed detecta elementos já processados', function () {
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
        
        // Marcar como processado
        $markMethod = $reflection->getMethod('markElementAsProcessed');
        $markMethod->setAccessible(true);
        $markMethod->invoke($injector, $paragraph);

        // Verificar
        $isProcessedMethod = $reflection->getMethod('isElementProcessed');
        $isProcessedMethod->setAccessible(true);
        $isProcessed = $isProcessedMethod->invoke($injector, $paragraph);

        expect($isProcessed)->toBeTrue();
    });

    test('wrapElementInline lança exceção se elemento sem owner document', function () {
        $injector = new SDTInjector();
        $config = new SDTConfig(id: '12345678');

        // Criar documento mas não adicionar o elemento ao documento
        $doc = new DOMDocument();
        $element = $doc->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:p');
        // NÃO adicionar ao documento, deixar órfão
        
        // Elemento tem ownerDocument mas não tem parent
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('wrapElementInline');
        $method->setAccessible(true);

        expect(fn() => $method->invoke($injector, $element, $config))
            ->toThrow(\RuntimeException::class, 'Target element has no parent node');
    });

});
describe('SDTInjector - Ordenação por Profundidade (v3.0)', function () {
    test('ordena Cell antes de Table', function () {
        // Criar elementos PHPWord
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow();
        $cell = $table->addCell(2000);
        $cell->addText('Cell content');

        // Criar configs
        $cellConfig = new SDTConfig(id: '11111111', alias: 'Cell Control');
        $tableConfig = new SDTConfig(id: '22222222', alias: 'Table Control');

        // Array desordenado (Table primeiro)
        $sdtTuples = [
            ['element' => $table, 'config' => $tableConfig],
            ['element' => $cell, 'config' => $cellConfig],
        ];

        // Usar reflexão para chamar sortElementsByDepth
        $injector = new SDTInjector();
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('sortElementsByDepth');
        $method->setAccessible(true);

        $sorted = $method->invoke($injector, $sdtTuples);

        assert(is_array($sorted));
        /** @var array<int, array{element: mixed, config: SDTConfig}> $sorted */

        // Verificar ordem: Cell (depth 3) deve vir antes de Table (depth 1)
        expect($sorted[0]['element'])->toBe($cell);
        expect($sorted[1]['element'])->toBe($table);
    });

    test('getElementDepth retorna valores corretos', function () {
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

    test('ordena múltiplos elementos corretamente', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $table1 = $section->addTable();
        $table1->addRow();
        $cell1 = $table1->addCell(2000);
        $table2 = $section->addTable();
        $table2->addRow();
        $cell2 = $table2->addCell(2000);
        $text = $section->addText('Text');

        // Array desordenado
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

        // Primeiros elementos devem ser Cells (depth 3)
        expect($sorted[0]['element'])->toBeInstanceOf(\PhpOffice\PhpWord\Element\Cell::class);
        expect($sorted[1]['element'])->toBeInstanceOf(\PhpOffice\PhpWord\Element\Cell::class);
        
        // Últimos 3 elementos devem ser Tables ou Text (depth 1)
        // Ordem entre eles não é garantida (mesma profundidade)
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
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
        // Criar DOCX base
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Base content');

        $tempFile = sys_get_temp_dir() . '/test_inject_' . uniqid() . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        // Criar SDT para injetar
        $sdtSection = createSectionWithText('SDT content');
        $config = new SDTConfig(id: '12345678', alias: 'Test SDT');

        $tuples = [
            ['element' => $sdtSection, 'config' => $config]
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
        expect($xml)->toContain('SDT content');

        unlink($tempFile);
    });

    test('injeta múltiplos SDTs', function () {
        // Criar DOCX base
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Base content');

        $tempFile = sys_get_temp_dir() . '/test_multi_inject_' . uniqid() . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        // Criar múltiplos SDTs
        $tuples = [
            [
                'element' => createSectionWithText('SDT 1'),
                'config' => new SDTConfig(id: '12345678', alias: 'First')
            ],
            [
                'element' => createSectionWithText('SDT 2'),
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
        // Criar DOCX base
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Before SDT');

        $tempFile = sys_get_temp_dir() . '/test_position_' . uniqid() . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        // Injetar SDT
        $tuples = [
            [
                'element' => createSectionWithText('SDT content'),
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

        $sdtPos = strpos($xml, '<w:sdt>');
        $bodyClosePos = strpos($xml, '</w:body>');

        expect($sdtPos)->toBeLessThan($bodyClosePos);

        unlink($tempFile);
    });
});

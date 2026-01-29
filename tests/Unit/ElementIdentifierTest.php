<?php

declare(strict_types=1);

use MkGrow\ContentControl\ElementIdentifier;
use Tests\Fixtures\SampleElements;

describe('ElementIdentifier', function () {

    test('generateMarker cria marcador único por objeto', function () {
        $section1 = createSection();
        $section2 = createSection();
        
        $marker1 = ElementIdentifier::generateMarker($section1);
        $marker2 = ElementIdentifier::generateMarker($section2);
        
        expect($marker1)->toMatch('/^sdt-marker-\d+-[a-f0-9]{8}$/');
        expect($marker2)->toMatch('/^sdt-marker-\d+-[a-f0-9]{8}$/');
        expect($marker1)->not->toBe($marker2);
    });

    test('generateContentHash é consistente para conteúdo idêntico', function () {
        $section1 = SampleElements::createSectionWithText('Teste');
        $section2 = SampleElements::createSectionWithText('Teste');
        
        $hash1 = ElementIdentifier::generateContentHash($section1);
        $hash2 = ElementIdentifier::generateContentHash($section2);
        
        expect($hash1)->toBe($hash2);
        expect($hash1)->toHaveLength(8);
        expect($hash1)->toMatch('/^[a-f0-9]{8}$/');
    });

    test('generateContentHash diferencia conteúdos diferentes', function () {
        $section1 = SampleElements::createSectionWithText('Texto A');
        $section2 = SampleElements::createSectionWithText('Texto B');
        
        $hash1 = ElementIdentifier::generateContentHash($section1);
        $hash2 = ElementIdentifier::generateContentHash($section2);
        
        expect($hash1)->not->toBe($hash2);
    });

    test('generateContentHash funciona com Table', function () {
        $table1 = createSimpleTable(2, 2);
        $table2 = createSimpleTable(3, 3);
        
        $hash1 = ElementIdentifier::generateContentHash($table1);
        $hash2 = ElementIdentifier::generateContentHash($table2);
        
        expect($hash1)->not->toBe($hash2);
    });

    test('generateMarker usa object_id diferente para elementos idênticos', function () {
        $text1 = SampleElements::createSectionWithText('Mesmo texto');
        $text2 = SampleElements::createSectionWithText('Mesmo texto');
        
        $marker1 = ElementIdentifier::generateMarker($text1);
        $marker2 = ElementIdentifier::generateMarker($text2);
        
        // Marcadores devem ser diferentes (object_id diferente)
        expect($marker1)->not->toBe($marker2);
        
        // Mas hashes devem ser iguais (conteúdo idêntico)
        $hash1 = ElementIdentifier::generateContentHash($text1);
        $hash2 = ElementIdentifier::generateContentHash($text2);
        expect($hash1)->toBe($hash2);
    });

    test('generateContentHash processa TextRun como elemento principal', function () {
        // Criar Section com TextRun (não Text)
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $textRun = $section->addTextRun();
        $textRun->addText('Parte 1 ');
        $textRun->addText('Parte 2', ['bold' => true]);
        
        $hash = ElementIdentifier::generateContentHash($textRun);
        
        expect($hash)->toHaveLength(8);
        expect($hash)->toMatch('/^[a-f0-9]{8}$/');
    });

    test('generateContentHash para TextRun inclui tipo paragraph', function () {
        // TextRun deve gerar hash similar a Text (ambos viram w:p)
        $phpWord1 = new \PhpOffice\PhpWord\PhpWord();
        $section1 = $phpWord1->addSection();
        $textRun = $section1->addTextRun();
        $textRun->addText('Conteúdo');
        
        $phpWord2 = new \PhpOffice\PhpWord\PhpWord();
        $section2 = $phpWord2->addSection();
        $text = $section2->addText('Conteúdo');
        
        $hashTextRun = ElementIdentifier::generateContentHash($textRun);
        $hashText = ElementIdentifier::generateContentHash($text);
        
        // Hashes devem ser iguais (mesmo conteúdo, mesmo tipo de parágrafo)
        expect($hashTextRun)->toBe($hashText);
    });

    test('generateContentHash para Cell com TextRun interno', function () {
        // Criar Table com Cell contendo TextRun
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow();
        $cell = $table->addCell(2000);
        
        $textRun = $cell->addTextRun();
        $textRun->addText('Texto em ');
        $textRun->addText('TextRun', ['bold' => true]);
        
        $hash = ElementIdentifier::generateContentHash($cell);
        
        expect($hash)->toHaveLength(8);
        expect($hash)->toMatch('/^[a-f0-9]{8}$/');
    });

    test('generateContentHash para Cell com Table aninhada', function () {
        // Criar Table com Cell contendo outra Table
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow();
        $cell = $table->addCell(4000);
        
        // Tabela aninhada dentro da célula
        $nestedTable = $cell->addTable();
        $nestedTable->addRow();
        $nestedTable->addCell(2000)->addText('Nested R1C1');
        
        $hash = ElementIdentifier::generateContentHash($cell);
        
        expect($hash)->toHaveLength(8);
        expect($hash)->toMatch('/^[a-f0-9]{8}$/');
    });

    test('generateContentHash para Section com TextRun como filho', function () {
        // Section contendo TextRun
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        
        $textRun1 = $section->addTextRun();
        $textRun1->addText('Parágrafo 1');
        
        $textRun2 = $section->addTextRun();
        $textRun2->addText('Parágrafo 2');
        
        $hash = ElementIdentifier::generateContentHash($section);
        
        expect($hash)->toHaveLength(8);
        expect($hash)->toMatch('/^[a-f0-9]{8}$/');
    });

});

describe('ElementIdentifier Cache', function () {

    beforeEach(function () {
        // Limpar cache antes de cada teste
        ElementIdentifier::clearCache();
    });

    afterEach(function () {
        // Limpar cache após cada teste
        ElementIdentifier::clearCache();
    });

    test('clearCache limpa todos os caches', function () {
        $section = createSection();
        
        // Gerar marcador e hash (popula cache)
        ElementIdentifier::generateMarker($section);
        ElementIdentifier::generateContentHash($section);
        
        $stats = ElementIdentifier::getCacheStats();
        expect($stats['markers'])->toBe(1);
        expect($stats['hashes'])->toBe(1);
        
        // Limpar cache
        ElementIdentifier::clearCache();
        
        $stats = ElementIdentifier::getCacheStats();
        expect($stats['markers'])->toBe(0);
        expect($stats['hashes'])->toBe(0);
    });

    test('generateMarker usa cache para elemento já processado', function () {
        $section = createSection();
        
        // Primeira chamada - popula cache
        $marker1 = ElementIdentifier::generateMarker($section);
        
        // Segunda chamada - deve retornar do cache (mesmo valor)
        $marker2 = ElementIdentifier::generateMarker($section);
        
        expect($marker1)->toBe($marker2);
        
        $stats = ElementIdentifier::getCacheStats();
        expect($stats['markers'])->toBe(1); // Apenas 1 entrada
    });

    test('generateContentHash usa cache para elemento já processado', function () {
        $section = createSection();
        
        // Primeira chamada - popula cache
        $hash1 = ElementIdentifier::generateContentHash($section);
        
        // Segunda chamada - deve retornar do cache (mesmo valor)
        $hash2 = ElementIdentifier::generateContentHash($section);
        
        expect($hash1)->toBe($hash2);
        
        $stats = ElementIdentifier::getCacheStats();
        expect($stats['hashes'])->toBe(1); // Apenas 1 entrada
    });

    test('cache não interfere com elementos diferentes', function () {
        $section1 = SampleElements::createSectionWithText('Texto 1');
        $section2 = SampleElements::createSectionWithText('Texto 2');
        
        // Gerar marcadores para ambos
        $marker1 = ElementIdentifier::generateMarker($section1);
        $marker2 = ElementIdentifier::generateMarker($section2);
        
        expect($marker1)->not->toBe($marker2);
        
        $stats = ElementIdentifier::getCacheStats();
        expect($stats['markers'])->toBe(2); // 2 entradas diferentes
        expect($stats['hashes'])->toBe(2);  // Hashes também cachados
    });

    test('getCacheStats retorna contadores corretos', function () {
        ElementIdentifier::clearCache();
        
        $section1 = createSection();
        $section2 = createSection();
        $section3 = createSection();
        
        // Gerar apenas marcadores (que também geram hashes internamente)
        ElementIdentifier::generateMarker($section1);
        ElementIdentifier::generateMarker($section2);
        ElementIdentifier::generateMarker($section3);
        
        $stats = ElementIdentifier::getCacheStats();
        expect($stats['markers'])->toBe(3);
        expect($stats['hashes'])->toBe(3);
    });

    test('cache persiste entre chamadas de generateMarker e generateContentHash', function () {
        $section = createSection();
        
        // Gerar hash primeiro
        $hash = ElementIdentifier::generateContentHash($section);
        
        $stats1 = ElementIdentifier::getCacheStats();
        expect($stats1['hashes'])->toBe(1);
        expect($stats1['markers'])->toBe(0);
        
        // Gerar marcador depois (deve reutilizar hash do cache)
        $marker = ElementIdentifier::generateMarker($section);
        
        $stats2 = ElementIdentifier::getCacheStats();
        expect($stats2['hashes'])->toBe(1);  // Ainda 1 (reusado)
        expect($stats2['markers'])->toBe(1); // Agora 1
        
        // Verificar que marcador contém o hash
        expect($marker)->toContain($hash);
    });

    test('cache melhora performance em chamadas repetidas', function () {
        $section = SampleElements::createSectionWithTable(10, 5); // Tabela grande
        
        // Medir primeira chamada (sem cache)
        $start1 = microtime(true);
        $hash1 = ElementIdentifier::generateContentHash($section);
        $time1 = microtime(true) - $start1;
        
        // Medir segunda chamada (com cache)
        $start2 = microtime(true);
        $hash2 = ElementIdentifier::generateContentHash($section);
        $time2 = microtime(true) - $start2;
        
        // Segunda chamada deve ser significativamente mais rápida
        expect($hash1)->toBe($hash2);
        expect($time2)->toBeLessThan($time1); // Cache é mais rápido
    });

});

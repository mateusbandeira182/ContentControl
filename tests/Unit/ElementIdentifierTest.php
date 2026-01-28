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

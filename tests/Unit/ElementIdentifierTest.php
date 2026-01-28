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

});

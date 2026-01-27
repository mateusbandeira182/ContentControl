<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use PhpOffice\PhpWord\PhpWord;

describe('ContentControl - Construtor', function () {
    
    test('cria com defaults quando opções vazias', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $control = new ContentControl($section);
        $xml = $control->getXml();
        
        // ID deve existir (8 dígitos)
        expect($xml)->toMatch('/<w:id w:val="\d{8}"\/>/');
        expect($xml)->toContain('<w:richText/>');  // Type padrão
        expect($xml)->not->toContain('<w:lock');  // Lock padrão NONE = ausente
    });

    test('gera ID único automaticamente', function () {
        $phpWord = new PhpWord();
        $section1 = $phpWord->addSection();
        $section2 = $phpWord->addSection();
        
        $control1 = new ContentControl($section1);
        $control2 = new ContentControl($section2);
        
        $xml1 = $control1->getXml();
        $xml2 = $control2->getXml();
        
        // Extrair IDs via regex
        preg_match('/<w:id w:val="(\d+)"/', $xml1, $matches1);
        preg_match('/<w:id w:val="(\d+)"/', $xml2, $matches2);
        
        expect($matches1[1])->not->toBe($matches2[1]);
    });

    test('aceita todas as opções válidas', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $control = new ContentControl($section, [
            'id' => '12345678',
            'alias' => 'Test Alias',
            'tag' => 'test-tag',
            'type' => ContentControl::TYPE_PLAIN_TEXT,
            'lockType' => ContentControl::LOCK_SDT_LOCKED,
        ]);
        
        $xml = $control->getXml();
        
        expect($xml)->toContain('w:val="12345678"');
        expect($xml)->toContain('w:val="Test Alias"');
        expect($xml)->toContain('w:val="test-tag"');
        expect($xml)->toContain('<w:text/>');
        expect($xml)->toContain('w:val="sdtLocked"');
    });

    test('lança exceção para tipo inválido', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        new ContentControl($section, ['type' => 'invalid-type']);
    })->throws(InvalidArgumentException::class, 'Invalid type');

    test('lança exceção para lockType inválido', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        new ContentControl($section, ['lockType' => 'invalid-lock']);
    })->throws(InvalidArgumentException::class, 'Invalid lock type');
});

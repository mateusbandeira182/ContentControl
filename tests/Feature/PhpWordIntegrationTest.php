<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use PhpOffice\PhpWord\PhpWord;
use Tests\Fixtures\SampleElements;

describe('PHPWord Integration - Documento Completo', function () {
    
    test('gera documento válido com Content Control', function () {
        $phpWord = new PhpWord();
        
        // Criar section com conteúdo
        $section = $phpWord->addSection();
        $section->addText('Este é um Content Control funcional', ['bold' => true]);
        
        // Envolver em Content Control
        $control = new ContentControl($section, [
            'alias' => 'Campo de Teste',
            'tag' => 'test-field',
            'type' => ContentControl::TYPE_RICH_TEXT,
            'lockType' => ContentControl::LOCK_SDT_LOCKED,
        ]);
        
        // Gerar XML do controle
        $xml = $control->getXml();
        
        // Validar XML parse
        $dom = new DOMDocument();
        expect(@$dom->loadXML($xml))->toBeTrue();
    });

    test('integra com fixtures de elementos', function () {
        // TextRun
        $section1 = SampleElements::createSectionWithTextRun();
        $control1 = new ContentControl($section1, ['type' => ContentControl::TYPE_RICH_TEXT]);
        $xml1 = $control1->getXml();
        
        expect($xml1)->toContain('Texto normal');
        expect($xml1)->toContain('Texto negrito');
        
        // Table
        $section2 = SampleElements::createSectionWithTable(3, 2);
        $control2 = new ContentControl($section2, ['type' => ContentControl::TYPE_GROUP]);
        $xml2 = $control2->getXml();
        
        expect($xml2)->toContain('<w:tbl>');
        expect($xml2)->toContain('R0C0');
        expect($xml2)->toContain('R2C1');
    });
});

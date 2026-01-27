<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use PhpOffice\PhpWord\PhpWord;

describe('Element Serialization - Text', function () {
    
    test('serializa Text com wrapper <w:p>', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Texto de teste');
        
        $control = new ContentControl($section);
        $xml = $control->getXml();
        
        // Deve conter <w:p> envolvendo <w:r><w:t>
        expect($xml)->toContain('<w:p>');
        expect($xml)->toContain('<w:t');
        expect($xml)->toContain('Texto de teste');
    });
});

describe('Element Serialization - TextRun', function () {
    
    test('serializa TextRun com wrapper <w:p> externo', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $textRun = $section->addTextRun();
        $textRun->addText('Parte 1 ');
        $textRun->addText('Parte 2', ['bold' => true]);
        
        $control = new ContentControl($section);
        $xml = $control->getXml();
        
        // TextRun deve ter <w:p> externo
        expect($xml)->toContain('<w:p>');
        expect($xml)->toContain('Parte 1');
        expect($xml)->toContain('Parte 2');
    });
});

describe('Element Serialization - Table', function () {
    
    test('serializa Table SEM wrapper <w:p>', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Célula 1');
        $table->addCell(2000)->addText('Célula 2');
        
        $control = new ContentControl($section);
        $xml = $control->getXml();
        
        // Deve conter <w:tbl> diretamente em <w:sdtContent>
        expect($xml)->toContain('<w:tbl>');
        expect($xml)->toContain('Célula 1');
        expect($xml)->toContain('Célula 2');
        
        // NÃO deve ter <w:p> antes de <w:tbl>
        expect($xml)->not->toMatch('/<w:p[^>]*>\s*<w:tbl/');
    });
});

describe('Element Serialization - Múltiplos Elementos', function () {
    
    test('serializa mix de elementos corretamente', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        // Text (precisa wrapper)
        $section->addText('Parágrafo antes da tabela');
        
        // Table (sem wrapper)
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Célula');
        
        // Text (precisa wrapper)
        $section->addText('Parágrafo depois da tabela');
        
        $control = new ContentControl($section);
        $xml = $control->getXml();
        
        // Verificar presença de todos elementos
        expect($xml)->toContain('Parágrafo antes da tabela');
        expect($xml)->toContain('<w:tbl>');
        expect($xml)->toContain('Célula');
        expect($xml)->toContain('Parágrafo depois da tabela');
    });
});

test('lida com Content Control vazio', function () {
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();
    // Section vazio
    
    $control = new ContentControl($section);
    $xml = $control->getXml();
    
    // sdtContent deve estar vazio ou self-closing
    expect($xml)->toMatch('/<w:sdtContent\s*\/?>.*?<\/w:sdtContent>|<w:sdtContent\s*\/>/s');
});

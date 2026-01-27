<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Writer\Word2007\Element\ContentControl as ContentControlWriter;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\XMLWriter;

describe('ContentControl Writer', function () {
    
    test('writer gera XML válido do Content Control', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Teste de Writer');
        
        $control = new ContentControl($section, [
            'alias' => 'Campo de Teste',
            'tag' => 'test-field',
        ]);
        
        // Criar XMLWriter em modo memória
        $xmlWriter = new XMLWriter(XMLWriter::STORAGE_MEMORY);
        $xmlWriter->openMemory();
        
        // Criar Writer e escrever
        $writer = new ContentControlWriter($xmlWriter, $control);
        $writer->write();
        
        $xml = $xmlWriter->getData();
        
        // Validar XML gerado
        expect($xml)->toContain('<w:sdt')
            ->and($xml)->toContain('xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"')
            ->and($xml)->toContain('<w:alias w:val="Campo de Teste"/>')
            ->and($xml)->toContain('<w:tag w:val="test-field"/>');
    });
    
    test('writer ignora elementos que não são ContentControl', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Texto normal');
        
        $xmlWriter = new XMLWriter(XMLWriter::STORAGE_MEMORY);
        $xmlWriter->openMemory();
        
        // Tentar criar Writer com elemento Text (não ContentControl)
        $writer = new ContentControlWriter($xmlWriter, $text);
        $writer->write();
        
        $xml = $xmlWriter->getData();
        
        // Não deve gerar nada
        expect($xml)->toBe('');
    });
    
    test('writer lida com Content Control vazio', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        // Section vazio
        
        $control = new ContentControl($section);
        
        $xmlWriter = new XMLWriter(XMLWriter::STORAGE_MEMORY);
        $xmlWriter->openMemory();
        
        $writer = new ContentControlWriter($xmlWriter, $control);
        $writer->write();
        
        $xml = $xmlWriter->getData();
        
        // Deve gerar estrutura SDT vazia
        expect($xml)->toContain('<w:sdt')
            ->and($xml)->toContain('<w:sdtContent');
    });
});

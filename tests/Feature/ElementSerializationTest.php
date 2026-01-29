<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\SDTInjector;

describe('Element Serialization - Text', function () {
    
    test('serializa Text com wrapper <w:p>', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText('Texto de teste');
        
        // Criar arquivo temporário para injetar XML
        $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.docx';
        try {
            $cc->save($tempFile);
            
            // Abrir e validar XML
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            
            // Deve conter <w:p> envolvendo <w:r><w:t>
            expect($xml)->toContain('<w:p');
            expect($xml)->toContain('<w:t');
            expect($xml)->toContain('Texto de teste');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    });
});

describe('Element Serialization - TextRun', function () {
    
    test('serializa TextRun com wrapper <w:p> externo', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $textRun = $section->addTextRun();
        $textRun->addText('Parte 1 ');
        $textRun->addText('Parte 2', ['bold' => true]);
        
        $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.docx';
        try {
            $cc->save($tempFile);
            
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            
            // TextRun deve ter <w:p> externo
            expect($xml)->toContain('<w:p');
            expect($xml)->toContain('Parte 1');
            expect($xml)->toContain('Parte 2');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    });
});

describe('Element Serialization - Table', function () {
    
    test('serializa Table SEM wrapper <w:p>', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Célula 1');
        $table->addCell(2000)->addText('Célula 2');
        
        $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.docx';
        try {
            $cc->save($tempFile);
            
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            
            // Deve conter <w:tbl>
            expect($xml)->toContain('<w:tbl>');
            expect($xml)->toContain('Célula 1');
            expect($xml)->toContain('Célula 2');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    });
});

describe('Element Serialization - Múltiplos Elementos', function () {
    
    test('serializa mix de elementos corretamente', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        // Text (precisa wrapper)
        $section->addText('Parágrafo antes da tabela');
        
        // Table (sem wrapper)
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Célula');
        
        // Text (precisa wrapper)
        $section->addText('Parágrafo depois da tabela');
        
        $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.docx';
        try {
            $cc->save($tempFile);
            
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            
            // Verificar presença de todos elementos
            expect($xml)->toContain('Parágrafo antes da tabela');
            expect($xml)->toContain('<w:tbl>');
            expect($xml)->toContain('Célula');
            expect($xml)->toContain('Parágrafo depois da tabela');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    });
});

test('lida com Content Control vazio', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    // Adicionar Text vazio (v3.0 não suporta Section vazio)
    $emptyText = $section->addText('');
    
    $cc->addContentControl($emptyText);
    
    $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.docx';
    try {
        $cc->save($tempFile);
        
        $zip = new ZipArchive();
        $zip->open($tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        // sdtContent deve estar presente
        expect($xml)->toContain('w:sdtContent');
    } finally {
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
});

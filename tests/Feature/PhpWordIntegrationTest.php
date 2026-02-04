<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use Tests\Fixtures\SampleElements;

describe('PHPWord Integration - Documento Completo', function () {
    
    test('gera documento válido com Content Control', function () {
        $cc = new ContentControl();
        
        // Criar section com conteúdo
        $section = $cc->addSection();
        $textElement = $section->addText('Este é um Content Control funcional', ['bold' => true]);
        
        // Envolver elemento Text em Content Control (v3.0 não suporta Section)
        $cc->addContentControl($textElement, [
            'alias' => 'Campo de Teste',
            'tag' => 'test-field',
            'type' => ContentControl::TYPE_RICH_TEXT,
            'lockType' => ContentControl::LOCK_SDT_LOCKED,
        ]);
        
        // Gerar arquivo temporário
        $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.docx';
        try {
            $cc->save($tempFile);
            
            // Validar arquivo criado
            expect(file_exists($tempFile))->toBeTrue();
            
            // Abrir e validar XML
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            
            expect($xml)->toBeString();
            assert(is_string($xml)); // PHPStan type narrowing
            $dom = new DOMDocument();
            expect(@$dom->loadXML($xml))->toBeTrue();
        } finally {
            if (file_exists($tempFile)) {
                safeUnlink($tempFile);
            }
        }
    });

    test('integra com fixtures de elementos', function () {
        // Table (elemento complexo)
        $cc1 = new ContentControl();
        $section1 = $cc1->addSection();
        
        $table = $section1->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Célula 1');
        $table->addCell(2000)->addText('Célula 2');
        
        $cc1->addContentControl($table, ['type' => ContentControl::TYPE_GROUP]);
        
        $tempFile1 = sys_get_temp_dir() . '/test_' . uniqid() . '.docx';
        try {
            $cc1->save($tempFile1);
            
            $zip = new ZipArchive();
            $zip->open($tempFile1);
            $xml1 = $zip->getFromName('word/document.xml');
            $zip->close();
            
            // Validar Table protegida
            expect($xml1)->toContain('Célula 1');
            expect($xml1)->toContain('Célula 2');
            expect($xml1)->toContain('<w:group');
        } finally {
            if (file_exists($tempFile1)) {
                safeUnlink($tempFile1);
            }
        }
        
        // Test adicional: proteger apenas uma célula da tabela
        $cc2 = new ContentControl();
        $section2 = $cc2->addSection();
        
        $table = $section2->addTable();
        for ($r = 0; $r < 3; $r++) {
            $table->addRow();
            for ($c = 0; $c < 2; $c++) {
                $table->addCell(2000)->addText("R{$r}C{$c}");
            }
        }
        
        // Proteger Table inteiro (não Section)
        $cc2->addContentControl($table, ['type' => ContentControl::TYPE_GROUP]);
        
        $tempFile2 = sys_get_temp_dir() . '/test_' . uniqid() . '.docx';
        try {
            $cc2->save($tempFile2);
            
            $zip = new ZipArchive();
            $zip->open($tempFile2);
            $xml2 = $zip->getFromName('word/document.xml');
            $zip->close();
            
            expect($xml2)->toContain('<w:tbl>');
            expect($xml2)->toContain('R0C0');
            expect($xml2)->toContain('R2C1');
        } finally {
            if (file_exists($tempFile2)) {
                safeUnlink($tempFile2);
            }
        }
    });
});

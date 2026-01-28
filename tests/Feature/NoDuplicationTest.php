<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use PhpOffice\PhpWord\IOFactory as PHPWordIOFactory;

describe('v3.0 - Eliminação de Duplicação (DOM Inline Wrapping)', function () {
    test('não duplica conteúdo ao envolver Text com SDT', function () {
        // Criar ContentControl
        $cc = new ContentControl();
        $section = $cc->addSection();
        $text = $section->addText('Texto único que não deve duplicar');
        
        // Adicionar Content Control no elemento Text
        $cc->addContentControl($text, [
            'id' => '12345678',
            'alias' => 'Texto Principal',
            'tag' => 'main-text'
        ]);
        
        // Salvar DOCX
        $outputPath = sys_get_temp_dir() . '/test_no_duplication_text.docx';
        $cc->save($outputPath);
        
        // Verificar estrutura do DOCX
        $zip = new ZipArchive();
        $zip->open($outputPath);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        expect($documentXml)->toBeString();
        assert(is_string($documentXml));
        
        // Contar ocorrências do texto
        $textOccurrences = substr_count($documentXml, 'Texto único que não deve duplicar');
        expect($textOccurrences)->toBe(1, 'Text should appear exactly once in document.xml');
        
        // Verificar presença de SDT
        expect($documentXml)->toContain('<w:sdt>');
        expect($documentXml)->toContain('<w:sdtPr>');
        expect($documentXml)->toContain('w:val="12345678"');
        
        // Limpar arquivo
        @unlink($outputPath);
    });

    test('não duplica conteúdo ao envolver Table com SDT', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Cell R0C0');
        $table->addCell(2000)->addText('Cell R0C1');
        
        // Adicionar Content Control na tabela
        $cc->addContentControl($table, [
            'id' => '87654321',
            'alias' => 'Tabela Principal'
        ]);
        
        $outputPath = sys_get_temp_dir() . '/test_no_duplication_table.docx';
        $cc->save($outputPath);
        
        $zip = new ZipArchive();
        $zip->open($outputPath);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        assert(is_string($documentXml));
        
        // Verificar não duplicação
        $cellR0C0Count = substr_count($documentXml, 'Cell R0C0');
        $cellR0C1Count = substr_count($documentXml, 'Cell R0C1');
        
        expect($cellR0C0Count)->toBe(1, 'Cell R0C0 should appear exactly once');
        expect($cellR0C1Count)->toBe(1, 'Cell R0C1 should appear exactly once');
        
        // Verificar SDT
        expect($documentXml)->toContain('<w:sdt>');
        expect($documentXml)->toContain('w:val="87654321"');
        
        @unlink($outputPath);
    });

    test('não duplica conteúdo ao envolver Cell aninhada', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        $table = $section->addTable();
        $table->addRow();
        $cell1 = $table->addCell(2000);
        $cell1->addText('Conteúdo da célula protegida');
        $cell2 = $table->addCell(2000);
        $cell2->addText('Célula normal');
        
        // Adicionar Content Control APENAS na primeira célula
        $cc->addContentControl($cell1, [
            'id' => '11111111',
            'alias' => 'Célula Protegida',
            'lockType' => ContentControl::LOCK_SDT_LOCKED
        ]);
        
        $outputPath = sys_get_temp_dir() . '/test_no_duplication_cell.docx';
        $cc->save($outputPath);
        
        $zip = new ZipArchive();
        $zip->open($outputPath);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        assert(is_string($documentXml));
        
        // Verificar não duplicação
        $protectedCellCount = substr_count($documentXml, 'Conteúdo da célula protegida');
        $normalCellCount = substr_count($documentXml, 'Célula normal');
        
        expect($protectedCellCount)->toBe(1, 'Protected cell content should appear exactly once');
        expect($normalCellCount)->toBe(1, 'Normal cell content should appear exactly once');
        
        // Verificar SDT apenas na célula protegida
        expect($documentXml)->toContain('<w:sdt>');
        expect($documentXml)->toContain('w:val="11111111"');
        expect($documentXml)->toContain('w:val="sdtLocked"');
        
        @unlink($outputPath);
    });

    test('não duplica ao envolver múltiplos elementos diferentes', function () {
        $cc = new ContentControl();
        $section = $cc->addSection();
        
        // Adicionar texto
        $section->addText('Texto antes da tabela');
        
        // Adicionar tabela
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Dados da tabela');
        
        // Adicionar outro texto
        $section->addText('Texto depois da tabela');
        
        // Adicionar Content Controls
        $cc->addContentControl($table, [
            'id' => '22222222',
            'alias' => 'Tabela de Dados'
        ]);
        
        $outputPath = sys_get_temp_dir() . '/test_no_duplication_multiple.docx';
        $cc->save($outputPath);
        
        $zip = new ZipArchive();
        $zip->open($outputPath);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        assert(is_string($documentXml));
        
        // Verificar não duplicação de TODOS os conteúdos
        expect(substr_count($documentXml, 'Texto antes da tabela'))->toBe(1);
        expect(substr_count($documentXml, 'Dados da tabela'))->toBe(1);
        expect(substr_count($documentXml, 'Texto depois da tabela'))->toBe(1);
        
        // Verificar SDT
        expect($documentXml)->toContain('<w:sdt>');
        expect($documentXml)->toContain('w:val="22222222"');
        
        @unlink($outputPath);
    });
});


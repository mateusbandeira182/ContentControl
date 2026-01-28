<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;

/**
 * Testes de cenários de erro no ContentControl
 * 
 * Cobertura de linhas: 305-307, 326-328
 * Nota: unlinkWithRetry (348-368) não é testado para Windows file locks
 */

test('save lança RuntimeException se diretório não for gravável', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('Test content');
    
    // Tentar salvar em diretório que não existe/não é gravável
    // Usar caminho absoluto que não existe em Linux e Windows
    $invalidPath = '/nonexistent_dir_12345/subdir/document.docx';
    
    expect(fn() => $cc->save($invalidPath))
        ->toThrow(\RuntimeException::class, 'Target directory not writable');
});

test('save lança RuntimeException se diretório for caminho inválido', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('Test content');
    
    // Caminho com caracteres inválidos
    $invalidPath = ":\invalid\0path\document.docx";
    
    expect(fn() => $cc->save($invalidPath))
        ->toThrow(\RuntimeException::class);
});

test('save cria documento com sucesso em diretório temporário', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('Test content for valid save');
    
    $tempFile = sys_get_temp_dir() . '/test_valid_' . uniqid() . '.docx';
    
    // Não deve lançar exceção
    $cc->save($tempFile);
    
    expect(file_exists($tempFile))->toBeTrue();
    expect(filesize($tempFile))->toBeGreaterThan(0);
    
    // Cleanup
    unlink($tempFile);
});

test('save com Content Controls registrados funciona corretamente', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    $text = $section->addText('Protected content');
    
    $cc->addContentControl($text, [
        'alias' => 'Test Control',
        'tag' => 'test-tag',
        'type' => ContentControl::TYPE_RICH_TEXT
    ]);
    
    $tempFile = sys_get_temp_dir() . '/test_with_sdt_' . uniqid() . '.docx';
    
    $cc->save($tempFile);
    
    expect(file_exists($tempFile))->toBeTrue();
    
    // Verificar que é um arquivo ZIP válido (DOCX é ZIP)
    $zip = new ZipArchive();
    $openResult = $zip->open($tempFile);
    expect($openResult)->toBeTrue();
    
    // Verificar que word/document.xml existe
    $documentXml = $zip->getFromName('word/document.xml');
    expect($documentXml)->not->toBeFalse();
    
    $zip->close();
    
    // Cleanup
    unlink($tempFile);
});

test('save processa múltiplos Content Controls sem erro', function () {
    $cc = new ContentControl();
    
    $section1 = $cc->addSection();
    $text1 = $section1->addText('Section 1');
    $cc->addContentControl($text1, ['alias' => 'Text 1']);
    
    $section2 = $cc->addSection();
    $text2 = $section2->addText('Section 2');
    $cc->addContentControl($text2, ['alias' => 'Text 2']);
    
    $tempFile = sys_get_temp_dir() . '/test_multiple_sdt_' . uniqid() . '.docx';
    
    $cc->save($tempFile);
    
    expect(file_exists($tempFile))->toBeTrue();
    
    // Cleanup
    unlink($tempFile);
});

test('save com formato Word2007 explícito funciona', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('Content with explicit format');
    
    $tempFile = sys_get_temp_dir() . '/test_explicit_format_' . uniqid() . '.docx';
    
    $cc->save($tempFile, 'Word2007');
    
    expect(file_exists($tempFile))->toBeTrue();
    
    // Cleanup
    unlink($tempFile);
});

test('save limpa arquivo temporário após processamento', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('Temporary file cleanup test');
    
    $tempFile = sys_get_temp_dir() . '/test_cleanup_' . uniqid() . '.docx';
    
    // Capturar arquivos temporários antes
    $tempDir = sys_get_temp_dir();
    $beforeFiles = glob($tempDir . '/phpword_*.docx');
    $beforeCount = is_array($beforeFiles) ? count($beforeFiles) : 0;
    
    $cc->save($tempFile);
    
    // Verificar que nenhum arquivo phpword_* ficou para trás
    $afterFiles = glob($tempDir . '/phpword_*.docx');
    $afterCount = is_array($afterFiles) ? count($afterFiles) : 0;
    
    expect($afterCount)->toBeLessThanOrEqual($beforeCount);
    
    // Cleanup
    unlink($tempFile);
});

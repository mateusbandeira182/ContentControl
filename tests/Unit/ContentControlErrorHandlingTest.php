<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;

/**
 * Tests for ContentControl error scenarios
 * 
 * Line coverage: 305-307, 326-328
 * Note: unlinkWithRetry (348-368) is not tested for Windows file locks
 */

test('save throws RuntimeException if directory is not writable', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('Test content');
    
    // Attempt to save in a directory that does not exist/is not writable
    // Use absolute path that does not exist on Linux and Windows
    $invalidPath = '/nonexistent_dir_12345/subdir/document.docx';
    
    expect(fn() => $cc->save($invalidPath))
        ->toThrow(\RuntimeException::class, 'Target directory not writable');
});

test('save throws RuntimeException if directory path is invalid', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('Test content');
    
    // Path with null character (causes ValueError in is_dir/is_writable in PHP 8.2+)
    // Use appropriate directory separator for the operating system
    $sep = DIRECTORY_SEPARATOR;
    $invalidPath = "{$sep}invalid{$sep}\0path{$sep}document.docx";
    
    expect(fn() => $cc->save($invalidPath))
        ->toThrow(\RuntimeException::class);
});

test('save creates document successfully in temporary directory', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('Test content for valid save');
    
    $tempFile = sys_get_temp_dir() . '/test_valid_' . uniqid() . '.docx';
    
    // Should not throw exception
    $cc->save($tempFile);
    
    expect(file_exists($tempFile))->toBeTrue();
    expect(filesize($tempFile))->toBeGreaterThan(0);
    
    // Cleanup
    unlink($tempFile);
});

test('save with registered Content Controls works correctly', function () {
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
    
    // Verify that it is a valid ZIP file (DOCX is ZIP)
    $zip = new ZipArchive();
    $openResult = $zip->open($tempFile);
    expect($openResult)->toBeTrue();
    
    // Verify that word/document.xml exists
    $documentXml = $zip->getFromName('word/document.xml');
    expect($documentXml)->not->toBeFalse();
    
    $zip->close();
    
    // Cleanup
    unlink($tempFile);
});

test('save processes multiple Content Controls without error', function () {
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

test('save with explicit Word2007 format works', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('Content with explicit format');
    
    $tempFile = sys_get_temp_dir() . '/test_explicit_format_' . uniqid() . '.docx';
    
    $cc->save($tempFile, 'Word2007');
    
    expect(file_exists($tempFile))->toBeTrue();
    
    // Cleanup
    unlink($tempFile);
});

test('save cleans up temporary file after processing', function () {
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('Temporary file cleanup test');
    
    $tempFile = sys_get_temp_dir() . '/test_cleanup_' . uniqid() . '.docx';
    
    // Capture temporary files before
    $tempDir = sys_get_temp_dir();
    $beforeFiles = glob($tempDir . '/phpword_*.docx');
    $beforeCount = is_array($beforeFiles) ? count($beforeFiles) : 0;
    
    $cc->save($tempFile);
    
    // Verify that no phpword_* files were left behind
    $afterFiles = glob($tempDir . '/phpword_*.docx');
    $afterCount = is_array($afterFiles) ? count($afterFiles) : 0;
    
    expect($afterCount)->toBeLessThanOrEqual($beforeCount);
    
    // Cleanup
    unlink($tempFile);
});

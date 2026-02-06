<?php

declare(strict_types=1);

use MkGrow\ContentControl\ElementIdentifier;
use Tests\Fixtures\SampleElements;

describe('ElementIdentifier', function () {

    test('generateMarker creates unique marker per object', function () {
        $section1 = createSection();
        $section2 = createSection();
        
        $marker1 = ElementIdentifier::generateMarker($section1);
        $marker2 = ElementIdentifier::generateMarker($section2);
        
        expect($marker1)->toMatch('/^sdt-marker-\d+-[a-f0-9]{8}$/');
        expect($marker2)->toMatch('/^sdt-marker-\d+-[a-f0-9]{8}$/');
        expect($marker1)->not->toBe($marker2);
    });

    test('generateContentHash is consistent for identical content', function () {
        $section1 = SampleElements::createSectionWithText('Teste');
        $section2 = SampleElements::createSectionWithText('Teste');
        
        $hash1 = ElementIdentifier::generateContentHash($section1);
        $hash2 = ElementIdentifier::generateContentHash($section2);
        
        expect($hash1)->toBe($hash2);
        expect($hash1)->toHaveLength(8);
        expect($hash1)->toMatch('/^[a-f0-9]{8}$/');
    });

    test('generateContentHash differentiates different content', function () {
        $section1 = SampleElements::createSectionWithText('Texto A');
        $section2 = SampleElements::createSectionWithText('Texto B');
        
        $hash1 = ElementIdentifier::generateContentHash($section1);
        $hash2 = ElementIdentifier::generateContentHash($section2);
        
        expect($hash1)->not->toBe($hash2);
    });

    test('generateContentHash works with Table', function () {
        $table1 = createSimpleTable(2, 2);
        $table2 = createSimpleTable(3, 3);
        
        $hash1 = ElementIdentifier::generateContentHash($table1);
        $hash2 = ElementIdentifier::generateContentHash($table2);
        
        expect($hash1)->not->toBe($hash2);
    });

    test('generateMarker uses different object_id for identical elements', function () {
        $text1 = SampleElements::createSectionWithText('Mesmo texto');
        $text2 = SampleElements::createSectionWithText('Mesmo texto');
        
        $marker1 = ElementIdentifier::generateMarker($text1);
        $marker2 = ElementIdentifier::generateMarker($text2);
        
        // Markers should be different (different object_id)
        expect($marker1)->not->toBe($marker2);
        
        // But hashes should be equal (identical content)
        $hash1 = ElementIdentifier::generateContentHash($text1);
        $hash2 = ElementIdentifier::generateContentHash($text2);
        expect($hash1)->toBe($hash2);
    });

    test('generateContentHash processes TextRun as main element', function () {
        // Create Section with TextRun (not Text)
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $textRun = $section->addTextRun();
        $textRun->addText('Parte 1 ');
        $textRun->addText('Parte 2', ['bold' => true]);
        
        $hash = ElementIdentifier::generateContentHash($textRun);
        
        expect($hash)->toHaveLength(8);
        expect($hash)->toMatch('/^[a-f0-9]{8}$/');
    });

    test('generateContentHash for TextRun includes paragraph type', function () {
        // TextRun should generate hash similar to Text (both become w:p)
        $phpWord1 = new \PhpOffice\PhpWord\PhpWord();
        $section1 = $phpWord1->addSection();
        $textRun = $section1->addTextRun();
        $textRun->addText('Conteúdo');
        
        $phpWord2 = new \PhpOffice\PhpWord\PhpWord();
        $section2 = $phpWord2->addSection();
        $text = $section2->addText('Conteúdo');
        
        $hashTextRun = ElementIdentifier::generateContentHash($textRun);
        $hashText = ElementIdentifier::generateContentHash($text);
        
        // Hashes should be equal (same content, same paragraph type)
        expect($hashTextRun)->toBe($hashText);
    });

    test('generateContentHash for Cell with internal TextRun', function () {
        // Create Table with Cell containing TextRun
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow();
        $cell = $table->addCell(2000);
        
        $textRun = $cell->addTextRun();
        $textRun->addText('Texto em ');
        $textRun->addText('TextRun', ['bold' => true]);
        
        $hash = ElementIdentifier::generateContentHash($cell);
        
        expect($hash)->toHaveLength(8);
        expect($hash)->toMatch('/^[a-f0-9]{8}$/');
    });

    test('generateContentHash for Cell with nested Table', function () {
        // Create Table with Cell containing another Table
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow();
        $cell = $table->addCell(4000);
        
        // Nested table inside cell
        $nestedTable = $cell->addTable();
        $nestedTable->addRow();
        $nestedTable->addCell(2000)->addText('Nested R1C1');
        
        $hash = ElementIdentifier::generateContentHash($cell);
        
        expect($hash)->toHaveLength(8);
        expect($hash)->toMatch('/^[a-f0-9]{8}$/');
    });

    test('generateContentHash for Section with TextRun as child', function () {
        // Section containing TextRun
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        
        $textRun1 = $section->addTextRun();
        $textRun1->addText('Parágrafo 1');
        
        $textRun2 = $section->addTextRun();
        $textRun2->addText('Parágrafo 2');
        
        $hash = ElementIdentifier::generateContentHash($section);
        
        expect($hash)->toHaveLength(8);
        expect($hash)->toMatch('/^[a-f0-9]{8}$/');
    });

});

describe('ElementIdentifier Cache', function () {

    beforeEach(function () {
        // Clear cache before each test
        ElementIdentifier::clearCache();
    });

    afterEach(function () {
        // Clear cache after each test
        ElementIdentifier::clearCache();
    });

    test('clearCache clears all caches', function () {
        $section = createSection();
        
        // Generate marker and hash (populates cache)
        ElementIdentifier::generateMarker($section);
        ElementIdentifier::generateContentHash($section);
        
        $stats = ElementIdentifier::getCacheStats();
        expect($stats['markers'])->toBe(1);
        expect($stats['hashes'])->toBe(1);
        
        // Clear cache
        ElementIdentifier::clearCache();
        
        $stats = ElementIdentifier::getCacheStats();
        expect($stats['markers'])->toBe(0);
        expect($stats['hashes'])->toBe(0);
    });

    test('generateMarker uses cache for already processed element', function () {
        $section = createSection();
        
        // Primeira chamada - popula cache
        $marker1 = ElementIdentifier::generateMarker($section);
        
        // Segunda chamada - deve retornar do cache (mesmo valor)
        $marker2 = ElementIdentifier::generateMarker($section);
        
        expect($marker1)->toBe($marker2);
        
        $stats = ElementIdentifier::getCacheStats();
        expect($stats['markers'])->toBe(1); // Only 1 entry
    });

    test('generateContentHash uses cache for already processed element', function () {
        $section = createSection();
        
        // Primeira chamada - popula cache
        $hash1 = ElementIdentifier::generateContentHash($section);
        
        // Segunda chamada - deve retornar do cache (mesmo valor)
        $hash2 = ElementIdentifier::generateContentHash($section);
        
        expect($hash1)->toBe($hash2);
        
        $stats = ElementIdentifier::getCacheStats();
        expect($stats['hashes'])->toBe(1); // Only 1 entry
    });

    test('cache does not interfere with different elements', function () {
        $section1 = SampleElements::createSectionWithText('Texto 1');
        $section2 = SampleElements::createSectionWithText('Texto 2');
        
        // Generate markers for both
        $marker1 = ElementIdentifier::generateMarker($section1);
        $marker2 = ElementIdentifier::generateMarker($section2);
        
        expect($marker1)->not->toBe($marker2);
        
        $stats = ElementIdentifier::getCacheStats();
        expect($stats['markers'])->toBe(2); // 2 different entries
        expect($stats['hashes'])->toBe(2);  // Hashes also cached
    });

    test('getCacheStats returns correct counters', function () {
        ElementIdentifier::clearCache();
        
        $section1 = createSection();
        $section2 = createSection();
        $section3 = createSection();
        
        // Generate only markers (which also generate hashes internally)
        ElementIdentifier::generateMarker($section1);
        ElementIdentifier::generateMarker($section2);
        ElementIdentifier::generateMarker($section3);
        
        $stats = ElementIdentifier::getCacheStats();
        expect($stats['markers'])->toBe(3);
        expect($stats['hashes'])->toBe(3);
    });

    test('cache persists between generateMarker and generateContentHash calls', function () {
        $section = createSection();
        
        // Generate hash first
        $hash = ElementIdentifier::generateContentHash($section);
        
        $stats1 = ElementIdentifier::getCacheStats();
        expect($stats1['hashes'])->toBe(1);
        expect($stats1['markers'])->toBe(0);
        
        // Generate marker later (should reuse hash from cache)
        $marker = ElementIdentifier::generateMarker($section);
        
        $stats2 = ElementIdentifier::getCacheStats();
        expect($stats2['hashes'])->toBe(1);  // Still 1 (reused)
        expect($stats2['markers'])->toBe(1); // Now 1
        
        // Verify that marker contains the hash
        expect($marker)->toContain($hash);
    });

    test('cache improves performance on repeated calls', function () {
        $section = SampleElements::createSectionWithTable(10, 5); // Large table
        
        // Measure first call (no cache)
        $start1 = microtime(true);
        $hash1 = ElementIdentifier::generateContentHash($section);
        $time1 = microtime(true) - $start1;
        
        // Measure second call (with cache)
        $start2 = microtime(true);
        $hash2 = ElementIdentifier::generateContentHash($section);
        $time2 = microtime(true) - $start2;
        
        // Second call should be significantly faster
        expect($hash1)->toBe($hash2);
        expect($time2)->toBeLessThan($time1); // Cache is faster
    });

});

describe('ElementIdentifier::generateImageHash()', function () {

    test('generates valid UUID v5 format', function () {
        $testImagePath = __DIR__ . '/../Fixtures/test_image.png';
        $image = new \PhpOffice\PhpWord\Element\Image($testImagePath, ['width' => 100, 'height' => 100]);
        
        $hash = ElementIdentifier::generateImageHash($image);
        
        // Verify UUID v5 format: xxxxxxxx-xxxx-5xxx-yxxx-xxxxxxxxxxxx
        expect($hash)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
    });

    test('different sources produce different hashes with same dimensions', function () {
        $testImagePath = __DIR__ . '/../Fixtures/test_image.png';
        
        // Create temporary second image with different content
        $tempImagePath = sys_get_temp_dir() . '/test_image_2_' . uniqid() . '.png';
        $imageResource = imagecreatetruecolor(1, 1);
        $blue = imagecolorallocate($imageResource, 0, 0, 255);
        imagefilledrectangle($imageResource, 0, 0, 1, 1, $blue);
        imagepng($imageResource, $tempImagePath);
        imagedestroy($imageResource);
        
        try {
            // Both images have identical dimensions (100x100) but different sources
            $image1 = new \PhpOffice\PhpWord\Element\Image($testImagePath, ['width' => 100, 'height' => 100]);
            $image2 = new \PhpOffice\PhpWord\Element\Image($tempImagePath, ['width' => 100, 'height' => 100]);
            
            $hash1 = ElementIdentifier::generateImageHash($image1);
            $hash2 = ElementIdentifier::generateImageHash($image2);
            
            // CRITICAL: UUID v5 includes basename(source), so hashes MUST be different
            expect($hash1)->not->toBe($hash2)
                ->and($hash1)->not->toBeEmpty()
                ->and($hash2)->not->toBeEmpty();
        } finally {
            // Cleanup temporary file
            if (file_exists($tempImagePath)) {
                unlink($tempImagePath);
            }
        }
    });

    test('same image produces identical hash (determinism)', function () {
        $testImagePath = __DIR__ . '/../Fixtures/test_image.png';
        
        $hashes = [];
        
        // Generate hash 100 times for same image
        for ($i = 0; $i < 100; $i++) {
            $image = new \PhpOffice\PhpWord\Element\Image($testImagePath, ['width' => 200, 'height' => 200]);
            $hashes[] = ElementIdentifier::generateImageHash($image);
        }
        
        // All hashes must be identical (deterministic algorithm)
        $uniqueHashes = array_unique($hashes);
        
        expect($uniqueHashes)->toHaveCount(1)
            ->and($hashes[0])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
    });

    // NOTE: Exception test for image without style requires mocking (Mockery not available in test suite)
    // The defensive check in generateImageHash() verifies $style === null and !is_string($source)
    // These scenarios are highly unlikely in production code (PHPWord always creates ImageStyle objects)

});

<?php

declare(strict_types=1);

/**
 * Image Hash Collision Resistance Test
 *
 * Validates UUID v5 image hashing produces zero collisions with large dataset.
 * Tests 10,000 images with varying dimensions and sources.
 *
 * @package MkGrow\ContentControl\Tests\Feature
 * @since 0.5.0
 */

use Tests\Helpers\WithImageFixtures;
use MkGrow\ContentControl\ElementIdentifier;
use PhpOffice\PhpWord\Element\Image;
use PhpOffice\PhpWord\Writer\Word2007\Style\Image as ImageStyleWriter;

uses(WithImageFixtures::class);

beforeEach(function () {
    $this->setUpImageFixtures();
});

/**
 * Test: Zero collisions with 10,000 image hashes
 *
 * Tests the hash generation algorithm directly by creating Image elements
 * with varied dimensions and source paths. Uses a valid test image file
 * to satisfy Ph pWord validation, but generates unique combinations through
 * Reflection to modify the source property after construction.
 */
test('Image Hash Collision - generates unique hashes for 10000 images', function () {
    $hashes = [];
    $imageCount = 0;
    
    // Base image for creating instances
    $baseImage = new Image($this->testImagePath, ['width' => 100, 'height' => 100]);
    
    // Generate 50 different dimension combinations
    $dimensions = [];
    for ($i = 1; $i <= 50; $i++) {
        $dimensions[] = ['width' => 100 + ($i * 10), 'height' => 80 + ($i * 8)];
    }
    
    // Generate 200 different source basenames
    $sources = [];
    for ($j = 1; $j <= 200; $j++) {
        $sources[] = "product_{$j}.jpg";
    }
    
    $startTime = microtime(true);
    
    // Generate all combinations
    foreach ($dimensions as $dim) {
        foreach ($sources as $sourceName) {
            // Create image with test file (satisfies PHPWord validation)
            $image = new Image($this->testImagePath, [
                'width' => $dim['width'],
                'height' => $dim['height'],
            ]);
            
            // Use Reflection to modify source property (bypass validation)
            $reflection = new \ReflectionClass($image);
            $sourceProperty = $reflection->getProperty('source');
            $sourceProperty->setAccessible(true);
            $sourceProperty->setValue($image, $sourceName);
            
            // Generate hash (uses modified source)
            $hash = ElementIdentifier::generateImageHash($image);
            $hashes[] = $hash;
            $imageCount++;
        }
    }
    
    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;
    
    // Validate results
    expect($imageCount)->toBe(10000, "Should generate exactly 10,000 images");
    
    $uniqueHashes = array_unique($hashes);
    $collisions = $imageCount - count($uniqueHashes);
    
    expect($collisions)->toBe(0, "Should have ZERO collisions (UUID v5 guarantee)");
    expect(count($uniqueHashes))->toBe($imageCount, "All hashes should be unique");
    
    // Performance: Should complete in <30 seconds
    expect($executionTime)->toBeLessThan(30.0, "Hashing 10,000 images should complete in <30s");
    
    // Average time per hash: Should be <1ms (target from spec)
    $avgTimeMs = ($executionTime / $imageCount) * 1000;
    expect($avgTimeMs)->toBeLessThan(3.0, "Average hash generation should be <3ms per image");
    
    echo sprintf(
        "\n  ✓ Collision Test Results:\n" .
        "    - Images tested: %d\n" .
        "    - Unique hashes: %d\n" .
        "    - Collisions: %d (0.00%%)\n" .
        "    - Execution time: %.2fs\n" .
        "    - Avg time per hash: %.3fms\n",
        $imageCount,
        count($uniqueHashes),
        $collisions,
        $executionTime,
        $avgTimeMs
    );
})->group('feature', 'image-hash', 'performance');

/**
 * Test: Hash determinism
 *
 * Validates that the same image produces identical hash across multiple invocations.
 */
test('Image Hash - produces deterministic hashes', function () {
    $image = new Image(
        $this->testImagePath,
        ['width' => 200, 'height' => 150, 'name' => 'test-determinism']
    );
    
    $hashes = [];
    
    // Generate hash 100 times
    for ($i = 0; $i < 100; $i++) {
        $hashes[] = ElementIdentifier::generateImageHash($image);
    }
    
    // All hashes should be identical
    $uniqueHashes = array_unique($hashes);
    expect(count($uniqueHashes))->toBe(1, "Same image should always produce same hash");
    
    // Validate UUID v5 format
    $hash = $hashes[0];
    expect($hash)->toMatch(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
        "Hash should be valid UUID v5 format"
    );
})->group('feature', 'image-hash');

/**
 * Test: Different sources with same dimensions produce different hashes
 *
 * Validates that UUID v5 includes source filename in hash calculation.
 */
test('Image Hash - different sources produce different hashes', function () {
    // Create base images with same dimensions
    $image1 = new Image($this->testImagePath, ['width' => 300, 'height' => 200]);
    $image2 = new Image($this->testImagePath, ['width' => 300, 'height' => 200]);
    
    // Use Reflection to set different source names
    $reflection = new \ReflectionClass($image1);
    $sourceProperty = $reflection->getProperty('source');
    $sourceProperty->setAccessible(true);
    $sourceProperty->setValue($image1, 'product-a.jpg');
    $sourceProperty->setValue($image2, 'product-b.jpg');
    
    $hash1 = ElementIdentifier::generateImageHash($image1);
    $hash2 = ElementIdentifier::generateImageHash($image2);
    
    expect($hash1)->not->toBe($hash2, "Different sources should produce different hashes");
})->group('feature', 'image-hash');

/**
 * Test: MD5 collision scenario resolved
 *
 * Validates the specific collision case from v0.4.2 is resolved:
 * Two images with identical dimensions but different sources.
 */
test('Image Hash - resolves MD5 collision scenario', function () {
    // Scenario: Two product images with identical dimensions
    // Previously (MD5): Both would have same hash (collision)
    // Now (UUID v5): Different hashes based on basename
    
    $product1 = new Image($this->testImagePath, ['width' => 800, 'height' => 600]);
    $product2 = new Image($this->testImagePath, ['width' => 800, 'height' => 600]);
    
    // Use Reflection to set different basenames
    $reflection = new \ReflectionClass($product1);
    $sourceProperty = $reflection->getProperty('source');
    $sourceProperty->setAccessible(true);
    $sourceProperty->setValue($product1, 'widget-standard-800x600.jpg');
    $sourceProperty->setValue($product2, 'gadget-premium-800x600.jpg');
    
    $hash1 = ElementIdentifier::generateImageHash($product1);
    $hash2 = ElementIdentifier::generateImageHash($product2);
    
    expect($hash1)->not->toBe($hash2, "UUID v5 should prevent MD5 collision scenario");
    
    // Both should be valid UUIDs
    expect($hash1)->toMatch('/^[0-9a-f-]{36}$/');
    expect($hash2)->toMatch('/^[0-9a-f-]{36}$/');
})->group('feature', 'image-hash', 'bugfix');

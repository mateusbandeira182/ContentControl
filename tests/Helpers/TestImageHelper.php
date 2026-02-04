<?php

declare(strict_types=1);

namespace MkGrow\ContentControl\Tests\Helpers;

/**
 * Helper for managing image fixtures in tests
 */
final class TestImageHelper
{
    /**
     * Returns the path to the test fixture image
     * 
     * @return string Absolute path to tests/Fixtures/test_image.png
     */
    public static function getTestImagePath(): string
    {
        return __DIR__ . '/../Fixtures/test_image.png';
    }
    
    /**
     * Ensures test fixture image exists on disk
     * 
     * This method validates that the committed fixture exists. If the file
     * is missing, throws an exception to fail fast and
     * ensure deterministic test behavior.
     * 
     * @return void
     * @throws \RuntimeException If fixture does not exist
     */
    public static function ensureTestImageExists(): void
    {
        $testImagePath = self::getTestImagePath();
        
        if (!file_exists($testImagePath)) {
            throw new \RuntimeException(sprintf(
                'Test image fixture not found at %s. The committed fixture file is required for deterministic test behavior.',
                $testImagePath
            ));
        }
    }
}

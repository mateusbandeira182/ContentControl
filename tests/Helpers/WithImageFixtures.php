<?php

declare(strict_types=1);

namespace Tests\Helpers;

/**
 * Trait for image hash collision test fixtures
 * 
 * Provides type-safe properties for fixture directory and test image path
 * Eliminates PHPStan warnings about undefined properties in Pest tests
 * 
 * @package Tests\Helpers
 */
trait WithImageFixtures
{
    /**
     * Test fixture directory path
     * 
     * @var string
     */
    private string $fixtureDir;

    /**
     * Path to test image file
     * 
     * @var string
     */
    private string $testImagePath;

    /**
     * Set up image fixtures directory and test image
     * 
     * @return void
     */
    protected function setUpImageFixtures(): void
    {
        // Ensure test fixtures exist
        $this->fixtureDir = __DIR__ . '/../Fixtures';
        if (!is_dir($this->fixtureDir)) {
            mkdir($this->fixtureDir, 0755, recursive: true);
        }
        
        // Create test image if not exists
        $this->testImagePath = $this->fixtureDir . '/test_image.png';
        if (!file_exists($this->testImagePath)) {
            // Create minimal 1x1 PNG
            $img = imagecreate(1, 1);
            imagepng($img, $this->testImagePath);
            imagedestroy($img);
        }
    }

    /**
     * Clean up test image fixtures
     * 
     * @return void
     */
    protected function tearDownImageFixtures(): void
    {
        // Optional: Clean up test image if needed
        // Currently preserved for performance (reused across tests)
    }
}

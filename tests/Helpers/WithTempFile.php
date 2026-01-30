<?php

declare(strict_types=1);

namespace Tests\Helpers;

/**
 * Trait for managing temporary files in tests
 * 
 * Provides type-safe property for $tempFile and automatic cleanup
 * Eliminates PHPStan warnings about undefined properties
 * 
 * @package Tests\Helpers
 */
trait WithTempFile
{
    /**
     * Temporary file path for testing
     * 
     * @var string
     */
    private string $tempFile;

    /**
     * Set up temporary file path
     * 
     * @param string|null $prefix Optional prefix for temp file
     * @return void
     */
    protected function setUpTempFile(?string $prefix = 'test_'): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), $prefix) . '.docx';
    }

    /**
     * Clean up temporary file
     * 
     * @return void
     */
    protected function tearDownTempFile(): void
    {
        if (isset($this->tempFile) && file_exists($this->tempFile)) {
            @unlink($this->tempFile);
        }
    }

    /**
     * Get temporary file path
     * 
     * @return string
     */
    protected function getTempFile(): string
    {
        return $this->tempFile;
    }
}

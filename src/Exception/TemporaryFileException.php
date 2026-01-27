<?php

namespace MkGrow\ContentControl\Exception;

/**
 * Exception thrown when temporary file operations fail
 * 
 * This typically occurs during cleanup when attempting to delete
 * temporary files created during document processing.
 * 
 * @package MkGrow\ContentControl\Exception
 */
class TemporaryFileException extends ContentControlException
{
    /**
     * Creates exception for temporary file operation failure
     * 
     * @param string $filePath Path to the temporary file
     * @param \Throwable|null $previous Previous exception for chaining
     */
    public function __construct(string $filePath, ?\Throwable $previous = null)
    {
        $message = "ContentControl: Failed to delete temporary file after multiple attempts: {$filePath}";
        parent::__construct($message, 0, $previous);
    }
}

<?php

namespace MkGrow\ContentControl\Exception;

/**
 * Exception thrown when ZipArchive operations fail
 * 
 * Provides context about ZIP-related errors during document manipulation.
 * Maps ZipArchive error codes to human-readable messages.
 * 
 * @package MkGrow\ContentControl\Exception
 */
class ZipArchiveException extends ContentControlException
{
    /**
     * Creates exception from ZipArchive error code
     * 
     * Maps ZipArchive::ER_* constants to descriptive error messages:
     * - ER_OK (0): No error (should not trigger exception)
     * - ER_MULTIDISK: Multi-disk ZIP archives not supported
     * - ER_RENAME: File rename failed
     * - ER_CLOSE: Error closing archive
     * - ER_SEEK: Seek error in file
     * - ER_READ: Read error
     * - ER_WRITE: Write error
     * - ER_CRC: CRC error (corrupted file)
     * - ER_ZIPCLOSED: Archive already closed
     * - ER_NOENT: No such file
     * - ER_EXISTS: File already exists
     * - ER_OPEN: Cannot open file
     * - ER_TMPOPEN: Failure to create temporary file
     * - ER_ZLIB: Zlib error
     * - ER_MEMORY: Memory allocation failed
     * - ER_CHANGED: Entry has been changed
     * - ER_COMPNOTSUPP: Compression method not supported
     * - ER_EOF: Premature end of file
     * - ER_INVAL: Invalid argument
     * - ER_NOZIP: Not a ZIP archive
     * - ER_INTERNAL: Internal error
     * - ER_INCONS: ZIP archive inconsistent
     * - ER_REMOVE: Cannot remove file
     * - ER_DELETED: Entry has been deleted
     * 
     * @param int $errorCode ZipArchive error code
     * @param string $filePath Path to the file being processed
     * @param \Throwable|null $previous Previous exception for chaining
     */
    public function __construct(int $errorCode, string $filePath, ?\Throwable $previous = null)
    {
        $message = $this->mapErrorCodeToMessage($errorCode, $filePath);
        parent::__construct($message, $errorCode, $previous);
    }

    /**
     * Maps ZipArchive error codes to descriptive messages
     * 
     * @param int $errorCode ZipArchive::ER_* constant
     * @param string $filePath File path for context
     * @return string Prefixed error message
     */
    private function mapErrorCodeToMessage(int $errorCode, string $filePath): string
    {
        $baseMessage = match ($errorCode) {
            \ZipArchive::ER_OK => 'No error',
            \ZipArchive::ER_MULTIDISK => 'Multi-disk ZIP archives not supported',
            \ZipArchive::ER_RENAME => 'Renaming temporary file failed',
            \ZipArchive::ER_CLOSE => 'Closing ZIP archive failed',
            \ZipArchive::ER_SEEK => 'Seek error in archive',
            \ZipArchive::ER_READ => 'Read error',
            \ZipArchive::ER_WRITE => 'Write error',
            \ZipArchive::ER_CRC => 'CRC error (file may be corrupted)',
            \ZipArchive::ER_ZIPCLOSED => 'ZIP archive was already closed',
            \ZipArchive::ER_NOENT => 'No such file in archive',
            \ZipArchive::ER_EXISTS => 'File already exists',
            \ZipArchive::ER_OPEN => 'Cannot open file',
            \ZipArchive::ER_TMPOPEN => 'Failure to create temporary file',
            \ZipArchive::ER_ZLIB => 'Zlib error',
            \ZipArchive::ER_MEMORY => 'Memory allocation failed',
            \ZipArchive::ER_CHANGED => 'Entry has been changed',
            \ZipArchive::ER_COMPNOTSUPP => 'Compression method not supported',
            \ZipArchive::ER_EOF => 'Premature end of file',
            \ZipArchive::ER_INVAL => 'Invalid argument',
            \ZipArchive::ER_NOZIP => 'Not a ZIP archive',
            \ZipArchive::ER_INTERNAL => 'Internal error',
            \ZipArchive::ER_INCONS => 'ZIP archive inconsistent',
            \ZipArchive::ER_REMOVE => 'Cannot remove file',
            \ZipArchive::ER_DELETED => 'Entry has been deleted',
            default => "Unknown error (code: {$errorCode})",
        };

        return "ContentControl: ZIP error - {$baseMessage}: {$filePath}";
    }
}

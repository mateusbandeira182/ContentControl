<?php

namespace MkGrow\ContentControl\Exception;

/**
 * Exception thrown when required document XML file is not found in DOCX archive
 * 
 * DOCX files are ZIP archives containing XML files. This exception is thrown
 * when a required XML file (typically word/document.xml) is missing from the archive.
 * 
 * @package MkGrow\ContentControl\Exception
 */
class DocumentNotFoundException extends ContentControlException
{
    /**
     * Creates exception for missing document XML
     * 
     * @param string $xmlPath Path within ZIP archive (e.g., 'word/document.xml')
     * @param string $docxPath Path to the DOCX file being processed
     * @param \Throwable|null $previous Previous exception for chaining
     */
    public function __construct(string $xmlPath, string $docxPath, ?\Throwable $previous = null)
    {
        $message = "ContentControl: Required XML file '{$xmlPath}' not found in document: {$docxPath}";
        parent::__construct($message, 0, $previous);
    }
}

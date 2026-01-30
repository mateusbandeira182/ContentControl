<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentProcessor;
use MkGrow\ContentControl\Exception\ZipArchiveException;
use MkGrow\ContentControl\Exception\DocumentNotFoundException;

describe('ContentProcessor Constructor', function () {
    it('throws exception for non-existent file', function () {
        new ContentProcessor('nonexistent.docx');
    })->throws(\InvalidArgumentException::class, 'File does not exist');

    it('throws exception for invalid ZIP file', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'invalid_') . '.docx';
        file_put_contents($tempFile, 'Not a ZIP file');

        try {
            new ContentProcessor($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    })->throws(ZipArchiveException::class);

    it('throws exception for ZIP without document.xml', function () {
        // Create empty ZIP
        $tempFile = tempnam(sys_get_temp_dir(), 'empty_') . '.docx';
        $zip = new ZipArchive();
        $zip->open($tempFile, ZipArchive::CREATE);
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?>');
        $zip->close();

        try {
            new ContentProcessor($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    })->throws(DocumentNotFoundException::class);

    it('throws exception for malformed XML in document.xml', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'malformed_') . '.docx';
        $zip = new ZipArchive();
        $zip->open($tempFile, ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', '<invalid>xml<unclosed>');
        $zip->close();

        try {
            new ContentProcessor($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    })->throws(\RuntimeException::class, 'Failed to parse XML');

    it('successfully loads valid DOCX file', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'valid_') . '.docx';
        createValidDocx($tempFile);

        try {
            $processor = new ContentProcessor($tempFile);
            expect($processor)->toBeInstanceOf(ContentProcessor::class);
            unset($processor); // Force destructor call to close ZIP
            
            // Small delay for Windows file system
            if (PHP_OS_FAMILY === 'Windows') {
                usleep(100000); // 100ms
            }
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    });

    it('throws exception for non-readable file', function () {
        if (PHP_OS_FAMILY === 'Windows') {
            expect(true)->toBeTrue(); // Skip test on Windows
            return;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'readonly_') . '.docx';
        createValidDocx($tempFile);
        chmod($tempFile, 0000);

        try {
            new ContentProcessor($tempFile);
        } finally {
            chmod($tempFile, 0644);
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    })->throws(\InvalidArgumentException::class, 'File is not readable');
});

/**
 * Helper function to create valid minimal DOCX
 *
 * @param string $path File path
 * @return void
 */
function createValidDocx(string $path): void
{
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:r>
                <w:t>Test document</w:t>
            </w:r>
        </w:p>
    </w:body>
</w:document>
XML;

    $contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', $xml);
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
    $zip->close();
}

<?php

use MkGrow\ContentControl\Exception\ContentControlException;
use MkGrow\ContentControl\Exception\DocumentNotFoundException;
use MkGrow\ContentControl\Exception\TemporaryFileException;
use MkGrow\ContentControl\Exception\ZipArchiveException;

describe('Exceptions', function () {
    describe('ContentControlException', function () {
        it('can be instantiated', function () {
            $exception = new ContentControlException('Test message');
            
            expect($exception)
                ->toBeInstanceOf(\RuntimeException::class)
                ->and($exception->getMessage())->toBe('Test message');
        });

        it('supports exception chaining', function () {
            $previous = new \Exception('Previous exception');
            $exception = new ContentControlException('Test message', 0, $previous);
            
            expect($exception->getPrevious())->toBe($previous);
        });
    });

    describe('DocumentNotFoundException', function () {
        it('creates exception with proper message', function () {
            $exception = new DocumentNotFoundException('word/document.xml', '/path/to/document.docx');
            
            expect($exception)
                ->toBeInstanceOf(ContentControlException::class)
                ->and($exception->getMessage())
                ->toContain('word/document.xml')
                ->toContain('/path/to/document.docx')
                ->toContain('not found');
        });

        it('supports exception chaining', function () {
            $previous = new \Exception('Underlying error');
            $exception = new DocumentNotFoundException('word/document.xml', '/path/to/doc.docx', $previous);
            
            expect($exception->getPrevious())->toBe($previous);
        });

        it('includes full path in message', function () {
            $exception = new DocumentNotFoundException('word/styles.xml', 'C:/Users/Test/document.docx');
            
            expect($exception->getMessage())
                ->toBe("ContentControl: Required XML file 'word/styles.xml' not found in document: C:/Users/Test/document.docx");
        });
    });

    describe('TemporaryFileException', function () {
        it('creates exception with proper message', function () {
            $exception = new TemporaryFileException('/tmp/phpword_12345.docx');
            
            expect($exception)
                ->toBeInstanceOf(ContentControlException::class)
                ->and($exception->getMessage())
                ->toContain('/tmp/phpword_12345.docx')
                ->toContain('Failed to delete temporary file');
        });

        it('supports exception chaining', function () {
            $previous = new \Exception('Permission denied');
            $exception = new TemporaryFileException('/tmp/test.docx', $previous);
            
            expect($exception->getPrevious())->toBe($previous);
        });

        it('mentions multiple attempts', function () {
            $exception = new TemporaryFileException('/tmp/locked-file.docx');
            
            expect($exception->getMessage())
                ->toContain('after multiple attempts');
        });
    });

    describe('ZipArchiveException', function () {
        it('maps ER_OPEN error code', function () {
            $exception = new ZipArchiveException(\ZipArchive::ER_OPEN, '/path/to/file.docx');
            
            expect($exception)
                ->toBeInstanceOf(ContentControlException::class)
                ->and($exception->getMessage())
                ->toContain('Cannot open file')
                ->toContain('/path/to/file.docx')
                ->and($exception->getCode())->toBe(\ZipArchive::ER_OPEN);
        });

        it('maps ER_NOZIP error code', function () {
            $exception = new ZipArchiveException(\ZipArchive::ER_NOZIP, '/path/to/file.txt');
            
            expect($exception->getMessage())
                ->toContain('Not a ZIP archive')
                ->toContain('/path/to/file.txt');
        });

        it('maps ER_READ error code', function () {
            $exception = new ZipArchiveException(\ZipArchive::ER_READ, '/corrupted.docx');
            
            expect($exception->getMessage())
                ->toContain('Read error')
                ->toContain('/corrupted.docx');
        });

        it('maps ER_WRITE error code', function () {
            $exception = new ZipArchiveException(\ZipArchive::ER_WRITE, '/readonly.docx');
            
            expect($exception->getMessage())
                ->toContain('Write error');
        });

        it('maps ER_CRC error code', function () {
            $exception = new ZipArchiveException(\ZipArchive::ER_CRC, '/corrupted.docx');
            
            expect($exception->getMessage())
                ->toContain('CRC error')
                ->toContain('corrupted');
        });

        it('maps ER_NOENT error code', function () {
            $exception = new ZipArchiveException(\ZipArchive::ER_NOENT, '/missing.docx');
            
            expect($exception->getMessage())
                ->toContain('No such file');
        });

        it('maps ER_EXISTS error code', function () {
            $exception = new ZipArchiveException(\ZipArchive::ER_EXISTS, '/duplicate.docx');
            
            expect($exception->getMessage())
                ->toContain('File already exists');
        });

        it('maps ER_TMPOPEN error code', function () {
            $exception = new ZipArchiveException(\ZipArchive::ER_TMPOPEN, '/test.docx');
            
            expect($exception->getMessage())
                ->toContain('Failure to create temporary file');
        });

        it('maps unknown error codes', function () {
            $exception = new ZipArchiveException(99999, '/test.docx');
            
            expect($exception->getMessage())
                ->toContain('Unknown error')
                ->toContain('99999');
        });

        it('supports exception chaining', function () {
            $previous = new \Exception('Underlying issue');
            $exception = new ZipArchiveException(\ZipArchive::ER_OPEN, '/test.docx', $previous);
            
            expect($exception->getPrevious())->toBe($previous);
        });

        it('maps all standard ZipArchive error codes', function () {
            $errorCodes = [
                \ZipArchive::ER_OK => 'No error',
                \ZipArchive::ER_MULTIDISK => 'Multi-disk',
                \ZipArchive::ER_RENAME => 'Renaming',
                \ZipArchive::ER_CLOSE => 'Closing',
                \ZipArchive::ER_SEEK => 'Seek error',
                \ZipArchive::ER_ZLIB => 'Zlib error',
                \ZipArchive::ER_MEMORY => 'Memory allocation',
                \ZipArchive::ER_CHANGED => 'Entry has been changed',
                \ZipArchive::ER_COMPNOTSUPP => 'Compression method not supported',
                \ZipArchive::ER_EOF => 'Premature end of file',
                \ZipArchive::ER_INVAL => 'Invalid argument',
                \ZipArchive::ER_INTERNAL => 'Internal error',
                \ZipArchive::ER_INCONS => 'ZIP archive inconsistent',
                \ZipArchive::ER_REMOVE => 'Cannot remove file',
                \ZipArchive::ER_DELETED => 'Entry has been deleted',
            ];

            foreach ($errorCodes as $code => $expectedText) {
                $exception = new ZipArchiveException($code, '/test.docx');
                expect($exception->getMessage())->toContain('ContentControl: ZIP error');
            }
        });
    });
});

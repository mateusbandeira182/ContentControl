<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentProcessor;
use PhpOffice\PhpWord\PhpWord;

beforeEach(function () {
    $this->tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
});

afterEach(function () {
    if (isset($this->tempFile) && file_exists($this->tempFile)) {
        unlink($this->tempFile);
    }
});

describe('ContentProcessor replaceContent() - String Values', function () {
    it('replaces content with simple text', function () {
        createDocxWithSdt($this->tempFile, 'customer-name');
        $processor = new ContentProcessor($this->tempFile);

        $result = $processor->replaceContent('customer-name', 'Acme Corporation LTDA');

        expect($result)->toBeTrue();

        $processor->save();
        unset($processor);
        if (PHP_OS_FAMILY === 'Windows') {
            usleep(100000);
        }

        // Verify modified content
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('<w:t>Acme Corporation LTDA</w:t>')
            ->and($xml)->toContain('<w:tag w:val="customer-name"/>');
    });

    it('preserves xml:space attribute for text with leading/trailing spaces', function () {
        createDocxWithSdt($this->tempFile, 'test-tag');
        $processor = new ContentProcessor($this->tempFile);

        $processor->replaceContent('test-tag', '  Text with spaces  ');
        $processor->save();
        
        unset($processor);
        if (PHP_OS_FAMILY === 'Windows') {
            usleep(100000);
        }

        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('xml:space="preserve"')
            ->and($xml)->toContain('  Text with spaces  ');
    });

    it('handles empty string', function () {
        createDocxWithSdt($this->tempFile, 'test-tag');
        $processor = new ContentProcessor($this->tempFile);

        $result = $processor->replaceContent('test-tag', '');

        expect($result)->toBeTrue();

        $processor->save();
        unset($processor);
        if (PHP_OS_FAMILY === 'Windows') {
            usleep(100000);
        }

        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        // Should have empty text node
        expect($xml)->toContain('<w:t></w:t>');
    });

    it('handles multi-line text', function () {
        createDocxWithSdt($this->tempFile, 'test-tag');
        $processor = new ContentProcessor($this->tempFile);

        $text = "Line 1\nLine 2\nLine 3";
        $processor->replaceContent('test-tag', $text);
        $processor->save();
        
        unset($processor);
        if (PHP_OS_FAMILY === 'Windows') {
            usleep(100000);
        }

        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('Line 1')
            ->and($xml)->toContain('Line 2')
            ->and($xml)->toContain('Line 3');
    });

    it('returns false for non-existent tag', function () {
        createDocxWithSdt($this->tempFile, 'existing-tag');
        $processor = new ContentProcessor($this->tempFile);

        $result = $processor->replaceContent('nonexistent-tag', 'Text');

        expect($result)->toBeFalse();
        
        unset($processor);
        if (PHP_OS_FAMILY === 'Windows') {
            usleep(100000);
        }
    });

    it('handles special XML characters', function () {
        createDocxWithSdt($this->tempFile, 'test-tag');
        $processor = new ContentProcessor($this->tempFile);

        $text = 'Text with <special> & "characters"';
        $processor->replaceContent('test-tag', $text);
        $processor->save();
        
        unset($processor);
        if (PHP_OS_FAMILY === 'Windows') {
            usleep(100000);
        }

        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        // Should be properly escaped
        expect($xml)->toContain('&lt;special&gt;')
            ->and($xml)->toContain('&amp;');
    });
});

describe('ContentProcessor replaceContent() - PHPWord Elements', function () {
    it('replaces content with Table element', function () {
        createDocxWithSdt($this->tempFile, 'invoice-items');
        $processor = new ContentProcessor($this->tempFile);

        // Create table
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Item');
        $table->addCell(1000)->addText('Qty');
        $table->addRow();
        $table->addCell(2000)->addText('Notebook');
        $table->addCell(1000)->addText('2');

        $result = $processor->replaceContent('invoice-items', $table);

        expect($result)->toBeTrue();

        $processor->save();
        unset($processor);
        if (PHP_OS_FAMILY === 'Windows') {
            usleep(100000);
        }

        // Verify XML
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('<w:tbl>')
            ->and($xml)->toContain('Notebook')
            ->and($xml)->toContain('Item');
    });

    it('replaces content with Text element', function () {
        createDocxWithSdt($this->tempFile, 'test-tag');
        $processor = new ContentProcessor($this->tempFile);

        // Create Text element with formatting
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $text = $section->addText('Bold Text', ['bold' => true]);

        $processor->replaceContent('test-tag', $text);
        $processor->save();
        
        unset($processor);
        if (PHP_OS_FAMILY === 'Windows') {
            usleep(100000);
        }

        // Verify formatting preserved
        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('w:b')
            ->and($xml)->toContain('Bold Text');
    });

    it('replaces content with TextRun element', function () {
        createDocxWithSdt($this->tempFile, 'test-tag');
        $processor = new ContentProcessor($this->tempFile);

        // Create TextRun with multiple formatted parts
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $textRun = $section->addTextRun();
        $textRun->addText('Normal ', []);
        $textRun->addText('Bold', ['bold' => true]);
        $textRun->addText(' Italic', ['italic' => true]);

        $processor->replaceContent('test-tag', $textRun);
        $processor->save();
        
        unset($processor);
        if (PHP_OS_FAMILY === 'Windows') {
            usleep(100000);
        }

        $zip = new ZipArchive();
        $zip->open($this->tempFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('Normal ')
            ->and($xml)->toContain('w:b')
            ->and($xml)->toContain('w:i');
    });
});

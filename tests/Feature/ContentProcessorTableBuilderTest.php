<?php

declare(strict_types=1);

use MkGrow\ContentControl\Bridge\TableBuilder;
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\ContentProcessor;
use MkGrow\ContentControl\Exception\ContentControlException;
use PhpOffice\PhpWord\Element\AbstractElement;

/**
 * Feature tests for ContentProcessor::replaceContent() with TableBuilder.
 *
 * Validates SDT-aware table injection, regression guards for existing
 * string/AbstractElement paths, and header/footer support.
 *
 * @since 0.7.0
 */
describe('ContentProcessor::replaceContent() with TableBuilder', function (): void {
    /**
     * Create a template DOCX with a single SDT placeholder in the body.
     */
    function createTemplateWithSdt(string $path, string $tag): void
    {
        createDocxWithSdt($path, $tag);
    }

    /**
     * Create a template DOCX with SDT in header via ContentControl.
     */
    function createTemplateWithHeaderSdt(string $path, string $tag): void
    {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText('Body content');
        $header = $section->addHeader();
        $headerText = $header->addText('Header placeholder');
        $cc->addContentControl($headerText, ['tag' => $tag, 'alias' => 'Header SDT']);
        $cc->save($path);
    }

    /**
     * Create a template DOCX with SDT in footer via ContentControl.
     */
    function createTemplateWithFooterSdt(string $path, string $tag): void
    {
        $cc = new ContentControl();
        $section = $cc->addSection();
        $section->addText('Body content');
        $footer = $section->addFooter();
        $footerText = $footer->addText('Footer placeholder');
        $cc->addContentControl($footerText, ['tag' => $tag, 'alias' => 'Footer SDT']);
        $cc->save($path);
    }

    it('[CP-TB-01] replaceContent with TableBuilder returns true and inserts w:tbl', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'cp_tb01_') . '.docx';
        $outputFile = tempnam(sys_get_temp_dir(), 'cp_out_') . '.docx';
        createTemplateWithSdt($tempFile, 'table-placeholder');

        $processor = new ContentProcessor($tempFile);

        $builder = new TableBuilder();
        $row = $builder->addRow();
        $row->addCell(3000)->addText('Product');
        $row->addCell(2000)->addText('Price');

        $result = $processor->replaceContent('table-placeholder', $builder);
        $processor->save($outputFile);

        expect($result)->toBeTrue();

        // Verify table was injected
        $zip = new ZipArchive();
        $zip->open($outputFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('<w:tbl');
        expect($xml)->toContain('<w:sdt>');  // SDT wrapper preserved

        safeUnlink($tempFile);
        safeUnlink($outputFile);
    });

    it('[CP-TB-02] preserves run-level SDTs from TableBuilder', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'cp_tb02_') . '.docx';
        $outputFile = tempnam(sys_get_temp_dir(), 'cp_out_') . '.docx';
        createTemplateWithSdt($tempFile, 'run-level-table');

        $processor = new ContentProcessor($tempFile);

        // Build table with run-level SDT
        $builder = new TableBuilder();
        $cc = $builder->getContentControl();
        $section = $cc->addSection();
        $table = $section->addTable();
        $row = $table->addRow();
        $cell = $row->addCell(3000);
        $text = $cell->addText('Run SDT Value');

        // Set builder's table for serialization
        $reflection = new ReflectionClass($builder);
        $tableProperty = $reflection->getProperty('table');
        $tableProperty->setAccessible(true);
        $tableProperty->setValue($builder, $table);

        $cc->addContentControl($text, [
            'tag' => 'run-cell',
            'runLevel' => true,
            'inlineLevel' => true,
        ]);

        $result = $processor->replaceContent('run-level-table', $builder);
        $processor->save($outputFile);

        expect($result)->toBeTrue();

        $zip = new ZipArchive();
        $zip->open($outputFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('run-cell');
        expect($xml)->toContain('<w:sdt>');

        safeUnlink($tempFile);
        safeUnlink($outputFile);
    });

    it('[CP-TB-03] preserves inline-level SDTs from TableBuilder', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'cp_tb03_') . '.docx';
        $outputFile = tempnam(sys_get_temp_dir(), 'cp_out_') . '.docx';
        createTemplateWithSdt($tempFile, 'inline-table');

        $processor = new ContentProcessor($tempFile);

        $builder = new TableBuilder();
        $row = $builder->addRow();
        $row->addCell(3000)
            ->withContentControl(['tag' => 'inline-cell', 'alias' => 'Inline Cell'])
            ->addText('Inline Value');

        $result = $processor->replaceContent('inline-table', $builder);
        $processor->save($outputFile);

        expect($result)->toBeTrue();

        $zip = new ZipArchive();
        $zip->open($outputFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('inline-cell');

        safeUnlink($tempFile);
        safeUnlink($outputFile);
    });

    it('[CP-TB-04] injects table with block-level SDT wrapping', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'cp_tb04_') . '.docx';
        $outputFile = tempnam(sys_get_temp_dir(), 'cp_out_') . '.docx';
        createTemplateWithSdt($tempFile, 'block-table');

        $processor = new ContentProcessor($tempFile);

        $builder = new TableBuilder();
        $row = $builder->addRow();
        $row->addCell(3000)->addText('Block Cell');

        $result = $processor->replaceContent('block-table', $builder);
        $processor->save($outputFile);

        expect($result)->toBeTrue();

        $zip = new ZipArchive();
        $zip->open($outputFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('<w:tbl');

        safeUnlink($tempFile);
        safeUnlink($outputFile);
    });

    it('[CP-TB-05] returns false for non-existent tag', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'cp_tb05_') . '.docx';
        createTemplateWithSdt($tempFile, 'existing-tag');

        $processor = new ContentProcessor($tempFile);

        $builder = new TableBuilder();
        $row = $builder->addRow();
        $row->addCell(3000)->addText('Test');

        $result = $processor->replaceContent('non-existent-tag', $builder);

        expect($result)->toBeFalse();

        unset($processor);
        safeUnlink($tempFile);
    });

    it('[CP-TB-06] throws ContentControlException for empty builder', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'cp_tb06_') . '.docx';
        createTemplateWithSdt($tempFile, 'empty-builder');

        $processor = new ContentProcessor($tempFile);
        $builder = new TableBuilder();

        expect(fn () => $processor->replaceContent('empty-builder', $builder))
            ->toThrow(ContentControlException::class, 'No table to serialize');

        unset($processor);
        safeUnlink($tempFile);
    });

    it('[CP-TB-07] string value still works (regression guard)', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'cp_tb07_') . '.docx';
        $outputFile = tempnam(sys_get_temp_dir(), 'cp_out_') . '.docx';
        createTemplateWithSdt($tempFile, 'string-test');

        $processor = new ContentProcessor($tempFile);
        $result = $processor->replaceContent('string-test', 'New text value');
        $processor->save($outputFile);

        expect($result)->toBeTrue();

        $zip = new ZipArchive();
        $zip->open($outputFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('New text value');

        safeUnlink($tempFile);
        safeUnlink($outputFile);
    });

    it('[CP-TB-08] AbstractElement value still works (regression guard)', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'cp_tb08_') . '.docx';
        $outputFile = tempnam(sys_get_temp_dir(), 'cp_out_') . '.docx';
        createTemplateWithSdt($tempFile, 'element-test');

        $processor = new ContentProcessor($tempFile);

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow()->addCell(3000)->addText('Element table');

        $result = $processor->replaceContent('element-test', $table);
        $processor->save($outputFile);

        expect($result)->toBeTrue();

        $zip = new ZipArchive();
        $zip->open($outputFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('<w:tbl');

        safeUnlink($tempFile);
        safeUnlink($outputFile);
    });

    it('[CP-TB-09] works with SDT in header', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'cp_tb09_') . '.docx';
        $outputFile = tempnam(sys_get_temp_dir(), 'cp_out_') . '.docx';
        createTemplateWithHeaderSdt($tempFile, 'header-table');

        $processor = new ContentProcessor($tempFile);

        $builder = new TableBuilder();
        $row = $builder->addRow();
        $row->addCell(3000)->addText('Header Table Cell');

        $result = $processor->replaceContent('header-table', $builder);
        $processor->save($outputFile);

        expect($result)->toBeTrue();

        // Check header XML for table
        $zip = new ZipArchive();
        $zip->open($outputFile);

        // Find header file
        $headerXml = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_contains((string) $name, 'header') && str_ends_with((string) $name, '.xml')) {
                $headerXml = $zip->getFromName((string) $name);
                break;
            }
        }
        $zip->close();

        expect($headerXml)->not->toBeNull();
        expect($headerXml)->toContain('<w:tbl');

        safeUnlink($tempFile);
        safeUnlink($outputFile);
    });

    it('[CP-TB-10] works with SDT in footer', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'cp_tb10_') . '.docx';
        $outputFile = tempnam(sys_get_temp_dir(), 'cp_out_') . '.docx';
        createTemplateWithFooterSdt($tempFile, 'footer-table');

        $processor = new ContentProcessor($tempFile);

        $builder = new TableBuilder();
        $row = $builder->addRow();
        $row->addCell(3000)->addText('Footer Table Cell');

        $result = $processor->replaceContent('footer-table', $builder);
        $processor->save($outputFile);

        expect($result)->toBeTrue();

        // Check footer XML for table
        $zip = new ZipArchive();
        $zip->open($outputFile);

        $footerXml = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_contains((string) $name, 'footer') && str_ends_with((string) $name, '.xml')) {
                $footerXml = $zip->getFromName((string) $name);
                break;
            }
        }
        $zip->close();

        expect($footerXml)->not->toBeNull();
        expect($footerXml)->toContain('<w:tbl');

        safeUnlink($tempFile);
        safeUnlink($outputFile);
    });

    it('[CP-TB-11] generated DOCX has valid XML after TableBuilder replacement', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'cp_tb11_') . '.docx';
        $outputFile = tempnam(sys_get_temp_dir(), 'cp_out_') . '.docx';
        createTemplateWithSdt($tempFile, 'valid-xml-test');

        $processor = new ContentProcessor($tempFile);

        $builder = new TableBuilder();
        $row = $builder->addRow();
        $row->addCell(3000)->addText('Validation');
        $row->addCell(2000)->addText('Test');

        $processor->replaceContent('valid-xml-test', $builder);
        $processor->save($outputFile);

        $zip = new ZipArchive();
        $zip->open($outputFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toBeValidXml();

        safeUnlink($tempFile);
        safeUnlink($outputFile);
    });
});

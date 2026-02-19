<?php

declare(strict_types=1);

use MkGrow\ContentControl\Bridge\TableBuilder;
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Exception\ContentControlException;

/**
 * Unit tests for TableBuilder::serializeWithSdts().
 *
 * Validates XML output, SDT preservation, error handling,
 * temp file cleanup, and namespace hygiene.
 *
 * @since 0.7.0
 */
describe('TableBuilder::serializeWithSdts()', function (): void {
    it('[TB-SER-01] returns valid XML containing w:tbl', function (): void {
        $builder = new TableBuilder();
        $row = $builder->addRow();
        $row->addCell(3000)->addText('Product');
        $row->addCell(2000)->addText('Price');

        $xml = $builder->serializeWithSdts();

        expect($xml)->toBeString();
        expect($xml)->toContain('<w:tbl');
        // Wrap in a root element for XML validation
        $wrappedXml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . $xml . '</w:body></w:document>';
        expect($wrappedXml)->toBeValidXml();
    });

    it('[TB-SER-02] preserves SDTs from addContentControl() in output', function (): void {
        $builder = new TableBuilder();
        $row = $builder->addRow();
        $row->addCell(3000)
            ->withContentControl(['tag' => 'cell-sdt', 'alias' => 'Cell SDT'])
            ->addText('Value');

        $xml = $builder->serializeWithSdts();

        expect($xml)->toContain('<w:sdt>');
        expect($xml)->toContain('<w:sdtPr>');
        expect($xml)->toContain('cell-sdt');
    });

    it('[TB-SER-03] preserves run-level SDTs in output', function (): void {
        $builder = new TableBuilder();
        $cc = $builder->getContentControl();
        $section = $cc->addSection();
        $table = $section->addTable();
        $row = $table->addRow();
        $cell = $row->addCell(3000);
        $text = $cell->addText('Run level text');

        // Set builder's table via reflection so serializeWithSdts() can find it
        $reflection = new ReflectionClass($builder);
        $tableProperty = $reflection->getProperty('table');
        $tableProperty->setAccessible(true);
        $tableProperty->setValue($builder, $table);

        // Register run-level SDT on the builder's own ContentControl
        $cc->addContentControl($text, [
            'tag' => 'run-sdt',
            'runLevel' => true,
            'inlineLevel' => true,
        ]);

        $xml = $builder->serializeWithSdts();

        expect($xml)->toContain('<w:sdt>');
        expect($xml)->toContain('run-sdt');
    });

    it('[TB-SER-04] throws ContentControlException when table is null', function (): void {
        $builder = new TableBuilder();

        expect(fn () => $builder->serializeWithSdts())
            ->toThrow(ContentControlException::class, 'No table to serialize');
    });

    it('[TB-SER-05] cleans up temp file after successful serialization', function (): void {
        $builder = new TableBuilder();
        $row = $builder->addRow();
        $row->addCell(3000)->addText('Test');

        // Use reflection to get the temp file path that would be generated
        $reflection = new ReflectionClass($builder);
        $method = $reflection->getMethod('getTempFilePath');
        $method->setAccessible(true);
        $expectedTempPath = $method->invoke($builder);

        $builder->serializeWithSdts();

        // Temp file should be cleaned up
        expect(file_exists($expectedTempPath))->toBeFalse();
    });

    it('[TB-SER-06] cleans up temp file even when extraction fails', function (): void {
        $builder = new TableBuilder();

        // Create a builder with a table from a different ContentControl instance
        // This will cause extractTableXmlWithSdts() to fail (hash mismatch)
        $externalCc = new ContentControl();
        $section = $externalCc->addSection();
        $externalTable = $section->addTable();
        $externalTable->addRow()->addCell(3000)->addText('External');

        // Use reflection to set the table to the external one
        $reflection = new ReflectionClass($builder);
        $tableProperty = $reflection->getProperty('table');
        $tableProperty->setAccessible(true);
        $tableProperty->setValue($builder, $externalTable);

        // Get the temp path before the call
        $method = $reflection->getMethod('getTempFilePath');
        $method->setAccessible(true);
        $expectedTempPath = $method->invoke($builder);

        try {
            $builder->serializeWithSdts();
        } catch (\Throwable) {
            // Expected to throw
        }

        // Temp file should be cleaned up regardless
        expect(file_exists($expectedTempPath))->toBeFalse();
    });

    it('[TB-SER-07] output contains no redundant xmlns:w declarations', function (): void {
        $builder = new TableBuilder();
        $row = $builder->addRow();
        $row->addCell(3000)->addText('Clean NS');

        $xml = $builder->serializeWithSdts();

        // The output should not contain redundant xmlns:w declarations
        expect($xml)->not->toMatch('/xmlns:w="/');
    });
});

<?php

declare(strict_types=1);

use MkGrow\ContentControl\Bridge\TableBuilder;
use MkGrow\ContentControl\ContentProcessor;
use MkGrow\ContentControl\Exception\ContentControlException;

describe('FluentTableBuilderTest - Fluent API Integration', function () {
    describe('End-to-End Table Creation', function () {
        it('creates simple table with fluent API and saves successfully', function () {
            $builder = new TableBuilder();

            // Create table using fluent API
            $builder->addRow()
                ->addCell(3000)->addText('Product')->end()
                ->addCell(2000)->addText('Price')->end()
                ->end();

            $builder->addRow()
                ->addCell(3000)->addText('Widget A')->end()
                ->addCell(2000)->addText('$9.99')->end()
                ->end();

            // Save to temp file
            $tempFile = tempnam(sys_get_temp_dir(), 'fluent_test_') . '.docx';
            $builder->getContentControl()->save($tempFile);

            // Verify file exists
            expect(file_exists($tempFile))->toBeTrue();

            // Extract and verify XML contains table
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            expect($xml)->toBeValidXml();
            expect($xml)->toContain('<w:tbl>');
            expect($xml)->toContain('Product');
            expect($xml)->toContain('Widget A');

            // Cleanup
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        });

        it('creates table with row styling', function () {
            $builder = new TableBuilder();

            $builder->addRow(720, ['tblHeader' => true])
                ->addCell(3000)->addText('Column 1')->end()
                ->addCell(3000)->addText('Column 2')->end()
                ->end();

            $tempFile = tempnam(sys_get_temp_dir(), 'fluent_style_test_') . '.docx';
            $builder->getContentControl()->save($tempFile);

            expect(file_exists($tempFile))->toBeTrue();

            // Cleanup
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        });

        it('creates table with cell styling', function () {
            $builder = new TableBuilder();

            $builder->addRow()
                ->addCell(3000, ['bgColor' => 'CCCCCC'])
                    ->addText('Header Cell', ['bold' => true])
                    ->end()
                ->addCell(3000, ['valign' => 'center'])
                    ->addText('Regular Cell')
                    ->end()
                ->end();

            $tempFile = tempnam(sys_get_temp_dir(), 'fluent_cell_style_test_') . '.docx';
            $builder->getContentControl()->save($tempFile);

            expect(file_exists($tempFile))->toBeTrue();

            // Cleanup
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        });
    });

    describe('SDT Preservation', function () {
        it('preserves cell-level SDTs created via withContentControl', function () {
            $builder = new TableBuilder();

            $builder->addRow()
                ->addCell(3000)
                    ->withContentControl(['tag' => 'product-name', 'alias' => 'Product Name'])
                    ->addText('Sample Product')
                    ->end()
                ->addCell(2000)
                    ->withContentControl(['tag' => 'product-price', 'alias' => 'Product Price'])
                    ->addText('$19.99')
                    ->end()
                ->end();

            $tempFile = tempnam(sys_get_temp_dir(), 'fluent_sdt_test_') . '.docx';
            $builder->getContentControl()->save($tempFile);

            // Verify SDTs in XML
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            expect($xml)->toBeValidXml();
            expect($xml)->toContain('<w:sdt>');
            expect($xml)->toContain('<w:tag w:val="product-name"/>');
            expect($xml)->toContain('<w:alias w:val="Product Name"/>');
            expect($xml)->toContain('<w:tag w:val="product-price"/>');

            // Cleanup
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        });

        it('preserves multiple cell-level SDTs in same table', function () {
            $builder = new TableBuilder();

            $builder->addRow()
                ->addCell(3000)
                    ->withContentControl(['tag' => 'cell-1'])
                    ->addText('Data 1')
                    ->end()
                ->addCell(2000)
                    ->withContentControl(['tag' => 'cell-2'])
                    ->addText('Data 2')
                    ->end()
                ->end();

            $tempFile = tempnam(sys_get_temp_dir(), 'fluent_multi_cell_sdt_test_') . '.docx';
            $builder->getContentControl()->save($tempFile);

            // Verify cell SDTs in XML
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            expect($xml)->toBeValidXml();
            expect($xml)->toContain('<w:tag w:val="cell-1"/>');
            expect($xml)->toContain('<w:tag w:val="cell-2"/>');

            // Cleanup
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        });
    });

    describe('injectInto() Method', function () {
        it('injects fluent table into template SDT', function () {
            // Create template MANUALLY (not using ContentControl.addContentControl to avoid SDTInjector bug)
            // This mimics how tests/Helpers/ContentProcessorTestHelper.php creates templates
            $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p><w:r><w:t>Before table</w:t></w:r></w:p>
        <w:sdt>
            <w:sdtPr>
                <w:id w:val="12345678"/>
                <w:tag w:val="table-placeholder"/>
            </w:sdtPr>
            <w:sdtContent>
                <w:p><w:r><w:t>Placeholder</w:t></w:r></w:p>
            </w:sdtContent>
        </w:sdt>
        <w:p><w:r><w:t>After table</w:t></w:r></w:p>
    </w:body>
</w:document>
XML;

            $templatePath = tempnam(sys_get_temp_dir(), 'fluent_template_') . '.docx';
            createDocxFromXml($templatePath, $xml);

            // Create table with fluent API
            $builder = new TableBuilder();
            $builder->addRow()
                ->addCell(3000)->addText('Name')->end()
                ->addCell(2000)->addText('Value')->end()
                ->end();
            $builder->addRow()
                ->addCell(3000)->addText('Item A')->end()
                ->addCell(2000)->addText('123')->end()
                ->end();

            // Inject into template
            $processor = new ContentProcessor($templatePath);
            $builder->injectInto($processor, 'table-placeholder');

            $outputPath = tempnam(sys_get_temp_dir(), 'fluent_output_') . '.docx';
            $processor->save($outputPath);

            // Verify output
            expect(file_exists($outputPath))->toBeTrue();

            $zip = new ZipArchive();
            $zip->open($outputPath);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            expect($xml)->toBeValidXml();
            expect($xml)->toContain('<w:tbl>');
            expect($xml)->toContain('Item A');
            expect($xml)->not->toContain('Placeholder');

            // Cleanup
            foreach ([$templatePath, $outputPath] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        });

        it('throws exception when injectInto called without creating table', function () {
            $builder = new TableBuilder();

            $template = new \MkGrow\ContentControl\ContentControl();
            $section = $template->addSection();
            $placeholder = $section->addText('Placeholder');
            $template->addContentControl($placeholder, ['tag' => 'table-placeholder']);

            $templatePath = tempnam(sys_get_temp_dir(), 'fluent_template_err_') . '.docx';
            $template->save($templatePath);

            $processor = new ContentProcessor($templatePath);

            expect(fn() => $builder->injectInto($processor, 'table-placeholder'))
                ->toThrow(
                    ContentControlException::class,
                    'Cannot inject table: no table created. Call addRow() first to create a table.'
                );

            // Cleanup
            if (file_exists($templatePath)) {
                unlink($templatePath);
            }
        });

        it('injects table with table-level SDT creating nested structure', function () {
            // Create template MANUALLY (avoid SDTInjector bug)
            $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:sdt>
            <w:sdtPr>
                <w:id w:val="87654321"/>
                <w:tag w:val="invoice-table"/>
            </w:sdtPr>
            <w:sdtContent>
                <w:p><w:r><w:t>Placeholder</w:t></w:r></w:p>
            </w:sdtContent>
        </w:sdt>
    </w:body>
</w:document>
XML;

            $templatePath = tempnam(sys_get_temp_dir(), 'fluent_template_sdt_') . '.docx';
            createDocxFromXml($templatePath, $xml);

            // Create table with its own SDT
            $builder = new TableBuilder();
            $builder->addContentControl(['tag' => 'items-table', 'alias' => 'Items'])
                ->addRow()
                    ->addCell(3000)->addText('Product')->end()
                    ->addCell(2000)->addText('Price')->end()
                    ->end();

            // Inject into template SDT (creates nested SDT structure)
            $processor = new ContentProcessor($templatePath);
            $builder->injectInto($processor, 'invoice-table');

            $outputPath = tempnam(sys_get_temp_dir(), 'fluent_output_sdt_') . '.docx';
            $processor->save($outputPath);

            // Verify nested SDT structure: invoice-table wraps items-table
            $zip = new ZipArchive();
            $zip->open($outputPath);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            expect($xml)->toBeValidXml();
            // Both SDTs should be present (nested)
            expect($xml)->toContain('<w:tag w:val="invoice-table"/>');
            expect($xml)->toContain('<w:tag w:val="items-table"/>');
            expect($xml)->toContain('<w:alias w:val="Items"/>');
            expect($xml)->toContain('Product');
            expect($xml)->toContain('Price');

            // Cleanup
            foreach ([$templatePath, $outputPath] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        });
    });

    describe('Complex Scenarios', function () {
        it('creates multi-row table with mixed SDTs', function () {
            $builder = new TableBuilder();

            // Header row
            $builder->addRow(360)
                ->addCell(2000)->addText('Product', ['bold' => true])->end()
                ->addCell(1500)->addText('Quantity', ['bold' => true])->end()
                ->addCell(1500)->addText('Price', ['bold' => true])->end()
                ->end();

            // Data rows with SDTs
            for ($i = 1; $i <= 3; $i++) {
                $builder->addRow()
                    ->addCell(2000)
                        ->withContentControl(['tag' => "product-{$i}"])
                        ->addText("Product {$i}")
                        ->end()
                    ->addCell(1500)
                        ->withContentControl(['tag' => "qty-{$i}"])
                        ->addText((string) ($i * 10))
                        ->end()
                    ->addCell(1500)
                        ->withContentControl(['tag' => "price-{$i}"])
                        ->addText("\${$i}9.99")
                        ->end()
                    ->end();
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'fluent_complex_') . '.docx';
            $builder->getContentControl()->save($tempFile);

            // Verify structure
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            expect($xml)->toBeValidXml();
            expect($xml)->toContain('<w:tbl>');
            
            // Verify all SDTs present
            for ($i = 1; $i <= 3; $i++) {
                expect($xml)->toContain("<w:tag w:val=\"product-{$i}\"/>");
                expect($xml)->toContain("<w:tag w:val=\"qty-{$i}\"/>");
                expect($xml)->toContain("<w:tag w:val=\"price-{$i}\"/>");
            }

            // Cleanup
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        });

        it('chains multiple method calls without errors', function () {
            $builder = new TableBuilder();

            // Complex chaining without table-level SDT
            $result = $builder
                ->addRow(720)
                    ->addCell(3000, ['bgColor' => 'E0E0E0'])
                        ->addText('Header 1', ['bold' => true, 'size' => 14])
                        ->end()
                    ->addCell(3000, ['bgColor' => 'E0E0E0'])
                        ->addText('Header 2', ['bold' => true, 'size' => 14])
                        ->end()
                    ->end()
                ->addRow()
                    ->addCell(3000)
                        ->withContentControl(['tag' => 'data-1'])
                        ->addText('Cell 1')
                        ->end()
                    ->addCell(3000)
                        ->withContentControl(['tag' => 'data-2'])
                        ->addText('Cell 2')
                        ->end()
                    ->end();

            // Result should be TableBuilder instance
            expect($result)->toBeInstanceOf(TableBuilder::class);

            $tempFile = tempnam(sys_get_temp_dir(), 'fluent_chain_') . '.docx';
            $builder->getContentControl()->save($tempFile);

            expect(file_exists($tempFile))->toBeTrue();

            // Cleanup
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        });

        it('handles multiple addText calls with separate SDTs', function () {
            $builder = new TableBuilder();

            $builder->addRow()
                ->addCell(5000)
                    ->withContentControl(['tag' => 'text-1'])
                    ->addText('First text')
                    ->withContentControl(['tag' => 'text-2'])
                    ->addText('Second text')
                    ->end()
                ->end();

            $tempFile = tempnam(sys_get_temp_dir(), 'fluent_multi_text_') . '.docx';
            $builder->getContentControl()->save($tempFile);

            // Verify both SDTs present
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            expect($xml)->toBeValidXml();
            expect($xml)->toContain('<w:tag w:val="text-1"/>');
            expect($xml)->toContain('<w:tag w:val="text-2"/>');

            // Cleanup
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        });
    });

    describe('Backward Compatibility', function () {
        it('legacy createTable() still works despite deprecation', function () {
            $builder = new TableBuilder();

            // This should work but trigger deprecation warning
            $table = @$builder->createTable([
                'rows' => [
                    ['cells' => [
                        ['text' => 'Legacy', 'width' => 3000],
                        ['text' => 'Method', 'width' => 2000],
                    ]],
                ],
            ]);

            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);

            $tempFile = tempnam(sys_get_temp_dir(), 'legacy_compat_') . '.docx';
            $builder->getContentControl()->save($tempFile);

            expect(file_exists($tempFile))->toBeTrue();

            // Cleanup
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        });
    });
});

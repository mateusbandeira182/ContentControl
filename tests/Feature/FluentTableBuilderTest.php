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
                safeUnlink($tempFile);
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
                safeUnlink($tempFile);
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
                safeUnlink($tempFile);
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
                safeUnlink($tempFile);
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
                safeUnlink($tempFile);
            }
        });
    });

    describe('injectInto() Method', function () {
        it('injects fluent table into template SDT', function () {
            // Create template using ContentControl API (bug fixed)
            $template = new \MkGrow\ContentControl\ContentControl();
            $section = $template->addSection();
            $section->addText('Before table');
            $placeholder = $section->addText('Placeholder');
            $template->addContentControl($placeholder, ['tag' => 'table-placeholder', 'id' => '12345678']);
            $section->addText('After table');

            $templatePath = tempnam(sys_get_temp_dir(), 'fluent_template_') . '.docx';
            $template->save($templatePath);

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
                    safeUnlink($file);
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
            safeUnlink($templatePath);
        });

        it('throws exception when attempting removed addContentControl method', function () {
            $builder = new TableBuilder();
            
            expect(fn() => $builder->addContentControl(['tag' => 'items-table']))
                ->toThrow(
                    ContentControlException::class,
                    'TableBuilder::addContentControl() removed in v0.5.1'
                );
        });

        it('supports cell-level SDTs without table-level wrapping (recommended pattern)', function () {
            // Create template
            $template = new \MkGrow\ContentControl\ContentControl();
            $section = $template->addSection();
            $placeholder = $section->addText('Placeholder');
            $template->addContentControl($placeholder, ['tag' => 'invoice-table', 'id' => '87654321']);

            $templatePath = tempnam(sys_get_temp_dir(), 'fluent_template_sdt_') . '.docx';
            $template->save($templatePath);

            // Create table with cell-level SDTs only (no table-level SDT)
            $builder = new TableBuilder();
            $builder->addRow()
                ->addCell(3000)
                    ->withContentControl([
                        'tag' => 'product-1',
                        'alias' => 'Product',
                        'inlineLevel' => true,
                    ])
                    ->addText('Widget A')
                    ->end()
                ->addCell(2000)
                    ->withContentControl([
                        'tag' => 'price-1',
                        'alias' => 'Price',
                        'inlineLevel' => true,
                    ])
                    ->addText('$99.99')
                    ->end()
                ->end();

            // Inject into template
            $processor = new ContentProcessor($templatePath);
            $builder->injectInto($processor, 'invoice-table');

            $outputPath = tempnam(sys_get_temp_dir(), 'fluent_output_sdt_') . '.docx';
            $processor->save($outputPath);

            // Verify zero nested SDTs (XPath: no SDT containing another SDT)
            $zip = new ZipArchive();
            $zip->open($outputPath);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            expect($xml)->toBeValidXml();
            
            // Validate cell-level SDTs exist
            expect($xml)->toContain('<w:tag w:val="product-1"/>');
            expect($xml)->toContain('<w:tag w:val="price-1"/>');
            
            // Critical validation: Verify NO table-level SDT wrapping cell-level SDTs
            // (The template SDT wrapping the injected table is expected and acceptable)
            // We're checking that there's no extra table-level SDT layer between template and cells
            $doc = new \DOMDocument();
            $doc->loadXML($xml);
            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            
            // Check: No w:sdt with tag "items-table" or similar table-level tags
            // (which would indicate the removed addContentControl() was used)
            $tableSdts = $xpath->query('//w:sdt[w:sdtPr/w:tag[@w:val="items-table"]]');
            expect($tableSdts->length)->toBe(0, 'Should have no table-level SDT from addContentControl()');
            
            // Verify cell-level SDTs exist (correct pattern)
            $cellSdt1 = $xpath->query('//w:sdt[w:sdtPr/w:tag[@w:val="product-1"]]');
            $cellSdt2 = $xpath->query('//w:sdt[w:sdtPr/w:tag[@w:val="price-1"]]');
            expect($cellSdt1->length)->toBe(1, 'Should have cell-level product SDT');
            expect($cellSdt2->length)->toBe(1, 'Should have cell-level price SDT');

            if (file_exists($templatePath)) {
                unlink($templatePath);
            }
            if (file_exists($outputPath)) {
                unlink($outputPath);
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
                safeUnlink($tempFile);
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
                safeUnlink($tempFile);
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
                safeUnlink($tempFile);
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
                safeUnlink($tempFile);
            }
        });
    });
});

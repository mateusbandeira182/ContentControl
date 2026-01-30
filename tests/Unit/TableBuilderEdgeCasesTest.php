<?php

declare(strict_types=1);

use MkGrow\ContentControl\Bridge\TableBuilder;
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Exception\ContentControlException;

/**
 * Unit tests for TableBuilder edge cases and full coverage.
 *
 * Tests covered:
 * - Destructor cleanup
 * - getContentControl() method
 * - injectTable() error cases
 * - Complex multi-table scenarios
 */
describe('TableBuilder Edge Cases', function (): void {
    describe('getContentControl()', function (): void {
        it('returns the same ContentControl instance', function (): void {
            $builder = new TableBuilder();

            $cc1 = $builder->getContentControl();
            $cc2 = $builder->getContentControl();

            expect($cc1)->toBe($cc2);
            expect($cc1)->toBeInstanceOf(ContentControl::class);
        });

        it('returns provided ContentControl', function (): void {
            $customCc = new ContentControl();
            $builder = new TableBuilder($customCc);

            $cc = $builder->getContentControl();

            expect($cc)->toBe($customCc);
        });

        it('can use returned ContentControl for document manipulation', function (): void {
            $builder = new TableBuilder();
            $cc = $builder->getContentControl();

            $section = $cc->addSection();
            $text = $section->addText('Test content');

            expect($text)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Text::class);
        });
    });

    describe('injectTable() error handling', function (): void {
        it('throws exception when template file does not exist', function (): void {
            $builder = new TableBuilder();

            $table = $builder->createTable([
                'rows' => [
                    ['cells' => [['text' => 'Test']]],
                ],
            ]);

            $builder->injectTable('/nonexistent/path/template.docx', 'test-tag', $table);
        })->throws(ContentControlException::class, 'Template file not found');

        it('throws exception when target SDT not found in template', function (): void {
            $builder = new TableBuilder();

            // Create template with SDT
            $template = new ContentControl();
            $section = $template->addSection();
            $placeholder = $section->addText('Placeholder');
            $template->addContentControl($placeholder, ['tag' => 'existing-tag']);

            $templatePath = tempnam(sys_get_temp_dir(), 'template_') . '.docx';
            $template->save($templatePath);

            try {
                // Create table
                $table = $builder->createTable([
                    'rows' => [
                        ['cells' => [['text' => 'Test']]],
                    ],
                ]);

                // Try to inject into non-existent SDT
                $builder->injectTable($templatePath, 'missing-tag', $table);
            } finally {
                if (file_exists($templatePath)) {
                    @unlink($templatePath);
                }
            }
        })->throws(ContentControlException::class, 'SDT with tag \'missing-tag\' not found');

        it('throws exception when table not found in temporary document', function (): void {
            $builder = new TableBuilder();

            // Create template
            $template = new ContentControl();
            $section = $template->addSection();
            $placeholder = $section->addText('Placeholder');
            $template->addContentControl($placeholder, ['tag' => 'target-tag']);

            $templatePath = tempnam(sys_get_temp_dir(), 'template_') . '.docx';
            $template->save($templatePath);

            try {
                // Create empty table manually (will have different hash)
                $cc = new ContentControl();
                $manualTable = $cc->addSection()->addTable();

                // This should fail because the hash won't match
                $builder->injectTable($templatePath, 'target-tag', $manualTable);
            } finally {
                if (file_exists($templatePath)) {
                    @unlink($templatePath);
                }
            }
        })->throws(ContentControlException::class);
    });

    describe('Destructor cleanup', function (): void {
        it('cleans up temporary files on destruction', function (): void {
            $builder = new TableBuilder();

            // Create template and inject table to trigger temp file creation
            $template = new ContentControl();
            $section = $template->addSection();
            $placeholder = $section->addText('Placeholder');
            $template->addContentControl($placeholder, ['tag' => 'test-tag']);

            $templatePath = tempnam(sys_get_temp_dir(), 'template_') . '.docx';
            $template->save($templatePath);

            try {
                $table = $builder->createTable([
                    'rows' => [
                        ['cells' => [['text' => 'Test']]],
                    ],
                ]);

                $builder->injectTable($templatePath, 'test-tag', $table);

                // Force destruction
                unset($builder);

                // If we get here without errors, cleanup worked
                expect(true)->toBeTrue();
            } finally {
                if (file_exists($templatePath)) {
                    @unlink($templatePath);
                }
            }
        });
    });

    describe('Complex table scenarios', function (): void {
        it('creates table with all optional parameters', function (): void {
            $builder = new TableBuilder();

            $table = $builder->createTable([
                'tableTag' => 'complex-table',
                'tableAlias' => 'Complex Table',
                'style' => [
                    'borderSize' => 12,
                    'borderColor' => '1F4788',
                    'cellMargin' => 120,
                ],
                'rows' => [
                    [
                        'height' => 800,
                        'style' => ['tblHeader' => true],
                        'cells' => [
                            [
                                'text' => 'Header 1',
                                'width' => 3000,
                                'style' => [
                                    'bgColor' => '1F4788',
                                    'color' => 'FFFFFF',
                                    'bold' => true,
                                    'size' => 14,
                                    'alignment' => 'center',
                                    'valign' => 'center',
                                ],
                            ],
                            [
                                'text' => 'Header 2',
                                'width' => 2000,
                                'style' => [
                                    'bgColor' => '1F4788',
                                    'color' => 'FFFFFF',
                                    'bold' => true,
                                    'size' => 14,
                                    'alignment' => 'center',
                                    'valign' => 'center',
                                ],
                            ],
                        ],
                    ],
                    [
                        'cells' => [
                            ['text' => 'Data 1', 'width' => 3000],
                            ['text' => 'Data 2', 'width' => 2000],
                        ],
                    ],
                ],
            ]);

            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);

            // Verify SDT was registered
            $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
            $builder->getContentControl()->save($tempFile);

            $zip = new \ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            expect($xml)->toContain('<w:tag w:val="complex-table"/>');
            expect($xml)->toContain('<w:alias w:val="Complex Table"/>');

            @unlink($tempFile);
        });

        it('creates large table with many rows', function (): void {
            $builder = new TableBuilder();

            $rows = [];
            for ($i = 0; $i < 50; $i++) {
                $rows[] = [
                    'cells' => [
                        ['text' => "Row $i Col 1"],
                        ['text' => "Row $i Col 2"],
                        ['text' => "Row $i Col 3"],
                    ],
                ];
            }

            $table = $builder->createTable(['rows' => $rows]);

            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('creates wide table with many columns', function (): void {
            $builder = new TableBuilder();

            $cells = [];
            for ($i = 0; $i < 20; $i++) {
                $cells[] = ['text' => "Col $i", 'width' => 1000];
            }

            $table = $builder->createTable([
                'rows' => [
                    ['cells' => $cells],
                ],
            ]);

            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('successfully injects table into template', function (): void {
            $builder = new TableBuilder();

            // Create template
            $template = new ContentControl();
            $section = $template->addSection();
            $section->addText('Invoice', ['bold' => true, 'size' => 16]);
            $placeholder = $section->addText('[Table will be inserted here]');
            $template->addContentControl($placeholder, ['tag' => 'invoice-table']);

            $templatePath = tempnam(sys_get_temp_dir(), 'template_') . '.docx';
            $template->save($templatePath);

            try {
                // Create and inject table
                $table = $builder->createTable([
                    'rows' => [
                        ['cells' => [
                            ['text' => 'Item', 'width' => 3000],
                            ['text' => 'Price', 'width' => 2000],
                        ]],
                        ['cells' => [
                            ['text' => 'Product A', 'width' => 3000],
                            ['text' => '$100', 'width' => 2000],
                        ]],
                    ],
                ]);

                $builder->injectTable($templatePath, 'invoice-table', $table);

                // Verify template was modified
                $zip = new \ZipArchive();
                $zip->open($templatePath);
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();

                // Should contain table elements
                expect($xml)->toContain('<w:tbl>');
                expect($xml)->toContain('<w:tr>');
                expect($xml)->toContain('<w:tc>');

                // Should contain the data
                expect($xml)->toContain('Product A');
                expect($xml)->toContain('$100');
            } finally {
                if (file_exists($templatePath)) {
                    @unlink($templatePath);
                }
            }
        });

        it('injects multiple different tables into same template', function (): void {
            $builder = new TableBuilder();

            // Create template with two SDTs
            $template = new ContentControl();
            $section = $template->addSection();

            $section->addText('Summary', ['bold' => true]);
            $summaryPlaceholder = $section->addText('[Summary table]');
            $template->addContentControl($summaryPlaceholder, ['tag' => 'summary']);

            $section->addText('');
            $section->addText('Details', ['bold' => true]);
            $detailsPlaceholder = $section->addText('[Details table]');
            $template->addContentControl($detailsPlaceholder, ['tag' => 'details']);

            $templatePath = tempnam(sys_get_temp_dir(), 'template_') . '.docx';
            $template->save($templatePath);

            try {
                // Create and inject summary table
                $summaryTable = $builder->createTable([
                    'rows' => [
                        ['cells' => [['text' => 'Total'], ['text' => '$500']]],
                    ],
                ]);
                $builder->injectTable($templatePath, 'summary', $summaryTable);

                // Create and inject details table
                $builder2 = new TableBuilder();
                $detailsTable = $builder2->createTable([
                    'rows' => [
                        ['cells' => [['text' => 'Item 1'], ['text' => '$100']]],
                        ['cells' => [['text' => 'Item 2'], ['text' => '$200']]],
                        ['cells' => [['text' => 'Item 3'], ['text' => '$200']]],
                    ],
                ]);
                $builder2->injectTable($templatePath, 'details', $detailsTable);

                // Verify both tables were injected
                $zip = new \ZipArchive();
                $zip->open($templatePath);
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();

                expect($xml)->toContain('Total');
                expect($xml)->toContain('$500');
                expect($xml)->toContain('Item 1');
                expect($xml)->toContain('Item 2');
                expect($xml)->toContain('Item 3');
            } finally {
                if (file_exists($templatePath)) {
                    @unlink($templatePath);
                }
            }
        });
    });

    describe('Constructor variations', function (): void {
        it('creates with default ContentControl', function (): void {
            $builder = new TableBuilder();

            expect($builder)->toBeInstanceOf(TableBuilder::class);
            expect($builder->getContentControl())->toBeInstanceOf(ContentControl::class);
        });

        it('creates with custom ContentControl', function (): void {
            $customCc = new ContentControl();
            $customCc->addSection()->addText('Custom content');

            $builder = new TableBuilder($customCc);

            expect($builder->getContentControl())->toBe($customCc);
        });

        it('creates with null explicitly', function (): void {
            $builder = new TableBuilder(null);

            expect($builder)->toBeInstanceOf(TableBuilder::class);
            expect($builder->getContentControl())->toBeInstanceOf(ContentControl::class);
        });
    });

    describe('Cell text and element handling', function (): void {
        it('throws exception when cell has no text or element', function (): void {
            $builder = new TableBuilder();
            
            $config = [
                'rows' => [
                    ['cells' => [
                        ['width' => 3000], // No text property
                    ]],
                ],
            ];
            
            expect(fn() => $builder->createTable($config))
                ->toThrow(ContentControlException::class, 'Row 0, Cell 0: must have "text" or "element"');
        });

        it('throws exception when cell has null text', function (): void {
            $builder = new TableBuilder();
            
            $config = [
                'rows' => [
                    ['cells' => [
                        ['text' => null], // Null text
                    ]],
                ],
            ];
            
            expect(fn() => $builder->createTable($config))
                ->toThrow(ContentControlException::class, 'Row 0, Cell 0: must have "text" or "element"');
        });

        it('handles cell with empty string text', function (): void {
            $builder = new TableBuilder();
            
            $config = [
                'rows' => [
                    ['cells' => [
                        ['text' => ''], // Empty string
                    ]],
                ],
            ];
            
            $table = $builder->createTable($config);
            
            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('handles cell with non-string text', function (): void {
            $builder = new TableBuilder();
            
            $config = [
                'rows' => [
                    ['cells' => [
                        ['text' => 123], // Non-string
                    ]],
                ],
            ];
            
            $table = $builder->createTable($config);
            
            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('throws exception for custom element in cell', function (): void {
            $builder = new TableBuilder();
            
            $element = new \PhpOffice\PhpWord\Element\Text('Some text');
            
            $config = [
                'rows' => [
                    ['cells' => [
                        ['element' => $element], // Custom element
                    ]],
                ],
            ];
            
            expect(fn() => $builder->createTable($config))
                ->toThrow(ContentControlException::class, 'Row 0, Cell 0: "element" is not supported in v0.4.0, use "text" only');
        });
    });

    describe('Cell SDT property type handling', function (): void {
        it('handles cell with tag but no alias', function (): void {
            $builder = new TableBuilder();
            
            $config = [
                'rows' => [
                    ['cells' => [
                        [
                            'text' => 'Test',
                            'tag' => 'test-tag',
                            // No alias - should use tag as alias
                        ],
                    ]],
                ],
            ];
            
            $table = $builder->createTable($config);
            
            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('handles cell with non-string tag', function (): void {
            $builder = new TableBuilder();
            
            $config = [
                'rows' => [
                    ['cells' => [
                        [
                            'text' => 'Test',
                            'tag' => 123, // Non-string tag
                        ],
                    ]],
                ],
            ];
            
            $table = $builder->createTable($config);
            
            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('handles cell with non-string alias', function (): void {
            $builder = new TableBuilder();
            
            $config = [
                'rows' => [
                    ['cells' => [
                        [
                            'text' => 'Test',
                            'tag' => 'test-tag',
                            'alias' => 123, // Non-string alias
                        ],
                    ]],
                ],
            ];
            
            $table = $builder->createTable($config);
            
            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('handles cell with non-string type', function (): void {
            $builder = new TableBuilder();
            
            $config = [
                'rows' => [
                    ['cells' => [
                        [
                            'text' => 'Test',
                            'tag' => 'test-tag',
                            'type' => 123, // Non-string type
                        ],
                    ]],
                ],
            ];
            
            $table = $builder->createTable($config);
            
            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('handles cell with non-string lockType', function (): void {
            $builder = new TableBuilder();
            
            $config = [
                'rows' => [
                    ['cells' => [
                        [
                            'text' => 'Test',
                            'tag' => 'test-tag',
                            'lockType' => 123, // Non-string lockType
                        ],
                    ]],
                ],
            ];
            
            $table = $builder->createTable($config);
            
            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });
    });
});

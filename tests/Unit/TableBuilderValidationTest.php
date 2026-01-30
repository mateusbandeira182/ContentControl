<?php

declare(strict_types=1);

use MkGrow\ContentControl\Bridge\TableBuilder;
use MkGrow\ContentControl\Exception\ContentControlException;

/**
 * Unit tests for TableBuilder validation methods.
 *
 * Tests covered:
 * - validateTableConfig() with various invalid inputs
 * - validateRowConfig() with missing/invalid cells
 * - validateCellConfig() with text/element validation
 * - Edge cases and error messages
 */
describe('TableBuilder Validation Methods', function (): void {
    describe('validateTableConfig()', function (): void {
        it('throws exception when rows key is missing', function (): void {
            $builder = new TableBuilder();

            $builder->createTable(['style' => []]);
        })->throws(ContentControlException::class, 'Table configuration must have "rows" key');

        it('throws exception when rows is not an array', function (): void {
            $builder = new TableBuilder();

            $builder->createTable(['rows' => 'invalid']);
        })->throws(ContentControlException::class, 'Table "rows" must be non-empty array');

        it('throws exception when rows is empty', function (): void {
            $builder = new TableBuilder();

            $builder->createTable(['rows' => []]);
        })->throws(ContentControlException::class, 'Table "rows" must be non-empty array');

        it('accepts valid table config', function (): void {
            $builder = new TableBuilder();

            $table = $builder->createTable([
                'rows' => [
                    ['cells' => [['text' => 'Test']]],
                ],
            ]);

            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });
    });

    describe('validateRowConfig()', function (): void {
        it('throws exception when row is not an array', function (): void {
            $builder = new TableBuilder();

            $builder->createTable([
                'rows' => ['invalid'],
            ]);
        })->throws(ContentControlException::class, 'Row 0: configuration must be an array');

        it('throws exception when cells key is missing', function (): void {
            $builder = new TableBuilder();

            $builder->createTable([
                'rows' => [
                    ['height' => 500],
                ],
            ]);
        })->throws(ContentControlException::class, 'Row 0: missing "cells" key');

        it('throws exception when cells is not an array', function (): void {
            $builder = new TableBuilder();

            $builder->createTable([
                'rows' => [
                    ['cells' => 'invalid'],
                ],
            ]);
        })->throws(ContentControlException::class, 'Row 0: "cells" must be non-empty array');

        it('throws exception when cells is empty', function (): void {
            $builder = new TableBuilder();

            $builder->createTable([
                'rows' => [
                    ['cells' => []],
                ],
            ]);
        })->throws(ContentControlException::class, 'Row 0: "cells" must be non-empty array');

        it('accepts valid row config with height', function (): void {
            $builder = new TableBuilder();

            $table = $builder->createTable([
                'rows' => [
                    [
                        'height' => 800,
                        'cells' => [['text' => 'Test']],
                    ],
                ],
            ]);

            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('accepts valid row config with style', function (): void {
            $builder = new TableBuilder();

            $table = $builder->createTable([
                'rows' => [
                    [
                        'style' => ['tblHeader' => true],
                        'cells' => [['text' => 'Header']],
                    ],
                ],
            ]);

            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });
    });

    describe('validateCellConfig()', function (): void {
        it('throws exception when cell is not an array', function (): void {
            $builder = new TableBuilder();

            $builder->createTable([
                'rows' => [
                    ['cells' => ['invalid']],
                ],
            ]);
        })->throws(ContentControlException::class, 'Row 0, Cell 0: configuration must be an array');

        it('throws exception when both text and element are missing', function (): void {
            $builder = new TableBuilder();

            $builder->createTable([
                'rows' => [
                    ['cells' => [['width' => 2000]]],
                ],
            ]);
        })->throws(ContentControlException::class, 'Row 0, Cell 0: must have "text" or "element"');

        it('throws exception when both text and element are present', function (): void {
            $builder = new TableBuilder();

            $builder->createTable([
                'rows' => [
                    ['cells' => [
                        ['text' => 'Test', 'element' => new stdClass()],
                    ]],
                ],
            ]);
        })->throws(ContentControlException::class, 'Row 0, Cell 0: cannot have both "text" and "element"');

        it('accepts valid cell with text only', function (): void {
            $builder = new TableBuilder();

            $table = $builder->createTable([
                'rows' => [
                    ['cells' => [['text' => 'Test']]],
                ],
            ]);

            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('accepts valid cell with text and width', function (): void {
            $builder = new TableBuilder();

            $table = $builder->createTable([
                'rows' => [
                    ['cells' => [['text' => 'Test', 'width' => 3000]]],
                ],
            ]);

            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('accepts valid cell with text and style', function (): void {
            $builder = new TableBuilder();

            $table = $builder->createTable([
                'rows' => [
                    ['cells' => [
                        [
                            'text' => 'Styled',
                            'style' => [
                                'bgColor' => 'FFFF00',
                                'bold' => true,
                                'size' => 12,
                            ],
                        ],
                    ]],
                ],
            ]);

            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('accepts valid cell with alignment styles', function (): void {
            $builder = new TableBuilder();

            $table = $builder->createTable([
                'rows' => [
                    ['cells' => [
                        [
                            'text' => 'Aligned',
                            'style' => [
                                'alignment' => 'center',
                                'valign' => 'center',
                            ],
                        ],
                    ]],
                ],
            ]);

            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('accepts valid cell with multiple rows', function (): void {
            $builder = new TableBuilder();

            $table = $builder->createTable([
                'rows' => [
                    ['cells' => [['text' => 'Row 1']]],
                    ['cells' => [['text' => 'Row 2']]],
                    ['cells' => [['text' => 'Row 3']]],
                ],
            ]);

            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('accepts valid cell with multiple cells per row', function (): void {
            $builder = new TableBuilder();

            $table = $builder->createTable([
                'rows' => [
                    ['cells' => [
                        ['text' => 'A1'],
                        ['text' => 'A2'],
                        ['text' => 'A3'],
                    ]],
                ],
            ]);

            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });
    });

    describe('Complex validation scenarios', function (): void {
        it('validates multiple rows with different cell counts', function (): void {
            $builder = new TableBuilder();

            $table = $builder->createTable([
                'rows' => [
                    ['cells' => [['text' => 'A1'], ['text' => 'A2'], ['text' => 'A3']]],
                    ['cells' => [['text' => 'B1'], ['text' => 'B2']]],
                    ['cells' => [['text' => 'C1']]],
                ],
            ]);

            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('validates table with all styling options', function (): void {
            $builder = new TableBuilder();

            $table = $builder->createTable([
                'style' => [
                    'borderSize' => 12,
                    'borderColor' => '1F4788',
                    'cellMargin' => 100,
                ],
                'rows' => [
                    [
                        'height' => 700,
                        'style' => ['tblHeader' => true],
                        'cells' => [
                            [
                                'text' => 'Header',
                                'width' => 3000,
                                'style' => [
                                    'bgColor' => '1F4788',
                                    'color' => 'FFFFFF',
                                    'bold' => true,
                                    'size' => 12,
                                    'alignment' => 'center',
                                    'valign' => 'center',
                                ],
                            ],
                        ],
                    ],
                    ['cells' => [['text' => 'Data', 'width' => 3000]]],
                ],
            ]);

            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('throws descriptive error for deeply nested invalid config', function (): void {
            $builder = new TableBuilder();

            $builder->createTable([
                'rows' => [
                    ['cells' => [['text' => 'Valid']]],
                    ['cells' => [['text' => 'Valid']]],
                    ['cells' => [['width' => 2000]]], // Invalid - no text
                ],
            ]);
        })->throws(ContentControlException::class, 'Row 2, Cell 0: must have "text" or "element"');
    });
});

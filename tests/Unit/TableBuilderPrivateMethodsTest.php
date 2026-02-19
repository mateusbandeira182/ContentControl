<?php

declare(strict_types=1);

use MkGrow\ContentControl\Bridge\TableBuilder;
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Exception\ContentControlException;

describe('TableBuilder Private Methods', function () {
    describe('validateTableConfig()', function () {
        it('validates minimal valid config', function () {
            $builder = new TableBuilder();
            $config = [
                'rows' => [
                    ['cells' => [['text' => 'Test']]],
                ],
            ];
            
            $table = $builder->createTable($config);
            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('validates config with all optional fields', function () {
            $builder = new TableBuilder();
            $config = [
                'style' => ['borderSize' => 6],
                'tableTag' => 'test-table',
                'tableAlias' => 'Test Table',
                'tableLockType' => ContentControl::LOCK_SDT_LOCKED,
                'rows' => [
                    [
                        'height' => 500,
                        'style' => ['bgColor' => 'FF0000'],
                        'cells' => [
                            [
                                'text' => 'Cell 1',
                                'width' => 3000,
                                'style' => ['bold' => true],
                                'tag' => 'cell-1',
                                'alias' => 'Cell One',
                                'type' => ContentControl::TYPE_PLAIN_TEXT,
                                'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
                            ],
                        ],
                    ],
                ],
            ];
            
            $table = $builder->createTable($config);
            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('throws exception when rows is missing', function () {
            $builder = new TableBuilder();
            $config = [
                'style' => ['borderSize' => 6],
            ];
            
            expect(fn() => $builder->createTable($config))
                ->toThrow(ContentControlException::class, 'Table configuration must have "rows" key');
        });

        it('throws exception when rows is empty', function () {
            $builder = new TableBuilder();
            $config = [
                'rows' => [],
            ];
            
            expect(fn() => $builder->createTable($config))
                ->toThrow(ContentControlException::class, 'Table "rows" must be non-empty array');
        });

        it('throws exception when row has no cells', function () {
            $builder = new TableBuilder();
            $config = [
                'rows' => [
                    ['height' => 500],
                ],
            ];
            
            expect(fn() => $builder->createTable($config))
                ->toThrow(ContentControlException::class, 'Row 0: missing "cells" key');
        });

        it('throws exception when cells is empty', function () {
            $builder = new TableBuilder();
            $config = [
                'rows' => [
                    ['cells' => []],
                ],
            ];
            
            expect(fn() => $builder->createTable($config))
                ->toThrow(ContentControlException::class, 'Row 0: "cells" must be non-empty array');
        });

        it('throws exception when cell has no text or element', function () {
            $builder = new TableBuilder();
            $config = [
                'rows' => [
                    ['cells' => [
                        ['width' => 3000],
                    ]],
                ],
            ];
            
            expect(fn() => $builder->createTable($config))
                ->toThrow(ContentControlException::class, 'Row 0, Cell 0: must have "text" or "element"');
        });

        it('throws exception when cell has element (not supported)', function () {
            $builder = new TableBuilder();
            $config = [
                'rows' => [
                    ['cells' => [
                        ['element' => new \PhpOffice\PhpWord\Element\Text('test')],
                    ]],
                ],
            ];
            
            expect(fn() => $builder->createTable($config))
                ->toThrow(ContentControlException::class, 'Row 0, Cell 0: "element" is not supported in v0.4.0, use "text" only');
        });
    });

    describe('createTable() with SDT registration', function () {
        it('registers table-level SDT when tableTag provided', function () {
            $builder = new TableBuilder();
            
            $table = $builder->createTable([
                'tableTag' => 'my-table',
                'tableAlias' => 'My Table',
                'tableLockType' => ContentControl::LOCK_SDT_LOCKED,
                'rows' => [
                    ['cells' => [['text' => 'Test']]],
                ],
            ]);
            
            $registry = $builder->getContentControl()->getSDTRegistry();
            expect($registry->has($table))->toBeTrue();
            
            $config = $registry->getConfig($table);
            expect($config)->not->toBeNull();
            expect($config->tag)->toBe('my-table');
            expect($config->alias)->toBe('My Table');
            expect($config->lockType)->toBe(ContentControl::LOCK_SDT_LOCKED);
        });

        it('uses tableTag as alias when tableAlias not provided', function () {
            $builder = new TableBuilder();
            
            $table = $builder->createTable([
                'tableTag' => 'my-table',
                'rows' => [
                    ['cells' => [['text' => 'Test']]],
                ],
            ]);
            
            $registry = $builder->getContentControl()->getSDTRegistry();
            $config = $registry->getConfig($table);
            
            expect($config)->not->toBeNull();
            expect($config->alias)->toBe('my-table');
        });

        it('defaults to LOCK_NONE when tableLockType not provided', function () {
            $builder = new TableBuilder();
            
            $table = $builder->createTable([
                'tableTag' => 'my-table',
                'rows' => [
                    ['cells' => [['text' => 'Test']]],
                ],
            ]);
            
            $registry = $builder->getContentControl()->getSDTRegistry();
            $config = $registry->getConfig($table);
            
            expect($config)->not->toBeNull();
            expect($config->lockType)->toBe(ContentControl::LOCK_NONE);
        });
    });

    describe('createTable() with cell styles', function () {
        it('applies default width when not specified', function () {
            $builder = new TableBuilder();
            
            $table = $builder->createTable([
                'rows' => [
                    ['cells' => [['text' => 'Test']]],
                ],
            ]);
            
            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('handles empty text content', function () {
            $builder = new TableBuilder();
            
            $table = $builder->createTable([
                'rows' => [
                    ['cells' => [['text' => '']]],
                ],
            ]);
            
            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('applies cell width when provided', function () {
            $builder = new TableBuilder();
            
            $table = $builder->createTable([
                'rows' => [
                    ['cells' => [['text' => 'Test', 'width' => 5000]]],
                ],
            ]);
            
            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });

        it('applies row height when provided', function () {
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

        it('applies row and cell styles when provided', function () {
            $builder = new TableBuilder();
            
            $table = $builder->createTable([
                'rows' => [
                    [
                        'style' => ['bgColor' => 'FF0000'],
                        'cells' => [
                            [
                                'text' => 'Test',
                                'style' => ['bold' => true],
                            ],
                        ],
                    ],
                ],
            ]);
            
            expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
        });
    });
});

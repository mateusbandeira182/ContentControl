<?php

declare(strict_types=1);

use MkGrow\ContentControl\Bridge\TableBuilder;

describe('TableBuilder Row Styling', function () {
    it('applies row height', function () {
        $builder = new TableBuilder();
        
        $config = [
            'rows' => [
                [
                    'cells' => [['text' => 'Test']],
                    'height' => 800,
                ],
            ],
        ];
        
        $table = $builder->createTable($config);
        
        expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
    });

    it('applies row style properties', function () {
        $builder = new TableBuilder();
        
        $config = [
            'rows' => [
                [
                    'cells' => [['text' => 'Test']],
                    'style' => [
                        'tblHeader' => true,
                        'cantSplit' => true,
                    ],
                ],
            ],
        ];
        
        $table = $builder->createTable($config);
        
        expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
    });

    it('applies row height and style together', function () {
        $builder = new TableBuilder();
        
        $config = [
            'rows' => [
                [
                    'cells' => [['text' => 'Header Row']],
                    'height' => 600,
                    'style' => ['tblHeader' => true],
                ],
                [
                    'cells' => [['text' => 'Data Row']],
                    'height' => 400,
                ],
            ],
        ];
        
        $table = $builder->createTable($config);
        
        expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
    });

    it('handles null row height', function () {
        $builder = new TableBuilder();
        
        $config = [
            'rows' => [
                [
                    'cells' => [['text' => 'Test']],
                    'height' => null,
                ],
            ],
        ];
        
        $table = $builder->createTable($config);
        
        expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
    });

    it('handles empty row style array', function () {
        $builder = new TableBuilder();
        
        $config = [
            'rows' => [
                [
                    'cells' => [['text' => 'Test']],
                    'style' => [],
                ],
            ],
        ];
        
        $table = $builder->createTable($config);
        
        expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
    });

    it('applies different styles to multiple rows', function () {
        $builder = new TableBuilder();
        
        $config = [
            'rows' => [
                [
                    'cells' => [['text' => 'Header']],
                    'height' => 600,
                    'style' => [
                        'tblHeader' => true,
                        'exactHeight' => true,
                    ],
                ],
                [
                    'cells' => [['text' => 'Row 1']],
                    'height' => 400,
                    'style' => ['cantSplit' => false],
                ],
                [
                    'cells' => [['text' => 'Row 2']],
                    'height' => 400,
                ],
            ],
        ];
        
        $table = $builder->createTable($config);
        
        expect($table)->toBeInstanceOf(\PhpOffice\PhpWord\Element\Table::class);
    });
});

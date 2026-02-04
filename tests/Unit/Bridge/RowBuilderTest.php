<?php

declare(strict_types=1);

use MkGrow\ContentControl\Bridge\CellBuilder;
use MkGrow\ContentControl\Bridge\RowBuilder;
use MkGrow\ContentControl\Bridge\TableBuilder;
use PhpOffice\PhpWord\Element\Row;

describe('RowBuilder', function (): void {
    describe('Constructor', function (): void {
        it('initializes with Row and TableBuilder parent', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            
            expect($rowBuilder)->toBeInstanceOf(RowBuilder::class);
        });
    });

    describe('addCell()', function (): void {
        it('returns CellBuilder instance', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            
            $cellBuilder = $rowBuilder->addCell(2000);
            
            expect($cellBuilder)->toBeInstanceOf(CellBuilder::class);
        });

        it('creates cell with specified width', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            
            $cellBuilder = $rowBuilder->addCell(3000);
            
            expect($cellBuilder)->toBeInstanceOf(CellBuilder::class);
        });

        it('applies cell style when provided', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            
            $style = ['bgColor' => 'CCCCCC', 'borderSize' => 6];
            $cellBuilder = $rowBuilder->addCell(2000, $style);
            
            expect($cellBuilder)->toBeInstanceOf(CellBuilder::class);
        });

        it('creates multiple cells in same row', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            
            $cell1 = $rowBuilder->addCell(1500);
            $cell2 = $rowBuilder->addCell(2500);
            $cell3 = $rowBuilder->addCell(2000);
            
            expect($cell1)->toBeInstanceOf(CellBuilder::class);
            expect($cell2)->toBeInstanceOf(CellBuilder::class);
            expect($cell3)->toBeInstanceOf(CellBuilder::class);
        });

        it('accepts zero width (auto-sized cell)', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            
            $cellBuilder = $rowBuilder->addCell(0);
            
            expect($cellBuilder)->toBeInstanceOf(CellBuilder::class);
        });
    });

    describe('end()', function (): void {
        it('returns parent TableBuilder', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            
            $returned = $rowBuilder->end();
            
            expect($returned)->toBe($tableBuilder);
        });

        it('returns same TableBuilder instance', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            
            $returned1 = $rowBuilder->end();
            $returned2 = $rowBuilder->end();
            
            expect($returned1)->toBe($returned2);
        });
    });

    describe('Method Chaining', function (): void {
        it('supports fluent interface for adding cells', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            
            // addCell() returns CellBuilder, need to call end() to get back to RowBuilder
            // Then end() on RowBuilder returns TableBuilder
            $result = $rowBuilder
                ->addCell(2000)
                    ->addText('Cell 1')
                    ->end()  // Returns RowBuilder
                ->addCell(3000)
                    ->addText('Cell 2')
                    ->end()  // Returns RowBuilder
                ->end();     // Returns TableBuilder
            
            expect($result)->toBe($tableBuilder);
        });

        it('can chain cell creation and end() call', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            
            $result = $rowBuilder
                ->addCell(1000)
                ->end()
                ->end();
            
            expect($result)->toBe($tableBuilder);
        });
    });

    describe('Integration with Cell Styles', function (): void {
        it('creates cells with complex styling', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            
            $complexStyle = [
                'bgColor' => 'FF0000',
                'borderSize' => 12,
                'borderColor' => '000000',
                'valign' => 'center',
            ];
            
            $cellBuilder = $rowBuilder->addCell(2500, $complexStyle);
            
            expect($cellBuilder)->toBeInstanceOf(CellBuilder::class);
        });

        it('handles empty style array', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            
            $cellBuilder = $rowBuilder->addCell(2000, []);
            
            expect($cellBuilder)->toBeInstanceOf(CellBuilder::class);
        });
    });
});

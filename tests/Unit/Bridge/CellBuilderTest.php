<?php

declare(strict_types=1);

use MkGrow\ContentControl\Bridge\CellBuilder;
use MkGrow\ContentControl\Bridge\RowBuilder;
use MkGrow\ContentControl\Bridge\TableBuilder;
use MkGrow\ContentControl\ContentControl;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Row;

describe('CellBuilder', function (): void {
    describe('Constructor', function (): void {
        it('initializes with Cell, RowBuilder and TableBuilder', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            expect($cellBuilder)->toBeInstanceOf(CellBuilder::class);
        });
    });

    describe('addText()', function (): void {
        it('creates text element in cell', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            $result = $cellBuilder->addText('Hello World');
            
            expect($result)->toBe($cellBuilder);
        });

        it('applies text style when provided', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            $style = ['bold' => true, 'size' => 14, 'color' => 'FF0000'];
            $result = $cellBuilder->addText('Styled Text', $style);
            
            expect($result)->toBe($cellBuilder);
        });

        it('returns self for method chaining', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            $result = $cellBuilder->addText('Line 1')
                ->addText('Line 2')
                ->addText('Line 3');
            
            expect($result)->toBe($cellBuilder);
        });

        it('handles empty text', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            $result = $cellBuilder->addText('');
            
            expect($result)->toBe($cellBuilder);
        });
    });

    describe('addImage()', function (): void {
        it('creates image element in cell', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            // Use __DIR__ to get a valid path relative to test file
            $imagePath = __DIR__ . '/../../Fixtures/test_image.png';
            
            $result = $cellBuilder->addImage($imagePath);
            
            expect($result)->toBe($cellBuilder);
        });

        it('applies image style when provided', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            $imagePath = __DIR__ . '/../../Fixtures/test_image.png';
            $style = ['width' => 100, 'height' => 100];
            
            $result = $cellBuilder->addImage($imagePath, $style);
            
            expect($result)->toBe($cellBuilder);
        });

        it('returns self for method chaining', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            $imagePath = __DIR__ . '/../../Fixtures/test_image.png';
            
            $result = $cellBuilder->addImage($imagePath);
            
            expect($result)->toBe($cellBuilder);
        });
    });

    describe('withContentControl()', function (): void {
        it('stores SDT configuration', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            $config = ['tag' => 'user-name', 'alias' => 'User Name'];
            $result = $cellBuilder->withContentControl($config);
            
            expect($result)->toBe($cellBuilder);
        });

        it('applies configuration to next addText() call', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            $cellBuilder->withContentControl(['tag' => 'greeting'])
                ->addText('Hello World');
            
            // Verify SDT was registered
            $registry = $tableBuilder->getContentControl()->getSDTRegistry();
            expect($registry->count())->toBe(1);
        });

        it('resets configuration after applying', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            $cellBuilder->withContentControl(['tag' => 'first'])
                ->addText('First');
            
            $cellBuilder->addText('Second'); // No SDT applied
            
            $registry = $tableBuilder->getContentControl()->getSDTRegistry();
            expect($registry->count())->toBe(1); // Only one SDT registered
        });

        it('supports multiple elements with separate SDTs', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            $cellBuilder->withContentControl(['tag' => 'first'])
                ->addText('First')
                ->withContentControl(['tag' => 'second'])
                ->addText('Second');
            
            $registry = $tableBuilder->getContentControl()->getSDTRegistry();
            expect($registry->count())->toBe(2);
        });

        it('applies configuration to addImage() call', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            $imagePath = __DIR__ . '/../../Fixtures/test_image.png';
            
            $cellBuilder->withContentControl([
                    'tag' => 'product-image',
                    'type' => ContentControl::TYPE_PICTURE
                ])
                ->addImage($imagePath);
            
            $registry = $tableBuilder->getContentControl()->getSDTRegistry();
            expect($registry->count())->toBe(1);
        });

        it('supports full SDT configuration', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            $fullConfig = [
                'tag' => 'field-name',
                'alias' => 'Field Display Name',
                'type' => ContentControl::TYPE_PLAIN_TEXT,
                'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
            ];
            
            $cellBuilder->withContentControl($fullConfig)
                ->addText('Protected Content');
            
            $registry = $tableBuilder->getContentControl()->getSDTRegistry();
            expect($registry->count())->toBe(1);
        });
    });

    describe('end() removal (v0.7.0)', function (): void {
        it('does not have end() method (removed in v0.7.0)', function (): void {
            expect(method_exists(CellBuilder::class, 'end'))->toBeFalse();
        });
    });

    describe('Method Chaining', function (): void {
        it('supports complex fluent interface', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            
            $result = (new CellBuilder($cell, $rowBuilder, $tableBuilder))
                ->withContentControl(['tag' => 'header'])
                ->addText('Header', ['bold' => true])
                ->addText('Subtitle', ['italic' => true]);
            
            expect($result)->toBeInstanceOf(CellBuilder::class);
        });

        it('chains from withContentControl to addText', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            $result = $cellBuilder
                ->withContentControl(['tag' => 'content'])
                ->addText('Content');
            
            expect($result)->toBeInstanceOf(CellBuilder::class);
            
            $registry = $tableBuilder->getContentControl()->getSDTRegistry();
            expect($registry->count())->toBe(1);
        });
    });

    describe('Edge Cases', function (): void {
        it('handles text with special characters', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            $specialText = "Special: <>&\"'\n\tEnd";
            $result = $cellBuilder->addText($specialText);
            
            expect($result)->toBe($cellBuilder);
        });

        it('handles multiple withContentControl calls without addText', function (): void {
            $tableBuilder = new TableBuilder();
            $row = new Row(100, 'exactHeight');
            $rowBuilder = new RowBuilder($row, $tableBuilder);
            $cell = new Cell(2000);
            $cellBuilder = new CellBuilder($cell, $rowBuilder, $tableBuilder);
            
            // Second config should overwrite first
            $result = $cellBuilder
                ->withContentControl(['tag' => 'first'])
                ->withContentControl(['tag' => 'second'])
                ->addText('Text');
            
            expect($result)->toBe($cellBuilder);
            
            $registry = $tableBuilder->getContentControl()->getSDTRegistry();
            expect($registry->count())->toBe(1); // Only one element, so one SDT
        });
    });
});

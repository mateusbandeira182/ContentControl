<?php

declare(strict_types=1);

use MkGrow\ContentControl\Bridge\CellBuilder;
use MkGrow\ContentControl\Bridge\RowBuilder;
use MkGrow\ContentControl\Bridge\TableBuilder;

describe('Deprecation Notices (v0.6.0)', function (): void {

    beforeEach(function (): void {
        // Reset all deprecation flags so each test captures its own warnings
        TableBuilder::resetDeprecationFlags();
        RowBuilder::resetDeprecationFlags();
        CellBuilder::resetDeprecationFlags();
    });

    it('DEP-01: addRow() triggers E_USER_DEPRECATED', function (): void {
        $deprecations = [];
        set_error_handler(function (int $errno, string $errstr) use (&$deprecations): bool {
            if ($errno === E_USER_DEPRECATED) {
                $deprecations[] = $errstr;
                return true;
            }
            return false;
        });

        try {
            $builder = new TableBuilder();
            $builder->addRow();
        } finally {
            restore_error_handler();
        }

        expect($deprecations)->toHaveCount(1);
        expect($deprecations[0])->toContain('TableBuilder::addRow()');
        expect($deprecations[0])->toContain('v0.6.0');
        expect($deprecations[0])->toContain('v0.8.0');
    });

    it('DEP-02: addCell() triggers E_USER_DEPRECATED', function (): void {
        $deprecations = [];
        set_error_handler(function (int $errno, string $errstr) use (&$deprecations): bool {
            if ($errno === E_USER_DEPRECATED) {
                $deprecations[] = $errstr;
                return true;
            }
            return false;
        });

        try {
            $builder = new TableBuilder();
            $row = $builder->addRow();
            $row->addCell(3000);
        } finally {
            restore_error_handler();
        }

        // Should contain at least one deprecation for addCell()
        $addCellDeprecations = array_filter($deprecations, fn(string $msg) => str_contains($msg, 'RowBuilder::addCell()'));
        expect($addCellDeprecations)->not->toBeEmpty();
    });

    it('DEP-03: withContentControl() triggers E_USER_DEPRECATED', function (): void {
        $deprecations = [];
        set_error_handler(function (int $errno, string $errstr) use (&$deprecations): bool {
            if ($errno === E_USER_DEPRECATED) {
                $deprecations[] = $errstr;
                return true;
            }
            return false;
        });

        try {
            $builder = new TableBuilder();
            $cell = $builder->addRow()->addCell(3000);
            $cell->withContentControl(['tag' => 'test']);
        } finally {
            restore_error_handler();
        }

        $wcDeprecations = array_filter($deprecations, fn(string $msg) => str_contains($msg, 'CellBuilder::withContentControl()'));
        expect($wcDeprecations)->not->toBeEmpty();
    });

    it('DEP-04: CellBuilder::addText() triggers E_USER_DEPRECATED', function (): void {
        $deprecations = [];
        set_error_handler(function (int $errno, string $errstr) use (&$deprecations): bool {
            if ($errno === E_USER_DEPRECATED) {
                $deprecations[] = $errstr;
                return true;
            }
            return false;
        });

        try {
            $builder = new TableBuilder();
            $cell = $builder->addRow()->addCell(3000);
            $cell->addText('Hello');
        } finally {
            restore_error_handler();
        }

        $textDeprecations = array_filter($deprecations, fn(string $msg) => str_contains($msg, 'CellBuilder::addText()'));
        expect($textDeprecations)->not->toBeEmpty();
    });

    it('DEP-05: CellBuilder::addImage() triggers E_USER_DEPRECATED', function (): void {
        $deprecations = [];
        set_error_handler(function (int $errno, string $errstr) use (&$deprecations): bool {
            if ($errno === E_USER_DEPRECATED) {
                $deprecations[] = $errstr;
                return true;
            }
            return false;
        });

        try {
            $builder = new TableBuilder();
            $cell = $builder->addRow()->addCell(3000);
            // Use a non-existent path - deprecation fires before PHPWord validates
            // PHPWord may throw, but we only care about the deprecation notice
            try {
                $cell->addImage('non_existent_image.png', ['width' => 50, 'height' => 50]);
            } catch (\Throwable) {
                // Ignore PHPWord file validation errors
            }
        } finally {
            restore_error_handler();
        }

        $imgDeprecations = array_filter($deprecations, fn(string $msg) => str_contains($msg, 'CellBuilder::addImage()'));
        expect($imgDeprecations)->not->toBeEmpty();
    });

    it('DEP-06: All deprecated methods still function correctly', function (): void {
        // Suppress deprecation warnings for this functional test
        $previousHandler = set_error_handler(function (int $errno): bool {
            if ($errno === E_USER_DEPRECATED) {
                return true;
            }
            return false;
        });

        try {
            $builder = new TableBuilder();

            // addRow() returns RowBuilder
            $row = $builder->addRow();
            expect($row)->toBeInstanceOf(\MkGrow\ContentControl\Bridge\RowBuilder::class);

            // addCell() returns CellBuilder
            $cell = $row->addCell(3000);
            expect($cell)->toBeInstanceOf(\MkGrow\ContentControl\Bridge\CellBuilder::class);

            // withContentControl() returns self
            $result = $cell->withContentControl(['tag' => 'test']);
            expect($result)->toBe($cell);

            // addText() returns self
            $result = $cell->addText('Test Content');
            expect($result)->toBe($cell);
        } finally {
            restore_error_handler();
        }
    });

});

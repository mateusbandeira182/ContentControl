<?php

declare(strict_types=1);

use MkGrow\ContentControl\ElementIdentifier;
use MkGrow\ContentControl\Bridge\TableBuilder;
use PhpOffice\PhpWord\Element\Table;

/**
 * Unit tests for ElementIdentifier::generateTableHash()
 *
 * Tests UUID v5 generation for table identification
 * Introduced to replace MD5 hash with deterministic UUID
 *
 * UUID v5 provides:
 * - Deterministic hashing (same table → same UUID)
 * - Better collision resistance than MD5
 * - Enables table matching in template injection
 *
 * @covers \MkGrow\ContentControl\ElementIdentifier::generateTableHash
 */
describe('ElementIdentifier::generateTableHash()', function (): void {
    it('generates UUID v5 format string', function (): void {
        $builder = new TableBuilder();
        $table = $builder->createTable([
            'rows' => [
                ['cells' => [['text' => 'A1'], ['text' => 'A2']]],
                ['cells' => [['text' => 'B1'], ['text' => 'B2']]],
            ],
        ]);

        $hash = ElementIdentifier::generateTableHash($table);

        expect($hash)->toBeString();
        // UUID v5 format: xxxxxxxx-xxxx-5xxx-yxxx-xxxxxxxxxxxx
        expect($hash)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
    });

    it('generates same UUID for tables with identical dimensions', function (): void {
        $builder = new TableBuilder();
        
        // Two tables with same dimensions
        $table1 = $builder->createTable([
            'rows' => [
                ['cells' => [['text' => 'A'], ['text' => 'B']]],
                ['cells' => [['text' => 'C'], ['text' => 'D']]],
            ],
        ]);
        
        $table2 = $builder->createTable([
            'rows' => [
                ['cells' => [['text' => 'X'], ['text' => 'Y']]],
                ['cells' => [['text' => 'Z'], ['text' => 'W']]],
            ],
        ]);

        $hash1 = ElementIdentifier::generateTableHash($table1);
        $hash2 = ElementIdentifier::generateTableHash($table2);

        // UUID v5 is deterministic - same dimensions should produce same UUID
        expect($hash1)->toBe($hash2);
    });

    it('generates same UUID on multiple calls for same table', function (): void {
        $builder = new TableBuilder();
        $table = $builder->createTable([
            'rows' => [
                ['cells' => [['text' => 'Test']]],
            ],
        ]);

        $hash1 = ElementIdentifier::generateTableHash($table);
        $hash2 = ElementIdentifier::generateTableHash($table);

        // UUID v5 is deterministic - same table should produce same UUID
        expect($hash1)->toBe($hash2);
    });

    it('generates different UUIDs for different dimensions', function (): void {
        $builder = new TableBuilder();
        
        $table1 = $builder->createTable([
            'rows' => [
                ['cells' => [['text' => 'A']]],
            ],
        ]);
        
        $table2 = $builder->createTable([
            'rows' => [
                ['cells' => [['text' => 'A']]],
                ['cells' => [['text' => 'B']]],
            ],
        ]);

        $hash1 = ElementIdentifier::generateTableHash($table1);
        $hash2 = ElementIdentifier::generateTableHash($table2);

        // Different dimensions should produce different UUIDs
        expect($hash1)->not->toBe($hash2);
    });

    it('generates collision-resistant hashes for similar dimensions', function (): void {
        $builder = new TableBuilder();
        $hashes = [];
        
        // Generate tables with varying dimensions
        for ($rows = 1; $rows <= 10; $rows++) {
            for ($cols = 1; $cols <= 10; $cols++) {
                $rowsArray = [];
                for ($r = 0; $r < $rows; $r++) {
                    $cellsArray = [];
                    for ($c = 0; $c < $cols; $c++) {
                        $cellsArray[] = ['text' => "R{$r}C{$c}"];
                    }
                    $rowsArray[] = ['cells' => $cellsArray];
                }
                
                $table = $builder->createTable(['rows' => $rowsArray]);
                $uuid = ElementIdentifier::generateTableHash($table);
                $hashes["{$rows}x{$cols}"] = $uuid;
            }
        }

        // All UUIDs should be unique (no collisions)
        $uniqueHashes = array_unique($hashes);
        expect(count($uniqueHashes))->toBe(count($hashes));
    });

    it('generates hashes with performance under 1ms average', function (): void {
        $builder = new TableBuilder();
        $table = $builder->createTable([
            'rows' => [
                ['cells' => [['text' => 'Performance test']]],
            ],
        ]);

        $iterations = 1000;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            ElementIdentifier::generateTableHash($table);
        }

        $duration = (microtime(true) - $start) * 1000; // Convert to milliseconds
        $averagePerCall = $duration / $iterations;

        expect($averagePerCall)->toBeLessThan(1.0); // <1ms per call
    });

    it('validates UUID version 5 variant bits', function (): void {
        $builder = new TableBuilder();
        $table = $builder->createTable([
            'rows' => [
                ['cells' => [['text' => 'Variant test']]],
            ],
        ]);

        $uuid = ElementIdentifier::generateTableHash($table);
        $parts = explode('-', $uuid);

        // Version should be 5 (third group first char should be 5)
        expect($parts[2][0])->toBe('5');

        // Variant should be 10xx binary (first char of fourth group should be 8, 9, a, or b)
        expect($parts[3][0])->toBeIn(['8', '9', 'a', 'b', 'A', 'B']);
    });

    it('throws RuntimeException for empty table', function (): void {
        $builder = new TableBuilder();
        
        // Create an empty table using reflection
        $section = $builder->getContentControl()->addSection();
        $table = $section->addTable();
        
        expect(fn() => ElementIdentifier::generateTableHash($table))
            ->toThrow(\RuntimeException::class, 'Cannot generate hash for empty table');
    });
});

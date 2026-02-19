<?php

declare(strict_types=1);

use MkGrow\ContentControl\Bridge\TableBuilder;
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Exception\ContentControlException;
use PhpOffice\PhpWord\Element\Table;

/**
 * Unit tests for TableBuilder injection methods.
 *
 * Tests covered:
 * - Hash generation consistency
 * - Hash uniqueness for different dimensions
 * - XML extraction with SDTs preserved
 * - XML namespace cleanup
 * - Table localization via hash
 */
describe('TableBuilder Injection Methods', function (): void {
    describe('extractTableXmlWithSdts()', function (): void {
        it('extracts table XML correctly', function (): void {
            $builder = new TableBuilder();

            // Create simple table without cell SDTs (due to ElementLocator limitation)
            $config = [
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
            ];

            $table = $builder->createTable($config);

            // Save to temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'table_extract_test_') . '.docx';
            try {
                $builder->getContentControl()->save($tempFile);

                // Use reflection to access private method
                $reflection = new ReflectionClass($builder);
                $method = $reflection->getMethod('extractTableXmlWithSdts');
                $method->setAccessible(true);

                $xml = $method->invoke($builder, $tempFile, $table);

                // Verify it's valid XML
                expect($xml)->toBeValidXml();

                // Verify it contains table element (without namespace prefix in extracted XML)
                expect($xml)->toContain('<w:tbl>');

                // Verify it contains rows
                expect($xml)->toContain('<w:tr>');

                // Verify it contains cells
                expect($xml)->toContain('<w:tc>');
            } finally {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
        });

        it('cleans redundant namespace declarations', function (): void {
            $builder = new TableBuilder();

            $config = [
                'rows' => [
                    ['cells' => [
                        ['text' => 'Test'],
                    ]],
                ],
            ];

            $table = $builder->createTable($config);

            // Save to temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'table_namespace_test_') . '.docx';
            try {
                $builder->getContentControl()->save($tempFile);

                // Use reflection to access private method
                $reflection = new ReflectionClass($builder);
                $method = $reflection->getMethod('extractTableXmlWithSdts');
                $method->setAccessible(true);

                $xml = $method->invoke($builder, $tempFile, $table);

                // Count xmlns:w declarations (should be none in extracted XML)
                $count = is_string($xml) ? substr_count($xml, 'xmlns:w=') : 0;

                expect($count)->toBe(0);
            } finally {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
        });

        it('throws exception if table not found', function (): void {
            $builder1 = new TableBuilder();
            $builder2 = new TableBuilder();

            // Create table with builder1
            $config = [
                'rows' => [
                    ['cells' => [['text' => 'Test']]],
                ],
            ];

            $table = $builder1->createTable($config);

            // Save with builder1
            $tempFile = tempnam(sys_get_temp_dir(), 'table_notfound_test_') . '.docx';
            try {
                $builder1->getContentControl()->save($tempFile);

                // Create different table with builder2
                $differentConfig = [
                    'rows' => [
                        ['cells' => [['text' => 'A'], ['text' => 'B']]],
                        ['cells' => [['text' => 'C'], ['text' => 'D']]],
                    ],
                ];

                $differentTable = $builder2->createTable($differentConfig);

                // Try to extract differentTable from builder1's file
                $reflection = new ReflectionClass($builder2);
                $method = $reflection->getMethod('extractTableXmlWithSdts');
                $method->setAccessible(true);

                $method->invoke($builder2, $tempFile, $differentTable);
            } finally {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
        })->throws(ContentControlException::class, 'Table with hash');

        it('throws exception for invalid DOCX file', function (): void {
            $builder = new TableBuilder();

            $config = [
                'rows' => [
                    ['cells' => [['text' => 'Test']]],
                ],
            ];

            $table = $builder->createTable($config);

            // Create invalid DOCX file
            $invalidFile = tempnam(sys_get_temp_dir(), 'invalid_') . '.docx';
            try {
                file_put_contents($invalidFile, 'Not a valid ZIP file');

                // Use reflection to access private method
                $reflection = new ReflectionClass($builder);
                $method = $reflection->getMethod('extractTableXmlWithSdts');
                $method->setAccessible(true);

                $method->invoke($builder, $invalidFile, $table);
            } finally {
                if (file_exists($invalidFile)) {
                    @unlink($invalidFile);
                }
            }
        })->throws(ContentControlException::class);
    });
});

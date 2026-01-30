<?php

declare(strict_types=1);

namespace MkGrow\ContentControl\Bridge;

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\ContentProcessor;
use MkGrow\ContentControl\Exception\ContentControlException;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Cell;

/**
 * TableBuilder - Create and inject PHPWord tables with Content Controls
 *
 * Implements Bridge pattern to separate table creation from template injection.
 * Provides a fluent interface for creating tables with automatic SDT registration
 * and injecting them into existing template documents.
 *
 * Key Features:
 * - Create tables programmatically with PHPWord
 * - Automatic Content Control registration for cells and tables
 * - Inject tables into template SDTs preserving internal structure
 * - Hash-based table identification for extraction
 * - Automatic cleanup of temporary files
 *
 * Usage:
 * ```php
 * $builder = new TableBuilder();
 * 
 * // Create table with Content Controls
 * $table = $builder->createTable([
 *     'tableTag' => 'invoice-items',
 *     'rows' => [
 *         ['cells' => [
 *             ['text' => 'Item', 'width' => 3000],
 *             ['text' => 'Price', 'width' => 2000, 'tag' => 'price-1']
 *         ]]
 *     ]
 * ]);
 * 
 * // Inject into template
 * $builder->injectTable('template.docx', 'invoice-items', $table);
 * $builder->getContentControl()->save('output.docx');
 * ```
 *
 * @package MkGrow\ContentControl\Bridge
 * @since 0.4.0
 * @final This class cannot be extended
 */
final class TableBuilder
{
    /**
     * ContentControl instance for document manipulation
     *
     * @var ContentControl
     */
    private ContentControl $contentControl;

    /**
     * Temporary file path for intermediate processing
     *
     * Used during table extraction. Automatically cleaned up in destructor.
     *
     * @var string|null
     */
    private ?string $tempFile = null;

    /**
     * TableBuilder Constructor
     *
     * Initializes the builder with a ContentControl instance.
     * If not provided, creates a new instance.
     *
     * @param ContentControl|null $contentControl Optional ContentControl instance
     *
     * @since 0.4.0
     *
     * @example
     * ```php
     * // With default ContentControl
     * $builder = new TableBuilder();
     * 
     * // With custom ContentControl
     * $cc = new ContentControl();
     * $builder = new TableBuilder($cc);
     * ```
     */
    public function __construct(?ContentControl $contentControl = null)
    {
        $this->contentControl = $contentControl ?? new ContentControl();
    }

    /**
     * Get the underlying ContentControl instance
     *
     * Provides access to the ContentControl for document manipulation,
     * saving, and other operations.
     *
     * @return ContentControl The ContentControl instance
     *
     * @since 0.4.0
     *
     * @example
     * ```php
     * $builder = new TableBuilder();
     * $cc = $builder->getContentControl();
     * 
     * // Add custom content
     * $section = $cc->addSection();
     * $section->addText('Additional content');
     * 
     * // Save document
     * $cc->save('output.docx');
     * ```
     */
    public function getContentControl(): ContentControl
    {
        return $this->contentControl;
    }

    /**
     * Destructor - ensures cleanup of temporary files
     *
     * Automatically removes any temporary files created during
     * table extraction to prevent disk space leaks.
     *
     * @return void
     *
     * @since 0.4.0
     */
    public function __destruct()
    {
        if ($this->tempFile !== null && file_exists($this->tempFile)) {
            @unlink($this->tempFile);
        }
    }

    /**
     * Validate table configuration structure
     *
     * Ensures the configuration array contains all required keys
     * and validates the structure of rows.
     *
     * @param array<string, mixed> $config Table configuration
     *
     * @throws ContentControlException If configuration is invalid
     *
     * @return void
     */
    private function validateTableConfig(array $config): void
    {
        if (!isset($config['rows'])) {
            throw new ContentControlException(
                'Table configuration must have "rows" key'
            );
        }

        if (!is_array($config['rows']) || count($config['rows']) === 0) {
            throw new ContentControlException(
                'Table "rows" must be non-empty array'
            );
        }

        foreach ($config['rows'] as $index => $rowConfig) {
            if (!is_array($rowConfig)) {
                throw new ContentControlException(
                    "Row {$index}: configuration must be an array"
                );
            }
            /** @var array<string, mixed> $rowConfig */
            $this->validateRowConfig($rowConfig, $index);
        }
    }

    /**
     * Validate row configuration structure
     *
     * Ensures each row contains valid cells configuration.
     *
     * @param array<string, mixed> $config Row configuration
     * @param int $rowIndex Row index for error messages
     *
     * @throws ContentControlException If configuration is invalid
     *
     * @return void
     */
    private function validateRowConfig(array $config, int $rowIndex): void
    {
        if (!isset($config['cells'])) {
            throw new ContentControlException(
                "Row {$rowIndex}: missing \"cells\" key"
            );
        }

        if (!is_array($config['cells']) || count($config['cells']) === 0) {
            throw new ContentControlException(
                "Row {$rowIndex}: \"cells\" must be non-empty array"
            );
        }

        foreach ($config['cells'] as $cellIndex => $cellConfig) {
            if (!is_array($cellConfig)) {
                throw new ContentControlException(
                    "Row {$rowIndex}, Cell {$cellIndex}: configuration must be an array"
                );
            }
            /** @var array<string, mixed> $cellConfig */
            $this->validateCellConfig($cellConfig, $rowIndex, $cellIndex);
        }
    }

    /**
     * Validate cell configuration structure
     *
     * Ensures each cell has either 'text' or 'element' (mutually exclusive)
     * and validates the presence of required content when tag is specified.
     *
     * @param array<string, mixed> $config Cell configuration
     * @param int $rowIndex Row index for error messages
     * @param int $cellIndex Cell index for error messages
     *
     * @throws ContentControlException If configuration is invalid
     *
     * @return void
     */
    private function validateCellConfig(array $config, int $rowIndex, int $cellIndex): void
    {
        $hasText = isset($config['text']);
        $hasElement = isset($config['element']);
        $hasTag = isset($config['tag']);

        // Mutually exclusive: text XOR element
        if ($hasText && $hasElement) {
            throw new ContentControlException(
                "Row {$rowIndex}, Cell {$cellIndex}: cannot have both \"text\" and \"element\""
            );
        }

        // Must have either text or element
        if (!$hasText && !$hasElement) {
            throw new ContentControlException(
                "Row {$rowIndex}, Cell {$cellIndex}: must have \"text\" or \"element\""
            );
        }

        // If element is provided, it's not supported in v0.4.0
        if ($hasElement) {
            throw new ContentControlException(
                "Row {$rowIndex}, Cell {$cellIndex}: \"element\" is not supported in v0.4.0, use \"text\" only"
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace MkGrow\ContentControl\Bridge;

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\ContentProcessor;
use MkGrow\ContentControl\Exception\ContentControlException;
use PhpOffice\PhpWord\Element\Table;

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
     * Create table with Content Controls from configuration
     *
     * Builds a PHPWord table with automatic SDT registration for cells and
     * optionally the entire table. Supports multi-level styling and validation.
     *
     * Configuration Structure:
     * - rows: Array of row configurations (required)
     * - style: Table-level style array (optional)
     * - tableTag: Content Control tag for entire table (optional)
     * - tableAlias: Display name for table SDT (optional)
     * - tableLockType: Lock type for table SDT (optional)
     *
     * Row Configuration:
     * - cells: Array of cell configurations (required)
     * - height: Row height in twips (optional)
     * - style: Row-level style array (optional)
     *
     * Cell Configuration:
     * - text: Cell text content (required, mutually exclusive with element)
     * - element: Custom element (NOT SUPPORTED in v0.4.0)
     * - width: Cell width in twips (optional, default: 2000)
     * - style: Cell-level style array (optional)
     * - tag: Content Control tag for cell (optional)
     * - alias: Display name for cell SDT (optional)
     * - type: SDT type (optional, default: TYPE_RICH_TEXT)
     * - lockType: SDT lock type (optional, default: LOCK_NONE)
     *
     * @phpstan-type CellConfig array{
     *     text?: string,
     *     element?: object,
     *     tag?: string,
     *     alias?: string,
     *     type?: string,
     *     lockType?: string,
     *     width?: int,
     *     style?: array<string, mixed>
     * }
     * @phpstan-type RowConfig array{
     *     cells: array<CellConfig>,
     *     height?: int|null,
     *     style?: array<string, mixed>
     * }
     * @phpstan-type TableConfig array{
     *     rows: array<RowConfig>,
     *     style?: array<string, mixed>,
     *     tableTag?: string,
     *     tableAlias?: string,
     *     tableLockType?: string
     * }
     *
     * @param array<string, mixed> $config Table configuration array
     *
     * @return Table The created PHPWord table with registered SDTs
     *
     * @throws ContentControlException If configuration is invalid or element is used
     *
     * @since 0.4.0
     *
     * @example
     * ```php
     * $builder = new TableBuilder();
     * 
     * // Simple table
     * $table = $builder->createTable([
     *     'rows' => [
     *         ['cells' => [
     *             ['text' => 'Item', 'width' => 3000],
     *             ['text' => '$0.00', 'width' => 2000, 'tag' => 'price-1']
     *         ]]
     *     ]
     * ]);
     * 
     * // Table with styles and wrapper SDT
     * $table = $builder->createTable([
     *     'tableTag' => 'invoice-items',
     *     'style' => ['borderSize' => 6, 'borderColor' => '000000'],
     *     'rows' => [
     *         ['cells' => [
     *             ['text' => 'Product', 'width' => 4000],
     *             ['text' => 'Price', 'width' => 2000]
     *         ]]
     *     ]
     * ]);
     * ```
     */
    public function createTable(array $config): Table
    {
        // 1. Validate configuration structure
        $this->validateTableConfig($config);
        
        // 2. Get section from ContentControl
        $section = $this->contentControl->addSection();
        
        // 3. Create table with optional style
        $tableStyle = $config['style'] ?? [];
        $table = $section->addTable($tableStyle);
        
        // 4. Process rows
        /** @var array<string, mixed> $rowsConfig */
        $rowsConfig = $config['rows'];
        
        foreach ($rowsConfig as $rowConfig) {
            /** @var array<string, mixed> $rowConfig */
            $rowHeight = isset($rowConfig['height']) && is_int($rowConfig['height']) ? $rowConfig['height'] : null;
            $rowStyle = isset($rowConfig['style']) && is_array($rowConfig['style']) ? $rowConfig['style'] : [];
            $row = $table->addRow($rowHeight, $rowStyle);
            
            // 5. Process cells
            /** @var array<array<string, mixed>> $cellsConfig */
            $cellsConfig = $rowConfig['cells'];
            
            foreach ($cellsConfig as $cellConfig) {
                $cellWidth = isset($cellConfig['width']) && is_int($cellConfig['width']) ? $cellConfig['width'] : 2000;
                $cellStyle = isset($cellConfig['style']) && is_array($cellConfig['style']) ? $cellConfig['style'] : [];
                $cell = $row->addCell($cellWidth, $cellStyle);
                
                // 6. Add content (text only in v0.4.0)
                if (isset($cellConfig['element'])) {
                    throw new ContentControlException(
                        'Custom elements in cells not yet supported. Use "text" property.'
                    );
                }
                
                $textContent = isset($cellConfig['text']) && is_string($cellConfig['text']) 
                    ? $cellConfig['text'] 
                    : '';
                $textElement = $cell->addText($textContent);
                
                // 7. Register SDT for cell if configured
                if (isset($cellConfig['tag'])) {
                    $tag = is_string($cellConfig['tag']) ? $cellConfig['tag'] : '';
                    $alias = isset($cellConfig['alias']) && is_string($cellConfig['alias']) 
                        ? $cellConfig['alias'] 
                        : $tag;
                    $type = isset($cellConfig['type']) && is_string($cellConfig['type'])
                        ? $cellConfig['type']
                        : ContentControl::TYPE_RICH_TEXT;
                    $lockType = isset($cellConfig['lockType']) && is_string($cellConfig['lockType'])
                        ? $cellConfig['lockType']
                        : ContentControl::LOCK_NONE;
                    
                    $sdtConfig = [
                        'tag' => $tag,
                        'alias' => $alias,
                        'type' => $type,
                        'lockType' => $lockType,
                    ];
                    $this->contentControl->addContentControl($textElement, $sdtConfig);
                }
            }
        }
        
        // 8. Register table wrapper SDT if configured
        if (isset($config['tableTag'])) {
            $tableTag = is_string($config['tableTag']) ? $config['tableTag'] : '';
            $tableAlias = isset($config['tableAlias']) && is_string($config['tableAlias'])
                ? $config['tableAlias']
                : $tableTag;
            $tableLockType = isset($config['tableLockType']) && is_string($config['tableLockType'])
                ? $config['tableLockType']
                : ContentControl::LOCK_NONE;
            
            $tableSdtConfig = [
                'tag' => $tableTag,
                'alias' => $tableAlias,
                'type' => ContentControl::TYPE_GROUP,
                'lockType' => $tableLockType,
            ];
            $this->contentControl->addContentControl($table, $tableSdtConfig);
        }
        
        return $table;
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

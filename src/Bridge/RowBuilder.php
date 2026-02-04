<?php

declare(strict_types=1);

namespace MkGrow\ContentControl\Bridge;

use PhpOffice\PhpWord\Element\Row;

/**
 * Row Builder - Fluent interface for table rows
 *
 * Provides a chainable API for building table rows with cells and Content Controls.
 * Part of the Builder Pattern implementation for PHPWord table construction.
 *
 * Usage Example:
 * ```php
 * $builder = new TableBuilder();
 * $builder->addRow()
 *     ->addCell(2000)
 *         ->addText('Header 1')
 *         ->end()
 *     ->addCell(2000)
 *         ->addText('Header 2')
 *         ->end()
 *     ->end();
 * ```
 *
 * @package MkGrow\ContentControl\Bridge
 * @since 0.4.2
 * @final
 */
final class RowBuilder
{
    /**
     * The PhpWord Row element being built
     *
     * @var Row
     */
    private Row $row;

    /**
     * Reference to parent TableBuilder for method chaining
     *
     * @var TableBuilder
     */
    private TableBuilder $parent;

    /**
     * Constructs a new RowBuilder instance
     *
     * @param Row $row The PhpWord Row element to wrap
     * @param TableBuilder $parent The parent TableBuilder for chaining
     */
    public function __construct(Row $row, TableBuilder $parent)
    {
        $this->row = $row;
        $this->parent = $parent;
    }

    /**
     * Adds a new cell to the row and returns a CellBuilder
     *
     * Creates a new cell in the current row with the specified width
     * and optional styling. Returns a CellBuilder instance to enable
     * adding content and Content Controls to the cell.
     *
     * Example:
     * ```php
     * $rowBuilder->addCell(2000, ['bgColor' => 'CCCCCC'])
     *     ->addText('Cell content')
     *     ->end();
     * ```
     *
     * @param int $width Cell width in twips (1/1440 inch)
     * @param array<string, mixed> $style Optional cell styling (bgColor, borders, etc.)
     * @return CellBuilder Builder for the created cell
     */
    public function addCell(int $width, array $style = []): CellBuilder
    {
        $cell = $this->row->addCell($width, $style);
        return new CellBuilder($cell, $this, $this->parent);
    }

    /**
     * Ends row building and returns to parent TableBuilder
     *
     * Completes the current row and returns the parent TableBuilder,
     * allowing you to add more rows or finalize the table.
     *
     * Example:
     * ```php
     * $tableBuilder = $rowBuilder->end();
     * $tableBuilder->addRow(); // Add another row
     * ```
     *
     * @return TableBuilder The parent TableBuilder instance
     */
    public function end(): TableBuilder
    {
        return $this->parent;
    }
}

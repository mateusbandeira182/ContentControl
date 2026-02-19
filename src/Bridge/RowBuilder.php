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
 * $row = $builder->addRow();
 * $row->addCell(2000)->addText('Header 1');
 * $row->addCell(2000)->addText('Header 2');
 * ```
 *
 * @package MkGrow\ContentControl\Bridge
 * @since 0.4.2
 * @deprecated Since v0.6.0, will be removed in v0.8.0.
 *             Use direct PHPWord Table API with TableBuilder::addContentControl() instead.
 * @final
 */
final class RowBuilder
{
    /**
     * Flag: whether addCell() deprecation was already emitted
     *
     * @var bool
     * @internal
     */
    private static bool $addCellWarned = false;

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
     * $cell = $rowBuilder->addCell(2000, ['bgColor' => 'CCCCCC']);
     * $cell->addText('Cell content');
     * ```
     *
     * @param int $width Cell width in twips (1/1440 inch)
     * @param array<string, mixed> $style Optional cell styling (bgColor, borders, etc.)
     * @return CellBuilder Builder for the created cell
     *
     * @deprecated Since v0.6.0, will be removed in v0.8.0.
     *             Use direct PHPWord Table API with TableBuilder::addContentControl() instead.
     *             See docs/migration/v0.5.2-to-v0.6.0.md for migration guide.
     */
    public function addCell(int $width, array $style = []): CellBuilder
    {
        // Emit deprecation warning (only once per script execution to avoid log spam)
        if (!self::$addCellWarned) {
            trigger_error(
                'RowBuilder::addCell() is deprecated since v0.6.0 and will be removed in v0.8.0. ' .
                'Use direct PHPWord Table API with TableBuilder::addContentControl() instead. ' .
                'See docs/migration/v0.5.2-to-v0.6.0.md for migration guide.',
                E_USER_DEPRECATED
            );
            self::$addCellWarned = true;
        }

        $cell = $this->row->addCell($width, $style);
        return new CellBuilder($cell, $this, $this->parent);
    }

    /**
     * Reset deprecation warning flags (for testing only)
     *
     * @internal This method is intended for test cleanup only.
     *           Do not call in production code.
     *
     * @codeCoverageIgnore
     */
    public static function resetDeprecationFlags(): void
    {
        self::$addCellWarned = false;
    }
}

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
     * Flag: whether end() deprecation was already emitted
     *
     * @var bool
     * @internal
     */
    private static bool $endWarned = false;

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
     * Ends row building and returns to parent TableBuilder
     *
     * @deprecated Since v0.5.1, will be removed in v0.7.0
     *             The end() method pattern is foreign to PHPWord conventions.
     *             Recommended: Use end() calls until v0.6.0 introduces optional auto-close.
     *             Migration timeline: v0.7.0 (H1 2027) will remove this method entirely.
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
        // Emit deprecation warning (only once per script execution to avoid log spam)
        if (!self::$endWarned) {
            trigger_error(
                'RowBuilder::end() is deprecated since v0.5.1 and will be removed in v0.7.0. ' .
                'Continue using end() for now. In v0.6.0, end() will become optional (auto-close pattern). ' .
                'Full removal planned for v0.7.0 (18-month deprecation window).',
                E_USER_DEPRECATED
            );
            self::$endWarned = true;
        }
        
        return $this->parent;
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
        self::$endWarned = false;
    }
}

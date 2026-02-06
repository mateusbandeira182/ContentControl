<?php

declare(strict_types=1);

namespace MkGrow\ContentControl\Bridge;

use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\Cell;

/**
 * Cell Builder - Fluent interface for table cells
 *
 * Provides a chainable API for adding content and Content Controls to table cells.
 * Supports deferred SDT registration, allowing you to specify Content Control
 * configuration before adding the actual element.
 *
 * Usage Example:
 * ```php
 * $cellBuilder->withContentControl(['tag' => 'user-name', 'alias' => 'User Name'])
 *     ->addText('John Doe')
 *     ->end();
 * ```
 *
 * @package MkGrow\ContentControl\Bridge
 * @since 0.4.2
 * @final
 */
final class CellBuilder
{
    /**
     * The PhpWord Cell element being built
     *
     * @var Cell
     */
    private Cell $cell;

    /**
     * Reference to parent RowBuilder for method chaining
     *
     * @var RowBuilder
     */
    private RowBuilder $parent;

    /**
     * Reference to root TableBuilder for SDT registration
     *
     * @var TableBuilder
     */
    private TableBuilder $tableParent;

    /**
     * Pending SDT configuration to apply to next element
     *
     * @var array{id?: string, alias?: string, tag?: string, type?: string, lockType?: string, inlineLevel?: bool}|null
     */
    private ?array $sdtConfig = null;

    /**
     * Constructs a new CellBuilder instance
     *
     * @param Cell $cell The PhpWord Cell element to wrap
     * @param RowBuilder $parent The parent RowBuilder for chaining
     * @param TableBuilder $tableParent The root TableBuilder for SDT registration
     */
    public function __construct(Cell $cell, RowBuilder $parent, TableBuilder $tableParent)
    {
        $this->cell = $cell;
        $this->parent = $parent;
        $this->tableParent = $tableParent;
    }

    /**
     * Sets Content Control configuration for the next element
     *
     * The configuration will be applied when the next element is added
     * (via `addText()` or `addImage()`), then automatically reset.
     *
     * Example:
     * ```php
     * $cellBuilder->withContentControl([
     *     'tag' => 'product-price',
     *     'alias' => 'Product Price',
     *     'type' => ContentControl::TYPE_PLAIN_TEXT,
     *     'lockType' => ContentControl::LOCK_CONTENT_LOCKED
     * ])->addText('$99.99');
     * ```
     *
     * @param array{id?: string, alias?: string, tag?: string, type?: string, lockType?: string, inlineLevel?: bool} $config SDT configuration
     * @return self This CellBuilder for method chaining
     */
    public function withContentControl(array $config): self
    {
        $this->sdtConfig = $config;
        return $this;
    }

    /**
     * Adds text content to the cell
     *
     * Creates a Text element in the cell with optional styling.
     * If a Content Control configuration was set via `withContentControl()`,
     * it will be applied to this text element.
     *
     * Example:
     * ```php
     * $cellBuilder->addText('Hello World', ['bold' => true, 'size' => 14]);
     * ```
     *
     * With Content Control:
     * ```php
     * $cellBuilder->withContentControl(['tag' => 'greeting'])
     *     ->addText('Hello World');
     * ```
     *
     * @param string $text The text content
     * @param array<string, mixed> $style Optional text styling (bold, italic, size, color, etc.)
     * @return self This CellBuilder for method chaining
     */
    public function addText(string $text, array $style = []): self
    {
        $textElement = $this->cell->addText($text, $style);

        // Apply pending SDT configuration
        if ($this->sdtConfig !== null) {
            $this->tableParent->registerSdt($textElement, $this->sdtConfig);
            $this->sdtConfig = null; // Reset after application
        }

        return $this;
    }

    /**
     * Adds an image to the cell
     *
     * Creates an Image element in the cell with optional styling.
     * If a Content Control configuration was set via `withContentControl()`,
     * it will be applied to this image element.
     *
     * Example:
     * ```php
     * $cellBuilder->addImage('path/to/image.jpg', [
     *     'width' => 100,
     *     'height' => 100,
     *     'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
     * ]);
     * ```
     *
     * With Content Control:
     * ```php
     * $cellBuilder->withContentControl(['tag' => 'product-image', 'type' => ContentControl::TYPE_PICTURE])
     *     ->addImage('product.jpg');
     * ```
     *
     * @param string $source Path to the image file
     * @param array<string, mixed> $style Optional image styling (width, height, alignment, etc.)
     * @return self This CellBuilder for method chaining
     */
    public function addImage(string $source, array $style = []): self
    {
        $imageElement = $this->cell->addImage($source, $style);

        // Apply pending SDT configuration
        if ($this->sdtConfig !== null) {
            $this->tableParent->registerSdt($imageElement, $this->sdtConfig);
            $this->sdtConfig = null; // Reset after application
        }

        return $this;
    }

    /**
     * Ends cell building and returns to parent RowBuilder
     *
     * @deprecated Since v0.5.1, will be removed in v0.7.0
     *             The end() method pattern is foreign to PHPWord conventions.
     *             Recommended: Use end() calls until v0.6.0 introduces optional auto-close.
     *             Migration timeline: v0.7.0 (H1 2027) will remove this method entirely.
     *
     * Completes the current cell and returns the parent RowBuilder,
     * allowing you to add more cells or end the row.
     *
     * Example:
     * ```php
     * $rowBuilder = $cellBuilder->end();
     * $rowBuilder->addCell(2000); // Add another cell
     * ```
     *
     * @return RowBuilder The parent RowBuilder instance
     */
    public function end(): RowBuilder
    {
        // Emit deprecation warning (only once per script execution to avoid log spam)
        static $warned = false;
        if (!$warned) {
            trigger_error(
                'CellBuilder::end() is deprecated since v0.5.1 and will be removed in v0.7.0. ' .
                'Continue using end() for now. In v0.6.0, end() will become optional (auto-close pattern). ' .
                'Full removal planned for v0.7.0 (18-month deprecation window).',
                E_USER_DEPRECATED
            );
            $warned = true;
        }
        
        return $this->parent;
    }
}

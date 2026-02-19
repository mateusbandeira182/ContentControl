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
 *     ->addText('John Doe');
 * ```
 *
 * @package MkGrow\ContentControl\Bridge
 * @since 0.4.2
 * @deprecated Since v0.6.0, will be removed in v0.8.0.
 *             Use direct PHPWord Cell API with TableBuilder::addContentControl() instead.
 * @final
 */
final class CellBuilder
{
    /**
     * Flag: whether withContentControl() deprecation was already emitted
     *
     * @var bool
     * @internal
     */
    private static bool $withContentControlWarned = false;

    /**
     * Flag: whether addText() deprecation was already emitted
     *
     * @var bool
     * @internal
     */
    private static bool $addTextWarned = false;

    /**
     * Flag: whether addImage() deprecation was already emitted
     *
     * @var bool
     * @internal
     */
    private static bool $addImageWarned = false;

    /**
     * The PhpWord Cell element being built
     *
     * @var Cell
     */
    private Cell $cell;

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
     *
     * @deprecated Since v0.6.0, will be removed in v0.8.0.
     *             Use TableBuilder::addContentControl() instead.
     *             See docs/migration/v0.5.2-to-v0.6.0.md for migration guide.
     */
    public function withContentControl(array $config): self
    {
        // Emit deprecation warning (only once per script execution to avoid log spam)
        if (!self::$withContentControlWarned) {
            trigger_error(
                'CellBuilder::withContentControl() is deprecated since v0.6.0 and will be removed in v0.8.0. ' .
                'Use TableBuilder::addContentControl() instead. ' .
                'See docs/migration/v0.5.2-to-v0.6.0.md for migration guide.',
                E_USER_DEPRECATED
            );
            self::$withContentControlWarned = true;
        }

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
     *
     * @deprecated Since v0.6.0, will be removed in v0.8.0.
     *             Use direct PHPWord Cell::addText() with TableBuilder::addContentControl() instead.
     *             See docs/migration/v0.5.2-to-v0.6.0.md for migration guide.
     */
    public function addText(string $text, array $style = []): self
    {
        // Emit deprecation warning (only once per script execution to avoid log spam)
        if (!self::$addTextWarned) {
            trigger_error(
                'CellBuilder::addText() is deprecated since v0.6.0 and will be removed in v0.8.0. ' .
                'Use direct PHPWord Cell::addText() with TableBuilder::addContentControl() instead. ' .
                'See docs/migration/v0.5.2-to-v0.6.0.md for migration guide.',
                E_USER_DEPRECATED
            );
            self::$addTextWarned = true;
        }

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
     *
     * @deprecated Since v0.6.0, will be removed in v0.8.0.
     *             Use direct PHPWord Cell::addImage() with TableBuilder::addContentControl() instead.
     *             See docs/migration/v0.5.2-to-v0.6.0.md for migration guide.
     */
    public function addImage(string $source, array $style = []): self
    {
        // Emit deprecation warning (only once per script execution to avoid log spam)
        if (!self::$addImageWarned) {
            trigger_error(
                'CellBuilder::addImage() is deprecated since v0.6.0 and will be removed in v0.8.0. ' .
                'Use direct PHPWord Cell::addImage() with TableBuilder::addContentControl() instead. ' .
                'See docs/migration/v0.5.2-to-v0.6.0.md for migration guide.',
                E_USER_DEPRECATED
            );
            self::$addImageWarned = true;
        }

        $imageElement = $this->cell->addImage($source, $style);

        // Apply pending SDT configuration
        if ($this->sdtConfig !== null) {
            $this->tableParent->registerSdt($imageElement, $this->sdtConfig);
            $this->sdtConfig = null; // Reset after application
        }

        return $this;
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
        self::$withContentControlWarned = false;
        self::$addTextWarned = false;
        self::$addImageWarned = false;
    }
}

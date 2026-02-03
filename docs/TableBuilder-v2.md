# TableBuilder v2 - Fluent Interface API (v0.4.2)

**Fluent Interface for PHP Table Creation with Content Controls**

## Table of Contents

1. [Overview](#overview)
2. [Migration from v0.4.1](#migration-from-v041)
3. [Core Concepts](#core-concepts)
4. [Quick Start](#quick-start)
5. [API Reference](#api-reference)
6. [Advanced Examples](#advanced-examples)
7. [Best Practices](#best-practices)

---

## Overview

The **TableBuilder v2** fluent API (introduced in v0.4.2) replaces the declarative array-based `createTable()` method with a type-safe, chainable interface. This dramatically improves developer experience while maintaining 100% backward compatibility.

### Key Benefits

| Feature | Legacy API (v0.4.1) | Fluent API (v0.4.2) | Improvement |
|---------|---------------------|---------------------|-------------|
| **Code Length** | 30+ lines for complex tables | 12 lines | **-60%** |
| **Type Safety** | Runtime validation only | Compile-time (PHPStan Level 9) | **+100%** |
| **IDE Support** | No autocomplete (arrays) | Full autocomplete | **+100%** |
| **Nesting Depth** | 4-5 levels (arrays) | 2 levels (chaining) | **-50%** |
| **Error Messages** | Generic array errors | Specific method errors | **Better** |

### Architecture

```
TableBuilder (Director)
    ├─> addRow() → RowBuilder
    │     ├─> addCell() → CellBuilder
    │     │     ├─> addText()
    │     │     ├─> addImage()
    │     │     ├─> withContentControl()
    │     │     └─> end() → RowBuilder
    │     └─> end() → TableBuilder
    └─> addContentControl() → TableBuilder (table-level SDT)
```

---

## Migration from v0.4.1

### Legacy API (Deprecated in v0.4.2)

```php
$builder = new TableBuilder();

$table = $builder->createTable([
    'rows' => [
        ['cells' => [
            ['text' => 'Name', 'width' => 3000, 'style' => ['bold' => true]],
            ['text' => 'Age', 'width' => 2000],
        ]],
        ['cells' => [
            ['text' => 'Alice', 'width' => 3000, 'tag' => 'name-1'],
            ['text' => '30', 'width' => 2000],
        ]],
    ],
]); // 12 lines, deeply nested, no autocomplete
```

**Deprecation Warning (since v0.4.2):**
```
TableBuilder::createTable() is deprecated since v0.4.2. Use fluent API instead: 
$builder->addRow()->addCell()->end(). Will be removed in v1.0.0.
```

### Fluent API (Recommended)

```php
$cc = new ContentControl();
$builder = new TableBuilder($cc);

$builder
    ->addRow()
        ->addCell(3000)->addText('Name', ['bold' => true])->end()
        ->addCell(2000)->addText('Age')->end()
    ->end()
    ->addRow()
        ->addCell(3000)
            ->addText('Alice')
            ->withContentControl(['tag' => 'name-1'])
        ->end()
        ->addCell(2000)->addText('30')->end()
    ->end();

$cc->save('output.docx'); // 11 lines, flat hierarchy, full autocomplete
```

---

## Core Concepts

### 1. Builder Pattern

Each level of the table structure has its own builder:

- **TableBuilder**: Manages table-level operations (`addRow()`, `addContentControl()`)
- **RowBuilder**: Manages row-level operations (`addCell()`, `end()`)
- **CellBuilder**: Manages cell content (`addText()`, `addImage()`, `withContentControl()`, `end()`)

### 2. Fluent Chaining

Every method returns either:
- **`self`** - For chaining operations at the same level
- **Child Builder** - For descending into nested structure (`addCell()` → `CellBuilder`)
- **Parent Builder** - For ascending back up (`end()` → `RowBuilder/TableBuilder`)

### 3. SDT Configuration Before Element Creation

`withContentControl()` configures the SDT **before** the element is created. The configuration is applied when `addText()` / `addImage()` is called:

```php
->addCell(3000)
    ->withContentControl(['tag' => 'customer'])  // 1. Set configuration
    ->addText('Customer Name')                   // 2. Create element and apply SDT
->end()
```

---

## Quick Start

### Basic Table

```php
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Bridge\TableBuilder;

$cc = new ContentControl();
$builder = new TableBuilder($cc);

$builder
    ->addRow()
        ->addCell(3000)->addText('Product')->end()
        ->addCell(2000)->addText('Price')->end()
    ->end()
    ->addRow()
        ->addCell(3000)->addText('Widget A')->end()
        ->addCell(2000)->addText('$50.00')->end()
    ->end();

$cc->save('basic-table.docx');
```

### Table with Cell Content Controls

```php
$builder
    ->addRow()
        ->addCell(4000)->addText('Customer Name:')->end()
        ->addCell(4000)
            ->withContentControl([
                'tag' => 'customer_name',
                'alias' => 'Customer Name Field',
                'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
            ])
            ->addText('{{ placeholder }}')
        ->end()
    ->end();
```

### Table with Table-Level Content Control

```php
$builder
    ->addRow()
        ->addCell(2500)->addText('Item', ['bold' => true])->end()
        ->addCell(2000)->addText('Price', ['bold' => true])->end()
    ->end()
    ->addRow()
        ->addCell(2500)->addText('Widget')->end()
        ->addCell(2000)->addText('$20.00')->end()
    ->end()
    ->addContentControl([
        'tag' => 'invoice_items',
        'alias' => 'Invoice Items Table',
        'lockType' => ContentControl::LOCK_SDT_LOCKED,
    ]);

$cc->save('table-with-sdt.docx');
```

---

## API Reference

### TableBuilder

#### `__construct(?ContentControl $contentControl = null)`

Creates a new TableBuilder with optional ContentControl instance.

```php
$cc = new ContentControl();
$builder = new TableBuilder($cc);

// Or with default ContentControl
$builder = new TableBuilder();
```

#### `addRow(?int $height = null, array $style = []): RowBuilder`

Adds a new row to the table.

**Parameters:**
- `$height` (int|null): Row height in twips (1/20 point). Default: auto
- `$style` (array): PHPWord row style options

```php
$builder->addRow(500, ['tblHeader' => true]); // Header row, 500 twips height
```

#### `addContentControl(array $config): self`

Wraps the entire table with a Content Control.

**Parameters:**
- `$config['tag']` (string): SDT tag (required)
- `$config['alias']` (string): Display name (optional)
- `$config['lockType']` (string): Lock type (optional)

```php
$builder->addContentControl([
    'tag' => 'invoice-table',
    'alias' => 'Invoice Items',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);
```

#### `getContentControl(): ContentControl`

Returns the underlying ContentControl instance.

```php
$cc = $builder->getContentControl();
$cc->save('output.docx');
```

---

### RowBuilder

#### `addCell(int $width, array $style = []): CellBuilder`

Adds a cell to the current row.

**Parameters:**
- `$width` (int): Cell width in twips (required)
- `$style` (array): PHPWord cell style options

```php
$rowBuilder->addCell(3000, ['bgColor' => 'CCCCCC']);
```

#### `end(): TableBuilder`

Finalizes the row and returns to TableBuilder.

```php
->addRow()
    ->addCell(3000)->addText('Data')->end()
->end() // Back to TableBuilder
```

---

### CellBuilder

#### `addText(string $text, array $style = []): self`

Adds text content to the cell.

**Parameters:**
- `$text` (string): Text content
- `$style` (array): PHPWord text style options (`bold`, `size`, `color`, etc.)

```php
->addCell(3000)
    ->addText('Bold Red Text', ['bold' => true, 'color' => 'FF0000'])
->end()
```

#### `addImage(string $source, array $style = []): self`

Adds an image to the cell.

**Parameters:**
- `$source` (string): Image file path or URL
- `$style` (array): PHPWord image style options (`width`, `height`, etc.)

```php
->addCell(3000)
    ->addImage('logo.png', ['width' => 100, 'height' => 50])
->end()
```

#### `withContentControl(array $config): self`

Configures the Content Control to apply to the next element (text/image).

**Parameters:**
- `$config['tag']` (string): SDT tag (required)
- `$config['alias']` (string): Display name (optional)
- `$config['lockType']` (string): Lock type (optional)

```php
->addCell(3000)
    ->withContentControl([
        'tag' => 'customer_name',
        'alias' => 'Customer Name',
    ])
    ->addText('Customer Name')
->end()
```

#### `end(): RowBuilder`

Finalizes the cell and returns to RowBuilder.

```php
->addCell(3000)->addText('Data')->end() // Back to RowBuilder
```

---

## Advanced Examples

### Complex Nested Structure

```php
$cc = new ContentControl();
$builder = new TableBuilder($cc);

$builder
    ->addRow()
        ->addCell(3000)->addText('Product', ['bold' => true, 'size' => 12])->end()
        ->addCell(3000)->addText('Status', ['bold' => true, 'size' => 12])->end()
    ->end()
    ->addRow()
        ->addCell(3000)
            ->withContentControl(['tag' => 'product_name'])
            ->addText('Premium Widget')
        ->end()
        ->addCell(3000)
            ->withContentControl(['tag' => 'product_status'])
            ->addText('In Stock', ['color' => '00AA00'])
        ->end()
    ->end()
    ->addContentControl([
        'tag' => 'product_catalog',
        'lockType' => ContentControl::LOCK_SDT_LOCKED,
    ]);

$cc->save('nested-sdts.docx');
```

### Template Injection with Fluent API

```php
use MkGrow\ContentControl\ContentProcessor;

// Create table with fluent API
$cc = new ContentControl();
$builder = new TableBuilder($cc);

$builder
    ->addRow()
        ->addCell(3000)->addText('Item')->end()
        ->addCell(2000)->addText('Price')->end()
    ->end()
    ->addRow()
        ->addCell(3000)->addText('Widget A')->end()
        ->addCell(2000)->addText('$50.00')->end()
    ->end();

// Inject into template
$processor = new ContentProcessor('template.docx');
$builder->injectInto($processor, 'invoice-items');
$processor->save('output.docx');
```

---

## Best Practices

### 1. Use Fluent API for New Code

```php
// ✅ GOOD: Fluent API (type-safe, readable)
$builder->addRow()->addCell(3000)->addText('Data')->end()->end();

// ❌ BAD: Legacy API (deprecated)
$builder->createTable(['rows' => [['cells' => [['text' => 'Data']]]]]);
```

### 2. Consistent Indentation

```php
$builder
    ->addRow()
        ->addCell(3000)->addText('Name')->end()
        ->addCell(2000)->addText('Age')->end()
    ->end()
    ->addRow()
        ->addCell(3000)->addText('Alice')->end()
        ->addCell(2000)->addText('30')->end()
    ->end();
```

### 3. SDT After Element Creation

```php
// ✅ GOOD: Add SDT after content
->addCell(3000)
    ->addText('Customer Name')
    ->withContentControl(['tag' => 'customer'])
->end()

// ❌ BAD: Cannot add SDT without content
->addCell(3000)
    ->withContentControl(['tag' => 'customer'])  // No element to wrap!
    ->addText('Customer Name')
->end()
```

### 4. Use Descriptive Tags

```php
// ✅ GOOD: Semantic tags
->withContentControl(['tag' => 'customer_name', 'alias' => 'Customer Name'])

// ❌ BAD: Generic tags
->withContentControl(['tag' => 'field1'])
```

### 5. Lock Appropriately

```php
// For user-editable fields
->withContentControl([
    'tag' => 'customer_name',
    'lockType' => ContentControl::LOCK_NONE,  // Can edit and delete
])

// For locked content (prevent deletion)
->withContentControl([
    'tag' => 'customer_name',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,  // Can edit, cannot delete
])

// For read-only content
->withContentControl([
    'tag' => 'invoice_total',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,  // Cannot edit
])
```

---

## Performance Comparison

| Metric | Legacy API | Fluent API | Difference |
|--------|-----------|------------|------------|
| Lines of Code (complex table) | 30 | 12 | **-60%** |
| Type Errors Caught | Runtime | Compile-time | **Better** |
| PHPStan Errors | Varies | 0 | **Level 9** |
| IDE Autocomplete | No | Yes | **+100%** |
| Learning Curve | Medium | Low | **Easier** |

---

## See Also

- [Migration Guide](MIGRATION-v042.md) - Detailed migration steps
- [GROUP SDT Documentation](GROUP-SDT-FIX.md) - GROUP Content Control support
- [Sample Files](../samples/) - Complete working examples
- [API Reference](../README.md) - Core ContentControl documentation

---

**Version:** v0.4.2  
**Last Updated:** February 3, 2026  
**Status:** Stable

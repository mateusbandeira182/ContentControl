# TableBuilder API Documentation

> Create and inject PHPWord tables with automatic Content Control wrapping

**Version:** 0.4.0  
**Namespace:** `MkGrow\ContentControl\Bridge\TableBuilder`  
**Since:** v0.4.0

---

## Table of Contents

1. [Overview](#overview)
2. [Quick Start](#quick-start)
3. [API Reference](#api-reference)
4. [Configuration](#configuration)
5. [Use Cases](#use-cases)
6. [Known Limitations](#known-limitations)
7. [Advanced Topics](#advanced-topics)

---

## Overview

The `TableBuilder` class is a bridge component that simplifies creating and injecting PHPWord tables into template documents. It provides:

- **Declarative Table Creation:** Define tables using simple array configuration
- **Automatic SDT Wrapping:** Tables are automatically wrapped in Content Controls for template workflows
- **Template Injection:** Replace placeholders in existing DOCX files with dynamic tables
- **Multi-Level Styling:** Apply styles at table, row, and cell levels

### Architecture

```
TableBuilder
├── createTable(array $config): Table
│   └── Returns PHPWord Table instance with SDT wrapper
│
└── injectTable(string $path, string $tag, Table $table): void
    ├── Extracts table XML from temporary document
    ├── Locates target SDT in template
    ├── Replaces SDT content with table
    └── Saves modified template
```

---

## Quick Start

### Installation

TableBuilder is included with ContentControl v0.4.0+:

```bash
composer require mkgrow/content-control ^0.4
```

### Basic Example

```php
<?php
use MkGrow\ContentControl\Bridge\TableBuilder;

$builder = new TableBuilder();

// Create table
$table = $builder->createTable([
    'rows' => [
        ['cells' => [['text' => 'Name'], ['text' => 'Age']]],
        ['cells' => [['text' => 'Alice'], ['text' => '30']]],
        ['cells' => [['text' => 'Bob'], ['text' => '25']]],
    ],
]);

// Inject into template
$builder->injectTable('template.docx', 'user-table', $table);
```

---

## API Reference

### Constructor

#### `__construct(?ContentControl $contentControl = null)`

Creates a new TableBuilder instance.

**Parameters:**
- `$contentControl` *(ContentControl|null)*: Optional ContentControl instance. If null, creates new instance.

**Example:**

```php
// Auto-create ContentControl
$builder = new TableBuilder();

// Use existing ContentControl
$cc = new ContentControl();
$builder = new TableBuilder($cc);
```

---

### getContentControl()

#### `getContentControl(): ContentControl`

Returns the underlying ContentControl instance.

**Returns:** `ContentControl` - The internal ContentControl instance

**Example:**

```php
$builder = new TableBuilder();
$cc = $builder->getContentControl();

// Use ContentControl directly
$section = $cc->addSection();
$section->addText('Additional content');
```

---

### createTable()

#### `createTable(array $config): Table`

Creates a PHPWord Table instance from configuration array.

**Parameters:**
- `$config` *(array)*: Table configuration (see [Configuration](#configuration))

**Returns:** `PhpOffice\PhpWord\Element\Table` - PHPWord table instance

**Throws:**
- `ContentControlException` - If configuration is invalid

**Example:**

```php
$table = $builder->createTable([
    'style' => [
        'borderSize' => 6,
        'borderColor' => '1F4788',
    ],
    'rows' => [
        [
            'height' => 500,
            'cells' => [
                ['text' => 'Header 1', 'width' => 3000],
                ['text' => 'Header 2', 'width' => 2000],
            ],
        ],
        [
            'cells' => [
                ['text' => 'Data 1', 'width' => 3000],
                ['text' => 'Data 2', 'width' => 2000],
            ],
        ],
    ],
]);
```

---

### injectTable()

#### `injectTable(string $templatePath, string $targetSdtTag, Table $table): void`

Injects a table into an existing DOCX template by replacing the content of a Content Control.

**Parameters:**
- `$templatePath` *(string)*: Absolute path to template DOCX file
- `$targetSdtTag` *(string)*: Tag of the target Content Control
- `$table` *(Table)*: PHPWord Table instance to inject

**Returns:** `void` - Modifies template file in-place

**Throws:**
- `ContentControlException` - If template not found, SDT not found, or injection fails

**Example:**

```php
// 1. Create template
$cc = new ContentControl();
$section = $cc->addSection();
$placeholder = $section->addText('Table will be inserted here');
$cc->addContentControl($placeholder, ['tag' => 'invoice-items']);
$cc->save('template.docx');

// 2. Create and inject table
$builder = new TableBuilder();
$table = $builder->createTable([
    'rows' => [
        ['cells' => [['text' => 'Item 1'], ['text' => '$10']]],
        ['cells' => [['text' => 'Item 2'], ['text' => '$20']]],
    ],
]);
$builder->injectTable('template.docx', 'invoice-items', $table);

// template.docx now contains the table
```

---

## Configuration

### Table Configuration Schema

```php
/**
 * @phpstan-type CellConfig array{
 *     text: string,               // Required: cell content
 *     width?: int,                // Optional: cell width in twips (1/1440 inch)
 *     style?: array{              // Optional: cell styles
 *         alignment?: string,     // 'left'|'center'|'right'|'justify'
 *         valign?: string,        // 'top'|'center'|'bottom'
 *         bgColor?: string,       // Hex color without #
 *         bold?: bool,
 *         italic?: bool,
 *         size?: int,             // Font size in points
 *         color?: string,         // Text color hex
 *     }
 * }
 *
 * @phpstan-type RowConfig array{
 *     cells: array<CellConfig>,  // Required: array of cell configurations
 *     height?: int,              // Optional: row height in twips
 * }
 *
 * @phpstan-type TableConfig array{
 *     rows: array<RowConfig>,    // Required: array of row configurations
 *     style?: array{             // Optional: table styles
 *         borderSize?: int,      // Border width in eighths of a point
 *         borderColor?: string,  // Border color hex
 *         cellMargin?: int,      // Cell margin in twips
 *         layout?: string,       // 'fixed'|'autofit'
 *     }
 * }
 */
```

### Configuration Examples

#### Minimal Configuration

```php
$config = [
    'rows' => [
        ['cells' => [['text' => 'Simple cell']]],
    ],
];
```

#### Complete Configuration

```php
$config = [
    'style' => [
        'borderSize' => 12,
        'borderColor' => '1F4788',
        'cellMargin' => 100,
        'layout' => 'fixed',
    ],
    'rows' => [
        [
            'height' => 800,
            'cells' => [
                [
                    'text' => 'Header Cell',
                    'width' => 3000,
                    'style' => [
                        'alignment' => 'center',
                        'valign' => 'center',
                        'bgColor' => 'FFCC00',
                        'bold' => true,
                        'size' => 12,
                        'color' => '000000',
                    ],
                ],
            ],
        ],
    ],
];
```

---

## Use Cases

### Invoice Template with Dynamic Items

```php
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Bridge\TableBuilder;

// 1. Create template
$template = new ContentControl();
$section = $template->addSection();

$section->addText('INVOICE', ['bold' => true, 'size' => 18]);
$section->addText(''); // spacing

$placeholder = $section->addText('Items will be inserted here');
$template->addContentControl($placeholder, [
    'tag' => 'invoice-items',
    'alias' => 'Invoice Items Table',
]);

$template->save('invoice-template.docx');

// 2. Generate invoice
$builder = new TableBuilder();

$items = [
    ['name' => 'Widget A', 'qty' => 5, 'price' => '$100.00'],
    ['name' => 'Widget B', 'qty' => 3, 'price' => '$75.00'],
    ['name' => 'Service Fee', 'qty' => 1, 'price' => '$50.00'],
];

$rows = [
    ['cells' => [
        ['text' => 'Item', 'width' => 3000],
        ['text' => 'Qty', 'width' => 1500],
        ['text' => 'Price', 'width' => 1500],
    ]],
];

foreach ($items as $item) {
    $rows[] = ['cells' => [
        ['text' => $item['name'], 'width' => 3000],
        ['text' => (string)$item['qty'], 'width' => 1500],
        ['text' => $item['price'], 'width' => 1500],
    ]];
}

$table = $builder->createTable([
    'style' => ['borderSize' => 6],
    'rows' => $rows,
]);

$builder->injectTable('invoice-template.docx', 'invoice-items', $table);
```

### Report with Multiple Tables

```php
$builder = new TableBuilder();

// Summary table
$summary = $builder->createTable([
    'rows' => [
        ['cells' => [['text' => 'Total Sales'], ['text' => '$10,000']]],
        ['cells' => [['text' => 'Total Expenses'], ['text' => '$3,000']]],
        ['cells' => [['text' => 'Net Profit'], ['text' => '$7,000']]],
    ],
]);

// Details table
$details = $builder->createTable([
    'rows' => [
        ['cells' => [['text' => 'Q1'], ['text' => '$2,500']]],
        ['cells' => [['text' => 'Q2'], ['text' => '$2,700']]],
        ['cells' => [['text' => 'Q3'], ['text' => '$2,300']]],
        ['cells' => [['text' => 'Q4'], ['text' => '$2,500']]],
    ],
]);

// Inject both
$builder->injectTable('report-template.docx', 'summary-table', $summary);
$builder->injectTable('report-template.docx', 'details-table', $details);
```

### Styled Header Row

```php
$table = $builder->createTable([
    'style' => [
        'borderSize' => 10,
        'borderColor' => '1F4788',
    ],
    'rows' => [
        // Header row with styling
        [
            'height' => 700,
            'cells' => [
                [
                    'text' => 'Product',
                    'width' => 3000,
                    'style' => [
                        'bgColor' => '1F4788',
                        'color' => 'FFFFFF',
                        'bold' => true,
                        'alignment' => 'center',
                        'valign' => 'center',
                    ],
                ],
                [
                    'text' => 'Price',
                    'width' => 2000,
                    'style' => [
                        'bgColor' => '1F4788',
                        'color' => 'FFFFFF',
                        'bold' => true,
                        'alignment' => 'center',
                        'valign' => 'center',
                    ],
                ],
            ],
        ],
        // Data rows
        ['cells' => [
            ['text' => 'Widget A', 'width' => 3000],
            ['text' => '$100.00', 'width' => 2000],
        ]],
        ['cells' => [
            ['text' => 'Widget B', 'width' => 3000],
            ['text' => '$75.00', 'width' => 2000],
        ]],
    ],
]);
```

---

## Known Limitations

### 1. Cell-Level Content Controls Not Supported

**Issue:** Cannot apply Content Controls to individual table cells in v0.4.0.

```php
// ❌ NOT SUPPORTED in v0.4.0
$config = [
    'rows' => [
        ['cells' => [
            [
                'element' => $customElement, // Not allowed
                'sdt' => ['tag' => 'cell-tag'], // Not allowed
            ],
        ]],
    ],
];
```

**Workaround:** Use table-level SDTs (wrap entire table) or wait for v0.5.0.

**Roadmap:** Cell SDTs planned for v0.5.0 (requires ElementLocator enhancement).

### 2. Hash-Based Table Matching

**Issue:** Tables are identified by dimensions (rows x cells) using MD5 hash.

**Impact:** Tables with same dimensions may collide in rare cases.

**Example:**

```php
// These two tables have the same hash (3 rows x 2 cells)
$table1 = $builder->createTable([
    'rows' => [
        ['cells' => [['text' => 'A1'], ['text' => 'A2']]],
        ['cells' => [['text' => 'B1'], ['text' => 'B2']]],
        ['cells' => [['text' => 'C1'], ['text' => 'C2']]],
    ],
]);

$table2 = $builder->createTable([
    'rows' => [
        ['cells' => [['text' => 'X1'], ['text' => 'X2']]],
        ['cells' => [['text' => 'Y1'], ['text' => 'Y2']]],
        ['cells' => [['text' => 'Z1'], ['text' => 'Z2']]],
    ],
]);

// If both are in the same document, extraction may fail
```

**Mitigation:**
- Clear error message: `"Table with hash {hash} not found in document"`
- Avoid multiple tables with same dimensions in one document

**Roadmap:** Content-based hashing in v0.5.0.

### 3. Custom Elements Not Supported

**Issue:** Only text content allowed in cells.

```php
// ❌ NOT SUPPORTED
$config = [
    'rows' => [
        ['cells' => [
            ['element' => $phpWordImage], // Not allowed
        ]],
    ],
];
```

**Workaround:** Create table manually using PHPWord API.

**Roadmap:** Custom element support in v0.5.0.

---

## Advanced Topics

### Temporary File Handling

`injectTable()` uses a temporary file strategy for Windows compatibility:

```
1. Save table to temp file
2. Extract table XML
3. Copy template to temp file
4. Modify temp file
5. Copy back to original
6. Cleanup (automatic via __destruct())
```

**Important:** Temporary files are automatically cleaned up. Manual cleanup not required.

### XML Namespace Handling

Tables are serialized with WordprocessingML namespace:

```xml
<w:tbl xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:tblPr>...</w:tblPr>
    <w:tr>...</w:tr>
</w:tbl>
```

Redundant namespace declarations are automatically removed during extraction.

### Performance Considerations

**Benchmark (50 rows x 5 cells):**
- Table creation: < 10ms
- Injection: < 200ms (target)

**Optimization Tips:**
- Reuse TableBuilder instance for multiple tables
- Process tables in batch if possible
- Avoid excessive styling (impacts Word rendering)

### Error Handling

All methods throw `ContentControlException` with descriptive messages:

```php
use MkGrow\ContentControl\Exception\ContentControlException;

try {
    $builder->injectTable('template.docx', 'missing-tag', $table);
} catch (ContentControlException $e) {
    echo $e->getMessage();
    // "SDT with tag 'missing-tag' not found in template"
}
```

**Common Errors:**
- `"Template file not found: {path}"`
- `"SDT with tag '{tag}' not found in template"`
- `"Table with hash {hash} not found in document"`
- `"Invalid configuration: rows is required"`
- `"Row 2, Cell 3: text or element is required"`

---

## Examples Repository

See `samples/` directory for complete examples:

- `samples/table_builder_basic.php` - Basic usage
- `samples/table_builder_advanced.php` - Styled tables with headers
- `samples/table_builder_injection.php` - Template injection workflow

---

## ISO/IEC 29500-1:2016 Compliance

TableBuilder generates OOXML-compliant structures:

- **Tables:** `<w:tbl>` elements per §17.4.38
- **Content Controls:** `<w:sdt>` elements per §17.5.2
- **Namespaces:** WordprocessingML namespace per §M.1.1

---

## See Also

- [ContentControl API](../README.md#api-reference)
- [ContentProcessor API](../README.md#contentprocessor-api-template-processing)
- [PHPWord Documentation](https://phpword.readthedocs.io/)
- [OOXML Specification](https://www.iso.org/standard/71691.html)

---

**Version:** 0.4.0  
**Last Updated:** January 30, 2026  
**Maintained By:** MkGrow Development Team

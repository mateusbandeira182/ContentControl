# ContentControl

> PHP library for adding Word Content Controls (Structured Document Tags) to PHPOffice/PHPWord documents

[![Latest Stable Version](https://poser.pugx.org/mkgrow/content-control/v/stable)](https://packagist.org/packages/mkgrow/content-control)
[![CI Status](https://img.shields.io/github/actions/workflow/status/mateusbandeira182/ContentControl/ci.yml?branch=main&label=CI)](https://github.com/mateusbandeira182/ContentControl/actions)
[![Code Coverage](https://img.shields.io/badge/coverage-85%25-brightgreen)](coverage/html/index.html)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue)](composer.json)
[![PHPStan Level 9](https://img.shields.io/badge/PHPStan-Level%209-brightgreen)](phpstan.neon)

---

## Features

- **Proxy Pattern API** - Unified interface encapsulating PhpWord with automatic SDT management
- **Content Protection** - Lock elements from editing or deletion in Word documents
- **Fluent TableBuilder API** - Type-safe, chainable interface for table creation with 60% less code (v0.4.2)
- **TableBuilder** - Create and inject tables into templates with automatic SDT wrapping (v0.3.0)
- **GROUP SDT Support** - Replace GROUP Content Controls with complex structures preserving nested SDTs (v0.4.2)
- **ContentProcessor** - Open and modify existing DOCX files with powerful manipulation methods (v0.3.0)
  - `replaceGroupContent()` - Replace GROUP SDT with complex structures (v0.4.2)
  - `replaceContent()` - Replace entire Content Control content
  - `setValue()` - Replace text while preserving formatting (bold, color, size, etc.)
  - `appendContent()` - Add content to existing SDT content
  - `removeContent()` - Clear specific Content Control
  - `removeAllControlContents()` - Clear all SDTs and optionally block editing
- **Headers & Footers** - Apply Content Controls to headers and footers (v0.2.0)
- **UUID v5 Hashing** - Zero-collision deterministic table hashing (SHA-1 based) replacing MD5 (v0.4.2)
- **Type-Safe Configuration** - Immutable value objects for Content Control properties
- **Production Ready** - 464 tests, PHPStan Level 9 (0 errors), 82%+ code coverage
- **Zero Dependencies** - Only requires PHPOffice/PHPWord (already in your project)

## Fluent TableBuilder API (v0.4.2)

The `TableBuilder` now supports a **fluent interface** for type-safe, chainable table creation:

```php
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Bridge\TableBuilder;

$cc = new ContentControl();
$builder = new TableBuilder($cc);

$builder
    ->addRow()
        ->addCell(3000)->addText('Product', ['bold' => true])->end()
        ->addCell(2000)->addText('Price', ['bold' => true])->end()
    ->end()
    ->addRow()
        ->addCell(3000)
            ->withContentControl(['tag' => 'product_name'])
            ->addText('Widget A')
        ->end()
        ->addCell(2000)
            ->withContentControl(['tag' => 'product_price'])
            ->addText('$50.00')
        ->end()
    ->end();

$cc->save('fluent-table.docx');
```

**Benefits:** 60% less code, full IDE autocomplete, compile-time type safety

**See:** [`docs/TableBuilder-v2.md`](docs/TableBuilder-v2.md) for complete fluent API documentation

---

## TableBuilder - Dynamic Table Creation (v0.3.0)

The legacy declarative API is still supported (deprecated in v0.4.2):

```php
use MkGrow\ContentControl\Bridge\TableBuilder;

// Create table from configuration
$builder = new TableBuilder();
$table = $builder->createTable([
    'style' => [
        'borderSize' => 6,
        'borderColor' => '000000',
    ],
    'rows' => [
        [
            'height' => 500,
            'cells' => [
                ['text' => 'Header 1', 'width' => 3000, 'style' => ['bold' => true]],
                ['text' => 'Header 2', 'width' => 3000, 'style' => ['bold' => true]],
            ]
        ],
        [
            'cells' => [
                ['text' => 'Data 1', 'width' => 3000],
                ['text' => 'Data 2', 'width' => 3000],
            ]
        ]
    ]
]);


// Inject into template with SDT placeholder
$builder->injectTable('template.docx', 'invoice-table', $table);
```

**See:** `docs/TableBuilder.md` for complete API reference and examples

## Installation

```bash
composer require mkgrow/content-control
```

**Requirements:** PHP 8.2+ | PHPOffice/PHPWord 1.x

## Quick Start

```php
<?php
require 'vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;

// Create document with Proxy Pattern
$cc = new ContentControl();

// Add section and text
$section = $cc->addSection();
$text = $section->addText('Protected content');

// Add Content Control (SDT)
$cc->addContentControl($text, [
    'alias' => 'Customer Name',
    'tag' => 'customer-name',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_SDT_LOCKED  // Cannot delete, can edit
]);

// Save with automatic SDT injection
$cc->save('protected-document.docx');
```

## Advanced Example: Protected Table Cells

```php
<?php
use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();
$section = $cc->addSection();

// Create invoice table
$table = $section->addTable(['borderSize' => 6]);

// Header row
$table->addRow();
$table->addCell(3000)->addText('Item', ['bold' => true]);
$table->addCell(2000)->addText('Quantity', ['bold' => true]);
$table->addCell(2000)->addText('Price', ['bold' => true]);

// Data row with protected cells
$table->addRow();
$itemCell = $table->addCell(3000);
$itemText = $itemCell->addText('Product Name');

$qtyCell = $table->addCell(2000);
$qtyText = $qtyCell->addText('5');

$priceCell = $table->addCell(2000);
$priceText = $priceCell->addText('$100.00');

// Protect specific cells
$cc->addContentControl($itemText, [
    'alias' => 'Product',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED  // Cannot edit content
]);

$cc->addContentControl($priceText, [
    'alias' => 'Unit Price',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED
]);

// Quantity is editable (no Content Control)

$cc->save('protected-invoice.docx');
```

## ContentProcessor - Template Processing (v0.3.0)

The `ContentProcessor` class allows you to open existing DOCX files and modify Content Controls programmatically:

```php
<?php
use MkGrow\ContentControl\ContentProcessor;
use PhpOffice\PhpWord\PhpWord;

// Open existing template
$processor = new ContentProcessor('template.docx');

// Replace text in Content Controls by tag
$processor->replaceContent('customer-name', 'Acme Corporation');
$processor->replaceContent('invoice-date', '2026-01-30');

// Replace with PHPWord elements (tables, formatted text, etc.)
$phpWord = new PhpWord();
$section = $phpWord->addSection();
$table = $section->addTable();
$table->addRow();
$table->addCell(3000)->addText('Product A');
$table->addCell(2000)->addText('$100.00');

$processor->replaceContent('invoice-items', $table);

// Save (in-place or to new file)
$processor->save('output.docx');
```

**Requirements for Template:**
- DOCX file must contain Content Controls with `tag` attributes
- Tags are case-sensitive and must match exactly
- Supports: Text, TextRun, Table, Image elements

**See:** `samples/content_processor_example.php` for complete example

## API Reference

### Content Control Types

| Constant | Description | XML Element |
|----------|-------------|-------------|
| `TYPE_RICH_TEXT` | Text with formatting (default) | `<w:richText/>` |
| `TYPE_PLAIN_TEXT` | Plain text without formatting | `<w:text/>` |
| `TYPE_PICTURE` | Image control | `<w:picture/>` |
| `TYPE_GROUP` | Grouping control | `<w:group/>` |

### Lock Types

| Constant | Description | Effect in Word |
|----------|-------------|----------------|
| `LOCK_NONE` | No locking (default) | Fully editable and deletable |
| `LOCK_SDT_LOCKED` | Control locked | Cannot delete, content editable |
| `LOCK_CONTENT_LOCKED` | Content locked | Can delete, content not editable |
| `LOCK_UNLOCKED` | Explicitly unlocked | Same as LOCK_NONE |

### Configuration Options

```php
$cc->addContentControl($element, [
    'id' => '12345678',          // Optional: 8-digit ID (auto-generated if omitted)
    'alias' => 'Display Name',   // Optional: Name shown in Word UI (max 255 chars)
    'tag' => 'metadata-tag',     // Optional: Programmatic identifier (alphanumeric + _-.)
    'type' => 'richText',        // Optional: Control type (default: TYPE_RICH_TEXT)
    'lockType' => 'sdtLocked',   // Optional: Lock level (default: LOCK_NONE)
    'inlineLevel' => false       // Optional: Inline-level SDT injection (default: false, experimental)
]);
```

### Inline-Level Content Controls

**Available since:** v0.4.1  
**Status:** Production Ready - Fully functional and tested

#### What is Inline-Level?

Content Controls can be injected at two levels in OOXML documents:

| Level | Structure | Use Case | Status |
|-------|-----------|----------|--------|
| **Block-level** (default) | `<w:body>` → `<w:sdt>` → `<w:p>` | Protect entire elements | Supported: Stable |
| **Inline-level** | `<w:tc>` → `<w:sdt>` → `<w:p>` | Protect content inside table cells | Supported: Production Ready |

#### Use Case: Editable Cells in Locked Tables

Combine GROUP SDT (locks table structure) with inline-level SDTs (allows cell editing):

```php
<?php
use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();
$section = $cc->addSection();

// Create table
$table = $section->addTable(['borderSize' => 6]);
$table->addRow();

// Add editable cell content
$cell = $table->addCell(3000);
$text = $cell->addText('Editable content');

// Wrap table with GROUP SDT (locks structure)
$cc->addContentControl($table, [
    'alias' => 'Invoice Table',
    'type' => ContentControl::TYPE_GROUP,
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

// Wrap cell content with inline SDT (allows editing inside locked table)
$cc->addContentControl($text, [
    'alias' => 'Customer Name',
    'inlineLevel' => true,  // Inject inside <w:tc> instead of <w:body>
    'lockType' => ContentControl::LOCK_NONE
]);

$cc->save('locked-table-editable-cells.docx');
```

#### Known Limitations

1. **Manual Parameter Required**: PHPWord does not expose element context (`container` property), so you must explicitly set `'inlineLevel' => true`
2. **Mixed Content**: When using inline-level SDTs, avoid registering block-level Text elements before inline elements (register inline SDTs first, then block-level elements)

#### Technical Details

**Block-level XML:**
```xml
<w:body>
    <w:sdt>
        <w:sdtPr><w:id w:val="12345678"/></w:sdtPr>
        <w:sdtContent>
            <w:p>...</w:p>  <!-- Original paragraph -->
        </w:sdtContent>
    </w:sdt>
</w:body>
```

**Inline-level XML:**
```xml
<w:tbl>
    <w:tr>
        <w:tc>
            <w:sdt>
                <w:sdtPr><w:id w:val="12345678"/></w:sdtPr>
                <w:sdtContent>
                    <w:p>...</w:p>  <!-- Paragraph inside cell -->
                </w:sdtContent>
            </w:sdt>
        </w:tc>
    </w:tr>
</w:tbl>
```

**Roadmap (v4.0):**
- Implement XPath queries for Text/TextRun in `<w:tc>` elements
- Reactivate skipped integration tests
- Full validation in OnlyOffice/Word/LibreOffice
- Create `samples/inline_sdt_example.php` with end-to-end workflow

**See:** `CHANGELOG.md` for detailed implementation notes

## Supported Elements

ContentControl can wrap the following PHPWord elements with Structured Document Tags (SDTs):

| Element | Class | OOXML Structure | SDT Type | Version |
|---------|-------|-----------------|----------|---------|
| **Text** | `\PhpOffice\PhpWord\Element\Text` | `<w:p>` | `TYPE_RICH_TEXT` | Supported: v0.0.0 |
| **TextRun** | `\PhpOffice\PhpWord\Element\TextRun` | `<w:p>` (multi-run) | `TYPE_RICH_TEXT` | Supported: v0.0.0 |
| **Table** | `\PhpOffice\PhpWord\Element\Table` | `<w:tbl>` | `TYPE_RICH_TEXT` | Supported: v0.0.0 |
| **Cell** | `\PhpOffice\PhpWord\Element\Cell` | `<w:tc>` | `TYPE_RICH_TEXT` | Supported: v0.0.0 |
| **Title** | `\PhpOffice\PhpWord\Element\Title` | `<w:p>` (with `w:pStyle`) | `TYPE_RICH_TEXT` | Supported: v0.1.0 |
| **Image** | `\PhpOffice\PhpWord\Element\Image` | `<w:p><w:pict>` | `TYPE_PICTURE` | Supported: v0.1.0 |
| TOC | `\PhpOffice\PhpWord\Element\TOC` | `<w:fldChar>` (multi-paragraph) | - | Not supported |
| Section | `\PhpOffice\PhpWord\Element\Section` | `<w:sectPr>` | - | Not wrappable |

### Title Elements vs Document Metadata

PHPWord has two distinct "title" concepts that are commonly confused:

**1. Document Metadata** (stored in `docProps/core.xml`):
```php
$cc->getDocInfo()->setTitle('Report Title'); // NOT wrappable with SDT
```
This sets the document properties visible in File > Properties in Word.
CANNOT be wrapped with Content Controls (not part of document body XML).

**2. Title Content Element** (stored in `word/document.xml`):
```php
$title = $section->addTitle('Report Title', 0); // Wrappable with SDT
$cc->addContentControl($title, ['tag' => 'doc-title']);
```
This creates a styled heading in the document body.
CAN be wrapped with Content Controls.

**Common Mistake:**
```php
// WRONG: Trying to sync metadata and wrap it
$cc->getDocInfo()->setTitle('Report');
$cc->addContentControl(???, ['tag' => 'title']); // No element to wrap!
```

**Correct Pattern:**
```php
// Create Title element in document body
$title = $section->addTitle('Report Title', 0);
$cc->addContentControl($title, ['tag' => 'doc-title']);

// Optionally sync metadata separately
$cc->getDocInfo()->setTitle('Report Title');
```

**Helper Method (v0.6.0+):**
```php
// Combines both in one call
$cc->addProtectedTitle($section, 'Report Title', 0, 
    ['tag' => 'doc-title'], 
    syncMetadata: true
);
```

---

## ContentProcessor API (Template Processing)

### Opening Documents

```php
use MkGrow\ContentControl\ContentProcessor;

// Open existing DOCX file
$processor = new ContentProcessor('path/to/template.docx');
```

### Replacing Content

#### `replaceContent(string $tag, string|AbstractElement $value): bool`

Replace ALL content of a Content Control. Removes existing content and inserts new.

```php
// Replace with simple text
$processor->replaceContent('customer-name', 'Acme Corporation');

// Replace with PHPWord element (preserves formatting)
$phpWord = new PhpOffice\PhpWord\PhpWord();
$section = $phpWord->addSection();
$table = $section->addTable();
// ... configure table
$processor->replaceContent('invoice-items', $table);

// Returns true if SDT found, false otherwise
$success = $processor->replaceContent('non-existent-tag', 'value'); // false
```

### Preserving Formatting: setValue()

#### `setValue(string $tag, string $value): bool`

Replace text while preserving formatting (bold, italic, color, size, etc.).

```php
// Template has bold, 14pt customer name
$processor->setValue('customer-name', 'New Company Name');
// Result: "New Company Name" is ALSO bold and 14pt

// Multiple text nodes are consolidated into first
$processor->setValue('address', 'Single consolidated text');
```

**How it works:**
- Finds all `<w:t>` (text) nodes within SDT
- Replaces content of FIRST `<w:t>` node
- Removes remaining `<w:t>` nodes (consolidation)
- Preserves parent `<w:r>` (run) properties: bold, italic, color, font size, etc.

**Difference from `replaceContent()`:**
| Method | Formatting | Structure | Use Case |
|--------|-----------|-----------|----------|
| `setValue()` | Preserved | Text only | Formatted fields |
| `replaceContent()` | Reset | Any element | Complex structures |

### Appending Content

#### `appendContent(string $tag, AbstractElement $element): bool`

Add content to the END of existing Content Control content.

```php
$phpWord = new PhpOffice\PhpWord\PhpWord();
$section = $phpWord->addSection();

// Append multiple paragraphs to list
$processor->appendContent('notes', $section->addText('First note'));
$processor->appendContent('notes', $section->addText('Second note'));
$processor->appendContent('notes', $section->addText('Third note'));

// Useful for building lists, adding rows to tables, etc.
```

**Note:** No type validation in v1.0 - you are responsible for matching content types (e.g., don't append text to table SDT).

### Clearing Content

#### `removeContent(string $tag): bool`

Remove all content from a Content Control (leaves SDT structure intact).

```php
// Clear customer name field
$processor->removeContent('customer-name');

// SDT remains, content is empty - can be refilled later
$processor->replaceContent('customer-name', 'New Name');
```

**Use cases:**
- Resetting templates for reuse
- Clearing optional fields
- Pre-processing before final content insertion

### Finalizing Documents

#### `removeAllControlContents(bool $block = false): int`

Remove content from ALL Content Controls in document. Optionally add document protection.

```php
// Clear all SDTs, keep document editable
$count = $processor->removeAllControlContents();
echo "Cleared {$count} Content Controls";

// Clear all SDTs AND block editing (read-only)
$count = $processor->removeAllControlContents(true);
// Document is now protected - only tracked changes allowed
```

**What happens:**
1. Searches all XML files (document.xml, headers, footers)
2. Finds all `<w:sdt>` elements
3. Clears `<w:sdtContent>` (preserves SDT structure)
4. If `$block = true`: Creates/modifies `word/settings.xml` to add `<w:documentProtection w:edit="readOnly"/>`

**Use cases:**
- Template finalization (remove placeholder values)
- Document archiving (make read-only after completion)
- Compliance workflows (prevent further editing)

**Exceptions:**
- `InvalidArgumentException` - File does not exist or is not readable
- `ZipArchiveException` - Not a valid ZIP/DOCX file
- `DocumentNotFoundException` - Missing word/document.xml
- `RuntimeException` - Malformed XML

### Replacing Content

```php
// Replace with string
$processor->replaceContent('tag-name', 'New text content');

// Replace with PHPWord element
$phpWord = new PhpWord();
$section = $phpWord->addSection();
$table = $section->addTable();
// ... build table ...
$processor->replaceContent('table-tag', $table);

// Returns bool (true if tag found, false otherwise)
```

**Supported Elements:**
- `string` - Converted to `<w:p><w:r><w:t>text</w:t></w:r></w:p>`
- `Text` - Single text run with formatting
- `TextRun` - Multiple formatted text runs
- `Table` - Complete table structure

### Saving Documents

```php
// Save in-place (modifies original file)
$processor->save();

// Save to new file
$processor->save('output/final.docx');
```

**Important:** `ContentProcessor` is single-use. Cannot modify after `save()`.

### Working with Headers/Footers

Content Controls in headers and footers are automatically detected:

```php
// Template has SDT in header with tag="header-title"
$processor = new ContentProcessor('template.docx');
$processor->replaceContent('header-title', 'New Header Text');
$processor->save();
```

Search order: `document.xml` → `header*.xml` → `footer*.xml`

### Coming Soon

```php
// Future enhancements under consideration:
// - Multi-file batch processing
// - SDT content type validation
// - Advanced namespace management
```

---

## Headers and Footers

**NEW in v0.2.0:** Content Controls can now be applied to elements in headers and footers!

### Basic Usage

```php
use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();
$section = $cc->addSection();

// Add header with protected content
$header = $section->addHeader();
$headerText = $header->addText('Company Name - Confidential', ['bold' => true]);

$cc->addContentControl($headerText, [
    'alias' => 'Company Header',
    'tag' => 'company-header',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

// Add footer with protected copyright
$footer = $section->addFooter();
$copyrightText = $footer->addText('© 2026 Company. All Rights Reserved.', [
    'alignment' => 'center'
]);

$cc->addContentControl($copyrightText, [
    'alias' => 'Copyright Notice',
    'tag' => 'copyright',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

$section->addText('Document body content...');
$cc->save('protected_headers.docx');
```

### Header/Footer Types

PHPWord supports three types of headers/footers per section:

| Type | Usage | Method Call |
|------|-------|-------------|
| **Default** | All pages (or odd pages in duplex) | `$section->addHeader()` |
| **First** | First page only | `$section->addHeader('first')` |
| **Even** | Even pages in duplex mode | `$section->addHeader('even')` |

Same applies to footers: `addFooter()`, `addFooter('first')`, `addFooter('even')`

### First Page Headers/Footers

```php
$cc = new ContentControl();
$section = $cc->addSection();

// Special header for first page (cover page)
$firstHeader = $section->addHeader('first');
$coverTitle = $firstHeader->addText('ANNUAL REPORT 2026', [
    'bold' => true,
    'size' => 18,
    'alignment' => 'center',
    'color' => '1F4788'
]);

$cc->addContentControl($coverTitle, [
    'alias' => 'Cover Page Title',
    'tag' => 'cover-title',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

// Default header for subsequent pages
$defaultHeader = $section->addHeader();
$standardHeader = $defaultHeader->addText('Annual Report - Page Header', [
    'size' => 10
]);

$cc->addContentControl($standardHeader, [
    'alias' => 'Standard Header',
    'tag' => 'standard-header'
]);

$cc->save('different_first_page.docx');
```

### Complex Headers with Tables

```php
$cc = new ContentControl();
$section = $cc->addSection();

$header = $section->addHeader();

// Create letterhead table
$table = $header->addTable([
    'borderSize' => 0,
    'width' => 100 * 50,
    'unit' => 'pct',
]);

$table->addRow(400);
$table->addCell(4000)->addText('ACME Corporation', ['bold' => true, 'size' => 14]);
$table->addCell(4000)->addText('Document #: 12345', ['alignment' => 'right']);

$table->addRow(300);
$table->addCell(4000)->addText('123 Business Street, City, ST 12345', ['size' => 9]);
$table->addCell(4000)->addText('Date: 2026-01-29', ['alignment' => 'right', 'size' => 9]);

// Protect entire letterhead table
$cc->addContentControl($table, [
    'alias' => 'Letterhead Table',
    'tag' => 'letterhead',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

$cc->save('letterhead_document.docx');
```

### Multiple Sections with Independent Headers

```php
$cc = new ContentControl();

// Section 1: Introduction
$intro = $cc->addSection();
$introHeader = $intro->addHeader();
$introHeaderText = $introHeader->addText('Section 1: Introduction', ['bold' => true]);
$cc->addContentControl($introHeaderText, [
    'alias' => 'Intro Header',
    'tag' => 'intro-header'
]);

// Section 2: Main Content (different header)
$main = $cc->addSection();
$mainHeader = $main->addHeader();
$mainHeaderText = $mainHeader->addText('Section 2: Analysis', ['bold' => true]);
$cc->addContentControl($mainHeaderText, [
    'alias' => 'Main Header',
    'tag' => 'main-header'
]);

$cc->save('multi_section_headers.docx');
```

### Supported Elements in Headers/Footers

All element types supported in the body can also be used in headers/footers:

- Supported: Text
- Supported: TextRun (formatted text)
- Supported: Table
- Supported: Cell (individual table cells)
- Supported: Image
- Not supported: Title (not applicable - titles are body-only elements)

### Performance

Header/footer processing adds minimal overhead:

- **Single section** (body + header + footer): < 250ms
- **3 sections** (each with header + footer): < 500ms
- **10 sections**: < 1000ms
- **Overhead**: ≤ 20% compared to body-only processing

### Complete Examples

See `samples/header_footer_examples.php` for 6 complete examples demonstrating:
1. Basic header/footer protection
2. Complex headers with tables
3. First page vs default headers
4. Even page footers
5. Multiple sections with independent headers/footers
6. Mixed content types in headers/footers

Run examples:
```bash
php samples/header_footer_examples.php
php samples/complete_end_to_end_example.php
```

### Advanced Examples

#### Working with Hierarchical Titles

```php
use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();

// Add title styles (required before adding titles)
$cc->addTitleStyle(0, ['size' => 20, 'bold' => true]); // Title (depth 0)
$cc->addTitleStyle(1, ['size' => 18, 'bold' => true]); // Heading1
$cc->addTitleStyle(2, ['size' => 16, 'bold' => true]); // Heading2
$cc->addTitleStyle(3, ['size' => 14, 'bold' => true]); // Heading3

$section = $cc->addSection();

// Add hierarchical structure
$docTitle = $section->addTitle('Annual Report 2025', 0);
$chapter = $section->addTitle('Chapter 1: Introduction', 1);
$section1 = $section->addTitle('1.1 Background', 2);
$section2 = $section->addTitle('1.1.1 Historical Context', 3);

// Wrap titles with Content Controls
$cc->addContentControl($chapter, [
    'alias' => 'Chapter Title',
    'tag' => 'chapter-1',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

$cc->addContentControl($section1, [
    'alias' => 'Section 1.1',
    'tag' => 'section-1-1',
]);

$cc->save('document_with_titles.docx');

// Note: Bookmarks are preserved - Table of Contents will still work!
```

#### Working with Images

```php
use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();
$section = $cc->addSection();

// Add inline image
$inlineImage = $section->addImage('logo.png', [
    'width' => 100,
    'height' => 100,
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
]);

// Add another image with different size
$productImage = $section->addImage('product.jpg', [
    'width' => 200,
    'height' => 150,
]);

// Wrap images with Content Controls (use TYPE_PICTURE for images)
$cc->addContentControl($inlineImage, [
    'alias' => 'Company Logo',
    'tag' => 'logo-image',
    'type' => ContentControl::TYPE_PICTURE,
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED
]);

$cc->addContentControl($productImage, [
    'alias' => 'Product Image',
    'tag' => 'product-image',
    'type' => ContentControl::TYPE_PICTURE
]);

$cc->save('document_with_images.docx');
```

#### Mixed Documents (Titles + Images + Text)

```php
$cc = new ContentControl();
$cc->addTitleStyle(1, ['size' => 16, 'bold' => true]);

$section = $cc->addSection();

// Build mixed content
$title = $section->addTitle('Chapter 1: Overview', 1);
$text = $section->addText('This is the introduction paragraph.');
$image = $section->addImage('chart.png', ['width' => 300, 'height' => 200]);

// Wrap all elements
$cc->addContentControl($title, ['alias' => 'Chapter Title', 'tag' => 'ch1']);
$cc->addContentControl($text, ['alias' => 'Intro Text', 'tag' => 'intro']);
$cc->addContentControl($image, [
    'alias' => 'Chart Image',
    'tag' => 'chart',
    'type' => ContentControl::TYPE_PICTURE
]);

$cc->save('mixed_document.docx');
```

#### Table of Contents Workaround

```php
// TOC elements cannot be wrapped directly, but you can wrap individual titles

$cc = new ContentControl();
$cc->addTitleStyle(1, ['size' => 16, 'bold' => true]);

$mainSection = $cc->addSection();

// Add TOC first
$mainSection->addTOC(
    ['size' => 12],
    ['tabLeader' => \PhpOffice\PhpWord\Style\TOC::TAB_LEADER_DOT]
);
$mainSection->addPageBreak();

// Add titles
$title1 = $mainSection->addTitle('Chapter 1', 1);
$mainSection->addText('Content...');

$title2 = $mainSection->addTitle('Chapter 2', 1);
$mainSection->addText('More content...');

// Wrap titles (bookmarks preserved, TOC works correctly)
$cc->addContentControl($title1, ['alias' => 'Chapter 1', 'tag' => 'ch1']);
$cc->addContentControl($title2, ['alias' => 'Chapter 2', 'tag' => 'ch2']);

$cc->save('document_with_toc.docx');
// Open in Word and right-click TOC > Update Field to verify links work!
```

### Known Limitations

1. **TOC Elements**: Cannot be wrapped due to complex field structure spanning multiple paragraphs
   - **Impact**: `$cc->addContentControl($tocElement, [...])` not supported
   - **Workaround**: Wrap individual Title elements instead - TOC will still generate correctly
   
2. **Watermark Images**: Not explicitly validated (future enhancement)
   - **Impact**: Watermark images may not render correctly when wrapped
   - **Recommendation**: Use inline or floating images instead
   
3. **Image Positioning**: Some floating image positioning styles may shift post-wrapping
   - **Impact**: Manual adjustment may be needed in Word after document generation
   - **Affected styles**: Absolute positioning with custom anchors

4. **Image Hash Collisions**: Different images with identical dimensions may be treated as the same image
   - **Impact**: When multiple images share the same width and height, hash-based element matching may select the wrong image
   - **Technical Detail**: Image hashes are based only on width/height (not image content or source path) because the source path cannot be derived from the DOM without resolving relationships
   - **Recommendation**: Use unique dimensions when possible, or rely on sequential processing order (images are processed in document order)

## Testing

```bash
# Run all tests (323 tests, 857 assertions)
composer test

# Unit tests only
composer test:unit

# Integration tests
composer test:feature

# Code coverage report (85%+)
composer test:coverage
```

## Standards Compliance

- **OOXML Specification:** ISO/IEC 29500-1:2016 §17.5.2 (Structured Document Tags)
- **Code Quality:** PHPStan Level 9 with strict rules
- **Type Safety:** `declare(strict_types=1)` in all files
- **Testing:** Pest PHP with custom XML expectations

## Documentation

- [API Documentation](docs/README.md)
- [Changelog](CHANGELOG.md)
- [Contributing Guide](CONTRIBUTING.md)
- [ISO/IEC 29500-1:2016 Specification](https://www.iso.org/standard/71691.html)

---

## TableBuilder API (v0.4.0)

**NEW in v0.4.0:** Create and inject PHPWord tables with automatic Content Control wrapping for template-based workflows.

### Quick Start

```php
use MkGrow\ContentControl\Bridge\TableBuilder;

$builder = new TableBuilder();

// Create a table
$table = $builder->createTable([
    'rows' => [
        ['cells' => [
            ['text' => 'Product', 'width' => 3000],
            ['text' => 'Quantity', 'width' => 2000],
            ['text' => 'Price', 'width' => 2000],
        ]],
        ['cells' => [
            ['text' => 'Widget A', 'width' => 3000],
            ['text' => '5', 'width' => 2000],
            ['text' => '$100.00', 'width' => 2000],
        ]],
    ],
]);

// Inject into template
$builder->injectTable('template.docx', 'invoice-items', $table);
```

### Creating Tables

#### `createTable(array $config): Table`

Creates a PHPWord table with automatic SDT wrapping support.

**Configuration Structure:**

```php
$config = [
    'rows' => [                    // Required: array of row configurations
        [
            'height' => 500,       // Optional: row height in twips
            'cells' => [           // Required: array of cell configurations
                [
                    'text' => 'Cell Content',    // Required: cell text content
                    'width' => 2000,             // Optional: cell width in twips
                    'style' => [                 // Optional: cell-level styles
                        'alignment' => 'center',
                        'valign' => 'center',
                        'bgColor' => 'EEEEEE',
                    ],
                ],
            ],
        ],
    ],
    'style' => [                   // Optional: table-level styles
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMargin' => 100,
    ],
];
```

**Multi-Level Styling:**

```php
$table = $builder->createTable([
    'style' => [                    // Table-level: applies to entire table
        'borderSize' => 12,
        'borderColor' => '1F4788',
    ],
    'rows' => [
        [
            'height' => 800,        // Row-level: applies to this row
            'cells' => [
                [
                    'text' => 'Header',
                    'style' => [    // Cell-level: most specific, overrides table/row
                        'bgColor' => 'FFCC00',
                        'bold' => true,
                    ],
                ],
            ],
        ],
    ],
]);
```

### Template Injection

#### `injectTable(string $templatePath, string $targetSdtTag, Table $table): void`

Injects a PHPWord table into an existing DOCX template, replacing the content of a Content Control.

**Workflow:**

1. **Create Template** in Word with a Content Control (SDT)
2. **Tag the SDT** (Developer Tab → Properties → Tag: "invoice-items")
3. **Inject Table** using `injectTable()`

**Example:**

```php
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Bridge\TableBuilder;

// 1. Create template with placeholder
$template = new ContentControl();
$section = $template->addSection();
$placeholder = $section->addText('Items will be inserted here');
$template->addContentControl($placeholder, ['tag' => 'invoice-items']);
$template->save('invoice-template.docx');

// 2. Create table
$builder = new TableBuilder();
$table = $builder->createTable([
    'rows' => [
        ['cells' => [['text' => 'Item 1'], ['text' => '$10']]],
        ['cells' => [['text' => 'Item 2'], ['text' => '$20']]],
    ],
]);

// 3. Inject table into template
$builder->injectTable('invoice-template.docx', 'invoice-items', $table);

// Result: Template now has table where placeholder was
```

### Advanced: Multiple Tables

```php
$builder = new TableBuilder();

// Create multiple tables
$invoiceItems = $builder->createTable([/* config */]);
$taxBreakdown = $builder->createTable([/* config */]);

// Inject into different SDTs in same template
$builder->injectTable('template.docx', 'invoice-items', $invoiceItems);
$builder->injectTable('template.docx', 'tax-breakdown', $taxBreakdown);
```

### Known Limitations (v0.4.0)

1. **Cell SDTs Not Supported:** Cannot apply Content Controls to individual table cells
   - **Workaround:** Use table-level SDTs (wrap entire table)
   - **Roadmap:** Planned for v0.5.0
   
2. **Hash-Based Matching:** Tables identified by dimensions (rows x cells)
   - **Impact:** Tables with same dimensions may collide
   - **Mitigation:** Clear error messages if table not found
   - **Roadmap:** Content-based hashing in v0.5.0

3. **Custom Elements:** `'element' => $customObject` not supported in cells
   - **Impact:** Only text content allowed in `createTable()`
   - **Roadmap:** Planned for v0.5.0

**Full Documentation:** See [docs/TableBuilder.md](docs/TableBuilder.md) for detailed API reference and examples.

---

## Content Control Flags

### inlineLevel Flag

When wrapping elements inside table cells with Content Controls, you must specify the `inlineLevel => true` flag.

**Required for:**
- Text/TextRun/Image elements inside table cells
- Any element within the `<w:tc>` (table cell) XML structure

**Not required for:**
- Text at document body level
- Table elements (always block-level)
- Title elements (always block-level)

**Correct Usage:**
```php
$cell = $row->addCell(3000);
$text = $cell->addText('Protected');
$cc->addContentControl($text, ['tag' => 'cell-text', 'inlineLevel' => true]);
```

**Incorrect Usage (will fail):**
```php
$cell = $row->addCell(3000);
$text = $cell->addText('Protected');
$cc->addContentControl($text, ['tag' => 'cell-text']); // Missing inlineLevel flag
```

**Technical Explanation:**
The ElementLocator uses XPath to find elements in the DOM. For elements inside
table cells, the search strategy checks `//w:tc//w:p` (cell paragraphs) before
`//w:body//w:p` (body paragraphs). The `inlineLevel` flag tells the locator to
prioritize cell-level searches.

---

## Architecture

ContentControl uses the **Proxy Pattern** to encapsulate PHPWord:

```
┌─────────────────────────┐
│   ContentControl        │  ← Proxy (unified API)
│  ┌─────────────────┐    │
│  │ PhpWord         │    │  ← Encapsulated object
│  └─────────────────┘    │
│  ┌─────────────────┐    │
│  │ SDTRegistry     │    │  ← ID management
│  └─────────────────┘    │
└─────────────────────────┘
         │
         ├─→ save()
         │    └─→ SDTInjector (XML manipulation)
         │
         └─→ addContentControl()
              └─→ SDTConfig (immutable value object)
```

## Error Handling

All exceptions extend `ContentControlException` for unified error handling:

```php
use MkGrow\ContentControl\Exception\ContentControlException;

try {
    $cc->save('output.docx');
} catch (ContentControlException $e) {
    // Handles: ZipArchiveException, DocumentNotFoundException, TemporaryFileException
    echo "Error: " . $e->getMessage();
}
```

## Version History

- **v0.3.0** (2026-01-30) - ContentProcessor Complete Implementation
  - All advanced methods implemented: `setValue()`, `appendContent()`, `removeContent()`, `removeAllControlContents()`
  - Full template processing capabilities
  - 500 tests, 1174 assertions, 80.2% coverage
  - Windows compatibility improvements (Unix permission test properly skipped)
  - Production ready with PHPStan Level 9 compliance
  
- **v0.2.0** (2026-01-29) - Header and Footer Support
  - Content Controls in headers and footers
  - Support for first page and even page headers/footers
  - Multiple sections with independent headers/footers
  - 293 tests, 82.3% coverage
  
- **v0.1.0** (2026-01-28) - Title and Image Support
  - Title elements with hierarchy preservation
  - Image elements with VML support
  
- **v0.0.0** (2026-01-28) - First public release
  - Proxy Pattern architecture
  - PHPStan Level 9 compliance
  - 227 tests with 80%+ coverage

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Author

**Mateus Bandeira** - [GitHub](https://github.com/mateusbandeira182)

## Contributing

Contributions welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) before submitting PRs.

---

**Note:** This library is in active development. APIs may change between minor versions until v1.0.0.

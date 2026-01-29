# ContentControl

> PHP library for adding Word Content Controls (Structured Document Tags) to PHPOffice/PHPWord documents

[![Latest Stable Version](https://poser.pugx.org/mkgrow/content-control/v/stable)](https://packagist.org/packages/mkgrow/content-control)
[![CI Status](https://img.shields.io/github/actions/workflow/status/mateusbandeira182/ContentControl/ci.yml?branch=main&label=CI)](https://github.com/mateusbandeira182/ContentControl/actions)
[![Code Coverage](https://img.shields.io/badge/coverage-80%25-brightgreen)](coverage/html/index.html)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue)](composer.json)
[![PHPStan Level 9](https://img.shields.io/badge/PHPStan-Level%209-brightgreen)](phpstan.neon)

---

## Features

- 🎯 **Proxy Pattern API** - Unified interface encapsulating PhpWord with automatic SDT management
- 🔒 **Content Protection** - Lock elements from editing or deletion in Word documents
- 🔢 **Unique ID Generation** - Automatic 8-digit collision-resistant identifiers with automatic collision handling
- 📝 **Type-Safe Configuration** - Immutable value objects for Content Control properties
- ✅ **Production Ready** - 227 tests, PHPStan Level 9 strict mode, 80%+ code coverage
- 📦 **Zero Dependencies** - Only requires PHPOffice/PHPWord (already in your project)

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
    'lockType' => 'sdtLocked'    // Optional: Lock level (default: LOCK_NONE)
]);
```

## Supported Elements

ContentControl can wrap the following PHPWord elements with Structured Document Tags (SDTs):

| Element | Class | OOXML Structure | SDT Type | Version |
|---------|-------|-----------------|----------|---------|
| **Text** | `\PhpOffice\PhpWord\Element\Text` | `<w:p>` | `TYPE_RICH_TEXT` | ✅ v0.0.0 |
| **TextRun** | `\PhpOffice\PhpWord\Element\TextRun` | `<w:p>` (multi-run) | `TYPE_RICH_TEXT` | ✅ v0.0.0 |
| **Table** | `\PhpOffice\PhpWord\Element\Table` | `<w:tbl>` | `TYPE_RICH_TEXT` | ✅ v0.0.0 |
| **Cell** | `\PhpOffice\PhpWord\Element\Cell` | `<w:tc>` | `TYPE_RICH_TEXT` | ✅ v0.0.0 |
| **Title** | `\PhpOffice\PhpWord\Element\Title` | `<w:p>` (with `w:pStyle`) | `TYPE_RICH_TEXT` | ✅ v0.1.0 |
| **Image** | `\PhpOffice\PhpWord\Element\Image` | `<w:p><w:pict>` | `TYPE_PICTURE` | ✅ v0.1.0 |
| TOC | `\PhpOffice\PhpWord\Element\TOC` | `<w:fldChar>` (multi-paragraph) | - | ❌ Not supported |
| Section | `\PhpOffice\PhpWord\Element\Section` | `<w:sectPr>` | - | ❌ Not wrappable |

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
# Run all tests (247 tests, 586 assertions)
composer test

# Unit tests only
composer test:unit

# Integration tests
composer test:feature

# Code coverage report
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

- **v0.0.0** (2026-01-28) - First public release (v0.0.0 baseline for public versioning)
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

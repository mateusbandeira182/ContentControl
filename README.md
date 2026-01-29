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
- 🔢 **Unique ID Generation** - Automatic 8-digit, collision-resistant identifiers with automatic collision handling
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

ContentControl v3.0 supports wrapping the following PHPWord elements:

- ✅ **Text** - Simple text elements
- ✅ **TextRun** - Formatted text with runs
- ✅ **Table** - Complete tables
- ✅ **Cell** - Individual table cells
- ❌ **Section** - Not supported (wrap child elements instead)

## Testing

```bash
# Run all tests (227 tests)
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

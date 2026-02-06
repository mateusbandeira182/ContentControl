# ContentControl Documentation

Welcome to the comprehensive documentation for the **ContentControl** library - a PHP extension for PHPOffice/PHPWord that adds Word Content Controls (Structured Document Tags) to .docx files.

**Version:** 0.5.0  
**PHP:** >= 8.2  
**PHPWord:** ^1.4  
**Standard:** ISO/IEC 29500-1:2016 §17.5.2

## Table of Contents

- [Quick Start Guide](#quick-start-guide)
- [Architecture Overview](#architecture-overview)
- [Component Documentation](#component-documentation)
- [Workflow Guides](#workflow-guides)
- [Additional Resources](#additional-resources)

## Quick Start Guide

### Installation

```bash
composer require mkgrow/content-control
```

### Basic Usage

**Create a Document with Content Controls:**

```php
<?php
require 'vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();
$section = $cc->addSection();

$text = $section->addText('Protected content');
$cc->addContentControl($text, [
    'alias' => 'Protected Field',
    'tag' => 'field_1',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

$cc->save('output.docx');
```

**Modify Existing Template:**

```php
<?php
use MkGrow\ContentControl\ContentProcessor;

$processor = new ContentProcessor('template.docx');
$processor->replaceContent('field_1', 'Updated value');
$processor->save('output.docx');
```

**Build Tables with Content Controls:**

```php
<?php
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Bridge\TableBuilder;

$cc = new ContentControl();
$builder = new TableBuilder($cc);

$builder->setStyles(['borderSize' => 6])
    ->addRow()
        ->addCell(3000)->addText('Name')->end()
        ->addCell(3000)->addText('Value')->end()
        ->end();

$cc->save('table.docx');
```

For more examples, see the [samples/](../samples/) directory.

## Architecture Overview

### Design Philosophy

ContentControl follows a **composition-based architecture** with three core principles:

1. **Composition over Inheritance** - All 8 core classes are `final`, promoting extension via composition
2. **Immutability** - Value objects use readonly properties (PHP 8.2+) for predictability
3. **Single Responsibility** - Each class has one clear purpose

### Core Components

```
┌─────────────────────────────────────────────────────────┐
│                   ContentControl                        │
│  (Facade/Proxy for PHPWord with SDT support)            │
│                                                         │
│  Purpose: Create new documents with Content Controls    │
│  Workflow: Create → Register → Save                     │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│                  ContentProcessor                       │
│  (Template modification via XPath)                      │
│                                                         │
│  Purpose: Modify existing DOCX files                    │
│  Workflow: Open → Modify → Save                         │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│                    TableBuilder                         │
│  (Bridge for tables with SDTs)                          │
│                                                         │
│  Purpose: Create tables with Content Controls           │
│  Workflow: Build → Save or Inject                       │
└─────────────────────────────────────────────────────────┘
```

### Supporting Services

- **SDTRegistry** - ID generation and element-config mapping
- **SDTInjector** - DOM manipulation service for wrapping elements
- **ElementLocator** - XPath-based element location in DOM
- **ElementIdentifier** - Content hashing and element identification
- **SDTConfig** - Immutable value object for SDT configuration
- **IDValidator** - Centralized ID validation and generation

### Version Evolution

| Version | Features |
|---------|----------|
| **v1.x/v2.x** | String-based XML manipulation (deprecated) |
| **v3.0** | DOM manipulation with XPath (current standard) |
| **v0.4.0** | Inline-level SDT support with `inlineLevel` flag |
| **v0.4.2** | Fluent API for TableBuilder, UUID v5 hashing, GROUP SDT replacement |
| **v0.5.0** | TableBuilder::setStyles() method (must be called before first addRow) |

## Component Documentation

### Core Components

#### 1. ContentControl

**Purpose:** Facade/proxy for creating Word documents with Content Controls.

**Key Features:**
- Transparent PHPWord integration
- Automatic ID generation with collision detection
- DOM-based SDT injection
- Support for all PHPWord elements (Text, Table, Image, Title, etc.)

**Documentation:** [contentcontrol.md](contentcontrol.md)

**When to Use:**
- Creating new Word documents from scratch
- Adding protected fields to generated documents
- Building document templates with locked sections

**Example:**
```php
$cc = new ContentControl();
$section = $cc->addSection();

// Add header with locked SDT
$header = $section->addHeader();
$headerText = $header->addText('Company Name');
$cc->addContentControl($headerText, [
    'alias' => 'Company Header',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED
]);

$cc->save('output.docx');
```

---

#### 2. ContentProcessor

**Purpose:** Open and modify existing DOCX files via XPath-based SDT manipulation.

**Key Features:**
- Template modification workflows
- XPath-based SDT location
- Multiple modification methods (replaceContent, setValue, appendContent)
- GROUP SDT replacement with complex structures
- Header/footer SDT support

**Documentation:** [contentprocessor.md](contentprocessor.md)

**When to Use:**
- Modifying existing DOCX templates
- Filling form fields in pre-designed documents
- Batch processing template-based documents
- Replacing placeholder SDTs with dynamic content

**Example:**
```php
$processor = new ContentProcessor('invoice_template.docx');

// Simple text replacement
$processor->replaceContent('customer_name', 'Acme Corp');

// Preserve formatting
$processor->setValue('invoice_date', date('Y-m-d'));

// Replace GROUP SDT with complex structure
$structure = new ContentControl();
// ... build complex content
$processor->replaceGroupContent('invoice_details', $structure);

$processor->save('invoice_2024_001.docx');
```

---

#### 3. TableBuilder

**Purpose:** Bridge pattern for creating PHPWord tables with Content Controls.

**Key Features:**
- Fluent API (60% less code than legacy)
- Two workflows: direct creation and template injection
- Cell-level Content Controls with `inlineLevel` support
- UUID v5 hashing for zero-collision template matching
- Table-level GROUP SDTs

**Documentation:** [tablebuilder.md](tablebuilder.md)

**When to Use:**
- Creating tables with per-cell Content Controls
- Injecting complex tables into templates
- Generating invoice line items with locked fields
- Building data grids with editable/locked cells

**Example:**
```php
// Direct creation workflow
$cc = new ContentControl();
$builder = new TableBuilder($cc);

$builder->setStyles(['borderSize' => 6])
    ->addRow()
        ->addCell(3000)->addText('Product')->end()
        ->addCell(3000)->addText('Price')->end()
        ->end()
    ->addRow()
        ->addCell(3000)->addText('Widget')->end()
        ->addCell(3000)
            ->addText('$99.99')
            ->withContentControl([
                'tag' => 'price_1',
                'inlineLevel' => true,  // REQUIRED for cells
                'lockType' => ContentControl::LOCK_SDT_LOCKED
            ])
            ->end()
        ->end();

$cc->save('table.docx');
```

---

### Supporting Services

#### SDTRegistry

**Purpose:** ID generation with collision detection and element-config mapping.

**Key Features:**
- Generates unique 8-digit IDs
- 100 random attempts + sequential fallback
- O(1) collision detection via hash set
- Element identity tracking via `spl_object_id()`

**Location:** `src/SDTRegistry.php`

---

#### SDTInjector

**Purpose:** Service layer for DOM manipulation - wraps elements with `<w:sdt>` in existing DOCX.

**Key Features:**
- DOM-based element wrapping (v3.0+)
- Depth-first processing for nested structures
- Header/footer discovery and processing
- Prevents duplicate SDTs via processed element tracking

**Location:** `src/SDTInjector.php`

---

#### ElementLocator

**Purpose:** XPath-based element location in DOM with multiple search strategies.

**Key Features:**
- Content hash-based search (primary)
- Type + registration order fallback
- Inline-level (cell) and block-level support
- Namespace-aware XPath queries

**Location:** `src/ElementLocator.php`

---

#### ElementIdentifier

**Purpose:** Generate deterministic markers and hashes for element identification.

**Key Features:**
- UUID v5 table hashing (zero collisions)
- Content-based hash generation
- Static caching for performance
- Reflection-based property access

**Location:** `src/ElementIdentifier.php`

---

## Workflow Guides

### Workflow 1: New Document Creation

**Use Case:** Generate Word documents from scratch with Content Controls.

**Components:** `ContentControl` + `SDTRegistry` + `SDTInjector`

**Steps:**
1. Create `ContentControl` instance
2. Add sections and elements via PHPWord API
3. Register elements for SDT wrapping with `addContentControl()`
4. Save document - triggers SDT injection

**Example:**
```php
$cc = new ContentControl();
$section = $cc->addSection();

// Add title
$title = $section->addTitle('Report Title', 1);
$cc->addContentControl($title, [
    'alias' => 'Report Title',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED
]);

// Add body text
$text = $section->addText('Report content goes here');
$cc->addContentControl($text, [
    'alias' => 'Report Body',
    'tag' => 'report_body'
]);

// Add footer
$footer = $section->addFooter();
$footerText = $footer->addText('Confidential');
$cc->addContentControl($footerText, [
    'alias' => 'Footer Confidential',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

$cc->save('report.docx');
```

---

### Workflow 2: Template Modification

**Use Case:** Fill existing DOCX templates with data.

**Components:** `ContentProcessor`

**Steps:**
1. Open existing template with `ContentProcessor`
2. Locate and modify SDTs by tag
3. Save changes (in-place or new file)

**Example:**
```php
$data = [
    'customer_name' => 'Acme Corporation',
    'invoice_number' => 'INV-2024-001',
    'invoice_date' => '2024-02-06',
    'total_amount' => '$5,432.10'
];

$processor = new ContentProcessor('invoice_template.docx');

foreach ($data as $tag => $value) {
    $processor->replaceContent($tag, $value);
}

$processor->save("invoices/{$data['invoice_number']}.docx");
```

---

### Workflow 3: Template Injection (Tables)

**Use Case:** Build complex tables and inject into template placeholders.

**Components:** `TableBuilder` + `ContentProcessor`

**Steps:**
1. Build table in temporary `ContentControl`
2. Open template with `ContentProcessor`
3. Inject table into placeholder SDT
4. Save final document

**Example:**
```php
// 1. Build table
$cc = new ContentControl();
$builder = new TableBuilder($cc);

$builder->setStyles(['borderSize' => 6])
    ->addRow()
        ->addCell(3000)->addText('Item')->end()
        ->addCell(3000)->addText('Qty')->end()
        ->addCell(3000)->addText('Price')->end()
        ->end();

// Add data rows with cell SDTs
for ($i = 1; $i <= 5; $i++) {
    $builder->addRow()
        ->addCell(3000)->addText("Item {$i}")->end()
        ->addCell(3000)
            ->addText('0')
            ->withContentControl([
                'tag' => "qty_{$i}",
                'inlineLevel' => true,
                'lockType' => ContentControl::LOCK_SDT_LOCKED
            ])
            ->end()
        ->addCell(3000)
            ->addText('$0.00')
            ->withContentControl([
                'tag' => "price_{$i}",
                'inlineLevel' => true,
                'lockType' => ContentControl::LOCK_SDT_LOCKED
            ])
            ->end()
        ->end();
}

// 2. Inject into template
$processor = new ContentProcessor('order_template.docx');
$builder->injectInto($processor, 'line_items_placeholder');

// 3. Fill other fields
$processor->replaceContent('order_number', 'ORD-2024-001');
$processor->replaceContent('customer_name', 'Customer LLC');

$processor->save('order_2024_001.docx');
```

---

### Workflow 4: GROUP SDT Replacement

**Use Case:** Replace placeholder with complex multi-element structures containing nested SDTs.

**Components:** `ContentControl` + `ContentProcessor`

**Steps:**
1. Build complex structure in `ContentControl`
2. Open template with `ContentProcessor`
3. Replace GROUP SDT with structure
4. Save final document

**Example:**
```php
// 1. Build complex structure
$structure = new ContentControl();
$section = $structure->addSection();

// Add title
$title = $section->addTitle('Invoice Details', 2);
$structure->addContentControl($title, [
    'tag' => 'section_title',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED
]);

// Add description paragraph
$description = $section->addText('Order summary and line items:');

// Add table with nested SDTs
$table = $section->addTable(['borderSize' => 6]);
$table->addRow();
$table->addCell(6000)->addText('Description');
$table->addCell(3000)->addText('Amount');

$table->addRow();
$table->addCell(6000)->addText('Consulting Services');
$cell = $table->addCell(3000);
$priceText = $cell->addText('$5,000.00');
$structure->addContentControl($priceText, [
    'tag' => 'consulting_price',
    'inlineLevel' => true,
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

// 2. Replace GROUP SDT in template
$processor = new ContentProcessor('contract_template.docx');
$processor->replaceGroupContent('invoice_section_placeholder', $structure);

$processor->save('contract_filled.docx');
```

**Template Requirement:** SDT with tag `'invoice_section_placeholder'` must have `<w:group/>` type.

---

## Additional Resources

### Code Quality and Testing

**PHPStan Level 9:**
```bash
composer analyse
```

**Test Suite (Pest):**
```bash
composer test              # All tests (464+ tests)
composer test:unit         # Unit tests only
composer test:feature      # Feature tests only
composer test:coverage     # Enforce 80% coverage
composer test:coverage-html  # HTML report
```

**CI Pipeline:**
```bash
composer ci  # analyse + coverage
composer check  # analyse + test
```

### Sample Files

The [samples/](../samples/) directory contains 8 progressive examples:

1. **01-quick-start.php** - Basic ContentControl usage
2. **02-basic-table.php** - Demonstrates setStyles() method
3. **03-template-modification.php** - ContentProcessor examples
4. **04-table-with-controls.php** - Cell-level SDTs with inlineLevel flag
5. **05-template-injection.php** - UUID v5 table injection
6. **06-multi-element-document.php** - Comprehensive feature demo
7. **07-header-footer-controls.php** - Header/footer SDTs
8. **08-group-sdt-replacement.php** - GROUP SDT replacement

Run any sample: `php samples/01-quick-start.php`

### Migration Guides

- **[MIGRATION-v042.md](MIGRATION-v042.md)** - Legacy array API to fluent API conversion

### Testing Guides

- **[MANUAL_TESTING_GUIDE.md](MANUAL_TESTING_GUIDE.md)** - Word compatibility checklist

### External Documentation

**ISO/IEC Standard:**
- [ISO/IEC 29500-1:2016](https://www.iso.org/standard/71691.html) - Office Open XML File Formats
- §17.5.2 - Structured Document Tags (Content Controls)

**PHPWord Documentation:**
- [PHPWord Official Docs](https://phpoffice.github.io/PHPWord/) - Element creation reference
- [PHPWord GitHub](https://github.com/PHPOffice/PHPWord) - Source code and issues

**UUID v5 Specification:**
- [RFC 4122](https://www.rfc-editor.org/rfc/rfc4122.html) - UUID standard (NAMESPACE_DNS)

---

## Common Pitfalls and Solutions

### 1. Missing inlineLevel for Cell SDTs

**Problem:**
```php
$cell->addText('Value');
$cc->addContentControl($cellText, ['tag' => 'cell_field']);  // Missing inlineLevel!
```

**Solution:**
```php
$cc->addContentControl($cellText, [
    'tag' => 'cell_field',
    'inlineLevel' => true  // REQUIRED
]);
```

---

### 2. Calling injectInto on ContentControl

**Problem:**
```php
$builder->injectInto($contentControl, 'tag');  // Type error!
```

**Solution:**
```php
// Template injection workflow
$processor = new ContentProcessor('template.docx');
$builder->injectInto($processor, 'tag');  // Correct type
```

---

### 3. setStyles After addRow

**Problem:**
```php
$builder->addRow();  // Table created
$builder->setStyles([...]);  // Exception!
```

**Solution:**
```php
$builder->setStyles([...])  // BEFORE first addRow
    ->addRow();
```

---

### 4. ContentProcessor Single-Use

**Problem:**
```php
$processor->save('output.docx');
$processor->replaceContent('tag', 'value');  // ZIP closed!
```

**Solution:**
```php
$processor->save('output.docx');

// Create new instance for additional changes
$processor2 = new ContentProcessor('output.docx');
$processor2->replaceContent('tag', 'value');
$processor2->save('final.docx');
```

---

## Quick Reference

### Constants

**Content Control Types:**
```php
ContentControl::TYPE_RICH_TEXT    // Full formatting (default)
ContentControl::TYPE_PLAIN_TEXT   // Simple text
ContentControl::TYPE_PICTURE      // Image control
ContentControl::TYPE_GROUP        // Container for elements
```

**Lock Types:**
```php
ContentControl::LOCK_NONE              // No protection (default)
ContentControl::LOCK_SDT_LOCKED        // SDT cannot be deleted
ContentControl::LOCK_CONTENT_LOCKED    // Content read-only
ContentControl::LOCK_UNLOCKED          // Explicitly unlocked
```

### CLI Commands

```bash
# Testing
composer test
composer test:coverage
composer test:coverage-html

# Analysis
composer analyse
composer ci

# Full check
composer check
```

### File Locations

```
src/
├── ContentControl.php        # Main facade
├── ContentProcessor.php      # Template modification
├── SDTRegistry.php          # ID generation + mapping
├── SDTInjector.php          # DOM manipulation
├── ElementLocator.php       # XPath-based search
├── ElementIdentifier.php    # Content hashing
├── SDTConfig.php            # Immutable config object
├── IDValidator.php          # ID validation
└── Bridge/
    ├── TableBuilder.php     # Table builder
    ├── RowBuilder.php       # Row builder
    └── CellBuilder.php      # Cell builder
```

---

## Support and Community

**GitHub Repository:** [mateusbandeira182/ContentControl](https://github.com/mateusbandeira182/ContentControl)

**Issues:** [GitHub Issues](https://github.com/mateusbandeira182/ContentControl/issues)

**Security:** Email security vulnerabilities to mateusbandeiraweb@gmail.com (DO NOT open public issues)

**Contributing:** See [CONTRIBUTING.md](../CONTRIBUTING.md) for development guidelines

**Changelog:** See [CHANGELOG.md](../CHANGELOG.md) for version history

---

**Last Updated:** February 6, 2026  
**Version:** 0.5.0  
**License:** MIT

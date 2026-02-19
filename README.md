# ContentControl

[![Build Status](https://github.com/mateusbandeira182/ContentControl/workflows/CI/badge.svg)](https://github.com/mateusbandeira182/ContentControl/actions)
[![Code Coverage](https://img.shields.io/badge/coverage-82.2%25-green.svg)](coverage/html/index.html)
[![Tests](https://img.shields.io/badge/tests-535%20passed-brightgreen.svg)](https://github.com/mateusbandeira182/ContentControl/actions)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg)](phpstan.neon)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net)
[![Version](https://img.shields.io/badge/version-0.6.0-blue.svg)](CHANGELOG.md)

**ContentControl** is a PHP library that extends [PHPOffice/PHPWord](https://github.com/PHPOffice/PHPWord) to add Word Content Controls (Structured Document Tags/SDTs) to .docx files. It enables document-level content protection and metadata tagging conforming to **ISO/IEC 29500-1:2016 §17.5.2**.

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Features](#features)
- [Documentation](#documentation)
  - [Architecture Overview](#architecture-overview)
  - [Core Components](#core-components)
  - [Configuration](#configuration)
  - [Error Handling](#error-handling)
  - [Logging and Debugging](#logging-and-debugging)
- [Testing](#testing)
- [Changelog and Contributing](#changelog-and-contributing)
- [Security](#security)
- [Credits](#credits)
- [License](#license)

## Installation

Install via Composer:

```bash
composer require mkgrow/content-control
```

**Requirements:**
- PHP >= 8.2
- ext-dom
- ext-mbstring
- ext-zip
- phpoffice/phpword ^1.4
- ramsey/uuid ^4.7

## Quick Start

### Creating a New Document with Content Controls

```php
<?php
require 'vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;

// Create a new document
$cc = new ContentControl();
$section = $cc->addSection();

// Add text element
$text = $section->addText('This field is protected');

// Wrap with Content Control
$cc->addContentControl($text, [
    'alias' => 'Protected Field',
    'tag' => 'field_1',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

// Save the document
$cc->save('protected_document.docx');
```

### Modifying Existing Documents

```php
<?php
use MkGrow\ContentControl\ContentProcessor;

// Open existing template
$processor = new ContentProcessor('template.docx');

// Replace SDT content by tag
$processor->replaceContent('field_1', 'Updated value');

// Update text while preserving formatting
$processor->setValue('field_2', 'New text');

// Save changes
$processor->save('output.docx');
```

### Building Tables with Content Controls (v0.6.0+)

```php
<?php
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Bridge\TableBuilder;

// Create document and table via PHPWord API
$cc = new ContentControl();
$section = $cc->addSection();
$table = $section->addTable(['borderSize' => 6, 'borderColor' => '1F4788']);

// Build table using native PHPWord API
$row = $table->addRow();
$row->addCell(3000)->addText('Name', ['bold' => true]);
$row->addCell(3000)->addText('Value', ['bold' => true]);

$row2 = $table->addRow();
$row2->addCell(3000)->addText('Item 1');
$priceCell = $row2->addCell(3000);
$priceText = $priceCell->addText('$100');

// Wrap elements with Content Controls via TableBuilder
$builder = new TableBuilder($table);
$builder->addContentControl($priceText, [
    'tag' => 'price_1',
    'alias' => 'Price',
    'inlineLevel' => true,   // Required for cell-level elements
    'runLevel' => true,      // Wraps <w:r> instead of <w:p>
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

$cc->save('table_document.docx');
```

For more examples, see the [samples/](samples/) directory.

## Features

**Core Capabilities:**
- **Content Control Support:** Add Word Content Controls (SDTs) to any PHPWord element
- **ISO/IEC 29500-1:2016 Compliance:** Full adherence to OOXML standard §17.5.2
- **Document Protection:** Lock SDTs, content, or both to prevent unauthorized modifications
- **Template Processing:** Modify existing DOCX files with XPath-based SDT location
- **Run-Level SDT Wrapping:** Wrap individual `<w:r>` text runs with Content Controls (v0.6.0+)
- **TableBuilder v2:** Direct PHPWord Table API with `addContentControl()` delegation (v0.6.0+)
- **GROUP SDT Replacement:** Replace placeholder SDTs with complex multi-element structures
- **Header/Footer Support:** Add Content Controls to headers and footers (v0.2.0+)
- **UUID v5 Hashing:** Zero-collision element identification for template injection (v0.4.2+)

**PHP Compatibility:**
- PHP 8.2+ with full typed properties support
- PSR-4 autoloading (`MkGrow\ContentControl` namespace)
- All classes are `final` (composition over inheritance)
- Immutable value objects using readonly properties

**Quality Standards:**
- PHPStan Level 9 static analysis with strict rules
- 82%+ code coverage with 535+ tests (Pest framework)
- Zero-collision UUID v5 hashing for element identification
- Single Responsibility Principle across all components

**Supported Content Control Types:**
- `TYPE_RICH_TEXT` - Full formatting support (default)
- `TYPE_PLAIN_TEXT` - Simple text without formatting
- `TYPE_PICTURE` - Image controls
- `TYPE_GROUP` - Container for multiple elements

**Lock Types:**
- `LOCK_NONE` - No protection (default)
- `LOCK_SDT_LOCKED` - SDT cannot be deleted, content editable
- `LOCK_CONTENT_LOCKED` - SDT deletable, content read-only
- `LOCK_UNLOCKED` - Explicitly no lock

**Supported PHPWord Elements:**
- Text - Simple text nodes
- TextRun - Formatted text with multiple runs
- Table - Complete table structures
- Cell - Individual table cells (requires `inlineLevel: true`)
- Title - Heading elements (depth 0-9)
- Image - VML inline/floating images

## Documentation

### Architecture Overview

ContentControl follows a **composition-based architecture** with no inheritance hierarchies. All 8 core classes are `final`, promoting extension via composition rather than inheritance.

**Design Philosophy:**
- **Single Responsibility:** Each class has one clear purpose
- **Immutability:** Value objects use readonly properties for predictability
- **No Duplication:** v3.0+ uses DOM manipulation (not string replacement) to prevent duplicate SDTs
- **Depth-First Processing:** Elements sorted by depth (Cell before Table) for correct nested structures

**Core Patterns:**
- **Proxy/Facade Pattern:** `ContentControl` acts as proxy for PHPWord with SDT functionality
- **Bridge Pattern:** `TableBuilder` bridges PHPWord table creation with SDT template injection
- **Registry Pattern:** `SDTRegistry` maintains element-to-config mapping with collision-free ID generation
- **Service Layer:** `SDTInjector` operates as stateless service for DOM manipulation

**Version Evolution:**
- **v1.x/v2.x:** String-based XML manipulation (deprecated)
- **v3.0:** DOM manipulation with XPath - current standard
- **v0.4.0:** Inline-level SDT support with `inlineLevel` flag
- **v0.4.2:** Fluent API, UUID v5 hashing, GROUP SDT replacement
- **v0.5.0:** TableBuilder::setStyles() method (must be called before first addRow)
- **v0.5.2:** Fix content hash strategy for inline-level Text/TextRun hash collisions
- **v0.6.0:** Run-level SDT wrapping (CT_SdtContentRun), TableBuilder v2 with direct PHPWord Table API

```
┌─────────────────────────────────────────────────────────────┐
│                     ContentControl                          │
│  (Facade/Proxy for PHPWord with SDT support)                │
│                                                             │
│  - Creates documents via PHPWord delegation                 │
│  - Registers SDTs in SDTRegistry                            │
│  - Saves with SDTInjector DOM manipulation                  │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                   SDTRegistry                               │
│  (ID generation and element-config mapping)                 │
│                                                             │
│  - Generates unique 8-digit IDs with collision detection    │
│  - Maps elements to SDT configurations                      │
│  - Validates duplicate elements/IDs                         │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                    SDTInjector                              │
│  (DOM manipulation service layer)                           │
│                                                             │
│  - Opens DOCX as ZIP, loads XML as DOMDocument              │
│  - Locates elements via ElementLocator (XPath)              │
│  - Wraps elements with <w:sdt> in DOM tree                  │
│  - Processes document.xml, headers, footers                 │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                 ElementLocator                              │
│  (XPath-based element location)                             │
│                                                             │
│  - Finds elements by content hash (UUID v5)                 │
│  - Fallback to type + registration order                    │
│  - Supports inline-level (cell) and block-level elements    │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                 ContentProcessor                            │
│  (Template modification via XPath)                          │
│                                                             │
│  - Opens existing DOCX files                                │
│  - Modifies SDTs: replaceContent, setValue, appendContent   │
│  - GROUP SDT replacement: replaceGroupContent               │
│  - Saves with in-place updates                              │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                   TableBuilder                              │
│  (Bridge for tables with SDTs)                              │
│                                                             │
│  - v0.6.0: Direct PHPWord Table API + addContentControl()   │
│  - Constructor accepts Table|ContentControl|null            │
│  - Run-level SDT wrapping (runLevel: true)                  │
│  - Template injection: injectInto(ContentProcessor, tag)    │
│  - Legacy fluent API deprecated (removal in v0.8.0)         │
└─────────────────────────────────────────────────────────────┘
```

### Core Components

#### 1. ContentControl

**Purpose:** Facade/proxy for PHPWord that adds SDT registration and injection capabilities.

**Location:** `src/ContentControl.php`

**Key Methods:**
- `__construct(?PhpWord $phpWord = null)` - Create new document or wrap existing PHPWord instance
- `addSection()` - Delegate to PHPWord for section creation
- `addContentControl(object $element, array $options = []): object` - Register element for SDT wrapping
- `save(string $filename, string $format = 'Word2007'): void` - Save document with injected SDTs
- `getPhpWord(): PhpWord` - Access underlying PHPWord instance
- `getSDTRegistry(): SDTRegistry` - Access registry for advanced use

**Workflow:**
1. Create document via PHPWord delegation
2. Register elements with `addContentControl()`
3. Save - triggers `SDTInjector` to wrap elements in DOM
4. Modified XML written to DOCX ZIP

**Example:**
```php
$cc = new ContentControl();
$section = $cc->addSection();
$text = $section->addText('Protected content');

$cc->addContentControl($text, [
    'id' => '12345678',  // Optional, auto-generated if omitted
    'alias' => 'Display Name',
    'tag' => 'metadata-id',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
    'inlineLevel' => false  // true for cell-level elements
]);

$cc->save('output.docx');
```

For detailed documentation, see [docs/contentcontrol.md](docs/contentcontrol.md).

#### 2. ContentProcessor

**Purpose:** Open and modify existing DOCX files via XPath-based SDT manipulation.

**Location:** `src/ContentProcessor.php`

**Key Methods:**
- `__construct(string $documentPath)` - Open existing DOCX for modification
- `findSdt(string $tag): ?array` - Locate SDT by tag (returns DOM info)
- `replaceContent(string $tag, string|AbstractElement $value): bool` - Replace entire SDT content
- `setValue(string $tag, string $value): bool` - Replace text preserving formatting
- `appendContent(string $tag, AbstractElement $element): bool` - Add content to existing SDT
- `replaceGroupContent(string $tag, ContentControl $structure): bool` - Replace GROUP SDT with complex structure
- `removeContent(string $tag): bool` - Clear SDT content
- `removeAllControlContents(bool $block = false): int` - Clear all SDTs, optionally lock document
- `save(string $outputPath = ''): void` - Save changes (in-place if no path provided)

**Workflow:**
```php
// 1. Open template
$processor = new ContentProcessor('template.docx');

// 2. Modify SDTs
$processor->replaceContent('field_1', 'New value');
$processor->setValue('field_2', 'Text with preserved formatting');

// 3. Replace GROUP SDT with complex structure
$complexStructure = new ContentControl();
$section = $complexStructure->addSection();
$table = $section->addTable();
// ... build complex content
$processor->replaceGroupContent('group_placeholder', $complexStructure);

// 4. Save
$processor->save('output.docx');
```

**Important:** ContentProcessor is **single-use**. After `save()`, the ZIP is closed and no further modifications are possible. Create a new instance for additional changes.

For detailed documentation, see [docs/contentprocessor.md](docs/contentprocessor.md).

#### 3. TableBuilder

**Purpose:** Bridge pattern for creating PHPWord tables with Content Controls and injecting into templates.

**Location:** `src/Bridge/TableBuilder.php`

**Two Distinct Workflows:**

**Workflow 1: Direct Creation with v2 API (v0.6.0+)** (recommended)
```php
// Create table via PHPWord directly
$cc = new ContentControl();
$section = $cc->addSection();
$table = $section->addTable(['borderSize' => 6, 'borderColor' => '1F4788']);

$row = $table->addRow();
$cell = $row->addCell(3000);
$text = $cell->addText('Protected Value');

// Pass Table to TableBuilder, register SDTs
$builder = new TableBuilder($table);
$builder->addContentControl($text, [
    'tag' => 'value-1',
    'runLevel' => true,       // Wrap <w:r> instead of <w:p>
    'inlineLevel' => true,    // Element is inside a cell
]);

$cc->save('output.docx');
```

**Workflow 2: Template Injection** (build table, inject into template placeholder)
```php
// Build table in new ContentControl
$cc = new ContentControl();
$builder = new TableBuilder($cc);
// ... build table via fluent or direct API

// Inject into template
$processor = new ContentProcessor('template.docx');
$builder->injectInto($processor, 'placeholder-tag');
$processor->save('output.docx');
```

**Critical Rules:**
- **NEVER call `injectInto()` on ContentControl** - only accepts ContentProcessor
- **ALWAYS call `setStyles()` BEFORE first `addRow()`** - throws exception if table exists
- **Cell-level SDTs REQUIRE `inlineLevel: true`** in configuration
- **Run-level SDTs** use `runLevel: true` to wrap `<w:r>` instead of `<w:p>` (v0.6.0+)

**v2 API (v0.6.0+):**
- `__construct(Table|ContentControl|null $source)` - Accept Table, ContentControl, or null
- `addContentControl(AbstractElement $element, array $config = []): self` - Register SDT wrapping for any element
- `injectInto(ContentProcessor $processor, string $targetTag): void` - Inject into template
- `setStyles(array $style): self` - Configure table styles (must be before addRow)
- `getContentControl(): ContentControl` - Access underlying ContentControl instance

**Legacy Fluent API (deprecated since v0.6.0, removal in v0.8.0):**
- `addRow(?int $height = null, array $style = []): RowBuilder`
- `RowBuilder::addCell(int $width, array $style = []): CellBuilder`
- `CellBuilder::addText(string $text, array $style = []): self`
- `CellBuilder::withContentControl(array $config): self`
- `CellBuilder::end(): RowBuilder` / `RowBuilder::end(): TableBuilder`

See [Migration Guide](docs/migration/v0.5.2-to-v0.6.0.md) for fluent-to-direct API migration examples.

For detailed documentation, see [docs/tablebuilder.md](docs/tablebuilder.md).

### Configuration

#### SDT Configuration Options

All Content Controls support the following configuration options:

```php
$config = [
    'id' => '12345678',           // 8-digit ID (auto-generated if omitted)
    'alias' => 'Display Name',     // Name shown in Word UI
    'tag' => 'metadata_id',        // Programmatic identifier
    'type' => ContentControl::TYPE_RICH_TEXT,  // SDT type
    'lockType' => ContentControl::LOCK_SDT_LOCKED,  // Protection level
    'inlineLevel' => false,        // true for cell-level elements (REQUIRED)
    'runLevel' => false            // true to wrap <w:r> instead of <w:p> (v0.6.0+)
];
```

**ID Constraints:**
- Exactly 8 characters
- Numeric only (0-9)
- Range: 10000000 to 99999999
- Auto-generated with collision detection if omitted

**Type Constants:**
- `ContentControl::TYPE_RICH_TEXT` - Full formatting (default)
- `ContentControl::TYPE_PLAIN_TEXT` - Simple text
- `ContentControl::TYPE_PICTURE` - Image control
- `ContentControl::TYPE_GROUP` - Container for multiple elements

**Lock Type Constants:**
- `ContentControl::LOCK_NONE` - No protection (default)
- `ContentControl::LOCK_SDT_LOCKED` - SDT cannot be deleted, content editable
- `ContentControl::LOCK_CONTENT_LOCKED` - SDT deletable, content locked
- `ContentControl::LOCK_UNLOCKED` - Explicitly no lock

**Inline Level Flag:**
- **MUST be `true`** for elements inside table cells
- Default: `false` (block-level elements)
- Affects XPath search priority (cells before rootElement)

**Run Level Flag (v0.6.0+):**
- When `true`, wraps individual `<w:r>` (text runs) instead of `<w:p>` (paragraphs)
- Default: `false` (paragraph-level wrapping)
- Conforms to CT_SdtContentRun (ECMA-376 Part 4 S17.5.2.30)
- Can be combined with `inlineLevel: true` for cell-scoped run-level wrapping

#### Table Styles Configuration

```php
$tableStyles = [
    'borderSize' => 6,          // Border width in eighths of a point
    'borderColor' => '1F4788',  // Hex color without #
    'cellMargin' => 80,         // Default cell margin in twips
    'alignment' => 'center',    // left, center, right
    'width' => 100,             // Table width
    'unit' => 'pct',            // pct (percentage) or dxa (twips)
    'layout' => 'autofit'       // fixed or autofit
];

$builder->setStyles($tableStyles);
```

**Important:** `setStyles()` must be called **BEFORE** first `addRow()` call. Throws `ContentControlException` if table already exists.

### Error Handling

#### Exception Hierarchy

All library-specific exceptions extend `MkGrow\ContentControl\Exception\ContentControlException`:

```php
use MkGrow\ContentControl\Exception\ContentControlException;
use MkGrow\ContentControl\Exception\DocumentNotFoundException;
use MkGrow\ContentControl\Exception\ZipArchiveException;
use MkGrow\ContentControl\Exception\TemporaryFileException;
```

**Exception Types:**

| Exception | Thrown When | Example Scenario |
|-----------|-------------|------------------|
| `ContentControlException` | General library errors | Invalid configuration, duplicate elements |
| `DocumentNotFoundException` | `word/document.xml` missing from DOCX | Corrupted ZIP archive |
| `ZipArchiveException` | ZIP manipulation failures | File permissions, disk space |
| `TemporaryFileException` | Temp file cleanup fails after 3 retries | Windows file locks |
| `InvalidArgumentException` | Invalid parameters | Duplicate IDs, invalid SDT types |
| `RuntimeException` | DOM/XML processing errors | Malformed XML, serialization failures |

**Best Practices:**

```php
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Exception\ContentControlException;

try {
    $cc = new ContentControl();
    $section = $cc->addSection();
    $text = $section->addText('Content');
    
    $cc->addContentControl($text, [
        'id' => '12345678',
        'tag' => 'field_1'
    ]);
    
    $cc->save('output.docx');
    
} catch (ContentControlException $e) {
    // Library-specific errors
    error_log("ContentControl error: " . $e->getMessage());
    
} catch (\InvalidArgumentException $e) {
    // Invalid parameters
    error_log("Invalid argument: " . $e->getMessage());
    
} catch (\RuntimeException $e) {
    // General runtime errors
    error_log("Runtime error: " . $e->getMessage());
}
```

**Common Error Scenarios:**

1. **Duplicate Element Registration:**
   ```php
   // Throws InvalidArgumentException
   $cc->addContentControl($text, ['tag' => 'field_1']);
   $cc->addContentControl($text, ['tag' => 'field_2']);  // Same element!
   ```

2. **Duplicate ID:**
   ```php
   $cc->addContentControl($text1, ['id' => '12345678']);
   $cc->addContentControl($text2, ['id' => '12345678']);  // ID collision!
   ```

3. **Invalid ID Format:**
   ```php
   // Throws InvalidArgumentException
   $cc->addContentControl($text, ['id' => '123']);  // Must be 8 digits
   ```

4. **Missing inlineLevel for Cell SDTs:**
   ```php
   // SDT wrapping may fail or wrap incorrect element
   $cellText = $cell->addText('Value');
   $cc->addContentControl($cellText, ['tag' => 'cell_1']);  // Missing inlineLevel!
   
   // CORRECT:
   $cc->addContentControl($cellText, [
       'tag' => 'cell_1',
       'inlineLevel' => true  // REQUIRED
   ]);
   ```

5. **setStyles After addRow:**
   ```php
   $builder->addRow();  // Table created
   $builder->setStyles([...]);  // Throws ContentControlException!
   
   // CORRECT:
   $builder->setStyles([...])->addRow();
   ```

6. **injectInto on ContentControl:**
   ```php
   $builder = new TableBuilder($cc);
   $row = $builder->addRow();
   $row->addCell();
   $builder->injectInto($cc, 'tag');  // Type error! Expects ContentProcessor
   
   // CORRECT for template injection:
   $processor = new ContentProcessor('template.docx');
   $builder->injectInto($processor, 'tag');
   ```

### Logging and Debugging

#### DOCX Inspection

DOCX files are ZIP archives containing XML. To inspect generated Content Controls:

**Windows PowerShell:**
```powershell
# Extract DOCX
Expand-Archive generated.docx -DestinationPath temp -Force

# View SDTs
Get-Content temp/word/document.xml | Select-String '<w:sdt'

# Pretty print XML
[xml]$xml = Get-Content temp/word/document.xml
$xml.Save("temp/formatted.xml")
code temp/formatted.xml
```

**Linux/macOS Bash:**
```bash
# Extract DOCX
unzip -q generated.docx -d temp/

# View SDTs
cat temp/word/document.xml | grep '<w:sdt'

# Pretty print XML
xmllint --format temp/word/document.xml > temp/formatted.xml
cat temp/formatted.xml
```

#### Expected SDT Structure

Valid Content Control XML (ISO/IEC 29500-1:2016 §17.5.2):

**Block-level SDT** (wraps `<w:p>`, `<w:tbl>`, `<w:tc>`):
```xml
<w:sdt>
    <w:sdtPr>
        <w:id w:val="12345678"/>
        <w:alias w:val="Display Name"/>
        <w:tag w:val="metadata-tag"/>
        <w:lock w:val="sdtLocked"/>
        <w:richText/>  <!-- or w:text, w:picture, w:group -->
    </w:sdtPr>
    <w:sdtContent>
        <!-- Original element (w:p, w:tbl, w:tc, etc.) -->
        <w:p>
            <w:r>
                <w:t>Protected content</w:t>
            </w:r>
        </w:p>
    </w:sdtContent>
</w:sdt>
```

**Run-level SDT (v0.6.0+)** (wraps `<w:r>` inside `<w:p>`, CT_SdtContentRun):
```xml
<w:p>
    <w:sdt>
        <w:sdtPr>
            <w:id w:val="12345678"/>
            <w:alias w:val="First Name"/>
            <w:tag w:val="first-name"/>
            <w:richText/>
        </w:sdtPr>
        <w:sdtContent>
            <w:r>
                <w:rPr><w:b/></w:rPr>
                <w:t>John</w:t>
            </w:r>
        </w:sdtContent>
    </w:sdt>
</w:p>
```

#### Common Debugging Scenarios

1. **Duplicate SDTs:**
   - **Symptom:** Multiple `<w:sdt>` wrappers around same element
   - **Cause:** Using v2.x string replacement (deprecated)
   - **Check:** Verify `SDTInjector::$processedElements` registry marks element before wrapping
   - **Solution:** Upgrade to v3.0+ with DOM manipulation

2. **Missing SDTs:**
   - **Symptom:** Element not wrapped with SDT in output
   - **Cause:** `ElementLocator` XPath query doesn't match element
   - **Debug:** Check `findElementInDOM()` return value, verify element type and order
   - **Solution:** Ensure element type is supported, check registration order

3. **Malformed XML:**
   - **Symptom:** Word cannot open file, reports corruption
   - **Cause:** Invalid XML structure or namespace issues
   - **Debug:** Use `libxml_get_errors()` after `DOMDocument::loadXML()`
   - **Solution:** Validate XML with `xmllint --noout temp/word/document.xml`

4. **Namespace Pollution:**
   - **Symptom:** Redundant `xmlns:w` declarations in SDT elements
   - **Cause:** Manual XML string creation instead of DOM methods
   - **Solution:** Use `createElementNS()` with namespace URI, relies on root inheritance

#### PHPStan Analysis

Run PHPStan Level 9 analysis to catch type errors:

```bash
composer analyse
```

Output includes line-specific errors with context. Fix issues before committing.

#### Element Cache Statistics

For advanced debugging, inspect element identification cache:

```php
use MkGrow\ContentControl\ElementIdentifier;

// Get cache stats
$stats = ElementIdentifier::getCacheStats();
echo "Cached markers: " . $stats['markers'] . "\n";
echo "Cached hashes: " . $stats['hashes'] . "\n";

// Clear cache if needed (testing only)
ElementIdentifier::clearCache();
```

## Testing

### Running Tests

The project uses [Pest](https://pestphp.com/) for testing with 535+ tests and 82%+ code coverage.

**All Tests:**
```bash
composer test
```

**Unit Tests Only:**
```bash
composer test:unit
```

**Feature Tests Only:**
```bash
composer test:feature
```

**Coverage Report (Enforces 80% Minimum):**
```bash
composer test:coverage
```

**HTML Coverage Report:**
```bash
composer test:coverage-html
# Open coverage/html/index.html in browser
```

### Test Structure

**Unit Tests** (`tests/Unit/`):
- Test individual classes in isolation
- Mock dependencies for controllable environment
- Fast execution, no file I/O

**Feature Tests** (`tests/Feature/`):
- Integration tests with real DOCX generation
- Verify PHPWord integration
- Validate XML structure in generated files

**Key Test Categories:**
- `AdvancedHeaderFooterTest.php` - SDT injection in headers/footers
- `FluentTableBuilderTest.php` - Fluent API validation
- `GroupSdtReplacementTest.php` - Complex structure replacement
- `InlineLevelSDTTest.php` - Cell-level SDT wrapping
- `NoDuplicationTest.php` - v3.0 DOM manipulation verification
- `ImageHashCollisionTest.php` - UUID v5 collision resistance
- `NestedSDTDetectionTest.php` - Multi-level SDT preservation
- `RunLevelSDTTest.php` - Run-level `<w:r>` SDT wrapping (v0.6.0)
- `TableBuilderV2Test.php` - Direct Table API + addContentControl (v0.6.0)
- `DeprecationTest.php` - Deprecation notice validation (v0.6.0)

### Custom Pest Expectations

The test suite includes custom expectations defined in `tests/Pest.php`:

```php
expect($xml)->toBeValidXml();  // Validates XML well-formedness

expect($xml)->toHaveXmlElement('w:sdt');  // Checks element via XPath

expect($xml)->toHaveXmlAttribute('w:id', '12345678');  // Verifies attribute
```

### Static Analysis

**PHPStan Level 9:**
```bash
composer analyse
```

**Combined CI Check (Analysis + Coverage):**
```bash
composer ci
```

**Full Check (Analysis + All Tests):**
```bash
composer check
```

### Manual Testing in Word

After generating DOCX files, verify in Microsoft Word:

1. **Open Developer Tab:** File → Options → Customize Ribbon → Enable Developer
2. **View Content Controls:** Developer → Design Mode
3. **Check Properties:** Click SDT → Properties button
4. **Test Protection:** Try editing/deleting locked SDTs
5. **Verify Display Names:** Hover over SDT to see alias

For comprehensive Word testing checklist, see [docs/MANUAL_TESTING_GUIDE.md](docs/MANUAL_TESTING_GUIDE.md).

## Changelog and Contributing

**Changelog:** See [CHANGELOG.md](CHANGELOG.md) for version history and release notes.

**Contributing:** See [CONTRIBUTING.md](CONTRIBUTING.md) for development guidelines, coding standards, and pull request process.

**Code of Conduct:** Be professional, respectful, and constructive. Focus on technical merit and project improvement.

## Security

**Reporting Vulnerabilities:**

If you discover a security vulnerability in ContentControl, please **DO NOT** open a public issue or pull request.

Instead, report it privately via email to: **mateusbandeiraweb@gmail.com**

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if available)

We will respond within 48 hours and work with you to address the issue responsibly.

**Security Best Practices:**
- Never pass untrusted user input directly to `ContentProcessor::replaceContent()` without validation
- Validate file paths before processing to prevent directory traversal attacks
- Use `LIBXML_NONET` flag (enabled by default) to prevent XXE (XML External Entity) attacks
- Sanitize user-provided SDT tags and aliases
- Verify DOCX file integrity before processing

## Credits

**Main Contributors:**
- [Mateus Bandeira](https://github.com/mateusbandeira182) - Creator and Lead Developer

**Third-Party Assets:**
- [PHPOffice/PHPWord](https://github.com/PHPOffice/PHPWord) - Core Word document manipulation
- [ramsey/uuid](https://github.com/ramsey/uuid) - UUID v5 generation for collision-free hashing
- [Pest](https://pestphp.com/) - Testing framework
- [PHPStan](https://phpstan.org/) - Static analysis tool

**Acknowledgments:**
- ISO/IEC JTC 1/SC 34 for the Office Open XML standard
- PHPOffice community for excellent documentation and support

## License

ContentControl is licensed under the **MIT License**. See [LICENSE](LICENSE) for details.

---

**Documentation:** [docs/README.md](docs/README.md)  
**Samples:** [samples/README.md](samples/README.md)  
**GitHub:** [mateusbandeira182/ContentControl](https://github.com/mateusbandeira182/ContentControl)  
**Issues:** [GitHub Issues](https://github.com/mateusbandeira182/ContentControl/issues)

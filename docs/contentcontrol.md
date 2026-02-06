# ContentControl Component Documentation

## Overview

**ContentControl** is the primary facade/proxy component for creating Word documents with Structured Document Tags (SDTs). It extends PHPOffice/PHPWord functionality by transparently adding Content Control support while maintaining full compatibility with the PHPWord API.

**Location:** `src/ContentControl.php`

**Namespace:** `MkGrow\ContentControl`

**Key Characteristics:**
- **Final class** - designed for composition, not inheritance
- **Proxy pattern** - delegates document creation to PHPWord
- **Transparent integration** - all PHPWord methods available
- **Automatic ID generation** - collision-free 8-digit IDs
- **DOM-based injection** - v3.0+ wraps elements in-place to prevent duplication

## Architecture and Design

### Purpose and Role

ContentControl serves as the entry point for creating new Word documents with Content Controls. It acts as a thin wrapper around PHPWord, adding three critical capabilities:

1. **SDT Registration** - Track elements that should be wrapped with Content Controls
2. **ID Management** - Generate unique 8-digit IDs with collision detection
3. **DOM Injection** - Coordinate with `SDTInjector` to wrap elements during save

### Design Patterns

**Proxy/Facade Pattern:**
```
User → ContentControl → PHPWord
                ↓
          SDTRegistry (element tracking)
                ↓
          SDTInjector (DOM manipulation on save)
```

**Component Interaction:**
```php
// 1. ContentControl delegates to PHPWord for document structure
$section = $contentControl->addSection();  // Proxied to PHPWord

// 2. User creates elements via PHPWord API
$text = $section->addText('Content');

// 3. ContentControl registers element for SDT wrapping
$contentControl->addContentControl($text, [
    'tag' => 'field_1',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

// 4. On save(), SDTInjector wraps registered elements in DOM
$contentControl->save('output.docx');
```

### Dependencies

**Direct Dependencies:**
- `PhpOffice\PhpWord\PhpWord` - Core document manipulation
- `SDTRegistry` - Element-to-config mapping and ID generation
- `SDTInjector` - DOM manipulation service
- `SDTConfig` - Immutable configuration value object
- `IOFactory` - PHPWord writer factory

**Indirect Dependencies:**
- `ElementLocator` - XPath-based element location (via SDTInjector)
- `ElementIdentifier` - Content hash generation (via SDTInjector)
- `IDValidator` - ID format validation (via SDTConfig)

## Setup and Configuration

### Installation

```bash
composer require mkgrow/content-control
```

### Basic Instantiation

**Default Constructor:**
```php
use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();
// Creates new PHPWord instance internally
// Initializes SDTRegistry for element tracking
```

**Custom PHpWord Instance:**
```php
use PhpOffice\PhpWord\PhpWord;
use MkGrow\ContentControl\ContentControl;

$phpWord = new PhpWord();
// Configure PhpWord settings, styles, etc.
$phpWord->getSettings()->setZoom(100);

$cc = new ContentControl($phpWord);
// Uses provided instance instead of creating new one
```

### Document-Level Configuration

**Document Information:**
```php
$docInfo = $cc->getDocInfo();
$docInfo->setCreator('Your Name');
$docInfo->setCompany('Your Company');
$docInfo->setTitle('Document Title');
$docInfo->setDescription('Document description');
$docInfo->setCategory('Document category');
$docInfo->setLastModifiedBy('Your Name');
$docInfo->setCreated(time());
$docInfo->setModified(time());
```

**Document Settings:**
```php
$settings = $cc->getSettings();
$settings->setZoom(100);
$settings->setMirrorMargins(true);
$settings->setHideSpellingErrors(true);
$settings->setHideGrammaticalErrors(true);
```

**Named Styles:**
```php
// Font styles
$cc->addFontStyle('customFont', [
    'name' => 'Arial',
    'size' => 12,
    'bold' => true,
    'color' => '1F4788'
]);

// Paragraph styles
$cc->addParagraphStyle('customParagraph', [
    'alignment' => 'center',
    'spaceBefore' => 240,
    'spaceAfter' => 240
]);

// Table styles
$cc->addTableStyle('customTable', [
    'borderSize' => 6,
    'borderColor' => '1F4788',
    'cellMargin' => 80,
    'alignment' => 'center'
]);

// Title styles (headings 1-9)
$cc->addTitleStyle(1, [
    'name' => 'Arial',
    'size' => 16,
    'bold' => true,
    'color' => '1F4788'
]);
```

## Usage Examples

### Example 1: Simple Protected Text Field

```php
<?php
require 'vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();
$section = $cc->addSection();

// Add text element
$text = $section->addText('This field cannot be deleted');

// Wrap with Content Control
$cc->addContentControl($text, [
    'alias' => 'Protected Text Field',
    'tag' => 'protected_field_1',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

$cc->save('protected_field.docx');
```

**Generated XML:**
```xml
<w:sdt>
    <w:sdtPr>
        <w:id w:val="12345678"/>  <!-- Auto-generated -->
        <w:alias w:val="Protected Text Field"/>
        <w:tag w:val="protected_field_1"/>
        <w:lock w:val="sdtLocked"/>
        <w:richText/>
    </w:sdtPr>
    <w:sdtContent>
        <w:p>
            <w:r>
                <w:t>This field cannot be deleted</w:t>
            </w:r>
        </w:p>
    </w:sdtContent>
</w:sdt>
```

### Example 2: Multiple Elements with Different Lock Types

```php
<?php
use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();
$section = $cc->addSection();

// Locked SDT, editable content
$text1 = $section->addText('You can edit this text but not delete the control');
$cc->addContentControl($text1, [
    'alias' => 'Editable Content',
    'tag' => 'editable_field',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

// Locked content, deletable SDT
$text2 = $section->addText('You can delete this control but not edit the text');
$cc->addContentControl($text2, [
    'alias' => 'Locked Content',
    'tag' => 'locked_content_field',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED
]);

// No lock (fully editable)
$text3 = $section->addText('Fully editable and deletable');
$cc->addContentControl($text3, [
    'alias' => 'No Protection',
    'tag' => 'unlocked_field',
    'lockType' => ContentControl::LOCK_NONE
]);

$cc->save('multiple_locks.docx');
```

### Example 3: Formatted Text with Content Control

```php
<?php
use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();
$section = $cc->addSection();

// Create TextRun for complex formatting
$textRun = $section->addTextRun();
$textRun->addText('This is ', ['size' => 12]);
$textRun->addText('bold', ['bold' => true, 'size' => 12]);
$textRun->addText(' and ', ['size' => 12]);
$textRun->addText('italic', ['italic' => true, 'size' => 12]);
$textRun->addText(' text', ['size' => 12]);

// Wrap entire TextRun with Content Control
$cc->addContentControl($textRun, [
    'alias' => 'Formatted Text',
    'tag' => 'formatted_field',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

$cc->save('formatted_text.docx');
```

### Example 4: Table with Protected Structure

```php
<?php
use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();
$section = $cc->addSection();

// Create table
$table = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '1F4788',
    'cellMargin' => 80
]);

$table->addRow();
$table->addCell(4500)->addText('Name');
$table->addCell(4500)->addText('Value');

$table->addRow();
$table->addCell(4500)->addText('Item 1');
$table->addCell(4500)->addText('$100');

// Wrap entire table with GROUP Content Control
$cc->addContentControl($table, [
    'alias' => 'Protected Table',
    'tag' => 'table_1',
    'type' => ContentControl::TYPE_GROUP,
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

$cc->save('protected_table.docx');
```

### Example 5: Header and Footer with Content Controls

```php
<?php
use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();
$section = $cc->addSection();

// Add header
$header = $section->addHeader();
$headerText = $header->addText('Company Name');
$cc->addContentControl($headerText, [
    'alias' => 'Header Company Name',
    'tag' => 'header_company',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED
]);

// Add footer
$footer = $section->addFooter();
$footerText = $footer->addText('Confidential Document');
$cc->addContentControl($footerText, [
    'alias' => 'Footer Confidential',
    'tag' => 'footer_confidential',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED
]);

// Add body content
$section->addText('Document content here');

$cc->save('header_footer_sdt.docx');
```

### Example 6: Custom ID Specification

```php
<?php
use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();
$section = $cc->addSection();

$text = $section->addText('Custom ID field');

// Specify custom 8-digit ID
$cc->addContentControl($text, [
    'id' => '98765432',  // Must be exactly 8 digits, range: 10000000-99999999
    'alias' => 'Custom ID Field',
    'tag' => 'custom_id_field'
]);

$cc->save('custom_id.docx');
```

**Note:** IDs are auto-generated if omitted. Custom IDs must:
- Be exactly 8 characters
- Contain only digits (0-9)
- Be in range 10000000 to 99999999
- Be unique within the document

### Example 7: Image with Content Control

```php
<?php
use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();
$section = $cc->addSection();

// Add image
$image = $section->addImage('path/to/image.png', [
    'width' => 300,
    'height' => 200,
    'wrappingStyle' => 'inline'
]);

// Wrap image with Content Control
$cc->addContentControl($image, [
    'alias' => 'Protected Image',
    'tag' => 'image_1',
    'type' => ContentControl::TYPE_PICTURE,
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

$cc->save('protected_image.docx');
```

### Example 8: Title/Heading with Content Control

```php
<?php
use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();
$section = $cc->addSection();

// Add title (heading level 1)
$title = $section->addTitle('Document Title', 1);
$cc->addContentControl($title, [
    'alias' => 'Protected Title',
    'tag' => 'title_1',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED
]);

// Add heading level 2
$heading2 = $section->addTitle('Section Heading', 2);
$cc->addContentControl($heading2, [
    'alias' => 'Section Header',
    'tag' => 'heading_2_1',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED
]);

$section->addText('Section content here');

$cc->save('protected_headings.docx');
```

### Example 9: Cell-Level Content Controls (Inline Level)

```php
<?php
use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();
$section = $cc->addSection();

$table = $section->addTable();

// Header row
$table->addRow();
$table->addCell(4500)->addText('Field Name');
$table->addCell(4500)->addText('Field Value');

// Data row with cell-level SDTs
$table->addRow();
$table->addCell(4500)->addText('Price');

$cell = $table->addCell(4500);
$cellText = $cell->addText('$0.00');

// CRITICAL: inlineLevel MUST be true for cell elements
$cc->addContentControl($cellText, [
    'alias' => 'Price Value',
    'tag' => 'price_cell',
    'inlineLevel' => true,  // REQUIRED for cell-level SDTs
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

$cc->save('cell_level_sdt.docx');
```

**Important:** Omitting `inlineLevel: true` for elements inside table cells will cause SDT wrapping to fail or wrap incorrect elements.

## Technical Reference

### Public Methods

#### Constructor

```php
public function __construct(?PhpWord $phpWord = null)
```

**Parameters:**
- `$phpWord` - Optional PHPWord instance. If null, creates new instance.

**Behavior:**
- Stores PHPWord instance in private property
- Initializes SDTRegistry for element tracking
- No validation (PhpWord instance can be in any state)

**Example:**
```php
$cc = new ContentControl();  // New PhpWord instance
// or
$phpWord = new PhpWord();
$cc = new ContentControl($phpWord);  // Use existing instance
```

---

#### addContentControl

```php
public function addContentControl(object $element, array $options = []): object
```

**Purpose:** Register a PHPWord element for Content Control wrapping.

**Parameters:**
- `$element` - PHPWord element (Text, TextRun, Table, Cell, Title, Image)
- `$options` - Configuration array:
  - `id` (string, optional) - 8-digit ID, auto-generated if omitted
  - `alias` (string, default: '') - Display name in Word UI
  - `tag` (string, default: '') - Metadata identifier
  - `type` (string, default: TYPE_RICH_TEXT) - SDT type constant
  - `lockType` (string, default: LOCK_NONE) - Lock type constant
  - `inlineLevel` (bool, default: false) - MUST be true for cell elements

**Returns:** Same `$element` instance for fluent chaining

**Throws:**
- `InvalidArgumentException` - If element already registered or ID duplicated
- `InvalidArgumentException` - If ID format invalid (via SDTConfig validation)

**Behavior:**
1. Create SDTConfig from options array
2. If ID omitted, generate unique 8-digit ID via SDTRegistry
3. Validate element not already registered (identity check)
4. Validate ID not already used
5. Register element-config mapping in SDTRegistry
6. Return element unchanged

**Example:**
```php
$text = $section->addText('Content');

// Fluent chaining
$cc->addContentControl($text, [
    'tag' => 'field_1',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]); // Returns $text

// Can continue building
$section->addText('More content');
```

---

#### save

```php
public function save(string $filename, string $format = 'Word2007'): void
```

**Purpose:** Save document with injected Content Controls.

**Parameters:**
- `$filename` - Output file path
- `$format` - Writer format (default: 'Word2007' for .docx)

**Throws:**
- `RuntimeException` - If output directory not writable
- `ContentControlException` - If PHPWord save fails
- `TemporaryFileException` - If temp file cleanup fails after 3 retries

**Workflow:**
1. Validate output directory is writable
2. Generate temp file path: `sys_get_temp_dir() . '/phpword_' . uniqid() . '.docx'`
3. Create PHPWord writer via `IOFactory::createWriter($phpWord, $format)`
4. Save to temp file: `$writer->save($tempFile)`
5. If SDTs registered, invoke `SDTInjector::inject($tempFile, $sdtRegistry->getAll())`
6. Move temp file to final destination: `rename($tempFile, $filename)`
7. Clean up temp file if move fails (retry 3 times with 100ms sleep)

**Performance:**
- PHPWord save: ~50-200ms depending on document size
- SDTInjector: ~10-50ms for typical documents with <100 SDTs
- Total: ~100-300ms for typical use

**Example:**
```php
$cc->save('output.docx');  // Default Word2007 format

// Alternative formats
$cc->save('output.odt', 'ODText');  // OpenDocument
$cc->save('output.rtf', 'RTF');     // Rich Text Format
$cc->save('output.html', 'HTML');   // HTML (Content Controls not supported)
```

**Note:** Content Controls (SDTs) are only supported in Word2007 (.docx) format. Other formats will save the document but SDTs will not be present.

---

#### Delegation Methods

All PHPWord methods are available via magic `__call`:

```php
public function __call(string $method, array $arguments): mixed
```

**Delegated Methods:**
- `addSection()` - Create document section
- `getSections()` - Get all sections
- `getDocInfo()` - Access document metadata
- `getSettings()` - Access document settings
- `addFontStyle(string $name, array $style)` - Define named font style
- `addParagraphStyle(string $name, array $style)` - Define named paragraph style
- `addTableStyle(string $name, array $style)` - Define named table style
- `addTitleStyle(int $depth, array $fontStyle, array $paragraphStyle = [])` - Define title style

**Example:**
```php
$section = $cc->addSection();  // Proxied to PhpWord
$settings = $cc->getSettings();  // Proxied to PhpWord
```

---

#### getPhpWord

```php
public function getPhpWord(): PhpWord
```

**Purpose:** Access underlying PHPWord instance for advanced use.

**Returns:** PHPWord instance

**Use Cases:**
- Access PHPWord-specific features not exposed by ContentControl
- Integration with third-party PHPWord extensions
- Advanced document manipulation

**Example:**
```php
$phpWord = $cc->getPhpWord();
$phpWord->getSettings()->setProofState('dirty');
```

---

#### getSDTRegistry

```php
public function getSDTRegistry(): SDTRegistry
```

**Purpose:** Access SDT registry for advanced element tracking.

**Returns:** SDTRegistry instance

**Use Cases:**
- Query registered elements
- Check if element already has Content Control
- Inspect generated IDs

**Example:**
```php
$registry = $cc->getSDTRegistry();

// Check if element registered
if ($registry->has($element)) {
    $config = $registry->getConfig($element);
    echo "Element ID: " . $config->id;
}

// Get all registrations
$allRegistrations = $registry->getAll();
foreach ($allRegistrations as $registration) {
    echo "Tag: " . $registration['config']->tag . "\n";
}
```

### Constants

#### Content Control Types

```php
const TYPE_RICH_TEXT = 'richText';    // ISO/IEC 29500-1:2016 §17.5.2.31
const TYPE_PLAIN_TEXT = 'plainText';  // §17.5.2.34
const TYPE_PICTURE = 'picture';       // §17.5.2.27
const TYPE_GROUP = 'group';           // §17.5.2.15
```

**Usage:**
```php
$cc->addContentControl($element, [
    'type' => ContentControl::TYPE_RICH_TEXT  // Allow full formatting
]);
```

#### Lock Types

```php
const LOCK_NONE = '';                         // No protection (default)
const LOCK_SDT_LOCKED = 'sdtLocked';          // SDT cannot be deleted
const LOCK_CONTENT_LOCKED = 'sdtContentLocked';  // Content read-only
const LOCK_UNLOCKED = 'unlocked';             // Explicitly unlocked
```

**Lock Type Semantics:**

| Lock Type | SDT Deletable | Content Editable |
|-----------|---------------|------------------|
| `LOCK_NONE` | Yes | Yes |
| `LOCK_SDT_LOCKED` | No | Yes |
| `LOCK_CONTENT_LOCKED` | Yes | No |
| `LOCK_UNLOCKED` | Yes | Yes (explicit) |

**Usage:**
```php
$cc->addContentControl($element, [
    'lockType' => ContentControl::LOCK_SDT_LOCKED  // Protect control structure
]);
```

## Edge Cases and Limitations

### 1. Duplicate Element Registration

**Scenario:** Attempting to add Content Control to same element twice

```php
$text = $section->addText('Content');
$cc->addContentControl($text, ['tag' => 'field_1']);
$cc->addContentControl($text, ['tag' => 'field_2']);  // Throws!
```

**Exception:** `InvalidArgumentException: Element already registered`

**Solution:** Each element can only have one Content Control. Create separate elements for multiple SDTs:
```php
$text1 = $section->addText('Content 1');
$cc->addContentControl($text1, ['tag' => 'field_1']);

$text2 = $section->addText('Content 2');
$cc->addContentControl($text2, ['tag' => 'field_2']);
```

### 2. Duplicate ID

**Scenario:** Manually specifying same ID for different elements

```php
$cc->addContentControl($text1, ['id' => '12345678']);
$cc->addContentControl($text2, ['id' => '12345678']);  // Throws!
```

**Exception:** `InvalidArgumentException: ID already used`

**Solution:** Let auto-generation handle IDs, or ensure manual IDs are unique:
```php
// Auto-generation (recommended)
$cc->addContentControl($text1, ['tag' => 'field_1']);
$cc->addContentControl($text2, ['tag' => 'field_2']);

// Manual with unique IDs
$cc->addContentControl($text1, ['id' => '12345678', 'tag' => 'field_1']);
$cc->addContentControl($text2, ['id' => '87654321', 'tag' => 'field_2']);
```

### 3. Invalid ID Format

**Scenario:** Specifying ID that doesn't meet validation rules

```php
// Too short
$cc->addContentControl($text, ['id' => '123']);  // Throws!

// Non-numeric
$cc->addContentControl($text, ['id' => 'abc12345']);  // Throws!

// Out of range
$cc->addContentControl($text, ['id' => '00000001']);  // Throws!
```

**Exception:** `InvalidArgumentException` with specific error message

**Solution:** Use 8-digit numeric IDs in range 10000000-99999999:
```php
$cc->addContentControl($text, ['id' => '12345678']);  // Valid
```

### 4. Missing inlineLevel for Cell Elements

**Scenario:** Adding Content Control to element inside table cell without `inlineLevel: true`

```php
$cell = $table->addCell(4500);
$cellText = $cell->addText('Value');

// Missing inlineLevel flag
$cc->addContentControl($cellText, [
    'tag' => 'cell_field'
]);  // SDT may not wrap correctly!
```

**Result:** SDT wrapping fails or wraps incorrect element

**Solution:** Always set `inlineLevel: true` for cell elements:
```php
$cc->addContentControl($cellText, [
    'tag' => 'cell_field',
    'inlineLevel' => true  // REQUIRED
]);
```

### 5. Unsupported Element Types

**Scenario:** Attempting to add Content Control to unsupported element

```php
$section = $cc->addSection();
$cc->addContentControl($section, ['tag' => 'section_1']);  // Not supported!
```

**Supported Elements:**
- Text, TextRun, Table, Cell, Title, Image

**Unsupported Elements:**
- Section, TOC (Table of Contents)

**Reason:** Sections are too broad (wrap section children individually), TOC has complex field structure incompatible with SDTs

**Solution:** Wrap section children individually:
```php
$section = $cc->addSection();
$text = $section->addText('Content');
$cc->addContentControl($text, ['tag' => 'field_1']);  // OK
```

### 6. Modifying Elements After Registration

**Scenario:** Changing element content after adding Content Control

```php
$text = $section->addText('Original content');
$cc->addContentControl($text, ['tag' => 'field_1']);

// Modify element after registration
// PHPWord doesn't support direct text modification
// This is a PHPWord limitation, not ContentControl
```

**Behavior:** ContentControl tracks element by identity, not content. Changes to element content before `save()` are preserved in output.

**Note:** PHPWord elements are not designed for post-creation modification. Create elements with final content before registering.

### 7. Performance with Large Documents

**Scenario:** Document with 1000+ elements and 500+ SDTs

**Performance Characteristics:**
- ID generation: O(1) average, O(n) worst case with fallback
- Element registration: O(1) per element
- DOM manipulation: O(n) where n = element count
- Total save time: ~2s for 10,000 elements with 1,000 SDTs (tested)

**Optimization Tips:**
- Use auto-generated IDs (faster than manual validation)
- Register SDTs only for elements that need protection
- Consider splitting very large documents into multiple files

### 8. Header/Footer SDT Injection

**Scenario:** Adding Content Controls to header/footer elements

```php
$header = $section->addHeader();
$headerText = $header->addText('Header content');
$cc->addContentControl($headerText, ['tag' => 'header_field']);
```

**Behavior:** 
- Header/footer SDTs supported since v0.2.0
- `SDTInjector` automatically discovers and processes `word/header*.xml` and `word/footer*.xml` files
- Same API as body elements

**Limitation:** Header/footer discovery happens during `save()`, so performance is similar to body processing.

## Diagram

### Class Structure

```
┌───────────────────────────────────────────────────────────┐
│                    ContentControl                         │
├───────────────────────────────────────────────────────────┤
│ - phpWord: PhpWord                                        │
│ - sdtRegistry: SDTRegistry                                │
├───────────────────────────────────────────────────────────┤
│ + __construct(?PhpWord): void                             │
│ + addContentControl(object, array): object                │
│ + save(string, string): void                              │
│ + getPhpWord(): PhpWord                                   │
│ + getSDTRegistry(): SDTRegistry                           │
│ + __call(string, array): mixed                            │
├───────────────────────────────────────────────────────────┤
│ Constants:                                                │
│ + TYPE_RICH_TEXT = 'richText'                             │
│ + TYPE_PLAIN_TEXT = 'plainText'                           │
│ + TYPE_PICTURE = 'picture'                                │
│ + TYPE_GROUP = 'group'                                    │
│ + LOCK_NONE = ''                                          │
│ + LOCK_SDT_LOCKED = 'sdtLocked'                           │
│ + LOCK_CONTENT_LOCKED = 'sdtContentLocked'                │
│ + LOCK_UNLOCKED = 'unlocked'                              │
└───────────────────────────────────────────────────────────┘
                         │
                         │ delegates to
                         ▼
            ┌────────────────────────┐
            │      PhpWord           │
            │  (PHPOffice/PHPWord)   │
            └────────────────────────┘
                         │
                         │ uses
                         ▼
            ┌────────────────────────┐
            │    SDTRegistry         │
            │ (ID + mapping)         │
            └────────────────────────┘
                         │
                         │ injects via
                         ▼
            ┌────────────────────────┐
            │    SDTInjector         │
            │ (DOM manipulation)     │
            └────────────────────────┘
```

### Sequence Diagram: Document Creation Workflow

```
User          ContentControl    PhpWord    SDTRegistry    SDTInjector
 │                 │              │             │              │
 │ new CC()        │              │             │              │
 ├────────────────>│              │             │              │
 │                 │ new PhpWord  │             │              │
 │                 ├──────────────>│             │              │
 │                 │              │             │              │
 │                 │ new Registry │             │              │
 │                 ├──────────────┼─────────────>│              │
 │                 │              │             │              │
 │ addSection()    │              │             │              │
 ├────────────────>│ delegate     │             │              │
 │                 ├──────────────>│             │              │
 │ <section>       │ <section>    │             │              │
 │<────────────────┤<─────────────┤             │              │
 │                 │              │             │              │
 │ addText()       │              │             │              │
 ├────────────────────────────────>│             │              │
 │ <text element>  │              │             │              │
 │<────────────────┼──────────────┤             │              │
 │                 │              │             │              │
 │ addContentControl($text, config)             │              │
 ├────────────────>│              │             │              │
 │                 │ register()   │             │              │
 │                 ├──────────────┼─────────────>│              │
 │                 │              │             │ validate     │
 │                 │              │             │ generate ID  │
 │                 │              │             │ store mapping│
 │                 │ <success>    │             │              │
 │                 │<─────────────┼─────────────┤              │
 │ <text element>  │              │             │              │
 │<────────────────┤              │             │              │
 │                 │              │             │              │
 │ save('out.docx')│              │             │              │
 ├────────────────>│              │             │              │
 │                 │ createWriter │             │              │
 │                 ├──────────────>│             │              │
 │                 │ save(temp)   │             │              │
 │                 ├──────────────>│             │              │
 │                 │ <temp.docx>  │             │              │
 │                 │<─────────────┤             │              │
 │                 │              │             │              │
 │                 │ inject(temp, mappings)     │              │
 │                 ├──────────────┼─────────────┼──────────────>│
 │                 │              │             │   open DOCX  │
 │                 │              │             │   load DOM   │
 │                 │              │             │   wrap elements
 │                 │              │             │   save XML   │
 │                 │ <success>    │             │              │
 │                 │<─────────────┼─────────────┼──────────────┤
 │                 │              │             │              │
 │                 │ rename(temp, output)       │              │
 │                 ├──────────────┼─────────────┼──────────────┤
 │ <void>          │              │             │              │
 │<────────────────┤              │             │              │
```

---

**Related Documentation:**
- [ContentProcessor](contentprocessor.md) - Template modification
- [TableBuilder](tablebuilder.md) - Table creation with Content Controls
- [Main Documentation](README.md) - Architecture overview

**ISO/IEC Standard Reference:**
- ISO/IEC 29500-1:2016 §17.5.2 - Structured Document Tags specification

**PHPWord Documentation:**
- [PHPWord Docs](https://phpoffice.github.io/PHPWord/) - Core element creation

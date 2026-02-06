# ContentProcessor Component Documentation

## Overview

**ContentProcessor** is a specialized component for opening and modifying existing DOCX files. It provides XPath-based SDT location and manipulation capabilities for template-based workflows.

**Location:** `src/ContentProcessor.php`

**Namespace:** `MkGrow\ContentControl`

**Key Characteristics:**
- **Final class** - designed for composition, not inheritance
- **Template modification pattern** - works with existing files only
- **XPath-based SDT location** - efficient content control discovery
- **Single-use pattern** - ZIP closed after `save()`, requires new instance for additional modifications
- **Lazy loading** - headers/footers loaded on demand for performance

## Architecture and Design

### Purpose and Role

ContentProcessor serves as the entry point for template-based document workflows. Unlike `ContentControl` (which creates new documents), ContentProcessor opens existing DOCX files and provides methods to locate and modify Structured Document Tags (SDTs) by their tag attribute.

**Primary Use Cases:**
1. **Form Filling** - Replace placeholder SDTs with user data
2. **Template Processing** - Batch process documents from master template
3. **Dynamic Content Injection** - Replace GROUP SDTs with complex structures
4. **Document Finalization** - Clear all SDT content and lock document

### Design Patterns

**Service Layer Pattern:**
```
ContentProcessor (coordinates operations)
        ↓
    findSdt() (XPath query)
        ↓
    modify SDT content (replaceContent, setValue, etc.)
        ↓
    save() (update ZIP archive)
```

**Component Interaction:**
```php
// 1. Open existing DOCX
$processor = new ContentProcessor('template.docx');

// 2. Locate SDT by tag
$sdt = $processor->findSdt('field_1');

// 3. Modify content
$processor->replaceContent('field_1', 'New value');

// 4. Save changes
$processor->save('output.docx');
```

### Dependencies

**Direct Dependencies:**
- `ZipArchive` (ext-zip) - DOCX file manipulation
- `DOMDocument` (ext-dom) - XML parsing and manipulation
- `DOMXPath` - XPath-based element location
- `PhpOffice\PhpWord\Shared\XMLWriter` - Element serialization (via reflection)

**Indirect Dependencies:**
- PHPWord Writer classes - Element-to-XML conversion (accessed via reflection)
- `ContentControl` - For GROUP SDT replacement workflow
- `SDTInjector` - Indirectly via `ContentControl` serialization

### Workflow Type

**ContentProcessor vs ContentControl:**

| Feature | ContentProcessor | ContentControl |
|---------|------------------|----------------|
| Purpose | Modify existing DOCX | Create new DOCX |
| Input | Existing file path | None (new document) |
| SDT Discovery | XPath query by tag | Registration during creation |
| Modification | Replace/update SDT content | Wrap elements with SDTs |
| Save | In-place or new file | Always new file |
| Reusability | Single-use (ZIP closed after save) | Reusable before save |

## Setup and Configuration

### Installation

```bash
composer require mkgrow/content-control
```

### Basic Instantiation

```php
use MkGrow\ContentControl\ContentProcessor;

// Open existing DOCX file
$processor = new ContentProcessor('path/to/template.docx');
```

**Constructor Workflow:**
1. Validate file exists and is readable
2. Open DOCX as ZIP via `ZipArchive`
3. Verify `word/document.xml` exists
4. Eagerly load `word/document.xml` as `DOMDocument`
5. Lazy load headers/footers on demand (performance optimization)

**Throws:**
- `InvalidArgumentException` - File doesn't exist or not readable
- `ZipArchiveException` - ZIP open failure (includes ZipArchive error code)
- `DocumentNotFoundException` - `word/document.xml` missing
- `RuntimeException` - XML parse error

### XML Namespace Configuration

ContentProcessor registers three XML namespaces for XPath queries:

```php
const WORDML_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
const RELS_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
const VML_NAMESPACE = 'urn:schemas-microsoft-com:vml';
```

These are automatically registered in all XPath instances created by `createXPath()`.

## Usage Examples

### Example 1: Simple Text Replacement

```php
<?php
require 'vendor/autoload.php';

use MkGrow\ContentControl\ContentProcessor;

// Open template with SDTs
$processor = new ContentProcessor('invoice_template.docx');

// Replace SDT content by tag
$processor->replaceContent('customer_name', 'Acme Corporation');
$processor->replaceContent('invoice_number', 'INV-2024-001');
$processor->replaceContent('total_amount', '$1,234.56');

// Save to new file
$processor->save('invoice_2024_001.docx');
```

**Template Requirements:**
- Must contain SDTs with matching tag attributes
- SDTs can be any type (rich text, plain text, etc.)
- Original content is completely replaced

### Example 2: Preserve Formatting with setValue

```php
<?php
use MkGrow\ContentControl\ContentProcessor;

$processor = new ContentProcessor('formatted_template.docx');

// Replace text while preserving formatting (bold, italic, color, etc.)
$processor->setValue('company_name', 'New Company LLC');
$processor->setValue('date_field', date('Y-m-d'));

$processor->save('output.docx');
```

**setValue vs replaceContent:**
- `setValue()` - Replaces text in first `<w:t>` node, preserves parent `<w:r>` formatting
- `replaceContent()` - Removes all children, inserts new content (formatting lost)

**Use Case:** When template has pre-formatted fields (e.g., bold labels, colored text)

### Example 3: Append Content to Existing SDT

```php
<?php
use MkGrow\ContentControl\ContentProcessor;
use PhpOffice\PhpWord\Element\Text;

$processor = new ContentProcessor('report_template.docx');

// Add rows to existing table SDT
$textElement = new Text('New line item');
$processor->appendContent('line_items', $textElement);

$processor->save('report_with_items.docx');
```

**Behavior:**
- Existing `<w:sdtContent>` children preserved
- New element serialized and appended
- Useful for adding rows to tables, paragraphs to sections

### Example 4: GROUP SDT Replacement with Complex Structure

```php
<?php
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\ContentProcessor;

// Build complex structure to inject
$structure = new ContentControl();
$section = $structure->addSection();

// Add title
$title = $section->addTitle('Invoice Details', 1);
$structure->addContentControl($title, [
    'tag' => 'invoice_title',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED
]);

// Add table with nested SDTs
$table = $section->addTable();
$table->addRow();
$table->addCell(4500)->addText('Item');
$table->addCell(4500)->addText('Price');

$table->addRow();
$table->addCell(4500)->addText('Product 1');
$cell = $table->addCell(4500);
$priceText = $cell->addText('$99.99');
$structure->addContentControl($priceText, [
    'tag' => 'price_1',
    'inlineLevel' => true,
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

// Open template and replace GROUP SDT
$processor = new ContentProcessor('template_with_group.docx');
$processor->replaceGroupContent('invoice_section', $structure);

$processor->save('output_with_invoice.docx');
```

**Template Requirements:**
- SDT with tag `'invoice_section'` must have `<w:sdtPr><w:group/></w:sdtPr>`
- SDT type must be GROUP, otherwise throws `InvalidArgumentException`

**Performance:**
- ~150ms overhead (temp file I/O ~100ms + XML parsing ~50ms)
- Trade-off: Simplicity and reliability over speed
- Preserves unlimited nested SDTs via temp DOCX serialization

**Workflow:**
1. Validate target SDT is GROUP type
2. Save `ContentControl` to temp DOCX (triggers `SDTInjector`)
3. Extract `<w:body>` children XML (all nested SDTs preserved)
4. Clear target SDT `<w:sdtContent>`
5. Import extracted XML as `DOMDocumentFragment`
6. Append fragment to SDT content
7. Cleanup temp file

### Example 5: Batch Processing Template

```php
<?php
use MkGrow\ContentControl\ContentProcessor;

$customers = [
    ['name' => 'Customer A', 'invoice' => 'INV-001', 'amount' => '$500'],
    ['name' => 'Customer B', 'invoice' => 'INV-002', 'amount' => '$750'],
    ['name' => 'Customer C', 'invoice' => 'INV-003', 'amount' => '$1200'],
];

foreach ($customers as $customer) {
    // Create new processor for each iteration (single-use pattern)
    $processor = new ContentProcessor('invoice_template.docx');
    
    $processor->replaceContent('customer_name', $customer['name']);
    $processor->replaceContent('invoice_number', $customer['invoice']);
    $processor->replaceContent('total_amount', $customer['amount']);
    
    $processor->save("output/{$customer['invoice']}.docx");
}
```

**Important:** Must create new `ContentProcessor` instance for each iteration. ZIP is closed after `save()`, making instance unusable for further modifications.

### Example 6: Clear All SDT Content and Lock Document

```php
<?php
use MkGrow\ContentControl\ContentProcessor;

$processor = new ContentProcessor('template.docx');

// Clear all SDT content and lock document
$clearedCount = $processor->removeAllControlContents(true);

echo "Cleared {$clearedCount} Content Controls\n";

$processor->save('locked_template.docx');
```

**Parameters:**
- `$block = false` - Only clear SDT content
- `$block = true` - Clear content AND add document protection

**Document Protection:**
Adds to `word/settings.xml`:
```xml
<w:documentProtection w:edit="readOnly" w:enforcement="1"/>
```

**Use Case:** Template finalization - clear all fields and prevent editing

### Example 7: In-Place File Update

```php
<?php
use MkGrow\ContentControl\ContentProcessor;

$processor = new ContentProcessor('document.docx');

$processor->replaceContent('field_1', 'Updated value');

// No output path = in-place update
$processor->save();
```

**Behavior:**
- Saves changes to original file
- Original file is overwritten
- Use with caution in production (backup original first)

### Example 8: Remove Specific SDT Content

```php
<?php
use MkGrow\ContentControl\ContentProcessor;

$processor = new ContentProcessor('filled_form.docx');

// Clear specific SDT content (preserves SDT structure)
$processor->removeContent('field_to_clear');

// SDT remains, but <w:sdtContent> is empty
$processor->save('cleared_form.docx');
```

**Difference from replaceContent():**
- `removeContent()` - Clears all children, SDT preserved for reuse
- `replaceContent()` - Replaces children with new content

### Example 9: Working with Headers and Footers

```php
<?php
use MkGrow\ContentControl\ContentProcessor;

$processor = new ContentProcessor('template_with_headers.docx');

// SDTs in headers/footers automatically discovered
$processor->replaceContent('header_company', 'New Company Name');
$processor->replaceContent('footer_confidential', 'Internal Use Only');

// Also works in body
$processor->replaceContent('body_field', 'Body content');

$processor->save('output.docx');
```

**Search Order:**
1. `word/document.xml` (body)
2. `word/header*.xml` (all headers)
3. `word/footer*.xml` (all footers)

**Performance:** Headers/footers lazy-loaded on first `findSdt()` call, then cached.

## Technical Reference

### Public Methods

#### Constructor

```php
public function __construct(string $documentPath)
```

**Purpose:** Open existing DOCX file for modification.

**Parameters:**
- `$documentPath` - Absolute or relative path to .docx file

**Throws:**
- `InvalidArgumentException` - File doesn't exist or not readable
- `ZipArchiveException` - ZIP open failure (error code in message)
- `DocumentNotFoundException` - `word/document.xml` missing from archive
- `RuntimeException` - XML parsing error

**Workflow:**
1. Validate file exists: `file_exists($documentPath)`
2. Validate file readable: `is_readable($documentPath)`
3. Open ZIP: `$zip->open($documentPath)`
4. Check for main document: `$zip->statName('word/document.xml')`
5. Load document XML: `$xml = $zip->getFromName('word/document.xml')`
6. Parse as DOM: `$dom->loadXML($xml, LIBXML_NONET)`
7. Cache DOM: `$this->domCache['word/document.xml'] = $dom`
8. Keep ZIP open for later file discovery

**Example:**
```php
try {
    $processor = new ContentProcessor('template.docx');
} catch (\InvalidArgumentException $e) {
    echo "File error: " . $e->getMessage();
} catch (ZipArchiveException $e) {
    echo "ZIP error: " . $e->getMessage();
} catch (DocumentNotFoundException $e) {
    echo "Document structure error: " . $e->getMessage();
}
```

---

#### findSdt

```php
public function findSdt(string $tag): ?array
```

**Purpose:** Locate SDT by tag attribute across document, headers, and footers.

**Parameters:**
- `$tag` - Value of `<w:tag w:val="..."/>` attribute

**Returns:**
- `array` with keys: `['dom' => DOMDocument, 'sdt' => DOMElement, 'file' => string]`
- `null` if SDT not found

**Search Order:**
1. `word/document.xml`
2. `word/header*.xml` (lazy-loaded if not cached)
3. `word/footer*.xml` (lazy-loaded if not cached)

**XPath Query:**
```xpath
//w:sdt[w:sdtPr/w:tag[@w:val='{$tag}']][1]
```

**Example:**
```php
$sdt = $processor->findSdt('customer_name');

if ($sdt) {
    echo "Found in: " . $sdt['file'] . "\n";
    echo "SDT element: " . $sdt['sdt']->nodeName . "\n";
} else {
    echo "SDT with tag 'customer_name' not found\n";
}
```

**Use Case:** Exposed for extensions like `TableBuilder` to locate placeholder SDTs before injection.

---

#### replaceContent

```php
public function replaceContent(string $tag, string|AbstractElement $value): bool
```

**Purpose:** Replace entire SDT content with new value.

**Parameters:**
- `$tag` - SDT tag attribute value
- `$value` - String or PHPWord element (Text, TextRun, Table, etc.)

**Returns:**
- `true` - SDT found and modified
- `false` - SDT not found

**Behavior:**
1. Locate SDT via `findSdt($tag)`
2. Find `<w:sdtContent>` child
3. Remove ALL existing children
4. Insert new content:
   - String: Create `<w:p><w:r><w:t>{$value}</w:t></w:r></w:p>`
   - Element: Serialize via PHPWord writers (reflection)
5. Mark file as modified

**Example:**
```php
// String replacement
$processor->replaceContent('field_1', 'New text value');

// Element replacement
use PhpOffice\PhpWord\Element\Text;
$textElement = new Text('Formatted text');
$processor->replaceContent('field_2', $textElement);
```

**Important:** Removes all formatting from existing content. Use `setValue()` to preserve formatting.

---

#### setValue

```php
public function setValue(string $tag, string $value): bool
```

**Purpose:** Replace text while preserving formatting (bold, italic, color, font, etc.).

**Parameters:**
- `$tag` - SDT tag attribute value
- `$value` - New text content (plain string)

**Returns:**
- `true` - SDT found and modified
- `false` - SDT not found

**Behavior:**
1. Locate SDT and `<w:sdtContent>`
2. Find all `<w:t>` text nodes
3. Replace FIRST `<w:t>` node content (preserves parent `<w:r>` formatting)
4. Remove remaining `<w:t>` nodes (consolidate to first)
5. Add `xml:space="preserve"` if value has leading/trailing spaces
6. Mark file as modified

**Example:**
```php
// Template has: <w:r><w:rPr><w:b/><w:color w:val="FF0000"/></w:rPr><w:t>Old</w:t></w:r>
$processor->setValue('bold_red_field', 'New');
// Result: <w:r><w:rPr><w:b/><w:color w:val="FF0000"/></w:rPr><w:t>New</w:t></w:r>
```

**Advantage:** Preserves formatting from template designer's settings.

---

#### appendContent

```php
public function appendContent(string $tag, AbstractElement $element): bool
```

**Purpose:** Add element to existing SDT content without removing current children.

**Parameters:**
- `$tag` - SDT tag attribute value
- `$element` - PHPWord element to append

**Returns:**
- `true` - SDT found and modified
- `false` - SDT not found

**Behavior:**
1. Locate SDT and `<w:sdtContent>`
2. Serialize `$element` via PHPWord writers
3. Create `DOMDocumentFragment` from XML
4. Append fragment to `<w:sdtContent>` (existing children preserved)
5. Mark file as modified

**Example:**
```php
use PhpOffice\PhpWord\Element\Text;

// Add row to table SDT
$textElement = new Text('New line item');
$processor->appendContent('line_items_table', $textElement);
```

**Use Case:** Incrementally building lists, adding table rows, appending paragraphs.

---

#### replaceGroupContent

```php
public function replaceGroupContent(string $tag, ContentControl $structure): bool
```

**Purpose:** Replace GROUP SDT with complex multi-element structure preserving nested SDTs.

**Parameters:**
- `$tag` - Tag of GROUP SDT in template
- `$structure` - ContentControl instance with complex content

**Returns:**
- `true` - SDT found and modified
- `false` - SDT not found

**Throws:**
- `InvalidArgumentException` - Target SDT is not GROUP type

**Workflow:**
1. Locate SDT via `findSdt($tag)`
2. Validate `<w:sdtPr>` contains `<w:group/>` element
3. Serialize `$structure` to temp DOCX via `serializeContentControlWithSdts()`
4. Extract `<w:body>` children XML from temp file
5. Clear `<w:sdtContent>` children
6. Import XML as `DOMDocumentFragment`
7. Append fragment to `<w:sdtContent>`
8. Cleanup temp file
9. Mark file as modified

**Example:**
```php
$structure = new ContentControl();
$section = $structure->addSection();

$table = $section->addTable();
// ... build complex table with nested SDTs

$processor = new ContentProcessor('template.docx');
$processor->replaceGroupContent('invoice_section', $structure);
$processor->save('output.docx');
```

**Performance:** ~150ms overhead due to temp file I/O, acceptable for preserving unlimited nested SDTs.

**Template Requirement:**
```xml
<w:sdt>
    <w:sdtPr>
        <w:tag w:val="invoice_section"/>
        <w:group/>  <!-- REQUIRED -->
    </w:sdtPr>
    <w:sdtContent>
        <!-- Placeholder content, will be replaced -->
    </w:sdtContent>
</w:sdt>
```

---

#### removeContent

```php
public function removeContent(string $tag): bool
```

**Purpose:** Clear SDT content while preserving SDT structure.

**Parameters:**
- `$tag` - SDT tag attribute value

**Returns:**
- `true` - SDT found and cleared
- `false` - SDT not found

**Behavior:**
1. Locate SDT and `<w:sdtContent>`
2. Remove all children
3. Mark file as modified

**Example:**
```php
$processor->removeContent('field_to_clear');
// SDT preserved, <w:sdtContent> now empty
```

**Use Case:** Template reset - clear filled fields for reuse.

---

#### removeAllControlContents

```php
public function removeAllControlContents(bool $block = false): int
```

**Purpose:** Clear all SDT content across document, headers, footers. Optionally lock document.

**Parameters:**
- `$block` - If `true`, add read-only document protection

**Returns:** Count of SDTs cleared

**Behavior:**
1. Process `word/document.xml`, all headers, all footers
2. For each SDT: clear `<w:sdtContent>` children
3. If `$block = true`:
   - Load or create `word/settings.xml`
   - Add `<w:documentProtection w:edit="readOnly" w:enforcement="1"/>`
4. Mark all modified files

**Example:**
```php
// Clear content only
$count = $processor->removeAllControlContents();
echo "Cleared {$count} SDTs\n";

// Clear and lock document
$count = $processor->removeAllControlContents(true);
echo "Cleared and locked {$count} SDTs\n";
```

**Use Case:** Finalize template for distribution - empty all fields and prevent editing.

---

#### save

```php
public function save(string $outputPath = ''): void
```

**Purpose:** Save modifications to DOCX file.

**Parameters:**
- `$outputPath` - Output file path. Empty string = in-place update (overwrites original)

**Throws:**
- `RuntimeException` - File write error, ZIP close failure

**Workflow:**
1. Iterate modified files in `$this->modifiedFiles`
2. Serialize each cached DOM to XML string
3. Remove duplicate XML declarations: `preg_replace('/<\?xml[^?]+\?>\s*/', '', $xml, -1, $replaced)`
4. Update ZIP entry: `$zip->deleteName($file)` then `$zip->addFromString($file, $xml)`
5. Close ZIP: `$zip->close()` (persists changes)
6. If `$outputPath` provided: `copy($originalPath, $outputPath)`

**Example:**
```php
// Save to new file
$processor->save('output.docx');

// In-place update (overwrites original)
$processor->save();
```

**Important:** After `save()`, the `ContentProcessor` instance is unusable (ZIP closed). Create new instance for additional modifications.

---

### Protected/Private Methods (Internal Use)

#### findSdtByTag

```php
protected function findSdtByTag(string $tag): ?array
```

Alias for `findSdt()`. Exposed as public for API clarity.

---

#### createXPath

```php
protected function createXPath(DOMDocument $dom): DOMXPath
```

Creates `DOMXPath` instance with registered namespaces:
- `w:` → `WORDML_NAMESPACE`
- `r:` → `RELS_NAMESPACE`
- `v:` → `VML_NAMESPACE`

---

#### insertTextContent

```php
protected function insertTextContent(DOMElement $sdtContent, string $text, DOMDocument $dom): void
```

Inserts plain text as `<w:p><w:r><w:t>` structure.

---

#### insertElementContent

```php
protected function insertElementContent(DOMElement $sdtContent, AbstractElement $element, DOMDocument $dom): void
```

Serializes PHPWord element via reflection and inserts into SDT.

---

#### serializePhpWordElement

```php
protected function serializePhpWordElement(AbstractElement $element): string
```

Uses reflection to access PHPWord writers:
```php
$className = (new ReflectionClass($element))->getShortName();
$writerClass = "PhpOffice\\PhpWord\\Writer\\Word2007\\Element\\{$className}";
$writer = new $writerClass($xmlWriter, $element);
$writer->write();
return $xmlWriter->getData();
```

---

#### createDomFragment

```php
protected function createDomFragment(DOMDocument $dom, string $xml): DOMDocumentFragment
```

Converts XML string to `DOMDocumentFragment` for safe DOM insertion.

---

#### serializeContentControlWithSdts

```php
protected function serializeContentControlWithSdts(ContentControl $contentControl): string
```

Critical for GROUP SDT replacement:
1. Save `ContentControl` to temp DOCX
2. Open as ZIP and read `word/document.xml`
3. Parse as DOM
4. Extract `<w:body>` children
5. Serialize children to XML string
6. Return XML (temp file auto-cleaned in destructor)

**Performance:** ~100ms for temp file I/O.

## Edge Cases and Limitations

### 1. Single-Use Pattern

**Scenario:** Attempting to modify after `save()`

```php
$processor = new ContentProcessor('template.docx');
$processor->replaceContent('field_1', 'Value');
$processor->save('output.docx');

// ZIP is now closed
$processor->replaceContent('field_2', 'New value');  // Fails silently or throws
```

**Solution:** Create new instance for each workflow:
```php
$processor = new ContentProcessor('template.docx');
$processor->replaceContent('field_1', 'Value');
$processor->save('output.docx');

// New instance for additional changes
$processor2 = new ContentProcessor('output.docx');
$processor2->replaceContent('field_2', 'New value');
$processor2->save('final.docx');
```

### 2. GROUP SDT Type Validation

**Scenario:** Calling `replaceGroupContent()` on non-GROUP SDT

```php
// Template has RICH_TEXT SDT, not GROUP
$processor->replaceGroupContent('rich_text_field', $structure);  // Throws!
```

**Exception:** `InvalidArgumentException: SDT with tag 'rich_text_field' is not a GROUP content control`

**Solution:** Ensure template SDT has `<w:group/>` in `<w:sdtPr>`:
```xml
<w:sdtPr>
    <w:tag w:val="placeholder_tag"/>
    <w:group/>  <!-- REQUIRED for replaceGroupContent() -->
</w:sdtPr>
```

### 3. SDT Not Found

**Scenario:** Modifying SDT that doesn't exist in template

```php
$processor->replaceContent('nonexistent_tag', 'Value');  // Returns false
```

**Behavior:** Methods return `false` if SDT not found. No exception thrown.

**Best Practice:** Check return value:
```php
if (!$processor->replaceContent('field_1', 'Value')) {
    error_log("SDT with tag 'field_1' not found in template");
}
```

### 4. Template Corruption

**Scenario:** Malformed XML in template

```php
// Template has invalid XML structure
$processor = new ContentProcessor('corrupted.docx');  // Throws RuntimeException
```

**Debugging:**
1. Extract DOCX: `unzip corrupted.docx -d temp/`
2. Validate XML: `xmllint --noout temp/word/document.xml`
3. Check for:
   - Unclosed tags
   - Invalid namespace declarations
   - Malformed attribute values

### 5. In-Place Update Risk

**Scenario:** Overwriting original file without backup

```php
$processor = new ContentProcessor('important_template.docx');
$processor->replaceContent('field_1', 'Value');
$processor->save();  // Overwrites original!
```

**Recommendation:** Always save to new file in production:
```php
$processor->save('output/processed_' . uniqid() . '.docx');
```

### 6. Header/Footer SDT Performance

**Scenario:** Large document with many headers/footers

**Performance Impact:**
- First `findSdt()` lazy-loads ALL headers and footers
- Subsequent searches use cached DOMs (O(1) file access)

**Optimization:** If working only with body, headers/footers never loaded (performance neutral).

### 7. Namespace Conflicts

**Scenario:** Template uses non-standard namespaces

**Registered Namespaces:**
- `w:` (WordML Main)
- `r:` (Relationships)
- `v:` (VML)

**Limitation:** If template uses custom namespaces, XPath queries may fail.

**Solution:** Extend `createXPath()` to register additional namespaces if needed (requires subclass, but class is final).

### 8. Complex Element Serialization

**Scenario:** Appending or replacing with complex PHPWord elements

**Supported Elements:**
- Text, TextRun, Table, Image, Title

**Limitation:** PhpWord writers accessed via reflection - if PHPWord changes writer class structure, serialization may fail.

**Mitigation:** Library tested against PhpWord ^1.4, pinned in composer.json.

## Diagram

### Class Structure

```
┌─────────────────────────────────────────────────────────────┐
│                    ContentProcessor                         │
├─────────────────────────────────────────────────────────────┤
│ - zip: ZipArchive                                           │
│ - domCache: array<string, DOMDocument>                      │
│ - modifiedFiles: array<string, true>                        │
│ - documentPath: string                                      │
├─────────────────────────────────────────────────────────────┤
│ + __construct(string): void                                 │
│ + findSdt(string): ?array                                   │
│ + replaceContent(string, string|AbstractElement): bool      │
│ + setValue(string, string): bool                            │
│ + appendContent(string, AbstractElement): bool              │
│ + replaceGroupContent(string, ContentControl): bool         │
│ + removeContent(string): bool                               │
│ + removeAllControlContents(bool): int                       │
│ + save(string): void                                        │
├─────────────────────────────────────────────────────────────┤
│ # createXPath(DOMDocument): DOMXPath                        │
│ # serializePhpWordElement(AbstractElement): string          │
│ # createDomFragment(DOMDocument, string): DOMFragment       │
│ # serializeContentControlWithSdts(ContentControl): string   │
└─────────────────────────────────────────────────────────────┘
```

### Sequence Diagram: Template Modification Workflow

```
User       ContentProcessor    ZipArchive    DOMDocument    XPath
 │               │                 │             │            │
 │ new CP('template.docx')
 ├──────────────>│                 │             │            │
 │               │ open()          │             │            │
 │               ├────────────────>│             │            │
 │               │ getFromName('word/document.xml')
 │               ├────────────────>│             │            │
 │               │ <xml string>    │             │            │
 │               │<────────────────┤             │            │
 │               │ loadXML()       │             │            │
 │               ├─────────────────┼────────────>│            │
 │               │ <dom cached>    │             │            │
 │               │                 │             │            │
 │ replaceContent('tag', 'value')
 ├──────────────>│                 │             │            │
 │               │ createXPath()   │             │            │
 │               ├─────────────────┼─────────────┼───────────>│
 │               │ query("//w:sdt[w:sdtPr/w:tag[@w:val='tag']]")
 │               ├─────────────────┼─────────────┼───────────>│
 │               │ <sdt element>   │             │            │
 │               │<────────────────┼─────────────┼────────────┤
 │               │                 │             │            │
 │               │ find sdtContent │             │            │
 │               │ remove children │             │            │
 │               │ insert new content            │            │
 │               │ mark modified   │             │            │
 │               │                 │             │            │
 │ save('output.docx')             │             │            │
 ├──────────────>│                 │             │            │
 │               │ serialize DOM   │             │            │
 │               ├─────────────────┼────────────>│            │
 │               │ <xml string>    │             │            │
 │               │<────────────────┼─────────────┤            │
 │               │                 │             │            │
 │               │ deleteName()    │             │            │
 │               ├────────────────>│             │            │
 │               │ addFromString() │             │            │
 │               ├────────────────>│             │            │
 │               │                 │             │            │
 │               │ close()         │             │            │
 │               ├────────────────>│             │            │
 │               │                 │             │            │
 │               │ copy to output  │             │            │
 │               ├─────────────────┼─────────────┼────────────┤
 │ <void>        │                 │             │            │
 │<──────────────┤                 │             │            │
```

### Sequence Diagram: GROUP SDT Replacement

```
User    ContentProcessor  ContentControl  SDTInjector  TempFile
 │            │                 │              │          │
 │ replaceGroupContent('tag', $structure)
 ├───────────>│                 │              │          │
 │            │ findSdt('tag')  │              │          │
 │            │ validate GROUP  │              │          │
 │            │                 │              │          │
 │            │ serializeContentControlWithSdts($structure)
 │            ├────────────────>│              │          │
 │            │                 │ save(temp)   │          │
 │            │                 ├──────────────┤          │
 │            │                 │              │ inject() │
 │            │                 │              ├─────────>│
 │            │                 │<─────────────┤          │
 │            │                 │              │          │
 │            │                 │ open temp    │          │
 │            │                 ├──────────────┼─────────>│
 │            │                 │ extract <w:body> children
 │            │                 │<─────────────┼──────────┤
 │            │ <xml string>    │              │          │
 │            │<────────────────┤              │          │
 │            │                 │              │          │
 │            │ clear sdtContent│              │          │
 │            │ import fragment │              │          │
 │            │ append to sdt   │              │          │
 │            │                 │              │          │
 │            │ cleanup temp    │              │          │
 │            ├─────────────────┼──────────────┼─����────>│
 │            │                 │              │          │
 │ <true>     │                 │              │          │
 │<───────────┤                 │              │          │
```

---

**Related Documentation:**
- [ContentControl](contentcontrol.md) - Document creation
- [TableBuilder](tablebuilder.md) - Table injection into templates
- [Main Documentation](README.md) - Architecture overview

**ISO/IEC Standard Reference:**
- ISO/IEC 29500-1:2016 §17.5.2 - Structured Document Tags specification

**PHPWord Documentation:**
- [PHPWord Docs](https://phpoffice.github.io/PHPWord/) - Element reference

# GROUP Content Control Support (v0.4.2)

**Deep Serialization for Complex Document Structures**

## Table of Contents

1. [Overview](#overview)
2. [Problem Statement](#problem-statement)
3. [Solution Architecture](#solution-architecture)
4. [Usage Guide](#usage-guide)
5. [Technical Implementation](#technical-implementation)
6. [Limitations & Workarounds](#limitations--workarounds)

---

## Overview

ContentControl v0.4.2 introduces `replaceGroupContent()` method in `ContentProcessor`, enabling replacement of GROUP-type Content Controls with complex structures containing:

- ✅ Multiple paragraphs and tables
- ✅ Nested Content Controls (text, rich text, picture)
- ✅ Mixed content types (text + tables + images)
- ✅ Deep serialization preserving SDT metadata (`w:sdtPr` + `w:sdtContent`)

This unblocks template-based document generation workflows that require replacing GROUP SDT placeholders with rich, structured content.

---

## Problem Statement

### The Limitation (v0.4.1 and earlier)

Prior to v0.4.2, `ContentProcessor` methods (`setValue()`, `replaceContent()`) only supported **simple text replacement** within Content Controls:

```php
// ❌ This worked, but lost SDTs nested in $contentControl
$processor->replaceContent('group-placeholder', $contentControl);
// Result: SDTs serialized as plain text, not XML structure
```

**Why?** The replacement logic used `TextRun` elements, which **escape XML** and lose SDT structure.

### The Blocker

Template workflows required:
1. **Template Creation**: Document with GROUP SDT placeholder
2. **Content Generation**: Complex structure with nested SDTs (invoice table, customer fields, etc.)
3. **Replacement**: Inject generated content into template **preserving all nested SDTs**

**Impact:** Users had to manually merge documents or use external tools, defeating the purpose of the library.

---

## Solution Architecture

### High-Level Workflow

```
┌─────────────────────────┐
│  Template Document      │
│  ┌──────────────────┐   │
│  │ GROUP SDT        │   │
│  │ tag="invoice"    │   │
│  │ {{ placeholder }}│   │
│  └──────────────────┘   │
└─────────────────────────┘
           │
           │ replaceGroupContent('invoice', $replacement)
           ▼
┌─────────────────────────┐
│  Generated Content      │
│  ┌──────────────────┐   │
│  │ Text + Table     │   │
│  │ ┌──────────┐     │   │
│  │ │ Cell SDT │     │   │
│  │ └──────────┘     │   │
│  └──────────────────┘   │
└─────────────────────────┘
           │
           │ Deep Serialization (w:sdtPr + w:sdtContent)
           ▼
┌─────────────────────────┐
│  Result Document        │
│  ┌──────────────────┐   │
│  │ GROUP SDT        │   │
│  │ ┌──────────────┐ │   │
│  │ │ Text + Table │ │   │
│  │ │ Nested SDTs  │ │   │
│  │ └──────────────┘ │   │
│  └──────────────────┘   │
└─────────────────────────┘
```

### Key Components

1. **`replaceGroupContent()`** - Public API in `ContentProcessor`
2. **`serializeContentControlWithSdts()`** - Deep serialization engine
3. **`createDomFragment()`** - XML → DOM conversion
4. **Temp File Workflow** - Leverages existing `ContentControl::save()` infrastructure

---

## Usage Guide

### Step 1: Create Template with GROUP SDT

```php
use MkGrow\ContentControl\ContentControl;

$template = new ContentControl();
$section = $template->addSection();

// Add placeholder text
$placeholder = $section->addText('{{ invoice_data }}');

// Wrap with GROUP SDT
$template->addContentControl($placeholder, [
    'tag' => 'invoice_section',
    'alias' => 'Invoice Data Section',
    'type' => ContentControl::TYPE_GROUP,
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

$template->save('template.docx');
```

### Step 2: Create Replacement Content

```php
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Bridge\TableBuilder;

$replacement = new ContentControl();
$section = $replacement->addSection();

// Add text with SDT
$customerName = $section->addText('Acme Corporation');
$replacement->addContentControl($customerName, [
    'tag' => 'customer_name',
    'alias' => 'Customer Name',
]);

// Add table with nested SDTs using fluent API
$builder = new TableBuilder($replacement);
$builder
    ->addRow()
        ->addCell(3000)->addText('Item', ['bold' => true])->end()
        ->addCell(2000)->addText('Price', ['bold' => true])->end()
    ->end()
    ->addRow()
        ->addCell(3000)
            ->withContentControl(['tag' => 'item_name'])
            ->addText('Widget Premium')
        ->end()
        ->addCell(2000)
            ->withContentControl(['tag' => 'item_price'])
            ->addText('$50.00')
        ->end()
    ->end()
    ->addContentControl([
        'tag' => 'invoice_items_table',
        'alias' => 'Invoice Items',
        'lockType' => ContentControl::LOCK_SDT_LOCKED,
    ]);
```

### Step 3: Replace GROUP SDT

```php
use MkGrow\ContentControl\ContentProcessor;

$processor = new ContentProcessor('template.docx');
$success = $processor->replaceGroupContent('invoice_section', $replacement);

if ($success) {
    $processor->save('output.docx');
    echo "✓ GROUP SDT replaced successfully!\n";
} else {
    echo "✗ Failed to replace GROUP SDT\n";
}
```

### Step 4: Verify Result

Open `output.docx` in Microsoft Word:
1. **Developer Tab** → **XML Mapping Pane**
2. Verify nested SDTs are present:
   - `customer_name` (editable text SDT)
   - `invoice_items_table` (locked table SDT)
   - `item_name`, `item_price` (cell-level SDTs)

---

## Technical Implementation

### Serialization Process

```php
private function serializeContentControlWithSdts(ContentControl $contentControl): string
{
    // 1. Save ContentControl to temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'group_sdt_') . '.docx';
    $contentControl->save($tempFile);
    
    // 2. Extract word/document.xml from ZIP
    $zip = new \ZipArchive();
    $zip->open($tempFile);
    $documentXml = $zip->getFromName('word/document.xml');
    $zip->close();
    
    // 3. Parse XML and extract <w:body> content
    $dom = new \DOMDocument();
    $dom->loadXML($documentXml);
    $xpath = new \DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    
    $bodyNodes = $xpath->query('//w:body/*');
    
    // 4. Serialize child nodes (preserving SDT structure)
    $serialized = '';
    foreach ($bodyNodes as $node) {
        $serialized .= $dom->saveXML($node);
    }
    
    // 5. Clean up namespace pollution
    $serialized = preg_replace('/\s+xmlns:w="[^"]+"/', '', $serialized);
    
    // 6. Cleanup temp file
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
    
    return $serialized;
}
```

### Why Temp File Approach?

**Rationale:**
- Reuses battle-tested `ContentControl::save()` and `SDTInjector` logic
- Guarantees identical XML structure as direct save
- Avoids duplicating complex serialization logic
- Simple, maintainable, testable

**Performance:**
- ~150ms overhead for typical documents
- Acceptable for template generation workflows
- In-memory optimization deferred to v0.5.0 (see [Issue #42](../issues/42))

---

## Limitations & Workarounds

### 1. GROUP SDT Type Enforcement

**Limitation:** `replaceGroupContent()` only works with GROUP-type SDTs.

**Error:**
```php
$processor->replaceGroupContent('rich-text-sdt', $content);
// InvalidArgumentException: SDT 'rich-text-sdt' is not a GROUP type
```

**Workaround:** Ensure template uses `TYPE_GROUP`:
```php
$template->addContentControl($placeholder, [
    'type' => ContentControl::TYPE_GROUP,  // Required!
    'tag' => 'invoice_section',
]);
```

---

### 2. Performance (Temp File I/O)

**Limitation:** Temp file workflow adds ~150ms vs in-memory (measured on SSD).

**Workaround:** For high-performance scenarios, consider:
1. **Batch Processing**: Generate multiple docs in parallel
2. **Caching**: Reuse generated content across templates
3. **Upgrade to v0.5.0**: In-memory serialization planned (see roadmap)

---

### 3. Namespace Pollution

**Limitation:** PhpWord sometimes adds redundant `xmlns:w` declarations.

**Solution:** Automatic cleanup in `serializeContentControlWithSdts()`:
```php
$xml = preg_replace('/\s+xmlns:w="[^"]+"/', '', $xml);
```

No action required from users.

---

### 4. Complex Table Structures

**Limitation:** Nested tables (table within table cell) may have layout issues in Word.

**Workaround:** Use flat table structures or test thoroughly in Microsoft Word 365.

---

## Error Handling

### Invalid SDT Type

```php
try {
    $processor->replaceGroupContent('non-group-sdt', $content);
} catch (InvalidArgumentException $e) {
    echo "Error: " . $e->getMessage();
    // "SDT with tag 'non-group-sdt' is not a GROUP type Content Control"
}
```

### Non-Existent Tag

```php
$result = $processor->replaceGroupContent('non-existent-tag', $content);
if (!$result) {
    echo "Warning: SDT with tag 'non-existent-tag' not found";
}
```

---

## Best Practices

### 1. Use Descriptive Tags

```php
// ✅ GOOD: Semantic tags
$template->addContentControl($placeholder, [
    'tag' => 'invoice_section',
    'alias' => 'Invoice Data Section',
]);

// ❌ BAD: Generic tags
$template->addContentControl($placeholder, ['tag' => 'section1']);
```

---

### 2. Validate Before Replacement

```php
function validateTemplate(string $templatePath, string $tag): bool {
    $processor = new ContentProcessor($templatePath);
    $sdt = $processor->findSdt($tag);
    
    if ($sdt === null) {
        throw new Exception("SDT with tag '{$tag}' not found in template");
    }
    
    // Additional validations (type, lock status, etc.)
    return true;
}
```

---

### 3. Preserve Existing Content

```php
// If template has default content, consider backing up
$processor = new ContentProcessor('template.docx');

// Backup original
copy('template.docx', 'template-backup.docx');

// Replace
$processor->replaceGroupContent('invoice_section', $replacement);
$processor->save('template.docx'); // Overwrites original
```

---

## Examples

See working examples in `samples/`:
- [tablebuilder_group_integration.php](../samples/tablebuilder_group_integration.php) - Complete workflow with nested SDTs
- [complete_end_to_end_example.php](../samples/complete_end_to_end_example.php) - Real-world invoice generation

---

## Roadmap

### Planned Enhancements (v0.5.0)

- **In-Memory Serialization**: Eliminate temp file I/O for 90% performance gain
- **Streaming API**: Handle large documents (500+ pages) without memory issues
- **Validation API**: Pre-flight checks for template compatibility
- **Custom Serializers**: Plugin architecture for custom XML generation

---

## See Also

- [TableBuilder v2 Documentation](TableBuilder-v2.md) - Fluent API for table creation
- [Migration Guide](MIGRATION-v042.md) - Upgrading from v0.4.1
- [API Reference](../README.md) - Core ContentControl documentation

---

**Version:** v0.4.2  
**Last Updated:** February 3, 2026  
**Status:** Stable

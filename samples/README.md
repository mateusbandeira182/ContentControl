# ContentControl Sample Files

Progressive examples demonstrating ContentControl library features from basic to advanced usage.

## Prerequisites

- PHP 8.2 or higher
- Composer dependencies installed (`composer install`)
- Microsoft Word 2016+ or LibreOffice Writer 7.6+ (for viewing generated documents)

## Sample Files Overview

### 01-quick-start.php (Basic)
**Features:** Document creation, basic text elements, Content Controls  
**Description:** Minimal "Hello World" example showing how to wrap text with SDTs.

```bash
php samples/01-quick-start.php
```

**Output:** `output/01-quick-start.docx`  
**Key Concepts:**
- Creating a ContentControl instance
- Adding sections and text
- Wrapping elements with `addContentControl()`
- Lock types: `LOCK_SDT_LOCKED` vs `LOCK_CONTENT_LOCKED`

---

### 02-basic-table.php (Basic)
**Features:** TableBuilder, fluent API, `setStyles()` method (v0.5.0)  
**Description:** Creates a styled table using the new `setStyles()` method.

```bash
php samples/02-basic-table.php
```

**Output:** `output/02-basic-table.docx`  
**Key Concepts:**
- Using `TableBuilder` for table creation
- Setting table-level styles BEFORE adding rows
- Fluent API chaining: `$row = $builder->addRow(); $row->addCell()->addText()`
- Border customization (size, color, margins)

---

### 03-template-modification.php (Intermediate)
**Features:** ContentProcessor, template modification, SDT replacement  
**Description:** Opens existing DOCX and modifies Content Controls.

**Prerequisites:** Requires custom template file (see instructions in script)

```bash
php samples/03-template-modification.php
```

**Output:** `output/03-invoice-filled.docx`  
**Key Concepts:**
- Opening existing documents with `ContentProcessor`
- Replacing SDT content with `replaceContent()` and `setValue()`
- Template-based document generation workflow

---

### 04-table-with-controls.php (Intermediate)
**Features:** Cell-level SDTs, `inlineLevel` flag  
**Description:** Creates table with Content Controls at individual cell level.

```bash
php samples/04-table-with-controls.php
```

**Output:** `output/04-table-with-controls.docx`  
**Key Concepts:**
- Adding SDTs to table cells using `withContentControl()`
- **CRITICAL:** `inlineLevel => true` flag for cell-level SDTs
- Difference between table-level and cell-level protection

---

### 05-template-injection.php (Advanced)
**Features:** Template injection, UUID v5 matching, placeholder replacement  
**Description:** Injects complex table into existing template using placeholder SDT.

**Prerequisites:** Requires custom template file (see instructions in script)

```bash
php samples/05-template-injection.php
```

**Output:** `output/05-injected-table.docx`  
**Key Concepts:**
- Building tables programmatically
- Injecting into templates with `injectInto($processor, $tag)`
- UUID v5 deterministic hashing for table matching

---

### 06-multi-element-document.php (Advanced)
**Features:** Multiple element types, all lock types, comprehensive SDT usage  
**Description:** Demonstrates Text, Title, Image, Table with various protection levels.

```bash
php samples/06-multi-element-document.php
```

**Output:** `output/06-multi-element-document.docx`  
**Key Concepts:**
- Title elements (Heading 1, Heading 2)
- Image elements with `TYPE_PICTURE`
- Multiple lock types: `LOCK_NONE`, `LOCK_CONTENT_LOCKED`, `LOCK_SDT_LOCKED`
- Rich text with `TYPE_RICH_TEXT`

---

### 07-header-footer-controls.php (Advanced)
**Features:** Header/footer SDTs (v0.2.0)  
**Description:** Adds Content Controls to headers and footers.

```bash
php samples/07-header-footer-controls.php
```

**Output:** `output/07-header-footer-controls.docx`  
**Key Concepts:**
- Adding headers/footers with `addHeader()` and `addFooter()`
- Wrapping header/footer elements with SDTs
- Page numbering with `{PAGE}` and `{NUMPAGES}` fields
- Feature added in v0.2.0

---

### 08-group-sdt-replacement.php (Advanced)
**Features:** GROUP SDTs, `replaceGroupContent()` (v0.4.2)  
**Description:** Replaces GROUP SDTs with complex structures.

**Prerequisites:** Requires custom template file (see instructions in script)

```bash
php samples/08-group-sdt-replacement.php
```

**Output:** `output/08-group-replaced.docx`  
**Key Concepts:**
- Creating complex replacement content
- Replacing entire GROUP SDTs with `replaceGroupContent()`
- Building multi-element structures (title + text + list + table)
- Feature fixed in v0.4.2 (see docs/GROUP-SDT-FIX.md)

---

## Running All Samples

### PowerShell (Windows)
```powershell
Get-ChildItem samples\0*.php | ForEach-Object { php $_.FullName }
```

### Bash (Linux/macOS)
```bash
for sample in samples/0*.php; do php "$sample"; done
```

---

## Output Directory

All generated DOCX files are saved to `samples/output/` (gitignored).

To view generated files:
```powershell
# Windows
start samples\output\01-quick-start.docx

# Linux with LibreOffice
libreoffice samples/output/01-quick-start.docx

# macOS
open samples/output/01-quick-start.docx
```

---

## Troubleshooting

### Error: "Class 'MkGrow\ContentControl\ContentControl' not found"
**Solution:** Run `composer install` from project root.

### Error: "Template not found"
**Solution:** Templates for examples 03, 05, and 08 must be created manually. Follow instructions printed by the script.

### Error: "Failed to save document"
**Solution:** Ensure `samples/output/` directory exists and is writable:
```bash
mkdir -p samples/output
chmod 755 samples/output
```

### Generated DOCX shows errors in Word
**Solutions:**
1. Verify you're using Microsoft Word 2016 or later
2. Enable Developer tab: File → Options → Customize Ribbon → Check "Developer"
3. Check if Content Controls are visible: Developer → Design Mode
4. For LibreOffice: Install latest version (7.6+)

### Content Controls not visible in Word
**Solution:** Enable Developer tab and click "Design Mode" to see SDT boundaries.

---

## Template Creation Guide

Templates for examples 03, 05, and 08 require Content Controls created in Word:

1. Open Microsoft Word
2. Enable Developer tab: File → Options → Customize Ribbon → Check "Developer"
3. Click Developer → Design Mode
4. Insert → Controls → Plain Text Content Control
5. Select control → Click Properties
6. Set Tag value (e.g., `invoice-number`, `product-table-placeholder`, `report-section`)
7. Set Title/Alias for identification
8. For GROUP SDTs (example 08): Use "Group" control instead of "Plain Text"
9. Save as .docx in `samples/fixtures/` directory

---

## Additional Resources

- Main Documentation: [README.md](../README.md)
- TableBuilder Guide: [docs/TableBuilder-v2.md](../docs/TableBuilder-v2.md)
- Migration Guide (v0.4.2): [docs/MIGRATION-v042.md](../docs/MIGRATION-v042.md)
- API Documentation: [docs/README.md](../docs/README.md)
- Compatibility Matrix: [docs/COMPATIBILITY-v042.md](../docs/COMPATIBILITY-v042.md)

---

## Version Information

**Library Version:** 0.5.0  
**PHP Requirement:** ≥8.2  
**PHPWord Requirement:** ^1.4  

For older sample files (v0.4.2), see `.archive/v0.4.2/` directory.

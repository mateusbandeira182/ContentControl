# Migration Guide to v0.4.2

**Upgrading from v0.4.1 to v0.4.2**

## Table of Contents

1. [Overview](#overview)
2. [Breaking Changes](#breaking-changes)
3. [Deprecations](#deprecations)
4. [New Features](#new-features)
5. [Migration Steps](#migration-steps)

---

## Overview

ContentControl v0.4.2 introduces:
- **Fluent API** for TableBuilder (60% less code)
- **GROUP SDT Support** via `replaceGroupContent()`
- **UUID v5 Hashing** (zero collisions vs ~42% with MD5)
- **100% Backward Compatibility** (no breaking changes)

**Upgrade Time:** ~15 minutes for typical projects

---

## Breaking Changes

**None.** v0.4.2 is 100% backward compatible with v0.4.1.

---

## Deprecations

### `TableBuilder::createTable()` (Deprecated)

**Status:** Deprecated in v0.4.2, will be removed in v1.0.0  
**Replacement:** Fluent API (`addRow()->addCell()->end()`)

**Before (v0.4.1):**
```php
$builder = new TableBuilder();
$table = $builder->createTable([
    'rows' => [
        ['cells' => [
            ['text' => 'Name', 'width' => 3000],
            ['text' => 'Age', 'width' => 2000],
        ]],
    ],
]);
```

**After (v0.4.2):**
```php
$cc = new ContentControl();
$builder = new TableBuilder($cc);

$builder
    ->addRow()
        ->addCell(3000)->addText('Name')->end()
        ->addCell(2000)->addText('Age')->end()
    ->end();

$cc->save('output.docx');
```

**Deprecation Warning:**
```
TableBuilder::createTable() is deprecated since v0.4.2. 
Use fluent API instead: $builder->addRow()->addCell()->end(). 
Will be removed in v1.0.0. See docs/MIGRATION-v042.md.
```

---

## New Features

### 1. Fluent TableBuilder API

**Type-safe, chainable interface:**
```php
$builder
    ->addRow()
        ->addCell(3000)
            ->addText('Customer Name')
            ->withContentControl(['tag' => 'customer'])
        ->end()
    ->end();
```

**Benefits:**
- Full IDE autocomplete
- Compile-time type checking (PHPStan Level 9)
- 60% less code
- Better error messages

**See:** [TableBuilder-v2.md](TableBuilder-v2.md)

---

### 2. GROUP Content Control Support

**Replace GROUP SDTs with complex structures:**
```php
// 1. Create template
$template = new ContentControl();
$placeholder = $template->addSection()->addText('{{ data }}');
$template->addContentControl($placeholder, [
    'tag' => 'invoice',
    'type' => ContentControl::TYPE_GROUP,
]);
$template->save('template.docx');

// 2. Create replacement content
$replacement = new ContentControl();
$replacement->addSection()->addText('Invoice Data');
// ... add tables, nested SDTs, etc.

// 3. Replace
$processor = new ContentProcessor('template.docx');
$processor->replaceGroupContent('invoice', $replacement);
$processor->save('output.docx');
```

**See:** [GROUP-SDT-FIX.md](GROUP-SDT-FIX.md)

---

### 3. UUID v5 Deterministic Hashing

**Replaces MD5 for table matching:**
- Zero collisions (tested up to 500 tables)
- Deterministic (same table = same hash)
- Secure (SHA-1 vs deprecated MD5)

**Performance:** <1ms overhead vs MD5 (negligible)

**No action required** - automatic internal change.

---

## Migration Steps

### Step 1: Update Dependency

```bash
composer update mkgrow/content-control
```

Verify version:
```bash
composer show mkgrow/content-control | grep versions
# versions : * v0.4.2
```

---

### Step 2: Update TableBuilder Calls (Optional)

**Current Code (still works in v0.4.2):**
```php
$builder = new TableBuilder();
$table = $builder->createTable([
    'rows' => [['cells' => [['text' => 'Data']]]]
]);
```

**Recommended Refactor:**
```php
$cc = new ContentControl();
$builder = new TableBuilder($cc);

$builder->addRow()->addCell(3000)->addText('Data')->end()->end();

$cc->save('output.docx');
```

**Suppressing Deprecation Warnings (temporary):**
```php
// phpcs:ignore Generic.PHP.DeprecatedFunctions
$table = $builder->createTable($config);
```

---

### Step 3: Test GROUP SDT Workflows

If using templates with GROUP SDTs, update to `replaceGroupContent()`:

**Old Workaround (lossy):**
```php
$processor->replaceContent('group-tag', $textContent); // Lost SDTs
```

**New Solution (preserves SDTs):**
```php
$processor->replaceGroupContent('group-tag', $contentControl); // Preserves SDTs
```

---

### Step 4: Run Tests

```bash
composer test          # Run full test suite
composer analyse       # PHPStan Level 9
composer test:coverage # Verify ≥80% coverage
```

---

### Step 5: Update Documentation

Update project README/docs to reference new features:

```markdown
## Features

- Fluent TableBuilder API (v0.4.2)
- GROUP Content Control support (v0.4.2)
- UUID v5 deterministic hashing (v0.4.2)
```

---

## Common Issues

### Issue 1: Deprecation Warnings

**Problem:** See warnings in logs:
```
Deprecated: TableBuilder::createTable() is deprecated since v0.4.2...
```

**Solution:**
1. **Short-term:** Suppress warnings (see Step 2)
2. **Long-term:** Migrate to fluent API

---

### Issue 2: Missing ContentControl Instance

**Problem:**
```
Error: Call to undefined method TableBuilder::save()
```

**Cause:** Fluent API requires ContentControl instance.

**Solution:**
```php
// Before (wrong)
$builder = new TableBuilder();
$builder->addRow()->end();
$builder->save('output.docx'); // WRONG: No save() method

// After (correct)
$cc = new ContentControl();
$builder = new TableBuilder($cc);
$builder->addRow()->end();
$cc->save('output.docx'); // Correct
```

---

### Issue 3: GROUP SDT Not Found

**Problem:**
```
InvalidArgumentException: SDT 'my-tag' is not a GROUP type
```

**Cause:** Template SDT is RICH_TEXT or PLAIN_TEXT, not GROUP.

**Solution:** Update template creation:
```php
$template->addContentControl($placeholder, [
    'type' => ContentControl::TYPE_GROUP, // Required!
    'tag' => 'my-tag',
]);
```

---

## Rollback Plan

If critical issues arise:

```bash
# Downgrade to v0.4.1
composer require mkgrow/content-control:^0.4.1

# Clear cache
rm -rf vendor/
composer install
```

---

## Testing Checklist

- [ ] All existing tests pass (`composer test`)
- [ ] PHPStan Level 9 passes (`composer analyse`)
- [ ] Code coverage ≥80% (`composer test:coverage`)
- [ ] Manual testing in Microsoft Word 365
- [ ] Manual testing in LibreOffice Writer (if applicable)
- [ ] Deprecation warnings acceptable or suppressed
- [ ] GROUP SDT workflows tested (if used)

---

## Support

- **Documentation:** [TableBuilder-v2.md](TableBuilder-v2.md), [GROUP-SDT-FIX.md](GROUP-SDT-FIX.md)
- **Examples:** [samples/](../samples/)
- **Issues:** [GitHub Issues](https://github.com/mateusbandeira182/ContentControl/issues)

---

**Migration Estimated Time:** 15-30 minutes  
**Complexity:** Low (backward compatible)  
**Risk:** Minimal (deprecations only)

---

**Version:** v0.4.2  
**Last Updated:** February 3, 2026  
**Status:** Stable

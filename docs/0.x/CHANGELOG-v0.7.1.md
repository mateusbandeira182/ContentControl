# Changelog v0.7.1

## [0.7.1] - 2026-03-11

### Summary

This release focuses on a critical `ContentProcessor` behavior fix for SDT cleanup workflows. Instead of clearing SDT content (which removed visible document data), the processor now unwraps SDTs while preserving content.

Core changes delivered:

1. `removeAllControlContents()` now preserves visible content by unwrapping SDTs.
2. Nested SDTs are processed safely with inner-first order.
3. Invalid/orphan SDTs without `w:sdtContent` are removed explicitly.
4. Unit tests were rewritten to validate correct behavior and edge cases.
5. Component documentation was updated to match runtime behavior.

---

### Fixed

#### ContentProcessor SDT Finalization Bug

`ContentProcessor::removeAllSdtsInFile()` no longer empties `<w:sdtContent>` and leaves empty wrappers behind.

Previous behavior:

```php
while ($sdtContent->firstChild) {
    $sdtContent->removeChild($sdtContent->firstChild);
}
```

Current behavior:

```php
while ($sdtContent->firstChild) {
    $parent->insertBefore($sdtContent->firstChild, $sdtNode);
}

$parent->removeChild($sdtNode);
```

Impact:

- Visible content is preserved in the document body/header/footer.
- `<w:sdt>` wrappers are removed completely.
- XML remains valid after unwrap.

#### SDT Without `w:sdtContent`

Orphan SDTs (missing `w:sdtContent`) are now removed directly and counted.

---

### Changed

#### Nested SDT Processing Order

SDTs are now processed in reverse document order (`array_reverse`) to unwrap inner nodes before outer nodes.

Benefits:

- Stable behavior for nested SDTs.
- Correct count reporting in nested scenarios.
- Reduced risk of invalid node access during DOM manipulation.

#### Documentation Alignment

The following docs now describe unwrap semantics instead of content clearing semantics:

- `src/ContentProcessor.php` PHPDoc
- `docs/contentprocessor.md`

---

### Public API Notes

No public signature changes were introduced in v0.7.1.

Behavioral correction:

- `removeAllControlContents(bool $block = false): int`
  - Now unwraps SDTs and preserves visible content.
  - Still supports optional document protection with `$block = true`.

Example:

```php
$processor = new ContentProcessor('template.docx');
$count = $processor->removeAllControlContents(true);
$processor->save('finalized.docx');
```

---

### Migration Notes

No breaking changes.

If your workflow relied on the old buggy behavior (clearing all content inside SDTs), switch to targeted clearing with `removeContent($tag)` for each field instead of `removeAllControlContents()`.

Before:

```php
$processor->removeAllControlContents(); // previously cleared content (bug)
```

After:

```php
$processor->removeContent('field_1');
$processor->removeContent('field_2');
// Use removeAllControlContents() only when you want unwrap + preserve content
```

---

### Files Modified

| File | Change |
|------|--------|
| `src/ContentProcessor.php` | Reworked `removeAllSdtsInFile()` to unwrap SDTs, added inner-first order, removed orphan SDTs, updated PHPDoc |
| `tests/Helpers/ContentProcessorTestHelper.php` | Added `createDocxWithNestedSdts()` helper |
| `tests/Unit/ContentProcessorAdvancedTest.php` | Updated assertions to verify content preservation and SDT removal |
| `tests/Unit/ContentProcessorProtectionTest.php` | Rewrote bug-validating tests and added edge cases |
| `docs/contentprocessor.md` | Updated method behavior and examples to unwrap semantics |

---

### Testing

| Metric | v0.7.0 | v0.7.1 | Delta |
|--------|--------|--------|-------|
| Total tests | 556 | 559 | +3 |
| Assertions | 1564 | 1583 | +19 |
| Skipped tests | 3 | 3 | 0 |
| PHPStan errors | 0 | 0 | 0 |

New/updated coverage includes:

- Unwrap of single SDT preserving content.
- Unwrap of multiple SDTs preserving content.
- Nested SDT unwrap with inner-first processing.
- SDT removal when `w:sdtContent` is missing.
- SDT removal when `w:sdtContent` is empty.
- XML validity after unwrapping multiple SDTs.

---

### Related Documentation

- Root changelog: [../../CHANGELOG.md](../../CHANGELOG.md)
- Component guide: [../contentprocessor.md](../contentprocessor.md)
- Previous release: [CHANGELOG-v0.7.0.md](CHANGELOG-v0.7.0.md)

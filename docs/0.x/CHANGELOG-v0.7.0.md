# Changelog v0.7.0

## [0.7.0] - 2026-02-18

### ContentProcessor TableBuilder Support & end() Removal

This release delivers three core changes:

1. **ContentProcessor::replaceContent() TableBuilder support** -- SDT-aware table injection into existing DOCX templates via `TableBuilder` instances.
2. **RowBuilder::end() and CellBuilder::end() removal** -- Completes the 18-month deprecation cycle started in v0.5.1.
3. **runLevel cross-validation** -- Prevents invalid `runLevel=true` usage on non-Text elements.

---

### Added

#### ContentProcessor::replaceContent() with TableBuilder (Path E)

`replaceContent()` signature widened to accept `TableBuilder` as the replacement value:

```php
public function replaceContent(
    string $tag,
    string|AbstractElement|TableBuilder $value
): bool
```

**New private method:**
- `insertTableBuilderContent(DOMDocument, DOMElement, TableBuilder): void` -- Calls `serializeWithSdts()`, creates DOM fragment, appends to SDT content.

**Routing priority** (in `replaceContent`):
1. `TableBuilder` → `insertTableBuilderContent()`
2. `string` → `insertTextContent()`
3. `AbstractElement` → `insertElementContent()`

#### TableBuilder::serializeWithSdts()

New public method that serializes the table with all registered SDTs:

```php
public function serializeWithSdts(): string
```

- Saves to temporary file via `ContentControl::save()`
- Extracts table XML with SDTs via `extractTableXmlWithSdts()`
- Cleans up temp file in `finally` block
- Throws `ContentControlException` if no table exists

#### runLevel Cross-Validation in SDTInjector

Guard clause in `processElement()` validates that `runLevel=true` is only used with `Text` elements:

```php
if ($config->runLevel && !($element instanceof \PhpOffice\PhpWord\Element\Text)) {
    throw new \InvalidArgumentException(
        'runLevel SDT is only supported for Text elements. Got: ' . $elementClass
    );
}
```

Affected elements that now throw: `TextRun`, `Table`, `Image`, `Title`, `Cell`.

---

### Changed

- **`ContentProcessor::replaceContent()`** -- Signature widened from `string|AbstractElement` to `string|AbstractElement|TableBuilder`
- **PHPStan baseline** -- Updated from 2 entries to 4 entries:
  - Removed: `RowBuilder::end()` result-unused (12 occurrences)
  - Added: `CellBuilder` constructor unused `$parent` parameter (1)
  - Added: `method_exists` regression guards for `end()` removal (2)
  - Kept: Pest `group()` dynamic method (4 occurrences)

---

### Removed

#### [BREAKING] RowBuilder::end()

Deprecated since v0.5.1. The `end()` method and `$endWarned` static property have been removed. The `resetDeprecationFlags()` method no longer resets `$endWarned`.

**Migration:**
```php
// ❌ Before
$builder->addRow()
    ->addCell(3000)->addText('Value')->end()
    ->end();

// ✅ After
$row = $builder->addRow();
$row->addCell(3000)->addText('Value');
```

See [migration guide](../migration/v0.6.0-to-v0.7.0.md) for detailed instructions.

#### [BREAKING] CellBuilder::end()

Deprecated since v0.5.1. The `end()` method, `$endWarned` static property, and `$parent` private property have been removed. The `resetDeprecationFlags()` method now resets 3 flags instead of 4. Constructor parameter `$parent` is kept for backward compatibility but unused.

#### TableBuilder::generateTableHash() (Private)

Dead code since v0.4.2 (replaced by UUID v5 in `ElementIdentifier`). Removed with no public API impact. This was a private method used internally for MD5-based table identification.

---

### Files Modified

| File | Change |
|------|--------|
| `src/Bridge/RowBuilder.php` | Removed `end()`, `$endWarned`, updated `resetDeprecationFlags()`, updated docblock |
| `src/Bridge/CellBuilder.php` | Removed `end()`, `$endWarned`, `$parent`, updated `resetDeprecationFlags()`, updated docblock |
| `src/Bridge/TableBuilder.php` | Removed `generateTableHash()`, added `serializeWithSdts()` |
| `src/ContentProcessor.php` | Added `TableBuilder` import, widened `replaceContent()` signature, added routing, added `insertTableBuilderContent()` |
| `src/SDTInjector.php` | Added `runLevel` cross-validation guard in `processElement()` |
| `phpstan-baseline.neon` | Removed RowBuilder::end() entry, added 3 new entries |

### Testing

| Metric | v0.6.0 | v0.7.0 | Delta |
|--------|--------|--------|-------|
| Total tests | 536 | 556 | +20 |
| Assertions | ~1500 | 1564 | +64 |
| PHPStan errors | 0 | 0 | — |
| Baseline entries | 2 | 4 | +2 |

**New test files:**
- `tests/Unit/SDTInjectorRunLevelValidationTest.php` (4 tests)
- `tests/Unit/TableBuilderSerializeTest.php` (7 tests)
- `tests/Feature/ContentProcessorTableBuilderTest.php` (11 tests)

**Modified test files:**
- `tests/Unit/Bridge/RowBuilderTest.php` -- Removed `end()` tests, added `method_exists` guard
- `tests/Unit/Bridge/CellBuilderTest.php` -- Removed `end()` tests, added `method_exists` guard
- `tests/Feature/FluentTableBuilderTest.php` -- Replaced 43 `->end()` calls with variable pattern
- `tests/Feature/TableBuilderIntegrationTest.php` -- Removed 2 `->end()` calls
- `tests/Feature/NestedSDTDetectionTest.php` -- Removed 2 `->end()` calls
- `tests/Unit/TableBuilderSetStylesTest.php` -- Removed 2 `->end()` calls
- `tests/Unit/TableBuilderPrivateMethodsTest.php` -- Removed 4 `generateTableHash()` tests
- `tests/Unit/TableBuilderInjectionTest.php` -- Removed 3 `generateTableHash()` tests

**Updated samples:**
- `samples/02-basic-table.php` -- Variable pattern
- `samples/04-table-with-controls.php` -- Variable pattern
- `samples/05-template-injection.php` -- Variable pattern
- `samples/06-multi-element-document.php` -- Variable pattern
- `samples/08-group-sdt-replacement.php` -- Variable pattern
- `samples/README.md` -- Updated API pattern reference

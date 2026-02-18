# Changelog v0.6.0

## [0.6.0] - 2026-02-16

### TableBuilder v2 -- Run-Level SDT Wrapping and Direct PHPWord Table API

This release introduces three core capabilities:

1. **Run-level SDT wrapping** (`CT_SdtContentRun`, ECMA-376 Part 4 S17.5.2.30) -- Individual `<w:r>` elements inside `<w:p>` can be wrapped with `<w:sdt>`.
2. **Native PHPWord Table constructor support** -- `TableBuilder` accepts a `Table` object directly, eliminating the two-step `setTable()` ceremony.
3. **Functional `addContentControl()` on TableBuilder** -- Replaces the always-throwing stub with delegation to `ContentControl::addContentControl()` for any `AbstractElement`.

Additionally, the release formally deprecates the fluent builder API (`RowBuilder`, `CellBuilder`, `TableBuilder::addRow()`) in favor of the direct PHPWord Table API combined with `addContentControl()`.

---

### Added

#### Run-Level SDT Wrapping (CT_SdtContentRun)

**New SDT injection level** targeting individual text runs (`<w:r>`) inside paragraphs (`<w:p>`), conforming to ECMA-376 Part 4 S17.5.2.30.

**SDTConfig:**
- `SDTConfig::$runLevel` -- New `readonly bool` property (7th constructor parameter, default `false`)
- `SDTConfig::withRunLevel(bool): self` -- Immutable wither method following `withInlineLevel()` pattern
- `SDTConfig::fromArray()` -- Accepts `runLevel` key in options array
- All `with*()` methods propagate `runLevel` to new instances

**ElementLocator:**
- `findElementInDOM()` -- 6th parameter `bool $runLevel = false` (backward compatible)
- `findRunByTextContent(Text, string): ?DOMElement` -- Body-level run location via XPath with `normalize-space()` for whitespace tolerance. Fallback to first unprocessed run when text match fails.
- `findRunInCell(Text, string): ?DOMElement` -- Cell-scoped variant for `inlineLevel + runLevel` combination. XPath: `//w:tbl//w:tc/w:p/w:r[...][not(ancestor::w:sdtContent)]`
- `escapeXPathString(string): string` -- XPath injection prevention utility. Handles single quotes, double quotes, and mixed (via `concat()` technique).

**SDTInjector:**
- `processRunLevelSDT(DOMElement, SDTConfig): void` -- Validates target is `<w:r>` with `<w:p>` parent. Throws `RuntimeException` for invalid targets.
- `wrapRunInline(DOMElement, SDTConfig): void` -- DOM manipulation following the established 7-step pattern:
  1. Get ownerDocument and parent
  2. Detect namespace
  3. Create `<w:sdt>` via `createElementNS()`
  4. Create `<w:sdtPr>` via `createSdtProperties()` (reuse, zero duplication)
  5. Create `<w:sdtContent>` via `createElementNS()`
  6. `insertBefore(sdt, run)` + `appendChild(run into sdtContent)` (MOVE, not clone)
  7. `markElementAsProcessed()`

**Generated XML structure:**
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

**Routing priority in `processElement()`:**
1. `runLevel === true` -- `processRunLevelSDT()` (highest)
2. `inlineLevel === true` -- `processInlineLevelSDT()`
3. Default -- `processBlockLevelSDT()`

When both `runLevel` and `inlineLevel` are `true`, `runLevel` takes routing precedence. `inlineLevel` scopes element discovery to table cells; `runLevel` targets `<w:r>` elements within that scope.

#### TableBuilder v2 Constructor

`TableBuilder` constructor now accepts a PHPWord `Table` object directly:

```php
// v0.6.0: Pass Table directly
$table = $section->addTable(['borderSize' => 6]);
$builder = new TableBuilder($table);

// Existing: Pass ContentControl (preserved)
$builder = new TableBuilder($cc);

// Existing: Default (preserved)
$builder = new TableBuilder();
```

**Constructor signature:** `__construct(Table|ContentControl|null $source = null)`

- `Table` instance: stores table, creates new `ContentControl` internally
- `ContentControl` instance: preserves exact instance (existing behavior)
- `null`: creates default `ContentControl` (existing behavior)

#### Functional `addContentControl()` on TableBuilder

Replaces the always-throwing stub from v0.5.1 with actual delegation:

```php
$builder = new TableBuilder($table);
$builder->addContentControl($textElement, [
    'tag' => 'field-name',
    'alias' => 'Field Name',
    'runLevel' => true,
    'inlineLevel' => true,
]);
```

**New signature:** `addContentControl(AbstractElement $element, array $config = []): self`

Delegates internally to `ContentControl::addContentControl()`. Returns `$this` for method chaining.

---

### Changed

- `TableBuilder::addContentControl()` signature: `(array) -> (AbstractElement, array)` [**BREAKING**]
  - Note: Old signature always threw `ContentControlException`; no user code was functionally affected.
- `TableBuilder` constructor: `(?ContentControl) -> (Table|ContentControl|null)` [**BREAKING**]
  - All existing calling patterns (`null`, `ContentControl`) are backward compatible.

---

### Deprecated

All deprecations target removal in **v0.8.0**. Each emits `E_USER_DEPRECATED` with a static flag to prevent log spam (single warning per script execution).

| Method | Replacement |
|--------|-------------|
| `TableBuilder::addRow()` | Direct PHPWord `Table::addRow()` + `addContentControl()` |
| `RowBuilder::addCell()` | Direct PHPWord `Row::addCell()` |
| `CellBuilder::withContentControl()` | `TableBuilder::addContentControl()` |
| `CellBuilder::addText()` | Direct PHPWord `Cell::addText()` |
| `CellBuilder::addImage()` | Direct PHPWord `Cell::addImage()` |

Class-level `@deprecated` annotations added to `RowBuilder` and `CellBuilder`.

See [Migration Guide](../migration/v0.5.2-to-v0.6.0.md) for before/after code examples.

---

### Files Modified

| File | Type | Changes |
|------|------|---------|
| `src/SDTConfig.php` | Modified | +`runLevel` property, `fromArray()`, all `with*()` methods, +`withRunLevel()` |
| `src/ElementLocator.php` | Modified | +6th param, +`findRunByTextContent()`, +`findRunInCell()`, +`escapeXPathString()` |
| `src/SDTInjector.php` | Modified | +routing branch, +`processRunLevelSDT()`, +`wrapRunInline()` |
| `src/Bridge/TableBuilder.php` | Modified | Constructor union type, `addContentControl()` redesign, `addRow()` deprecation |
| `src/Bridge/RowBuilder.php` | Modified | `addCell()` deprecation, class-level `@deprecated` |
| `src/Bridge/CellBuilder.php` | Modified | 3 method deprecations, `end()` static flag refactor, class-level `@deprecated` |
| `samples/09-run-level-sdt.php` | New | Run-level SDT demonstration with Table constructor API |
| `docs/migration/v0.5.2-to-v0.6.0.md` | New | Migration guide from fluent to direct API |

---

### Testing

**51 new tests** across 7 new test files:

| File | Tests | Type |
|------|-------|------|
| `SDTConfigRunLevelTest.php` | 9 | Unit |
| `ElementLocatorRunLevelTest.php` | 10 | Unit |
| `SDTInjectorRunLevelTest.php` | 7 | Unit |
| `TableBuilderConstructorTest.php` | 6 | Unit |
| `DeprecationTest.php` | 6 | Feature |
| `RunLevelSDTTest.php` | 8 | Feature |
| `TableBuilderV2Test.php` | 5 | Feature |

**3 existing test files** modified to reflect `addContentControl()` API change (from throwing-behavior to delegation-behavior assertions):
- `FluentTableBuilderTest.php`
- `NestedSDTDetectionTest.php`
- `TableBuilderSetStylesTest.php`

**Totals:** 535 tests, 1533 assertions, 118 deprecated, 1 risky, 3 skipped.

**Quality:** PHPStan Level 9: 0 errors. Coverage: >= 82%.

---

### Design Decisions

1. **`runLevel` and `inlineLevel` are NOT mutually exclusive.** When both are `true`, `runLevel` takes precedence in injection routing. `inlineLevel` scopes discovery to cells; `runLevel` targets runs within that scope.
2. **No new classes created.** All changes are additive modifications to existing classes, preserving the "8 core classes" architecture.
3. **PHPWord `Table` in constructor is syntactic sugar.** The same result can be achieved via `setTable()`. Constructor overloading is a convenience.
4. **Deprecation window: v0.6.0 -> v0.8.0 removal.** 2 minor versions of deprecation before removal, consistent with project conventions.

### OOXML Reference

- **CT_SdtContentRun:** ECMA-376 Part 4 S17.5.2.30 (Run-level SDT content model)
- **CT_SdtContentBlock:** ISO/IEC 29500-1:2016 S17.5.2.29 (Block-level, existing)
- **CT_SdtContentCell:** ISO/IEC 29500-1:2016 S17.5.2.28 (Cell-level, existing)

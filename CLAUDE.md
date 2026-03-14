# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
composer test              # Run all tests (Pest)
composer test:unit         # Unit tests only
composer test:feature      # Feature tests only
composer test:coverage     # Tests + coverage (enforces 80% minimum)
composer analyse           # PHPStan level 9 static analysis
composer check             # analyse + test
composer ci                # analyse + test:coverage (full CI pipeline)
```

To run a single test file:
```bash
./vendor/bin/pest tests/Unit/SDTConfigTest.php
./vendor/bin/pest tests/Feature/RunLevelSDTTest.php
```

To run tests matching a description:
```bash
./vendor/bin/pest --filter "description substring"
```

> PHPStan requires PHP 8.3+ at runtime. CI matrix covers PHP 8.2, 8.3, 8.4 on Ubuntu.

## Architecture

PHP library extending PHPOffice/PHPWord to inject Word Content Controls (Structured Document Tags / SDTs) into `.docx` files, conforming to ISO/IEC 29500-1:2016 §17.5.2. Namespace: `MkGrow\ContentControl`.

**Data flow:**
```
PHPWord objects
  → SDTRegistry (registers elements + configs, assigns unique 8-digit IDs)
  → SDTInjector (opens DOCX ZIP, loads XML as DOMDocument)
  → ElementLocator (finds DOM nodes via XPath)
  → Wraps nodes with <w:sdt> XML
  → ZIP updated → final .docx
```

**SDT injection routing** (priority in `SDTInjector::processElement()`): `runLevel` → `inlineLevel` → block-level (default).

| Component | Role |
|---|---|
| `ContentControl` | Facade — wraps `PhpWord`, exposes `addContentControl()` / `save()`. Delegates to `SDTInjector`. |
| `ContentProcessor` | Opens existing `.docx`, locates SDTs by tag via XPath, replaces/appends content. |
| `SDTInjector` | Core service — opens DOCX ZIP, loads DOM, locates elements, wraps nodes with `<w:sdt>`. |
| `SDTConfig` | Immutable value object (`readonly` promoted props + `with*()` mutators). Validated in constructor. |
| `SDTRegistry` | Manages unique 8-digit IDs (10M–99M range), maps `(element, SDTConfig)` tuples. |
| `ElementLocator` | Finds PHPWord elements in DOM via XPath. Strategies: content hash or type + registration order. |
| `ElementIdentifier` | Generates unique markers (`sdt-marker-{objectId}-{hash8}`) and MD5 content hashes. Static cache per `spl_object_id`. |
| `Bridge/TableBuilder` | Bridge between PHPWord tables and ContentControl SDT registration. |

**Exception hierarchy:** `ContentControlException` (extends `\RuntimeException`) → `ZipArchiveException`, `DocumentNotFoundException`, `TemporaryFileException`.

## Code Conventions

- `declare(strict_types=1)` in every file (src and tests).
- All classes are `final` — composition over inheritance, no class hierarchy.
- `private` visibility by default; `public` only when necessary.
- PHPStan level 9 + `phpstan-strict-rules`. Zero errors outside `phpstan-baseline.neon`.
- Validation is fail-fast in constructors. Reject XML reserved chars (`< > & " '`) in alias/tag fields.
- Use `instanceof` for type checks, never string class comparison. Use `=== 1` for `preg_match()`.
- **`SDTInjector` exception:** Uses FQCNs for PHPWord types (e.g. `\PhpOffice\PhpWord\Element\Text`), no `use` imports. All other `src/` files use normal `use` imports.
- PHPDoc required on all public methods with `@since`, `@throws`, and OOXML spec references (e.g. §17.5.2.x) where applicable.
- Exception messages prefixed with class name: `"SDTConfig: Invalid ID"`.
- Conventional Commits: `feat(scope): description`, `fix(scope): description`, `test(scope): description`.

## Deprecation Pattern

Use a private static bool flag to emit `E_USER_DEPRECATED` only once per process. Include a public static `resetDeprecationFlags()` method (marked `@internal @codeCoverageIgnore`) for test cleanup:

```php
private static bool $methodWarned = false;

public function deprecatedMethod(): void {
    if (!self::$methodWarned) {
        trigger_error(
            'ClassName::deprecatedMethod() is deprecated since v0.6.0 and will be removed in v0.8.0. '
            . 'Use newMethod() instead. See docs/migration/v0.5.2-to-v0.6.0.md',
            E_USER_DEPRECATED
        );
        self::$methodWarned = true;
    }
}
```

## Testing Patterns (Pest v3)

- **Structure:** `tests/Unit/` (isolated class tests) and `tests/Feature/` (end-to-end workflows).
- **Style:** `describe()` / `test()` / `expect()` — functional Pest syntax, not PHPUnit classes.
- **Custom expectations** defined in `tests/Pest.php`: `toBeValidXml()`, `toHaveXmlElement()`, `toHaveXmlAttribute()`.
- **Global helpers** in `tests/Pest.php`: `createSection()`, `createFullContentControl()`, `assertValidSdtStructure()`, `safeUnlink()` (retry with exponential backoff for Windows file locking).
- **Fixtures:** `tests/Fixtures/SampleElements.php` — static factory methods like `SampleElements::createSectionWithText()`.
- **ContentProcessor tests:** Use `tests/Helpers/ContentProcessorTestHelper.php` to build minimal DOCX ZIPs from raw XML.
- **Exception testing:** `expect(fn() => ...)->toThrow(ExceptionClass::class, 'message substring')`.
- **Temp file cleanup:** Use `safeUnlink()` (not `unlink()`) to handle Windows file locking.

## Security

Use `ElementLocator::escapeXPathString()` when embedding user text in XPath queries — it handles single/double quotes and mixed strings via `concat()` to prevent XPath injection.

## Planning Artifacts

Plans, backlogs, specs, and contexts go in `.local/` subdirectories with timestamp naming (`YYYYMMDD-HHMMSS`). Context files use JSON format.

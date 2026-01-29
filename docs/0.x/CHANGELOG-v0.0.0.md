# Changelog

All notable changes to ContentControl will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.0.0] - 2026-01-28

### Added

- **Proxy Pattern Architecture** - Unified API encapsulating PhpWord with automatic SDT management via `ContentControl` class
- **SDT Injection System** - `SDTInjector` service layer for XML manipulation in DOCX files using DOM-based inline wrapping (v3.0)
- **Unique ID Generation** - `SDTRegistry` with automatic 8-digit ID generation and collision prevention (sequential fallback)
- **Type-Safe Configuration** - `SDTConfig` immutable value object with readonly properties for Content Control settings
- **Element Locator** - `ElementLocator` for DOM-based element identification using XPath queries (v3.0)
- **Comprehensive Testing** - 227 Pest tests (80%+ coverage) split into Unit and Feature categories
- **PHPStan Level 9** - Strict static analysis with Level 9 compliance and strict rules enabled
- **Exception Hierarchy** - `ContentControlException` base with specialized exceptions for ZIP, document, and file errors
- **Content Control Types** - Support for `TYPE_RICH_TEXT`, `TYPE_PLAIN_TEXT`, `TYPE_PICTURE`, `TYPE_GROUP`
- **Lock Types** - Support for `LOCK_NONE`, `LOCK_SDT_LOCKED`, `LOCK_CONTENT_LOCKED`, `LOCK_UNLOCKED`
- **Supported Elements** - Text, TextRun, Table, Cell wrapping with automatic SDT injection
- **Custom Pest Expectations** - `toBeValidXml()`, `toHaveXmlElement()`, `toHaveXmlAttribute()` helpers
- **ID Validator** - `IDValidator` class for 8-digit ID validation and random generation
- **Element Identifier** - `ElementIdentifier` for generating unique markers for PHPWord elements (v3.0)
- **Assert Helper** - `Assert` class with `notNull()` for PHPStan type narrowing (replaces native `assert()`)
- **Sample Fixtures** - `SampleElements` class with reusable test data generators

### Technical Details

**Architecture Highlights:**
- **Proxy Pattern:** `ContentControl` encapsulates `PhpWord` + `SDTRegistry` for unified API
- **DOM Manipulation (v3.0):** `SDTInjector` uses inline wrapping to eliminate content duplication
- **Depth-First Processing:** Elements sorted by depth (Cell before Table) to prevent re-wrapping
- **Dual XML Strategy:** DOMDocument for SDT structure + PHPWord XMLWriter for content serialization
- **Zero Duplication:** v3.0 eliminates content repetition by moving nodes instead of copying

**Code Quality:**
- PHPStan Level 9 with strict rules (`strictRules: true`)
- `declare(strict_types=1)` in all source files
- 227 tests covering Unit and Feature scenarios
- 80%+ code coverage (verified via Clover XML)

**Compliance:**
- ISO/IEC 29500-1:2016 §17.5.2 (Structured Document Tags)
- WordprocessingML namespace: `http://schemas.openxmlformats.org/wordprocessingml/2006/main`
- XML security: No network access during parsing (`LIBXML_NONET`)

### Installation

```bash
composer require mkgrow/content-control:0.0.0
```

**Requirements:**
- PHP >= 8.2
- PHPOffice/PHPWord ^1.4

### Breaking Changes from Pre-Release

This is the first public release. Internal v1.x and v2.x APIs are deprecated:
- ❌ `IOFactory::saveWithContentControls()` (v1.x) - Use `ContentControl::save()` instead
- ❌ Extending `AbstractContainer` (v1.x) - Use Proxy Pattern with `addContentControl()`
- ❌ Manual ID management - IDs now auto-generated via `SDTRegistry`

### Known Limitations

- Section wrapping not supported (wrap child elements instead)
- Windows temporary file cleanup may require retry logic (handled internally)
- Namespace warnings during XML fragment parsing (expected and filtered)

### Links

- [GitHub Repository](https://github.com/mateusbandeira182/ContentControl)
- [Packagist Package](https://packagist.org/packages/mkgrow/content-control)
- [Documentation](https://github.com/mateusbandeira182/ContentControl/tree/main/docs)

---

[0.0.0]: https://github.com/mateusbandeira182/ContentControl/releases/tag/v0.0.0

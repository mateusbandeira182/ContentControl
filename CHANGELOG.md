# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-01-28

### 🎉 Major Rewrite - Proxy Pattern Architecture

This is a **BREAKING CHANGE** release with complete API redesign.

### ✨ Added

- **Proxy Pattern**: ContentControl now encapsulates PhpWord instead of extending AbstractContainer
  - Single unified class for all operations
  - Automatic ID management via SDTRegistry
  - Cleaner API with fluent interface
- **SDTConfig**: Immutable value object for Content Control configuration
  - `readonly` properties (PHP 8.2+)
  - Factory method `fromArray()`
  - Immutability helpers: `withId()`, `withAlias()`, `withTag()`
- **SDTRegistry**: Centralized unique ID generator
  - Automatic 8-digit ID generation (10000000-99999999)
  - Duplicate detection (element and ID)
  - 0% collision rate in 10K IDs
  - O(1) ID usage check
- **SDTInjector**: Service layer for XML injection into DOCX files
  - Direct ZIP manipulation for SDT insertion
  - Injects before `</w:body>` in word/document.xml
  - Handles PhpWord element serialization
- **Exception hierarchy** (2.0.0):
  - `ContentControlException` (base)
  - `ZipArchiveException` (ZIP errors with code mapping)
  - `DocumentNotFoundException` (missing word/document.xml)
  - `TemporaryFileException` (cleanup failures)
- **ContentControl::save()**: New unified save method
  - Orchestrates PhpWord Writer + SDTInjector
  - Automatic temp file cleanup with retry mechanism
  - Directory validation
- **ContentControl delegation methods**:
  - `addSection()`, `getDocInfo()`, `getSettings()`
  - `addFontStyle()`, `addParagraphStyle()`, `addTableStyle()`, `addTitleStyle()`
  - `getSections()`, `getPhpWord()`, `getSDTRegistry()`

### 🔄 Changed

- **BREAKING**: Constructor signature changed
  - **OLD**: `new ContentControl($section, ['alias' => '...'])`
  - **NEW**: `$cc = new ContentControl(); $cc->addContentControl($section, [...])`
- **BREAKING**: `getXml()` method removed
  - XML generation now handled internally by SDTInjector
  - Users interact only with `save()` method
- **BREAKING**: No longer extends `AbstractContainer`
  - Uses composition instead of inheritance
  - Maintains compatibility with PhpWord via delegation

### ❌ Removed

- **IOFactory class**: Completely removed
  - `IOFactory::createWriter()` → use `PHPWordIOFactory::createWriter()` directly
  - `IOFactory::saveWithContentControls()` → use `ContentControl::save()`
  - `IOFactory::registerCustomWriters()` → deprecated in v1.x, now removed
- **Writer\Word2007\Element\ContentControl**: Removed (not used in Proxy Pattern)
- **Test files**: Removed obsolete tests (PropertiesTest, ValidationTest, Writer tests)

### 📚 Documentation

- Complete README.md rewrite for v2.0 API
- New samples/ContentControl_Sample.php with 9 comprehensive examples
- Updated .github/copilot-instructions.md with v2.0 architecture

### 🧪 Testing

- **116 tests passing** (240 assertions)
- **PHPStan Level 9**: 0 errors in src/
- New test files:
  - `SDTConfigTest.php` (41 tests)
  - `SDTRegistryTest.php` (27 tests)
  - `SDTInjectorTest.php` (15 tests)

### 🏗️ Architecture

- **Patterns**: Proxy, Value Object, Registry, Service Layer
- **Principles**: SOLID, immutability, type safety
- **Performance**: <200ms for 1K element registration

---

## [Unreleased]

### Added
- **Exception hierarchy**: Custom exception classes for better error handling
  - `ContentControlException`: Base exception for all library errors
  - `ZipArchiveException`: ZIP operation failures with detailed error mapping
  - `DocumentNotFoundException`: Missing word/document.xml in DOCX archive
  - `TemporaryFileException`: Temporary file cleanup failures
- **Assert helper class**: `Assert::notNull()` for PHPStan Level 9 type narrowing
- **ID validation**: Content Control IDs now validated as 8-digit numbers (10000000-99999999)
  - Accepts both string and int formats
  - Automatic generation when not provided
  - Validates format and range with descriptive error messages
- **XML security validation**: Prevents XML injection attacks
  - Alias validation: Rejects XML reserved characters (`<`, `>`, `&`, `"`, `'`)
  - Tag validation: Rejects XML reserved characters
  - Control character blocking in aliases (0x00-0x1F, 0x7F-0x9F)
- **Writer caching**: Class existence checks cached in static property to improve performance
- **Retry mechanism**: `IOFactory::unlinkWithRetry()` for Windows file lock handling (3 attempts with 100ms delay)
- **Comprehensive test coverage**: ~25 new test cases for validation edge cases
  - ID validation tests (format, range, type checking)
  - XML security tests (reserved character rejection)
  - Exception handling tests for IOFactory operations

### Changed
- **BREAKING**: `IOFactory::saveWithContentControls()` return type changed from `bool` to `void`
  - Now throws exceptions instead of returning `false` on errors
  - Throws `RuntimeException` for directory permission issues
  - Throws `ZipArchiveException` for ZIP operation failures
  - Throws `DocumentNotFoundException` when word/document.xml is missing
  - Throws `TemporaryFileException` when cleanup fails
- **PHPStan Level 9 compliance**: All source code passes strict type checking
  - Fixed `preg_match()` return type comparisons (use `=== 1` instead of truthy checks)
  - Added type assertions for DOM operations
  - Replaced string-based element type checking with `instanceof` checks
  - Improved `needsParagraphWrapper()` to use instanceof for type safety
- **libxml error filtering**: `getXml()` now filters errors by severity (LIBXML_ERR_ERROR and above)
  - Namespace warnings are expected and ignored
  - Only actual errors trigger exceptions
- **Exception message prefixing**: All validation errors prefixed with "ContentControl: " for clarity

### Deprecated
- `IOFactory::registerCustomWriters()`: Use `IOFactory::saveWithContentControls()` instead
  - Triggers `E_USER_DEPRECATED` when called
  - Placeholder method with no functionality
  - Will be removed in future major version

### Fixed
- **libxml warnings**: XML fragment injection no longer produces warnings for missing namespace declarations
  - Namespace inherited from `<w:sdt>` root element
  - Error handling improved with severity filtering
- **Type safety**: All preg_match comparisons use strict equality (`=== 1`)
- **Memory**: Writer class cache prevents repeated `class_exists()` calls
- **Windows compatibility**: Temporary file cleanup now handles file locks with retry mechanism

## [1.0.0] - Previous Release

Initial release with basic Content Control support.

[Unreleased]: https://github.com/mateusbandeira182/ContentControl/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/mateusbandeira182/ContentControl/releases/tag/v1.0.0

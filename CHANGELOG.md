# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

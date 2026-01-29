# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-01-29

### Added

**Title Element Support**
- Title elements (Heading1-9 and Title depth=0) can now be wrapped with Content Controls
- XPath locator distinguishes Titles from regular Text via `w:pStyle` attribute
- Hash generation includes heading depth and style name to prevent collisions with Text elements
- Bookmark references preserved for Table of Contents compatibility
- Supports all 10 depth levels (0=Title, 1=Heading1, ..., 9=Heading9)
- `ElementLocator::findTitleByDepth()` method for precise Title element location using Reflection

**Image Element Support**
- Inline and floating images can be wrapped with Content Controls
- Automatic detection of `w:pict` elements using VML namespaces (not DrawingML as initially planned)
- Hash generation based on image dimensions (width/height) and source path
- Relationship IDs (rId) preserved for image references in `word/_rels/document.xml.rels`
- `ElementLocator::findImageByOrder()` method with VML namespace support

### Changed

**ElementLocator Enhancements**
- Added VML namespace constants (`VML_NS`, `OFFICE_NS`) for image support
- Registered additional namespaces: `v` (VML), `o` (Office)
- Modified `findByTypeAndOrder()` to route Title and Image to specialized finders
- Updated `hashDOMElement()` to include `w:pStyle` for Title differentiation
- Updated `hashDOMElement()` to include `v:shape` style attributes for Image differentiation
- Modified `validateMatch()` to accept Title and Image as `w:p` node types

**ElementIdentifier Enhancements**
- Modified `serializeForHash()` to include Title depth and style name (using Reflection)
- Modified `serializeForHash()` to include Image dimensions via `getStyle()->getWidth()/getHeight()`
- Hash differentiation prevents Title/Text and Image/Text collisions

**Supported Elements** (updated list)
- Text ✅ (v0.0.0)
- TextRun ✅ (v0.0.0)
- Table ✅ (v0.0.0)
- Cell ✅ (v0.0.0)
- Title ✅ (v0.1.0) **NEW**
- Image ✅ (v0.1.0) **NEW**

### 🧪 Testing

- **27 new tests added** (247 total, up from 227)
  - 14 unit tests for Title support (ElementLocatorTitleTest)
  - 7 unit tests for Image support (ElementLocatorImageTest)
  - 6 feature tests for integration (TitleImageIntegrationTest)
- **586 assertions** passing (all green)
- Code coverage maintained at **80%+**
- PHPStan Level 9 strict mode: **0 errors**
- Performance: 247 tests complete in **<4 seconds**

### Documentation

- README updated with Title and Image in "Supported Elements" table
- New sample file: `samples/title_image_examples.php`
  - Example 1: Hierarchical titles (depth 0-3)
  - Example 2: Images with TYPE_PICTURE
  - Example 3: Mixed document (Titles + Images + Text)
  - Example 4: TOC compatibility demonstration
- PHPDoc blocks added for all new methods (`@since 0.1.0`)
- Copilot instructions updated with Title/Image implementation notes

### Known Limitations

- **TOC (Table of Contents)** elements not supported due to complex field structure spanning multiple paragraphs
  - **Impact**: `$cc->addContentControl($tocElement, [...])` not available
  - **Workaround**: Wrap individual Title elements instead - TOC will still generate correctly and bookmarks are preserved
- **Watermark images** not supported (different OOXML structure incompatible with SDT wrapping)
  - **Impact**: Images with `isWatermark=true` would throw `ContentControlException` (validation not yet implemented)
- **Image positioning**: Some floating image positioning styles may shift post-wrapping
  - **Impact**: Manual adjustment may be needed in Word after document generation
  - **Affected styles**: Absolute positioning with custom anchors

### 🔬 Technical Details

**Image Detection Strategy** (VML vs DrawingML):
- PHPWord generates images as `<w:pict>` with VML (`v:shape`, `v:imagedata`)
- Initial plan expected DrawingML (`w:drawing`) but actual output uses legacy VML
- XPath query: `//w:body//w:r/w:pict[not(ancestor::w:sdtContent)][1]`
- Returns parent `<w:p>` element containing the image

**Title Detection Strategy**:
- Uses Reflection to access private `$depth` property of `Title` element
- Maps depth to style name: `0 → "Title"`, `1 → "Heading1"`, `2 → "Heading2"`, etc.
- XPath query: `//w:body/w:p[w:pPr/w:pStyle[@w:val="Heading{depth}"]][not(ancestor::w:sdtContent)][1]`
- Bookmarks (`w:bookmarkStart`, `w:bookmarkEnd`) remain inside `<w:sdtContent>` preserving TOC functionality

## [0.0.0] - 2026-01-28

### First Public Release - Proxy Pattern Architecture

This is the first public release (v0.0.0 baseline for public versioning).

### Added

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

### Changed

- **BREAKING**: Constructor signature changed
  - **OLD**: `new ContentControl($section, ['alias' => '...'])`
  - **NEW**: `$cc = new ContentControl(); $cc->addContentControl($section, [...])`
- **BREAKING**: `getXml()` method removed
  - XML generation now handled internally by SDTInjector
  - Users interact only with `save()` method
- **BREAKING**: No longer extends `AbstractContainer`
  - Uses composition instead of inheritance
  - Maintains compatibility with PhpWord via delegation

### Removed

- **IOFactory class**: Completely removed
  - `IOFactory::createWriter()` → use `PHPWordIOFactory::createWriter()` directly
  - `IOFactory::saveWithContentControls()` → use `ContentControl::save()`
  - `IOFactory::registerCustomWriters()` → deprecated in v1.x, now removed
- **Writer\Word2007\Element\ContentControl**: Removed (not used in Proxy Pattern)
- **Test files**: Removed obsolete tests (PropertiesTest, ValidationTest, Writer tests)

### Documentation

- Complete README.md rewrite for v2.0 API
- New samples/ContentControl_Sample.php with 9 comprehensive examples
- Updated .github/copilot-instructions.md with v2.0 architecture

### Testing

- **116 tests passing** (240 assertions)
- **PHPStan Level 9**: 0 errors in src/
- New test files:
  - `SDTConfigTest.php` (41 tests)
  - `SDTRegistryTest.php` (27 tests)
  - `SDTInjectorTest.php` (15 tests)

### Architecture

- **Patterns**: Proxy, Value Object, Registry, Service Layer
- **Principles**: SOLID, immutability, type safety
- **Performance**: <200ms for 1K element registration

---

## [Unreleased] - 2026-01-28

### Major Enhancement - Zero Content Duplication

This release completely eliminates content duplication when wrapping elements with Content Controls (SDTs). The implementation uses in-place DOM manipulation instead of XML string concatenation.

### Added

- **ElementIdentifier**: Unique marker generation for PHPWord elements
  - SHA-256 based markers combining element type + content + position
  - Collision-resistant identification system
  - Supports Table, Cell, Text, TextRun, Image elements
- **ElementLocator**: Dual-strategy element location in DOM tree
  - Primary strategy: Index-based element counting (fast, deterministic)
  - Fallback strategy: Marker-based XPath queries (robust)
  - Supports nested element hierarchies (Cell → Row → Table)
  - Handles edge cases (empty elements, complex nesting)
- **SDTInjector DOM manipulation** (replaces XML string concatenation):
  - `wrapElementInline()`: Wraps existing DOM nodes without duplication
  - `loadDocumentAsDom()`: Parses document.xml into DOMDocument
  - `processElement()`: Locates and wraps individual elements
  - `sortElementsByDepth()`: Processes elements depth-first (Cell before Table)
  - `markElementAsProcessed()`: Prevents re-wrapping
- **SDTRegistry marker tracking**:
  - `getMarkerForElement()`: Retrieves marker for registered element
  - `getAllMarkers()`: Returns all registered markers (objectId → markerId)
  - Markers generated automatically during `register()`
- **Comprehensive test coverage**:
  - `NoDuplicationTest.php`: Validates zero content duplication (166 lines)
  - `ElementIdentifierTest.php`: Tests marker generation (70 lines)
  - `ElementLocatorTest.php`: Tests DOM location strategies (178 lines)
  - `PerformanceTest.php`: Benchmarks for 100+ elements
  - `ContentControlDelegationTest.php`: Tests PhpWord delegation (207 lines)
  - `ContentControlErrorHandlingTest.php`: Tests error scenarios (146 lines)
  - `SDTInjectorErrorTest.php`: Tests injection failures (287 lines)
  - `SDTRegistryFallbackTest.php`: Tests ID generation fallback (77 lines)
- **Samples demonstrating zero duplication**:
  - `v3_no_duplication_demo.php`: Before/after comparison
  - `v3_performance_benchmark.php`: Performance metrics
  - `v3_real_world_examples.php`: Practical use cases

### Changed

- **BREAKING**: `SDTInjector::inject()` now uses DOM manipulation instead of string replacement
  - Old behavior: Replaced closing `</w:body>` tag with SDT XML + closing tag
  - New behavior: Parses DOM, locates elements, wraps in-place
  - **Zero content duplication** guaranteed
- **SDTInjector workflow** (v3.0):
  1. Load document.xml as DOMDocument
  2. Sort elements by depth (deepest first: Cell → Table → Section)
  3. Locate each element using ElementLocator
  4. Wrap element inline with `<w:sdt>` structure
  5. Mark as processed to prevent re-wrapping
  6. Serialize modified DOM back to document.xml
- **Performance optimization**: Depth-first processing prevents re-wrapping
  - Cell elements processed before parent Table
  - Already-wrapped elements detected and skipped
  - Stable sort maintains original order for same depth

### Fixed

- **Critical**: Content duplication when wrapping elements with SDTs
  - **Root cause**: Old implementation used string replacement, duplicating content
  - **Solution**: DOM manipulation moves nodes instead of copying
  - **Validation**: All tests confirm zero duplication
- **Edge case**: Re-wrapping of already processed elements
  - **Solution**: Registry of processed elements (NodePath-based)
  - **Benefit**: Safe for nested hierarchies
- **Compatibility**: PHP 8.2+ ValueError handling in `dirname()`
  - Catches ValueError for invalid paths (PHP 8.2+)
  - Throws RuntimeException with clear error message
- **Compatibility**: Linux/Windows path handling in tests
  - Changed invalid Windows paths (`Z:\...`) to cross-platform paths
  - Tests now pass on Ubuntu (GitHub Actions) and Windows

### Performance

- **100 elements**: 88ms (depth-first processing + DOM manipulation)
- **Linear scaling**: O(n) complexity for n elements
- **Memory**: Minimal overhead (DOMDocument reused across elements)
- **Projection 1000 elements**: ~880ms (based on benchmarks)

### Testing

- **166 tests passing** (379 assertions)
- **PHPStan Level 9**: 0 errors in src/
- **Coverage**: 947 new lines of test code
- **New test files**:
  - `NoDuplicationTest.php` (validates zero duplication)
  - `ElementIdentifierTest.php` (marker generation)
  - `ElementLocatorTest.php` (DOM location strategies)
  - `ContentControlDelegationTest.php` (PhpWord method delegation)
  - `ContentControlErrorHandlingTest.php` (error scenarios)
  - `SDTInjectorErrorTest.php` (injection failures)
  - `SDTRegistryFallbackTest.php` (ID generation fallback)

### Documentation

- Updated README.md with v3.0 architecture details
- Added technical documentation for DOM manipulation strategy
- Updated .github/copilot-instructions.md with v3.0 patterns

### Architecture

- **Pattern**: Depth-first element processing
- **Principle**: In-place DOM manipulation (no content copying)
- **Type safety**: PHPStan Level 9 strict mode
- **Compatibility**: PHP 8.2+ | Linux & Windows

### 🔗 References

- [Pull Request #14](https://github.com/mateusbandeira182/ContentControl/pull/14)
- [ISO/IEC 29500-1:2016](https://www.iso.org/standard/71691.html) - OOXML Specification

---

## [Unreleased]

### Added
- **ElementIdentifier performance cache**: Hash and marker caching for better performance
  - `clearCache()`: Clears all cached markers and hashes
  - `getCacheStats()`: Returns cache statistics (for debugging/testing)
  - O(1) lookup for previously processed elements
  - Significant performance improvement for repeated operations
- **CONTRIBUTING.md**: Comprehensive contribution guidelines
  - Development workflow and setup instructions
  - Coding standards (PHPStan Level 9, strict types)
  - Testing guidelines with examples
  - Commit message guidelines (Conventional Commits)
  - Pull request process and checklist
  - Project architecture documentation

### Changed
- **ElementIdentifier**: Refactored to use internal cache
  - `generateMarker()`: Now caches results per object ID
  - `generateContentHash()`: Now caches results per object ID
  - No breaking changes - cache is transparent to users

### Fixed
- **Performance**: ElementIdentifier cache reduces repeated hash computation
  - First call: O(n) where n = element complexity
  - Subsequent calls: O(1) cache lookup

---

## [Unreleased] - 2026-01-28
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

## Previous Internal Releases

Internal v1.x, v2.x, and v3.x versions existed before public release. This changelog documents only the public v0.0.0+ versions.

For internal version history, see commit history before the first public release.

[Unreleased]: https://github.com/mateusbandeira182/ContentControl/compare/v0.0.0...HEAD
[0.0.0]: https://github.com/mateusbandeira182/ContentControl/releases/tag/v0.0.0

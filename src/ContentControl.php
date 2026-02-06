<?php

declare(strict_types=1);

namespace MkGrow\ContentControl;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory as PHPWordIOFactory;

/**
 * ContentControl v0.2 - Proxy Pattern for PhpWord with SDT support
 * 
 * This class encapsulates PhpWord and provides a unified API for:
 * - Creating Word documents
 * - Adding Content Controls (Structured Document Tags)
 * - Managing unique IDs automatically
 * - Saving documents with injected SDTs
 * 
 * @since 0.2.0
 */
final class ContentControl
{
    // ==================== TYPE CONSTANTS ====================
    /**
     * Group Content Control - Groups elements without allowing editing
     * 
     * Specification: ISO/IEC 29500-1:2016 §17.5.2.15
     * XML Element: <w:group/>
     */
    public const TYPE_GROUP = 'group';

    /**
     * Plain Text Content Control - Simple text without formatting
     * 
     * Specification: ISO/IEC 29500-1:2016 §17.5.2.34
     * XML Element: <w:text/>
     */
    public const TYPE_PLAIN_TEXT = 'plainText';

    /**
     * Rich Text Content Control - Text with full formatting
     * 
     * Specification: ISO/IEC 29500-1:2016 §17.5.2.31
     * XML Element: <w:richText/>
     */
    public const TYPE_RICH_TEXT = 'richText';

    /**
     * Picture Content Control - Control for images
     * 
     * Specification: ISO/IEC 29500-1:2016 §17.5.2.27
     * XML Element: <w:picture/>
     */
    public const TYPE_PICTURE = 'picture';

    // ==================== LOCK CONSTANTS ====================
    /**
     * No lock - Content Control can be edited and deleted
     * 
     * Specification: Default value when <w:lock> is absent
     */
    public const LOCK_NONE = '';

    /**
     * Content Control locked - Cannot be deleted, but content is editable
     * 
     * Specification: ISO/IEC 29500-1:2016 §17.5.2.23 Table 17-21
     * Value: sdtLocked
     */
    public const LOCK_SDT_LOCKED = 'sdtLocked';

    /**
     * Content locked - Content Control can be deleted, but content is not editable
     * 
     * Specification: ISO/IEC 29500-1:2016 §17.5.2.23 Table 17-21
     * Value: sdtContentLocked
     */
    public const LOCK_CONTENT_LOCKED = 'sdtContentLocked';

    /**
     * Explicitly unlocked
     * 
     * Specification: ISO/IEC 29500-1:2016 §17.5.2.23 Table 17-21
     * Value: unlocked
     */
    public const LOCK_UNLOCKED = 'unlocked';

    // ==================== PROPERTIES ====================
    
    /**
     * Encapsulated PhpWord instance
     */
    private PhpWord $phpWord;

    /**
     * Content Controls registry
     */
    private SDTRegistry $sdtRegistry;

    /**
     * Creates new ContentControl (Proxy for PhpWord)
     * 
     * @param PhpWord|null $phpWord Existing PhpWord instance or null to create new one
     */
    public function __construct(?PhpWord $phpWord = null)
    {
        $this->phpWord = $phpWord ?? new PhpWord();
        $this->sdtRegistry = new SDTRegistry();
    }

    // ==================== PHPWORD DELEGATION ====================

    /**
     * Adds Section to document
     * 
     * @param mixed[] $style Section style
     * @return Section
     */
    public function addSection(array $style = []): Section
    {
        return $this->phpWord->addSection($style);
    }

    /**
     * Returns document properties
     * 
     * @return \PhpOffice\PhpWord\Metadata\DocInfo
     */
    public function getDocInfo(): \PhpOffice\PhpWord\Metadata\DocInfo
    {
        return $this->phpWord->getDocInfo();
    }

    /**
     * Returns document settings
     * 
     * @return \PhpOffice\PhpWord\Metadata\Settings
     */
    public function getSettings(): \PhpOffice\PhpWord\Metadata\Settings
    {
        return $this->phpWord->getSettings();
    }

    /**
     * Adds font style
     * 
     * @param string $name Style name
     * @param mixed[] $style Style configuration
     * @return void
     */
    public function addFontStyle(string $name, array $style): void
    {
        $this->phpWord->addFontStyle($name, $style);
    }

    /**
     * Adds paragraph style
     * 
     * @param string $name Style name
     * @param mixed[] $style Style configuration
     * @return void
     */
    public function addParagraphStyle(string $name, array $style): void
    {
        $this->phpWord->addParagraphStyle($name, $style);
    }

    /**
     * Adds table style
     * 
     * @param string $name Style name
     * @param mixed[] $styleTable Table style
     * @param mixed[]|null $styleFirstRow First row style
     * @return void
     */
    public function addTableStyle(string $name, array $styleTable, ?array $styleFirstRow = null): void
    {
        $this->phpWord->addTableStyle($name, $styleTable, $styleFirstRow);
    }

    /**
     * Adds title style
     * 
     * @param int $level Title level (1-9)
     * @param mixed[] $fontStyle Font style
     * @param mixed[] $paragraphStyle Paragraph style
     * @return void
     */
    public function addTitleStyle(int $level, array $fontStyle, array $paragraphStyle = []): void
    {
        $this->phpWord->addTitleStyle($level, $fontStyle, $paragraphStyle);
    }

    /**
     * Returns all document sections
     * 
     * @return Section[]
     */
    public function getSections(): array
    {
        return $this->phpWord->getSections();
    }

    // ==================== CONTENT CONTROL API ====================

    /**
     * Adds Content Control wrapping an element
     * 
     * Supported element types in v3.0:
     * - Text: Simple text elements
     * - TextRun: Text elements with formatting
     * - Table: Complete tables
     * - Cell: Individual table cells
     * 
     * Note: Section is not supported in v3.0. To wrap sections,
     * wrap the section's child elements individually.
     * 
     * @param object $element PHPWord element (Text, TextRun, Table, Cell)
     * @param array{
     *     id?: string,
     *     alias?: string,
     *     tag?: string,
     *     type?: string,
     *     lockType?: string,
     *     inlineLevel?: bool
     * } $options Content Control Options
     * @return object The same element (for fluent API)
     * @throws \InvalidArgumentException If element type is not supported
     * 
     * @example
     * ```php
     * $cc = new ContentControl();
     * $section = $cc->addSection();
     * $text = $section->addText('Protected content');
     * 
     * $cc->addContentControl($text, [
     *     'alias' => 'Customer',
     *     'tag' => 'customer-name',
     *     'type' => ContentControl::TYPE_RICH_TEXT,
     *     'lockType' => ContentControl::LOCK_SDT_LOCKED
     * ]);
     * 
     * $cc->save('document.docx');
     * ```
     */
    public function addContentControl(object $element, array $options = []): object
    {
        // NEW LOGIC: Automatic inline-level detection
        $isInlineLevel = $this->shouldUseInlineLevel($element);
        
        // Merge with user options (user can force with 'inlineLevel' => false)
        // Order: auto-detection first, then user options (user override)
        $mergedOptions = array_merge(
            ['inlineLevel' => $isInlineLevel],
            $options
        );
        
        // Create config from merged options
        $config = SDTConfig::fromArray($mergedOptions);

        // Generate ID if not provided
        if ($config->id === '') {
            $config = $config->withId($this->sdtRegistry->generateUniqueId());
        }

        // Register element with config
        $this->sdtRegistry->register($element, $config);

        // Return element for fluent API
        return $element;
    }

    /**
     * Determines if element should use inline-level SDT
     * 
     * NOTE v3.1: Auto-detection disabled due to PHPWord limitation.
     * The 'container' property is not exposed in AbstractElement,
     * preventing automatic context detection (Cell vs Section).
     * 
     * Solution: Users must explicitly specify 'inlineLevel' => true
     * in addContentControl() options for elements inside cells.
     * 
     * @param object $element PHPWord Element
     * @return bool Always returns false (auto-detection disabled)
     * 
     * @see https://github.com/PHPOffice/PHPWord/issues - Feature request: expose container property
     */
    private function shouldUseInlineLevel(object $element): bool
    {
        // Auto-detection disabled - PHPWord does not expose 'container' property
        // Users must explicitly set 'inlineLevel' => true in options
        return false;
    }

    /**
     * Returns encapsulated PhpWord instance (for advanced cases)
     * 
     * @return PhpWord
     */
    public function getPhpWord(): PhpWord
    {
        return $this->phpWord;
    }

    /**
     * Returns SDT registry (for advanced use cases)
     * 
     * @return SDTRegistry
     */
    public function getSDTRegistry(): SDTRegistry
    {
        return $this->sdtRegistry;
    }

    // ==================== SAVE ====================

    /**
     * Saves document with Content Controls
     * 
     * Workflow:
     * 1. Generates base DOCX with PhpWord
     * 2. Injects SDTs into document.xml via SDTInjector
     * 3. Moves to final destination
     * 
     * @param string $filename Output file path
     * @param string $format Document format (default: 'Word2007')
     * @return void
     * @throws \RuntimeException If directory is not writable
     * @throws \PhpOffice\PhpWord\Exception\Exception If PHPWord error occurs
     * @throws Exception\ContentControlException If SDT injection error occurs
     * 
     * @example
     * ```php
     * $cc = new ContentControl();
     * $section = $cc->addSection();
     * $section->addText('Hello World');
     * $cc->save('document.docx');
     * ```
     */
    public function save(string $filename, string $format = 'Word2007'): void
    {
        // 1. Validate directory
        try {
            $dir = dirname($filename);
            
            if (!is_dir($dir) || !is_writable($dir)) {
                throw new \RuntimeException(
                    'ContentControl: Target directory not writable: ' . $dir
                );
            }
        } catch (\ValueError $e) {
            // PHP 8.2+: dirname(), is_dir() or is_writable() may throw ValueError for invalid paths
            throw new \RuntimeException(
                'ContentControl: Invalid file path: ' . $e->getMessage()
            );
        }

        // 2. Generate base DOCX
        $tempFile = sys_get_temp_dir() . '/phpword_' . uniqid() . '.docx';

        try {
            $writer = PHPWordIOFactory::createWriter($this->phpWord, $format);
            $writer->save($tempFile);

            // 3. Inject SDTs if any
            $sdts = $this->sdtRegistry->getAll();
            if (count($sdts) > 0) {
                $injector = new SDTInjector();
                $injector->inject($tempFile, $sdts);
            }

            // 4. Move to destination
            if (!rename($tempFile, $filename)) {
                throw new \RuntimeException(
                    'ContentControl: Failed to move file from ' . $tempFile . ' to ' . $filename
                );
            }
        } finally {
            // Clean up temporary file if it still exists
            if (file_exists($tempFile)) {
                $this->unlinkWithRetry($tempFile);
            }
        }
    }

    /**
     * Attempts to delete file with multiple retries
     * 
     * On Windows, files may be briefly locked after ZIP operations.
     * 
     * @param string $filePath Path to file to delete
     * @param int $maxAttempts Maximum number of attempts
     * @return void
     * @throws Exception\TemporaryFileException If all attempts fail
     */
    private function unlinkWithRetry(string $filePath, int $maxAttempts = 3): void
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            clearstatcache(true, $filePath);

            if (@unlink($filePath)) {
                return; // Success
            }

            if (!file_exists($filePath)) {
                return; // File no longer exists
            }

            // Wait before next attempt (except on last attempt)
            if ($attempt < $maxAttempts) {
                usleep(100000); // 100ms
            }
        }

        // All attempts failed
        throw new Exception\TemporaryFileException($filePath);
    }
}

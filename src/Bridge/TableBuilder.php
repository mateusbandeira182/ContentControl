<?php

declare(strict_types=1);

namespace MkGrow\ContentControl\Bridge;

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\ContentProcessor;
use MkGrow\ContentControl\Exception\ContentControlException;
use PhpOffice\PhpWord\Element\Table;

/**
 * TableBuilder - Create and inject PHPWord tables with Content Controls
 *
 * Implements Bridge pattern to separate table creation from template injection.
 * Provides a fluent interface for creating tables with automatic SDT registration
 * and injecting them into existing template documents.
 *
 * Key Features:
 * - Create tables programmatically with PHPWord
 * - Automatic Content Control registration for cells and tables
 * - Inject tables into template SDTs preserving internal structure
 * - Hash-based table identification for extraction
 * - Automatic cleanup of temporary files
 *
 * Usage:
 * ```php
 * $builder = new TableBuilder();
 * 
 * // Create table with Content Controls
 * $table = $builder->createTable([
 *     'tableTag' => 'invoice-items',
 *     'rows' => [
 *         ['cells' => [
 *             ['text' => 'Item', 'width' => 3000],
 *             ['text' => 'Price', 'width' => 2000, 'tag' => 'price-1']
 *         ]]
 *     ]
 * ]);
 * 
 * // Inject into template
 * $builder->injectTable('template.docx', 'invoice-items', $table);
 * $builder->getContentControl()->save('output.docx');
 * ```
 *
 * @package MkGrow\ContentControl\Bridge
 * @since 0.4.0
 * @final This class cannot be extended
 */
final class TableBuilder
{
    /**
     * ContentControl instance for document manipulation
     *
     * @var ContentControl
     */
    private ContentControl $contentControl;

    /**
     * Temporary file path for intermediate processing
     *
     * Used during table extraction. Automatically cleaned up in destructor.
     *
     * @var string|null
     */
    private ?string $tempFile = null;

    /**
     * TableBuilder Constructor
     *
     * Initializes the builder with a ContentControl instance.
     * If not provided, creates a new instance.
     *
     * @param ContentControl|null $contentControl Optional ContentControl instance
     *
     * @since 0.4.0
     *
     * @example
     * ```php
     * // With default ContentControl
     * $builder = new TableBuilder();
     * 
     * // With custom ContentControl
     * $cc = new ContentControl();
     * $builder = new TableBuilder($cc);
     * ```
     */
    public function __construct(?ContentControl $contentControl = null)
    {
        $this->contentControl = $contentControl ?? new ContentControl();
    }

    /**
     * Get the underlying ContentControl instance
     *
     * Provides access to the ContentControl for document manipulation,
     * saving, and other operations.
     *
     * @return ContentControl The ContentControl instance
     *
     * @since 0.4.0
     *
     * @example
     * ```php
     * $builder = new TableBuilder();
     * $cc = $builder->getContentControl();
     * 
     * // Add custom content
     * $section = $cc->addSection();
     * $section->addText('Additional content');
     * 
     * // Save document
     * $cc->save('output.docx');
     * ```
     */
    public function getContentControl(): ContentControl
    {
        return $this->contentControl;
    }

    /**
     * Create table with Content Controls from configuration
     *
     * Builds a PHPWord table with automatic SDT registration for cells and
     * optionally the entire table. Supports multi-level styling and validation.
     *
     * Configuration Structure:
     * - rows: Array of row configurations (required)
     * - style: Table-level style array (optional)
     * - tableTag: Content Control tag for entire table (optional)
     * - tableAlias: Display name for table SDT (optional)
     * - tableLockType: Lock type for table SDT (optional)
     *
     * Row Configuration:
     * - cells: Array of cell configurations (required)
     * - height: Row height in twips (optional)
     * - style: Row-level style array (optional)
     *
     * Cell Configuration:
     * - text: Cell text content (required, mutually exclusive with element)
     * - element: Custom element (NOT SUPPORTED in v0.4.0)
     * - width: Cell width in twips (optional, default: 2000)
     * - style: Cell-level style array (optional)
     * - tag: Content Control tag for cell (optional)
     * - alias: Display name for cell SDT (optional)
     * - type: SDT type (optional, default: TYPE_RICH_TEXT)
     * - lockType: SDT lock type (optional, default: LOCK_NONE)
     *
     * @phpstan-type CellConfig array{
     *     text?: string,
     *     element?: object,
     *     tag?: string,
     *     alias?: string,
     *     type?: string,
     *     lockType?: string,
     *     width?: int,
     *     style?: array<string, mixed>
     * }
     * @phpstan-type RowConfig array{
     *     cells: array<CellConfig>,
     *     height?: int|null,
     *     style?: array<string, mixed>
     * }
     * @phpstan-type TableConfig array{
     *     rows: array<RowConfig>,
     *     style?: array<string, mixed>,
     *     tableTag?: string,
     *     tableAlias?: string,
     *     tableLockType?: string
     * }
     *
     * @param array<string, mixed> $config Table configuration array
     *
     * @return Table The created PHPWord table with registered SDTs
     *
     * @throws ContentControlException If configuration is invalid or element is used
     *
     * @since 0.4.0
     *
     * @example
     * ```php
     * $builder = new TableBuilder();
     * 
     * // Simple table
     * $table = $builder->createTable([
     *     'rows' => [
     *         ['cells' => [
     *             ['text' => 'Item', 'width' => 3000],
     *             ['text' => '$0.00', 'width' => 2000, 'tag' => 'price-1']
     *         ]]
     *     ]
     * ]);
     * 
     * // Table with styles and wrapper SDT
     * $table = $builder->createTable([
     *     'tableTag' => 'invoice-items',
     *     'style' => ['borderSize' => 6, 'borderColor' => '000000'],
     *     'rows' => [
     *         ['cells' => [
     *             ['text' => 'Product', 'width' => 4000],
     *             ['text' => 'Price', 'width' => 2000]
     *         ]]
     *     ]
     * ]);
     * ```
     */
    public function createTable(array $config): Table
    {
        // 1. Validate configuration structure
        $this->validateTableConfig($config);
        
        // 2. Get section from ContentControl
        $section = $this->contentControl->addSection();
        
        // 3. Create table with optional style
        $tableStyle = $config['style'] ?? [];
        $table = $section->addTable($tableStyle);
        
        // 4. Process rows
        /** @var array<string, mixed> $rowsConfig */
        $rowsConfig = $config['rows'];
        
        foreach ($rowsConfig as $rowConfig) {
            /** @var array<string, mixed> $rowConfig */
            $rowHeight = isset($rowConfig['height']) && is_int($rowConfig['height']) ? $rowConfig['height'] : null;
            $rowStyle = isset($rowConfig['style']) && is_array($rowConfig['style']) ? $rowConfig['style'] : [];
            $row = $table->addRow($rowHeight, $rowStyle);
            
            // 5. Process cells
            /** @var array<array<string, mixed>> $cellsConfig */
            $cellsConfig = $rowConfig['cells'];
            
            foreach ($cellsConfig as $cellConfig) {
                $cellWidth = isset($cellConfig['width']) && is_int($cellConfig['width']) ? $cellConfig['width'] : 2000;
                $cellStyle = isset($cellConfig['style']) && is_array($cellConfig['style']) ? $cellConfig['style'] : [];
                $cell = $row->addCell($cellWidth, $cellStyle);
                
                // 6. Add content (text only in v0.4.0)
                if (isset($cellConfig['element'])) {
                    throw new ContentControlException(
                        'Custom elements in cells not yet supported. Use "text" property.'
                    );
                }
                
                $textContent = isset($cellConfig['text']) && is_string($cellConfig['text']) 
                    ? $cellConfig['text'] 
                    : '';
                $textElement = $cell->addText($textContent);
                
                // 7. Register SDT for cell if configured
                if (isset($cellConfig['tag'])) {
                    $tag = is_string($cellConfig['tag']) ? $cellConfig['tag'] : '';
                    $alias = isset($cellConfig['alias']) && is_string($cellConfig['alias']) 
                        ? $cellConfig['alias'] 
                        : $tag;
                    $type = isset($cellConfig['type']) && is_string($cellConfig['type'])
                        ? $cellConfig['type']
                        : ContentControl::TYPE_RICH_TEXT;
                    $lockType = isset($cellConfig['lockType']) && is_string($cellConfig['lockType'])
                        ? $cellConfig['lockType']
                        : ContentControl::LOCK_NONE;
                    
                    $sdtConfig = [
                        'tag' => $tag,
                        'alias' => $alias,
                        'type' => $type,
                        'lockType' => $lockType,
                    ];
                    $this->contentControl->addContentControl($textElement, $sdtConfig);
                }
            }
        }
        
        // 8. Register table wrapper SDT if configured
        if (isset($config['tableTag'])) {
            $tableTag = is_string($config['tableTag']) ? $config['tableTag'] : '';
            $tableAlias = isset($config['tableAlias']) && is_string($config['tableAlias'])
                ? $config['tableAlias']
                : $tableTag;
            $tableLockType = isset($config['tableLockType']) && is_string($config['tableLockType'])
                ? $config['tableLockType']
                : ContentControl::LOCK_NONE;
            
            $tableSdtConfig = [
                'tag' => $tableTag,
                'alias' => $tableAlias,
                'type' => ContentControl::TYPE_GROUP,
                'lockType' => $tableLockType,
            ];
            $this->contentControl->addContentControl($table, $tableSdtConfig);
        }
        
        return $table;
    }

    /**
     * Inject table into template SDT with internal SDTs preserved
     *
     * Replaces the content of a target SDT in a template document with
     * a pre-built table, preserving all internal Content Controls.
     *
     * Workflow:
     * 1. Creates temporary file for table extraction
     * 2. Saves ContentControl to temp file (triggers SDTInjector)
     * 3. Extracts table XML with internal SDTs
     * 4. Opens target template file
     * 5. Locates target SDT by tag
     * 6. Clears current SDT content
     * 7. Imports and appends table XML
     * 8. Marks template as modified
     * 9. Cleans up temp file (guaranteed via try-finally)
     *
     * Error Cases Handled:
     * - Template file not found
     * - Target SDT not found in template
     * - SDT structure invalid (missing w:sdtContent)
     * - ZIP/XML parsing failures
     * - Table extraction failures
     *
     * @param string $templatePath Absolute path to template .docx file
     * @param string $targetSdtTag Tag of SDT to replace content
     * @param Table $table Pre-built table with internal SDTs
     *
     * @return void
     *
     * @throws ContentControlException On any failure during injection
     *
     * @since 0.4.0
     *
     * @example
     * ```php
     * $builder = new TableBuilder();
     * 
     * // Create table with SDTs
     * $table = $builder->createTable([
     *     'rows' => [
     *         ['cells' => [
     *             ['text' => 'Product', 'width' => 3000],
     *             ['text' => '$0.00', 'width' => 2000, 'tag' => 'price-1']
     *         ]]
     *     ]
     * ]);
     * 
     * // Inject into template
     * $builder->injectTable('invoice-template.docx', 'invoice-items', $table);
     * 
     * // Save modified template
     * $builder->getContentControl()->save('invoice-output.docx');
     * ```
     */
    public function injectTable(string $templatePath, string $targetSdtTag, Table $table): void
    {
        // Ensure temp file is cleaned up even if exceptions occur
        try {
            // 1. Create temporary file for table extraction
            $tempFileBase = tempnam(sys_get_temp_dir(), 'tablebuilder_');
            if ($tempFileBase === false) {
                throw new ContentControlException(
                    'Failed to create temporary file for table extraction'
                );
            }
            $this->tempFile = $tempFileBase . '.docx';

            // 2. Save ContentControl to temp file (triggers SDTInjector)
            $this->contentControl->save($this->tempFile);

            // 3. Extract table XML with internal SDTs
            $tableXml = $this->extractTableXmlWithSdts($this->tempFile, $table);

            // 4. Open template file
            if (!file_exists($templatePath)) {
                throw new ContentControlException(
                    "Template file not found: {$templatePath}"
                );
            }

            $zip = new \ZipArchive();
            if ($zip->open($templatePath) !== true) {
                throw new ContentControlException(
                    "Failed to open template as ZIP: {$templatePath}"
                );
            }

            try {
                // 5. Read document.xml from template
                $documentXml = $zip->getFromName('word/document.xml');
                if ($documentXml === false) {
                    throw new ContentControlException(
                        "word/document.xml not found in template: {$templatePath}"
                    );
                }

                // 6. Parse as DOM
                $dom = new \DOMDocument();
                if (!$dom->loadXML($documentXml)) {
                    throw new ContentControlException(
                        'Failed to parse template document.xml as XML'
                    );
                }

                // 7. Create ContentProcessor for SDT operations
                $processor = new ContentProcessor($templatePath);

                // 8. Locate target SDT by tag
                $sdtData = $processor->findSdt($targetSdtTag);
                if ($sdtData === null) {
                    throw new ContentControlException(
                        "SDT with tag '{$targetSdtTag}' not found in template"
                    );
                }

                // 9. Create XPath with namespaces
                $xpath = $processor->createXPathForDom($dom);

                // 10. Locate SDT element in DOM using XPath
                $sdtElements = $xpath->query("//w:sdt[.//w:tag[@w:val='{$targetSdtTag}']]");
                if ($sdtElements === false || $sdtElements->length === 0) {
                    throw new ContentControlException(
                        "SDT element for tag '{$targetSdtTag}' not found in DOM"
                    );
                }

                $sdtElement = $sdtElements->item(0);
                if ($sdtElement === null) {
                    throw new ContentControlException(
                        "Failed to retrieve SDT element for tag '{$targetSdtTag}'"
                    );
                }

                // 11. Locate w:sdtContent child
                $sdtContentElements = $xpath->query('.//w:sdtContent', $sdtElement);
                if ($sdtContentElements === false || $sdtContentElements->length === 0) {
                    throw new ContentControlException(
                        "w:sdtContent not found in SDT '{$targetSdtTag}'"
                    );
                }

                $sdtContent = $sdtContentElements->item(0);
                if ($sdtContent === null) {
                    throw new ContentControlException(
                        "Failed to retrieve w:sdtContent for SDT '{$targetSdtTag}'"
                    );
                }

                // 12. Clear current content
                while ($sdtContent->firstChild !== null) {
                    $sdtContent->removeChild($sdtContent->firstChild);
                }

                // 13. Parse table XML as fragment
                $tableFragment = $dom->createDocumentFragment();
                if (!$tableFragment->appendXML($tableXml)) {
                    throw new ContentControlException(
                        'Failed to parse table XML as document fragment'
                    );
                }

                // 14. Import and append table
                $importedTable = $dom->importNode($tableFragment, true);
                // @phpstan-ignore-next-line identical.alwaysFalse (DOMDocument::importNode() can return false per PHP docs)
                if ($importedTable === false) {
                    throw new ContentControlException(
                        'Failed to import table XML into template DOM'
                    );
                }
                /** @var \DOMNode $importedTable */

                $sdtContent->appendChild($importedTable);

                // 15. Serialize modified DOM
                $modifiedXml = $dom->saveXML();
                if ($modifiedXml === false) {
                    throw new ContentControlException(
                        'Failed to serialize modified template XML'
                    );
                }

                // 16. Write back to ZIP
                if (!$zip->deleteName('word/document.xml')) {
                    throw new ContentControlException(
                        'Failed to delete old document.xml from template'
                    );
                }

                if (!$zip->addFromString('word/document.xml', $modifiedXml)) {
                    throw new ContentControlException(
                        'Failed to write modified document.xml to template'
                    );
                }

                // 17. Mark template as modified
                $processor->markModified($templatePath);

            } finally {
                $zip->close();
            }

        } finally {
            // 18. Guaranteed cleanup of temp file
            if ($this->tempFile !== null && file_exists($this->tempFile)) {
                @unlink($this->tempFile);
                $this->tempFile = null;
            }
        }
    }

    /**
     * Generate hash for table identification in DOCX XML
     *
     * Creates a deterministic MD5 hash based on table dimensions (rows x cells)
     * extracted via Reflection from PHPWord's private properties.
     *
     * Algorithm:
     * - Extracts rowCount from Table's private $rows property
     * - Extracts cellCount from first row's private $cells property
     * - Generates MD5 hash from "{rowCount}x{cellCount}" string
     *
     * Limitations:
     * - Hash collisions possible for tables with same dimensions
     * - Only dimensions considered (not content or styles)
     * - Assumes all rows have same number of cells
     *
     * @param Table $table PHPWord table instance
     *
     * @return string MD5 hash (32 characters)
     *
     * @throws ContentControlException If reflection fails or table has no rows
     *
     * @since 0.4.0
     *
     * @example
     * ```php
     * $table = $builder->createTable([
     *     'rows' => [
     *         ['cells' => [['text' => 'A'], ['text' => 'B'], ['text' => 'C']]],
     *         ['cells' => [['text' => 'D'], ['text' => 'E'], ['text' => 'F']]],
     *     ]
     * ]);
     * $hash = $builder->generateTableHash($table);
     * // Returns: md5("2x3") = "a87ff679a2f3e71d9181a67b7542122c"
     * ```
     */
    private function generateTableHash(Table $table): string
    {
        try {
            // Use Reflection to access private $rows property
            $reflectionTable = new \ReflectionClass($table);
            $rowsProperty = $reflectionTable->getProperty('rows');
            $rowsProperty->setAccessible(true);
            
            /** @var array<\PhpOffice\PhpWord\Element\Row> $rows */
            $rows = $rowsProperty->getValue($table);
            
            if (count($rows) === 0) {
                throw new ContentControlException(
                    'Cannot generate hash for empty table'
                );
            }
            
            $rowCount = count($rows);
            
            // Use Reflection to access private $cells property from first row
            $firstRow = $rows[0];
            $reflectionRow = new \ReflectionClass($firstRow);
            $cellsProperty = $reflectionRow->getProperty('cells');
            $cellsProperty->setAccessible(true);
            
            /** @var array<\PhpOffice\PhpWord\Element\Cell> $cells */
            $cells = $cellsProperty->getValue($firstRow);
            $cellCount = count($cells);
            
            // Generate hash: md5("{rowCount}x{cellCount}")
            $dimensionString = "{$rowCount}x{$cellCount}";
            
            return md5($dimensionString);
            
        } catch (\ReflectionException $e) {
            throw new ContentControlException(
                "Failed to generate table hash via Reflection: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Extract table XML from DOCX with internal SDTs preserved
     *
     * Opens a DOCX file, locates a specific table by hash, and extracts
     * its complete XML representation including any nested Content Controls.
     *
     * Process:
     * 1. Opens DOCX as ZIP archive
     * 2. Reads word/document.xml
     * 3. Parses XML as DOM
     * 4. Generates hash from target table
     * 5. Locates matching table in DOM via XPath
     * 6. Serializes table node to XML string
     * 7. Cleans redundant namespace declarations
     *
     * Namespace Cleanup:
     * - Removes redundant xmlns:w declarations (inherited from root)
     * - Preserves w:sdt and w:sdtContent elements
     * - Returns clean XML ready for DOM import
     *
     * @param string $docxPath Absolute path to .docx file
     * @param Table $table Table to extract (used for hash generation)
     *
     * @return string Complete table XML with SDTs preserved
     *
     * @throws ContentControlException If file not found, ZIP fails, or table not found
     *
     * @since 0.4.0
     *
     * @example
     * ```php
     * $table = $builder->createTable([...]);
     * $tempFile = sys_get_temp_dir() . '/temp.docx';
     * $builder->getContentControl()->save($tempFile);
     * $xml = $builder->extractTableXmlWithSdts($tempFile, $table);
     * // Returns: "<w:tbl>...<w:sdt>...<w:sdtContent>...</w:sdtContent></w:sdt>...</w:tbl>"
     * ```
     */
    private function extractTableXmlWithSdts(string $docxPath, Table $table): string
    {
        // 1. Validate file exists
        if (!file_exists($docxPath)) {
            throw new ContentControlException(
                "DOCX file not found: {$docxPath}"
            );
        }

        // 2. Open as ZIP archive
        $zip = new \ZipArchive();
        if ($zip->open($docxPath) !== true) {
            throw new ContentControlException(
                "Failed to open DOCX as ZIP: {$docxPath}"
            );
        }

        try {
            // 3. Read document.xml
            $documentXml = $zip->getFromName('word/document.xml');
            if ($documentXml === false) {
                throw new ContentControlException(
                    "word/document.xml not found in DOCX: {$docxPath}"
                );
            }

            // 4. Parse as DOM
            $dom = new \DOMDocument();
            if (!$dom->loadXML($documentXml)) {
                throw new ContentControlException(
                    "Failed to parse word/document.xml as XML"
                );
            }

            // 5. Create XPath with namespaces
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            // 6. Generate hash for target table
            $targetHash = $this->generateTableHash($table);

            // 7. Locate all tables in document
            $tables = $xpath->query('//w:tbl');
            if ($tables === false || $tables->length === 0) {
                throw new ContentControlException(
                    "No tables found in document"
                );
            }

            // 8. Find matching table by hash
            $matchingTable = null;
            foreach ($tables as $tableNode) {
                /** @var \DOMElement $tableNode */
                // Count rows
                $rows = $xpath->query('.//w:tr', $tableNode);
                if ($rows === false) {
                    continue;
                }
                $rowCount = $rows->length;

                // Count cells in first row
                if ($rowCount > 0) {
                    $firstRow = $rows->item(0);
                    if ($firstRow !== null) {
                        $cells = $xpath->query('.//w:tc', $firstRow);
                        if ($cells !== false) {
                            $cellCount = $cells->length;
                            $currentHash = md5("{$rowCount}x{$cellCount}");

                            if ($currentHash === $targetHash) {
                                $matchingTable = $tableNode;
                                break;
                            }
                        }
                    }
                }
            }

            if ($matchingTable === null) {
                throw new ContentControlException(
                    "Table with hash {$targetHash} not found in document"
                );
            }

            // 9. Serialize table to XML
            $tableXml = $dom->saveXML($matchingTable);
            if ($tableXml === false) {
                throw new ContentControlException(
                    "Failed to serialize table XML"
                );
            }

            // 10. Clean redundant namespace declarations
            // Remove xmlns:w (inherited from root element)
            $tableXml = preg_replace('/\s+xmlns:w="[^"]+"/', '', $tableXml);
            if ($tableXml === null) {
                throw new ContentControlException(
                    "Failed to clean namespace declarations"
                );
            }

            return $tableXml;

        } finally {
            $zip->close();
        }
    }

    /**
     * Destructor - ensures cleanup of temporary files
     *
     * Automatically removes any temporary files created during
     * table extraction to prevent disk space leaks.
     *
     * @return void
     *
     * @since 0.4.0
     */
    public function __destruct()
    {
        if ($this->tempFile !== null && file_exists($this->tempFile)) {
            @unlink($this->tempFile);
        }
    }

    /**
     * Validate table configuration structure
     *
     * Ensures the configuration array contains all required keys
     * and validates the structure of rows.
     *
     * @param array<string, mixed> $config Table configuration
     *
     * @throws ContentControlException If configuration is invalid
     *
     * @return void
     */
    private function validateTableConfig(array $config): void
    {
        if (!isset($config['rows'])) {
            throw new ContentControlException(
                'Table configuration must have "rows" key'
            );
        }

        if (!is_array($config['rows']) || count($config['rows']) === 0) {
            throw new ContentControlException(
                'Table "rows" must be non-empty array'
            );
        }

        foreach ($config['rows'] as $index => $rowConfig) {
            if (!is_array($rowConfig)) {
                throw new ContentControlException(
                    "Row {$index}: configuration must be an array"
                );
            }
            /** @var array<string, mixed> $rowConfig */
            $this->validateRowConfig($rowConfig, $index);
        }
    }

    /**
     * Validate row configuration structure
     *
     * Ensures each row contains valid cells configuration.
     *
     * @param array<string, mixed> $config Row configuration
     * @param int $rowIndex Row index for error messages
     *
     * @throws ContentControlException If configuration is invalid
     *
     * @return void
     */
    private function validateRowConfig(array $config, int $rowIndex): void
    {
        if (!isset($config['cells'])) {
            throw new ContentControlException(
                "Row {$rowIndex}: missing \"cells\" key"
            );
        }

        if (!is_array($config['cells']) || count($config['cells']) === 0) {
            throw new ContentControlException(
                "Row {$rowIndex}: \"cells\" must be non-empty array"
            );
        }

        foreach ($config['cells'] as $cellIndex => $cellConfig) {
            if (!is_array($cellConfig)) {
                throw new ContentControlException(
                    "Row {$rowIndex}, Cell {$cellIndex}: configuration must be an array"
                );
            }
            /** @var array<string, mixed> $cellConfig */
            $this->validateCellConfig($cellConfig, $rowIndex, $cellIndex);
        }
    }

    /**
     * Validate cell configuration structure
     *
     * Ensures each cell has either 'text' or 'element' (mutually exclusive)
     * and validates the presence of required content when tag is specified.
     *
     * @param array<string, mixed> $config Cell configuration
     * @param int $rowIndex Row index for error messages
     * @param int $cellIndex Cell index for error messages
     *
     * @throws ContentControlException If configuration is invalid
     *
     * @return void
     */
    private function validateCellConfig(array $config, int $rowIndex, int $cellIndex): void
    {
        $hasText = isset($config['text']);
        $hasElement = isset($config['element']);
        $hasTag = isset($config['tag']);

        // Mutually exclusive: text XOR element
        if ($hasText && $hasElement) {
            throw new ContentControlException(
                "Row {$rowIndex}, Cell {$cellIndex}: cannot have both \"text\" and \"element\""
            );
        }

        // Must have either text or element
        if (!$hasText && !$hasElement) {
            throw new ContentControlException(
                "Row {$rowIndex}, Cell {$cellIndex}: must have \"text\" or \"element\""
            );
        }

        // If element is provided, it's not supported in v0.4.0
        if ($hasElement) {
            throw new ContentControlException(
                "Row {$rowIndex}, Cell {$cellIndex}: \"element\" is not supported in v0.4.0, use \"text\" only"
            );
        }
    }
}

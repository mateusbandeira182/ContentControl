<?php

declare(strict_types=1);

namespace MkGrow\ContentControl\Bridge;

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\ContentProcessor;
use MkGrow\ContentControl\ElementIdentifier;
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
     * Current PHPWord Table instance for fluent API
     *
     * Created on first addRow() call. Null when using legacy createTable() API.
     *
     * @var Table|null
     * @since 0.4.2
     */
    private ?Table $table = null;

    /**
     * Pending table-level Content Control configuration
     *
     * Stored when addContentControl() is called, applied before extraction/injection.
     *
     * @var array{id?: string, alias?: string, tag?: string, type?: string, lockType?: string}|null
     * @since 0.4.2
     */
    private ?array $tableSdtConfig = null;

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
     * Register Content Control for an element (internal use by fluent API)
     *
     * This method is called internally by CellBuilder to register SDTs
     * for elements added via the fluent interface. It delegates to
     * ContentControl::addContentControl() for the actual registration.
     *
     * @internal Used by CellBuilder, not part of public API
     *
     * @param \PhpOffice\PhpWord\Element\AbstractElement $element The element to wrap with SDT
     * @param array{id?: string, alias?: string, tag?: string, type?: string, lockType?: string, inlineLevel?: bool} $config SDT configuration
     * @return void
     *
     * @since 0.4.2
     */
    public function registerSdt(\PhpOffice\PhpWord\Element\AbstractElement $element, array $config): void
    {
        $this->contentControl->addContentControl($element, $config);
    }

    /**
     * Add row to table with fluent interface
     *
     * Creates a new row in the table and returns a RowBuilder for configuring cells.
     * If the table doesn't exist yet, it will be created automatically.
     *
     * This is part of the fluent API introduced in v0.4.2, providing a more
     * developer-friendly alternative to the legacy createTable() method.
     *
     * @param int|null $height Optional row height in twips (1/20 point)
     *                         Common values: 360 twips (0.25 inch), 720 twips (0.5 inch)
     * @param array<string, mixed> $style Optional row style configuration
     *                                     Supported properties (PhpWord Row style):
     *                                     - 'tblHeader': bool - Repeat as header row
     *                                     - 'cantSplit': bool - Keep row together on page
     *                                     - 'exactHeight': bool - Use exact height vs minimum
     *
     * @return RowBuilder Row builder for chaining cell configuration
     *
     * @since 0.4.2
     *
     * @example Basic usage
     * ```php
     * $builder = new TableBuilder();
     * $builder->addRow()
     *     ->addCell(3000)->addText('Name')->end()
     *     ->addCell(5000)->addText('Description')->end()
     *     ->end();
     * ```
     *
     * @example With row styling
     * ```php
     * $builder->addRow(720, ['tblHeader' => true])
     *     ->addCell(3000)->addText('Column 1')->end()
     *     ->addCell(3000)->addText('Column 2')->end()
     *     ->end();
     * ```
     */
    public function addRow(?int $height = null, array $style = []): RowBuilder
    {
        // Lazy create table on first addRow() call
        if ($this->table === null) {
            $section = $this->contentControl->addSection();
            $this->table = $section->addTable();
        }

        $row = $this->table->addRow($height, $style);
        return new RowBuilder($row, $this);
    }

    /**
     * Add Content Control to entire table
     *
     * Wraps the table with a GROUP SDT. The configuration is stored and applied
     * when injectInto() is called. This is primarily intended for template injection workflows.
     *
     * **Important:** Table-level SDTs via addContentControl() are only reliably applied
     * when using injectInto(). For direct ContentControl::save(), the timing of SDT application
     * may conflict with cell-level SDTs. This limitation will be addressed in a future version.
     *
     * This allows creating a table-level SDT that contains all cell SDTs,
     * useful for template scenarios where the entire table needs to be replaceable.
     *
     * @param array{id?: string, alias?: string, tag?: string, type?: string, lockType?: string} $config SDT configuration
     *                                                                                                    - tag: string (required for injectInto)
     *                                                                                                    - alias: string (optional, display name)
     *                                                                                                    - type: string (optional, defaults to TYPE_GROUP)
     *                                                                                                    - lockType: string (optional, defaults to LOCK_NONE)
     *
     * @return self For method chaining
     *
     * @since 0.4.2
     *
     * @example
     * ```php
     * $builder = new TableBuilder();
     * $builder->addContentControl(['tag' => 'invoice-table', 'alias' => 'Invoice Items'])
     *     ->addRow()
     *         ->addCell(3000)->addText('Product')->end()
     *         ->addCell(2000)->addText('Price')->end()
     *         ->end();
     * 
     * // Use with injectInto() for reliable table-level SDT
     * $processor = new ContentProcessor('template.docx');
     * $builder->injectInto($processor, 'invoice-table');
     * $processor->save('output.docx');
     * ```
     */
    public function addContentControl(array $config): self
    {
        $this->tableSdtConfig = $config;
        return $this;
    }

    /**
     * Inject table into ContentProcessor template
     *
     * Simplified injection workflow that:
     * 1. Applies table-level SDT if configured
     * 2. Saves table to temporary file
     * 3. Extracts table XML with nested SDTs
     * 4. Replaces target SDT in template using the existing injectTable() logic
     *
     * This is a convenience method that wraps the existing inject workflow but
     * works with ContentProcessor instead of file paths.
     *
     * @param ContentProcessor $processor Template processor with target SDT
     * @param string $targetTag Tag of SDT to replace in template
     *
     * @return void
     *
     * @throws ContentControlException If table not created, extraction fails, or SDT not found
     *
     * @since 0.4.2
     *
     * @example
     * ```php
     * $builder = new TableBuilder();
     * $builder->addRow()
     *     ->addCell(3000)->addText('Product A')->end()
     *     ->addCell(2000)->addText('$99.99')->end()
     *     ->end();
     *
     * $processor = new ContentProcessor('template.docx');
     * $builder->injectInto($processor, 'invoice-items');
     * $processor->save('output.docx');
     * ```
     */
    public function injectInto(ContentProcessor $processor, string $targetTag): void
    {
        if ($this->table === null) {
            throw new ContentControlException(
                'Cannot inject table: no table created. Call addRow() first to create a table.'
            );
        }

        // Apply pending table-level SDT if not yet applied
        // (This handles edge case where user calls injectInto() without creating rows first,
        // though that would be unusual since $table is null without rows)
        if ($this->tableSdtConfig !== null) {
            $this->contentControl->addContentControl($this->table, $this->tableSdtConfig);
            $this->tableSdtConfig = null;
        }

        // Save to temp file and extract XML
        $tempPath = $this->getTempFilePath();
        $this->contentControl->save($tempPath);

        $tableXml = $this->extractTableXmlWithSdts($tempPath, $this->table);

        // Use ContentProcessor to inject
        // We need to use internal DOM manipulation like injectTable() does
        // because replaceContent() escapes XML strings
        $this->injectTableXmlIntoProcessor($processor, $targetTag, $tableXml);

        // Cleanup
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }

    /**
     * Inject table XML into ContentProcessor using DOM manipulation
     *
     * Internal method that replicates the DOM injection logic from injectTable()
     * but works with ContentProcessor instance instead of file paths.
     *
     * @param ContentProcessor $processor Template processor
     * @param string $targetTag SDT tag to replace
     * @param string $tableXml Extracted table XML
     *
     * @return void
     *
     * @throws ContentControlException If SDT not found or injection fails
     *
     * @since 0.4.2
     */
    private function injectTableXmlIntoProcessor(
        ContentProcessor $processor,
        string $targetTag,
        string $tableXml
    ): void {
        // Find the SDT element (returns ['sdt' => DOMElement, 'dom' => DOMDocument, 'file' => string])
        $result = $processor->findSdt($targetTag);
        
        if ($result === null) {
            throw new ContentControlException(
                "SDT with tag '{$targetTag}' not found in template"
            );
        }

        $sdtElement = $result['sdt'];
        $doc = $result['dom'];
        $filePath = $result['file']; // Use the actual file path from search result

        // Find w:sdtContent
        $xpath = $processor->createXPathForDom($doc);
        $sdtContentNodes = $xpath->query('.//w:sdtContent', $sdtElement);
        
        if ($sdtContentNodes === false || $sdtContentNodes->length === 0) {
            throw new ContentControlException(
                "SDT '{$targetTag}' has no w:sdtContent element"
            );
        }

        $sdtContent = $sdtContentNodes->item(0);
        if ($sdtContent === null) {
            throw new ContentControlException(
                "Failed to retrieve w:sdtContent element"
            );
        }

        // Clear existing content
        while ($sdtContent->firstChild !== null) {
            $sdtContent->removeChild($sdtContent->firstChild);
        }

        // Create fragment from table XML
        $tempDoc = new \DOMDocument();
        $tempDoc->loadXML(
            '<w:root xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' .
            $tableXml .
            '</w:root>'
        );

        // Import table nodes
        $tempRoot = $tempDoc->documentElement;
        if ($tempRoot !== null) {
            foreach ($tempRoot->childNodes as $node) {
                $imported = $doc->importNode($node, true);
                $sdtContent->appendChild($imported);
            }
        }

        // Mark the correct file as modified (use result from findSdt, not hardcoded)
        $processor->markModified($filePath);
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
     * @deprecated Since v0.4.2. Use fluent API instead: $builder->addRow()->addCell()->end()
     *             This method will be removed in v1.0.0. Migration guide available in docs/MIGRATION-v042.md
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
        // Deprecation warning
        trigger_error(
            'TableBuilder::createTable() is deprecated since v0.4.2. ' .
            'Use fluent API instead: $builder->addRow()->addCell()->end(). ' .
            'Will be removed in v1.0.0. See docs/MIGRATION-v042.md for migration guide.',
            E_USER_DEPRECATED
        );

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

            // 4. Validate template file exists
            if (!file_exists($templatePath)) {
                throw new ContentControlException(
                    "Template file not found: {$templatePath}"
                );
            }

            // 5. Create temporary copy for modification
            $tempModified = tempnam(sys_get_temp_dir(), 'tablebuilder_modified_') . '.docx';
            if (!copy($templatePath, $tempModified)) {
                throw new ContentControlException(
                    "Failed to create temporary copy of template"
                );
            }

            try {
                // 6. Open temporary copy for modification
                $zip = new \ZipArchive();
                if ($zip->open($tempModified) !== true) {
                    throw new ContentControlException(
                        "Failed to open template copy as ZIP: {$tempModified}"
                    );
                }

                try {
                    // 7. Read document.xml from template
                    $documentXml = $zip->getFromName('word/document.xml');
                    if ($documentXml === false) {
                        throw new ContentControlException(
                            "word/document.xml not found in template: {$templatePath}"
                        );
                    }

                    // 8. Parse as DOM
                    $dom = new \DOMDocument();
                    if (!$dom->loadXML($documentXml)) {
                        throw new ContentControlException(
                            'Failed to parse template document.xml as XML'
                        );
                    }

                    // 9. Create ContentProcessor for SDT operations
                    $processor = new ContentProcessor($templatePath);

                    // 10. Locate target SDT by tag
                    $sdtData = $processor->findSdt($targetSdtTag);
                    if ($sdtData === null) {
                        throw new ContentControlException(
                            "SDT with tag '{$targetSdtTag}' not found in template"
                        );
                    }

                    // 11. Create XPath with namespaces
                    $xpath = $processor->createXPathForDom($dom);

                    // 12. Locate SDT element in DOM using XPath
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

                    // 13. Locate w:sdtContent child
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

                    // 14. Clear current content
                    while ($sdtContent->firstChild !== null) {
                        $sdtContent->removeChild($sdtContent->firstChild);
                    }

                    // 15. Parse table XML as fragment and wrap with proper namespace
                    // Note: We need to wrap the XML in a temporary element with namespace declaration
                    // because createDocumentFragment() doesn't preserve namespace context
                    $wrappedXml = '<root xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' 
                        . $tableXml . '</root>';
                    
                    $tempDom = new \DOMDocument();
                    if (!$tempDom->loadXML($wrappedXml)) {
                        throw new ContentControlException(
                            'Failed to parse wrapped table XML'
                        );
                    }

                    // Get the table element (first child of root)
                $documentElement = $tempDom->documentElement;
                if ($documentElement === null) {
                    throw new ContentControlException('Root element not found in temporary DOM');
                }
                
                $tableElement = $documentElement->firstChild;
                if ($tableElement === null) {
                    throw new ContentControlException(
                        'No table element found in wrapped XML'
                    );
                }

                // 16. Import table into main DOM
                $importedTable = $dom->importNode($tableElement, true);
                // @phpstan-ignore-next-line identical.alwaysFalse (DOMDocument::importNode() can return false per PHP docs)
                if ($importedTable === false) {
                    throw new ContentControlException(
                        'Failed to import table XML into template DOM'
                    );
                }
                /** @var \DOMNode $importedTable */

                $sdtContent->appendChild($importedTable);

                // 17. Serialize modified DOM
                $modifiedXml = $dom->saveXML();
                if ($modifiedXml === false) {
                    throw new ContentControlException(
                        'Failed to serialize modified template XML'
                    );
                }

                // 18. Write back to ZIP
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

                } finally {
                    $zip->close();
                }

                // 18. Copy modified file back to original template
                if (!copy($tempModified, $templatePath)) {
                    throw new ContentControlException(
                        "Failed to copy modified template back to: {$templatePath}"
                    );
                }

                // 19. Mark template as modified
                $processor->markModified($templatePath);

            } finally {
                // Cleanup temporary modified file
                if (file_exists($tempModified)) {
                    @unlink($tempModified);
                }
            }

        } finally {
            // 20. Guaranteed cleanup of temp file
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
     * @deprecated since 0.4.2, use ElementIdentifier::generateTableHash() instead.
     *             This method will be removed in v1.0.0.
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
    private function generateTableHash(Table $table): string // @phpstan-ignore-line method.unused
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
            try {
                $targetHash = ElementIdentifier::generateTableHash($table);
            } catch (\RuntimeException $e) {
                throw new ContentControlException(
                    "Failed to generate table hash: " . $e->getMessage(),
                    0,
                    $e
                );
            }

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
                            
                            // Generate UUID v5 hash using same algorithm as ElementIdentifier
                            $dimensionString = "{$rowCount}x{$cellCount}";
                            $namespace = \Ramsey\Uuid\Uuid::NAMESPACE_DNS;
                            $currentHash = \Ramsey\Uuid\Uuid::uuid5($namespace, "contentcontrol:table:{$dimensionString}")->toString();

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

            // 9. Check if table is wrapped in SDT - if so, serialize the SDT instead
            $elementToSerialize = $matchingTable;
            $parent = $matchingTable->parentNode;
            
            // Check if parent is w:sdtContent (table is wrapped in SDT)
            if ($parent instanceof \DOMElement && $parent->localName === 'sdtContent') {
                // Get the w:sdt grandparent
                $sdtElement = $parent->parentNode;
                if ($sdtElement instanceof \DOMElement && $sdtElement->localName === 'sdt') {
                    // Serialize the entire SDT structure instead of just the table
                    $elementToSerialize = $sdtElement;
                }
            }

            // 10. Serialize element (table or wrapping SDT) to XML
            $tableXml = $dom->saveXML($elementToSerialize);
            if ($tableXml === false) {
                throw new ContentControlException(
                    "Failed to serialize table XML"
                );
            }

            // 11. Clean redundant namespace declarations
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
     * Get temporary file path for intermediate processing
     *
     * Generates a unique temporary file path with .docx extension.
     * Used for saving table before extraction.
     *
     * @return string Absolute path to temporary file
     *
     * @throws ContentControlException If temp file creation fails
     *
     * @since 0.4.2
     */
    private function getTempFilePath(): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'tbl_');
        
        if ($tempPath === false) {
            throw new ContentControlException(
                'Failed to create temporary file for table extraction'
            );
        }

        return $tempPath . '.docx';
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

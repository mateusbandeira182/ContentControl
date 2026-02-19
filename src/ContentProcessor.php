<?php

declare(strict_types=1);

namespace MkGrow\ContentControl;

use PhpOffice\PhpWord\Element\AbstractElement;
use MkGrow\ContentControl\Bridge\TableBuilder;
use MkGrow\ContentControl\Exception\ZipArchiveException;
use MkGrow\ContentControl\Exception\DocumentNotFoundException;

/**
 * Content Control Manipulator for Existing DOCX Documents
 *
 * Allows opening, locating, and modifying Content Controls (SDTs) in existing
 * Word (.docx) documents, following ISO/IEC 29500-1:2016 §17.5.2 standard.
 *
 * Inspired by PhpWord\TemplateProcessor, but focused on SDTs instead of
 * text placeholders.
 *
 * @package MkGrow\ContentControl
 * @since 1.0.0
 *
 * @example
 * ```php
 * $processor = new ContentProcessor('template.docx');
 * $processor->replaceContent('customer-name', 'Acme Corp');
 * $processor->save('output.docx');
 * ```
 *
 * @final This class cannot be extended
 */
final class ContentProcessor
{
    /**
     * WordprocessingML namespace URI
     */
    private const WORDML_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Office Document Relationships namespace URI
     */
    private const RELS_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * VML namespace URI (for images)
     */
    private const VML_NAMESPACE = 'urn:schemas-microsoft-com:vml';

    /**
     * ZIP archive instance of the DOCX file
     *
     * @var \ZipArchive
     */
    private \ZipArchive $zip;

    /**
     * Absolute path to the original DOCX file
     *
     * @var string
     */
    private string $documentPath;

    /**
     * Cache of loaded DOMs (lazy loading)
     *
     * Key: Relative path in ZIP (e.g., 'word/document.xml')
     * Value: DOMDocument instance
     *
     * @var array<string, \DOMDocument>
     */
    private array $domCache = [];

    /**
     * Modified files tracker (write-through cache)
     *
     * Key: Relative path in ZIP
     * Value: true (presence indicates modification)
     *
     * @var array<string, true>
     */
    private array $modifiedFiles = [];

    /**
     * Track if ZIP archive has been closed
     *
     * @var bool
     */
    private bool $zipClosed = false;

    /**
     * ContentProcessor Constructor
     *
     * Opens an existing DOCX file and validates its structure.
     * The document.xml file is eagerly loaded; headers/footers use lazy loading.
     *
     * @param string $documentPath Absolute or relative path to DOCX file
     *
     * @throws \InvalidArgumentException If file does not exist or is not readable
     * @throws ZipArchiveException If file is not a valid ZIP archive
     * @throws DocumentNotFoundException If word/document.xml is missing
     * @throws \RuntimeException If document.xml contains malformed XML
     */
    public function __construct(string $documentPath)
    {
        // 1. Validate file existence and readability
        if (!file_exists($documentPath)) {
            throw new \InvalidArgumentException(
                "File does not exist: {$documentPath}"
            );
        }

        if (!is_readable($documentPath)) {
            throw new \InvalidArgumentException(
                "File is not readable: {$documentPath}"
            );
        }

        $realPath = realpath($documentPath);
        if ($realPath === false) {
            throw new \InvalidArgumentException(
                "Failed to resolve absolute path: {$documentPath}"
            );
        }
        $this->documentPath = $realPath;

        // 2. Open as ZIP archive
        $this->zip = new \ZipArchive();
        $result = $this->zip->open($this->documentPath);

        if ($result !== true) {
            throw new ZipArchiveException(
                $result,
                $documentPath
            );
        }

        // 3. Verify word/document.xml presence
        $documentXml = $this->zip->getFromName('word/document.xml');

        if ($documentXml === false) {
            $this->zip->close();
            throw new DocumentNotFoundException(
                'word/document.xml',
                $this->documentPath
            );
        }

        // 4. Load document.xml as DOM (eager loading)
        $this->domCache['word/document.xml'] = $this->loadXmlAsDom($documentXml);
    }

    /**
     * Destructor - ensures ZIP archive is closed
     *
     * @return void
     */
    public function __destruct()
    {
        if (isset($this->zip) && !$this->zipClosed) {
            $this->zip->close();
            $this->zipClosed = true;
        }
    }

    /**
     * Find SDT by tag in document
     *
     * Exposes internal SDT search functionality for extensions like TableBuilder.
     * Searches in order: document.xml → headers → footers.
     *
     * @param string $tag The tag value to search for
     *
     * @return array{dom: \DOMDocument, sdt: \DOMElement, file: string}|null
     *         Array containing DOM document, SDT element, and file path; null if not found
     *
     * @since 0.4.0
     *
     * @example
     * ```php
     * $result = $processor->findSdt('invoice-total');
     * if ($result !== null) {
     *     ['dom' => $dom, 'sdt' => $sdt, 'file' => $file] = $result;
     *     // Manipulate SDT element as needed
     * }
     * ```
     */
    public function findSdt(string $tag): ?array
    {
        return $this->findSdtByTag($tag);
    }

    /**
     * Create configured XPath object for DOM document
     *
     * Creates XPath instance with all necessary OOXML namespaces registered:
     * - w: WordprocessingML
     * - r: Relationships
     * - v: VML (for images)
     *
     * @param \DOMDocument $dom The DOM document to create XPath for
     *
     * @return \DOMXPath XPath instance with namespaces registered
     *
     * @since 0.4.0
     *
     * @example
     * ```php
     * $result = $processor->findSdt('table-container');
     * $xpath = $processor->createXPathForDom($result['dom']);
     * $content = $xpath->query('.//w:sdtContent', $result['sdt'])->item(0);
     * ```
     */
    public function createXPathForDom(\DOMDocument $dom): \DOMXPath
    {
        return $this->createXPath($dom);
    }

    /**
     * Mark file as modified for subsequent save operation
     *
     * Notifies the processor that a specific file within the DOCX archive
     * has been modified and should be written back on save().
     *
     * Used by extensions that directly manipulate DOM elements.
     *
     * @param string $filePath Relative path within DOCX (e.g., 'word/document.xml')
     *
     * @return void
     *
     * @since 0.4.0
     *
     * @example
     * ```php
     * $result = $processor->findSdt('content');
     * // ... modify $result['dom'] ...
     * $processor->markModified($result['file']);
     * $processor->save('output.docx');
     * ```
     */
    public function markModified(string $filePath): void
    {
        $this->markFileAsModified($filePath);
    }

    /**
     * Load XML string as DOMDocument
     *
     * @param string $xmlContent XML content to parse
     *
     * @return \DOMDocument Parsed DOM tree
     *
     * @throws \RuntimeException If XML is malformed
     */
    private function loadXmlAsDom(string $xmlContent): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        // Suppress XML parsing warnings
        $previousValue = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xmlContent);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousValue);

        if (!$loaded) {
            $errorMessages = array_map(
                fn($error) => trim($error->message),
                $errors
            );
            throw new \RuntimeException(
                'Failed to parse XML: ' . implode('; ', $errorMessages)
            );
        }

        return $dom;
    }

    /**
     * Locate SDT by tag attribute across multiple XML files
     *
     * Searches in order: document.xml → headers → footers
     * If tag is duplicated, returns first occurrence.
     *
     * @param string $tag Value of <w:tag w:val="..."/> attribute
     *
     * @return array{dom: \DOMDocument, sdt: \DOMElement, file: string}|null
     *         Array with DOM, SDT element, and file path, or null if not found
     */
    private function findSdtByTag(string $tag): ?array
    {
        // 1. Search in document.xml (80% of cases)
        $result = $this->searchSdtInFile('word/document.xml', $tag);
        if ($result !== null) {
            return $result;
        }

        // 2. Search in headers
        foreach ($this->discoverHeaderFooterFiles('header') as $headerFile) {
            $result = $this->searchSdtInFile($headerFile, $tag);
            if ($result !== null) {
                return $result;
            }
        }

        // 3. Search in footers
        foreach ($this->discoverHeaderFooterFiles('footer') as $footerFile) {
            $result = $this->searchSdtInFile($footerFile, $tag);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Search for SDT in specific XML file
     *
     * @param string $xmlPath Relative path in ZIP (e.g., 'word/document.xml')
     * @param string $tag Tag value to search for
     *
     * @return array{dom: \DOMDocument, sdt: \DOMElement, file: string}|null
     *         Array with DOM, SDT element, and file path, or null if not found
     */
    private function searchSdtInFile(string $xmlPath, string $tag): ?array
    {
        $dom = $this->getOrLoadDom($xmlPath);
        $xpath = $this->createXPath($dom);

        $escapedTag = $this->escapeXPathValue($tag);
        $query = "//w:sdt[w:sdtPr/w:tag[@w:val={$escapedTag}]]";

        $nodes = $xpath->query($query);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $sdtNode = $nodes->item(0);
        if (!$sdtNode instanceof \DOMElement) {
            return null;
        }

        return [
            'dom' => $dom,
            'sdt' => $sdtNode,
            'file' => $xmlPath,
        ];
    }

    /**
     * Get or load DOM on demand (lazy loading with cache)
     *
     * @param string $xmlPath Relative path in ZIP
     *
     * @return \DOMDocument Cached or newly loaded DOM
     *
     * @throws DocumentNotFoundException If file not found in ZIP
     */
    private function getOrLoadDom(string $xmlPath): \DOMDocument
    {
        if (!isset($this->domCache[$xmlPath])) {
            $xmlContent = $this->zip->getFromName($xmlPath);

            if ($xmlContent === false) {
                throw new DocumentNotFoundException(
                    $xmlPath,
                    $this->documentPath
                );
            }

            $this->domCache[$xmlPath] = $this->loadXmlAsDom($xmlContent);
        }

        return $this->domCache[$xmlPath];
    }

    /**
     * Create DOMXPath instance with registered namespaces
     *
     * @param \DOMDocument $dom DOM to create XPath for
     *
     * @return \DOMXPath XPath instance with registered namespaces
     */
    private function createXPath(\DOMDocument $dom): \DOMXPath
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORDML_NAMESPACE);
        $xpath->registerNamespace('r', self::RELS_NAMESPACE);
        $xpath->registerNamespace('v', self::VML_NAMESPACE);
        return $xpath;
    }

    /**
     * Escape value for safe use in XPath expression
     *
     * Handles special characters, particularly single quotes.
     *
     * @param string $value Value to escape
     *
     * @return string Escaped value (already quoted)
     */
    private function escapeXPathValue(string $value): string
    {
        // If contains single quotes, use concat()
        if (strpos($value, "'") !== false) {
            $parts = explode("'", $value);
            $escaped = array_map(fn($part) => "'{$part}'", $parts);
            return "concat(" . implode(", \"'\", ", $escaped) . ")";
        }

        return "'" . $value . "'";
    }

    /**
     * Discover header/footer files in the document
     *
     * @param string $type 'header' or 'footer'
     *
     * @return array<string> List of file paths (e.g., ['word/header1.xml', 'word/header2.xml'])
     */
    private function discoverHeaderFooterFiles(string $type): array
    {
        $files = [];
        $pattern = "word/{$type}";

        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $fileName = $this->zip->getNameIndex($i);
            if ($fileName === false) {
                continue;
            }
            if (strpos($fileName, $pattern) === 0 && str_ends_with($fileName, '.xml')) {
                $files[] = $fileName;
            }
        }

        return $files;
    }

    /**
     * Mark file as modified for write-through cache
     *
     * @param string $filePath Relative path in ZIP
     *
     * @return void
     */
    private function markFileAsModified(string $filePath): void
    {
        $this->modifiedFiles[$filePath] = true;
    }

    /**
     * Replace ALL content of a Content Control
     *
     * Removes all current content from <w:sdtContent> and inserts new content.
     * Preserves <w:sdtPr> (SDT properties).
     *
     * @param string $tag Value of <w:tag w:val="..."/> attribute
     * @param string|AbstractElement|TableBuilder $value New content
     *
     * @return bool True if SDT found and modified, false otherwise
     *
     * @example
     * ```php
     * // Replace with simple text
     * $processor->replaceContent('customer-name', 'Acme Corporation');
     *
     * // Replace with table
     * $table = $section->addTable();
     * $processor->replaceContent('invoice-items', $table);
     *
     * // Replace with TableBuilder (SDT-aware, v0.7.0)
     * $builder = new TableBuilder();
     * $row = $builder->addRow();
     * $row->addCell(3000)->addText('Value');
     * $processor->replaceContent('table-placeholder', $builder);
     * ```
     */
    public function replaceContent(string $tag, string|AbstractElement|TableBuilder $value): bool
    {
        // 1. Locate SDT
        $result = $this->findSdtByTag($tag);

        if ($result === null) {
            return false;
        }

        $dom = $result['dom'];
        $sdtElement = $result['sdt'];
        $filePath = $result['file'];

        // 2. Locate <w:sdtContent>
        $xpath = $this->createXPath($dom);
        $sdtContentNodes = $xpath->query('.//w:sdtContent', $sdtElement);

        if ($sdtContentNodes === false || $sdtContentNodes->length === 0) {
            throw new \RuntimeException(
                "SDT with tag '{$tag}' has no <w:sdtContent> element"
            );
        }

        $sdtContent = $sdtContentNodes->item(0);
        if (!$sdtContent instanceof \DOMElement) {
            throw new \RuntimeException(
                "SDT with tag '{$tag}' has invalid <w:sdtContent> structure"
            );
        }

        // 3. Remove all current children
        while ($sdtContent->firstChild) {
            $sdtContent->removeChild($sdtContent->firstChild);
        }

        // 4. Insert new content
        if ($value instanceof TableBuilder) {
            $this->insertTableBuilderContent($dom, $sdtContent, $value);
        } elseif (is_string($value)) {
            $this->insertTextContent($dom, $sdtContent, $value);
        } else {
            $this->insertElementContent($dom, $sdtContent, $value);
        }

        // 5. Mark file as modified
        $this->markFileAsModified($filePath);

        return true;
    }

    /**
     * Append content to the end of a Content Control
     *
     * Adds new content after existing content in <w:sdtContent>.
     * For tables, if SDT contains a <w:tbl>, the element should be a row.
     * No type validation is performed in v1.0 - user responsibility.
     *
     * @param string $tag Value of <w:tag w:val="..."/> attribute
     * @param AbstractElement $element PHPWord element to append
     *
     * @return bool True if SDT found and modified, false otherwise
     *
     * @example
     * ```php
     * // Append row to table SDT
     * $row = $table->addRow();
     * $row->addCell(2000)->addText('New item');
     * $processor->appendContent('invoice-items', $row);
     * ```
     */
    public function appendContent(string $tag, AbstractElement $element): bool
    {
        // 1. Locate SDT
        $result = $this->findSdtByTag($tag);

        if ($result === null) {
            return false;
        }

        $dom = $result['dom'];
        $sdtElement = $result['sdt'];
        $filePath = $result['file'];

        // 2. Locate <w:sdtContent>
        $xpath = $this->createXPath($dom);
        $sdtContentNodes = $xpath->query('.//w:sdtContent', $sdtElement);

        if ($sdtContentNodes === false || $sdtContentNodes->length === 0) {
            throw new \RuntimeException(
                "SDT with tag '{$tag}' has no <w:sdtContent> element"
            );
        }

        $sdtContent = $sdtContentNodes->item(0);
        if (!$sdtContent instanceof \DOMElement) {
            throw new \RuntimeException(
                "SDT with tag '{$tag}' has invalid <w:sdtContent> structure"
            );
        }

        // 3. Serialize and append element
        $this->insertElementContent($dom, $sdtContent, $element);

        // 4. Mark file as modified
        $this->markFileAsModified($filePath);

        return true;
    }

    /**
     * Replace GROUP Content Control with complex structure preserving nested SDTs
     *
     * Designed specifically for GROUP-type Content Controls (w:group) that need
     * to be replaced with complex structures containing multiple elements with
     * their own nested SDTs.
     *
     * **Differences from replaceContent():**
     * - Validates SDT is GROUP type (throws InvalidArgumentException if not)
     * - Serializes ContentControl via temporary file to preserve ALL registered SDTs
     * - Supports unlimited nesting depth (SDTs within SDTs within SDTs...)
     * - Performance trade-off: ~150ms vs ~20ms for replaceContent()
     *
     * **Use Cases:**
     * - Invoice templates: Replace GROUP placeholder with invoice table + nested price SDTs
     * - Report generation: Replace section placeholder with multi-paragraph content + charts
     * - Form builders: Replace container with dynamic form fields (each field is an SDT)
     *
     * **Process:**
     * 1. Locate SDT by tag using findSdtByTag()
     * 2. Validate SDT has <w:sdtPr><w:group/></w:sdtPr> (GROUP type)
     * 3. Serialize ContentControl structure to XML (calls serializeContentControlWithSdts)
     * 4. Create DOMDocumentFragment from serialized XML
     * 5. Clear existing <w:sdtContent> children
     * 6. Append fragment to <w:sdtContent>
     * 7. Mark file as modified
     *
     * **Performance:**
     * - Temp file I/O: ~100ms
     * - XML parsing/manipulation: ~50ms
     * - Total: ~150ms (acceptable for complex structures)
     *
     * @param string $tag Value of <w:tag w:val="..."/> attribute
     * @param ContentControl $structure ContentControl instance with registered SDTs
     *
     * @return bool True if replacement successful, false if SDT not found
     *
     * @throws \InvalidArgumentException If SDT is not GROUP type
     * @throws \RuntimeException If SDT has no <w:sdtContent> element
     * @throws \RuntimeException If serialization or parsing fails
     *
     * @since 0.4.2
     *
     * @example
     * ```php
     * // Create complex structure with nested SDTs
     * $cc = new ContentControl();
     * $section = $cc->addSection();
     * 
     * // Add header paragraph with locked SDT
     * $header = $section->addText('Invoice Details', ['bold' => true, 'size' => 16]);
     * $cc->addContentControl($header, [
     *     'tag' => 'invoice-header',
     *     'lockType' => ContentControl::LOCK_CONTENT_LOCKED
     * ]);
     * 
     * // Add table with price SDTs in cells
     * $table = $section->addTable();
     * $row = $table->addRow();
     * $cell1 = $row->addCell(3000);
     * $priceText = $cell1->addText('$1,200.00');
     * $cc->addContentControl($priceText, ['tag' => 'item-price', 'alias' => 'Unit Price']);
     * 
     * // Replace GROUP placeholder with entire structure
     * $processor = new ContentProcessor('template.docx');
     * $processor->replaceGroupContent('invoice-section', $cc);
     * $processor->save('output.docx');
     * 
     * // Result: GROUP SDT now contains paragraph + table, both inner SDTs preserved
     * ```
     */
    public function replaceGroupContent(string $tag, ContentControl $structure): bool
    {
        // 1. Locate SDT
        $result = $this->findSdtByTag($tag);

        if ($result === null) {
            return false;
        }

        $dom = $result['dom'];
        $sdtElement = $result['sdt'];
        $filePath = $result['file'];

        // 2. Validate GROUP type
        $xpath = $this->createXPath($dom);
        $groupNodes = $xpath->query('.//w:sdtPr/w:group', $sdtElement);

        if ($groupNodes === false || $groupNodes->length === 0) {
            throw new \InvalidArgumentException(
                "SDT with tag '{$tag}' is not a GROUP type Content Control. " .
                "Use replaceContent() for non-GROUP SDTs."
            );
        }

        // 3. Locate <w:sdtContent>
        $sdtContentNodes = $xpath->query('.//w:sdtContent', $sdtElement);

        if ($sdtContentNodes === false || $sdtContentNodes->length === 0) {
            throw new \RuntimeException(
                "SDT with tag '{$tag}' has no <w:sdtContent> element"
            );
        }

        $sdtContent = $sdtContentNodes->item(0);
        if (!$sdtContent instanceof \DOMElement) {
            throw new \RuntimeException(
                "SDT with tag '{$tag}' has invalid <w:sdtContent> structure"
            );
        }

        // 4. Serialize ContentControl structure with nested SDTs
        $complexXml = $this->serializeContentControlWithSdts($structure);

        // 5. Clear existing <w:sdtContent> children
        while ($sdtContent->firstChild !== null) {
            $sdtContent->removeChild($sdtContent->firstChild);
        }

        // 6. Create fragment and append to <w:sdtContent>
        $fragment = $this->createDomFragment($dom, $complexXml);
        $sdtContent->appendChild($fragment);

        // 7. Mark file as modified
        $this->markFileAsModified($filePath);

        return true;
    }

    /**
     * Remove all content from a Content Control
     *
     * Clears <w:sdtContent> but preserves the SDT structure and properties.
     * Useful for resetting templates or clearing fields.
     *
     * @param string $tag Value of <w:tag w:val="..."/> attribute
     *
     * @return bool True if SDT found and cleared, false otherwise
     *
     * @example
     * ```php
     * // Clear customer name field
     * $processor->removeContent('customer-name');
     * ```
     */
    public function removeContent(string $tag): bool
    {
        // 1. Locate SDT
        $result = $this->findSdtByTag($tag);

        if ($result === null) {
            return false;
        }

        $dom = $result['dom'];
        $sdtElement = $result['sdt'];
        $filePath = $result['file'];

        // 2. Locate <w:sdtContent>
        $xpath = $this->createXPath($dom);
        $sdtContentNodes = $xpath->query('.//w:sdtContent', $sdtElement);

        if ($sdtContentNodes === false || $sdtContentNodes->length === 0) {
            throw new \RuntimeException(
                "SDT with tag '{$tag}' has no <w:sdtContent> element"
            );
        }

        $sdtContent = $sdtContentNodes->item(0);
        if (!$sdtContent instanceof \DOMElement) {
            throw new \RuntimeException(
                "SDT with tag '{$tag}' has invalid <w:sdtContent> structure"
            );
        }

        // 3. Remove all children
        while ($sdtContent->firstChild) {
            $sdtContent->removeChild($sdtContent->firstChild);
        }

        // 4. Mark file as modified
        $this->markFileAsModified($filePath);

        return true;
    }

    /**
     * Replace text content while preserving formatting
     *
     * Searches for all <w:t> nodes within <w:sdtContent> and replaces their
     * text content while preserving parent <w:r> (run) properties.
     * If multiple <w:t> nodes exist, consolidates into first and removes others.
     *
     * @param string $tag Value of <w:tag w:val="..."/> attribute
     * @param string $value New text value
     *
     * @return bool True if SDT found and modified, false otherwise
     *
     * @example
     * ```php
     * // Replace formatted text (preserves bold, italic, color, etc.)
     * $processor->setValue('customer-name', 'Acme Corporation');
     * ```
     */
    public function setValue(string $tag, string $value): bool
    {
        // 1. Locate SDT
        $result = $this->findSdtByTag($tag);

        if ($result === null) {
            return false;
        }

        $dom = $result['dom'];
        $sdtElement = $result['sdt'];
        $filePath = $result['file'];

        // 2. Locate <w:sdtContent>
        $xpath = $this->createXPath($dom);
        $sdtContentNodes = $xpath->query('.//w:sdtContent', $sdtElement);

        if ($sdtContentNodes === false || $sdtContentNodes->length === 0) {
            throw new \RuntimeException(
                "SDT with tag '{$tag}' has no <w:sdtContent> element"
            );
        }

        $sdtContent = $sdtContentNodes->item(0);
        if (!$sdtContent instanceof \DOMElement) {
            throw new \RuntimeException(
                "SDT with tag '{$tag}' has invalid <w:sdtContent> structure"
            );
        }

        // 3. Find all <w:t> nodes
        $textNodes = $xpath->query('.//w:t', $sdtContent);

        if ($textNodes === false || $textNodes->length === 0) {
            // No text nodes - fall back to replaceContent behavior
            while ($sdtContent->firstChild) {
                $sdtContent->removeChild($sdtContent->firstChild);
            }
            $this->insertTextContent($dom, $sdtContent, $value);
        } else {
            // Replace first <w:t> node content
            $firstTextNode = $textNodes->item(0);
            if ($firstTextNode instanceof \DOMElement) {
                // Clear and set new text
                while ($firstTextNode->firstChild) {
                    $firstTextNode->removeChild($firstTextNode->firstChild);
                }
                $firstTextNode->appendChild($dom->createTextNode($value));

                // Add xml:space="preserve" if needed
                if (preg_match('/^\s|\s$/', $value) === 1) {
                    $firstTextNode->setAttributeNS(
                        'http://www.w3.org/XML/1998/namespace',
                        'xml:space',
                        'preserve'
                    );
                }
            }

            // Remove remaining <w:t> nodes (consolidate to first)
            for ($i = 1; $i < $textNodes->length; $i++) {
                $textNode = $textNodes->item($i);
                if ($textNode instanceof \DOMElement && $textNode->parentNode !== null) {
                    $parent = $textNode->parentNode;
                    $parent->removeChild($textNode);
                    
                    // Remove parent <w:r> if now empty
                    if ($parent->childNodes->length === 0 && $parent->parentNode !== null) {
                        $parent->parentNode->removeChild($parent);
                    }
                }
            }
        }

        // 4. Mark file as modified
        $this->markFileAsModified($filePath);

        return true;
    }

    /**
     * Remove all Content Controls from document
     *
     * Searches all XML files (document, headers, footers) and removes
     * <w:sdtContent> content from all SDTs. Optionally blocks editing
     * by adding document protection.
     *
     * @param bool $block If true, adds readOnly protection to document
     *
     * @return int Count of SDTs removed
     *
     * @example
     * ```php
     * // Remove all SDT contents and block editing
     * $count = $processor->removeAllControlContents(true);
     * echo "Removed {$count} Content Controls";
     * ```
     */
    public function removeAllControlContents(bool $block = false): int
    {
        $count = 0;

        // 1. Process document.xml
        $count += $this->removeAllSdtsInFile('word/document.xml');

        // 2. Process headers
        foreach ($this->discoverHeaderFooterFiles('header') as $headerFile) {
            $count += $this->removeAllSdtsInFile($headerFile);
        }

        // 3. Process footers
        foreach ($this->discoverHeaderFooterFiles('footer') as $footerFile) {
            $count += $this->removeAllSdtsInFile($footerFile);
        }

        // 4. Add document protection if requested
        if ($block) {
            $this->addDocumentProtection();
        }

        return $count;
    }

    /**
     * Remove all SDTs in specific file
     *
     * @param string $xmlPath Relative path in ZIP
     *
     * @return int Count of SDTs removed
     */
    private function removeAllSdtsInFile(string $xmlPath): int
    {
        try {
            $dom = $this->getOrLoadDom($xmlPath);
        } catch (DocumentNotFoundException) {
            // File doesn't exist - skip
            return 0;
        }

        $xpath = $this->createXPath($dom);
        $sdtNodes = $xpath->query('//w:sdt');

        if ($sdtNodes === false || $sdtNodes->length === 0) {
            return 0;
        }

        // Convert NodeList to array to avoid iterator issues in PHP 8.2
        $sdtArray = [];
        foreach ($sdtNodes as $node) {
            $sdtArray[] = $node;
        }

        $count = 0;
        foreach ($sdtArray as $sdtNode) {
            if (!$sdtNode instanceof \DOMElement) {
                continue;
            }

            // PHP 8.2 compatibility: Skip if node was already removed (nested SDT case)
            // Accessing properties on a removed node throws Error in PHP 8.2
            try {
                $owner = $sdtNode->ownerDocument;
                $parent = $sdtNode->parentNode;
                
                if ($owner === null || $parent === null) {
                    continue;
                }
                
                // Find <w:sdtContent> using getElementsByTagNameNS
                // @phpstan-ignore-next-line catch.neverThrown (Error is thrown in PHP 8.2 for removed nodes)
                $sdtContentNodes = $sdtNode->getElementsByTagNameNS(self::WORDML_NAMESPACE, 'sdtContent');
            } catch (\Error $e) {
                // Node no longer exists in document (was removed as nested SDT)
                continue;
            }
            if ($sdtContentNodes->length === 0) {
                continue;
            }

            $sdtContent = $sdtContentNodes->item(0);
            if (!$sdtContent instanceof \DOMElement) {
                continue;
            }

            // Remove all children
            while ($sdtContent->firstChild) {
                $sdtContent->removeChild($sdtContent->firstChild);
            }

            $count++;
        }

        if ($count > 0) {
            $this->markFileAsModified($xmlPath);
        }

        return $count;
    }

    /**
     * Add document protection (readOnly mode)
     *
     * Creates or modifies word/settings.xml to add:
     * <w:documentProtection w:edit="readOnly" w:enforcement="1"/>
     *
     * @return void
     */
    private function addDocumentProtection(): void
    {
        $settingsPath = 'word/settings.xml';

        // Try to load existing settings.xml
        try {
            $dom = $this->getOrLoadDom($settingsPath);
            $xpath = $this->createXPath($dom);

            // Check if protection already exists
            $protectionNodes = $xpath->query('//w:documentProtection');
            if ($protectionNodes !== false && $protectionNodes->length > 0) {
                // Update existing
                $protection = $protectionNodes->item(0);
                if ($protection instanceof \DOMElement) {
                    $protection->setAttributeNS(self::WORDML_NAMESPACE, 'w:edit', 'readOnly');
                    $protection->setAttributeNS(self::WORDML_NAMESPACE, 'w:enforcement', '1');
                }
            } else {
                // Add new protection element
                $settingsNode = $xpath->query('//w:settings')->item(0);
                if ($settingsNode instanceof \DOMElement) {
                    $protection = $dom->createElementNS(self::WORDML_NAMESPACE, 'w:documentProtection');
                    $protection->setAttributeNS(self::WORDML_NAMESPACE, 'w:edit', 'readOnly');
                    $protection->setAttributeNS(self::WORDML_NAMESPACE, 'w:enforcement', '1');
                    $settingsNode->appendChild($protection);
                }
            }
        } catch (DocumentNotFoundException) {
            // Create minimal settings.xml
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $settings = $dom->createElementNS(self::WORDML_NAMESPACE, 'w:settings');
            $settings->setAttributeNS(
                'http://www.w3.org/2000/xmlns/',
                'xmlns:w',
                self::WORDML_NAMESPACE
            );

            $protection = $dom->createElementNS(self::WORDML_NAMESPACE, 'w:documentProtection');
            $protection->setAttributeNS(self::WORDML_NAMESPACE, 'w:edit', 'readOnly');
            $protection->setAttributeNS(self::WORDML_NAMESPACE, 'w:enforcement', '1');

            $settings->appendChild($protection);
            $dom->appendChild($settings);

            $this->domCache[$settingsPath] = $dom;
        }

        $this->markFileAsModified($settingsPath);
    }

    /**
     * Insert simple text content into <w:sdtContent>
     *
     * Creates structure: <w:p><w:r><w:t>text</w:t></w:r></w:p>
     *
     * @param \DOMDocument $dom DOM document
     * @param \DOMElement $sdtContent <w:sdtContent> node
     * @param string $text Text to insert
     *
     * @return void
     */
    private function insertTextContent(\DOMDocument $dom, \DOMElement $sdtContent, string $text): void
    {
        $nsUri = self::WORDML_NAMESPACE;

        // Create <w:p>
        $p = $dom->createElementNS($nsUri, 'w:p');

        // Create <w:r>
        $r = $dom->createElementNS($nsUri, 'w:r');

        // Create <w:t> with text
        $t = $dom->createElementNS($nsUri, 'w:t');
        $t->appendChild($dom->createTextNode($text));

        // Add xml:space="preserve" attribute if text has leading/trailing spaces
        if (preg_match('/^\s|\s$/', $text) === 1) {
            $t->setAttributeNS(
                'http://www.w3.org/XML/1998/namespace',
                'xml:space',
                'preserve'
            );
        }

        $r->appendChild($t);
        $p->appendChild($r);
        $sdtContent->appendChild($p);
    }

    /**
     * Insert PHPWord element content into <w:sdtContent>
     *
     * Uses PHPWord's internal writers via reflection to serialize element.
     *
     * @param \DOMDocument $dom DOM document
     * @param \DOMElement $sdtContent <w:sdtContent> node
     * @param AbstractElement $element PHPWord element to insert
     *
     * @return void
     *
     * @throws \RuntimeException If element cannot be serialized
     */
    private function insertElementContent(
        \DOMDocument $dom,
        \DOMElement $sdtContent,
        AbstractElement $element
    ): void {
        // 1. Serialize element using PhpWord writers
        $xmlString = $this->serializePhpWordElement($element);

        // 2. Parse XML string as temporary DOM
        $tempDom = new \DOMDocument();
        $loaded = @$tempDom->loadXML('<?xml version="1.0"?><root xmlns:w="' . self::WORDML_NAMESPACE . '">' . $xmlString . '</root>');

        if (!$loaded) {
            throw new \RuntimeException(
                'Failed to parse serialized element XML: ' . get_class($element)
            );
        }

        // 3. Import nodes from temporary DOM to target DOM
        $rootNode = $tempDom->documentElement;
        if ($rootNode === null) {
            throw new \RuntimeException(
                'Failed to get root element from serialized XML'
            );
        }

        foreach ($rootNode->childNodes as $child) {
            $imported = $dom->importNode($child, true);
            $sdtContent->appendChild($imported);
        }
    }

    /**
     * Insert TableBuilder content into SDT using SDT-aware serialization.
     *
     * Serializes the TableBuilder's table with all embedded SDTs preserved
     * via serializeWithSdts(), then imports the XML fragment into the target DOM.
     *
     * @param \DOMDocument $dom Target document DOM
     * @param \DOMElement $sdtContent Target <w:sdtContent> element
     * @param TableBuilder $builder TableBuilder instance with table and SDT registrations
     *
     * @throws Exception\ContentControlException If serialization fails
     *
     * @since 0.7.0
     */
    private function insertTableBuilderContent(
        \DOMDocument $dom,
        \DOMElement $sdtContent,
        TableBuilder $builder
    ): void {
        $tableXml = $builder->serializeWithSdts();
        $fragment = $this->createDomFragment($dom, $tableXml);
        $sdtContent->appendChild($fragment);
    }

    /**
     * Serialize PHPWord element to XML using internal writers
     *
     * Reuses reflection-based approach similar to SDTInjector.
     *
     * @param AbstractElement $element PHPWord element
     *
     * @return string Serialized XML (without declaration)
     *
     * @throws \RuntimeException If serialization fails
     */
    private function serializePhpWordElement(AbstractElement $element): string
    {
        // Create XML buffer
        $xmlWriter = new \PhpOffice\PhpWord\Shared\XMLWriter();
        $xmlWriter->openMemory();

        // Get element class name
        $elementClass = (new \ReflectionClass($element))->getShortName();

        // Build writer class name
        $writerClass = "PhpOffice\\PhpWord\\Writer\\Word2007\\Element\\{$elementClass}";

        if (!class_exists($writerClass)) {
            throw new \RuntimeException(
                "ContentProcessor: No writer found for element type {$elementClass}"
            );
        }

        // Instantiate writer and execute
        try {
            /** @var object $writer */
            $writer = new $writerClass($xmlWriter, $element);
            
            // Call write() method
            $reflection = new \ReflectionObject($writer);
            if (!$reflection->hasMethod('write')) {
                throw new \RuntimeException(
                    "Writer {$writerClass} does not have write() method"
                );
            }
            
            $writeMethod = $reflection->getMethod('write');
            $writeMethod->invoke($writer);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to serialize element {$elementClass}: {$e->getMessage()}",
                0,
                $e
            );
        }

        return $xmlWriter->getData();
    }

    /**
     * Save modified document
     *
     * Updates only modified XML files in ZIP. If $outputPath provided,
     * copies file to new destination. Otherwise, saves in-place.
     *
     * IMPORTANT: ContentProcessor is single-use. After save(), no further
     * modifications are possible (ZIP is closed).
     *
     * @param string $outputPath Destination path (optional). If empty, saves in-place.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If output directory does not exist
     * @throws ZipArchiveException If save operation fails
     *
     * @example
     * ```php
     * // Save in-place
     * $processor->save();
     *
     * // Save to new file
     * $processor->save('output/final.docx');
     * ```
     */
    public function save(string $outputPath = ''): void
    {
        // 1. Update modified files in ZIP
        foreach ($this->modifiedFiles as $xmlPath => $_) {
            if (!isset($this->domCache[$xmlPath])) {
                continue; // Skip if DOM was not loaded
            }

            $dom = $this->domCache[$xmlPath];

            // Serialize DOM
            $xml = $dom->saveXML();

            if ($xml === false) {
                throw new \RuntimeException(
                    "Failed to serialize DOM for file: {$xmlPath}"
                );
            }

            // Remove duplicate XML declaration (if exists)
            $xml = preg_replace('/<\?xml[^>]+\?>\s*/', '', $xml, 1);
            
            if ($xml === null) {
                throw new \RuntimeException(
                    "Failed to process XML for file: {$xmlPath}"
                );
            }

            // Update in ZIP
            $this->updateXmlInZip($xmlPath, $xml);
        }

        // 2. Close ZIP (persists changes)
        if (!$this->zip->close()) {
            throw new ZipArchiveException(
                \ZipArchive::ER_CLOSE,
                $this->documentPath
            );
        }
        $this->zipClosed = true;

        // 3. If output path provided, copy file
        if ($outputPath !== '') {
            $outputDir = dirname($outputPath);

            if (!is_dir($outputDir)) {
                throw new \InvalidArgumentException(
                    "Output directory does not exist: {$outputDir}"
                );
            }

            if (!copy($this->documentPath, $outputPath)) {
                throw new \RuntimeException(
                    "Failed to copy file to: {$outputPath}"
                );
            }
        }
    }

    /**
     * Update XML file in ZIP archive
     *
     * Removes existing file (if present) and adds new version.
     *
     * @param string $xmlPath Relative path in ZIP
     * @param string $xml XML content
     *
     * @return void
     *
     * @throws ZipArchiveException If operation fails
     */
    private function updateXmlInZip(string $xmlPath, string $xml): void
    {
        // Remove existing file if present
        if ($this->zip->statName($xmlPath) !== false) {
            if (!$this->zip->deleteName($xmlPath)) {
                throw new ZipArchiveException(
                    \ZipArchive::ER_REMOVE,
                    $this->documentPath
                );
            }
        }

        // Add new version
        if (!$this->zip->addFromString($xmlPath, $xml)) {
            throw new ZipArchiveException(
                \ZipArchive::ER_WRITE,
                $this->documentPath
            );
        }
    }

    /**
     * Serialize ContentControl structure with nested SDTs intact
     *
     * Converts a ContentControl instance (with registered SDTs) into XML
     * by saving to a temporary .docx file, extracting word/document.xml,
     * and returning the contents of <w:body> (excluding the body tag itself).
     *
     * This is critical for GROUP SDT replacement where we need to inject
     * complex structures (paragraphs + tables + nested SDTs) into a single
     * Content Control placeholder.
     *
     * **Process:**
     * 1. Create temporary .docx file
     * 2. Save ContentControl (triggers SDTInjector to apply all registered SDTs)
     * 3. Extract word/document.xml from ZIP
     * 4. Parse XML and locate <w:body>
     * 5. Serialize all child nodes of <w:body>
     * 6. Strip redundant xmlns:w declarations (parent document already has namespace)
     * 7. Clean up temp file (guaranteed via finally block)
     *
     * **Performance:**
     * - Temp file I/O: ~100ms for typical documents
     * - Trade-off: Simplicity and reliability over in-memory serialization
     * - Future optimization: In-memory XML writer (deferred to v0.5.0)
     *
     * @param ContentControl $cc ContentControl instance with registered SDTs
     *
     * @return string Serialized XML of <w:body> children (ready for injection)
     *
     * @throws \RuntimeException If temp file creation fails
     * @throws \RuntimeException If ZIP operations fail
     * @throws \RuntimeException If XML parsing fails
     * @throws \RuntimeException If <w:body> element not found
     *
     * @since 0.4.2
     *
     * @example
     * ```php
     * // Create complex structure with nested SDTs
     * $cc = new ContentControl();
     * $section = $cc->addSection();
     * $text = $section->addText('Customer Name');
     * $cc->addContentControl($text, ['tag' => 'name', 'alias' => 'Customer Name']);
     * 
     * $table = $section->addTable();
     * $row = $table->addRow();
     * $cell = $row->addCell(3000);
     * $cellText = $cell->addText('Invoice Total');
     * $cc->addContentControl($cellText, ['tag' => 'total', 'lockType' => ContentControl::LOCK_SDT_LOCKED]);
     * 
     * // Serialize to XML (all SDTs preserved)
     * $xml = $this->serializeContentControlWithSdts($cc);
     * // Returns: <w:p><w:sdt>...</w:sdt></w:p><w:tbl><w:tr><w:tc><w:sdt>...</w:sdt></w:tc></w:tr></w:tbl>
     * ```
     */
    private function serializeContentControlWithSdts(ContentControl $cc): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'cc_serialize_');
        if ($tempFile === false) {
            throw new \RuntimeException('Failed to create temporary file for ContentControl serialization');
        }

        $tempFile .= '.docx';

        try {
            // 1. Save ContentControl to temp file (triggers SDTInjector)
            $cc->save($tempFile);

            // 2. Open as ZIP archive
            $zip = new \ZipArchive();
            if ($zip->open($tempFile) !== true) {
                throw new \RuntimeException("Failed to open temporary file: {$tempFile}");
            }

            // 3. Extract word/document.xml
            $documentXml = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($documentXml === false) {
                throw new \RuntimeException('Failed to extract word/document.xml from temporary file');
            }

            // 4. Parse XML
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = false;

            if (!$dom->loadXML($documentXml)) {
                throw new \RuntimeException('Failed to parse word/document.xml');
            }

            // 5. Locate <w:body>
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('w', self::WORDML_NAMESPACE);

            $bodyNodes = $xpath->query('//w:body');
            if ($bodyNodes === false || $bodyNodes->length === 0) {
                throw new \RuntimeException('No <w:body> element found in document.xml');
            }

            $bodyNode = $bodyNodes->item(0);
            if (!$bodyNode instanceof \DOMElement) {
                throw new \RuntimeException('Invalid <w:body> element structure');
            }

            // 6. Serialize all child nodes (not the body tag itself)
            $contentXml = '';
            foreach ($bodyNode->childNodes as $child) {
                $childXml = $dom->saveXML($child);
                if ($childXml === false) {
                    throw new \RuntimeException('Failed to serialize child node of <w:body>');
                }
                $contentXml .= $childXml;
            }

            // 7. Strip redundant namespace declarations
            // Parent document already declares xmlns:w at <w:document> level
            $contentXml = preg_replace('/\s+xmlns:w="[^"]+"/', '', $contentXml);

            if ($contentXml === null) {
                throw new \RuntimeException('Failed to clean namespace declarations');
            }

            return $contentXml;

        } finally {
            // Guaranteed cleanup
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Create DOMDocumentFragment from XML string
     *
     * Converts raw XML string into a DOMDocumentFragment that can be
     * appended to existing DOM nodes. This is used for injecting complex
     * structures into GROUP SDTs while preserving namespace context.
     *
     * **Process:**
     * 1. Wrap XML in temporary root element with WordML namespace
     * 2. Parse to temporary DOMDocument
     * 3. Create empty DOMDocumentFragment in target document
     * 4. Import all child nodes from temp DOM to fragment
     * 5. Return fragment ready for appendChild()
     *
     * **Why Temporary Root Element?**
     * - DOMDocumentFragment requires well-formed XML
     * - Raw XML may have multiple root elements (multiple paragraphs/tables)
     * - Wrapping ensures single root for parsing
     * - Children extracted after parsing (root discarded)
     *
     * @param \DOMDocument $dom Target document (receives fragment)
     * @param string $xml Raw XML content (may have multiple root elements)
     *
     * @return \DOMDocumentFragment Fragment ready for appendChild()
     *
     * @throws \RuntimeException If XML parsing fails
     * @throws \RuntimeException If root element not found
     * @throws \RuntimeException If node import fails
     *
     * @since 0.4.2
     *
     * @example
     * ```php
     * $xml = '<w:p><w:r><w:t>Text</w:t></w:r></w:p><w:tbl>...</w:tbl>';
     * $fragment = $this->createDomFragment($dom, $xml);
     * $parentElement->appendChild($fragment);  // Injects both paragraph and table
     * ```
     */
    private function createDomFragment(\DOMDocument $dom, string $xml): \DOMDocumentFragment
    {
        // 1. Wrap in temporary root with namespace
        $wrappedXml = sprintf(
            '<root xmlns:w="%s">%s</root>',
            self::WORDML_NAMESPACE,
            $xml
        );

        // 2. Parse to temporary DOM
        $tempDom = new \DOMDocument();
        $tempDom->preserveWhiteSpace = false;
        $tempDom->formatOutput = false;

        if (!$tempDom->loadXML($wrappedXml)) {
            throw new \RuntimeException('Failed to parse XML for DOMDocumentFragment creation');
        }

        // 3. Create fragment in target document
        $fragment = $dom->createDocumentFragment();

        // 4. Get root element
        $rootElement = $tempDom->documentElement;
        if ($rootElement === null) {
            throw new \RuntimeException('Root element not found in temporary DOM');
        }

        // 5. Import all children to fragment
        foreach ($rootElement->childNodes as $child) {
            $importedNode = $dom->importNode($child, true);
            $fragment->appendChild($importedNode);
        }

        return $fragment;
    }
}

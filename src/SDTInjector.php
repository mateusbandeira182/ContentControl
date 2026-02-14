<?php

declare(strict_types=1);

namespace MkGrow\ContentControl;

use MkGrow\ContentControl\Exception\ZipArchiveException;
use MkGrow\ContentControl\Exception\DocumentNotFoundException;

/**
 * Service Layer for injecting Content Controls into DOCX files
 * 
 * Responsible for:
 * - Opening DOCX file as ZIP
 * - Generating Content Controls XML
 * - Injecting XML into document.xml
 * - Updating DOCX file
 * 
 * @since 2.0.0
 */
final class SDTInjector
{
    /**
     * WordprocessingML Namespace as per ISO/IEC 29500-1:2016 §9.3.2.1
     */
    private const WORDML_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Registry of already processed elements (avoids re-wrapping)
     * 
     * @var array<string, true>
     */
    private array $processedElements = [];

    /**
     * DOM Element Locator
     */
    private ElementLocator $locator;

    /**
     * Creates new SDTInjector instance
     */
    public function __construct()
    {
        $this->locator = new ElementLocator();
    }

    /**
     * Injects Content Controls into existing DOCX file (v3.0 - DOM manipulation)
     * 
     * Workflow v3.0:
     * 1. Opens DOCX as ZIP and reads document.xml
     * 2. Loads XML into DOMDocument
     * 3. Sorts elements by depth (Cell before Table)
     * 4. For each element:
     *    a. Locates in DOM using ElementLocator
     *    b. Wraps inline with wrapElementInline()
     *    c. Marks as processed
     * 5. Serializes modified DOM back to document.xml
     * 6. Updates ZIP and saves
     * 
     * @param string $docxPath DOCX file path
     * @param array<int, array{element: mixed, config: SDTConfig}> $sdtTuples Element->config tuples
     * @return void
     * @throws ZipArchiveException If fails to open/manipulate ZIP
     * @throws DocumentNotFoundException If word/document.xml does not exist
     * @throws \RuntimeException If unable to locate element in DOM
     */
    public function inject(string $docxPath, array $sdtTuples): void
    {
        $zip = $this->openDocxAsZip($docxPath);

        try {
            // Process document.xml (main body) - REQUIRED
            $this->processXmlFile($zip, 'word/document.xml', $sdtTuples, $docxPath, required: true);
            
            // Process headers and footers (v0.2.0)
            $headerFooterFiles = $this->discoverHeaderFooterFiles($zip);
            foreach ($headerFooterFiles as $xmlPath) {
                $this->processXmlFile($zip, $xmlPath, $sdtTuples, $docxPath, required: false);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Opens DOCX file as ZipArchive
     * 
     * @param string $docxPath DOCX file path
     * @return \ZipArchive Opened ZIP instance
     * @throws ZipArchiveException If fails to open
     */
    private function openDocxAsZip(string $docxPath): \ZipArchive
    {
        $zip = new \ZipArchive();
        $openResult = $zip->open($docxPath);
        if ($openResult !== true) {
            throw new ZipArchiveException($openResult, $docxPath);
        }
        return $zip;
    }

    /**
     * Reads XML content from ZIP archive
     * 
     * Generic method to read any XML file from DOCX ZIP.
     * Returns false if file does not exist (allows silent handling).
     * 
     * @param \ZipArchive $zip Opened ZIP archive
     * @param string $xmlPath Path to XML file inside ZIP (e.g., 'word/document.xml')
     * @return string|false XML content or false if file does not exist
     */
    private function readXmlFromZip(\ZipArchive $zip, string $xmlPath): string|false
    {
        return $zip->getFromName($xmlPath);
    }

    /**
     * Updates an XML file in the ZIP archive
     * 
     * Generic method to update any XML file in DOCX ZIP.
     * Deletes old version (if exists) and adds new content.
     * 
     * @param \ZipArchive $zip Opened ZIP archive
     * @param string $xmlPath Path to XML file inside ZIP (e.g., 'word/header1.xml')
     * @param string $xmlContent New XML content to write
     * @return void
     */
    private function updateXmlInZip(\ZipArchive $zip, string $xmlPath, string $xmlContent): void
    {
        // Delete old version (deleteName does not throw if file does not exist)
        $zip->deleteName($xmlPath);
        // Add new content
        $zip->addFromString($xmlPath, $xmlContent);
    }

    /**
     * Loads document.xml into DOMDocument (v3.0)
     * 
     * @param string $documentXml Document XML content
     * @return \DOMDocument Loaded DOM Document
     * @throws \RuntimeException If fails to load XML
     */
    private function loadDocumentAsDom(string $documentXml): \DOMDocument
    {
        libxml_use_internal_errors(true);
        
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        
        // Security: Prevent XXE attacks by disabling network access during XML parsing
        $success = $dom->loadXML(
            $documentXml,
            \LIBXML_NONET
        );
        
        if ($success === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors(false);
            
            $errorMessages = array_map(fn($e) => trim($e->message), $errors);
            throw new \RuntimeException(
                'SDTInjector: Failed to load document.xml as DOM: ' . implode('; ', $errorMessages)
            );
        }
        
        libxml_use_internal_errors(false);
        return $dom;
    }

    /**
     * Processes an element: locates in DOM and wraps with SDT (v3.0)
     * 
     * @param \DOMDocument $dom DOM Document
     * @param mixed $element PHPWord Element
     * @param SDTConfig $config Content Control Configuration
     * @param int $elementIndex Element index in processing order (0-indexed)
     * @param string $rootElement Root element context (w:body, w:hdr, or w:ftr)
     * @return void
     * @throws \RuntimeException If unable to locate element
     */
    private function processElement(
        \DOMDocument $dom,
        mixed $element,
        SDTConfig $config,
        int $elementIndex,
        string $rootElement = 'w:body'
    ): void {
        // Validate that element is an object
        if (!is_object($element)) {
            throw new \RuntimeException('SDTInjector: Element must be an object');
        }
        
        // Locate element in DOM using specific root context
        $targetElement = $this->locator->findElementInDOM($dom, $element, $elementIndex, $rootElement, $config->inlineLevel);
        
        if ($targetElement === null) {
            throw new \RuntimeException(
                'SDTInjector: Could not locate element in DOM tree'
            );
        }
        
        // Verify if already processed (avoid re-wrapping)
        if ($this->isElementProcessed($targetElement)) {
            return; // Already wrapped in <w:sdt>, skip
        }
        
        // NEW LOGIC v3.1: Routing based on inlineLevel
        if ($config->inlineLevel) {
            $this->processInlineLevelSDT($targetElement, $config);
        } else {
            $this->processBlockLevelSDT($targetElement, $config);
        }
    }

    /**
     * Serializes DOMDocument back to XML string (v3.0)
     * 
     * @param \DOMDocument $dom Modified DOM Document
     * @return string Serialized XML
     * @throws \RuntimeException If fails to serialize
     */
    private function serializeDocument(\DOMDocument $dom): string
    {
        $xml = $dom->saveXML();
        
        if ($xml === false) {
            throw new \RuntimeException('SDTInjector: Failed to serialize DOM to XML');
        }
        
        return $xml;
    }

    /**
     * Creates complete <w:sdt> XML element
     * 
     * NOTE: Method used in unit tests via ReflectionMethod (hence marked as @used)
     * 
     * @param mixed $element PHPWord Element
     * @param SDTConfig $config SDT Configuration
     * @return string Content Control XML
     * 
     * @phpstan-ignore-next-line
     */
    private function createSDTElement($element, SDTConfig $config): string
    {
        // Create DOM Document
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        // Create root element <w:sdt> with namespace
        $sdt = $doc->createElementNS(self::WORDML_NAMESPACE, 'w:sdt');
        $doc->appendChild($sdt);

        // Add properties (w:sdtPr)
        $sdtPr = $this->createSdtProperties($doc, $config);
        $sdt->appendChild($sdtPr);

        // Add content (w:sdtContent)
        $sdtContent = $doc->createElement('w:sdtContent');

        // Serialize internal elements
        $innerXml = $this->serializeElement($element);

        if ($innerXml !== '') {
            // Create fragment to inject serialized XML
            $fragment = $doc->createDocumentFragment();
            
            // Suppress namespace warnings (already defined in root element <w:sdt>)
            $previousUseInternalErrors = libxml_use_internal_errors(true);
            $success = $fragment->appendXML($innerXml);

            if ($success === false) {
                // Capture error messages for diagnosis
                $errors = libxml_get_errors();
                libxml_clear_errors();
                libxml_use_internal_errors($previousUseInternalErrors);

                // Filter only real errors (not warnings)
                $actualErrors = array_filter($errors, fn($e) => $e->level >= LIBXML_ERR_ERROR);

                $errorMessages = array_map(function($error) {
                    return trim($error->message);
                }, $actualErrors);

                $errorText = count($errorMessages) > 0
                    ? implode('; ', $errorMessages)
                    : 'Unknown error';

                throw new \DOMException(
                    'SDTInjector: Failed to parse inner XML content: ' . $errorText
                );
            }

            // Clear errors if any (namespace warnings expected)
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);

            $sdtContent->appendChild($fragment);
        }

        $sdt->appendChild($sdtContent);

        // Return only <w:sdt> element (without XML declaration)
        $xml = $doc->saveXML($sdt);

        // saveXML may return false in case of error
        if ($xml === false) {
            throw new \DOMException('SDTInjector: Failed to serialize Content Control to XML');
        }

        // Remove namespace declaration (will be inherited from document.xml) robustly
        $cleanXml = preg_replace(
            '/\s+xmlns:w=("|\')' . preg_quote(self::WORDML_NAMESPACE, '/') . '\1/',
            '',
            $xml,
            1
        );

        if ($cleanXml === null) {
            throw new \DOMException('SDTInjector: Failed to remove WordprocessingML namespace from serialized XML');
        }

        return $cleanXml;
    }

    /**
     * Creates <w:sdtPr> element with Content Control properties
     * 
     * @param \DOMDocument $doc DOM Document
     * @param SDTConfig $config SDT Configuration
     * @return \DOMElement Complete <w:sdtPr> element
     */
    private function createSdtProperties(\DOMDocument $doc, SDTConfig $config): \DOMElement
    {
        // Detect namespace (use document's or default)
        $nsUri = self::WORDML_NAMESPACE;
        
        $sdtPr = $doc->createElementNS($nsUri, 'w:sdtPr');

        // ID (required) - §17.5.2.14
        $id = $doc->createElementNS($nsUri, 'w:id');
        $id->setAttribute('w:val', $config->id);
        $sdtPr->appendChild($id);

        // Alias (optional) - §17.5.2.6
        if ($config->alias !== '') {
            $alias = $doc->createElementNS($nsUri, 'w:alias');
            $alias->setAttribute('w:val', $config->alias);
            $sdtPr->appendChild($alias);
        }

        // Tag (optional) - §17.5.2.33
        if ($config->tag !== '') {
            $tag = $doc->createElementNS($nsUri, 'w:tag');
            $tag->setAttribute('w:val', $config->tag);
            $sdtPr->appendChild($tag);
        }

        // Content Control Type (required)
        $typeElement = $doc->createElementNS($nsUri, $this->getTypeElementName($config->type));
        $sdtPr->appendChild($typeElement);

        // Lock (conditional) - §17.5.2.23
        if ($config->lockType !== ContentControl::LOCK_NONE) {
            $lock = $doc->createElementNS($nsUri, 'w:lock');
            $lock->setAttribute('w:val', $config->lockType);
            $sdtPr->appendChild($lock);
        }

        return $sdtPr;
    }

    /**
     * Returns XML element name for Content Control type
     * 
     * @param string $type Control type (ContentControl::TYPE_*)
     * @return string Element name (with w: prefix)
     */
    private function getTypeElementName(string $type): string
    {
        return match($type) {
            ContentControl::TYPE_GROUP => 'w:group',
            ContentControl::TYPE_PLAIN_TEXT => 'w:text',
            ContentControl::TYPE_RICH_TEXT => 'w:richText',
            ContentControl::TYPE_PICTURE => 'w:picture',
            default => 'w:richText',
        };
    }

    /**
     * Serializes PHPWord element to XML
     * 
     * @param mixed $element Element to serialize
     * @return string Serialized XML
     */
    private function serializeElement($element): string
    {
        // If not a valid PHPWord element, return empty
        if (!$element instanceof \PhpOffice\PhpWord\Element\AbstractElement) {
            return '';
        }

        // Create XMLWriter in memory mode
        $xmlWriter = new \PhpOffice\PhpWord\Shared\XMLWriter(
            \PhpOffice\PhpWord\Shared\XMLWriter::STORAGE_MEMORY,
            null,
            false
        );
        $xmlWriter->openMemory();

        // If it is a container (Section, Header, Footer), serialize its elements
        // AbstractContainer extends AbstractElement, so this check is valid
        if ($element instanceof \PhpOffice\PhpWord\Element\AbstractContainer) {
            foreach ($element->getElements() as $childElement) {
                $this->writeElement($xmlWriter, $childElement);
            }
        } else {
            // If it is a single element (not container), serialize directly
            $this->writeElement($xmlWriter, $element);
        }

        return $xmlWriter->getData();
    }

    /**
     * Writes PHPWord element using corresponding Writer
     * 
     * @param \PhpOffice\PhpWord\Shared\XMLWriter $xmlWriter XML Writer
     * @param \PhpOffice\PhpWord\Element\AbstractElement $element Element to serialize
     * @return void
     */
    private function writeElement(
        \PhpOffice\PhpWord\Shared\XMLWriter $xmlWriter,
        \PhpOffice\PhpWord\Element\AbstractElement $element
    ): void {
        // Extract class name from element
        $className = get_class($element);
        $lastBackslashPos = strrpos($className, '\\');

        if ($lastBackslashPos === false) {
            return; // Invalid class, ignore
        }

        $elementClass = substr($className, $lastBackslashPos + 1);

        // Containers should not be serialized directly
        if (in_array($elementClass, ['Section', 'Header', 'Footer', 'Cell'], true)) {
            return;
        }

        // Assemble Writer class name
        $writerClass = "PhpOffice\\PhpWord\\Writer\\Word2007\\Element\\{$elementClass}";

        // Verify if Writer exists
        if (!class_exists($writerClass)) {
            return; // Unsupported element - ignore
        }

        // Determine if element needs <w:p> wrapper
        $needsParagraphWrapper = $this->needsParagraphWrapper($element);
        $withoutParagraphWrapper = !$needsParagraphWrapper;

        // Instantiate Writer and serialize
        /** @var \PhpOffice\PhpWord\Writer\Word2007\Element\AbstractElement $writer */
        $writer = new $writerClass($xmlWriter, $element, $withoutParagraphWrapper);
        $writer->write();
    }

    /**
     * Checks if PHPWord element needs <w:p> wrapper
     * 
     * @param \PhpOffice\PhpWord\Element\AbstractElement $element PHPWord Element
     * @return bool true if wrapper is needed, false otherwise
     */
    private function needsParagraphWrapper(\PhpOffice\PhpWord\Element\AbstractElement $element): bool
    {
        return !(
            $element instanceof \PhpOffice\PhpWord\Element\Table ||
            $element instanceof \PhpOffice\PhpWord\Element\PageBreak ||
            $element instanceof \PhpOffice\PhpWord\Element\Section ||
            $element instanceof \PhpOffice\PhpWord\Element\Header ||
            $element instanceof \PhpOffice\PhpWord\Element\Footer
        );
    }

    /**
     * Wraps inline DOM element with <w:sdt> structure
     * 
     * Workflow:
     * 1. Create <w:sdt><w:sdtPr>...</w:sdtPr><w:sdtContent></w:sdtContent></w:sdt>
     * 2. Insert SDT before original element in DOM tree
     * 3. MOVE element inside <w:sdtContent> (appendChild moves the node)
     * 4. Mark as processed
     * 
     * IMPORTANT: appendChild() on existing node = MOVE (does not duplicate)
     * 
     * @param \DOMElement $targetElement DOM Element to wrap
     * @param SDTConfig $config SDT Configuration
     * @return void
     * @throws \RuntimeException If element has no parent or owner document
     */
    private function wrapElementInline(\DOMElement $targetElement, SDTConfig $config): void
    {
        // Validate pre-conditions
        $dom = $targetElement->ownerDocument;
        if ($dom === null) {
            throw new \RuntimeException('SDTInjector: Target element has no owner document');
        }

        $parent = $targetElement->parentNode;
        if ($parent === null) {
            throw new \RuntimeException('SDTInjector: Target element has no parent node');
        }

        // Detect document namespace (usually in root or parent element)
        $nsUri = $targetElement->namespaceURI;
        if ($nsUri === null || $nsUri === '') {
            $nsUri = self::WORDML_NAMESPACE;
        }

        // 1. Create <w:sdt> structure WITH namespace
        $sdt = $dom->createElementNS($nsUri, 'w:sdt');
        
        // 2. Add properties <w:sdtPr>
        $sdtPr = $this->createSdtProperties($dom, $config);
        $sdt->appendChild($sdtPr);
        
        // 3. Create content container <w:sdtContent> WITH namespace
        $sdtContent = $dom->createElementNS($nsUri, 'w:sdtContent');
        
        // 4. Insert SDT BEFORE original element
        $parent->insertBefore($sdt, $targetElement);
        
        // 5. MOVE element inside <w:sdtContent>
        // IMPORTANT: appendChild() MOVES the node (does not duplicate)
        $sdtContent->appendChild($targetElement);
        
        // 6. Complete SDT structure
        $sdt->appendChild($sdtContent);
        
        // 7. Mark as processed
        $this->markElementAsProcessed($targetElement);
    }

    /**
     * Processes block-level SDT (existing v3.0 behavior)
     * 
     * Routing method that delegates to wrapElementInline().
     * Created for code clarity and facilitating future maintenance.
     * 
     * @param \DOMElement $targetElement Element in DOM
     * @param SDTConfig $config SDT Configuration
     * @return void
     */
    private function processBlockLevelSDT(\DOMElement $targetElement, SDTConfig $config): void
    {
        // Delegates to existing method (v3.0 behavior)
        $this->wrapElementInline($targetElement, $config);
    }

    /**
     * Processes inline-level SDT (inside cells)
     * 
     * Workflow:
     * 1. Validate that element is <w:p>
     * 2. Locate parent cell of the paragraph
     * 3. Determine paragraph index in the cell
     * 4. Wrap paragraph with inline SDT
     * 
     * @param \DOMElement $targetElement <w:p> Element in DOM
     * @param SDTConfig $config SDT Configuration
     * @return void
     * @throws \RuntimeException If paragraph is not inside a cell
     */
    private function processInlineLevelSDT(\DOMElement $targetElement, SDTConfig $config): void
    {
        // Validate that it is a paragraph
        if ($targetElement->localName !== 'p' || 
            $targetElement->namespaceURI !== self::WORDML_NAMESPACE) {
            throw new \RuntimeException(
                'SDTInjector: Inline-level SDT can only wrap paragraphs (<w:p>)'
            );
        }
        
        // Locate parent cell
        $cellElement = $this->findParentCell($targetElement);
        
        // Determine paragraph index in cell
        $paragraphIndex = $this->getParagraphIndexInCell($cellElement, $targetElement);
        
        // Wrap paragraph inline
        $this->wrapParagraphInCellInline($cellElement, $paragraphIndex, $config);
    }

    /**
     * Locates <w:tc> element (cell) containing the paragraph
     * 
     * Navigates DOM tree upwards until finding <w:tc>.
     * 
     * @param \DOMElement $paragraphElement <w:p> Element
     * @return \DOMElement Parent <w:tc> element
     * @throws \RuntimeException If parent cell not found
     */
    private function findParentCell(\DOMElement $paragraphElement): \DOMElement
    {
        $current = $paragraphElement->parentNode;
        
        while ($current !== null) {
            if ($current instanceof \DOMElement &&
                $current->localName === 'tc' && 
                $current->namespaceURI === self::WORDML_NAMESPACE) {
                return $current;
            }
            $current = $current->parentNode;
        }
        
        throw new \RuntimeException('SDTInjector: Paragraph not inside a table cell (<w:tc>)');
    }

    /**
     * Returns paragraph index within the cell (0-based)
     * 
     * Uses XPath to list all unprocessed paragraphs
     * and finds the target paragraph index.
     * 
     * @param \DOMElement $cellElement <w:tc> Element
     * @param \DOMElement $paragraphElement <w:p> Element
     * @return int Index 0-based
     * @throws \RuntimeException If paragraph not found
     */
    private function getParagraphIndexInCell(
        \DOMElement $cellElement,
        \DOMElement $paragraphElement
    ): int {
        $dom = $cellElement->ownerDocument;
        
        if ($dom === null) {
            throw new \RuntimeException('SDTInjector: Cell element has no owner document');
        }
        
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORDML_NAMESPACE);
        
        $paragraphs = $xpath->query(
            './/w:p[not(ancestor::w:sdtContent)]',
            $cellElement
        );
        
        if ($paragraphs === false) {
            throw new \RuntimeException('SDTInjector: XPath query failed');
        }
        
        for ($i = 0; $i < $paragraphs->length; $i++) {
            $node = $paragraphs->item($i);
            if ($node !== null && $node->isSameNode($paragraphElement)) {
                return $i;
            }
        }
        
        throw new \RuntimeException('SDTInjector: Paragraph not found in cell');
    }

    /**
     * Wraps paragraph inside cell with inline SDT
     * 
     * Generated structure:
     * <w:tc>
     *   <w:sdt>
     *     <w:sdtPr>...</w:sdtPr>
     *     <w:sdtContent>
     *       <w:p>...</w:p>  <!-- Paragraph MOVED (not cloned) -->
     *     </w:sdtContent>
     *   </w:sdt>
     * </w:tc>
     * 
     * @param \DOMElement $cellElement <w:tc> Element
     * @param int $paragraphIndex Paragraph index (0-based)
     * @param SDTConfig $config SDT Configuration
     * @return void
     * @throws \RuntimeException If paragraph not found
     */
    private function wrapParagraphInCellInline(
        \DOMElement $cellElement,
        int $paragraphIndex,
        SDTConfig $config
    ): void {
        $dom = $cellElement->ownerDocument;
        
        if ($dom === null) {
            throw new \RuntimeException('SDTInjector: Cell element has no owner document');
        }
        
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORDML_NAMESPACE);
        
        // Find UNPROCESSED paragraph inside cell
        $paragraphs = $xpath->query(
            './/w:p[not(ancestor::w:sdtContent)]',
            $cellElement
        );
        
        if ($paragraphs === false || $paragraphIndex >= $paragraphs->length) {
            throw new \RuntimeException(
                sprintf(
                    'SDTInjector: Paragraph index %d not found in cell (found %d paragraphs)',
                    $paragraphIndex,
                    $paragraphs === false ? 0 : $paragraphs->length
                )
            );
        }
        
        $paragraphElement = $paragraphs->item($paragraphIndex);
        
        if (!($paragraphElement instanceof \DOMElement)) {
            throw new \RuntimeException('SDTInjector: Located paragraph is not a DOMElement');
        }
        
        // Detect namespace
        $nsUri = $cellElement->namespaceURI !== null ? $cellElement->namespaceURI : self::WORDML_NAMESPACE;
        
        // Create inline SDT structure
        $sdt = $dom->createElementNS($nsUri, 'w:sdt');
        
        // Add properties (reuses existing method)
        $sdtPr = $this->createSdtProperties($dom, $config);
        $sdt->appendChild($sdtPr);
        
        // Create EMPTY content container
        $sdtContent = $dom->createElementNS($nsUri, 'w:sdtContent');
        
        // Insert SDT BEFORE paragraph (preserves position)
        $parent = $paragraphElement->parentNode;
        if ($parent === null) {
            throw new \RuntimeException('SDTInjector: Paragraph element has no parent node');
        }
        $parent->insertBefore($sdt, $paragraphElement);
        
        // MOVE paragraph inside SDT (do not clone!)
        // appendChild() automatically removes the paragraph from original position
        $sdtContent->appendChild($paragraphElement);
        $sdt->appendChild($sdtContent);
        
        // Mark as processed
        $this->markElementAsProcessed($paragraphElement);
    }

    /**
     * Checks if element has already been processed (avoids re-wrapping)
     * 
     * @param \DOMElement $element DOM Element to check
     * @return bool true if already processed
     */
    private function isElementProcessed(\DOMElement $element): bool
    {
        $path = $element->getNodePath();
        return isset($this->processedElements[$path]);
    }

    /**
     * Marks element as processed
     * 
     * Uses NodePath as unique key (e.g., "/w:body[1]/w:sdt[1]/w:sdtContent[1]/w:p[1]")
     * 
     * @param \DOMElement $element DOM Element to mark
     * @return void
     */
    private function markElementAsProcessed(\DOMElement $element): void
    {
        $path = $element->getNodePath();
        $this->processedElements[$path] = true;
    }

    /**
     * Sorts elements by descending depth (depth-first)
     * 
     * Deeper elements (Cell) are processed before shallower elements
     * (Table), avoiding re-wrapping of already wrapped elements.
     * 
     * Uses stable sort: when depths are equal, keeps original order.
     * 
     * @param array<int, array{element: mixed, config: SDTConfig}> $sdtTuples
     * @return array<int, array{element: mixed, config: SDTConfig}> Sorted tuples
     */
    private function sortElementsByDepth(array $sdtTuples): array
    {
        // Add original index for stable sort
        $withIndex = array_map(function ($tuple, $index) {
            return ['tuple' => $tuple, 'originalIndex' => $index];
        }, $sdtTuples, array_keys($sdtTuples));

        usort($withIndex, function ($a, $b) {
            $depthA = $this->getElementDepth($a['tuple']['element']);
            $depthB = $this->getElementDepth($b['tuple']['element']);
            
            // Sort descending (deeper first)
            $depthComparison = $depthB <=> $depthA;
            
            // If depths are equal, keep original order (stability)
            if ($depthComparison === 0) {
                return $a['originalIndex'] <=> $b['originalIndex'];
            }
            
            return $depthComparison;
        });
        
        // Extract sorted tuples
        return array_map(fn($item) => $item['tuple'], $withIndex);
    }

    /**
     * Processes a single XML file (document.xml, header*.xml, footer*.xml)
     * 
     * Generic workflow:
     * 1. Read XML from ZIP (silently skip if not found, unless required)
     * 2. Load XML as DOMDocument
     * 3. Filter elements belonging to this XML file
     * 4. Sort elements by depth (depth-first processing)
     * 5. Process each element (locate in DOM and wrap with SDT)
     * 6. Serialize modified DOM and update ZIP
     * 
     * @param \ZipArchive $zip Opened ZIP archive
     * @param string $xmlPath Path to XML file (e.g., 'word/document.xml')
     * @param array<int, array{element: mixed, config: SDTConfig}> $sdtTuples All SDT tuples
     * @param string $docxPath DOCX path (for error messages)
     * @param bool $required Whether this XML file is required (throws if not found)
     * @return void
     * @throws DocumentNotFoundException If required file is not found
     * @throws \RuntimeException If XML loading or processing fails
     */
    private function processXmlFile(
        \ZipArchive $zip,
        string $xmlPath,
        array $sdtTuples,
        string $docxPath,
        bool $required = false
    ): void {
        // 1. Read XML from ZIP
        $xmlContent = $this->readXmlFromZip($zip, $xmlPath);
        
        // Handle missing file
        if ($xmlContent === false) {
            if ($required) {
                throw new DocumentNotFoundException($xmlPath, $docxPath);
            }
            // Silently skip optional files (headers/footers)
            return;
        }
        
        // 2. Load XML as DOMDocument
        $dom = $this->loadDocumentAsDom($xmlContent);
        
        // 2.5. Detect root element type (w:body, w:hdr, or w:ftr)
        $rootElement = $this->locator->detectRootElement($dom);
        
        // 3. Filter elements belonging to this XML file
        $filteredTuples = $this->filterElementsByXmlFile($sdtTuples, $xmlPath);
        
        // Skip processing if no elements belong to this file
        if (count($filteredTuples) === 0) {
            return;
        }
        
        // 4. Sort elements by depth (depth-first)
        $sortedTuples = $this->sortElementsByDepth($filteredTuples);
        
        // 5. Process each element with appropriate root context
        foreach ($sortedTuples as $index => $tuple) {
            $this->processElement($dom, $tuple['element'], $tuple['config'], $index, $rootElement);
        }
        
        // 6. Serialize modified DOM
        $modifiedXml = $this->serializeDocument($dom);
        
        // 7. Update XML in ZIP
        $this->updateXmlInZip($zip, $xmlPath, $modifiedXml);
    }

    /**
     * Calculates element depth in PHPWord hierarchy
     * 
     * Depths:
     * - Cell: 3 (inside Row inside Table)
     * - Table: 1
     * - Section: 1
     * - Text/TextRun: 1 (inside Section or Cell)
     * 
     * @param mixed $element PHPWord Element
     * @return int Depth (higher values = deeper)
     */
    private function getElementDepth($element): int
    {
        // Cell is the deepest (inside Row inside Table)
        if ($element instanceof \PhpOffice\PhpWord\Element\Cell) {
            return 3;
        }

        // Table
        if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
            return 1;
        }

        // Section
        if ($element instanceof \PhpOffice\PhpWord\Element\Section) {
            return 1;
        }

        // Text, TextRun, Image (inside container)
        // For simplicity, consider depth 1 (same priority as Section)
        return 1;
    }

    /**
     * Discovers header*.xml and footer*.xml files in DOCX ZIP
     * 
     * Returns sorted list of XML file paths found in the archive.
     * Files are sorted alphabetically for predictable processing order.
     * 
     * @param \ZipArchive $zip Opened ZIP archive
     * @return array<int, string> List of header/footer XML paths (e.g., ['word/footer1.xml', 'word/header1.xml'])
     */
    private function discoverHeaderFooterFiles(\ZipArchive $zip): array
    {
        $files = [];
        $numFiles = $zip->numFiles;

        for ($i = 0; $i < $numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (!is_string($filename)) {
                continue;
            }

            // Match word/header*.xml or word/footer*.xml
            if (preg_match('#^word/(header|footer)\d+\.xml$#', $filename) === 1) {
                $files[] = $filename;
            }
        }

        // Sort alphabetically for predictable order
        sort($files);

        return $files;
    }

    /**
     * Determines which XML file an element belongs to
     * 
     * Uses PHPWord's internal docPart property to determine location:
     * - docPart='Header' → word/header*.xml
     * - docPart='Footer' → word/footer*.xml
     * - docPart='Section' or other → word/document.xml
     * 
     * Also uses docPartId to map to specific header/footer number.
     * 
     * @param object $element PHPWord element instance
     * @return string XML path where element should be processed
     */
    private function getXmlFileForElement(object $element): string
    {
        try {
            $reflection = new \ReflectionClass($element);

            // Try to get docPart property (available in most PHPWord elements)
            if ($reflection->hasProperty('docPart')) {
                $docPartProp = $reflection->getProperty('docPart');
                $docPartProp->setAccessible(true);
                $docPart = $docPartProp->getValue($element);

                // Validate that docPart is actually a string
                if (!is_string($docPart)) {
                    return 'word/document.xml';
                }

                // Get docPartId if available
                $docPartId = 1; // Default
                if ($reflection->hasProperty('docPartId')) {
                    $docPartIdProp = $reflection->getProperty('docPartId');
                    $docPartIdProp->setAccessible(true);
                    $docPartIdValue = $docPartIdProp->getValue($element);
                    if (is_int($docPartIdValue)) {
                        $docPartId = $docPartIdValue;
                    }
                }

                // Map docPart to XML file (only accept expected values)
                if ($docPart === 'Header') {
                    return 'word/header' . $docPartId . '.xml';
                } elseif ($docPart === 'Footer') {
                    return 'word/footer' . $docPartId . '.xml';
                }
            }
        } catch (\ReflectionException $e) {
            // Reflection failed, assume body
        }

        // Default to document.xml (main body)
        return 'word/document.xml';
    }

    /**
     * Filters SDT tuples to only include elements from specific XML file
     * 
     * Uses getXmlFileForElement() to determine each element's location.
     * 
     * @param array<int, array{element: mixed, config: SDTConfig}> $sdtTuples All SDT tuples
     * @param string $xmlPath Target XML file path (e.g., 'word/header1.xml')
     * @return array<int, array{element: mixed, config: SDTConfig}> Filtered tuples
     */
    private function filterElementsByXmlFile(array $sdtTuples, string $xmlPath): array
    {
        $filtered = [];

        foreach ($sdtTuples as $tuple) {
            // Skip if element is not an object
            if (!is_object($tuple['element'])) {
                continue;
            }
            
            $elementXmlPath = $this->getXmlFileForElement($tuple['element']);

            if ($elementXmlPath === $xmlPath) {
                $filtered[] = $tuple;
            }
        }

        return $filtered;
    }
}

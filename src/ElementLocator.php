<?php

declare(strict_types=1);

namespace MkGrow\ContentControl;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * PHPWord Element Locator in document.xml DOM tree
 * 
 * Uses multiple XPath search strategies:
 * 1. By type + registration order (fastest)
 * 2. By content hash (fallback for identical elements)
 * 3. By structural characteristics (tables, cells)
 * 
 * @since 3.0.0
 */
final class ElementLocator
{
    private const WORDML_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const VML_NS = 'urn:schemas-microsoft-com:vml';
    private const OFFICE_NS = 'urn:schemas-microsoft-com:office:office';

    /**
     * XPath instance cache for reuse
     */
    private ?DOMXPath $xpath = null;

    /**
     * Locates PHPWord element in DOM
     * 
     * @param DOMDocument $dom Loaded DOM Document
     * @param object $element PHPWord element to locate
     * @param int $registrationOrder Element registration order (0-indexed)
     * @param string $rootElement Root element to search in (w:body, w:hdr, or w:ftr)
     * @return DOMElement|null DOM Element or null if not found
     * @throws \InvalidArgumentException If element type is not supported
     */
    public function findElementInDOM(
        DOMDocument $dom,
        object $element,
        int $registrationOrder = 0,
        string $rootElement = 'w:body'
    ): ?DOMElement {
        // Always (re)initialize XPath for the current DOM document
        // This is necessary because we process multiple XML files (document.xml, header*.xml, footer*.xml)
        // and each has its own DOMDocument instance
        $this->xpath = new DOMXPath($dom);
        $this->xpath->registerNamespace('w', self::WORDML_NS);
        $this->xpath->registerNamespace('v', self::VML_NS);
        $this->xpath->registerNamespace('o', self::OFFICE_NS);

        // FIX v0.4.2: Changed strategy priority to use content hash FIRST
        // This fixes the issue where registrationOrder doesn't match DOM position
        // when multiple elements are added to a section but only some have SDTs
        
        // Strategy 1: By content hash (more reliable)
        $contentHash = ElementIdentifier::generateContentHash($element);
        $found = $this->findByContentHash($element, $contentHash, $rootElement);
        if ($found !== null) {
            return $found;
        }

        // Strategy 2: By type + order (fallback)
        $found = $this->findByTypeAndOrder($element, $registrationOrder, $rootElement);
        if ($found !== null) {
            return $found;
        }

        // Not found
        return null;
    }

    /**
     * Search by element type + registration order
     * 
     * @param object $element PHPWord Element
     * @param int $order Registration order (0-indexed)
     * @param string $rootElement Root element to search in (w:body, w:hdr, or w:ftr)
     * @return DOMElement|null
     * @throws \InvalidArgumentException If element type is not supported
     */
    private function findByTypeAndOrder(object $element, int $order, string $rootElement): ?DOMElement
    {
        // Title: only supported in w:body (headers/footers cannot contain titles)
        if ($element instanceof \PhpOffice\PhpWord\Element\Title) {
            if ($rootElement !== 'w:body') {
                return null;
            }
            return $this->findTitleByDepth($element, $order);
        }

        // Image: use specialized method
        if ($element instanceof \PhpOffice\PhpWord\Element\Image) {
            return $this->findImageByOrder($order, $rootElement);
        }

        $query = $this->createXPathQuery($element, $rootElement);

        // For cells, search only cells NOT involved in SDTs
        // This avoids locating cells that have already been moved to <w:sdtContent>
        // Always search [1] because cells are removed from result after wrapping
        if ($element instanceof \PhpOffice\PhpWord\Element\Cell) {
            $query = '//' . $rootElement . '//w:tc[not(ancestor::w:sdtContent)][1]';
            
            $nodes = $this->xpath !== null ? $this->xpath->query($query) : null;
            if ($nodes === null || $nodes === false || $nodes->length === 0) {
                return null;
            }

            $node = $nodes->item(0);
            return ($node instanceof DOMElement) ? $node : null;
        }

        // Text/TextRun: try first in cells, then in rootElement
        // IMPORTANT ORDER: cells have priority to avoid false positives with block-level elements
        if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
            // Strategy 1: Search inside cells (inline-level SDT)
            $cellResult = $this->findTextInCell($order, $rootElement);
            if ($cellResult !== null) {
                return $cellResult;
            }
            
            // Strategy 2: Search in rootElement (w:body, w:hdr, w:ftr) - block-level fallback
            // NOTE: Using [1] to always get first unprocessed paragraph
            // This works because findByContentHash (called first) identifies the correct element
            $query .= '[not(ancestor::w:sdtContent)][1]';
            $nodes = $this->xpath !== null ? $this->xpath->query($query) : null;
            
            if ($nodes !== null && $nodes !== false && $nodes->length > 0) {
                $node = $nodes->item(0);
                if ($node instanceof DOMElement) {
                    return $node;
                }
            }
            
            return null;
        }
        
        if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
            // Strategy 1: Search inside cells (inline-level SDT)
            $cellResult = $this->findTextRunInCell($order, $rootElement);
            if ($cellResult !== null) {
                return $cellResult;
            }
            
            // Strategy 2: Search in rootElement (w:body, w:hdr, w:ftr) - block-level fallback
            // NOTE: Using [1] to always get first unprocessed paragraph
            // This works because findByContentHash (called first) identifies the correct element
            $query .= '[not(ancestor::w:sdtContent)][1]';
            $nodes = $this->xpath !== null ? $this->xpath->query($query) : null;
            
            if ($nodes !== null && $nodes !== false && $nodes->length > 0) {
                $node = $nodes->item(0);
                if ($node instanceof DOMElement) {
                    return $node;
                }
            }
            
            return null;
        }
        
        // Table: apply similar filter and always use [1]
        if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
            // Search only elements NOT involved in SDTs
            $query .= '[not(ancestor::w:sdtContent)][1]';
            
            $nodes = $this->xpath !== null ? $this->xpath->query($query) : null;
            if ($nodes === null || $nodes === false || $nodes->length === 0) {
                return null;
            }

            $node = $nodes->item(0);
            return ($node instanceof DOMElement) ? $node : null;
        }

        // For other elements without filter, use registration index
        // XPath is 1-indexed
        $xpathPosition = $order + 1;
        $query .= "[{$xpathPosition}]";

        $nodes = $this->xpath !== null ? $this->xpath->query($query) : null;
        if ($nodes === null || $nodes === false || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);
        return ($node instanceof DOMElement) ? $node : null;
    }

    /**
     * Search by content hash
     * 
     * Iterates through all elements of the type and compares hash.
     * Slower, but works for identical elements.
     * 
     * @param object $element PHPWord Element
     * @param string $contentHash Content MD5 Hash
     * @param string $rootElement Root element to search in (w:body, w:hdr, or w:ftr)
     * @return DOMElement|null
     * @throws \InvalidArgumentException If element type is not supported
     */
    private function findByContentHash(object $element, string $contentHash, string $rootElement): ?DOMElement
    {
        // Title: only supported in w:body (headers/footers cannot contain titles)
        if ($element instanceof \PhpOffice\PhpWord\Element\Title && $rootElement !== 'w:body') {
            return null;
        }

        $query = $this->createXPathQuery($element, $rootElement);
        if ($this->xpath === null) {
            return null;
        }

        $nodes = $this->xpath->query($query);
        if ($nodes === false) {
            return null;
        }
        
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            // Calculate DOM element hash
            $domHash = $this->hashDOMElement($node, $element);
            
            if ($domHash === $contentHash) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Creates XPath query for element type
     * 
     * @param object $element PHPWord Element
     * @param string $rootElement Root element to search in (w:body, w:hdr, or w:ftr)
     * @return string XPath Query
     * @throws \InvalidArgumentException If element type is not supported
     */
    private function createXPathQuery(object $element, string $rootElement = 'w:body'): string
    {
        // Text/TextRun: search <w:p> (paragraph)
        if ($element instanceof \PhpOffice\PhpWord\Element\Text ||
            $element instanceof \PhpOffice\PhpWord\Element\TextRun) {
            return '//' . $rootElement . '/w:p';
        }

        // Table: search <w:tbl>
        if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
            return '//' . $rootElement . '/w:tbl';
        }

        // Cell: search <w:tc> (table cell)
        if ($element instanceof \PhpOffice\PhpWord\Element\Cell) {
            return '//' . $rootElement . '//w:tc';
        }

        // Title: search <w:p> with w:pStyle (handled in findTitleByDepth)
        if ($element instanceof \PhpOffice\PhpWord\Element\Title) {
            return '//' . $rootElement . '/w:p[w:pPr/w:pStyle]';
        }

        // Image: search <w:p> that contains <w:r>/<w:pict> (handled in findImageByOrder)
        if ($element instanceof \PhpOffice\PhpWord\Element\Image) {
            return '//' . $rootElement . '//w:p[.//w:r/w:pict]';
        }

        // Section: does not locate (not serialized as a single element)
        // Containers are processed via their child elements
        
        // Unsupported element - throw descriptive exception
        $supportedTypes = [
            \PhpOffice\PhpWord\Element\Text::class,
            \PhpOffice\PhpWord\Element\TextRun::class,
            \PhpOffice\PhpWord\Element\Table::class,
            \PhpOffice\PhpWord\Element\Cell::class,
            \PhpOffice\PhpWord\Element\Title::class,
            \PhpOffice\PhpWord\Element\Image::class,
        ];

        // Use short class names for better readability in error message
        $shortSupportedTypes = array_map(
            function(string $class): string {
                $lastBackslashPos = strrpos($class, '\\');
                return $lastBackslashPos !== false ? substr($class, $lastBackslashPos + 1) : $class;
            },
            $supportedTypes
        );

        $elementClass = get_class($element);
        $lastBackslashPos = strrpos($elementClass, '\\');
        $elementClassShort = $lastBackslashPos !== false ? substr($elementClass, $lastBackslashPos + 1) : $elementClass;

        throw new \InvalidArgumentException(
            sprintf(
                'Element type "%s" is not supported for Content Controls. Supported types: %s',
                $elementClassShort,
                implode(', ', $shortSupportedTypes)
            )
        );
    }

    /**
     * Generates DOM element hash for comparison
     * 
     * @param DOMElement $domElement DOM Element from document.xml
     * @param object $phpWordElement PHPWord Element (for type context)
     * @return string Truncated MD5 Hash (8 chars)
     */
    private function hashDOMElement(DOMElement $domElement, object $phpWordElement): string
    {
        $parts = [];

        // Paragraph: extract all text
        if ($domElement->nodeName === 'w:p') {
            // Check if it is an Image (contains w:pict)
            if ($this->xpath !== null) {
                $pict = $this->xpath->query('.//w:r/w:pict', $domElement);
                if ($pict !== false && $pict->length > 0) {
                    $pictNode = $pict->item(0);
                    if ($pictNode instanceof DOMElement) {
                        // Process as image
                        $parts[] = 'image';
                        
                        // Extract dimensions from v:shape style attribute
                        $shapes = $this->xpath->query('.//v:shape', $pictNode);
                        if ($shapes !== false && $shapes->length > 0) {
                            $shape = $shapes->item(0);
                            if ($shape instanceof DOMElement) {
                                $style = $shape->getAttribute('style');
                                
                                // Parse width and height from style (format: "width:100pt; height:100pt;")
                                if (preg_match('/width:\s*([0-9.]+)pt/i', $style, $widthMatch) === 1) {
                                    $parts[] = "width:{$widthMatch[1]}";
                                }
                                if (preg_match('/height:\s*([0-9.]+)pt/i', $style, $heightMatch) === 1) {
                                    $parts[] = "height:{$heightMatch[1]}";
                                }
                                
                                // Note: We do not include r:id (relationship id) in the hash because it does not
                                // correspond to the file basename used by ElementIdentifier and
                                // cannot be resolved to the filename without reading document.xml.rels.
                                // LIMITATION: Using only width+height may cause collisions between distinct
                                // distinct images with the same dimensions. For guaranteed unique identification,
                                // it would be necessary to resolve relationships or use additional metadata.
                            }
                        }
                        
                        // Return image hash
                        $serialized = implode('|', $parts);
                        return substr(md5($serialized), 0, 8);
                    }
                }
                
                // Check if it is a Title (has w:pStyle)
                $pStyle = $this->xpath->query('.//w:pPr/w:pStyle', $domElement);
                if ($pStyle !== false && $pStyle->length > 0) {
                    $styleNode = $pStyle->item(0);
                    if ($styleNode instanceof DOMElement) {
                        $styleName = $styleNode->getAttribute('w:val');
                        $parts[] = 'title';
                        $parts[] = $styleName;
                        $text = $this->extractTextContent($domElement);
                        $parts[] = $text;
                        // Hash different from regular Text
                        $serialized = implode('|', $parts);
                        return substr(md5($serialized), 0, 8);
                    }
                }
            }
            
            // Regular Text/TextRun
            $parts[] = 'paragraph';  // Compatible with ElementIdentifier
            $text = $this->extractTextContent($domElement);
            $parts[] = $text;
        }

        // Table: count rows
        if ($domElement->nodeName === 'w:tbl' && $this->xpath !== null) {
            $parts[] = 'table';  // Compatible with ElementIdentifier
            $rows = $this->xpath->query('.//w:tr', $domElement);
            if ($rows !== false) {
                $parts[] = "rows:{$rows->length}";

                // Text from the first cell of each row
                foreach ($rows as $row) {
                    if (!$row instanceof DOMElement) {
                        continue;
                    }
                    $firstCell = $this->xpath->query('.//w:tc[1]', $row)->item(0);
                    if ($firstCell instanceof DOMElement) {
                        $text = $this->extractTextContent($firstCell);
                        $parts[] = $text;
                    }
                }
            }
        }

        // Cell: extract textual content from child elements
        if ($domElement->nodeName === 'w:tc' && $this->xpath !== null) {
            $parts[] = 'cell';  // Compatible with ElementIdentifier
            
            // Search child elements (paragraphs inside cell)
            $childParagraphs = $this->xpath->query('.//w:p', $domElement);
            if ($childParagraphs !== false) {
                foreach ($childParagraphs as $p) {
                    if ($p instanceof DOMElement) {
                        $text = $this->extractTextContent($p);
                        if ($text !== '') {
                            $parts[] = 'text';
                            $parts[] = $text;
                        }
                    }
                }
            }
        }

        $serialized = implode('|', $parts);
        return substr(md5($serialized), 0, 8);
    }

    /**
     * Locates a Title element in DOM by depth and order
     * 
     * Searches Title elements using the w:pStyle attribute that corresponds
     * to the depth (0=Title, 1=Heading1, 2=Heading2, etc.). This method
     * uses Reflection to access the private $depth property of said Title.
     * 
     * XPath Query Pattern:
     * //w:body/w:p[w:pPr/w:pStyle[@w:val="Heading{depth}"]][not(ancestor::w:sdtContent)][1]
     * 
     * @param \PhpOffice\PhpWord\Element\Title $element The Title element to locate
     * @param int $order Registration order (0-indexed), ignored in v3.0 implementation.
     *                   Kept for compatibility and potential future support for multiple titles.
     * @return DOMElement|null The located paragraph element, or null if not found
     * @throws \RuntimeException If the depth property is not a valid integer
     * @since 0.1.0
     */
    private function findTitleByDepth(
        \PhpOffice\PhpWord\Element\Title $element,
        int $order
    ): ?DOMElement {
        // NOTE: The $order parameter is intentionally unused.
        // In v3.0, element de-duplication guarantees that only the first
        // matching Title exists (order is always 1). We keep this parameter
        // for interface compatibility with earlier versions and potential
        // future use.
        if ($this->xpath === null) {
            return null;
        }

        // Use Reflection to access private $depth
        try {
            $reflection = new \ReflectionClass($element);
            $depthProperty = $reflection->getProperty('depth');
            $depthProperty->setAccessible(true);
            $depth = $depthProperty->getValue($element);
            
            // Ensure depth is integer
            if (!is_int($depth)) {
                throw new \RuntimeException('Title depth must be an integer');
            }
        } catch (\ReflectionException $e) {
            // Could not access "depth" property via Reflection.
            // Log error and return null, as there is no viable fallback without depth.
            error_log(sprintf(
                'ElementLocator: failed to access "depth" property via Reflection for element of type %s: %s',
                get_class($element),
                $e->getMessage()
            ));
            return null;
        }

        // Map depth to style name
        $styleName = $depth === 0 ? 'Title' : 'Heading' . $depth;

        // Validate that styleName contains only alphanumerics to prevent XPath injection
        if (preg_match('/^[a-zA-Z0-9]+$/', $styleName) !== 1) {
            throw new \RuntimeException(sprintf(
                'Invalid style name "%s" generated from depth %d',
                $styleName,
                $depth
            ));
        }

        // XPath Query to locate by style
        $query = sprintf(
            '//w:body/w:p[w:pPr/w:pStyle[@w:val="%s"]][not(ancestor::w:sdtContent)][1]',
            $styleName
        );

        $nodes = $this->xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);
        return ($node instanceof DOMElement) ? $node : null;
    }

    /**
     * Locates an Image element in DOM by order
     * 
     * Searches Image elements by locating w:pict elements inside w:r (run) nodes.
     * Supports inline and floating images. Watermark images are not supported and
     * will result in exception during processing.
     * 
     * XPath Query Pattern:
     * //{rootElement}//w:r/w:pict[not(ancestor::w:sdtContent)][1]
     * 
     * Requires VML namespaces registered:
     * - v: urn:schemas-microsoft-com:vml
     * - o: urn:schemas-microsoft-com:office:office
     * 
     * @param int $order Registration order (0-indexed), ignored in v3.0 implementation.
     *                   Kept for compatibility and potential future support for multiple images.
     * @param string $rootElement Root element to search in (w:body, w:hdr, or w:ftr)
     * @return DOMElement|null The parent w:p element containing w:pict, or null if not found
     * @since 0.1.0
     */
    private function findImageByOrder(int $order, string $rootElement = 'w:body'): ?DOMElement
    {
        // NOTE: The $order parameter is intentionally unused.
        // In v3.0, element de-duplication guarantees that only the first
        // matching Image exists (order is always 1). We keep this parameter
        // for interface compatibility with earlier versions and potential
        // future use.
        if ($this->xpath === null) {
            return null;
        }

        // Query to locate w:pict (VML images)
        $query = '//' . $rootElement . '//w:r/w:pict[not(ancestor::w:sdtContent)][1]';

        $nodes = $this->xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);
        if (!$node instanceof DOMElement) {
            return null;
        }

        // Return parent <w:p> element containing the image
        $parent = $node->parentNode;
        while ($parent !== null && !($parent instanceof DOMElement && $parent->nodeName === 'w:p')) {
            $parent = $parent->parentNode;
        }

        return ($parent instanceof DOMElement) ? $parent : null;
    }

    /**
     * Locates a Text element inside a table cell
     * 
     * Searches Text elements that are inside cells (w:tc).
     * Supports inline-level Content Controls.
     * 
     * XPath Query Pattern:
     * //{rootElement}//w:tbl//w:tc/w:p[not(ancestor::w:sdtContent)][1]
     * 
     * @param int $order Registration order (0-indexed), ignored in v3.0 implementation.
     *                   Kept for compatibility and potential future support.
     * @param string $rootElement Root element to search in (w:body, w:hdr, or w:ftr)
     * @return DOMElement|null The w:p element inside the cell, or null if not found
     * @since 4.0.0
     */
    private function findTextInCell(int $order, string $rootElement = 'w:body'): ?DOMElement
    {
        // NOTE: The $order parameter is intentionally unused.
        // In v3.0+, element de-duplication guarantees that only the first
        // matching Text in cell exists (order is always 1). We keep this parameter
        // for interface compatibility and potential future use.
        if ($this->xpath === null) {
            return null;
        }

        // Query to locate <w:p> inside table cells
        // Searches only paragraphs that are NOT inside existing SDTs
        $query = '//' . $rootElement . '//w:tbl//w:tc/w:p[not(ancestor::w:sdtContent)][1]';

        $nodes = $this->xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);
        return ($node instanceof DOMElement) ? $node : null;
    }

    /**
     * Locates a TextRun element inside a table cell
     * 
     * Searches TextRun elements (paragraphs with formatted runs) inside cells (w:tc).
     * Distinguishes from simple Text by the presence of w:r elements.
     * Supports inline-level Content Controls.
     * 
     * XPath Query Pattern:
     * //{rootElement}//w:tbl//w:tc/w:p[w:r][not(ancestor::w:sdtContent)][1]
     * 
     * @param int $order Registration order (0-indexed), ignored in v3.0 implementation.
     *                   Kept for compatibility and potential future support.
     * @param string $rootElement Root element to search in (w:body, w:hdr, or w:ftr)
     * @return DOMElement|null The w:p element containing w:r inside the cell, or null if not found
     * @since 4.0.0
     */
    private function findTextRunInCell(int $order, string $rootElement = 'w:body'): ?DOMElement
    {
        // NOTE: The $order parameter is intentionally unused.
        // In v3.0+, element de-duplication guarantees that only the first
        // matching TextRun in cell exists (order is always 1). We keep this parameter
        // for interface compatibility and potential future use.
        if ($this->xpath === null) {
            return null;
        }

        // Query to locate <w:p> with <w:r> inside table cells
        // w:r indicates it is a TextRun (formatted text)
        // Searches only paragraphs that are NOT inside existing SDTs
        $query = '//' . $rootElement . '//w:tbl//w:tc/w:p[w:r][not(ancestor::w:sdtContent)][1]';

        $nodes = $this->xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);
        return ($node instanceof DOMElement) ? $node : null;
    }

    /**
     * Extracts all textual content from DOM element
     * 
     * @param DOMElement $element DOM Element
     * @return string Concatenated text
     */
    private function extractTextContent(DOMElement $element): string
    {
        if ($this->xpath === null) {
            return '';
        }

        $textNodes = $this->xpath->query('.//w:t', $element);
        if ($textNodes === false) {
            return '';
        }

        $texts = [];

        foreach ($textNodes as $node) {
            $texts[] = $node->textContent;
        }

        return implode('', $texts);
    }

    /**
     * Validates if DOM element matches PHPWord element
     * 
     * @param DOMElement $domElement DOM Element
     * @param object $phpWordElement PHPWord Element
     * @return bool true if matches
     */
    public function validateMatch(DOMElement $domElement, object $phpWordElement): bool
    {
        // Initialize XPath if necessary
        if ($this->xpath === null && $domElement->ownerDocument !== null) {
            $this->xpath = new DOMXPath($domElement->ownerDocument);
            $this->xpath->registerNamespace('w', self::WORDML_NS);
            $this->xpath->registerNamespace('v', self::VML_NS);
            $this->xpath->registerNamespace('o', self::OFFICE_NS);
        }

        // Validate type
        $expectedNodeName = null;

        if ($phpWordElement instanceof \PhpOffice\PhpWord\Element\Text ||
            $phpWordElement instanceof \PhpOffice\PhpWord\Element\TextRun ||
            $phpWordElement instanceof \PhpOffice\PhpWord\Element\Title ||
            $phpWordElement instanceof \PhpOffice\PhpWord\Element\Image) {
            $expectedNodeName = 'w:p';
        } elseif ($phpWordElement instanceof \PhpOffice\PhpWord\Element\Table) {
            $expectedNodeName = 'w:tbl';
        }

        if ($expectedNodeName !== null && $domElement->nodeName !== $expectedNodeName) {
            return false;
        }

        // Validate hash
        $phpWordHash = ElementIdentifier::generateContentHash($phpWordElement);
        $domHash = $this->hashDOMElement($domElement, $phpWordElement);

        return $phpWordHash === $domHash;
    }

    /**
     * Detects the root element of a Word XML document
     * 
     * Identifies whether the document is a header (w:hdr), footer (w:ftr),
     * or main document (w:body) based on the root element name.
     * 
     * @param DOMDocument $dom The DOM document to analyze
     * @return string The root element name (w:hdr, w:ftr, or w:body)
     * @since 0.2.0
     */
    public function detectRootElement(DOMDocument $dom): string
    {
        $root = $dom->documentElement;
        
        if ($root === null) {
            return 'w:body';
        }

        return match ($root->localName) {
            'hdr' => 'w:hdr',
            'ftr' => 'w:ftr',
            default => 'w:body',
        };
    }
}

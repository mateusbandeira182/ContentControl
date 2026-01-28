<?php

declare(strict_types=1);

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\PhpWord;
use MkGrow\ContentControl\ContentControl;

// Autoload for test fixtures
require_once __DIR__ . '/Fixtures/SampleElements.php';

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses()->in('Unit', 'Feature');

/*
|--------------------------------------------------------------------------
| Custom Expectations
|--------------------------------------------------------------------------
*/

/**
 * Validates if the string is well-formed XML
 */
expect()->extend('toBeValidXml', function () {
    /** @var string $xml */
    $xml = $this->value;
    
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $result = $doc->loadXML($xml);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    
    expect($result)->toBeTrue("XML is not well-formed: " . print_r($errors, true));
    
    return $this;
});

/**
 * Verifies presence of XML element
 */
expect()->extend('toHaveXmlElement', function (string $elementName) {
    /** @var string $xml */
    $xml = $this->value;
    
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    
    $xpath = new DOMXPath($doc);
    $elements = $xpath->query("//{$elementName}");
    
    expect($elements->length)->toBeGreaterThan(0, "Element '{$elementName}' not found in XML");
    
    return $this;
});

/**
 * Verifies XML attribute with optional value
 * 
 * Note: XPath query "//*[@{$attribute}]" returns DOMElement nodes that have the attribute.
 * We check instanceof DOMElement to ensure getAttribute() is available before calling it.
 */
expect()->extend('toHaveXmlAttribute', function (string $attribute, ?string $expectedValue = null) {
    /** @var string $xml */
    $xml = $this->value;
    
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    
    $xpath = new DOMXPath($doc);
    // Query returns elements that have the specified attribute
    $nodes = $xpath->query("//*[@{$attribute}]");
    
    expect($nodes->length)->toBeGreaterThan(0, "Attribute '{$attribute}' not found in XML");
    
    // Check if node is DOMElement before calling getAttribute() (elements have this method)
    if ($expectedValue !== null && $nodes->item(0) instanceof DOMElement) {
        $actualValue = $nodes->item(0)->getAttribute($attribute);
        expect($actualValue)->toBe($expectedValue, "Attribute '{$attribute}' value mismatch");
    }
    
    return $this;
});

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

/**
 * Creates a basic PHPWord Section
 */
function createSection(): Section
{
    $phpWord = new PhpWord();
    return $phpWord->addSection(['sectionId' => 1]);
}

/**
 * Creates a Section with Text element
 */
function createSectionWithText(string $text = 'Sample text'): Section
{
    $section = createSection();
    $section->addText($text);
    return $section;
}

/**
 * Creates a ContentControl instance with customizable configuration (v2.0 API)
 * 
 * @param array<string, string> $options
 */
function createContentControl(array $options = []): ContentControl
{
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('Sample text');
    $cc->addContentControl($section, $options);
    return $cc;
}

/**
 * Creates a ContentControl with all properties configured (v2.0 API)
 */
function createFullContentControl(): ContentControl
{
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('Full content control');
    
    $cc->addContentControl($section, [
        'id' => '12345678',
        'alias' => 'Test Control',
        'tag' => 'test-tag',
        'lockType' => ContentControl::LOCK_CONTENT_LOCKED
    ]);
    
    return $cc;
}

/**
 * Validates complete SDT structure
 */
function assertValidSdtStructure(string $xml): void
{
    expect($xml)->toBeValidXml();
    expect($xml)->toHaveXmlElement('w:sdt');
    expect($xml)->toHaveXmlElement('w:sdtPr');
    expect($xml)->toHaveXmlElement('w:sdtContent');
}

/**
 * Extracts XML attribute value using XPath
 * 
 * This function queries for elements (not attributes directly) that have a specific attribute.
 * XPath query "//{$elementName}[@{$attributeName}]" returns DOMElement nodes.
 * 
 * Type checking pattern:
 * - XPath queries for elements → return DOMElement → check instanceof DOMElement
 * - XPath queries for attributes (e.g., @attr) → return DOMAttr → check !== null
 * 
 * @param string $xml The XML string to parse
 * @param string $elementName Name of the element to search for
 * @param string $attributeName Name of the attribute to extract
 * @return string|null The attribute value or null if not found
 */
function getXmlAttributeValue(string $xml, string $elementName, string $attributeName): ?string
{
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    
    $xpath = new DOMXPath($doc);
    // Query returns elements that have the specified attribute
    $nodes = $xpath->query("//{$elementName}[@{$attributeName}]");
    
    if ($nodes->length === 0) {
        return null;
    }
    
    $node = $nodes->item(0);
    // Verify node is a DOMElement before calling getAttribute() (only DOMElement has this method)
    if (!$node instanceof DOMElement) {
        return null;
    }
    
    return $node->getAttribute($attributeName);
}

/**
 * Creates a simple PHPWord Table
 */
function createSimpleTable(int $rows = 2, int $cols = 2): Table
{
    $section = createSection();
    $table = $section->addTable();
    
    for ($r = 0; $r < $rows; $r++) {
        $table->addRow();
        for ($c = 0; $c < $cols; $c++) {
            $table->addCell(2000)->addText("Row {$r} Col {$c}");
        }
    }
    
    return $table;
}

/**
 * Normalizes XML by removing whitespace for comparison
 */
function normalizeXml(string $xml): string
{
    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = false;
    $doc->loadXML($xml);
    
    return $doc->saveXML();
}

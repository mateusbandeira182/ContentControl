<?php

declare(strict_types=1);

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\PhpWord;
use MkGrow\ContentControl\ContentControl;

// Autoload para fixtures de teste
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
 * Valida se a string é XML bem formado
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
 * Verifica presença de elemento XML
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
 * Verifica atributo XML com valor opcional
 */
expect()->extend('toHaveXmlAttribute', function (string $attribute, ?string $expectedValue = null) {
    /** @var string $xml */
    $xml = $this->value;
    
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    
    $xpath = new DOMXPath($doc);
    $nodes = $xpath->query("//*[@{$attribute}]");
    
    expect($nodes->length)->toBeGreaterThan(0, "Attribute '{$attribute}' not found in XML");
    
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
 * Cria uma Section PHPWord básica
 */
function createSection(): Section
{
    $phpWord = new PhpWord();
    return $phpWord->addSection(['sectionId' => 1]);
}

/**
 * Cria uma Section com elemento Text
 */
function createSectionWithText(string $text = 'Sample text'): Section
{
    $section = createSection();
    $section->addText($text);
    return $section;
}

/**
 * Cria um ContentControl com configuração customizável
 * 
 * @param array<string, string> $options
 */
function createContentControl(array $options = []): ContentControl
{
    $section = createSection();
    return new ContentControl($section, $options);
}

/**
 * Cria um ContentControl com todas as propriedades configuradas
 */
function createFullContentControl(): ContentControl
{
    $section = createSectionWithText('Full content control');
    
    return new ContentControl($section, [
        'id' => 'test-id-123',
        'alias' => 'Test Control',
        'tag' => 'test-tag',
        'lockType' => 'sdtContentLocked'
    ]);
}

/**
 * Valida estrutura SDT completa
 */
function assertValidSdtStructure(string $xml): void
{
    expect($xml)->toBeValidXml();
    expect($xml)->toHaveXmlElement('w:sdt');
    expect($xml)->toHaveXmlElement('w:sdtPr');
    expect($xml)->toHaveXmlElement('w:sdtContent');
}

/**
 * Extrai valor de atributo XML usando XPath com namespace
 */
function getXmlAttributeValue(string $xml, string $elementName, string $attributeName): ?string
{
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    
    $xpath = new DOMXPath($doc);
    $nodes = $xpath->query("//{$elementName}[@{$attributeName}]");
    
    if ($nodes->length === 0) {
        return null;
    }
    
    $node = $nodes->item(0);
    if (!$node instanceof DOMElement) {
        return null;
    }
    
    return $node->getAttribute($attributeName);
}

/**
 * Cria uma Table PHPWord simples
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
 * Normaliza XML removendo whitespace para comparação
 */
function normalizeXml(string $xml): string
{
    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = false;
    $doc->loadXML($xml);
    
    return $doc->saveXML();
}

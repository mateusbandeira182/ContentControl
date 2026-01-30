<?php

// Deep debug of hash calculation

require 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Create footer with PHPWord
$phpWord = new PhpWord();
$section = $phpWord->addSection();
$footer = $section->addFooter();
$footerText = $footer->addText('Footer Content');

echo "=== PHPWord Element Hash Calculation ===\n\n";

// Use reflection to access ElementIdentifier::serializeForHash
$reflection = new \ReflectionClass('MkGrow\ContentControl\ElementIdentifier');
$serializeMethod = $reflection->getMethod('serializeForHash');
$serializeMethod->setAccessible(true);

$serialized = $serializeMethod->invoke(null, $footerText);
echo "Serialized parts: '{$serialized}'\n";
echo "MD5: " . md5($serialized) . "\n";
echo "Hash (first 8): " . substr(md5($serialized), 0, 8) . "\n\n";

// Generate DOCX and extract XML
$tempFile = __DIR__ . '/output/debug_hash_deep.docx';
$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save($tempFile);

$zip = new ZipArchive();
$zip->open($tempFile);
$footerXml = $zip->getFromName('word/footer1.xml');
$zip->close();

// Load as DOM
$dom = new DOMDocument();
$dom->loadXML($footerXml);
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

$paragraphs = $xpath->query('//w:p');
if ($paragraphs->length > 0) {
    $paragraph = $paragraphs->item(0);
    
    echo "=== DOM Element Hash Calculation ===\n\n";
    
    // Use reflection to access ElementLocator::hashDOMElement
    $locator = new \MkGrow\ContentControl\ElementLocator();
    $locatorReflection = new \ReflectionClass($locator);
    $hashMethod = $locatorReflection->getMethod('hashDOMElement');
    $hashMethod->setAccessible(true);
    
    // We need to call extractTextContent too
    $extractMethod = $locatorReflection->getMethod('extractTextContent');
    $extractMethod->setAccessible(true);
    
    // Manually calculate what hashDOMElement does for a paragraph
    $parts = [];
    $parts[] = 'paragraph';
    $text = $extractMethod->invoke($locator, $paragraph);
    $parts[] = $text;
    
    $manualSerialized = implode('|', $parts);
    echo "Manual serialized parts: '{$manualSerialized}'\n";
    echo "MD5: " . md5($manualSerialized) . "\n";
    echo "Hash (first 8): " . substr(md5($manualSerialized), 0, 8) . "\n\n";
    
    // Now call the actual method
    $actualHash = $hashMethod->invoke($locator, $paragraph, $footerText);
    echo "Actual DOM hash: {$actualHash}\n\n";
    
    echo "=== Comparison ===\n";
    echo "PHPWord serialized: '{$serialized}'\n";
    echo "DOM serialized:     '{$manualSerialized}'\n";
    echo "Match: " . ($serialized === $manualSerialized ? 'YES' : 'NO') . "\n";
}

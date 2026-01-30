<?php

// Test ElementIdentifier hash for footer text

require 'vendor/autoload.php';

use MkGrow\ContentControl\ElementIdentifier;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Create footer with PHPWord
$phpWord = new PhpWord();
$section = $phpWord->addSection();
$footer = $section->addFooter();
$footerText = $footer->addText('Footer Content');

// Generate hash for PHPWord element
$phpWordHash = ElementIdentifier::generateContentHash($footerText);
echo "PHPWord element hash: {$phpWordHash}\n";

// Save temporary DOCX and extract XML
$tempFile = __DIR__ . '/output/debug_hash_test.docx';
$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save($tempFile);

$zip = new ZipArchive();
$zip->open($tempFile);
$footerXml = $zip->getFromName('word/footer1.xml');
$zip->close();

// Load as DOM and get first paragraph
$dom = new DOMDocument();
$dom->loadXML($footerXml);
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

$paragraphs = $xpath->query('//w:p');
if ($paragraphs->length > 0) {
    $paragraph = $paragraphs->item(0);
    
    // Use ElementLocator to generate hash
    $locator = new \MkGrow\ContentControl\ElementLocator();
    $reflection = new \ReflectionClass($locator);
    $hashMethod = $reflection->getMethod('hashDOMElement');
    $hashMethod->setAccessible(true);
    
    $domHash = $hashMethod->invoke($locator, $paragraph, $footerText);
    echo "DOM element hash:     {$domHash}\n";
    
    echo "\nHashes match: " . ($phpWordHash === $domHash ? 'YES' : 'NO') . "\n";
    
    if ($phpWordHash !== $domHash) {
        echo "\nThis is the problem! Hashes don't match.\n";
        
        // Show text content
        $textNodes = $xpath->query('.//w:t', $paragraph);
        $texts = [];
        foreach ($textNodes as $node) {
            $texts[] = $node->textContent;
        }
        echo "DOM text content: '" . implode('', $texts) . "'\n";
        
        // Check PHPWord element
        echo "PHPWord text: '" . $footerText->getText() . "'\n";
    }
}

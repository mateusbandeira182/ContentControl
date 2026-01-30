<?php

// Debug script to test ElementLocator with footer XML

require 'vendor/autoload.php';

use MkGrow\ContentControl\ElementLocator;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Create footer with PHPWord
$phpWord = new PhpWord();
$section = $phpWord->addSection();
$footer = $section->addFooter();
$footerText = $footer->addText('Footer Content');

// Save temporary DOCX
$tempFile = __DIR__ . '/output/debug_locator_footer.docx';
$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save($tempFile);

// Extract footer XML
$zip = new ZipArchive();
$zip->open($tempFile);
$footerXml = $zip->getFromName('word/footer1.xml');
$zip->close();

// Load as DOM
$dom = new DOMDocument();
$dom->loadXML($footerXml);

// Test ElementLocator
$locator = new ElementLocator();

echo "Testing ElementLocator:\n";
echo "=======================\n\n";

// 1. Test detectRootElement
$rootElement = $locator->detectRootElement($dom);
echo "Detected root element: {$rootElement}\n";
echo "Expected: w:ftr\n";
echo "Match: " . ($rootElement === 'w:ftr' ? 'YES' : 'NO') . "\n\n";

// 2. Test findElementInDOM with w:ftr
try {
    $found = $locator->findElementInDOM($dom, $footerText, 0, 'w:ftr');
    
    if ($found) {
        echo "Element found: YES\n";
        echo "Node name: {$found->nodeName}\n";
        echo "Text content: {$found->textContent}\n";
    } else {
        echo "Element found: NO (returned null)\n";
    }
} catch (\Exception $e) {
    echo "Element found: ERROR\n";
    echo "Exception: {$e->getMessage()}\n";
}

// 3. Test with wrong root (w:body) - should fail
echo "\n\nTesting with wrong root (w:body):\n";
try {
    $found = $locator->findElementInDOM($dom, $footerText, 0, 'w:body');
    
    if ($found) {
        echo "Element found: YES (unexpected!)\n";
    } else {
        echo "Element found: NO (expected)\n";
    }
} catch (\Exception $e) {
    echo "Element found: ERROR\n";
    echo "Exception: {$e->getMessage()}\n";
}

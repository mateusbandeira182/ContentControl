<?php

/**
 * Research Script - Title and Image XML Structure Analysis
 * 
 * Generates sample DOCX files with Title and Image elements
 * and extracts their XML structures for analysis.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Create output directory
$outputDir = __DIR__ . '/output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

echo "=== ContentControl v0.1.0 - XML Structure Research ===\n\n";

// ========================================
// 1. Generate Document with Title Elements
// ========================================
echo "1. Generating document with Title elements...\n";

$phpWord = new PhpWord();
$section = $phpWord->addSection();

// Add title styles
$phpWord->addTitleStyle(0, ['size' => 20, 'bold' => true]); // Title (depth 0)
$phpWord->addTitleStyle(1, ['size' => 18, 'bold' => true]); // Heading1
$phpWord->addTitleStyle(2, ['size' => 16, 'bold' => true]); // Heading2
$phpWord->addTitleStyle(3, ['size' => 14, 'bold' => true]); // Heading3

// Add titles with different depths
$section->addTitle('Document Title', 0);
$section->addText('Some content after title.');

$section->addTitle('Chapter 1: Introduction', 1);
$section->addText('Content for chapter 1.');

$section->addTitle('1.1 Background', 2);
$section->addText('Content for section 1.1.');

$section->addTitle('1.1.1 Historical Context', 3);
$section->addText('Content for subsection 1.1.1.');

$titleFile = $outputDir . '/research_titles.docx';
$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save($titleFile);

echo "   ✓ Saved: $titleFile\n";

// Extract and display XML
extractAndDisplayXml($titleFile, 'Title Elements');

// ========================================
// 2. Generate Document with Image Elements
// ========================================
echo "\n2. Generating document with Image elements...\n";

$phpWord2 = new PhpWord();
$section2 = $phpWord2->addSection();

// Create a simple test image
$testImagePath = $outputDir . '/test_image.png';
createTestImage($testImagePath);

// Add inline image
$section2->addText('Inline Image:');
$section2->addImage($testImagePath, [
    'width' => 100,
    'height' => 100,
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
]);

$section2->addTextBreak();

// Add another inline image with different dimensions
$section2->addText('Different Size Image:');
$section2->addImage($testImagePath, [
    'width' => 200,
    'height' => 150,
]);

$imageFile = $outputDir . '/research_images.docx';
$objWriter2 = IOFactory::createWriter($phpWord2, 'Word2007');
$objWriter2->save($imageFile);

echo "   ✓ Saved: $imageFile\n";

// Extract and display XML
extractAndDisplayXml($imageFile, 'Image Elements');

// ========================================
// 3. Generate Mixed Document
// ========================================
echo "\n3. Generating mixed document (Titles + Images + Text)...\n";

$phpWord3 = new PhpWord();
$phpWord3->addTitleStyle(1, ['size' => 16, 'bold' => true]);
$phpWord3->addTitleStyle(2, ['size' => 14, 'bold' => true]);

$section3 = $phpWord3->addSection();

$section3->addTitle('Chapter 1: Overview', 1);
$section3->addText('This is the introduction paragraph.');

$section3->addImage($testImagePath, ['width' => 150, 'height' => 150]);

$section3->addTitle('1.1 Details', 2);
$section3->addText('More detailed content here.');

$section3->addImage($testImagePath, ['width' => 100, 'height' => 100]);

$mixedFile = $outputDir . '/research_mixed.docx';
$objWriter3 = IOFactory::createWriter($phpWord3, 'Word2007');
$objWriter3->save($mixedFile);

echo "   ✓ Saved: $mixedFile\n";

// Extract and display XML
extractAndDisplayXml($mixedFile, 'Mixed Document');

echo "\n=== Research Complete ===\n";
echo "Review the XML structures above to validate XPath queries.\n";

// ========================================
// Helper Functions
// ========================================

/**
 * Extract and display XML structure from DOCX file
 */
function extractAndDisplayXml(string $docxPath, string $label): void
{
    echo "\n   --- XML Structure for: $label ---\n";
    
    $zip = new ZipArchive();
    if ($zip->open($docxPath) !== true) {
        echo "   ✗ Failed to open DOCX\n";
        return;
    }
    
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    
    if ($xml === false) {
        echo "   ✗ Failed to extract document.xml\n";
        return;
    }
    
    // Format XML for readability
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml);
    
    $formattedXml = $dom->saveXML();
    
    // Display first 2000 characters
    $preview = substr($formattedXml, 0, 2000);
    echo $preview;
    
    if (strlen($formattedXml) > 2000) {
        echo "\n   ... (truncated, total length: " . strlen($formattedXml) . " chars)\n";
    }
    
    // Save full XML for manual inspection
    $xmlOutputPath = str_replace('.docx', '_document.xml', $docxPath);
    file_put_contents($xmlOutputPath, $formattedXml);
    echo "   ✓ Full XML saved to: $xmlOutputPath\n";
}

/**
 * Create a simple test image (1x1 PNG)
 */
function createTestImage(string $path): void
{
    // Create a simple 1x1 red pixel PNG
    $image = imagecreatetruecolor(1, 1);
    $red = imagecolorallocate($image, 255, 0, 0);
    imagefilledrectangle($image, 0, 0, 1, 1, $red);
    imagepng($image, $path);
    imagedestroy($image);
}

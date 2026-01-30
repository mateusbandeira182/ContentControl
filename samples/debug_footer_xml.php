<?php

// Debug script to inspect raw footer XML

require 'vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

$phpWord = new PhpWord();
$section = $phpWord->addSection();

// Add footer with text
$footer = $section->addFooter();
$footer->addText('Footer Content');

// Save temporary DOCX
$tempFile = __DIR__ . '/output/debug_footer_raw.docx';
$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save($tempFile);

// Extract and display footer XML
$zip = new ZipArchive();
$zip->open($tempFile);

echo "Files in DOCX:\n";
for ($i = 0; $i < $zip->numFiles; $i++) {
    $filename = $zip->getNameIndex($i);
    if (str_contains($filename, 'footer')) {
        echo "  - {$filename}\n";
    }
}

echo "\n\nFooter1.xml content:\n";
echo "==========================================\n";
$footerXml = $zip->getFromName('word/footer1.xml');
if ($footerXml) {
    // Pretty print
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($footerXml);
    echo $dom->saveXML();
} else {
    echo "Footer file not found!\n";
}

$zip->close();

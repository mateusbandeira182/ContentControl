<?php

// Debug script to understand header/footer XML paths

require 'vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;

$cc = new ContentControl();
$section = $cc->addSection();

// Add content to body
$bodyText = $section->addText('Body Content');

// Add content to header
$header = $section->addHeader();
$headerText = $header->addText('Header Content');

// Add content to footer
$footer = $section->addFooter();
$footerText = $footer->addText('Footer Content');

// Register Content Controls
$cc->addContentControl($bodyText, ['alias' => 'BodyText', 'tag' => 'body']);
$cc->addContentControl($headerText, ['alias' => 'HeaderText', 'tag' => 'header']);
$cc->addContentControl($footerText, ['alias' => 'FooterText', 'tag' => 'footer']);

// Get registry to inspect elements
$registry = $cc->getSDTRegistry();
$tuples = $registry->getAll();

echo "Total elements registered: " . count($tuples) . "\n\n";

// Use reflection to access SDTInjector methods
$injector = new \MkGrow\ContentControl\SDTInjector();
$reflection = new \ReflectionClass($injector);

$getXmlFileMethod = $reflection->getMethod('getXmlFileForElement');
$getXmlFileMethod->setAccessible(true);

foreach ($tuples as $index => $tuple) {
    $element = $tuple['element'];
    $config = $tuple['config'];
    $className = get_class($element);
    $alias = $config->alias;
    
    try {
        $xmlPath = $getXmlFileMethod->invoke($injector, $element);
        echo "Element #{$index}: {$className} (alias: {$alias}) -> {$xmlPath}\n";
    } catch (\Exception $e) {
        echo "Element #{$index}: {$className} (alias: {$alias}) -> ERROR: {$e->getMessage()}\n";
    }
}

// Generate DOCX to see if it works
$tempFile = __DIR__ . '/output/debug_multi_file.docx';

// First, generate raw DOCX with PHPWord to see what files are created
$phpWord = $cc->getPhpWord();
$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
$rawTempFile = __DIR__ . '/output/debug_multi_file_raw.docx';
$objWriter->save($rawTempFile);

echo "\n\nRaw DOCX generated (before SDT injection)\n";
echo "Checking which files were created:\n";
$zip = new ZipArchive();
$zip->open($rawTempFile);
for ($i = 0; $i < $zip->numFiles; $i++) {
    $filename = $zip->getNameIndex($i);
    if (strpos($filename, 'word/') === 0 && strpos($filename, '.xml') !== false) {
        echo "  - {$filename}\n";
        
        // If it's a header or footer, show content
        if (strpos($filename, 'header') !== false || strpos($filename, 'footer') !== false) {
            $content = $zip->getFromName($filename);
            echo "    Content preview: " . substr(strip_tags($content), 0, 100) . "...\n";
        }
    }
}
$zip->close();

echo "\n\nNow attempting to save with Content Controls...\n";

try {
    $cc->save($tempFile);
    echo "\n\nDOCX saved successfully to: {$tempFile}\n";
} catch (\Exception $e) {
    echo "\n\nERROR saving DOCX: {$e->getMessage()}\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

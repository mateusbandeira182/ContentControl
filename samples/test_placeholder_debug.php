<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\Bridge\TableBuilder;
use MkGrow\ContentControl\ContentControl;

// Create template with SDT
$template = new ContentControl();
$section = $template->addSection();
$section->addText('Invoice #12345');

// Add SDT with placeholder text
$placeholder = $section->addText('Items will be inserted here');
$template->addContentControl($placeholder, [
    'tag' => 'invoice-items',
    'alias' => 'Invoice Items Table',
]);

$templatePath = __DIR__ . '/output/test_placeholder.docx';
$template->save($templatePath);

echo "Template saved.\n\n";

// Check XML before injection
$zip = new ZipArchive();
$zip->open($templatePath);
$xmlBefore = $zip->getFromName('word/document.xml');
$zip->close();

echo "XML BEFORE injection:\n";
echo str_replace('><', ">\n<", $xmlBefore) . "\n\n";

// Create and inject table
$builder = new TableBuilder();
$table = $builder->createTable([
    'rows' => [
        ['cells' => [['text' => 'Widget A']]],
    ],
]);

$builder->injectTable($templatePath, 'invoice-items', $table);

// Check XML after injection
$zip->open($templatePath);
$xmlAfter = $zip->getFromName('word/document.xml');
$zip->close();

echo "XML AFTER injection:\n";
echo str_replace('><', ">\n<", $xmlAfter) . "\n\n";

// Check if placeholder still exists
if (strpos($xmlAfter, 'Items will be inserted here') !== false) {
    echo "❌ FAIL: Placeholder still exists!\n";
} else {
    echo "✅ SUCCESS: Placeholder was replaced!\n";
}

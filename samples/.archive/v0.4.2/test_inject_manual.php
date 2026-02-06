<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\Bridge\TableBuilder;
use MkGrow\ContentControl\ContentControl;

// Create template
$template = new ContentControl();
$section = $template->addSection();
$placeholder = $section->addText('This will be replaced');
$template->addContentControl($placeholder, ['tag' => 'test-sdt']);

$templatePath = __DIR__ . '/output/test_inject.docx';
$template->save($templatePath);

echo "Template saved. SDT created with tag 'test-sdt'\n";

// Create table
$builder = new TableBuilder();
$table = $builder->createTable([
    'rows' => [
        ['cells' => [['text' => 'Cell 1'], ['text' => 'Cell 2']]],
    ],
]);

echo "Table created.\n";

// Inject table
$builder->injectTable($templatePath, 'test-sdt', $table);

echo "Table injected into template.\n";

// Verify
$zip = new ZipArchive();
$zip->open($templatePath);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

if (strpos($xml, '<w:tbl>') !== false) {
    echo "✅ SUCCESS: Table was injected!\n";
    echo "  XML contains: " . substr($xml, strpos($xml, '<w:tbl'), 100) . "...\n";
} else {
    echo "❌ FAIL: Table was NOT injected\n";
    echo "  Full XML:\n";
    echo $xml . "\n";
}

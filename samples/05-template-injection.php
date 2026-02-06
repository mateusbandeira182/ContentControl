<?php

declare(strict_types=1);

/**
 * Template Injection Example - TableBuilder UUID Matching
 *
 * Difficulty: Advanced
 * Features: Template injection, UUID v5 matching, placeholder replacement
 * Description: Injects complex table into existing template using placeholder SDT
 *
 * @package ContentControl
 * @version 0.5.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentProcessor;
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Bridge\TableBuilder;

echo "Template Injection Example\n";

// Check if template exists
$templatePath = __DIR__ . '/fixtures/table-template.docx';

if (!file_exists($templatePath)) {
    echo "⚠ Template not found: $templatePath\n";
    echo "\nTo run this example:\n";
    echo "1. Create a Word document\n";
    echo "2. Add a Content Control with tag: 'product-table-placeholder'\n";
    echo "3. Save as: samples/fixtures/table-template.docx\n";
    echo "4. Run this script again\n\n";
    echo "The library will replace the placeholder SDT with the generated table.\n";
    exit(1);
}

// Open template
$processor = new ContentProcessor($templatePath);

// Build complex table to inject
$contentControl = new ContentControl();
$builder = new TableBuilder($contentControl);

$builder->setStyles([
    'borderSize' => 6,
    'borderColor' => 'D32F2F',  // Red
    'cellMargin' => 100,
]);

$builder
    ->addRow()
        ->addCell(2500)->addText('SKU', ['bold' => true])->end()
        ->addCell(3500)->addText('Product Name', ['bold' => true])->end()
        ->addCell(1500)->addText('Stock', ['bold' => true])->end()
        ->addCell(2000)->addText('Price', ['bold' => true])->end()
        ->end()
    ->addRow()
        ->addCell(2500)->addText('WDG-001')->end()
        ->addCell(3500)->addText('Standard Widget')->end()
        ->addCell(1500)->addText('150')->end()
        ->addCell(2000)->addText('$25.00')->end()
        ->end()
    ->addRow()
        ->addCell(2500)->addText('WDG-002')->end()
        ->addCell(3500)->addText('Premium Widget')->end()
        ->addCell(1500)->addText('75')->end()
        ->addCell(2000)->addText('$45.00')->end()
        ->end();

// Inject table into template (replaces placeholder SDT)
$builder->injectInto($processor, 'product-table-placeholder');

// Save modified template
$outputFile = __DIR__ . '/output/05-injected-table.docx';
$processor->save($outputFile);

echo "✓ Table injected successfully: $outputFile\n";
echo "  The placeholder SDT was replaced with the generated table.\n";

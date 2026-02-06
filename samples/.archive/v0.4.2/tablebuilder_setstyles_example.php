<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\Bridge\TableBuilder;

/**
 * TableBuilder::setStyles() Example
 *
 * Demonstrates the new setStyles() method introduced in v0.5.0
 * for setting table-level styles with the fluent API.
 *
 * This eliminates the need to use the deprecated createTable() method
 * just to set table styles like borders, alignment, and width.
 *
 * @since 0.5.0
 */

echo "TableBuilder::setStyles() Example\n";
echo "==================================\n\n";

// Example 1: Basic table with borders
echo "1. Creating table with borders and alignment...\n";

$builder = new TableBuilder();

$builder->setStyles([
    'borderSize' => 6,
    'borderColor' => '000000',
    'alignment' => 'center'
])
->addRow()
    ->addCell(3000)->addText('Product')->end()
    ->addCell(2000)->addText('Price')->end()
    ->end()
->addRow()
    ->addCell(3000)->addText('Widget')->end()
    ->addCell(2000)->addText('$19.99')->end()
    ->end();

$outputPath1 = __DIR__ . '/output/tablebuilder_setstyles_basic.docx';
$builder->getContentControl()->save($outputPath1);
echo "   ✓ Saved to: $outputPath1\n\n";

// Example 2: Full-width table with custom margins
echo "2. Creating full-width table with custom cell margins...\n";

$builder2 = new TableBuilder();

$builder2->setStyles([
    'width' => 100,
    'unit' => 'pct',
    'cellMargin' => 100,
    'layout' => 'fixed',
    'borderSize' => 4,
    'borderColor' => '0000FF'
])
->addRow(720, ['tblHeader' => true])
    ->addCell(2000)->addText('Header 1')->end()
    ->addCell(2000)->addText('Header 2')->end()
    ->addCell(2000)->addText('Header 3')->end()
    ->end()
->addRow()
    ->addCell(2000)->addText('Data 1')->end()
    ->addCell(2000)->addText('Data 2')->end()
    ->addCell(2000)->addText('Data 3')->end()
    ->end();

$outputPath2 = __DIR__ . '/output/tablebuilder_setstyles_fullwidth.docx';
$builder2->getContentControl()->save($outputPath2);
echo "   ✓ Saved to: $outputPath2\n\n";

// Example 3: Table with Content Control (SDT)
echo "3. Creating styled table with Content Control...\n";

$builder3 = new TableBuilder();

$builder3->setStyles([
    'borderSize' => 8,
    'borderColor' => 'FF0000',
    'alignment' => 'left',
    'cellMargin' => 80
])
->addContentControl(['tag' => 'invoice-table', 'alias' => 'Invoice Items'])
->addRow()
    ->addCell(3000)->addText('Item')->end()
    ->addCell(1500)->addText('Quantity')->end()
    ->addCell(2000)->addText('Total')->end()
    ->end()
->addRow()
    ->addCell(3000)->addText('Office Chair')->end()
    ->addCell(1500)->addText('2')->end()
    ->addCell(2000)->addText('$299.98')->end()
    ->end();

$outputPath3 = __DIR__ . '/output/tablebuilder_setstyles_with_sdt.docx';
$builder3->getContentControl()->save($outputPath3);
echo "   ✓ Saved to: $outputPath3\n\n";

// Example 4: Error handling - calling setStyles() after table creation
echo "4. Demonstrating error handling...\n";

$builder4 = new TableBuilder();

$builder4->addRow()
    ->addCell(3000)->addText('Table already created')->end()
    ->end();

try {
    $builder4->setStyles(['borderSize' => 6]);
    echo "   ✗ ERROR: Should have thrown exception!\n";
} catch (\MkGrow\ContentControl\Exception\ContentControlException $e) {
    echo "   ✓ Exception caught correctly: {$e->getMessage()}\n";
}

echo "\n";
echo "==================================\n";
echo "All examples completed successfully!\n";
echo "Generated files are in samples/output/\n";

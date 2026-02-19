<?php

declare(strict_types=1);

/**
 * Basic Table Example - TableBuilder with Styles
 *
 * Difficulty: Basic
 * Features: TableBuilder, fluent API, setStyles() method (v0.5.0)
 * Description: Creates a styled table using the new setStyles() method
 *
 * @package ContentControl
 * @version 0.5.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Bridge\TableBuilder;

echo "Creating Basic Table document...\n";

// Create document and table builder
$contentControl = new ContentControl();
$builder = new TableBuilder($contentControl);

// Set table styles BEFORE adding rows (new in v0.5.0)
$builder->setStyles([
    'borderSize' => 6,           // Border width in eighths of a point
    'borderColor' => '1F4788',   // Blue border (hex without #)
    'cellMargin' => 100,         // Cell margin in twips
]);

// Build table using fluent API
$row1 = $builder->addRow();
$row1->addCell(3000)->addText('Product', ['bold' => true]);
$row1->addCell(2000)->addText('Quantity', ['bold' => true]);
$row1->addCell(2000)->addText('Price', ['bold' => true]);

$row2 = $builder->addRow();
$row2->addCell(3000)->addText('Widget A');
$row2->addCell(2000)->addText('10');
$row2->addCell(2000)->addText('$50.00');

$row3 = $builder->addRow();
$row3->addCell(3000)->addText('Widget B');
$row3->addCell(2000)->addText('5');
$row3->addCell(2000)->addText('$75.00');

// Table is automatically added to the document during build
// No need to inject - it's already part of contentControl

// Save document
$outputFile = __DIR__ . '/output/02-basic-table.docx';
$contentControl->save($outputFile);

echo "✓ Table document created successfully: $outputFile\n";
echo "  Open in Word to see blue borders and proper cell margins.\n";

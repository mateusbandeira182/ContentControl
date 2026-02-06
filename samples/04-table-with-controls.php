<?php

declare(strict_types=1);

/**
 * Table with Content Controls Example  
 *
 * Difficulty: Intermediate
 * Features: TableBuilder, cell-level SDTs, inlineLevel flag
 * Description: Creates table with Content Controls at cell level
 *
 * @package ContentControl
 * @version 0.5.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Bridge\TableBuilder;

echo "Creating Table with Content Controls...\n";

// Create document and table builder
$contentControl = new ContentControl();
$builder = new TableBuilder($contentControl);

// Set table styles
$builder->setStyles([
    'borderSize' => 4,
    'borderColor' => '2E7D32',  // Green
    'cellMargin' => 80,
]);

// Build table with Content Controls at cell level
$builder
    ->addRow()
        ->addCell(4000)
            ->addText('Employee Name', ['bold' => true])
            ->end()
        ->addCell(3000)
            ->addText('Department', ['bold' => true])
            ->end()
        ->end()
    ->addRow()
        ->addCell(4000)
            ->addText('John Doe')
            ->withContentControl([
                'alias' => 'Employee 1 Name',
                'tag' => 'emp1-name',
                'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
                'inlineLevel' => true,  // CRITICAL: Required for cell-level SDTs
            ])
            ->end()
        ->addCell(3000)
            ->addText('Engineering')
            ->withContentControl([
                'alias' => 'Employee 1 Department',
                'tag' => 'emp1-dept',
                'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
                'inlineLevel' => true,  // CRITICAL: Required for cell-level SDTs
            ])
            ->end()
        ->end()
    ->addRow()
        ->addCell(4000)
            ->addText('Jane Smith')
            ->withContentControl([
                'alias' => 'Employee 2 Name',
                'tag' => 'emp2-name',
                'inlineLevel' => true,
            ])
            ->end()
        ->addCell(3000)
            ->addText('Marketing')
            ->withContentControl([
                'alias' => 'Employee 2 Department',
                'tag' => 'emp2-dept',
                'inlineLevel' => true,
            ])
            ->end()
        ->end();

// Inject table
$builder->injectInto($contentControl);

// Save document
$outputFile = __DIR__ . '/output/04-table-with-controls.docx';
$contentControl->save($outputFile);

echo "✓ Document created successfully: $outputFile\n";
echo "  Open in Word Developer tab to see cell-level Content Controls.\n";
echo "\n";
echo "IMPORTANT: The 'inlineLevel' => true flag is required for SDTs inside table cells.\n";
echo "Without it, the SDT wrapping will fail. See docs/TableBuilder-v2.md for details.\n";

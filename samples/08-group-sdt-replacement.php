<?php

declare(strict_types=1);

/**
 * GROUP SDT Replacement Example
 *
 * Difficulty: Advanced
 * Features: GROUP SDTs, replaceGroupContent (v0.4.2)
 * Description: Replaces GROUP SDTs with complex structures
 *
 * @package ContentControl
 * @version 0.5.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentProcessor;
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Bridge\TableBuilder;

echo "GROUP SDT Replacement Example\n";

// Check if template exists
$templatePath = __DIR__ . '/fixtures/group-template.docx';

if (!file_exists($templatePath)) {
    echo "⚠ Template not found: $templatePath\n";
    echo "\nTo run this example:\n";
    echo "1. Create a Word document\n";
    echo "2. Add a GROUP Content Control with tag: 'report-section'\n";
    echo "3. Inside the group, add placeholder content\n";
    echo "4. Save as: samples/fixtures/group-template.docx\n";
    echo "5. Run this script again\n\n";
    echo "See docs/GROUP-SDT-FIX.md for GROUP SDT implementation details.\n";
    exit(1);
}

// Open template
$processor = new ContentProcessor($templatePath);

// Create complex replacement content
$replacement = new ContentControl();
$section = $replacement->addSection();

// Add title
$section->addTitle('Sales Report - Q1 2026', 2);

// Add summary text
$section->addText('Executive Summary', ['bold' => true, 'size' => 12]);
$section->addText('Total revenue increased by 23% compared to Q4 2025.');
$section->addText('Key highlights:', ['bold' => true]);
$section->addListItem('Electronics: +45%', 0);
$section->addListItem('Furniture: +12%', 0);
$section->addListItem('Accessories: +8%', 0);

// Add data table
$builder = new TableBuilder($replacement);
$builder->setStyles([
    'borderSize' => 4,
    'borderColor' => '388E3C',
    'cellMargin' => 80,
]);

$row1 = $builder->addRow();
$row1->addCell(3000)->addText('Category', ['bold' => true]);
$row1->addCell(2000)->addText('Q4 2025', ['bold' => true]);
$row1->addCell(2000)->addText('Q1 2026', ['bold' => true]);
$row1->addCell(2000)->addText('Growth', ['bold' => true]);

$row2 = $builder->addRow();
$row2->addCell(3000)->addText('Electronics');
$row2->addCell(2000)->addText('$125,000');
$row2->addCell(2000)->addText('$181,250');
$row2->addCell(2000)->addText('+45%', ['color' => '388E3C', 'bold' => true]);

$row3 = $builder->addRow();
$row3->addCell(3000)->addText('Furniture');
$row3->addCell(2000)->addText('$87,500');
$row3->addCell(2000)->addText('$98,000');
$row3->addCell(2000)->addText('+12%', ['color' => '388E3C', 'bold' => true]);

// Replace GROUP SDT with complex content
$processor->replaceGroupContent('report-section', $replacement);

// Save modified template
$outputFile = __DIR__ . '/output/08-group-replaced.docx';
$processor->save($outputFile);

echo "✓ GROUP SDT replaced successfully: $outputFile\n";
echo "  The entire GROUP control was replaced with title, text, list, and table.\n";
echo "\nNote: GROUP SDT fix implemented in v0.4.2\n";
echo "See docs/GROUP-SDT-FIX.md for technical details.\n";

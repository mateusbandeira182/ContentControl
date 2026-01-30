<?php
/**
 * TableBuilder Injection Example
 *
 * Demonstrates template injection workflow with injectTable().
 *
 * @package MkGrow\ContentControl
 * @version 0.3.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Bridge\TableBuilder;

// Output directory
$outputDir = __DIR__ . '/output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

echo "TableBuilder Injection Example\n";
echo "==============================\n\n";

// Step 1: Create Template
echo "Step 1: Creating template with Content Control placeholders...\n";

$template = new ContentControl();
$section = $template->addSection();

// Add title
$section->addText('INVOICE', ['bold' => true, 'size' => 18]);
$section->addText('');

// Add placeholder for invoice items
$itemsPlaceholder = $section->addText('[Invoice items will be inserted here]', ['italic' => true, 'color' => '808080']);
$template->addContentControl($itemsPlaceholder, [
    'tag' => 'invoice-items',
    'alias' => 'Invoice Items Table',
    'type' => ContentControl::TYPE_RICH_TEXT,
]);

$section->addText('');

// Add placeholder for totals
$totalsPlaceholder = $section->addText('[Totals will be inserted here]', ['italic' => true, 'color' => '808080']);
$template->addContentControl($totalsPlaceholder, [
    'tag' => 'invoice-totals',
    'alias' => 'Totals Table',
    'type' => ContentControl::TYPE_RICH_TEXT,
]);

$templatePath = $outputDir . '/invoice_template.docx';
$template->save($templatePath);

echo "✓ Template saved: output/invoice_template.docx\n\n";

// Step 2: Create and Inject Invoice Items Table
echo "Step 2: Creating invoice items table...\n";

$builder = new TableBuilder();

$items = [
    ['desc' => 'Professional Services (40 hours)', 'qty' => 40, 'rate' => '$150.00', 'amount' => '$6,000.00'],
    ['desc' => 'Software License (Annual)', 'qty' => 1, 'rate' => '$1,200.00', 'amount' => '$1,200.00'],
    ['desc' => 'Cloud Hosting (Monthly)', 'qty' => 12, 'rate' => '$50.00', 'amount' => '$600.00'],
    ['desc' => 'Support & Maintenance', 'qty' => 1, 'rate' => '$800.00', 'amount' => '$800.00'],
];

$itemsRows = [
    [
        'cells' => [
            ['text' => 'Description', 'width' => 4000, 'style' => ['bold' => true, 'bgColor' => '4472C4', 'color' => 'FFFFFF']],
            ['text' => 'Qty', 'width' => 1000, 'style' => ['bold' => true, 'bgColor' => '4472C4', 'color' => 'FFFFFF', 'alignment' => 'center']],
            ['text' => 'Rate', 'width' => 1500, 'style' => ['bold' => true, 'bgColor' => '4472C4', 'color' => 'FFFFFF', 'alignment' => 'right']],
            ['text' => 'Amount', 'width' => 1500, 'style' => ['bold' => true, 'bgColor' => '4472C4', 'color' => 'FFFFFF', 'alignment' => 'right']],
        ],
    ],
];

foreach ($items as $index => $item) {
    $bgColor = ($index % 2 === 0) ? 'FFFFFF' : 'F2F2F2';
    
    $itemsRows[] = ['cells' => [
        ['text' => $item['desc'], 'width' => 4000, 'style' => ['bgColor' => $bgColor]],
        ['text' => (string)$item['qty'], 'width' => 1000, 'style' => ['bgColor' => $bgColor, 'alignment' => 'center']],
        ['text' => $item['rate'], 'width' => 1500, 'style' => ['bgColor' => $bgColor, 'alignment' => 'right']],
        ['text' => $item['amount'], 'width' => 1500, 'style' => ['bgColor' => $bgColor, 'alignment' => 'right']],
    ]];
}

$itemsTable = $builder->createTable([
    'style' => ['borderSize' => 6, 'borderColor' => '4472C4'],
    'rows' => $itemsRows,
]);

echo "✓ Invoice items table created\n";
echo "  Injecting into template...\n";

$builder->injectTable($templatePath, 'invoice-items', $itemsTable);

echo "✓ Items table injected\n\n";

// Step 3: Create and Inject Totals Table
echo "Step 3: Creating totals table...\n";

$builder2 = new TableBuilder();

$totalsTable = $builder2->createTable([
    'style' => ['borderSize' => 6, 'borderColor' => '4472C4'],
    'rows' => [
        ['cells' => [
            ['text' => 'Subtotal', 'width' => 6500, 'style' => ['bold' => true, 'alignment' => 'right']],
            ['text' => '$8,600.00', 'width' => 1500, 'style' => ['alignment' => 'right']],
        ]],
        ['cells' => [
            ['text' => 'Tax (10%)', 'width' => 6500, 'style' => ['bold' => true, 'alignment' => 'right']],
            ['text' => '$860.00', 'width' => 1500, 'style' => ['alignment' => 'right']],
        ]],
        [
            'height' => 500,
            'cells' => [
                ['text' => 'TOTAL', 'width' => 6500, 'style' => ['bold' => true, 'size' => 12, 'alignment' => 'right', 'bgColor' => 'FFF2CC']],
                ['text' => '$9,460.00', 'width' => 1500, 'style' => ['bold' => true, 'size' => 12, 'alignment' => 'right', 'bgColor' => 'FFF2CC']],
            ],
        ],
    ],
]);

echo "✓ Totals table created\n";
echo "  Injecting into template...\n";

$builder2->injectTable($templatePath, 'invoice-totals', $totalsTable);

echo "✓ Totals table injected\n\n";

// Step 4: Verify Final Document
echo "Step 4: Final invoice created successfully!\n";
echo "✓ Output: output/invoice_template.docx\n\n";

// Example 2: Multiple Templates Workflow
echo "\nExample 2: Multiple Templates Workflow\n";
echo "=======================================\n\n";

echo "Creating report template with multiple tables...\n";

$reportTemplate = new ContentControl();
$reportSection = $reportTemplate->addSection();

// Title
$reportSection->addText('MONTHLY REPORT', ['bold' => true, 'size' => 16]);
$reportSection->addText('');

// Summary section
$reportSection->addText('Executive Summary', ['bold' => true, 'size' => 14]);
$summaryPlaceholder = $reportSection->addText('[Summary table]', ['italic' => true, 'color' => '808080']);
$reportTemplate->addContentControl($summaryPlaceholder, ['tag' => 'summary-table']);
$reportSection->addText('');

// Details section
$reportSection->addText('Detailed Breakdown', ['bold' => true, 'size' => 14]);
$detailsPlaceholder = $reportSection->addText('[Details table]', ['italic' => true, 'color' => '808080']);
$reportTemplate->addContentControl($detailsPlaceholder, ['tag' => 'details-table']);

$reportTemplatePath = $outputDir . '/report_template.docx';
$reportTemplate->save($reportTemplatePath);

echo "✓ Report template created\n\n";

// Create summary table
$builder3 = new TableBuilder();
$summaryTable = $builder3->createTable([
    'style' => ['borderSize' => 8, 'borderColor' => '2E5C8A'],
    'rows' => [
        ['cells' => [
            ['text' => 'Metric', 'width' => 4000, 'style' => ['bold' => true, 'bgColor' => '2E5C8A', 'color' => 'FFFFFF']],
            ['text' => 'Value', 'width' => 2000, 'style' => ['bold' => true, 'bgColor' => '2E5C8A', 'color' => 'FFFFFF', 'alignment' => 'right']],
        ]],
        ['cells' => [
            ['text' => 'Total Sales', 'width' => 4000],
            ['text' => '$125,000', 'width' => 2000, 'style' => ['alignment' => 'right']],
        ]],
        ['cells' => [
            ['text' => 'New Customers', 'width' => 4000],
            ['text' => '47', 'width' => 2000, 'style' => ['alignment' => 'right']],
        ]],
        ['cells' => [
            ['text' => 'Customer Satisfaction', 'width' => 4000],
            ['text' => '94%', 'width' => 2000, 'style' => ['alignment' => 'right']],
        ]],
    ],
]);

$builder3->injectTable($reportTemplatePath, 'summary-table', $summaryTable);
echo "✓ Summary table injected\n";

// Create details table
$builder4 = new TableBuilder();
$detailsTable = $builder4->createTable([
    'style' => ['borderSize' => 6, 'borderColor' => '70AD47'],
    'rows' => [
        ['cells' => [
            ['text' => 'Region', 'width' => 2000, 'style' => ['bold' => true, 'bgColor' => '70AD47', 'color' => 'FFFFFF']],
            ['text' => 'Sales', 'width' => 2000, 'style' => ['bold' => true, 'bgColor' => '70AD47', 'color' => 'FFFFFF']],
            ['text' => 'Growth', 'width' => 2000, 'style' => ['bold' => true, 'bgColor' => '70AD47', 'color' => 'FFFFFF']],
        ]],
        ['cells' => [
            ['text' => 'North', 'width' => 2000],
            ['text' => '$45,000', 'width' => 2000, 'style' => ['alignment' => 'right']],
            ['text' => '+12%', 'width' => 2000, 'style' => ['alignment' => 'right', 'color' => '008000']],
        ]],
        ['cells' => [
            ['text' => 'South', 'width' => 2000],
            ['text' => '$38,000', 'width' => 2000, 'style' => ['alignment' => 'right']],
            ['text' => '+8%', 'width' => 2000, 'style' => ['alignment' => 'right', 'color' => '008000']],
        ]],
        ['cells' => [
            ['text' => 'East', 'width' => 2000],
            ['text' => '$28,000', 'width' => 2000, 'style' => ['alignment' => 'right']],
            ['text' => '-3%', 'width' => 2000, 'style' => ['alignment' => 'right', 'color' => 'FF0000']],
        ]],
        ['cells' => [
            ['text' => 'West', 'width' => 2000],
            ['text' => '$14,000', 'width' => 2000, 'style' => ['alignment' => 'right']],
            ['text' => '+15%', 'width' => 2000, 'style' => ['alignment' => 'right', 'color' => '008000']],
        ]],
    ],
]);

$builder4->injectTable($reportTemplatePath, 'details-table', $detailsTable);
echo "✓ Details table injected\n\n";

echo "✓ Final report: output/report_template.docx\n\n";

echo "All injection examples completed successfully!\n";
echo "Generated files:\n";
echo "  - output/invoice_template.docx (with 2 injected tables)\n";
echo "  - output/report_template.docx (with 2 injected tables)\n";

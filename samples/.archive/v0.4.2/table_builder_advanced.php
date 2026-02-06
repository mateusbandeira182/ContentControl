<?php
/**
 * TableBuilder Advanced Example
 *
 * Demonstrates advanced styling with multi-level configuration.
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

echo "TableBuilder Advanced Example\n";
echo "=============================\n\n";

// Example 1: Styled Header Row
echo "1. Creating table with styled header...\n";

$builder = new TableBuilder();

$table = $builder->createTable([
    'style' => [
        'borderSize' => 10,
        'borderColor' => '1F4788',
        'cellMargin' => 100,
    ],
    'rows' => [
        // Header row with custom styling
        [
            'height' => 700,
            'cells' => [
                [
                    'text' => 'Product',
                    'width' => 3000,
                    'style' => [
                        'bgColor' => '1F4788',
                        'color' => 'FFFFFF',
                        'bold' => true,
                        'size' => 12,
                        'alignment' => 'center',
                        'valign' => 'center',
                    ],
                ],
                [
                    'text' => 'Quantity',
                    'width' => 2000,
                    'style' => [
                        'bgColor' => '1F4788',
                        'color' => 'FFFFFF',
                        'bold' => true,
                        'size' => 12,
                        'alignment' => 'center',
                        'valign' => 'center',
                    ],
                ],
                [
                    'text' => 'Price',
                    'width' => 2000,
                    'style' => [
                        'bgColor' => '1F4788',
                        'color' => 'FFFFFF',
                        'bold' => true,
                        'size' => 12,
                        'alignment' => 'center',
                        'valign' => 'center',
                    ],
                ],
            ],
        ],
        // Data rows
        ['cells' => [
            ['text' => 'Widget A', 'width' => 3000],
            ['text' => '5', 'width' => 2000, 'style' => ['alignment' => 'center']],
            ['text' => '$100.00', 'width' => 2000, 'style' => ['alignment' => 'right']],
        ]],
        ['cells' => [
            ['text' => 'Widget B', 'width' => 3000],
            ['text' => '3', 'width' => 2000, 'style' => ['alignment' => 'center']],
            ['text' => '$75.00', 'width' => 2000, 'style' => ['alignment' => 'right']],
        ]],
    ],
]);

$cc = $builder->getContentControl();
$cc->save($outputDir . '/advanced_styled_header.docx');

echo "✓ Saved to: output/advanced_styled_header.docx\n\n";

// Example 2: Alternating Row Colors
echo "2. Creating table with alternating row colors...\n";

$builder2 = new TableBuilder();

$data = [
    ['name' => 'Alice', 'department' => 'Sales', 'salary' => '$75,000'],
    ['name' => 'Bob', 'department' => 'Engineering', 'salary' => '$95,000'],
    ['name' => 'Charlie', 'department' => 'Marketing', 'salary' => '$68,000'],
    ['name' => 'Diana', 'department' => 'HR', 'salary' => '$72,000'],
    ['name' => 'Edward', 'department' => 'Engineering', 'salary' => '$98,000'],
];

$rows = [
    [
        'cells' => [
            ['text' => 'Employee', 'width' => 2500, 'style' => ['bgColor' => '4472C4', 'color' => 'FFFFFF', 'bold' => true]],
            ['text' => 'Department', 'width' => 2500, 'style' => ['bgColor' => '4472C4', 'color' => 'FFFFFF', 'bold' => true]],
            ['text' => 'Salary', 'width' => 2000, 'style' => ['bgColor' => '4472C4', 'color' => 'FFFFFF', 'bold' => true]],
        ],
    ],
];

foreach ($data as $index => $employee) {
    $bgColor = ($index % 2 === 0) ? 'D9E1F2' : 'FFFFFF';
    
    $rows[] = ['cells' => [
        ['text' => $employee['name'], 'width' => 2500, 'style' => ['bgColor' => $bgColor]],
        ['text' => $employee['department'], 'width' => 2500, 'style' => ['bgColor' => $bgColor]],
        ['text' => $employee['salary'], 'width' => 2000, 'style' => ['bgColor' => $bgColor, 'alignment' => 'right']],
    ]];
}

$table2 = $builder2->createTable([
    'style' => ['borderSize' => 6, 'borderColor' => '4472C4'],
    'rows' => $rows,
]);

$cc2 = $builder2->getContentControl();
$cc2->save($outputDir . '/advanced_alternating_colors.docx');

echo "✓ Saved to: output/advanced_alternating_colors.docx\n\n";

// Example 3: Financial Report Table
echo "3. Creating financial report table...\n";

$builder3 = new TableBuilder();

$table3 = $builder3->createTable([
    'style' => [
        'borderSize' => 8,
        'borderColor' => '2E5C8A',
        'cellMargin' => 120,
    ],
    'rows' => [
        // Title row spanning full width
        [
            'height' => 600,
            'cells' => [
                [
                    'text' => 'Q4 2025 Financial Summary',
                    'width' => 7000,
                    'style' => [
                        'bgColor' => '2E5C8A',
                        'color' => 'FFFFFF',
                        'bold' => true,
                        'size' => 14,
                        'alignment' => 'center',
                        'valign' => 'center',
                    ],
                ],
            ],
        ],
        // Header row
        [
            'cells' => [
                ['text' => 'Category', 'width' => 3000, 'style' => ['bold' => true, 'bgColor' => 'E7E6E6']],
                ['text' => 'Amount', 'width' => 2000, 'style' => ['bold' => true, 'bgColor' => 'E7E6E6', 'alignment' => 'right']],
                ['text' => 'Change', 'width' => 2000, 'style' => ['bold' => true, 'bgColor' => 'E7E6E6', 'alignment' => 'right']],
            ],
        ],
        // Revenue section
        ['cells' => [
            ['text' => 'Revenue', 'width' => 3000, 'style' => ['bold' => true]],
            ['text' => '$1,250,000', 'width' => 2000, 'style' => ['alignment' => 'right', 'color' => '008000']],
            ['text' => '+12.5%', 'width' => 2000, 'style' => ['alignment' => 'right', 'color' => '008000']],
        ]],
        ['cells' => [
            ['text' => 'Expenses', 'width' => 3000, 'style' => ['bold' => true]],
            ['text' => '$875,000', 'width' => 2000, 'style' => ['alignment' => 'right', 'color' => 'FF0000']],
            ['text' => '+8.3%', 'width' => 2000, 'style' => ['alignment' => 'right', 'color' => 'FF0000']],
        ]],
        // Totals row
        [
            'height' => 500,
            'cells' => [
                ['text' => 'Net Profit', 'width' => 3000, 'style' => ['bold' => true, 'bgColor' => 'FFF2CC']],
                ['text' => '$375,000', 'width' => 2000, 'style' => ['bold' => true, 'alignment' => 'right', 'bgColor' => 'FFF2CC']],
                ['text' => '+18.7%', 'width' => 2000, 'style' => ['bold' => true, 'alignment' => 'right', 'bgColor' => 'FFF2CC', 'color' => '008000']],
            ],
        ],
    ],
]);

$cc3 = $builder3->getContentControl();
$cc3->save($outputDir . '/advanced_financial_report.docx');

echo "✓ Saved to: output/advanced_financial_report.docx\n\n";

// Example 4: Complex Multi-Section Table
echo "4. Creating complex multi-section table...\n";

$builder4 = new TableBuilder();

$table4 = $builder4->createTable([
    'style' => [
        'borderSize' => 6,
        'borderColor' => '000000',
    ],
    'rows' => [
        // Section 1: Personal Information
        [
            'cells' => [
                ['text' => 'PERSONAL INFORMATION', 'width' => 8000, 'style' => ['bold' => true, 'bgColor' => '4472C4', 'color' => 'FFFFFF', 'size' => 12]],
            ],
        ],
        ['cells' => [
            ['text' => 'Name', 'width' => 2500, 'style' => ['bold' => true, 'bgColor' => 'D9E1F2']],
            ['text' => 'John Doe', 'width' => 5500],
        ]],
        ['cells' => [
            ['text' => 'Email', 'width' => 2500, 'style' => ['bold' => true, 'bgColor' => 'D9E1F2']],
            ['text' => 'john.doe@example.com', 'width' => 5500],
        ]],
        ['cells' => [
            ['text' => 'Phone', 'width' => 2500, 'style' => ['bold' => true, 'bgColor' => 'D9E1F2']],
            ['text' => '+1 (555) 123-4567', 'width' => 5500],
        ]],
        // Separator
        ['cells' => [['text' => '', 'width' => 8000, 'style' => ['bgColor' => 'FFFFFF']]]],
        // Section 2: Employment
        [
            'cells' => [
                ['text' => 'EMPLOYMENT DETAILS', 'width' => 8000, 'style' => ['bold' => true, 'bgColor' => '70AD47', 'color' => 'FFFFFF', 'size' => 12]],
            ],
        ],
        ['cells' => [
            ['text' => 'Position', 'width' => 2500, 'style' => ['bold' => true, 'bgColor' => 'E2EFDA']],
            ['text' => 'Senior Developer', 'width' => 5500],
        ]],
        ['cells' => [
            ['text' => 'Department', 'width' => 2500, 'style' => ['bold' => true, 'bgColor' => 'E2EFDA']],
            ['text' => 'Engineering', 'width' => 5500],
        ]],
        ['cells' => [
            ['text' => 'Start Date', 'width' => 2500, 'style' => ['bold' => true, 'bgColor' => 'E2EFDA']],
            ['text' => 'January 15, 2023', 'width' => 5500],
        ]],
    ],
]);

$cc4 = $builder4->getContentControl();
$cc4->save($outputDir . '/advanced_multi_section.docx');

echo "✓ Saved to: output/advanced_multi_section.docx\n\n";

echo "All advanced examples created successfully!\n";
echo "Check the output/ directory for generated DOCX files.\n";

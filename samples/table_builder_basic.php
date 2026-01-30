<?php
/**
 * TableBuilder Basic Example
 *
 * Demonstrates basic table creation with TableBuilder API.
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

echo "TableBuilder Basic Example\n";
echo "==========================\n\n";

// Example 1: Simple 2x2 Table
echo "1. Creating simple 2x2 table...\n";

$builder = new TableBuilder();

$table = $builder->createTable([
    'rows' => [
        ['cells' => [
            ['text' => 'Name'],
            ['text' => 'Age'],
        ]],
        ['cells' => [
            ['text' => 'Alice'],
            ['text' => '30'],
        ]],
        ['cells' => [
            ['text' => 'Bob'],
            ['text' => '25'],
        ]],
    ],
]);

$cc = $builder->getContentControl();
$cc->save($outputDir . '/basic_simple_table.docx');

echo "✓ Saved to: output/basic_simple_table.docx\n\n";

// Example 2: Table with Fixed Column Widths
echo "2. Creating table with custom widths...\n";

$builder2 = new TableBuilder();

$table2 = $builder2->createTable([
    'rows' => [
        ['cells' => [
            ['text' => 'Product', 'width' => 4000],
            ['text' => 'Quantity', 'width' => 2000],
            ['text' => 'Price', 'width' => 2000],
        ]],
        ['cells' => [
            ['text' => 'Widget A', 'width' => 4000],
            ['text' => '5', 'width' => 2000],
            ['text' => '$100.00', 'width' => 2000],
        ]],
        ['cells' => [
            ['text' => 'Widget B', 'width' => 4000],
            ['text' => '3', 'width' => 2000],
            ['text' => '$75.00', 'width' => 2000],
        ]],
    ],
]);

$cc2 = $builder2->getContentControl();
$cc2->save($outputDir . '/basic_with_widths.docx');

echo "✓ Saved to: output/basic_with_widths.docx\n\n";

// Example 3: Table with Border Styling
echo "3. Creating table with border styling...\n";

$builder3 = new TableBuilder();

$table3 = $builder3->createTable([
    'style' => [
        'borderSize' => 12,
        'borderColor' => '1F4788',
    ],
    'rows' => [
        ['cells' => [
            ['text' => 'Item'],
            ['text' => 'Status'],
        ]],
        ['cells' => [
            ['text' => 'Task 1'],
            ['text' => 'Complete'],
        ]],
        ['cells' => [
            ['text' => 'Task 2'],
            ['text' => 'In Progress'],
        ]],
        ['cells' => [
            ['text' => 'Task 3'],
            ['text' => 'Pending'],
        ]],
    ],
]);

$cc3 = $builder3->getContentControl();
$cc3->save($outputDir . '/basic_with_borders.docx');

echo "✓ Saved to: output/basic_with_borders.docx\n\n";

// Example 4: Dynamic Table from Data
echo "4. Creating dynamic table from array data...\n";

$builder4 = new TableBuilder();

// Sample data
$users = [
    ['name' => 'Alice Johnson', 'email' => 'alice@example.com', 'role' => 'Admin'],
    ['name' => 'Bob Smith', 'email' => 'bob@example.com', 'role' => 'User'],
    ['name' => 'Charlie Brown', 'email' => 'charlie@example.com', 'role' => 'User'],
    ['name' => 'Diana Prince', 'email' => 'diana@example.com', 'role' => 'Manager'],
];

// Build table configuration dynamically
$rows = [
    // Header row
    ['cells' => [
        ['text' => 'Name', 'width' => 3000],
        ['text' => 'Email', 'width' => 3500],
        ['text' => 'Role', 'width' => 1500],
    ]],
];

// Data rows
foreach ($users as $user) {
    $rows[] = ['cells' => [
        ['text' => $user['name'], 'width' => 3000],
        ['text' => $user['email'], 'width' => 3500],
        ['text' => $user['role'], 'width' => 1500],
    ]];
}

$table4 = $builder4->createTable([
    'style' => [
        'borderSize' => 6,
        'borderColor' => '000000',
    ],
    'rows' => $rows,
]);

$cc4 = $builder4->getContentControl();
$cc4->save($outputDir . '/basic_dynamic_table.docx');

echo "✓ Saved to: output/basic_dynamic_table.docx\n\n";

echo "All basic examples created successfully!\n";
echo "Check the output/ directory for generated DOCX files.\n";

<?php
/**
 * TableBuilder GROUP SDT Integration Example
 *
 * Demonstrates GROUP Content Control integration with fluent TableBuilder API.
 * This shows how to use ContentProcessor::replaceGroupContent() to inject
 * complex structures (text + tables + nested SDTs) into GROUP SDT placeholders.
 *
 * @package MkGrow\ContentControl
 * @version 0.4.2
 * @since 0.4.2
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\ContentProcessor;
use MkGrow\ContentControl\Bridge\TableBuilder;

// Output directory
$outputDir = __DIR__ . '/output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

echo "TableBuilder GROUP SDT Integration Example\n";
echo "===========================================\n\n";

// Step 1: Create Template with GROUP SDT Placeholder
echo "STEP 1: Creating template with GROUP SDT placeholder...\n";

$template = new ContentControl();
$section = $template->addSection();

// Add title
$section->addText('Invoice Template', ['size' => 18, 'bold' => true]);
$section->addText('');

// Add paragraph with GROUP SDT
$placeholder = $section->addText('{{ invoice_data }}');
$template->addContentControl($placeholder, [
    'tag' => 'invoice_section',
    'alias' => 'Invoice Data Section',
    'type' => ContentControl::TYPE_GROUP,
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Add footer
$section->addText('');
$section->addText('Thank you for your business!', ['italic' => true]);

$templatePath = $outputDir . '/group_template.docx';
$template->save($templatePath);
echo "✓ Template saved: output/group_template.docx\n\n";

// Step 2: Create Replacement Content (Complex Structure)
echo "STEP 2: Creating replacement content with fluent API...\n";

$replacement = new ContentControl();
$repSection = $replacement->addSection();

// Add invoice header
$repSection->addText('Customer Information', ['bold' => true, 'size' => 14]);
$customerName = $repSection->addText('Acme Corporation');
$replacement->addContentControl($customerName, [
    'tag' => 'customer_name',
    'alias' => 'Customer Name',
]);

$customerEmail = $repSection->addText('contact@acme.com', ['color' => '0066CC']);
$replacement->addContentControl($customerEmail, [
    'tag' => 'customer_email',
    'alias' => 'Customer Email',
]);

$repSection->addText('');

// Add invoice items table with fluent API
$repSection->addText('Invoice Items', ['bold' => true, 'size' => 14]);

$builder = new TableBuilder($replacement);
$builder
    ->addRow()
        ->addCell(3000)->addText('Item', ['bold' => true])->end()
        ->addCell(1500)->addText('Qty', ['bold' => true])->end()
        ->addCell(1500)->addText('Unit Price', ['bold' => true])->end()
        ->addCell(2000)->addText('Total', ['bold' => true])->end()
    ->end()
    ->addRow()
        ->addCell(3000)
            ->withContentControl([
                'tag' => 'item_1_name',
                'alias' => 'Item 1 Name',
            ])
            ->addText('Widget Premium')
        ->end()
        ->addCell(1500)
            ->withContentControl([
                'tag' => 'item_1_qty',
                'alias' => 'Item 1 Quantity',
            ])
            ->addText('5')
        ->end()
        ->addCell(1500)->addText('$50.00')->end()
        ->addCell(2000)->addText('$250.00', ['bold' => true])->end()
    ->end()
    ->addRow()
        ->addCell(3000)
            ->withContentControl([
                'tag' => 'item_2_name',
                'alias' => 'Item 2 Name',
            ])
            ->addText('Widget Standard')
        ->end()
        ->addCell(1500)
            ->withContentControl([
                'tag' => 'item_2_qty',
                'alias' => 'Item 2 Quantity',
            ])
            ->addText('10')
        ->end()
        ->addCell(1500)->addText('$30.00')->end()
        ->addCell(2000)->addText('$300.00', ['bold' => true])->end()
    ->end()
    ->addRow()
        ->addCell(6000, ['gridSpan' => 3])->addText('TOTAL:', ['bold' => true, 'align' => 'right'])->end()
        ->addCell(2000)
            ->withContentControl([
                'tag' => 'invoice_total',
                'alias' => 'Invoice Total Amount',
            ])
            ->addText('$550.00', ['bold' => true, 'size' => 12])
        ->end()
    ->end()
    ->addContentControl([
        'tag' => 'invoice_items_table',
        'alias' => 'Invoice Items Table',
        'lockType' => ContentControl::LOCK_SDT_LOCKED,
    ]);

echo "✓ Replacement content created with nested SDTs\n\n";

// Step 3: Replace GROUP SDT with Complex Content
echo "STEP 3: Replacing GROUP SDT with complex structure...\n";

$processor = new ContentProcessor($templatePath);
$success = $processor->replaceGroupContent('invoice_section', $replacement);

if ($success) {
    $outputPath = $outputDir . '/group_replaced_invoice.docx';
    $processor->save($outputPath);
    echo "✓ GROUP SDT replaced successfully!\n";
    echo "✓ Saved to: output/group_replaced_invoice.docx\n\n";
} else {
    echo "✗ Failed to replace GROUP SDT\n\n";
}

// Step 4: Success Message
echo "STEP 4: Verification complete!\n";

echo "SUCCESS: Complex structure with nested SDTs injected successfully!\n";
echo "Open 'group_replaced_invoice.docx' in Microsoft Word to verify:\n";
echo "  - Customer Name SDT (editable)\n";
echo "  - Customer Email SDT (editable)\n";
echo "  - Invoice Items Table SDT (locked)\n";
echo "  - Individual item cells with SDTs (editable)\n";
echo "  - Invoice Total SDT (editable)\n\n";

echo "\n========================================\n";
echo "GROUP SDT Workflow Summary\n";
echo "========================================\n\n";

echo "1. Template Creation:\n";
echo "   - Create document with GROUP SDT placeholder\n";
echo "   - Use ContentControl::TYPE_GROUP for type\n\n";

echo "2. Replacement Content:\n";
echo "   - Build complex structure with ContentControl\n";
echo "   - Use fluent TableBuilder for tables\n";
echo "   - Add nested SDTs with withContentControl()\n\n";

echo "3. GROUP Replacement:\n";
echo "   - Open template with ContentProcessor\n";
echo "   - Call replaceGroupContent(\$tag, \$contentControl)\n";
echo "   - Save modified document\n\n";

echo "4. Benefits:\n";
echo "   - ✓ Preserve nested Content Controls\n";
echo "   - ✓ Mix text, tables, and images\n";
echo "   - ✓ Deep serialization (w:sdtPr + w:sdtContent)\n";
echo "   - ✓ Template-based document generation\n\n";

echo "Example completed successfully!\n";
echo "Open 'group_replaced_invoice.docx' in Microsoft Word to inspect SDTs.\n";

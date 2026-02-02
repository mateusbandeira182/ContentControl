<?php

declare(strict_types=1);

/**
 * Inline-Level Content Controls Example (Experimental)
 * 
 * Demonstrates the experimental inline-level SDT injection feature.
 * 
 * IMPORTANT: This feature is currently EXPERIMENTAL and has limitations:
 * - PHPWord does not expose element context (container property)
 * - ElementLocator cannot locate Text/TextRun inside cells (v4.0 planned)
 * - Manual parameter 'inlineLevel' => true is REQUIRED
 * - Integration tests are skipped (awaiting ElementLocator enhancement)
 * 
 * Use Case:
 * Combine GROUP SDT (locks table structure) with inline SDTs (allows cell editing)
 * 
 * Expected Result:
 * - Table structure cannot be deleted (GROUP SDT)
 * - Individual cell content can be edited (inline SDTs)
 * 
 * @version Unreleased (v0.4.0)
 * @status Experimental - Infrastructure complete, ElementLocator pending
 */

require __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;

// ============================================================================
// 1. INITIALIZE DOCUMENT
// ============================================================================

$cc = new ContentControl();
$cc->getDocInfo()
    ->setCreator('ContentControl Library')
    ->setTitle('Inline-Level SDT Demonstration')
    ->setSubject('Experimental: Inline-Level Content Controls');

// ============================================================================
// 2. CREATE INVOICE TABLE WITH MIXED PROTECTION
// ============================================================================

$section = $cc->addSection();

// Add title
$title = $section->addTitle('Protected Invoice Template', 1);
$cc->addContentControl($title, [
    'alias' => 'Document Title',
    'tag' => 'invoice-title',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Add description
$section->addText(
    'This table demonstrates inline-level Content Controls. ' .
    'The table structure is locked (GROUP SDT), but individual cells can be edited.',
    ['italic' => true, 'color' => '666666']
);

$section->addTextBreak();

// Create invoice table
$table = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 80,
]);

// --- Header Row (Protected) ---
$table->addRow(500);
$headerCell1 = $table->addCell(3000, ['bgColor' => 'CCCCCC']);
$headerText1 = $headerCell1->addText('Item Description', ['bold' => true]);

$headerCell2 = $table->addCell(2000, ['bgColor' => 'CCCCCC']);
$headerText2 = $headerCell2->addText('Quantity', ['bold' => true]);

$headerCell3 = $table->addCell(2000, ['bgColor' => 'CCCCCC']);
$headerText3 = $headerCell3->addText('Unit Price', ['bold' => true]);

// Protect header cells (content locked)
$cc->addContentControl($headerText1, [
    'alias' => 'Header - Item',
    'tag' => 'header-item',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
    'inlineLevel' => true,  // EXPERIMENTAL: Inject inside <w:tc>
]);

$cc->addContentControl($headerText2, [
    'alias' => 'Header - Quantity',
    'tag' => 'header-qty',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
    'inlineLevel' => true,
]);

$cc->addContentControl($headerText3, [
    'alias' => 'Header - Price',
    'tag' => 'header-price',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
    'inlineLevel' => true,
]);

// --- Data Row 1 (Mixed Protection) ---
$table->addRow();

// Item description - EDITABLE (inline SDT, no lock)
$itemCell1 = $table->addCell(3000);
$itemText1 = $itemCell1->addText('Laptop Computer');
$cc->addContentControl($itemText1, [
    'alias' => 'Product 1 - Description',
    'tag' => 'product-1-desc',
    'lockType' => ContentControl::LOCK_NONE,
    'inlineLevel' => true,  // EXPERIMENTAL
]);

// Quantity - EDITABLE (inline SDT, no lock)
$qtyCell1 = $table->addCell(2000);
$qtyText1 = $qtyCell1->addText('2');
$cc->addContentControl($qtyText1, [
    'alias' => 'Product 1 - Quantity',
    'tag' => 'product-1-qty',
    'lockType' => ContentControl::LOCK_NONE,
    'inlineLevel' => true,
]);

// Unit price - LOCKED (inline SDT, content locked)
$priceCell1 = $table->addCell(2000);
$priceText1 = $priceCell1->addText('$1,200.00');
$cc->addContentControl($priceText1, [
    'alias' => 'Product 1 - Price',
    'tag' => 'product-1-price',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
    'inlineLevel' => true,
]);

// --- Data Row 2 (Mixed Protection) ---
$table->addRow();

$itemCell2 = $table->addCell(3000);
$itemText2 = $itemCell2->addText('Wireless Mouse');
$cc->addContentControl($itemText2, [
    'alias' => 'Product 2 - Description',
    'tag' => 'product-2-desc',
    'inlineLevel' => true,
]);

$qtyCell2 = $table->addCell(2000);
$qtyText2 = $qtyCell2->addText('5');
$cc->addContentControl($qtyText2, [
    'alias' => 'Product 2 - Quantity',
    'tag' => 'product-2-qty',
    'inlineLevel' => true,
]);

$priceCell2 = $table->addCell(2000);
$priceText2 = $priceCell2->addText('$25.00');
$cc->addContentControl($priceText2, [
    'alias' => 'Product 2 - Price',
    'tag' => 'product-2-price',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
    'inlineLevel' => true,
]);

// --- Total Row (Protected) ---
$table->addRow();
$table->addCell(5000, ['gridSpan' => 2])->addText('TOTAL:', [
    'bold' => true,
    'alignment' => 'right',
]);

$totalCell = $table->addCell(2000, ['bgColor' => 'FFFFCC']);
$totalText = $totalCell->addText('$2,525.00', ['bold' => true]);
$cc->addContentControl($totalText, [
    'alias' => 'Invoice Total',
    'tag' => 'invoice-total',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
    'inlineLevel' => true,
]);

// ============================================================================
// 3. PROTECT TABLE STRUCTURE (GROUP SDT)
// ============================================================================

// Wrap entire table with GROUP SDT (prevents structure modification)
$cc->addContentControl($table, [
    'alias' => 'Invoice Table Structure',
    'tag' => 'invoice-table',
    'type' => ContentControl::TYPE_GROUP,
    'lockType' => ContentControl::LOCK_SDT_LOCKED,  // Cannot delete table
]);

// ============================================================================
// 4. ADD EXPLANATORY NOTES
// ============================================================================

$section->addTextBreak(2);

$section->addText('Protection Summary:', ['bold' => true, 'size' => 12]);
$section->addListItem('Table structure: LOCKED (GROUP SDT - cannot delete or resize)', 0);
$section->addListItem('Header cells: CONTENT LOCKED (cannot edit text)', 0);
$section->addListItem('Item descriptions: EDITABLE (can change text)', 0);
$section->addListItem('Quantities: EDITABLE (can change numbers)', 0);
$section->addListItem('Unit prices: CONTENT LOCKED (fixed values)', 0);
$section->addListItem('Total: CONTENT LOCKED (calculated value)', 0);

$section->addTextBreak();

$section->addText('Technical Notes:', ['bold' => true, 'size' => 12]);
$section->addText(
    '⚠ Experimental Feature: Inline-level SDTs require \'inlineLevel\' => true parameter. ' .
    'ElementLocator enhancement planned for v4.0 to support automatic element location inside cells.',
    ['italic' => true, 'color' => 'FF6600']
);

// ============================================================================
// 5. SAVE DOCUMENT
// ============================================================================

$outputPath = __DIR__ . '/output/inline_sdt_example.docx';

// Ensure output directory exists
if (!is_dir(__DIR__ . '/output')) {
    mkdir(__DIR__ . '/output', 0755, true);
}

$cc->save($outputPath);

// ============================================================================
// 6. SUCCESS MESSAGE
// ============================================================================

echo "\n";
echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│         Inline-Level SDT Example (Experimental)                │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";
echo "│ Document created successfully!                                 │\n";
echo "│                                                                │\n";
echo "│ Output: {$outputPath}                            │\n";
echo "│                                                                │\n";
echo "│ Features Demonstrated:                                         │\n";
echo "│ ✅ Inline-level SDTs inside table cells                        │\n";
echo "│ ✅ GROUP SDT protecting table structure                        │\n";
echo "│ ✅ Mixed lock types (editable vs locked cells)                 │\n";
echo "│                                                                │\n";
echo "│ ⚠ EXPERIMENTAL STATUS:                                         │\n";
echo "│ - Infrastructure complete (SDTConfig, SDTInjector)             │\n";
echo "│ - ElementLocator enhancement pending (v4.0)                    │\n";
echo "│ - Integration tests skipped (awaiting v4.0)                    │\n";
echo "│                                                                │\n";
echo "│ Validation:                                                    │\n";
echo "│ 1. Open document in Microsoft Word/OnlyOffice/LibreOffice      │\n";
echo "│ 2. Try to delete table → Should be blocked (GROUP SDT)         │\n";
echo "│ 3. Try to edit header cells → Should be blocked                │\n";
echo "│ 4. Try to edit item descriptions → Should work                 │\n";
echo "│ 5. Try to edit unit prices → Should be blocked                 │\n";
echo "└────────────────────────────────────────────────────────────────┘\n";
echo "\n";

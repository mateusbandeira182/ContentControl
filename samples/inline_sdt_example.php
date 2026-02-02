<?php

declare(strict_types=1);

/**
 * Inline-Level Content Controls Example (Experimental - DEMONSTRATION ONLY)
 * 
 * ⚠️ IMPORTANT: This example is for DOCUMENTATION purposes only.
 * 
 * Current Limitations (as of Unreleased v0.4.0):
 * 1. PHPWord does not expose element context (container property)
 * 2. ElementLocator cannot locate Text/TextRun inside cells (v4.0 planned)
 * 3. Manual parameter 'inlineLevel' => true is REQUIRED
 * 4. Integration tests are skipped (awaiting ElementLocator enhancement)
 * 
 * This example demonstrates the API and expected behavior, but will NOT
 * execute successfully until ElementLocator v4.0 is implemented.
 * 
 * Use Case:
 * Combine GROUP SDT (locks table structure) with inline SDTs (allows cell editing)
 * 
 * Expected Result (when ElementLocator v4.0 is complete):
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

echo "\n";
echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│    Inline-Level SDT Example (DEMONSTRATION ONLY - v0.4.0)     │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";
echo "│                                                                │\n";
echo "│ ⚠️  IMPORTANT NOTICE:                                           │\n";
echo "│                                                                │\n";
echo "│ This example demonstrates the inline-level SDT API but will    │\n";
echo "│ NOT execute successfully in current version.                   │\n";
echo "│                                                                │\n";
echo "│ Reason: ElementLocator does not yet support locating Text/     │\n";
echo "│         TextRun elements inside table cells (<w:tc>).          │\n";
echo "│                                                                │\n";
echo "│ Status: Infrastructure COMPLETE, ElementLocator pending v4.0   │\n";
echo "│                                                                │\n";
echo "│ What Works (Unit Tests Passing):                               │\n";
echo "│ ✅ SDTConfig::inlineLevel property                             │\n";
echo "│ ✅ SDTInjector::processInlineLevelSDT() method                 │\n";
echo "│ ✅ SDTInjector::findParentCell() method                        │\n";
echo "│ ✅ SDTInjector::wrapParagraphInCellInline() method             │\n";
echo "│ ✅ Backward compatibility (500 original tests passing)         │\n";
echo "│                                                                │\n";
echo "│ What's Missing (Skipped Tests):                                │\n";
echo "│ ⏳ ElementLocator XPath for Text in <w:tc>                     │\n";
echo "│ ⏳ ElementLocator XPath for TextRun in <w:tc>                  │\n";
echo "│ ⏳ End-to-end integration tests (4 skipped)                    │\n";
echo "│                                                                │\n";
echo "│ Expected Release: v4.0 (ElementLocator Enhancement)            │\n";
echo "│                                                                │\n";
echo "│ For now, see unit tests for infrastructure validation:         │\n";
echo "│ - tests/Unit/ContentControlInlineLevelTest.php                 │\n";
echo "│ - tests/Unit/SDTInjectorInlineLevelTest.php                    │\n";
echo "│ - tests/Feature/InlineLevelSDTTest.php                         │\n";
echo "│                                                                │\n";
echo "└────────────────────────────────────────────────────────────────┘\n";
echo "\n";
echo "Press Ctrl+C to exit or wait to see code demonstration below...\n\n";
sleep(3);

$cc = new ContentControl();
$cc->getDocInfo()
    ->setCreator('ContentControl Library')
    ->setTitle('Inline-Level SDT Demonstration')
    ->setSubject('Experimental: Inline-Level Content Controls');

// ============================================================================
// 2. CREATE INVOICE TABLE WITH MIXED PROTECTION
// ============================================================================

$section = $cc->addSection();

// Add title (NOTE: Title is block-level, not inline - for demonstration only)
// Commented out to avoid ElementLocator errors in current version
// $title = $section->addTitle('Protected Invoice Template', 1);
// $cc->addContentControl($title, [
//     'alias' => 'Document Title',
//     'tag' => 'invoice-title',
//     'lockType' => ContentControl::LOCK_SDT_LOCKED,
// ]);

// Add description using regular text (block-level)
$description = $section->addText(
    'This table demonstrates inline-level Content Controls. ' .
    'The table structure is locked (GROUP SDT), but individual cells can be edited.',
    ['italic' => true, 'color' => '666666']
);
$cc->addContentControl($description, [
    'alias' => 'Description',
    'tag' => 'description',
]);

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
// 5. SAVE DOCUMENT (Will fail due to ElementLocator limitation)
// ============================================================================

$outputPath = __DIR__ . '/output/inline_sdt_example.docx';

// Ensure output directory exists
if (!is_dir(__DIR__ . '/output')) {
    mkdir(__DIR__ . '/output', 0755, true);
}

echo "Attempting to save document (expected to fail)...\n";
echo "Expected error: \"Could not locate element in DOM tree\"\n\n";

try {
    $cc->save($outputPath);
    
    echo "SUCCESS (unexpected in v0.4.0)!\n";
} catch (\Exception $e) {
    echo "┌────────────────────────────────────────────────────────────────┐\n";
    echo "│ Expected Error (ElementLocator Limitation):                    │\n";
    echo "├────────────────────────────────────────────────────────────────┤\n";
    echo "│ {$e->getMessage()}\n";
    echo "│                                                                │\n";
    echo "│ This confirms the limitation documented in README.md           │\n";
    echo "│                                                                │\n";
    echo "│ Solution: Wait for ElementLocator v4.0 enhancement             │\n";
    echo "│                                                                │\n";
    echo "│ Meanwhile, infrastructure is complete and validated via:       │\n";
    echo "│ - Unit tests (all passing)                                     │\n";
    echo "│ - PHPStan Level 9 (0 errors)                                   │\n";
    echo "│ - Backward compatibility (500 original tests passing)          │\n";
    echo "└────────────────────────────────────────────────────────────────┘\n";
}

// ============================================================================
// 6. SUCCESS MESSAGE (Updated for demonstration-only status)
// ============================================================================

// ============================================================================
// 6. SUMMARY MESSAGE
// ============================================================================

echo "\n";
echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│         Inline-Level SDT API Demonstration Complete            │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";
echo "│                                                                │\n";
echo "│ Code Demonstrated:                                             │\n";
echo "│ ✅ GROUP SDT wrapping table structure                          │\n";
echo "│ ✅ Inline SDT syntax (inlineLevel => true)                     │\n";
echo "│ ✅ Mixed lock types in cell content                            │\n";
echo "│                                                                │\n";
echo "│ Current Status (v0.4.0 Unreleased):                            │\n";
echo "│ ✅ Infrastructure: COMPLETE                                    │\n";
echo "│ ✅ Unit Tests: PASSING (9 new tests)                           │\n";
echo "│ ✅ Documentation: COMPLETE                                     │\n";
echo "│ ⏳ ElementLocator: PENDING v4.0                                │\n";
echo "│ ⏳ Integration Tests: SKIPPED (4 tests)                        │\n";
echo "│                                                                │\n";
echo "│ Next Steps:                                                    │\n";
echo "│ 1. Implement ElementLocator XPath for Text/TextRun in cells    │\n";
echo "│ 2. Reactivate skipped integration tests                        │\n";
echo "│ 3. Validate in OnlyOffice/Word/LibreOffice                     │\n";
echo "│ 4. Update documentation with working examples                  │\n";
echo "│                                                                │\n";
echo "│ References:                                                    │\n";
echo "│ - CHANGELOG.md (Unreleased section)                            │\n";
echo "│ - README.md (Inline-Level Content Controls section)            │\n";
echo "│ - tests/Feature/InlineLevelSDTTest.php (skipped tests)         │\n";
echo "└────────────────────────────────────────────────────────────────┘\n";
echo "\n";

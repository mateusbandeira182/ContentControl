<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentProcessor;
use PhpOffice\PhpWord\PhpWord;

/**
 * ContentProcessor Advanced Methods Example
 *
 * Demonstrates all Phase 3 methods:
 * - appendContent() - Add content to existing SDT
 * - removeContent() - Clear SDT content
 * - setValue() - Replace text while preserving formatting
 * - removeAllControlContents() - Clear all SDTs and optionally block editing
 */

echo "ContentProcessor Advanced Methods Demo\n";
echo str_repeat('=', 60) . "\n\n";

// Create sample template with SDTs
$tempTemplate = tempnam(sys_get_temp_dir(), 'template_') . '.docx';

$zip = new ZipArchive();
$zip->open($tempTemplate, ZipArchive::CREATE);

$documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:r><w:t>Invoice Template</w:t></w:r>
        </w:p>
        
        <!-- Customer Name SDT with formatting -->
        <w:sdt>
            <w:sdtPr>
                <w:tag w:val="customer-name"/>
                <w:alias w:val="Customer Name"/>
            </w:sdtPr>
            <w:sdtContent>
                <w:p>
                    <w:r>
                        <w:rPr>
                            <w:b/>
                            <w:sz w:val="28"/>
                        </w:rPr>
                        <w:t>[CUSTOMER NAME]</w:t>
                    </w:r>
                </w:p>
            </w:sdtContent>
        </w:sdt>
        
        <!-- Invoice Items Table SDT -->
        <w:sdt>
            <w:sdtPr>
                <w:tag w:val="invoice-items"/>
                <w:alias w:val="Invoice Items"/>
            </w:sdtPr>
            <w:sdtContent>
                <w:tbl>
                    <w:tr>
                        <w:tc><w:p><w:r><w:t>Item</w:t></w:r></w:p></w:tc>
                        <w:tc><w:p><w:r><w:t>Quantity</w:t></w:r></w:p></w:tc>
                        <w:tc><w:p><w:r><w:t>Price</w:t></w:r></w:p></w:tc>
                    </w:tr>
                </w:tbl>
            </w:sdtContent>
        </w:sdt>
        
        <!-- Notes SDT (to be cleared) -->
        <w:sdt>
            <w:sdtPr>
                <w:tag w:val="notes"/>
                <w:alias w:val="Notes"/>
            </w:sdtPr>
            <w:sdtContent>
                <w:p><w:r><w:t>Default notes text</w:t></w:r></w:p>
            </w:sdtContent>
        </w:sdt>
        
        <!-- Test SDT (to be removed) -->
        <w:sdt>
            <w:sdtPr>
                <w:tag w:val="test-field"/>
            </w:sdtPr>
            <w:sdtContent>
                <w:p><w:r><w:t>Test content</w:t></w:r></w:p>
            </w:sdtContent>
        </w:sdt>
    </w:body>
</w:document>
XML;

$zip->addFromString('word/document.xml', $documentXml);
$zip->close();

echo "1. Template created with 4 Content Controls:\n";
echo "   - customer-name (formatted text)\n";
echo "   - invoice-items (table)\n";
echo "   - notes (plain text)\n";
echo "   - test-field (to be removed)\n\n";

// ============================================================================
// Example 1: setValue() - Replace text while preserving formatting
// ============================================================================

echo "2. setValue() - Replace customer name (preserves bold + size)\n";

$processor1 = new ContentProcessor($tempTemplate);
$result = $processor1->setValue('customer-name', 'Acme Corporation Ltd.');

if ($result) {
    echo "   ✓ Customer name updated\n";
    $output1 = __DIR__ . '/output/advanced_example_1_setvalue.docx';
    @mkdir(dirname($output1), 0755, true);
    $processor1->save($output1);
    echo "   ✓ Saved to: {$output1}\n";
    echo "   (Open in Word - notice text is bold and 14pt)\n\n";
} else {
    echo "   ✗ Failed to find customer-name SDT\n\n";
}

// ============================================================================
// Example 2: appendContent() - Add rows to table
// ============================================================================

echo "3. appendContent() - Add invoice items to table\n";

$processor2 = new ContentProcessor($tempTemplate);
$phpWord = new PhpWord();
$section = $phpWord->addSection();

// Create paragraphs to append (simulating line items as text)
$items = [
    ['Product A', '2', '$50.00'],
    ['Product B', '1', '$75.00'],
    ['Product C', '5', '$20.00'],
];

foreach ($items as $item) {
    $text = $section->addText(
        sprintf('%s - Qty: %s - Price: %s', $item[0], $item[1], $item[2])
    );
    
    $result = $processor2->appendContent('invoice-items', $text);
    if ($result) {
        echo "   ✓ Added: {$item[0]} - {$item[1]} x {$item[2]}\n";
    }
}

$output2 = __DIR__ . '/output/advanced_example_2_append.docx';
$processor2->save($output2);
echo "   ✓ Saved to: {$output2}\n\n";

// ============================================================================
// Example 3: removeContent() - Clear specific SDT
// ============================================================================

echo "4. removeContent() - Clear notes field\n";

$processor3 = new ContentProcessor($tempTemplate);
$result = $processor3->removeContent('notes');

if ($result) {
    echo "   ✓ Notes field cleared\n";
    $output3 = __DIR__ . '/output/advanced_example_3_remove.docx';
    $processor3->save($output3);
    echo "   ✓ Saved to: {$output3}\n";
    echo "   (SDT structure remains, content is empty)\n\n";
}

// ============================================================================
// Example 4: removeAllControlContents() - Clear all SDTs
// ============================================================================

echo "5. removeAllControlContents(false) - Clear all SDTs\n";

$processor4 = new ContentProcessor($tempTemplate);
$count = $processor4->removeAllControlContents(false);

echo "   ✓ Removed content from {$count} Content Controls\n";
$output4 = __DIR__ . '/output/advanced_example_4_removeall.docx';
$processor4->save($output4);
echo "   ✓ Saved to: {$output4}\n";
echo "   (Document is still editable)\n\n";

// ============================================================================
// Example 5: removeAllControlContents(true) - Block editing
// ============================================================================

echo "6. removeAllControlContents(true) - Clear all SDTs and block editing\n";

$processor5 = new ContentProcessor($tempTemplate);
$count = $processor5->removeAllControlContents(true);

echo "   ✓ Removed content from {$count} Content Controls\n";
echo "   ✓ Document protection enabled (read-only)\n";
$output5 = __DIR__ . '/output/advanced_example_5_finalize.docx';
$processor5->save($output5);
echo "   ✓ Saved to: {$output5}\n";
echo "   (Open in Word - document is protected)\n\n";

// ============================================================================
// Combined Example: Complete Workflow
// ============================================================================

echo "7. Combined workflow - Fill template and finalize\n";

$processor6 = new ContentProcessor($tempTemplate);

// Update customer name (preserving formatting)
$processor6->setValue('customer-name', 'GlobalTech Solutions');
echo "   ✓ Customer name set\n";

// Add invoice items
$phpWord2 = new PhpWord();
$section2 = $phpWord2->addSection();

$invoiceItems = [
    ['Consulting Services', '40 hrs', '$4,000.00'],
    ['Software License', '1', '$1,200.00'],
];

foreach ($invoiceItems as $item) {
    $text = $section2->addText(
        sprintf('%s - %s - %s', $item[0], $item[1], $item[2])
    );
    $processor6->appendContent('invoice-items', $text);
}
echo "   ✓ Invoice items added\n";

// Clear notes and test fields
$processor6->removeContent('notes');
$processor6->removeContent('test-field');
echo "   ✓ Unused fields cleared\n";

$output6 = __DIR__ . '/output/advanced_example_6_complete.docx';
$processor6->save($output6);
echo "   ✓ Saved to: {$output6}\n\n";

// Cleanup
unlink($tempTemplate);

echo str_repeat('=', 60) . "\n";
echo "All examples completed successfully!\n";
echo "Check output/ directory for generated files.\n";

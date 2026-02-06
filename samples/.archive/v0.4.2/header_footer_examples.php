<?php

declare(strict_types=1);

/**
 * Header and Footer Content Controls Example (v0.2.0)
 * 
 * Demonstrates all header/footer features:
 * - Default headers and footers
 * - First page headers and footers  
 * - Even page headers and footers
 * - Different element types in headers/footers
 * - Multiple sections with independent headers/footers
 */

require __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;

echo "ContentControl v0.2.0 - Header/Footer Examples\n";
echo str_repeat('=', 60) . "\n\n";

// ============================================================================
// Example 1: Basic Header and Footer Protection
// ============================================================================

echo "Example 1: Basic Header and Footer Protection\n";
echo str_repeat('-', 60) . "\n";

$cc1 = new ContentControl();
$section1 = $cc1->addSection();

// Add header with protected company name
$header1 = $section1->addHeader();
$headerText = $header1->addText('ACME Corporation - Confidential Document', [
    'bold' => true,
    'size' => 12,
]);

$cc1->addContentControl($headerText, [
    'alias' => 'Company Header',
    'tag' => 'company-header',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Add footer with protected disclaimer
$footer1 = $section1->addFooter();
$footerText = $footer1->addText('© 2026 ACME Corporation. All Rights Reserved.', [
    'alignment' => 'center',
]);

$cc1->addContentControl($footerText, [
    'alias' => 'Copyright Footer',
    'tag' => 'copyright',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Add some body content
$section1->addText('This document has protected headers and footers.');

$output1 = __DIR__ . '/output/example_1_basic_header_footer.docx';
$cc1->save($output1);

echo "✓ Generated: example_1_basic_header_footer.docx\n";
echo "  - Header: Protected company name\n";
echo "  - Footer: Protected copyright notice\n\n";

// ============================================================================
// Example 2: Complex Header with Table
// ============================================================================

echo "Example 2: Complex Header with Table\n";
echo str_repeat('-', 60) . "\n";

$cc2 = new ContentControl();
$section2 = $cc2->addSection();

// Create header with table for letterhead
$header2 = $section2->addHeader();
$headerTable = $header2->addTable([
    'borderSize' => 0,
    'width' => 100 * 50,
    'unit' => 'pct',
]);

$headerTable->addRow(400);
$headerTable->addCell(4000)->addText('ACME Corporation', ['bold' => true, 'size' => 14]);
$headerTable->addCell(4000)->addText('Doc #: 12345', ['alignment' => 'right']);

$headerTable->addRow(300);
$headerTable->addCell(4000)->addText('123 Business St, City, ST 12345', ['size' => 9]);
$headerTable->addCell(4000)->addText('Date: ' . date('Y-m-d'), ['alignment' => 'right', 'size' => 9]);

$cc2->addContentControl($headerTable, [
    'alias' => 'Letterhead Table',
    'tag' => 'letterhead',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Add body content
$section2->addText('This document has a table-based letterhead in the header.');

$output2 = __DIR__ . '/output/example_2_table_header.docx';
$cc2->save($output2);

echo "✓ Generated: example_2_table_header.docx\n";
echo "  - Header: Protected table with company info and document metadata\n\n";

// ============================================================================
// Example 3: First Page vs Default Headers
// ============================================================================

echo "Example 3: First Page vs Default Headers\n";
echo str_repeat('-', 60) . "\n";

$cc3 = new ContentControl();
$section3 = $cc3->addSection();

// First page header (cover page)
$firstHeader = $section3->addHeader('first');
$firstHeaderText = $firstHeader->addText('CORPORATE REPORT - COVER PAGE', [
    'bold' => true,
    'size' => 16,
    'alignment' => 'center',
    'color' => '1F4788',
]);

$cc3->addContentControl($firstHeaderText, [
    'alias' => 'Cover Page Header',
    'tag' => 'cover-header',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Default header (subsequent pages)
$defaultHeader = $section3->addHeader();
$defaultHeaderText = $defaultHeader->addText('CORPORATE REPORT', [
    'bold' => true,
    'size' => 10,
]);

$cc3->addContentControl($defaultHeaderText, [
    'alias' => 'Default Header',
    'tag' => 'default-header',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);

// Add content
$section3->addText('Page 1 (Cover) - Different header');
$section3->addPageBreak();
$section3->addText('Page 2 - Uses default header');

$output3 = __DIR__ . '/output/example_3_first_page_header.docx';
$cc3->save($output3);

echo "✓ Generated: example_3_first_page_header.docx\n";
echo "  - First page: Special cover page header\n";
echo "  - Subsequent pages: Standard header\n\n";

// ============================================================================
// Example 4: Even Page Footers (for book-style documents)
// ============================================================================

echo "Example 4: Even Page Footers\n";
echo str_repeat('-', 60) . "\n";

$cc4 = new ContentControl();
$section4 = $cc4->addSection();

// Default footer (odd pages)
$oddFooter = $section4->addFooter();
$oddFooterText = $oddFooter->addText('Chapter 1 - Introduction', [
    'alignment' => 'right',
]);

$cc4->addContentControl($oddFooterText, [
    'alias' => 'Odd Page Footer',
    'tag' => 'odd-footer',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);

// Even footer (even pages)
$evenFooter = $section4->addFooter('even');
$evenFooterText = $evenFooter->addText('ACME Corporation Annual Report', [
    'alignment' => 'left',
]);

$cc4->addContentControl($evenFooterText, [
    'alias' => 'Even Page Footer',
    'tag' => 'even-footer',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);

// Add multiple pages
for ($i = 1; $i <= 4; $i++) {
    $section4->addText("Page {$i} content");
    if ($i < 4) {
        $section4->addPageBreak();
    }
}

$output4 = __DIR__ . '/output/example_4_even_page_footer.docx';
$cc4->save($output4);

echo "✓ Generated: example_4_even_page_footer.docx\n";
echo "  - Odd pages: Chapter name on right\n";
echo "  - Even pages: Document name on left\n\n";

// ============================================================================
// Example 5: Multiple Sections with Different Headers/Footers
// ============================================================================

echo "Example 5: Multiple Sections with Independent Headers/Footers\n";
echo str_repeat('-', 60) . "\n";

$cc5 = new ContentControl();

// Section 1: Introduction
$intro = $cc5->addSection();
$introHeader = $intro->addHeader();
$introHeaderText = $introHeader->addText('Section 1: Introduction', ['bold' => true]);
$cc5->addContentControl($introHeaderText, [
    'alias' => 'Intro Header',
    'tag' => 'intro-header',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

$introFooter = $intro->addFooter();
$introFooterText = $introFooter->addText('Introductory Material', [
    'italic' => true,
    'alignment' => 'center',
]);
$cc5->addContentControl($introFooterText, [
    'alias' => 'Intro Footer',
    'tag' => 'intro-footer',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);

$intro->addText('Introduction section content');

// Section 2: Main Content
$main = $cc5->addSection();
$mainHeader = $main->addHeader();
$mainHeaderText = $mainHeader->addText('Section 2: Main Content', ['bold' => true]);
$cc5->addContentControl($mainHeaderText, [
    'alias' => 'Main Header',
    'tag' => 'main-header',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

$mainFooter = $main->addFooter();
$mainFooterText = $mainFooter->addText('Core Analysis and Findings', [
    'italic' => true,
    'alignment' => 'center',
]);
$cc5->addContentControl($mainFooterText, [
    'alias' => 'Main Footer',
    'tag' => 'main-footer',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);

$main->addText('Main section content');

// Section 3: Conclusion
$conclusion = $cc5->addSection();
$conclusionHeader = $conclusion->addHeader();
$conclusionHeaderText = $conclusionHeader->addText('Section 3: Conclusion', ['bold' => true]);
$cc5->addContentControl($conclusionHeaderText, [
    'alias' => 'Conclusion Header',
    'tag' => 'conclusion-header',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

$conclusionFooter = $conclusion->addFooter();
$conclusionFooterText = $conclusionFooter->addText('Final Remarks', [
    'italic' => true,
    'alignment' => 'center',
]);
$cc5->addContentControl($conclusionFooterText, [
    'alias' => 'Conclusion Footer',
    'tag' => 'conclusion-footer',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);

$conclusion->addText('Conclusion section content');

$output5 = __DIR__ . '/output/example_5_multiple_sections.docx';
$cc5->save($output5);

echo "✓ Generated: example_5_multiple_sections.docx\n";
echo "  - 3 sections, each with unique headers/footers\n";
echo "  - All headers/footers protected with Content Controls\n\n";

// ============================================================================
// Example 6: Mixed Content Types in Headers/Footers
// ============================================================================

echo "Example 6: Mixed Content Types in Headers/Footers\n";
echo str_repeat('-', 60) . "\n";

$cc6 = new ContentControl();
$section6 = $cc6->addSection();

// Header with multiple elements
$header6 = $section6->addHeader();

// Simple text
$headerTitle = $header6->addText('QUARTERLY REPORT', ['bold' => true]);
$cc6->addContentControl($headerTitle, [
    'alias' => 'Report Title',
    'tag' => 'report-title',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Text run with formatting
$headerMetadata = $header6->addTextRun();
$headerMetadata->addText('Q4 2026', ['italic' => true]);
$headerMetadata->addText(' | ');
$headerMetadata->addText('Version 1.0', ['color' => '666666']);
$cc6->addContentControl($headerMetadata, [
    'alias' => 'Report Metadata',
    'tag' => 'metadata',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);

// Footer with table
$footer6 = $section6->addFooter();
$footerTable = $footer6->addTable([
    'borderSize' => 0,
    'width' => 100 * 50,
    'unit' => 'pct',
]);

$footerTable->addRow(300);
$footerTable->addCell(2500)->addText('© ACME Corp', ['size' => 8]);
$footerTable->addCell(3000)->addText('Confidential', [
    'size' => 8,
    'alignment' => 'center',
    'color' => 'FF0000',
]);
$footerTable->addCell(2500)->addText('Page 1', ['size' => 8, 'alignment' => 'right']);

$cc6->addContentControl($footerTable, [
    'alias' => 'Footer Information Table',
    'tag' => 'footer-table',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

$section6->addText('Document with mixed content types in headers/footers');

$output6 = __DIR__ . '/output/example_6_mixed_content.docx';
$cc6->save($output6);

echo "✓ Generated: example_6_mixed_content.docx\n";
echo "  - Header: Text + TextRun with formatting\n";
echo "  - Footer: Table with copyright, confidential notice, page number\n\n";

// ============================================================================
// Summary
// ============================================================================

echo str_repeat('=', 60) . "\n";
echo "✓ All examples generated successfully!\n\n";
echo "Output directory: " . __DIR__ . "/output/\n\n";
echo "Generated files:\n";
echo "  1. example_1_basic_header_footer.docx\n";
echo "  2. example_2_table_header.docx\n";
echo "  3. example_3_first_page_header.docx\n";
echo "  4. example_4_even_page_footer.docx\n";
echo "  5. example_5_multiple_sections.docx\n";
echo "  6. example_6_mixed_content.docx\n\n";
echo "Open these files in Microsoft Word to see Content Controls in action!\n";

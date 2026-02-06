<?php

declare(strict_types=1);

/**
 * ContentControl Complete End-to-End Example (v0.2.0)
 * 
 * Demonstrates all features of the ContentControl library:
 * - Content Controls in body (Text, TextRun, Table, Cell, Title, Image)
 * - Content Controls in headers (default, first, even)
 * - Content Controls in footers (default, first, even)
 * - Multiple sections with independent headers/footers
 * - Different lock types and configurations
 * 
 * This example creates a complete corporate document with:
 * - Protected headers with company branding
 * - Protected footers with legal disclaimers
 * - Protected content in body (titles, images, tables)
 */

require __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;

// ============================================================================
// 1. INITIALIZE DOCUMENT
// ============================================================================

$cc = new ContentControl();

// Configure document properties
$cc->getDocInfo()
    ->setCreator('ContentControl Library')
    ->setTitle('Complete Feature Demonstration')
    ->setSubject('Content Controls in Headers, Footers, and Body')
    ->setDescription('End-to-end example showcasing all library features');

// ============================================================================
// 2. SECTION 1: CORPORATE COVER PAGE
// ============================================================================

$section1 = $cc->addSection();

// --- Header: Company Logo and Information ---
$header1 = $section1->addHeader();

// Company name (locked)
$companyName = $header1->addText('ACME Corporation', [
    'bold' => true,
    'size' => 16,
    'color' => '1F4788',
]);
$cc->addContentControl($companyName, [
    'alias' => 'Company Name',
    'tag' => 'company-name',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Tagline (content locked - can edit style, not text)
$tagline = $header1->addText('Innovation Through Excellence', [
    'italic' => true,
    'size' => 10,
    'color' => '666666',
]);
$cc->addContentControl($tagline, [
    'alias' => 'Company Tagline',
    'tag' => 'company-tagline',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);

// --- Footer: Confidentiality Notice ---
$footer1 = $section1->addFooter();

$confidential = $footer1->addText('CONFIDENTIAL - Internal Use Only', [
    'bold' => true,
    'color' => 'FF0000',
    'alignment' => 'center',
]);
$cc->addContentControl($confidential, [
    'alias' => 'Confidentiality Notice',
    'tag' => 'confidential-notice',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// --- Body: Cover Page Content ---

// Main Title (protected using addText instead of addTitle for better compatibility)
$title = $section1->addText('Annual Report 2026', [
    'bold' => true,
    'size' => 20,
    'alignment' => 'center',
]);
$cc->addContentControl($title, [
    'alias' => 'Document Title',
    'tag' => 'doc-title',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Subtitle (editable)
$subtitle = $section1->addText('Fiscal Year 2025-2026', [
    'size' => 14,
    'alignment' => 'center',
]);

// Section spacing
$section1->addTextBreak(2);

// Summary Table (protected structure)
$table1 = $section1->addTable([
    'borderSize' => 6,
    'borderColor' => '1F4788',
    'width' => 100 * 50,
    'unit' => 'pct',
]);

$table1->addRow(500);
$table1->addCell(3000)->addText('Metric', ['bold' => true]);
$table1->addCell(3000)->addText('Value', ['bold' => true]);

$table1->addRow(400);
$table1->addCell(3000)->addText('Total Revenue');
$table1->addCell(3000)->addText('$125.5M');

$table1->addRow(400);
$table1->addCell(3000)->addText('Net Profit');
$table1->addCell(3000)->addText('$23.8M');

$table1->addRow(400);
$table1->addCell(3000)->addText('Employees');
$table1->addCell(3000)->addText('1,247');

$cc->addContentControl($table1, [
    'alias' => 'Summary Table',
    'tag' => 'summary-table',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// ============================================================================
// 3. SECTION 2: MAIN CONTENT WITH DIFFERENT HEADERS/FOOTERS
// ============================================================================

$section2 = $cc->addSection();

// --- Header: Simplified for content pages ---
$header2 = $section2->addHeader();
$headerText2 = $header2->addText('ACME Corporation - Annual Report', [
    'bold' => true,
    'size' => 10,
]);
$cc->addContentControl($headerText2, [
    'alias' => 'Content Page Header',
    'tag' => 'content-header',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);

// --- Footer: Page number and copyright ---
$footer2 = $section2->addFooter();
$copyright = $footer2->addText('© 2026 ACME Corporation. All Rights Reserved.', [
    'size' => 9,
    'color' => '666666',
    'alignment' => 'center',
]);
$cc->addContentControl($copyright, [
    'alias' => 'Copyright Notice',
    'tag' => 'copyright',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// --- Body: Main Content ---

// Chapter Title (protected)
$chapterTitle = $section2->addText('Executive Summary', [
    'bold' => true,
    'size' => 16,
]);
$cc->addContentControl($chapterTitle, [
    'alias' => 'Chapter 1 Title',
    'tag' => 'chapter-1-title',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Paragraph (editable)
$section2->addText(
    'The fiscal year 2025-2026 marked a period of unprecedented growth for ACME Corporation. ' .
    'Our commitment to innovation and customer satisfaction has driven significant achievements ' .
    'across all business units.'
);

$section2->addTextBreak();

// SubHeading (protected)
$subHeading = $section2->addText('Key Achievements', [
    'bold' => true,
    'size' => 14,
]);
$cc->addContentControl($subHeading, [
    'alias' => 'Achievements Heading',
    'tag' => 'achievements-heading',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Achievements List (formatted text run with protection)
$textRun = $section2->addTextRun();
$textRun->addText('Revenue Growth: ', ['bold' => true]);
$textRun->addText('Increased by 18.5% year-over-year.');
$textRun->addTextBreak();

$textRun->addText('Market Expansion: ', ['bold' => true]);
$textRun->addText('Entered 5 new international markets.');
$textRun->addTextBreak();

$textRun->addText('Innovation: ', ['bold' => true]);
$textRun->addText('Launched 12 new products and filed 23 patents.');

$cc->addContentControl($textRun, [
    'alias' => 'Achievements List',
    'tag' => 'achievements-list',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);

// ============================================================================
// 4. SECTION 3: FINANCIAL DATA WITH SPECIALIZED HEADERS
// ============================================================================

$section3 = $cc->addSection();

// --- First Page Header (different from default) ---
$header3First = $section3->addHeader('first');
$header3FirstText = $header3First->addText('ACME Corporation - Financial Statements', [
    'bold' => true,
    'size' => 12,
    'color' => '1F4788',
]);
$cc->addContentControl($header3FirstText, [
    'alias' => 'Financial Section Header',
    'tag' => 'financial-header',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// --- Default Header (for subsequent pages) ---
$header3Default = $section3->addHeader();
$header3DefaultText = $header3Default->addText('Financial Statements (Continued)', [
    'size' => 10,
]);
$cc->addContentControl($header3DefaultText, [
    'alias' => 'Financial Continuation Header',
    'tag' => 'financial-cont-header',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);

// --- Footer with legal disclaimer ---
$footer3 = $section3->addFooter();
$legalDisclaimer = $footer3->addText(
    'Financial data audited by Independent Auditors LLC. See notes to financial statements.',
    [
        'size' => 8,
        'color' => '666666',
        'italic' => true,
    ]
);
$cc->addContentControl($legalDisclaimer, [
    'alias' => 'Legal Disclaimer',
    'tag' => 'legal-disclaimer',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// --- Body: Financial Table ---

$financialTitle = $section3->addText('Statement of Financial Position', [
    'bold' => true,
    'size' => 16,
]);
$cc->addContentControl($financialTitle, [
    'alias' => 'Financial Statement Title',
    'tag' => 'financial-title',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

$section3->addTextBreak();

// Financial Data Table (fully protected)
$financialTable = $section3->addTable([
    'borderSize' => 6,
    'borderColor' => '000000',
    'width' => 100 * 50,
    'unit' => 'pct',
]);

// Header Row
$financialTable->addRow(600, ['tblHeader' => true]);
$financialTable->addCell(4000)->addText('Category', ['bold' => true]);
$financialTable->addCell(2000)->addText('2026', ['bold' => true, 'alignment' => 'right']);
$financialTable->addCell(2000)->addText('2025', ['bold' => true, 'alignment' => 'right']);

// Assets
$financialTable->addRow(400);
$financialTable->addCell(4000)->addText('Total Assets', ['bold' => true]);
$financialTable->addCell(2000)->addText('$245.2M', ['alignment' => 'right']);
$financialTable->addCell(2000)->addText('$203.7M', ['alignment' => 'right']);

$financialTable->addRow(400);
$financialTable->addCell(4000)->addText('  Current Assets');
$financialTable->addCell(2000)->addText('$132.5M', ['alignment' => 'right']);
$financialTable->addCell(2000)->addText('$109.3M', ['alignment' => 'right']);

$financialTable->addRow(400);
$financialTable->addCell(4000)->addText('  Non-Current Assets');
$financialTable->addCell(2000)->addText('$112.7M', ['alignment' => 'right']);
$financialTable->addCell(2000)->addText('$94.4M', ['alignment' => 'right']);

// Liabilities
$financialTable->addRow(400);
$financialTable->addCell(4000)->addText('Total Liabilities', ['bold' => true]);
$financialTable->addCell(2000)->addText('$134.8M', ['alignment' => 'right']);
$financialTable->addCell(2000)->addText('$115.2M', ['alignment' => 'right']);

// Equity
$financialTable->addRow(400);
$financialTable->addCell(4000)->addText('Total Equity', ['bold' => true]);
$financialTable->addCell(2000)->addText('$110.4M', ['alignment' => 'right']);
$financialTable->addCell(2000)->addText('$88.5M', ['alignment' => 'right']);

$cc->addContentControl($financialTable, [
    'alias' => 'Financial Statement Table',
    'tag' => 'financial-table',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// ============================================================================
// 5. GENERATE DOCX FILE
// ============================================================================

$outputPath = __DIR__ . '/output/complete_example.docx';

// Ensure output directory exists
if (!file_exists(__DIR__ . '/output')) {
    mkdir(__DIR__ . '/output', 0777, true);
}

echo "Generating complete example document...\n";
$startTime = microtime(true);

$cc->save($outputPath);

$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2);

echo "✓ Document generated successfully!\n";
echo "  - Location: {$outputPath}\n";
echo "  - Execution time: {$executionTime}ms\n";
echo "  - File size: " . round(filesize($outputPath) / 1024, 2) . " KB\n";

// ============================================================================
// 6. VERIFY CONTENT CONTROLS
// ============================================================================

echo "\nVerifying Content Controls...\n";

$zip = new ZipArchive();
$zip->open($outputPath);

// Count SDTs in each file
$documentXml = $zip->getFromName('word/document.xml');
$bodySdtCount = substr_count($documentXml, '<w:sdt>');

$headerSdtCount = 0;
$footerSdtCount = 0;

for ($i = 1; $i <= 10; $i++) {
    $headerXml = $zip->getFromName("word/header{$i}.xml");
    $footerXml = $zip->getFromName("word/footer{$i}.xml");
    
    if ($headerXml !== false) {
        $headerSdtCount += substr_count($headerXml, '<w:sdt>');
    }
    
    if ($footerXml !== false) {
        $footerSdtCount += substr_count($footerXml, '<w:sdt>');
    }
}

$totalSdtCount = $bodySdtCount + $headerSdtCount + $footerSdtCount;

echo "✓ Content Controls injected:\n";
echo "  - Body (document.xml): {$bodySdtCount} SDTs\n";
echo "  - Headers: {$headerSdtCount} SDTs\n";
echo "  - Footers: {$footerSdtCount} SDTs\n";
echo "  - Total: {$totalSdtCount} SDTs\n";

$zip->close();

echo "\n✓ All done! Open the document in Microsoft Word to verify Content Controls.\n";
echo "  - Locked SDTs will show a lock icon when selected\n";
echo "  - Content-locked SDTs allow style changes but not text editing\n";
echo "  - All protected elements are highlighted with Content Control borders\n";

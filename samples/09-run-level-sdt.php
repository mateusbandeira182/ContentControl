<?php

declare(strict_types=1);

/**
 * Run-Level SDT Example - Wrapping Individual Text Runs
 *
 * Difficulty: Intermediate
 * Features: Run-level SDTs (CT_SdtContentRun), TextRun with tagged runs
 * Description: Demonstrates wrapping individual <w:r> elements with SDTs
 *              inside paragraphs, enabling fine-grained content protection.
 *
 * @package ContentControl
 * @version 0.6.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Bridge\TableBuilder;

echo "Creating Run-Level SDT document...\n";

$contentControl = new ContentControl();
$section = $contentControl->addSection();

// ============================================================================
// 1. Simple run-level SDT on standalone text
// ============================================================================
$section->addTitle('Run-Level SDT Examples', 1);

$standaloneText = $section->addText('This text has a run-level SDT wrapping the entire run.');
$contentControl->addContentControl($standaloneText, [
    'alias' => 'Standalone Run',
    'tag' => 'standalone-run',
    'runLevel' => true,
]);

// ============================================================================
// 2. TextRun with multiple tagged runs (fine-grained wrapping)
// ============================================================================
$section->addText(''); // Spacer

$textRun = $section->addTextRun();
$firstName = $textRun->addText('Alice', ['bold' => true]);
$textRun->addText(' ');
$lastName = $textRun->addText('Johnson', ['bold' => true, 'color' => '0000FF']);
$textRun->addText(' - Employee Record');

$contentControl->addContentControl($firstName, [
    'alias' => 'First Name',
    'tag' => 'first-name',
    'runLevel' => true,
]);

$contentControl->addContentControl($lastName, [
    'alias' => 'Last Name',
    'tag' => 'last-name',
    'runLevel' => true,
]);

// ============================================================================
// 3. Table with run-level SDTs in cells (v2 API)
// ============================================================================
$section->addText(''); // Spacer

$table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999']);

// Header row
$headerRow = $table->addRow(null, ['tblHeader' => true]);
$headerRow->addCell(3000)->addText('Field', ['bold' => true]);
$headerRow->addCell(5000)->addText('Value', ['bold' => true]);

// Data rows with run-level SDTs
$row1 = $table->addRow();
$row1->addCell(3000)->addText('Name');
$nameCell = $row1->addCell(5000);
$nameText = $nameCell->addText('John Doe');

$row2 = $table->addRow();
$row2->addCell(3000)->addText('Email');
$emailCell = $row2->addCell(5000);
$emailText = $emailCell->addText('john@example.com');

$row3 = $table->addRow();
$row3->addCell(3000)->addText('Department');
$deptCell = $row3->addCell(5000);
$deptText = $deptCell->addText('Engineering');

// Register run-level SDTs for cell values
$contentControl->addContentControl($nameText, [
    'alias' => 'Employee Name',
    'tag' => 'emp-name',
    'runLevel' => true,
    'inlineLevel' => true,
]);

$contentControl->addContentControl($emailText, [
    'alias' => 'Employee Email',
    'tag' => 'emp-email',
    'runLevel' => true,
    'inlineLevel' => true,
]);

$contentControl->addContentControl($deptText, [
    'alias' => 'Department',
    'tag' => 'emp-dept',
    'runLevel' => true,
    'inlineLevel' => true,
]);

// ============================================================================
// 4. Save document
// ============================================================================
$outputDir = __DIR__ . '/output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$outputFile = $outputDir . '/09-run-level-sdt.docx';
$contentControl->save($outputFile);

echo "Document saved to: {$outputFile}\n";
echo "Open in Word and check Developer tab to see Content Controls.\n";
echo "Run-level SDTs wrap individual text runs (<w:r>) instead of paragraphs.\n";

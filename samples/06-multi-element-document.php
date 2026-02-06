<?php

declare(strict_types=1);

/**
 * Multi-Element Document Example - Comprehensive Feature Demo
 *
 * Difficulty: Advanced
 * Features: Multiple element types, all lock types, comprehensive SDT usage
 * Description: Demonstrates Text, Title, Image, Table with various protection levels
 *
 * @package ContentControl
 * @version 0.5.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\Bridge\TableBuilder;

echo "Creating Multi-Element document...\n";

$contentControl = new ContentControl();

// 1. Title Element with SDT
$section = $contentControl->addSection();
$title = $section->addTitle('ContentControl Feature Showcase', 1);
$contentControl->addContentControl($title, [
    'alias' => 'Document Title',
    'tag' => 'doc-title',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// 2. Plain Text with LOCK_NONE (fully editable)
$text1 = $section->addText('This content is fully editable - both text and control can be modified.');
$contentControl->addContentControl($text1, [
    'alias' => 'Editable Section',
    'tag' => 'editable-text',
    'lockType' => ContentControl::LOCK_NONE,
]);

// 3. Rich Text with LOCK_CONTENT_LOCKED (control locked, content editable)
$textRun = $section->addTextRun();
$textRun->addText('This text can be ');
$textRun->addText('edited', ['bold' => true, 'color' => 'FF0000']);
$textRun->addText(' but the control cannot be deleted.');

$contentControl->addContentControl($textRun, [
    'alias' => 'Protected Control',
    'tag' => 'locked-control',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
    'type' => ContentControl::TYPE_RICH_TEXT,
]);

// 4. Image Element (if test image exists)
$imagePath = __DIR__ . '/../tests/Fixtures/test_image.png';
if (file_exists($imagePath)) {
    $image = $section->addImage($imagePath, [
        'width' => 200,
        'height' => 150,
    ]);
    
    $contentControl->addContentControl($image, [
        'alias' => 'Company Logo',
        'tag' => 'logo-image',
        'type' => ContentControl::TYPE_PICTURE,
        'lockType' => ContentControl::LOCK_SDT_LOCKED,
    ]);
    echo "  ✓ Image element added\n";
} else {
    echo "  ⚠ Skipping image (test_image.png not found)\n";
}

// 5. Table with Multiple Protection Levels
$section->addText(''); // Spacing
$section->addTitle('Product Inventory', 2);

$builder = new TableBuilder($contentControl);
$builder->setStyles([
    'borderSize' => 6,
    'borderColor' => '1976D2',  // Blue
    'cellMargin' => 80,
]);

$builder
    ->addRow()
        ->addCell(3000)->addText('Category', ['bold' => true])->end()
        ->addCell(2000)->addText('Items', ['bold' => true])->end()
        ->addCell(2000)->addText('Status', ['bold' => true])->end()
        ->end()
    ->addRow()
        ->addCell(3000)->addText('Electronics')->end()
        ->addCell(2000)->addText('245')->end()
        ->addCell(2000)->addText('In Stock')->end()
        ->end()
    ->addRow()
        ->addCell(3000)->addText('Furniture')->end()
        ->addCell(2000)->addText('87')->end()
        ->addCell(2000)->addText('Low Stock')->end()
        ->end();

// Table is automatically added to document
// No manual injection needed

// Save document
$outputFile = __DIR__ . '/output/06-multi-element-document.docx';
$contentControl->save($outputFile);

echo "✓ Comprehensive document created: $outputFile\n";
echo "\nElement Summary:\n";
echo "  - Title (Heading 1) with SDT\n";
echo "  - Plain Text (LOCK_NONE)\n";
echo "  - Rich Text (LOCK_CONTENT_LOCKED)\n";
echo "  - Image (TYPE_PICTURE, if available)\n";
echo "  - Table (LOCK_SDT_LOCKED)\n";
echo "\nOpen in Word Developer tab to inspect all Content Controls.\n";

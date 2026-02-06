<?php

/**
 * ContentControl v0.1.0 - Title and Image Examples
 * 
 * Demonstrates how to use Title and Image elements with Content Controls.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;

// Create output directory
$outputDir = __DIR__ . '/output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// Create test image for examples
$testImagePath = $outputDir . '/example_image.png';
if (!file_exists($testImagePath)) {
    $image = imagecreatetruecolor(200, 150);
    $blue = imagecolorallocate($image, 0, 102, 204);
    imagefilledrectangle($image, 0, 0, 200, 150, $blue);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagestring($image, 5, 50, 65, 'Sample Image', $white);
    imagepng($image, $testImagePath);
    imagedestroy($image);
}

echo "=== ContentControl v0.1.0 - Title and Image Examples ===\n\n";

// ========================================
// Example 1: Working with Hierarchical Titles
// ========================================
echo "1. Creating document with hierarchical titles...\n";

$cc1 = new ContentControl();

// Add title styles (required before adding titles)
$cc1->addTitleStyle(0, ['size' => 20, 'bold' => true, 'color' => '1F4E78']); // Title
$cc1->addTitleStyle(1, ['size' => 18, 'bold' => true, 'color' => '2E75B5']); // Heading 1
$cc1->addTitleStyle(2, ['size' => 16, 'bold' => true, 'color' => '5B9BD5']); // Heading 2
$cc1->addTitleStyle(3, ['size' => 14, 'bold' => true, 'color' => '70AD47']); // Heading 3

$section = $cc1->addSection();

// Add hierarchical structure
$docTitle = $section->addTitle('Annual Report 2025', 0); // depth 0 = Title style
$section->addText('This document contains the annual report for fiscal year 2025.');
$section->addTextBreak();

$chapter1 = $section->addTitle('Chapter 1: Introduction', 1); // depth 1 = Heading1
$section->addText('This chapter provides an overview of the year\'s achievements.');
$section->addTextBreak();

$section11 = $section->addTitle('1.1 Executive Summary', 2); // depth 2 = Heading2
$section->addText('Key highlights and performance metrics for the year.');
$section->addTextBreak();

$section12 = $section->addTitle('1.2 Market Overview', 2);
$section->addText('Analysis of market conditions and competitive landscape.');
$section->addTextBreak();

$section121 = $section->addTitle('1.2.1 Regional Performance', 3); // depth 3 = Heading3
$section->addText('Performance breakdown by geographical region.');

// Wrap titles with Content Controls
// This allows protecting/locking titles from editing while allowing content modification
$cc1->addContentControl($docTitle, [
    'alias' => 'Document Title',
    'tag' => 'doc-title',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_SDT_LOCKED // Lock the control itself
]);

$cc1->addContentControl($chapter1, [
    'alias' => 'Chapter 1 Title',
    'tag' => 'chapter-1-title',
    'type' => ContentControl::TYPE_RICH_TEXT
]);

$cc1->addContentControl($section11, [
    'alias' => 'Section 1.1 Title',
    'tag' => 'section-1-1-title'
]);

$cc1->addContentControl($section12, [
    'alias' => 'Section 1.2 Title',
    'tag' => 'section-1-2-title'
]);

$file1 = $outputDir . '/example1_hierarchical_titles.docx';
$cc1->save($file1);
echo "   ✓ Saved: $file1\n";
echo "   Note: Bookmarks are preserved - Table of Contents will still work!\n\n";

// ========================================
// Example 2: Working with Images
// ========================================
echo "2. Creating document with images...\n";

$cc2 = new ContentControl();
$section2 = $cc2->addSection();

$section2->addText('Product Catalog', ['size' => 16, 'bold' => true]);
$section2->addTextBreak();

// Add inline image (centered)
$section2->addText('Product 1: Premium Widget', ['bold' => true]);
$image1 = $section2->addImage($testImagePath, [
    'width' => 200,
    'height' => 150,
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
]);
$section2->addText('High-quality widget with advanced features.');
$section2->addTextBreak(2);

// Add another image (different size)
$section2->addText('Product 2: Standard Widget', ['bold' => true]);
$image2 = $section2->addImage($testImagePath, [
    'width' => 150,
    'height' => 112 // Maintain aspect ratio
]);
$section2->addText('Cost-effective solution for basic needs.');

// Wrap images with Content Controls (TYPE_PICTURE)
$cc2->addContentControl($image1, [
    'alias' => 'Product 1 Image',
    'tag' => 'product-1-image',
    'type' => ContentControl::TYPE_PICTURE, // Use PICTURE type for images
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED // Lock content but allow SDT deletion
]);

$cc2->addContentControl($image2, [
    'alias' => 'Product 2 Image',
    'tag' => 'product-2-image',
    'type' => ContentControl::TYPE_PICTURE
]);

$file2 = $outputDir . '/example2_images.docx';
$cc2->save($file2);
echo "   ✓ Saved: $file2\n";
echo "   Note: RelationIds (rId) are preserved for image references.\n\n";

// ========================================
// Example 3: Mixed Document (Titles + Images + Text)
// ========================================
echo "3. Creating mixed document with titles, images, and text...\n";

$cc3 = new ContentControl();

$cc3->addTitleStyle(1, ['size' => 16, 'bold' => true]);
$cc3->addTitleStyle(2, ['size' => 14, 'bold' => true]);

$section3 = $cc3->addSection();

// Chapter 1 with image
$ch1Title = $section3->addTitle('Chapter 1: Overview', 1);
$ch1Text = $section3->addText('This chapter provides a comprehensive overview of the subject matter.');
$ch1Image = $section3->addImage($testImagePath, ['width' => 180, 'height' => 135]);
$section3->addTextBreak();

// Chapter 2 with subsection
$ch2Title = $section3->addTitle('Chapter 2: Details', 1);
$ch2Text = $section3->addText('Detailed analysis and findings.');

$sec21Title = $section3->addTitle('2.1 Methodology', 2);
$sec21Text = $section3->addText('Our research methodology employed rigorous standards.');
$sec21Image = $section3->addImage($testImagePath, ['width' => 160, 'height' => 120]);

// Wrap all elements
$cc3->addContentControl($ch1Title, ['alias' => 'Chapter 1 Title', 'tag' => 'ch1-title']);
$cc3->addContentControl($ch1Text, ['alias' => 'Chapter 1 Text', 'tag' => 'ch1-text']);
$cc3->addContentControl($ch1Image, [
    'alias' => 'Chapter 1 Image',
    'tag' => 'ch1-image',
    'type' => ContentControl::TYPE_PICTURE
]);

$cc3->addContentControl($ch2Title, ['alias' => 'Chapter 2 Title', 'tag' => 'ch2-title']);
$cc3->addContentControl($ch2Text, ['alias' => 'Chapter 2 Text', 'tag' => 'ch2-text']);

$cc3->addContentControl($sec21Title, ['alias' => 'Section 2.1 Title', 'tag' => 'sec21-title']);
$cc3->addContentControl($sec21Text, ['alias' => 'Section 2.1 Text', 'tag' => 'sec21-text']);
$cc3->addContentControl($sec21Image, [
    'alias' => 'Section 2.1 Image',
    'tag' => 'sec21-image',
    'type' => ContentControl::TYPE_PICTURE
]);

$file3 = $outputDir . '/example3_mixed_document.docx';
$cc3->save($file3);
echo "   ✓ Saved: $file3\n";
echo "   Note: Mixed elements (8 Content Controls) processed without duplication.\n\n";

// ========================================
// Example 4: Table of Contents Compatibility
// ========================================
echo "4. Creating document with TOC (demonstrates bookmark preservation)...\n";

$cc4 = new ContentControl();

$cc4->addTitleStyle(1, ['size' => 16, 'bold' => true]);
$cc4->addTitleStyle(2, ['size' => 14, 'bold' => true]);
$cc4->addTitleStyle(3, ['size' => 12, 'bold' => true]);

$section4 = $cc4->addSection();

// Add Table of Contents first
$section4->addText('Table of Contents', ['size' => 18, 'bold' => true]);
$section4->addTOC(['size' => 11], ['tabLeader' => \PhpOffice\PhpWord\Style\TOC::TAB_LEADER_DOT]);
$section4->addPageBreak();

// Add content with titles
$t1 = $section4->addTitle('Introduction', 1);
$section4->addText('Introduction content goes here...');
$section4->addPageBreak();

$t2 = $section4->addTitle('Background', 1);
$section4->addText('Background information...');

$t21 = $section4->addTitle('Historical Context', 2);
$section4->addText('Historical context details...');

$t22 = $section4->addTitle('Current State', 2);
$section4->addText('Current state analysis...');

// Wrap titles - bookmarks will be preserved!
$cc4->addContentControl($t1, ['alias' => 'Introduction Title', 'tag' => 'intro']);
$cc4->addContentControl($t2, ['alias' => 'Background Title', 'tag' => 'background']);
$cc4->addContentControl($t21, ['alias' => 'Historical Context Title', 'tag' => 'hist-ctx']);
$cc4->addContentControl($t22, ['alias' => 'Current State Title', 'tag' => 'curr-state']);

$file4 = $outputDir . '/example4_toc_compatibility.docx';
$cc4->save($file4);
echo "   ✓ Saved: $file4\n";
echo "   Note: Open in Word and right-click TOC > Update Field to verify links work!\n\n";

// ========================================
// Summary
// ========================================
echo "=== Examples Complete ===\n";
echo "Generated files:\n";
echo "  1. $file1\n";
echo "  2. $file2\n";
echo "  3. $file3\n";
echo "  4. $file4\n\n";

echo "Key features demonstrated:\n";
echo "  ✓ Hierarchical titles (depth 0-3)\n";
echo "  ✓ Bookmark preservation for TOC compatibility\n";
echo "  ✓ Image wrapping with TYPE_PICTURE\n";
echo "  ✓ Mixed documents (Titles + Images + Text)\n";
echo "  ✓ Lock types (LOCK_SDT_LOCKED, LOCK_CONTENT_LOCKED)\n";
echo "  ✓ No duplication (v3.0 DOM inline wrapping)\n";

<?php

declare(strict_types=1);

/**
 * Header & Footer Content Controls Example
 *
 * Difficulty: Advanced
 * Features: Header/footer SDTs (v0.2.0), document sections
 * Description: Adds Content Controls to headers and footers
 *
 * @package ContentControl
 * @version 0.5.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;

echo "Creating Header & Footer document...\n";

$contentControl = new ContentControl();

// Add section with custom header/footer
$section = $contentControl->addSection();

// Header Content
$header = $section->addHeader();
$headerText = $header->addText('Company Confidential', [
    'bold' => true,
    'size' => 10,
    'color' => '666666',
]);

// Wrap header text with SDT
$contentControl->addContentControl($headerText, [
    'alias' => 'Header Classification',
    'tag' => 'header-classification',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);

// Footer Content
$footer = $section->addFooter();
$footerText = $footer->addText('Page ', ['size' => 9]);
$footer->addPreserveText('{PAGE}');
$footerText2 = $footer->addText(' of ', ['size' => 9]);
$footer->addPreserveText('{NUMPAGES}');

// Wrap footer with SDT
$contentControl->addContentControl($footerText, [
    'alias' => 'Footer Page Number',
    'tag' => 'footer-pagenumber',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Body Content
$section->addTitle('Document with Protected Headers & Footers', 1);
$section->addText('This document demonstrates Content Controls in headers and footers.');
$section->addText('The header shows "Company Confidential" text within a locked SDT.');
$section->addText('The footer contains page numbering with SDT protection.');

$section->addPageBreak();
$section->addText('Second page content - notice header and footer persist with SDT protection.');

// Save document
$outputFile = __DIR__ . '/output/07-header-footer-controls.docx';
$contentControl->save($outputFile);

echo "✓ Document created successfully: $outputFile\n";
echo "\nFeatures demonstrated:\n";
echo "  - Header with protected text (LOCK_CONTENT_LOCKED)\n";
echo "  - Footer with page numbers and SDT protection\n";
echo "  - Multi-page document with persistent headers/footers\n";
echo "\nNote: Header/footer SDT support added in v0.2.0\n";
echo "See docs/CHANGELOG-v0.2.0.md for implementation details.\n";

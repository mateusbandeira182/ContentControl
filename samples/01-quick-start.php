<?php

declare(strict_types=1);

/**
 * Quick Start Example - Basic ContentControl Usage
 *
 * Difficulty: Basic
 * Features: Document creation, text elements, basic Content Controls
 * Description: Minimal example showing how to create a document with protected text
 *
 * @package ContentControl
 * @version 0.5.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;

echo "Creating Quick Start document...\n";

// Create new document
$contentControl = new ContentControl();

// Add section (required for content)
$section = $contentControl->addSection();

// Add a simple text element
$text = $section->addText('Welcome to ContentControl!');

// Wrap text with Content Control (makes it protected)
$contentControl->addContentControl($text, [
    'alias' => 'Welcome Message',
    'tag' => 'intro-text',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Add another text with different protection
$text2 = $section->addText(
    'This content can be edited, but the control cannot be deleted.',
    ['bold' => true]
);

$contentControl->addContentControl($text2, [
    'alias' => 'Editable Content',
    'tag' => 'editable-text',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);

// Save document
$outputFile = __DIR__ . '/output/01-quick-start.docx';
$contentControl->save($outputFile);

echo "✓ Document created successfully: $outputFile\n";
echo "  Open in Microsoft Word to see Content Controls in Developer tab.\n";

<?php

declare(strict_types=1);

/**
 * Template Modification Example - ContentProcessor Usage
 *
 * Difficulty: Intermediate
 * Features: ContentProcessor, template modification, SDT replacement
 * Description: Opens existing DOCX and modifies Content Controls
 *
 * @package ContentControl  
 * @version 0.5.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentProcessor;

echo "Template Modification Example\n";

// Check if template exists (users need to create their own template)
$templatePath = __DIR__ . '/fixtures/invoice-template.docx';

if (!file_exists($templatePath)) {
    echo "⚠ Template not found: $templatePath\n";
    echo "\nTo run this example:\n";
    echo "1. Create a Word document with Content Controls\n";
    echo "2. Add SDTs with tags: 'invoice-number', 'customer-name', 'invoice-date'\n";
    echo "3. Save as: samples/fixtures/invoice-template.docx\n";
    echo "4. Run this script again\n\n";
    echo "See docs/ContentProcessor.md for detailed instructions.\n";
    exit(1);
}

// Open existing document
$processor = new ContentProcessor($templatePath);

// Replace content in existing SDTs
$processor->replaceContent('invoice-number', 'INV-2026-001');
$processor->setValue('customer-name', 'Acme Corporation');
$processor->setValue('invoice-date', date('Y-m-d'));

// Save modified document
$outputFile = __DIR__ . '/output/03-invoice-filled.docx';
$processor->save($outputFile);

echo "✓ Template modified successfully: $outputFile\n";
echo "  Original template preserved at: $templatePath\n";

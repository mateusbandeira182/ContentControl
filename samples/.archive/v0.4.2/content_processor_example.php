<?php

/**
 * ContentProcessor Example - Basic Usage
 *
 * Demonstrates how to use ContentProcessor to manipulate Content Controls
 * in existing DOCX documents.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentProcessor;
use PhpOffice\PhpWord\PhpWord;

// Create a sample template with SDTs
echo "Creating sample template...\n";
$templatePath = __DIR__ . '/output/template.docx';
createSampleTemplate($templatePath);

// Process the template
echo "Processing template...\n";
$processor = new ContentProcessor($templatePath);

// Replace simple text
$processor->replaceContent('customer-name', 'Acme Corporation LTDA');
$processor->replaceContent('invoice-number', 'INV-2026-001');
$processor->replaceContent('invoice-date', '30/01/2026');

// Replace with PHPWord element (table)
$phpWord = new PhpWord();
$section = $phpWord->addSection();
$table = $section->addTable();
$table->addRow();
$table->addCell(4000)->addText('Item');
$table->addCell(2000)->addText('Quantity');
$table->addCell(2000)->addText('Price');
$table->addRow();
$table->addCell(4000)->addText('Premium Laptop');
$table->addCell(2000)->addText('2');
$table->addCell(2000)->addText('$1,200.00');
$table->addRow();
$table->addCell(4000)->addText('Wireless Mouse');
$table->addCell(2000)->addText('5');
$table->addCell(2000)->addText('$25.00');

$processor->replaceContent('invoice-items', $table);

// Save to new file
$outputPath = __DIR__ . '/output/processed-invoice.docx';
$processor->save($outputPath);

echo "✓ Template processed successfully!\n";
echo "Output saved to: {$outputPath}\n";

/**
 * Create sample template DOCX with Content Controls
 *
 * @param string $path Output file path
 * @return void
 */
function createSampleTemplate(string $path): void
{
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:r>
                <w:t xml:space="preserve">INVOICE</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:r>
                <w:t xml:space="preserve">Customer: </w:t>
            </w:r>
        </w:p>
        <w:sdt>
            <w:sdtPr>
                <w:id w:val="10000001"/>
                <w:tag w:val="customer-name"/>
                <w:alias w:val="Customer Name"/>
            </w:sdtPr>
            <w:sdtContent>
                <w:p>
                    <w:r>
                        <w:t>[Customer Name]</w:t>
                    </w:r>
                </w:p>
            </w:sdtContent>
        </w:sdt>
        
        <w:p>
            <w:r>
                <w:t xml:space="preserve">Invoice #: </w:t>
            </w:r>
        </w:p>
        <w:sdt>
            <w:sdtPr>
                <w:id w:val="10000002"/>
                <w:tag w:val="invoice-number"/>
                <w:alias w:val="Invoice Number"/>
            </w:sdtPr>
            <w:sdtContent>
                <w:p>
                    <w:r>
                        <w:t>[Invoice Number]</w:t>
                    </w:r>
                </w:p>
            </w:sdtContent>
        </w:sdt>
        
        <w:p>
            <w:r>
                <w:t xml:space="preserve">Date: </w:t>
            </w:r>
        </w:p>
        <w:sdt>
            <w:sdtPr>
                <w:id w:val="10000003"/>
                <w:tag w:val="invoice-date"/>
                <w:alias w:val="Invoice Date"/>
            </w:sdtPr>
            <w:sdtContent>
                <w:p>
                    <w:r>
                        <w:t>[Invoice Date]</w:t>
                    </w:r>
                </w:p>
            </w:sdtContent>
        </w:sdt>
        
        <w:p>
            <w:r>
                <w:t xml:space="preserve">Items:</w:t>
            </w:r>
        </w:p>
        <w:sdt>
            <w:sdtPr>
                <w:id w:val="10000004"/>
                <w:tag w:val="invoice-items"/>
                <w:alias w:val="Invoice Items"/>
            </w:sdtPr>
            <w:sdtContent>
                <w:p>
                    <w:r>
                        <w:t>[Invoice Items Table]</w:t>
                    </w:r>
                </w:p>
            </w:sdtContent>
        </w:sdt>
    </w:body>
</w:document>
XML;

    $contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

    $rels = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

    // Ensure output directory exists
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', $xml);
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->close();
}

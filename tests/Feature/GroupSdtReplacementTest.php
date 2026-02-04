<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\ContentProcessor;
use ZipArchive;

/**
 * Integration tests for GROUP SDT replacement functionality
 *
 * Tests the replaceGroupContent() method which allows replacing GROUP-type
 * Content Controls with complex structures containing nested SDTs.
 *

 */

/**
 * Test Helper: Create template with GROUP SDT using manual XML
 *
 * @param string $tag Tag value for the GROUP SDT
 * @param string $initialContent Initial content inside the GROUP SDT
 * @return string Path to created DOCX file
 */
function createGroupSdtTemplate(string $tag, string $initialContent = 'Placeholder'): string
{
    $xml = sprintf(
        <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:sdt>
            <w:sdtPr>
                <w:id w:val="12345678"/>
                <w:tag w:val="%s"/>
                <w:group/>
            </w:sdtPr>
            <w:sdtContent>
                <w:p>
                    <w:r>
                        <w:t>%s</w:t>
                    </w:r>
                </w:p>
            </w:sdtContent>
        </w:sdt>
    </w:body>
</w:document>
XML,
        htmlspecialchars($tag, ENT_XML1, 'UTF-8'),
        htmlspecialchars($initialContent, ENT_XML1, 'UTF-8')
    );

    $tempPath = tempnam(sys_get_temp_dir(), 'group_sdt_') . '.docx';
    createDocxFromXml($tempPath, $xml);
    return $tempPath;
}

test('replaceGroupContent() replaces GROUP SDT with simple structure', function () {
    // 1. Create template with GROUP SDT
    $templatePath = createGroupSdtTemplate('invoice-section', 'Section Placeholder');

    // 2. Create replacement structure
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('Invoice Details', ['bold' => true, 'size' => 16]);
    $section->addText('Total: $1,200.00');

    // 3. Replace GROUP SDT
    $processor = new ContentProcessor($templatePath);
    $result = $processor->replaceGroupContent('invoice-section', $cc);

    expect($result)->toBeTrue();

    // 4. Save and verify
    $outputPath = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
    $processor->save($outputPath);

    $zip = new ZipArchive();
    $zip->open($outputPath);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    expect($xml)->toContain('Invoice Details')
        ->and($xml)->toContain('Total: $1,200.00')
        ->and($xml)->not->toContain('Section Placeholder');

    safeUnlink($templatePath);
    safeUnlink($outputPath);
});

test('replaceGroupContent() preserves nested SDTs in replaced content', function () {
    // 1. Create template with GROUP SDT
    $templatePath = createGroupSdtTemplate('form-section');

    // 2. Create complex structure with nested SDTs
    $cc = new ContentControl();
    $section = $cc->addSection();

    // First paragraph with SDT
    $nameText = $section->addText('Customer Name: ');
    $cc->addContentControl($nameText, [
        'tag' => 'customer-name',
        'alias' => 'Customer Name',
        'lockType' => ContentControl::LOCK_SDT_LOCKED
    ]);

    // Second paragraph with SDT
    $emailText = $section->addText('Email: ');
    $cc->addContentControl($emailText, [
        'tag' => 'customer-email',
        'alias' => 'Email Address',
        'lockType' => ContentControl::LOCK_CONTENT_LOCKED
    ]);

    // 3. Replace GROUP SDT
    $processor = new ContentProcessor($templatePath);
    $result = $processor->replaceGroupContent('form-section', $cc);

    expect($result)->toBeTrue();

    // 4. Save and verify nested SDTs are preserved
    $outputPath = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
    $processor->save($outputPath);

    $zip = new ZipArchive();
    $zip->open($outputPath);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    // Verify content is present
    expect($xml)->toContain('Customer Name:')
        ->and($xml)->toContain('Email:');

    // Verify nested SDTs are preserved
    expect($xml)->toContain('<w:tag w:val="customer-name"/>')
        ->and($xml)->toContain('<w:tag w:val="customer-email"/>')
        ->and($xml)->toContain('<w:alias w:val="Customer Name"/>')
        ->and($xml)->toContain('<w:alias w:val="Email Address"/>');

    // Verify lock types
    expect($xml)->toContain('<w:lock w:val="sdtLocked"/>')
        ->and($xml)->toContain('<w:lock w:val="sdtContentLocked"/>');

    safeUnlink($templatePath);
    safeUnlink($outputPath);
});

test('replaceGroupContent() works with table structures and cell SDTs', function () {
    // 1. Create template with GROUP SDT
    $templatePath = createGroupSdtTemplate('invoice-table');

    // 2. Create table with cell SDTs
    $cc = new ContentControl();
    $section = $cc->addSection();

    $table = $section->addTable();

    // Header row
    $headerRow = $table->addRow();
    $headerRow->addCell(3000)->addText('Item', ['bold' => true]);
    $headerRow->addCell(2000)->addText('Price', ['bold' => true]);

    // Data row with SDT in price cell
    $dataRow = $table->addRow();
    $dataRow->addCell(3000)->addText('Premium Laptop');
    $priceCell = $dataRow->addCell(2000);
    $priceText = $priceCell->addText('$1,200.00');
    $cc->addContentControl($priceText, [
        'tag' => 'item-price',
        'alias' => 'Item Price',
        'lockType' => ContentControl::LOCK_SDT_LOCKED
    ]);

    // 3. Replace GROUP SDT
    $processor = new ContentProcessor($templatePath);
    $result = $processor->replaceGroupContent('invoice-table', $cc);

    expect($result)->toBeTrue();

    // 4. Save and verify
    $outputPath = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
    $processor->save($outputPath);

    $zip = new ZipArchive();
    $zip->open($outputPath);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    // Verify table structure
    expect($xml)->toContain('<w:tbl>')
        ->and($xml)->toContain('Item')
        ->and($xml)->toContain('Price')
        ->and($xml)->toContain('Premium Laptop')
        ->and($xml)->toContain('$1,200.00');

    // Verify nested SDT in cell
    expect($xml)->toContain('<w:tag w:val="item-price"/>')
        ->and($xml)->toContain('<w:alias w:val="Item Price"/>');

    safeUnlink($templatePath);
    safeUnlink($outputPath);
});

test('replaceGroupContent() throws InvalidArgumentException for non-GROUP SDT', function () {
    // 1. Create template with RICH_TEXT SDT (not GROUP)
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:sdt>
            <w:sdtPr>
                <w:id w:val="12345678"/>
                <w:tag w:val="not-group"/>
                <w:richText/>
            </w:sdtPr>
            <w:sdtContent>
                <w:p>
                    <w:r>
                        <w:t>Text Content</w:t>
                    </w:r>
                </w:p>
            </w:sdtContent>
        </w:sdt>
    </w:body>
</w:document>
XML;

    $templatePath = tempnam(sys_get_temp_dir(), 'not_group_') . '.docx';
    createDocxFromXml($templatePath, $xml);

    // 2. Attempt to replace non-GROUP SDT
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('New Content');

    $processor = new ContentProcessor($templatePath);

    // 3. Should throw InvalidArgumentException
    expect(fn() => $processor->replaceGroupContent('not-group', $cc))
        ->toThrow(
            InvalidArgumentException::class,
            "SDT with tag 'not-group' is not a GROUP type Content Control"
        );

    safeUnlink($templatePath);
});

test('replaceGroupContent() returns false for non-existent tag', function () {
    // 1. Create template with GROUP SDT
    $templatePath = createGroupSdtTemplate('existing-tag');

    // 2. Attempt to replace non-existent tag
    $cc = new ContentControl();
    $cc->addSection()->addText('Content');

    $processor = new ContentProcessor($templatePath);
    $result = $processor->replaceGroupContent('non-existent-tag', $cc);

    expect($result)->toBeFalse();

    safeUnlink($templatePath);
});

test('replaceGroupContent() handles multiple nested levels of SDTs', function () {
    // 1. Create template with GROUP SDT
    $templatePath = createGroupSdtTemplate('complex-section');

    // 2. Create structure with 3 levels of nesting
    $cc = new ContentControl();
    $section = $cc->addSection();

    // Level 1: Table with SDT
    $table = $section->addTable();
    $row = $table->addRow();
    $cell = $row->addCell(5000);

    // Level 2: Paragraph inside cell with SDT
    $para = $cell->addText('Nested Content');
    $cc->addContentControl($para, ['tag' => 'level-2', 'alias' => 'Level 2 SDT']);

    // Wrap entire table in SDT (Level 1)
    $cc->addContentControl($table, ['tag' => 'level-1', 'alias' => 'Level 1 SDT']);

    // 3. Replace GROUP SDT
    $processor = new ContentProcessor($templatePath);
    $result = $processor->replaceGroupContent('complex-section', $cc);

    expect($result)->toBeTrue();

    // 4. Verify all levels preserved
    $outputPath = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
    $processor->save($outputPath);

    $zip = new ZipArchive();
    $zip->open($outputPath);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    // Verify both SDT levels
    expect($xml)->toContain('<w:tag w:val="level-1"/>')
        ->and($xml)->toContain('<w:tag w:val="level-2"/>')
        ->and($xml)->toContain('<w:alias w:val="Level 1 SDT"/>')
        ->and($xml)->toContain('<w:alias w:val="Level 2 SDT"/>');

    safeUnlink($templatePath);
    safeUnlink($outputPath);
});

test('replaceGroupContent() clears existing content before replacement', function () {
    // 1. Create template with GROUP SDT containing multiple paragraphs
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:sdt>
            <w:sdtPr>
                <w:id w:val="12345678"/>
                <w:tag w:val="replace-me"/>
                <w:group/>
            </w:sdtPr>
            <w:sdtContent>
                <w:p>
                    <w:r>
                        <w:t>Old Paragraph 1</w:t>
                    </w:r>
                </w:p>
                <w:p>
                    <w:r>
                        <w:t>Old Paragraph 2</w:t>
                    </w:r>
                </w:p>
                <w:p>
                    <w:r>
                        <w:t>Old Paragraph 3</w:t>
                    </w:r>
                </w:p>
            </w:sdtContent>
        </w:sdt>
    </w:body>
</w:document>
XML;

    $templatePath = tempnam(sys_get_temp_dir(), 'clear_content_') . '.docx';
    createDocxFromXml($templatePath, $xml);

    // 2. Replace with single paragraph
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('New Single Paragraph');

    $processor = new ContentProcessor($templatePath);
    $result = $processor->replaceGroupContent('replace-me', $cc);

    expect($result)->toBeTrue();

    // 3. Verify old content is gone
    $outputPath = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
    $processor->save($outputPath);

    $zip = new ZipArchive();
    $zip->open($outputPath);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    expect($xml)->toContain('New Single Paragraph')
        ->and($xml)->not->toContain('Old Paragraph 1')
        ->and($xml)->not->toContain('Old Paragraph 2')
        ->and($xml)->not->toContain('Old Paragraph 3');

    safeUnlink($templatePath);
    safeUnlink($outputPath);
});

test('replaceGroupContent() preserves GROUP SDT properties', function () {
    // 1. Create template with GROUP SDT with custom properties
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:sdt>
            <w:sdtPr>
                <w:id w:val="87654321"/>
                <w:tag w:val="preserve-props"/>
                <w:alias w:val="Custom Alias"/>
                <w:group/>
                <w:lock w:val="sdtLocked"/>
            </w:sdtPr>
            <w:sdtContent>
                <w:p>
                    <w:r>
                        <w:t>Original Content</w:t>
                    </w:r>
                </w:p>
            </w:sdtContent>
        </w:sdt>
    </w:body>
</w:document>
XML;

    $templatePath = tempnam(sys_get_temp_dir(), 'preserve_props_') . '.docx';
    createDocxFromXml($templatePath, $xml);

    // 2. Replace content
    $cc = new ContentControl();
    $section = $cc->addSection();
    $section->addText('New Content');

    $processor = new ContentProcessor($templatePath);
    $result = $processor->replaceGroupContent('preserve-props', $cc);

    expect($result)->toBeTrue();

    // 3. Verify properties are preserved
    $outputPath = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
    $processor->save($outputPath);

    $zip = new ZipArchive();
    $zip->open($outputPath);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    // Content replaced
    expect($xml)->toContain('New Content')
        ->and($xml)->not->toContain('Original Content');

    // Properties preserved
    expect($xml)->toContain('<w:id w:val="87654321"/>')
        ->and($xml)->toContain('<w:tag w:val="preserve-props"/>')
        ->and($xml)->toContain('<w:alias w:val="Custom Alias"/>')
        ->and($xml)->toContain('<w:group/>')
        ->and($xml)->toContain('<w:lock w:val="sdtLocked"/>');

    safeUnlink($templatePath);
    safeUnlink($outputPath);
});

<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentProcessor;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;

/**
 * Unit Tests for ContentProcessor Advanced Methods (Phase 3)
 *
 * Tests appendContent(), removeContent(), setValue(), and removeAllControlContents()
 */
describe('ContentProcessor Advanced Methods', function () {
    beforeEach(function () {
        // Helper function to create DOCX with SDT
        $this->createDocxWithSdt = function (string $tag, string $content): string {
            $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';

            $zip = new ZipArchive();
            $zip->open($tempFile, ZipArchive::CREATE);

            $documentXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:sdt>
            <w:sdtPr>
                <w:tag w:val="{$tag}"/>
            </w:sdtPr>
            <w:sdtContent>
                {$content}
            </w:sdtContent>
        </w:sdt>
    </w:body>
</w:document>
XML;

            $zip->addFromString('word/document.xml', $documentXml);
            $zip->close();

            return $tempFile;
        };
    });

    describe('appendContent()', function () {
        it('appends text element to existing content', function () {
            $tempFile = ($this->createDocxWithSdt)('test-tag', '<w:p><w:r><w:t>Original</w:t></w:r></w:p>');

            $processor = new ContentProcessor($tempFile);
            
            // Create text element to append
            $section = (new PhpOffice\PhpWord\PhpWord())->addSection();
            $textElement = $section->addText('Appended');

            $result = $processor->appendContent('test-tag', $textElement);
            expect($result)->toBeTrue();

            $processor->save();

            // Verify content was appended
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            expect($xml)->toContain('Original');
            expect($xml)->toContain('Appended');

            unlink($tempFile);
        });

        it('returns false for non-existent tag', function () {
            $tempFile = ($this->createDocxWithSdt)('test-tag', '<w:p><w:r><w:t>Text</w:t></w:r></w:p>');

            $processor = new ContentProcessor($tempFile);
            
            $section = (new PhpOffice\PhpWord\PhpWord())->addSection();
            $textElement = $section->addText('Test');

            $result = $processor->appendContent('non-existent', $textElement);
            expect($result)->toBeFalse();

            unset($processor); // Close ZIP via destructor
            unlink($tempFile);
        });

        it('appends multiple elements sequentially', function () {
            $tempFile = ($this->createDocxWithSdt)('list', '<w:p><w:r><w:t>Item 1</w:t></w:r></w:p>');

            $processor = new ContentProcessor($tempFile);
            
            $section = (new PhpOffice\PhpWord\PhpWord())->addSection();
            $processor->appendContent('list', $section->addText('Item 2'));
            $processor->appendContent('list', $section->addText('Item 3'));

            $processor->save();

            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            expect($xml)->toContain('Item 1');
            expect($xml)->toContain('Item 2');
            expect($xml)->toContain('Item 3');

            unlink($tempFile);
        });
    });

    describe('removeContent()', function () {
        it('removes all content from SDT', function () {
            $tempFile = ($this->createDocxWithSdt)('test-tag', '<w:p><w:r><w:t>Content to remove</w:t></w:r></w:p>');

            $processor = new ContentProcessor($tempFile);
            $result = $processor->removeContent('test-tag');

            expect($result)->toBeTrue();

            $processor->save();

            // Verify content was removed
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            expect($xml)->not->toContain('Content to remove');
            expect($xml)->toContain('<w:sdtContent/>'); // Empty but present

            unlink($tempFile);
        });

        it('returns false for non-existent tag', function () {
            $tempFile = ($this->createDocxWithSdt)('test-tag', '<w:p><w:r><w:t>Text</w:t></w:r></w:p>');

            $processor = new ContentProcessor($tempFile);
            $result = $processor->removeContent('non-existent');

            expect($result)->toBeFalse();

            unset($processor); // Close ZIP via destructor
            unlink($tempFile);
        });

        it('can remove and re-add content', function () {
            $tempFile = ($this->createDocxWithSdt)('test-tag', '<w:p><w:r><w:t>Original</w:t></w:r></w:p>');

            $processor = new ContentProcessor($tempFile);
            $processor->removeContent('test-tag');
            $processor->replaceContent('test-tag', 'New Content');

            $processor->save();

            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            expect($xml)->not->toContain('Original');
            expect($xml)->toContain('New Content');

            unlink($tempFile);
        });
    });

    describe('setValue()', function () {
        it('replaces text while preserving formatting', function () {
            $content = <<<XML
<w:p>
    <w:r>
        <w:rPr>
            <w:b/>
            <w:color w:val="FF0000"/>
        </w:rPr>
        <w:t>Old Text</w:t>
    </w:r>
</w:p>
XML;
            $tempFile = ($this->createDocxWithSdt)('formatted-text', $content);

            $processor = new ContentProcessor($tempFile);
            $result = $processor->setValue('formatted-text', 'New Text');

            expect($result)->toBeTrue();

            $processor->save();

            // Verify text changed but formatting preserved
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            expect($xml)->not->toContain('Old Text');
            expect($xml)->toContain('New Text');
            expect($xml)->toContain('<w:b/>'); // Bold preserved
            expect($xml)->toContain('FF0000'); // Color preserved

            unlink($tempFile);
        });

        it('consolidates multiple text nodes into first', function () {
            $content = <<<XML
<w:p>
    <w:r><w:t>Part 1</w:t></w:r>
    <w:r><w:t>Part 2</w:t></w:r>
    <w:r><w:t>Part 3</w:t></w:r>
</w:p>
XML;
            $tempFile = ($this->createDocxWithSdt)('multi-text', $content);

            $processor = new ContentProcessor($tempFile);
            $processor->setValue('multi-text', 'Consolidated');

            $processor->save();

            // Verify only one text node remains
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            $dom = new DOMDocument();
            $dom->loadXML($xml);
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            $textNodes = $xpath->query('//w:sdt//w:t');
            expect($textNodes->length)->toBe(1);
            expect($textNodes->item(0)->textContent)->toBe('Consolidated');

            unlink($tempFile);
        });

        it('falls back to replaceContent if no text nodes exist', function () {
            $tempFile = ($this->createDocxWithSdt)('empty-sdt', '');

            $processor = new ContentProcessor($tempFile);
            $result = $processor->setValue('empty-sdt', 'New Text');

            expect($result)->toBeTrue();

            $processor->save();

            $zip = new ZipArchive();
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            expect($xml)->toContain('New Text');

            unlink($tempFile);
        });

        it('returns false for non-existent tag', function () {
            $tempFile = ($this->createDocxWithSdt)('test-tag', '<w:p><w:r><w:t>Text</w:t></w:r></w:p>');

            $processor = new ContentProcessor($tempFile);
            $result = $processor->setValue('non-existent', 'Test');

            expect($result)->toBeFalse();

            unset($processor); // Close ZIP via destructor
            unlink($tempFile);
        });
    });

    describe('removeAllControlContents()', function () {
        it('removes all SDT contents from document', function () {
            $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';

            $zip = new ZipArchive();
            $zip->open($tempFile, ZipArchive::CREATE);

            $documentXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:sdt>
            <w:sdtPr><w:tag w:val="sdt1"/></w:sdtPr>
            <w:sdtContent><w:p><w:r><w:t>Content 1</w:t></w:r></w:p></w:sdtContent>
        </w:sdt>
        <w:sdt>
            <w:sdtPr><w:tag w:val="sdt2"/></w:sdtPr>
            <w:sdtContent><w:p><w:r><w:t>Content 2</w:t></w:r></w:p></w:sdtContent>
        </w:sdt>
        <w:sdt>
            <w:sdtPr><w:tag w:val="sdt3"/></w:sdtPr>
            <w:sdtContent><w:p><w:r><w:t>Content 3</w:t></w:r></w:p></w:sdtContent>
        </w:sdt>
    </w:body>
</w:document>
XML;

            $zip->addFromString('word/document.xml', $documentXml);
            $zip->close();

            $processor = new ContentProcessor($tempFile);
            $count = $processor->removeAllControlContents();

            expect($count)->toBe(3);

            $processor->save();

            // Verify all contents removed
            $zip->open($tempFile);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            expect($xml)->not->toContain('Content 1');
            expect($xml)->not->toContain('Content 2');
            expect($xml)->not->toContain('Content 3');

            unlink($tempFile);
        });

        it('adds document protection when block=true', function () {
            $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';

            $zip = new ZipArchive();
            $zip->open($tempFile, ZipArchive::CREATE);

            $documentXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:sdt>
            <w:sdtPr><w:tag w:val="test"/></w:sdtPr>
            <w:sdtContent><w:p><w:r><w:t>Test</w:t></w:r></w:p></w:sdtContent>
        </w:sdt>
    </w:body>
</w:document>
XML;

            $zip->addFromString('word/document.xml', $documentXml);
            $zip->close();

            $processor = new ContentProcessor($tempFile);
            $count = $processor->removeAllControlContents(true);

            expect($count)->toBe(1);

            $processor->save();

            // Verify settings.xml was created with protection
            $zip->open($tempFile);
            $settingsXml = $zip->getFromName('word/settings.xml');
            $zip->close();

            expect($settingsXml)->not->toBeFalse();
            expect($settingsXml)->toContain('documentProtection');
            expect($settingsXml)->toContain('readOnly');

            unlink($tempFile);
        });

        it('returns 0 for document with no SDTs', function () {
            $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';

            $zip = new ZipArchive();
            $zip->open($tempFile, ZipArchive::CREATE);

            $documentXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p><w:r><w:t>Regular text</w:t></w:r></w:p>
    </w:body>
</w:document>
XML;

            $zip->addFromString('word/document.xml', $documentXml);
            $zip->close();

            $processor = new ContentProcessor($tempFile);
            $count = $processor->removeAllControlContents();

            expect($count)->toBe(0);

            unset($processor); // Close ZIP via destructor
            unlink($tempFile);
        });
    });
});

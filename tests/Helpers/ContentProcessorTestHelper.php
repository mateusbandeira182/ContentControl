<?php

declare(strict_types=1);

/**
 * Test Helper Functions for ContentProcessor Tests
 *
 * Shared functions for creating test DOCX fixtures with SDTs.
 */

/**
 * Create DOCX with single SDT
 *
 * @param string $path File path
 * @param string $tag SDT tag value
 * @return void
 */
function createDocxWithSdt(string $path, string $tag): void
{
    $escapedTag = htmlspecialchars($tag, ENT_XML1, 'UTF-8');
    
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:sdt>
            <w:sdtPr>
                <w:id w:val="12345678"/>
                <w:tag w:val="{$escapedTag}"/>
            </w:sdtPr>
            <w:sdtContent>
                <w:p>
                    <w:r>
                        <w:t>Placeholder content</w:t>
                    </w:r>
                </w:p>
            </w:sdtContent>
        </w:sdt>
    </w:body>
</w:document>
XML;

    createDocxFromXml($path, $xml);
}

/**
 * Create DOCX with multiple SDTs
 *
 * @param string $path File path
 * @param array<string> $tags Array of tag values
 * @return void
 */
function createDocxWithMultipleSdts(string $path, array $tags): void
{
    $sdts = '';
    $id = 10000000;
    
    foreach ($tags as $tag) {
        $escapedTag = htmlspecialchars($tag, ENT_XML1, 'UTF-8');
        $sdts .= <<<XML

        <w:sdt>
            <w:sdtPr>
                <w:id w:val="{$id}"/>
                <w:tag w:val="{$escapedTag}"/>
            </w:sdtPr>
            <w:sdtContent>
                <w:p>
                    <w:r>
                        <w:t>Content for {$escapedTag}</w:t>
                    </w:r>
                </w:p>
            </w:sdtContent>
        </w:sdt>
XML;
        $id++;
    }
    
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>{$sdts}
    </w:body>
</w:document>
XML;

    createDocxFromXml($path, $xml);
}

/**
 * Create DOCX from XML content
 *
 * @param string $path File path
 * @param string $documentXml Content for word/document.xml
 * @return void
 */
function createDocxFromXml(string $path, string $documentXml): void
{
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

    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', $documentXml);
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->close();
}

<?php

declare(strict_types=1);

use MkGrow\ContentControl\SDTInjector;
use MkGrow\ContentControl\SDTConfig;
use MkGrow\ContentControl\ContentControl;

/**
 * Test suite for SDTInjector inline-level helper methods
 * 
 * Tests private methods findParentCell(), getParagraphIndexInCell(), etc.
 */
describe('SDTInjector - Inline-Level Helper Methods', function () {
    test('findParentCell locates parent cell element', function () {
        // Create DOM with paragraph inside cell
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl>
      <w:tr>
        <w:tc>
          <w:p>
            <w:r><w:t>Test Content</w:t></w:r>
          </w:p>
        </w:tc>
      </w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML;

        $dom = new DOMDocument();
        $dom->loadXML($xml);
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        
        $paragraphElement = $xpath->query('//w:p')->item(0);
        
        // Access private method via Reflection
        $injector = new SDTInjector();
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('findParentCell');
        $method->setAccessible(true);
        
        $cellElement = $method->invoke($injector, $paragraphElement);
        
        expect($cellElement)->toBeInstanceOf(DOMElement::class);
        expect($cellElement->localName)->toBe('tc');
    });

    test('findParentCell throws exception if paragraph not in cell', function () {
        // Create DOM with paragraph NOT inside cell
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t>Test Content</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

        $dom = new DOMDocument();
        $dom->loadXML($xml);
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        
        $paragraphElement = $xpath->query('//w:p')->item(0);
        
        // Access private method via Reflection
        $injector = new SDTInjector();
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('findParentCell');
        $method->setAccessible(true);
        
        expect(fn() => $method->invoke($injector, $paragraphElement))
            ->toThrow(RuntimeException::class, 'Paragraph not inside a table cell');
    });

    test('getParagraphIndexInCell returns correct index', function () {
        // Create DOM with multiple paragraphs in cell
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl>
      <w:tr>
        <w:tc>
          <w:p>
            <w:r><w:t>First paragraph</w:t></w:r>
          </w:p>
          <w:p>
            <w:r><w:t>Second paragraph</w:t></w:r>
          </w:p>
          <w:p>
            <w:r><w:t>Third paragraph</w:t></w:r>
          </w:p>
        </w:tc>
      </w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML;

        $dom = new DOMDocument();
        $dom->loadXML($xml);
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        
        $cellElement = $xpath->query('//w:tc')->item(0);
        $paragraphs = $xpath->query('//w:p');
        
        // Access private method via Reflection
        $injector = new SDTInjector();
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('getParagraphIndexInCell');
        $method->setAccessible(true);
        
        // Test first paragraph (index 0)
        $index0 = $method->invoke($injector, $cellElement, $paragraphs->item(0));
        expect($index0)->toBe(0);
        
        // Test second paragraph (index 1)
        $index1 = $method->invoke($injector, $cellElement, $paragraphs->item(1));
        expect($index1)->toBe(1);
        
        // Test third paragraph (index 2)
        $index2 = $method->invoke($injector, $cellElement, $paragraphs->item(2));
        expect($index2)->toBe(2);
    });

    test('getParagraphIndexInCell ignores paragraphs already wrapped in SDT', function () {
        // Create DOM with paragraph already wrapped in SDT
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl>
      <w:tr>
        <w:tc>
          <w:sdt>
            <w:sdtContent>
              <w:p>
                <w:r><w:t>Wrapped paragraph</w:t></w:r>
              </w:p>
            </w:sdtContent>
          </w:sdt>
          <w:p>
            <w:r><w:t>Unwrapped paragraph</w:t></w:r>
          </w:p>
        </w:tc>
      </w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML;

        $dom = new DOMDocument();
        $dom->loadXML($xml);
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        
        $cellElement = $xpath->query('//w:tc')->item(0);
        
        // Get unwrapped paragraph (should be index 0, not 1)
        $unwrappedParagraphs = $xpath->query('//w:p[not(ancestor::w:sdtContent)]');
        $unwrappedParagraph = $unwrappedParagraphs->item(0);
        
        // Access private method via Reflection
        $injector = new SDTInjector();
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('getParagraphIndexInCell');
        $method->setAccessible(true);
        
        $index = $method->invoke($injector, $cellElement, $unwrappedParagraph);
        expect($index)->toBe(0);
    });

    test('getParagraphIndexInCell throws exception if paragraph not found', function () {
        // Create DOM with paragraph in different cell
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl>
      <w:tr>
        <w:tc>
          <w:p>
            <w:r><w:t>Cell 1</w:t></w:r>
          </w:p>
        </w:tc>
        <w:tc>
          <w:p>
            <w:r><w:t>Cell 2</w:t></w:r>
          </w:p>
        </w:tc>
      </w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML;

        $dom = new DOMDocument();
        $dom->loadXML($xml);
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        
        $cells = $xpath->query('//w:tc');
        $cell1 = $cells->item(0);
        
        $paragraphs = $xpath->query('//w:p');
        $paragraphInCell2 = $paragraphs->item(1);
        
        // Access private method via Reflection
        $injector = new SDTInjector();
        $reflection = new ReflectionClass($injector);
        $method = $reflection->getMethod('getParagraphIndexInCell');
        $method->setAccessible(true);
        
        expect(fn() => $method->invoke($injector, $cell1, $paragraphInCell2))
            ->toThrow(RuntimeException::class, 'Paragraph not found in cell');
    });
});

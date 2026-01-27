<?php

namespace MkGrow\ContentControl;

use PhpOffice\PhpWord\Element\AbstractContainer;
use DOMDocument;
use DOMElement;
use PhpOffice\PhpWord\Shared\XMLWriter;

class ContentControl extends AbstractContainer
{
    private string $id;
    private string $alias;
    private string $tag;
    private string $lockType;
    private ?DOMDocument $contentDocument = null;

    public function __construct(
        AbstractContainer $content,
        array $options = [],
    )
    {
        $this->id = $options['id'] ?? '';
        $this->alias = $options['alias'] ?? '';
        $this->tag = $options['tag'] ?? '';
        $this->lockType = $options['lockType'] ?? '';
        $this->addElement($content);
    }

    public function getXml(): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $sdt = $doc->createElement('w:sdt');
        $sdtSecPr = $doc->createElement('w:sdtPr');

        $tag = $doc->createElement('w:tag');

        $tag->setAttribute('w:id', $this->id);
        $tag->setAttribute('w:alias', $this->alias);
        $tag->setAttribute('w:tag', $this->tag);
        $sdtSecPr->appendChild($tag);
        $docPart = $doc->createElement('w:docPartObj');
        $sdtSecPr->appendChild($docPart);
        $lock = $doc->createElement('w:lock');
        $lock->setAttribute('w:val', $this->lockType);
        $sdtSecPr->appendChild($lock);
        $sdtContent = $doc->createElement('w:sdtContent');
        $contentXml = $this->serializeInnerContent();
        $fragment = $doc->createDocumentFragment();
        $fragment->appendXML($contentXml);
        $sdtContent->appendChild($fragment);

        $sdt->appendChild($sdtSecPr);
        $sdt->appendChild($sdtContent);

        return $doc->saveXML($sdt);
    }

    private function serializeInnerContent(): string
    {
        // Usar PhpWord XMLWriter para elementos internos
        $xmlWriter = new XMLWriter();
        // Lógica para renderizar $this->elements como w:p, w:tbl etc.
        foreach ($this->elements as $element) {
            $xmlWriter->startElement('w:p'); // Exemplo simplificado
            $xmlWriter->writeRaw($element->getXml());
            $xmlWriter->endElement();
        }
        return $xmlWriter->getData();
    }
}
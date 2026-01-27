<?php

namespace MkGrow\ContentControl\Writer\Word2007\Element;

use PhpOffice\PhpWord\Writer\Word2007\Element\AbstractElement;
use PhpOffice\PhpWord\Shared\XMLWriter;

/**
 * Writer para Content Control (Structured Document Tag)
 * 
 * Integra ContentControl com sistema de Writers do PHPWord.
 * Serializa Content Controls como XML OOXML válido no documento Word.
 * 
 * @since 2.0.0
 */
class ContentControl extends AbstractElement
{
    /**
     * Escreve XML do Content Control no documento
     * 
     * Utiliza método getXml() do ContentControl para gerar
     * estrutura OOXML completa conforme ISO/IEC 29500-1:2016 §17.5.2
     * 
     * @return void
     */
    public function write(): void
    {
        $element = $this->getElement();
        
        // Verificar se é instância de ContentControl
        if (!$element instanceof \MkGrow\ContentControl\ContentControl) {
            return;
        }
        
        // Obter XMLWriter do PHPWord
        $xmlWriter = $this->getXmlWriter();
        
        // Gerar e escrever XML do Content Control
        $xml = $element->getXml();
        
        if ($xml !== '') {
            $xmlWriter->writeRaw($xml);
        }
    }
}

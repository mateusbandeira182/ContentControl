<?php

require 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use MkGrow\ContentControl\ContentControl;

// Criar documento PHPWord
$phpWord = new PhpWord();
$section = $phpWord->addSection();
$section->addText('Este é um Content Control funcional', ['bold' => true]);

// Criar Content Control
$control = new ContentControl($section, [
    'alias' => 'Campo de Teste',
    'tag' => 'test-field',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Exibir XML gerado
echo "=== XML GERADO ===\n\n";
$xml = $control->getXml();
echo $xml;
echo "\n\n=== VALIDAÇÃO ===\n\n";

// Validar XML
$dom = new DOMDocument();
if ($dom->loadXML($xml)) {
    echo "✓ XML válido e bem formado\n";
    echo "✓ Elemento raiz: " . $dom->documentElement->nodeName . "\n";
    
    // XPath para verificar estrutura
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    
    echo "✓ Namespace WordML: " . $dom->documentElement->getAttribute('xmlns:w') . "\n";
    echo "✓ Elementos <w:sdtPr>: " . $xpath->query('//w:sdtPr')->length . "\n";
    echo "✓ Elementos <w:sdtContent>: " . $xpath->query('//w:sdtContent')->length . "\n";
    echo "✓ Alias: " . $xpath->query('//w:alias/@w:val')->item(0)?->nodeValue . "\n";
    echo "✓ Tag: " . $xpath->query('//w:tag/@w:val')->item(0)?->nodeValue . "\n";
    echo "✓ Tipo: " . ($xpath->query('//w:richText')->length > 0 ? 'richText' : 'outro') . "\n";
    echo "✓ Lock: " . $xpath->query('//w:lock/@w:val')->item(0)?->nodeValue . "\n";
} else {
    echo "✗ XML inválido\n";
}

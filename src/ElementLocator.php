<?php

declare(strict_types=1);

namespace MkGrow\ContentControl;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Localizador de elementos PHPWord no DOM tree de document.xml
 * 
 * Usa múltiplas estratégias de busca XPath:
 * 1. Por tipo + ordem de registro (mais rápida)
 * 2. Por hash de conteúdo (fallback para elementos idênticos)
 * 3. Por características estruturais (tabelas, cells)
 * 
 * @since 3.0.0
 */
final class ElementLocator
{
    private const WORDML_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Cache de XPath instance para reutilização
     */
    private ?DOMXPath $xpath = null;

    /**
     * Localiza elemento PHPWord no DOM
     * 
     * @param DOMDocument $dom Documento DOM carregado
     * @param object $element Elemento PHPWord a localizar
     * @param int $registrationOrder Ordem de registro do elemento (0-indexed)
     * @return DOMElement|null Elemento DOM ou null se não encontrado
     */
    public function findElementInDOM(
        DOMDocument $dom,
        object $element,
        int $registrationOrder = 0
    ): ?DOMElement {
        // Inicializar XPath se necessário
        if ($this->xpath === null) {
            $this->xpath = new DOMXPath($dom);
            $this->xpath->registerNamespace('w', self::WORDML_NS);
        }

        // Estratégia 1: Por tipo + ordem
        $found = $this->findByTypeAndOrder($element, $registrationOrder);
        if ($found !== null) {
            return $found;
        }

        // Estratégia 2: Por hash de conteúdo
        $contentHash = ElementIdentifier::generateContentHash($element);
        $found = $this->findByContentHash($element, $contentHash);
        if ($found !== null) {
            return $found;
        }

        // Não encontrado
        return null;
    }

    /**
     * Busca por tipo de elemento + ordem de registro
     * 
     * @param object $element Elemento PHPWord
     * @param int $order Ordem de registro (0-indexed)
     * @return DOMElement|null
     * @throws \InvalidArgumentException Se tipo de elemento não é suportado
     */
    private function findByTypeAndOrder(object $element, int $order): ?DOMElement
    {
        $query = $this->createXPathQuery($element);

        // Para células, buscar apenas células NÃO envolvidas em SDTs
        // Isso evita localizar células que já foram movidas para <w:sdtContent>
        // Sempre busca [1] pois células são removidas do resultado após wrapping
        if ($element instanceof \PhpOffice\PhpWord\Element\Cell) {
            $query = '//w:body//w:tc[not(ancestor::w:sdtContent)][1]';
            
            $nodes = $this->xpath !== null ? $this->xpath->query($query) : null;
            if ($nodes === null || $nodes === false || $nodes->length === 0) {
                return null;
            }

            $node = $nodes->item(0);
            return ($node instanceof DOMElement) ? $node : null;
        }

        // Para outros elementos (Text, Table, etc), aplicar filtro similar
        // e sempre usar [1] pois elementos wrappados são removidos do resultado
        if ($element instanceof \PhpOffice\PhpWord\Element\Text ||
            $element instanceof \PhpOffice\PhpWord\Element\TextRun ||
            $element instanceof \PhpOffice\PhpWord\Element\Table) {
            // Buscar apenas elementos NÃO envolvidos em SDTs
            $query .= '[not(ancestor::w:sdtContent)][1]';
            
            $nodes = $this->xpath !== null ? $this->xpath->query($query) : null;
            if ($nodes === null || $nodes === false || $nodes->length === 0) {
                return null;
            }

            $node = $nodes->item(0);
            return ($node instanceof DOMElement) ? $node : null;
        }

        // Para outros elementos sem filtro, usar índice de registro
        // XPath é 1-indexed
        $xpathPosition = $order + 1;
        $query .= "[{$xpathPosition}]";

        $nodes = $this->xpath !== null ? $this->xpath->query($query) : null;
        if ($nodes === null || $nodes === false || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);
        return ($node instanceof DOMElement) ? $node : null;
    }

    /**
     * Busca por hash de conteúdo
     * 
     * Itera por todos elementos do tipo e compara hash.
     * Mais lento, mas funciona para elementos idênticos.
     * 
     * @param object $element Elemento PHPWord
     * @param string $contentHash Hash MD5 do conteúdo
     * @return DOMElement|null
     * @throws \InvalidArgumentException Se tipo de elemento não é suportado
     */
    private function findByContentHash(object $element, string $contentHash): ?DOMElement
    {
        $query = $this->createXPathQuery($element);
        if ($this->xpath === null) {
            return null;
        }

        $nodes = $this->xpath->query($query);
        if ($nodes === false) {
            return null;
        }
        
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            // Calcular hash do elemento DOM
            $domHash = $this->hashDOMElement($node, $element);
            
            if ($domHash === $contentHash) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Cria query XPath para tipo de elemento
     * 
     * @param object $element Elemento PHPWord
     * @return string Query XPath
     * @throws \InvalidArgumentException Se tipo de elemento não é suportado
     */
    private function createXPathQuery(object $element): string
    {
        // Text/TextRun: buscar <w:p> (paragraph)
        if ($element instanceof \PhpOffice\PhpWord\Element\Text ||
            $element instanceof \PhpOffice\PhpWord\Element\TextRun) {
            return '//w:body/w:p';
        }

        // Table: buscar <w:tbl>
        if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
            return '//w:body/w:tbl';
        }

        // Cell: buscar <w:tc> (table cell)
        if ($element instanceof \PhpOffice\PhpWord\Element\Cell) {
            return '//w:body//w:tc';
        }

        // Section: não localiza (não serializado como elemento único)
        // Containers são processados via seus elementos filhos
        
        // Elemento não suportado - lançar exceção descritiva
        $supportedTypes = [
            \PhpOffice\PhpWord\Element\Text::class,
            \PhpOffice\PhpWord\Element\TextRun::class,
            \PhpOffice\PhpWord\Element\Table::class,
            \PhpOffice\PhpWord\Element\Cell::class,
        ];

        $elementClass = get_class($element);

        throw new \InvalidArgumentException(
            sprintf(
                'Element type "%s" is not supported for Content Controls. Supported types: %s',
                $elementClass,
                implode(', ', $supportedTypes)
            )
        );
    }

    /**
     * Gera hash de elemento DOM para comparação
     * 
     * @param DOMElement $domElement Elemento DOM do document.xml
     * @param object $phpWordElement Elemento PHPWord (para contexto de tipo)
     * @return string Hash MD5 truncado (8 chars)
     */
    private function hashDOMElement(DOMElement $domElement, object $phpWordElement): string
    {
        $parts = [];

        // Paragraph: extrair todo texto
        if ($domElement->nodeName === 'w:p') {
            $parts[] = 'paragraph';  // Compatível com ElementIdentifier
            $text = $this->extractTextContent($domElement);
            $parts[] = $text;
        }

        // Table: contar linhas
        if ($domElement->nodeName === 'w:tbl' && $this->xpath !== null) {
            $parts[] = 'table';  // Compatível com ElementIdentifier
            $rows = $this->xpath->query('.//w:tr', $domElement);
            if ($rows !== false) {
                $parts[] = "rows:{$rows->length}";

                // Texto da primeira célula de cada linha
                foreach ($rows as $row) {
                    if (!$row instanceof DOMElement) {
                        continue;
                    }
                    $firstCell = $this->xpath->query('.//w:tc[1]', $row)->item(0);
                    if ($firstCell instanceof DOMElement) {
                        $text = $this->extractTextContent($firstCell);
                        $parts[] = $text;
                    }
                }
            }
        }

        // Cell: extrair conteúdo textual de elementos filhos
        if ($domElement->nodeName === 'w:tc' && $this->xpath !== null) {
            $parts[] = 'cell';  // Compatível com ElementIdentifier
            
            // Buscar elementos filhos (parágrafos dentro da célula)
            $childParagraphs = $this->xpath->query('.//w:p', $domElement);
            if ($childParagraphs !== false) {
                foreach ($childParagraphs as $p) {
                    if ($p instanceof DOMElement) {
                        $text = $this->extractTextContent($p);
                        if ($text !== '') {
                            $parts[] = 'text';
                            $parts[] = $text;
                        }
                    }
                }
            }
        }

        $serialized = implode('|', $parts);
        return substr(md5($serialized), 0, 8);
    }

    /**
     * Extrai todo conteúdo textual de elemento DOM
     * 
     * @param DOMElement $element Elemento DOM
     * @return string Texto concatenado
     */
    private function extractTextContent(DOMElement $element): string
    {
        if ($this->xpath === null) {
            return '';
        }

        $textNodes = $this->xpath->query('.//w:t', $element);
        if ($textNodes === false) {
            return '';
        }

        $texts = [];

        foreach ($textNodes as $node) {
            $texts[] = $node->textContent;
        }

        return implode('', $texts);
    }

    /**
     * Valida se elemento DOM corresponde ao elemento PHPWord
     * 
     * @param DOMElement $domElement Elemento DOM
     * @param object $phpWordElement Elemento PHPWord
     * @return bool true se corresponder
     */
    public function validateMatch(DOMElement $domElement, object $phpWordElement): bool
    {
        // Inicializar XPath se necessário
        if ($this->xpath === null && $domElement->ownerDocument !== null) {
            $this->xpath = new DOMXPath($domElement->ownerDocument);
            $this->xpath->registerNamespace('w', self::WORDML_NS);
        }

        // Validar tipo
        $expectedNodeName = null;

        if ($phpWordElement instanceof \PhpOffice\PhpWord\Element\Text ||
            $phpWordElement instanceof \PhpOffice\PhpWord\Element\TextRun) {
            $expectedNodeName = 'w:p';
        } elseif ($phpWordElement instanceof \PhpOffice\PhpWord\Element\Table) {
            $expectedNodeName = 'w:tbl';
        }

        if ($expectedNodeName !== null && $domElement->nodeName !== $expectedNodeName) {
            return false;
        }

        // Validar hash
        $phpWordHash = ElementIdentifier::generateContentHash($phpWordElement);
        $domHash = $this->hashDOMElement($domElement, $phpWordElement);

        return $phpWordHash === $domHash;
    }
}

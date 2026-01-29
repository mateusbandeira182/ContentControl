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
    private const VML_NS = 'urn:schemas-microsoft-com:vml';
    private const OFFICE_NS = 'urn:schemas-microsoft-com:office:office';

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
     * @throws \InvalidArgumentException Se tipo de elemento não é suportado
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
            $this->xpath->registerNamespace('v', self::VML_NS);
            $this->xpath->registerNamespace('o', self::OFFICE_NS);
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
        // Title: usar método especializado
        if ($element instanceof \PhpOffice\PhpWord\Element\Title) {
            return $this->findTitleByDepth($element, $order);
        }

        // Image: usar método especializado
        if ($element instanceof \PhpOffice\PhpWord\Element\Image) {
            return $this->findImageByOrder($order);
        }

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

        // Title: buscar <w:p> com w:pStyle (tratado em findTitleByDepth)
        if ($element instanceof \PhpOffice\PhpWord\Element\Title) {
            return '//w:body/w:p[w:pPr/w:pStyle]';
        }

        // Image: buscar <w:p> que contenha <w:r>/<w:pict> (tratado em findImageByOrder)
        if ($element instanceof \PhpOffice\PhpWord\Element\Image) {
            return '//w:body//w:p[.//w:r/w:pict]';
        }

        // Section: não localiza (não serializado como elemento único)
        // Containers são processados via seus elementos filhos
        
        // Elemento não suportado - lançar exceção descritiva
        $supportedTypes = [
            \PhpOffice\PhpWord\Element\Text::class,
            \PhpOffice\PhpWord\Element\TextRun::class,
            \PhpOffice\PhpWord\Element\Table::class,
            \PhpOffice\PhpWord\Element\Cell::class,
            \PhpOffice\PhpWord\Element\Title::class,
            \PhpOffice\PhpWord\Element\Image::class,
        ];

        // Usar nomes de classe curtos para melhor legibilidade na mensagem de erro
        $shortSupportedTypes = array_map(
            function(string $class): string {
                $lastBackslashPos = strrpos($class, '\\');
                return $lastBackslashPos !== false ? substr($class, $lastBackslashPos + 1) : $class;
            },
            $supportedTypes
        );

        $elementClass = get_class($element);
        $lastBackslashPos = strrpos($elementClass, '\\');
        $elementClassShort = $lastBackslashPos !== false ? substr($elementClass, $lastBackslashPos + 1) : $elementClass;

        throw new \InvalidArgumentException(
            sprintf(
                'Element type "%s" is not supported for Content Controls. Supported types: %s',
                $elementClassShort,
                implode(', ', $shortSupportedTypes)
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
            // Verificar se é Image (contém w:pict)
            if ($this->xpath !== null) {
                $pict = $this->xpath->query('.//w:r/w:pict', $domElement);
                if ($pict !== false && $pict->length > 0) {
                    $pictNode = $pict->item(0);
                    if ($pictNode instanceof DOMElement) {
                        // Processar como imagem
                        $parts[] = 'image';
                        
                        // Extrair dimensões do atributo style do v:shape
                        $shapes = $this->xpath->query('.//v:shape', $pictNode);
                        if ($shapes !== false && $shapes->length > 0) {
                            $shape = $shapes->item(0);
                            if ($shape instanceof DOMElement) {
                                $style = $shape->getAttribute('style');
                                
                                // Parsear width e height do style (formato: "width:100pt; height:100pt;")
                                if (preg_match('/width:\s*([0-9.]+)pt/i', $style, $widthMatch) === 1) {
                                    $parts[] = "width:{$widthMatch[1]}";
                                }
                                if (preg_match('/height:\s*([0-9.]+)pt/i', $style, $heightMatch) === 1) {
                                    $parts[] = "height:{$heightMatch[1]}";
                                }
                                
                                // Nota: Não incluímos o r:id (relationship id) no hash pois ele não
                                // corresponde ao basename do arquivo usado pelo ElementIdentifier e
                                // não pode ser resolvido para o nome do arquivo sem ler document.xml.rels.
                                // Usar apenas width+height é suficiente para identificação única.
                            }
                        }
                        
                        // Retornar hash de imagem
                        $serialized = implode('|', $parts);
                        return substr(md5($serialized), 0, 8);
                    }
                }
                
                // Verificar se é Title (tem w:pStyle)
                $pStyle = $this->xpath->query('.//w:pPr/w:pStyle', $domElement);
                if ($pStyle !== false && $pStyle->length > 0) {
                    $styleNode = $pStyle->item(0);
                    if ($styleNode instanceof DOMElement) {
                        $styleName = $styleNode->getAttribute('w:val');
                        $parts[] = 'title';
                        $parts[] = $styleName;
                        $text = $this->extractTextContent($domElement);
                        $parts[] = $text;
                        // Hash diferente de Text comum
                        $serialized = implode('|', $parts);
                        return substr(md5($serialized), 0, 8);
                    }
                }
            }
            
            // Text/TextRun comum
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
     * Localiza um Title element no DOM por depth e order
     * 
     * Busca Title elements usando o atributo w:pStyle que corresponde
     * ao depth (0=Title, 1=Heading1, 2=Heading2, etc.). Este método
     * usa Reflection para acessar a propriedade privada $depth do Title.
     * 
     * XPath Query Pattern:
     * //w:body/w:p[w:pPr/w:pStyle[@w:val="Heading{depth}"]][not(ancestor::w:sdtContent)][1]
     * 
     * @param \PhpOffice\PhpWord\Element\Title $element O Title element a localizar
     * @param int $order Ordem de registro (0-indexed), ignorado na implementação v3.0.
     *                   Mantido por compatibilidade e possível suporte futuro a múltiplos títulos.
     * @return DOMElement|null O paragraph element localizado, ou null se não encontrado
     * @throws \ReflectionException Se a propriedade depth não puder ser acessada
     * @since 0.1.0
     */
    private function findTitleByDepth(
        \PhpOffice\PhpWord\Element\Title $element,
        int $order
    ): ?DOMElement {
        // NOTE: The $order parameter is intentionally unused.
        // In v3.0, element de-duplication guarantees that only the first
        // matching Title exists (order is always 1). We keep this parameter
        // for interface compatibility with earlier versions and potential
        // future use.
        if ($this->xpath === null) {
            return null;
        }

        // Usar Reflection para acessar $depth privado
        try {
            $reflection = new \ReflectionClass($element);
            $depthProperty = $reflection->getProperty('depth');
            $depthProperty->setAccessible(true);
            $depth = $depthProperty->getValue($element);
            
            // Garantir que depth seja inteiro
            if (!is_int($depth)) {
                throw new \RuntimeException('Title depth must be an integer');
            }
        } catch (\ReflectionException $e) {
            // Fallback: tentar localizar por texto
            return null;
        }

        // Mapear depth para nome de estilo
        $styleName = $depth === 0 ? 'Title' : 'Heading' . $depth;

        // Query XPath para localizar por estilo
        $query = sprintf(
            '//w:body/w:p[w:pPr/w:pStyle[@w:val="%s"]][not(ancestor::w:sdtContent)][1]',
            $styleName
        );

        $nodes = $this->xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);
        return ($node instanceof DOMElement) ? $node : null;
    }

    /**
     * Localiza um Image element no DOM por order
     * 
     * Busca Image elements localizando elementos w:pict dentro de w:r (run) nodes.
     * Suporta imagens inline e floating. Imagens watermark não são suportadas e
     * resultarão em exceção durante o processamento.
     * 
     * XPath Query Pattern:
     * //w:body//w:r/w:pict[not(ancestor::w:sdtContent)][1]
     * 
     * Requer namespaces VML registrados:
     * - v: urn:schemas-microsoft-com:vml
     * - o: urn:schemas-microsoft-com:office:office
     * 
     * @param int $order Ordem de registro (0-indexed), ignorado na implementação v3.0.
     *                   Mantido por compatibilidade e possível suporte futuro a múltiplas imagens.
     * @return DOMElement|null O elemento w:p pai contendo w:pict, ou null se não encontrado
     * @since 0.1.0
     */
    private function findImageByOrder(int $order): ?DOMElement
    {
        // NOTE: The $order parameter is intentionally unused.
        // In v3.0, element de-duplication guarantees that only the first
        // matching Image exists (order is always 1). We keep this parameter
        // for interface compatibility with earlier versions and potential
        // future use.
        if ($this->xpath === null) {
            return null;
        }

        // Query para localizar w:pict (VML images)
        $query = '//w:body//w:r/w:pict[not(ancestor::w:sdtContent)][1]';

        $nodes = $this->xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);
        if (!$node instanceof DOMElement) {
            return null;
        }

        // Retornar o elemento <w:p> pai que contém a imagem
        $parent = $node->parentNode;
        while ($parent !== null && !($parent instanceof DOMElement && $parent->nodeName === 'w:p')) {
            $parent = $parent->parentNode;
        }

        return ($parent instanceof DOMElement) ? $parent : null;
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
            $this->xpath->registerNamespace('v', self::VML_NS);
            $this->xpath->registerNamespace('o', self::OFFICE_NS);
        }

        // Validar tipo
        $expectedNodeName = null;

        if ($phpWordElement instanceof \PhpOffice\PhpWord\Element\Text ||
            $phpWordElement instanceof \PhpOffice\PhpWord\Element\TextRun ||
            $phpWordElement instanceof \PhpOffice\PhpWord\Element\Title ||
            $phpWordElement instanceof \PhpOffice\PhpWord\Element\Image) {
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

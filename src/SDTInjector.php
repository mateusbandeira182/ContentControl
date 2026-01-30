<?php

declare(strict_types=1);

namespace MkGrow\ContentControl;

use MkGrow\ContentControl\Exception\ZipArchiveException;
use MkGrow\ContentControl\Exception\DocumentNotFoundException;

/**
 * Service Layer para injetar Content Controls em arquivos DOCX
 * 
 * Responsável por:
 * - Abrir arquivo DOCX como ZIP
 * - Gerar XML de Content Controls
 * - Injetar XML no document.xml
 * - Atualizar arquivo DOCX
 * 
 * @since 2.0.0
 */
final class SDTInjector
{
    /**
     * Namespace WordprocessingML conforme ISO/IEC 29500-1:2016 §9.3.2.1
     */
    private const WORDML_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Registry de elementos já processados (evita re-wrapping)
     * 
     * @var array<string, true>
     */
    private array $processedElements = [];

    /**
     * Localizador de elementos no DOM
     */
    private ElementLocator $locator;

    /**
     * Cria nova instância do SDTInjector
     */
    public function __construct()
    {
        $this->locator = new ElementLocator();
    }

    /**
     * Injeta Content Controls em arquivo DOCX existente (v3.0 - DOM manipulation)
     * 
     * Workflow v3.0:
     * 1. Abre DOCX como ZIP e lê document.xml
     * 2. Carrega XML em DOMDocument
     * 3. Ordena elementos por profundidade (Cell antes de Table)
     * 4. Para cada elemento:
     *    a. Localiza no DOM usando ElementLocator
     *    b. Envolve inline com wrapElementInline()
     *    c. Marca como processado
     * 5. Serializa DOM modificado de volta para document.xml
     * 6. Atualiza ZIP e salva
     * 
     * @param string $docxPath Caminho do arquivo DOCX
     * @param array<int, array{element: mixed, config: SDTConfig}> $sdtTuples Tuplas elemento→config
     * @return void
     * @throws ZipArchiveException Se falhar ao abrir/manipular ZIP
     * @throws DocumentNotFoundException Se word/document.xml não existir
     * @throws \RuntimeException Se não conseguir localizar elemento no DOM
     */
    public function inject(string $docxPath, array $sdtTuples): void
    {
        $zip = $this->openDocxAsZip($docxPath);

        try {
            // Process document.xml (main body) - REQUIRED
            $this->processXmlFile($zip, 'word/document.xml', $sdtTuples, $docxPath, required: true);
            
            // Process headers and footers (v0.2.0)
            $headerFooterFiles = $this->discoverHeaderFooterFiles($zip);
            foreach ($headerFooterFiles as $xmlPath) {
                $this->processXmlFile($zip, $xmlPath, $sdtTuples, $docxPath, required: false);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Abre arquivo DOCX como ZipArchive
     * 
     * @param string $docxPath Caminho do arquivo DOCX
     * @return \ZipArchive Instância do ZIP aberto
     * @throws ZipArchiveException Se falhar ao abrir
     */
    private function openDocxAsZip(string $docxPath): \ZipArchive
    {
        $zip = new \ZipArchive();
        $openResult = $zip->open($docxPath);
        if ($openResult !== true) {
            throw new ZipArchiveException($openResult, $docxPath);
        }
        return $zip;
    }

    /**
     * Reads XML content from ZIP archive
     * 
     * Generic method to read any XML file from DOCX ZIP.
     * Returns false if file does not exist (allows silent handling).
     * 
     * @param \ZipArchive $zip Opened ZIP archive
     * @param string $xmlPath Path to XML file inside ZIP (e.g., 'word/document.xml')
     * @return string|false XML content or false if file does not exist
     */
    private function readXmlFromZip(\ZipArchive $zip, string $xmlPath): string|false
    {
        return $zip->getFromName($xmlPath);
    }

    /**
     * Updates an XML file in the ZIP archive
     * 
     * Generic method to update any XML file in DOCX ZIP.
     * Deletes old version (if exists) and adds new content.
     * 
     * @param \ZipArchive $zip Opened ZIP archive
     * @param string $xmlPath Path to XML file inside ZIP (e.g., 'word/header1.xml')
     * @param string $xmlContent New XML content to write
     * @return void
     */
    private function updateXmlInZip(\ZipArchive $zip, string $xmlPath, string $xmlContent): void
    {
        // Delete old version (deleteName does not throw if file does not exist)
        $zip->deleteName($xmlPath);
        // Add new content
        $zip->addFromString($xmlPath, $xmlContent);
    }

    /**
     * Carrega document.xml em DOMDocument (v3.0)
     * 
     * @param string $documentXml Conteúdo XML do documento
     * @return \DOMDocument Documento DOM carregado
     * @throws \RuntimeException Se falhar ao carregar XML
     */
    private function loadDocumentAsDom(string $documentXml): \DOMDocument
    {
        libxml_use_internal_errors(true);
        
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        
        // Security: Prevent XXE attacks by disabling network access during XML parsing
        $success = $dom->loadXML(
            $documentXml,
            \LIBXML_NONET
        );
        
        if ($success === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors(false);
            
            $errorMessages = array_map(fn($e) => trim($e->message), $errors);
            throw new \RuntimeException(
                'SDTInjector: Failed to load document.xml as DOM: ' . implode('; ', $errorMessages)
            );
        }
        
        libxml_use_internal_errors(false);
        return $dom;
    }

    /**
     * Processa um elemento: localiza no DOM e envolve com SDT (v3.0)
     * 
     * @param \DOMDocument $dom Documento DOM
     * @param mixed $element Elemento PHPWord
     * @param SDTConfig $config Configuração do Content Control
     * @param int $elementIndex Índice do elemento na ordem de processamento (0-indexed)
     * @param string $rootElement Root element context (w:body, w:hdr, or w:ftr)
     * @return void
     * @throws \RuntimeException Se não conseguir localizar elemento
     */
    private function processElement(
        \DOMDocument $dom,
        mixed $element,
        SDTConfig $config,
        int $elementIndex,
        string $rootElement = 'w:body'
    ): void {
        // Validar que elemento é object
        if (!is_object($element)) {
            throw new \RuntimeException('SDTInjector: Element must be an object');
        }
        
        // Localizar elemento no DOM usando contexto de raiz específico
        $targetElement = $this->locator->findElementInDOM($dom, $element, $elementIndex, $rootElement);
        
        if ($targetElement === null) {
            throw new \RuntimeException(
                'SDTInjector: Could not locate element in DOM tree'
            );
        }
        
        // Verificar se já foi processado (evitar re-wrapping)
        if ($this->isElementProcessed($targetElement)) {
            return; // Já foi envolvido em <w:sdt>, pular
        }
        
        // Envolver elemento inline
        $this->wrapElementInline($targetElement, $config);
    }

    /**
     * Serializa DOMDocument de volta para string XML (v3.0)
     * 
     * @param \DOMDocument $dom Documento DOM modificado
     * @return string XML serializado
     * @throws \RuntimeException Se falhar ao serializar
     */
    private function serializeDocument(\DOMDocument $dom): string
    {
        $xml = $dom->saveXML();
        
        if ($xml === false) {
            throw new \RuntimeException('SDTInjector: Failed to serialize DOM to XML');
        }
        
        return $xml;
    }

    /**
     * Cria elemento XML <w:sdt> completo
     * 
     * NOTA: Método usado em testes unitários via ReflectionMethod (por isso marcado como @used)
     * 
     * @param mixed $element Elemento PHPWord
     * @param SDTConfig $config Configuração do SDT
     * @return string XML do Content Control
     * 
     * @phpstan-ignore-next-line
     */
    private function createSDTElement($element, SDTConfig $config): string
    {
        // Criar documento DOM
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        // Criar elemento raiz <w:sdt> com namespace
        $sdt = $doc->createElementNS(self::WORDML_NAMESPACE, 'w:sdt');
        $doc->appendChild($sdt);

        // Adicionar propriedades (w:sdtPr)
        $sdtPr = $this->createSdtProperties($doc, $config);
        $sdt->appendChild($sdtPr);

        // Adicionar conteúdo (w:sdtContent)
        $sdtContent = $doc->createElement('w:sdtContent');

        // Serializar elementos internos
        $innerXml = $this->serializeElement($element);

        if ($innerXml !== '') {
            // Criar fragment para injetar XML serializado
            $fragment = $doc->createDocumentFragment();
            
            // Suprimir warnings de namespace (já definido no elemento raiz <w:sdt>)
            $previousUseInternalErrors = libxml_use_internal_errors(true);
            $success = $fragment->appendXML($innerXml);

            if ($success === false) {
                // Capturar mensagens de erro para diagnóstico
                $errors = libxml_get_errors();
                libxml_clear_errors();
                libxml_use_internal_errors($previousUseInternalErrors);

                // Filtrar apenas erros reais (não warnings)
                $actualErrors = array_filter($errors, fn($e) => $e->level >= LIBXML_ERR_ERROR);

                $errorMessages = array_map(function($error) {
                    return trim($error->message);
                }, $actualErrors);

                $errorText = count($errorMessages) > 0
                    ? implode('; ', $errorMessages)
                    : 'Unknown error';

                throw new \DOMException(
                    'SDTInjector: Failed to parse inner XML content: ' . $errorText
                );
            }

            // Limpar erros se houver (namespace warnings esperados)
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);

            $sdtContent->appendChild($fragment);
        }

        $sdt->appendChild($sdtContent);

        // Retornar apenas elemento <w:sdt> (sem declaração XML)
        $xml = $doc->saveXML($sdt);

        // saveXML pode retornar false em caso de erro
        if ($xml === false) {
            throw new \DOMException('SDTInjector: Failed to serialize Content Control to XML');
        }

        // Remover declaração de namespace (será herdado do document.xml) de forma robusta
        $cleanXml = preg_replace(
            '/\s+xmlns:w=("|\')' . preg_quote(self::WORDML_NAMESPACE, '/') . '\1/',
            '',
            $xml,
            1
        );

        if ($cleanXml === null) {
            throw new \DOMException('SDTInjector: Failed to remove WordprocessingML namespace from serialized XML');
        }

        return $cleanXml;
    }

    /**
     * Cria elemento <w:sdtPr> com propriedades do Content Control
     * 
     * @param \DOMDocument $doc Documento DOM
     * @param SDTConfig $config Configuração do SDT
     * @return \DOMElement Elemento <w:sdtPr> completo
     */
    private function createSdtProperties(\DOMDocument $doc, SDTConfig $config): \DOMElement
    {
        // Detectar namespace (usar o do documento ou padrão)
        $nsUri = self::WORDML_NAMESPACE;
        
        $sdtPr = $doc->createElementNS($nsUri, 'w:sdtPr');

        // ID (obrigatório) - §17.5.2.14
        $id = $doc->createElementNS($nsUri, 'w:id');
        $id->setAttribute('w:val', $config->id);
        $sdtPr->appendChild($id);

        // Alias (opcional) - §17.5.2.6
        if ($config->alias !== '') {
            $alias = $doc->createElementNS($nsUri, 'w:alias');
            $alias->setAttribute('w:val', $config->alias);
            $sdtPr->appendChild($alias);
        }

        // Tag (opcional) - §17.5.2.33
        if ($config->tag !== '') {
            $tag = $doc->createElementNS($nsUri, 'w:tag');
            $tag->setAttribute('w:val', $config->tag);
            $sdtPr->appendChild($tag);
        }

        // Tipo de Content Control (obrigatório)
        $typeElement = $doc->createElementNS($nsUri, $this->getTypeElementName($config->type));
        $sdtPr->appendChild($typeElement);

        // Lock (condicional) - §17.5.2.23
        if ($config->lockType !== ContentControl::LOCK_NONE) {
            $lock = $doc->createElementNS($nsUri, 'w:lock');
            $lock->setAttribute('w:val', $config->lockType);
            $sdtPr->appendChild($lock);
        }

        return $sdtPr;
    }

    /**
     * Retorna nome do elemento XML para o tipo de Content Control
     * 
     * @param string $type Tipo do controle (ContentControl::TYPE_*)
     * @return string Nome do elemento (com prefixo w:)
     */
    private function getTypeElementName(string $type): string
    {
        return match($type) {
            ContentControl::TYPE_GROUP => 'w:group',
            ContentControl::TYPE_PLAIN_TEXT => 'w:text',
            ContentControl::TYPE_RICH_TEXT => 'w:richText',
            ContentControl::TYPE_PICTURE => 'w:picture',
            default => 'w:richText',
        };
    }

    /**
     * Serializa elemento PHPWord para XML
     * 
     * @param mixed $element Elemento a serializar
     * @return string XML serializado
     */
    private function serializeElement($element): string
    {
        // Se não é um elemento PHPWord válido, retornar vazio
        if (!$element instanceof \PhpOffice\PhpWord\Element\AbstractElement) {
            return '';
        }

        // Criar XMLWriter em modo memória
        $xmlWriter = new \PhpOffice\PhpWord\Shared\XMLWriter(
            \PhpOffice\PhpWord\Shared\XMLWriter::STORAGE_MEMORY,
            null,
            false
        );
        $xmlWriter->openMemory();

        // Se é um container (Section, Header, Footer), serializar seus elementos
        // AbstractContainer estende AbstractElement, então este check é válido
        if ($element instanceof \PhpOffice\PhpWord\Element\AbstractContainer) {
            foreach ($element->getElements() as $childElement) {
                $this->writeElement($xmlWriter, $childElement);
            }
        } else {
            // Se é um elemento único (não container), serializar diretamente
            $this->writeElement($xmlWriter, $element);
        }

        return $xmlWriter->getData();
    }

    /**
     * Escreve elemento PHPWord usando Writer correspondente
     * 
     * @param \PhpOffice\PhpWord\Shared\XMLWriter $xmlWriter Writer XML
     * @param \PhpOffice\PhpWord\Element\AbstractElement $element Elemento a serializar
     * @return void
     */
    private function writeElement(
        \PhpOffice\PhpWord\Shared\XMLWriter $xmlWriter,
        \PhpOffice\PhpWord\Element\AbstractElement $element
    ): void {
        // Extrair nome da classe do elemento
        $className = get_class($element);
        $lastBackslashPos = strrpos($className, '\\');

        if ($lastBackslashPos === false) {
            return; // Classe inválida, ignorar
        }

        $elementClass = substr($className, $lastBackslashPos + 1);

        // Containers não devem ser serializados diretamente
        if (in_array($elementClass, ['Section', 'Header', 'Footer', 'Cell'], true)) {
            return;
        }

        // Montar nome da classe Writer
        $writerClass = "PhpOffice\\PhpWord\\Writer\\Word2007\\Element\\{$elementClass}";

        // Verificar se Writer existe
        if (!class_exists($writerClass)) {
            return; // Elemento não suportado - ignorar
        }

        // Determinar se elemento precisa de wrapper <w:p>
        $needsParagraphWrapper = $this->needsParagraphWrapper($element);
        $withoutParagraphWrapper = !$needsParagraphWrapper;

        // Instanciar Writer e serializar
        /** @var \PhpOffice\PhpWord\Writer\Word2007\Element\AbstractElement $writer */
        $writer = new $writerClass($xmlWriter, $element, $withoutParagraphWrapper);
        $writer->write();
    }

    /**
     * Verifica se elemento PHPWord precisa de wrapper <w:p>
     * 
     * @param \PhpOffice\PhpWord\Element\AbstractElement $element Elemento PHPWord
     * @return bool true se precisa de wrapper, false caso contrário
     */
    private function needsParagraphWrapper(\PhpOffice\PhpWord\Element\AbstractElement $element): bool
    {
        return !(
            $element instanceof \PhpOffice\PhpWord\Element\Table ||
            $element instanceof \PhpOffice\PhpWord\Element\PageBreak ||
            $element instanceof \PhpOffice\PhpWord\Element\Section ||
            $element instanceof \PhpOffice\PhpWord\Element\Header ||
            $element instanceof \PhpOffice\PhpWord\Element\Footer
        );
    }

    /**
     * Envolve elemento DOM inline com estrutura <w:sdt>
     * 
     * Workflow:
     * 1. Criar <w:sdt><w:sdtPr>...</w:sdtPr><w:sdtContent></w:sdtContent></w:sdt>
     * 2. Inserir SDT antes do elemento original no DOM tree
     * 3. MOVER elemento para dentro de <w:sdtContent> (appendChild move o nó)
     * 4. Marcar como processado
     * 
     * IMPORTANTE: appendChild() em nó existente = MOVE (não duplica)
     * 
     * @param \DOMElement $targetElement Elemento DOM a envolver
     * @param SDTConfig $config Configuração do Content Control
     * @return void
     * @throws \RuntimeException Se elemento não tiver parent ou owner document
     */
    private function wrapElementInline(\DOMElement $targetElement, SDTConfig $config): void
    {
        // Validar pre-condições
        $dom = $targetElement->ownerDocument;
        if ($dom === null) {
            throw new \RuntimeException('SDTInjector: Target element has no owner document');
        }

        $parent = $targetElement->parentNode;
        if ($parent === null) {
            throw new \RuntimeException('SDTInjector: Target element has no parent node');
        }

        // Detectar namespace do documento (normalmente está no elemento root ou parent)
        $nsUri = $targetElement->namespaceURI;
        if ($nsUri === null || $nsUri === '') {
            $nsUri = self::WORDML_NAMESPACE;
        }

        // 1. Criar estrutura <w:sdt> COM namespace
        $sdt = $dom->createElementNS($nsUri, 'w:sdt');
        
        // 2. Adicionar propriedades <w:sdtPr>
        $sdtPr = $this->createSdtProperties($dom, $config);
        $sdt->appendChild($sdtPr);
        
        // 3. Criar container <w:sdtContent> COM namespace
        $sdtContent = $dom->createElementNS($nsUri, 'w:sdtContent');
        
        // 4. Inserir SDT ANTES do elemento original
        $parent->insertBefore($sdt, $targetElement);
        
        // 5. MOVER elemento para dentro de <w:sdtContent>
        // ⚠️ IMPORTANTE: appendChild() MOVE o nó (não duplica)
        $sdtContent->appendChild($targetElement);
        
        // 6. Completar estrutura SDT
        $sdt->appendChild($sdtContent);
        
        // 7. Marcar como processado
        $this->markElementAsProcessed($targetElement);
    }

    /**
     * Verifica se elemento já foi processado (evita re-wrapping)
     * 
     * @param \DOMElement $element Elemento DOM a verificar
     * @return bool true se já processado
     */
    private function isElementProcessed(\DOMElement $element): bool
    {
        $path = $element->getNodePath();
        return isset($this->processedElements[$path]);
    }

    /**
     * Marca elemento como processado
     * 
     * Usa NodePath como chave única (ex: "/w:body[1]/w:sdt[1]/w:sdtContent[1]/w:p[1]")
     * 
     * @param \DOMElement $element Elemento DOM a marcar
     * @return void
     */
    private function markElementAsProcessed(\DOMElement $element): void
    {
        $path = $element->getNodePath();
        $this->processedElements[$path] = true;
    }

    /**
     * Ordena elementos por profundidade decrescente (depth-first)
     * 
     * Elementos mais profundos (Cell) são processados antes de elementos
     * mais rasos (Table), evitando re-wrapping de elementos já envolvidos.
     * 
     * Usa ordenação estável: quando profundidades iguais, mantém ordem original.
     * 
     * @param array<int, array{element: mixed, config: SDTConfig}> $sdtTuples
     * @return array<int, array{element: mixed, config: SDTConfig}> Tuplas ordenadas
     */
    private function sortElementsByDepth(array $sdtTuples): array
    {
        // Adicionar índice original para ordenação estável
        $withIndex = array_map(function ($tuple, $index) {
            return ['tuple' => $tuple, 'originalIndex' => $index];
        }, $sdtTuples, array_keys($sdtTuples));

        usort($withIndex, function ($a, $b) {
            $depthA = $this->getElementDepth($a['tuple']['element']);
            $depthB = $this->getElementDepth($b['tuple']['element']);
            
            // Ordenar decrescente (mais profundo primeiro)
            $depthComparison = $depthB <=> $depthA;
            
            // Se profundidades iguais, manter ordem original (estabilidade)
            if ($depthComparison === 0) {
                return $a['originalIndex'] <=> $b['originalIndex'];
            }
            
            return $depthComparison;
        });
        
        // Extrair tuplas ordenadas
        return array_map(fn($item) => $item['tuple'], $withIndex);
    }

    /**
     * Processes a single XML file (document.xml, header*.xml, footer*.xml)
     * 
     * Generic workflow:
     * 1. Read XML from ZIP (silently skip if not found, unless required)
     * 2. Load XML as DOMDocument
     * 3. Filter elements belonging to this XML file
     * 4. Sort elements by depth (depth-first processing)
     * 5. Process each element (locate in DOM and wrap with SDT)
     * 6. Serialize modified DOM and update ZIP
     * 
     * @param \ZipArchive $zip Opened ZIP archive
     * @param string $xmlPath Path to XML file (e.g., 'word/document.xml')
     * @param array<int, array{element: mixed, config: SDTConfig}> $sdtTuples All SDT tuples
     * @param string $docxPath DOCX path (for error messages)
     * @param bool $required Whether this XML file is required (throws if not found)
     * @return void
     * @throws DocumentNotFoundException If required file is not found
     * @throws \RuntimeException If XML loading or processing fails
     */
    private function processXmlFile(
        \ZipArchive $zip,
        string $xmlPath,
        array $sdtTuples,
        string $docxPath,
        bool $required = false
    ): void {
        // 1. Read XML from ZIP
        $xmlContent = $this->readXmlFromZip($zip, $xmlPath);
        
        // Handle missing file
        if ($xmlContent === false) {
            if ($required) {
                throw new DocumentNotFoundException($xmlPath, $docxPath);
            }
            // Silently skip optional files (headers/footers)
            return;
        }
        
        // 2. Load XML as DOMDocument
        $dom = $this->loadDocumentAsDom($xmlContent);
        
        // 2.5. Detect root element type (w:body, w:hdr, or w:ftr)
        $rootElement = $this->locator->detectRootElement($dom);
        
        // 3. Filter elements belonging to this XML file
        $filteredTuples = $this->filterElementsByXmlFile($sdtTuples, $xmlPath);
        
        // Skip processing if no elements belong to this file
        if (count($filteredTuples) === 0) {
            return;
        }
        
        // 4. Sort elements by depth (depth-first)
        $sortedTuples = $this->sortElementsByDepth($filteredTuples);
        
        // 5. Process each element with appropriate root context
        foreach ($sortedTuples as $index => $tuple) {
            $this->processElement($dom, $tuple['element'], $tuple['config'], $index, $rootElement);
        }
        
        // 6. Serialize modified DOM
        $modifiedXml = $this->serializeDocument($dom);
        
        // 7. Update XML in ZIP
        $this->updateXmlInZip($zip, $xmlPath, $modifiedXml);
    }

    /**
     * Calcula profundidade do elemento na hierarquia PHPWord
     * 
     * Profundidades:
     * - Cell: 3 (dentro de Row dentro de Table)
     * - Table: 1
     * - Section: 1
     * - Text/TextRun: 1 (dentro de Section ou Cell)
     * 
     * @param mixed $element Elemento PHPWord
     * @return int Profundidade (valores maiores = mais profundo)
     */
    private function getElementDepth($element): int
    {
        // Cell é o mais profundo (dentro de Row dentro de Table)
        if ($element instanceof \PhpOffice\PhpWord\Element\Cell) {
            return 3;
        }

        // Table
        if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
            return 1;
        }

        // Section
        if ($element instanceof \PhpOffice\PhpWord\Element\Section) {
            return 1;
        }

        // Text, TextRun, Image (dentro de container)
        // Para simplicidade, considerar depth 1 (mesma prioridade que Section)
        return 1;
    }

    /**
     * Discovers header*.xml and footer*.xml files in DOCX ZIP
     * 
     * Returns sorted list of XML file paths found in the archive.
     * Files are sorted alphabetically for predictable processing order.
     * 
     * @param \ZipArchive $zip Opened ZIP archive
     * @return array<int, string> List of header/footer XML paths (e.g., ['word/footer1.xml', 'word/header1.xml'])
     */
    private function discoverHeaderFooterFiles(\ZipArchive $zip): array
    {
        $files = [];
        $numFiles = $zip->numFiles;

        for ($i = 0; $i < $numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (!is_string($filename)) {
                continue;
            }

            // Match word/header*.xml or word/footer*.xml
            if (preg_match('#^word/(header|footer)\d+\.xml$#', $filename) === 1) {
                $files[] = $filename;
            }
        }

        // Sort alphabetically for predictable order
        sort($files);

        return $files;
    }

    /**
     * Determines which XML file an element belongs to
     * 
     * Uses PHPWord's internal docPart property to determine location:
     * - docPart='Header' → word/header*.xml
     * - docPart='Footer' → word/footer*.xml
     * - docPart='Section' or other → word/document.xml
     * 
     * Also uses docPartId to map to specific header/footer number.
     * 
     * @param object $element PHPWord element instance
     * @return string XML path where element should be processed
     */
    private function getXmlFileForElement(object $element): string
    {
        try {
            $reflection = new \ReflectionClass($element);

            // Try to get docPart property (available in most PHPWord elements)
            if ($reflection->hasProperty('docPart')) {
                $docPartProp = $reflection->getProperty('docPart');
                $docPartProp->setAccessible(true);
                $docPart = $docPartProp->getValue($element);

                // Get docPartId if available
                $docPartId = 1; // Default
                if ($reflection->hasProperty('docPartId')) {
                    $docPartIdProp = $reflection->getProperty('docPartId');
                    $docPartIdProp->setAccessible(true);
                    $docPartIdValue = $docPartIdProp->getValue($element);
                    if (is_int($docPartIdValue)) {
                        $docPartId = $docPartIdValue;
                    }
                }

                // Map docPart to XML file
                if ($docPart === 'Header') {
                    return 'word/header' . $docPartId . '.xml';
                } elseif ($docPart === 'Footer') {
                    return 'word/footer' . $docPartId . '.xml';
                }
            }
        } catch (\ReflectionException $e) {
            // Reflection failed, assume body
        }

        // Default to document.xml (main body)
        return 'word/document.xml';
    }

    /**
     * Filters SDT tuples to only include elements from specific XML file
     * 
     * Uses getXmlFileForElement() to determine each element's location.
     * 
     * @param array<int, array{element: mixed, config: SDTConfig}> $sdtTuples All SDT tuples
     * @param string $xmlPath Target XML file path (e.g., 'word/header1.xml')
     * @return array<int, array{element: mixed, config: SDTConfig}> Filtered tuples
     */
    private function filterElementsByXmlFile(array $sdtTuples, string $xmlPath): array
    {
        $filtered = [];

        foreach ($sdtTuples as $tuple) {
            // Skip if element is not an object
            if (!is_object($tuple['element'])) {
                continue;
            }
            
            $elementXmlPath = $this->getXmlFileForElement($tuple['element']);

            if ($elementXmlPath === $xmlPath) {
                $filtered[] = $tuple;
            }
        }

        return $filtered;
    }
}

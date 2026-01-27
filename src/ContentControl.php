<?php

namespace MkGrow\ContentControl;

use PhpOffice\PhpWord\Element\AbstractContainer;
use DOMDocument;
use DOMElement;
use PhpOffice\PhpWord\Shared\XMLWriter;

/**
 * Content Control (Structured Document Tag) para documentos Word OOXML
 * 
 * Estende PHPWord AbstractContainer para envolver elementos em
 * Content Controls conforme ISO/IEC 29500-1:2016 §17.5.2
 * 
 * @property \PhpOffice\PhpWord\Element\AbstractElement[] $elements Elementos do container (herdado)
 */
class ContentControl extends AbstractContainer
{
    // ==================== CONSTANTES DE TIPO ====================
    /**
     * Content Control tipo Group - Agrupa elementos sem permitir edição
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.15
     * Elemento XML: <w:group/>
     * 
     * @var string
     */
    public const TYPE_GROUP = 'group';

    /**
     * Content Control tipo Plain Text - Texto simples sem formatação
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.34
     * Elemento XML: <w:text/>
     * 
     * @var string
     */
    public const TYPE_PLAIN_TEXT = 'plainText';

    /**
     * Content Control tipo Rich Text - Texto com formatação completa
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.31
     * Elemento XML: <w:richText/>
     * 
     * @var string
     */
    public const TYPE_RICH_TEXT = 'richText';

    /**
     * Content Control tipo Picture - Controle para imagens
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.27
     * Elemento XML: <w:picture/>
     * 
     * @var string
     */
    public const TYPE_PICTURE = 'picture';

    // ==================== CONSTANTES DE LOCK ====================
    /**
     * Sem bloqueio - Content Control pode ser editado e deletado
     * 
     * Especificação: Valor padrão quando <w:lock> ausente
     * 
     * @var string
     */
    public const LOCK_NONE = '';

    /**
     * Content Control bloqueado - Não pode ser deletado, mas conteúdo é editável
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.23 Table 17-21
     * Valor: sdtLocked
     * 
     * @var string
     */
    public const LOCK_SDT_LOCKED = 'sdtLocked';

    /**
     * Conteúdo bloqueado - Content Control pode ser deletado, mas conteúdo não é editável
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.23 Table 17-21
     * Valor: sdtContentLocked
     * 
     * @var string
     */
    public const LOCK_CONTENT_LOCKED = 'sdtContentLocked';

    /**
     * Desbloqueado explicitamente
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.23 Table 17-21
     * Valor: unlocked
     * 
     * @var string
     */
    public const LOCK_UNLOCKED = 'unlocked';

    // ==================== NAMESPACE OOXML ====================
    /**
     * Namespace WordprocessingML conforme ISO/IEC 29500-1:2016 §9.3.2.1
     * 
     * @var string
     */
    private const WORDML_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    // ==================== PROPRIEDADES ====================
    
    /**
     * Identificador único do Content Control (8 dígitos)
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.14
     * Elemento XML: <w:id w:val="12345678"/>
     * 
     * @var string
     */
    private string $id;

    /**
     * Nome amigável do Content Control (exibido no Word)
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.6
     * Elemento XML: <w:alias w:val="Nome do Cliente"/>
     * 
     * @var string
     */
    private string $alias;

    /**
     * Tag de metadados para identificação programática
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.33
     * Elemento XML: <w:tag w:val="customer-name"/>
     * 
     * @var string
     */
    private string $tag;

    /**
     * Tipo do Content Control
     * 
     * Valores permitidos: TYPE_GROUP, TYPE_PLAIN_TEXT, TYPE_RICH_TEXT, TYPE_PICTURE
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2 (diversos elementos)
     * 
     * @var string
     */
    private string $type;

    /**
     * Nível de bloqueio do Content Control
     * 
     * Valores permitidos: LOCK_NONE, LOCK_SDT_LOCKED, LOCK_CONTENT_LOCKED, LOCK_UNLOCKED
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.23
     * 
     * @var string
     */
    private string $lockType;

    /**
     * Cache de existência de classes Writer para evitar class_exists repetidos
     * 
     * @var array<string, bool>
     */
    private static array $writerCache = [];

    /**
     * Cria novo Content Control envolvendo um container PHPWord
     * 
     * @param AbstractContainer $content Container PHPWord (Section, TextRun, etc)
     * @param array{
     *     id?: string,
     *     alias?: string,
     *     tag?: string,
     *     type?: string,
     *     lockType?: string
     * } $options Configurações do Content Control
     * 
     * Opções:
     * - id: Identificador único (gerado automaticamente se omitido)
     * - alias: Nome amigável exibido no Word
     * - tag: Tag de metadados para identificação programática
     * - type: Tipo do controle (padrão: TYPE_RICH_TEXT)
     * - lockType: Nível de bloqueio (padrão: LOCK_NONE)
     * 
     * @throws \InvalidArgumentException Se type ou lockType inválidos
     * 
     * @example
     * ```php
     * $section = $phpWord->addSection();
     * $section->addText('Conteúdo protegido');
     * 
     * $control = new ContentControl($section, [
     *     'alias' => 'Nome do Cliente',
     *     'tag' => 'customer-name',
     *     'type' => ContentControl::TYPE_RICH_TEXT,
     *     'lockType' => ContentControl::LOCK_SDT_LOCKED
     * ]);
     * ```
     */
    public function __construct(
        AbstractContainer $content,
        array $options = []
    )
    {
        // Validar opções ANTES de atribuir
        $this->validateOptions($options);

        // Atribuir propriedades com defaults
        $this->id = isset($options['id']) ? $this->validateId($options['id']) : $this->generateId();
        $this->alias = $options['alias'] ?? '';
        $this->tag = $options['tag'] ?? '';
        $this->type = $options['type'] ?? self::TYPE_RICH_TEXT;
        $this->lockType = $options['lockType'] ?? self::LOCK_NONE;

        // Copiar elementos do container fonte para este Content Control
        foreach ($content->getElements() as $element) {
            $this->elements[] = $element;
        }
    }

    /**
     * Valida array de opções do construtor
     * 
     * Lança InvalidArgumentException se:
     * - type não está na lista de TYPE_* constantes
     * - lockType não está na lista de LOCK_* constantes
     * - alias contém caracteres problemáticos ou excede limite de comprimento
     * - tag contém caracteres problemáticos ou excede limite de comprimento
     * 
     * @param array<string, mixed> $options
     * @throws \InvalidArgumentException
     * @return void
     */
    private function validateOptions(array $options): void
    {
        $validTypes = [
            self::TYPE_GROUP,
            self::TYPE_PLAIN_TEXT,
            self::TYPE_RICH_TEXT,
            self::TYPE_PICTURE,
        ];

        $validLockTypes = [
            self::LOCK_NONE,
            self::LOCK_SDT_LOCKED,
            self::LOCK_CONTENT_LOCKED,
            self::LOCK_UNLOCKED,
        ];

        // Validar type se fornecido
        if (isset($options['type']) && !in_array($options['type'], $validTypes, true)) {
            $invalidType = is_scalar($options['type']) ? (string) $options['type'] : gettype($options['type']);
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid type "%s". Must be one of: %s',
                    $invalidType,
                    implode(', ', $validTypes)
                )
            );
        }

        // Validar lockType se fornecido
        if (isset($options['lockType']) && !in_array($options['lockType'], $validLockTypes, true)) {
            $invalidLockType = is_scalar($options['lockType']) ? (string) $options['lockType'] : gettype($options['lockType']);
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid lock type "%s". Must be one of: %s',
                    $invalidLockType,
                    implode(', ', $validLockTypes)
                )
            );
        }

        // Validar alias se fornecido
        if (isset($options['alias']) && $options['alias'] !== '') {
            $this->validateAlias($options['alias']);
        }

        // Validar tag se fornecido
        if (isset($options['tag']) && $options['tag'] !== '') {
            $this->validateTag($options['tag']);
        }

        // Validar id se fornecido
        if (isset($options['id'])) {
            $this->validateId($options['id']);
        }
    }

    /**
     * Valida o valor do alias
     * 
     * O alias é um nome amigável exibido no Word. Esta validação garante:
     * - Comprimento máximo de 255 caracteres (limite prático para exibição)
     * - Não contém caracteres de controle (0x00-0x1F, 0x7F-0x9F)
     * 
     * @param mixed $alias Valor a ser validado
     * @throws \InvalidArgumentException Se alias inválido
     * @return void
     */
    private function validateAlias($alias): void
    {
        if (!is_string($alias)) {
            throw new \InvalidArgumentException(
                sprintf('Alias must be a string, %s given', gettype($alias))
            );
        }

        // Limite de comprimento razoável para exibição
        $length = mb_strlen($alias, 'UTF-8');
        if ($length > 255) {
            throw new \InvalidArgumentException(
                sprintf('Alias must not exceed 255 characters, got %d characters', $length)
            );
        }

        // Verificar caracteres de controle que podem causar problemas
        // Bloqueia C0 controls (0x00-0x1F) e C1 controls (0x7F-0x9F)
        // Usa modificador 'u' para suporte correto a UTF-8
        if (preg_match('/[\x00-\x1F\x7F-\x9F]/u', $alias) === 1) {
            throw new \InvalidArgumentException(
                'Alias must not contain control characters'
            );
        }

        // Verificar caracteres reservados XML que podem causar problemas de parsing
        if (preg_match('/[<>&"\']/', $alias) === 1) {
            throw new \InvalidArgumentException(
                'ContentControl: Alias contains XML reserved characters'
            );
        }
    }

    /**
     * Valida o valor da tag
     * 
     * A tag é um identificador de metadados para uso programático. Esta validação garante:
     * - Comprimento máximo de 255 caracteres
     * - Apenas caracteres alfanuméricos, hífens, underscores e pontos
     * - Deve começar com letra ou underscore (convenção de identificadores)
     * 
     * @param mixed $tag Valor a ser validado
     * @throws \InvalidArgumentException Se tag inválida
     * @return void
     */
    private function validateTag($tag): void
    {
        if (!is_string($tag)) {
            throw new \InvalidArgumentException(
                sprintf('Tag must be a string, %s given', gettype($tag))
            );
        }

        // Limite de comprimento
        $length = mb_strlen($tag, 'UTF-8');
        if ($length > 255) {
            throw new \InvalidArgumentException(
                sprintf('Tag must not exceed 255 characters, got %d characters', $length)
            );
        }

        // Tag deve seguir padrão de identificador: começa com letra ou _, depois alfanumérico, -, _, .
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]*$/', $tag) !== 1) {
            throw new \InvalidArgumentException(
                'Tag must start with a letter or underscore and contain only alphanumeric characters, hyphens, underscores, and periods'
            );
        }

        // Verificar caracteres reservados XML que podem causar problemas de parsing
        if (preg_match('/[<>&"\']/', $tag) === 1) {
            throw new \InvalidArgumentException(
                'ContentControl: Tag contains XML reserved characters'
            );
        }
    }

    /**
     * Valida e normaliza o ID do Content Control
     * 
     * O ID deve ser um número de 8 dígitos entre 10000000 e 99999999.
     * Aceita tanto string quanto int como entrada.
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.14
     * 
     * @param mixed $id Valor a ser validado (string ou int)
     * @throws \InvalidArgumentException Se ID inválido
     * @return string ID validado como string de 8 dígitos
     */
    private function validateId($id): string
    {
        // Validar tipo
        if (!is_string($id) && !is_int($id)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'ContentControl: Invalid ID type. Expected string or int, got %s',
                    gettype($id)
                )
            );
        }

        // Converter para string se necessário
        $idString = (string) $id;

        // Validar formato (8 dígitos)
        if (preg_match('/^\d{8}$/', $idString) !== 1) {
            throw new \InvalidArgumentException(
                sprintf(
                    'ContentControl: Invalid ID format. Must be 8 digits, got "%s"',
                    $idString
                )
            );
        }

        // Validar range (10000000 - 99999999)
        $idInt = (int) $idString;
        if ($idInt < 10000000 || $idInt > 99999999) {
            throw new \InvalidArgumentException(
                sprintf(
                    'ContentControl: Invalid ID range. Must be between 10000000 and 99999999, got %d',
                    $idInt
                )
            );
        }

        return $idString;
    }

    /**
     * Gera ID único de 8 dígitos para o Content Control
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.14
     * Formato: Inteiro decimal de 8 dígitos (10000000 - 99999999)
     * 
     * @return string
     */
    private function generateId(): string
    {
        return (string) random_int(10000000, 99999999);
    }

    /**
     * Cria elemento <w:sdtPr> com propriedades do Content Control
     * 
     * Estrutura conforme ISO/IEC 29500-1:2016 §17.5.2.32:
     * - <w:id> - Identificador único (obrigatório)
     * - <w:alias> - Nome amigável (opcional)
     * - <w:tag> - Tag de metadados (opcional)
     * - <w:group|text|richText|picture> - Tipo (obrigatório)
     * - <w:lock> - Bloqueio (condicional)
     * 
     * @param \DOMDocument $doc Documento DOM para criar elementos
     * @return \DOMElement Elemento <w:sdtPr> completo
     */
    private function createSdtProperties(\DOMDocument $doc): \DOMElement
    {
        $sdtPr = $doc->createElement('w:sdtPr');
        
        // ID (obrigatório) - §17.5.2.14
        $id = $doc->createElement('w:id');
        $id->setAttribute('w:val', $this->id);
        $sdtPr->appendChild($id);
        
        // Alias (opcional) - §17.5.2.6
        if ($this->alias !== '') {
            $alias = $doc->createElement('w:alias');
            $alias->setAttribute('w:val', $this->alias);
            $sdtPr->appendChild($alias);
        }
        
        // Tag (opcional) - §17.5.2.33
        if ($this->tag !== '') {
            $tag = $doc->createElement('w:tag');
            $tag->setAttribute('w:val', $this->tag);
            $sdtPr->appendChild($tag);
        }
        
        // Tipo de Content Control (obrigatório)
        $typeElement = $doc->createElement($this->getTypeElementName());
        $sdtPr->appendChild($typeElement);
        
        // Lock (condicional) - §17.5.2.23
        if ($this->lockType !== self::LOCK_NONE) {
            $lock = $doc->createElement('w:lock');
            $lock->setAttribute('w:val', $this->lockType);
            $sdtPr->appendChild($lock);
        }
        
        return $sdtPr;
    }

    /**
     * Retorna nome do elemento XML para o tipo de Content Control
     * 
     * Mapeamento conforme ISO/IEC 29500-1:2016:
     * - TYPE_GROUP → <w:group/> (§17.5.2.15)
     * - TYPE_PLAIN_TEXT → <w:text/> (§17.5.2.34)
     * - TYPE_RICH_TEXT → <w:richText/> (§17.5.2.31)
     * - TYPE_PICTURE → <w:picture/> (§17.5.2.27)
     * 
     * @return string Nome do elemento (com prefixo w:)
     */
    private function getTypeElementName(): string
    {
        return match($this->type) {
            self::TYPE_GROUP => 'w:group',
            self::TYPE_PLAIN_TEXT => 'w:text',
            self::TYPE_RICH_TEXT => 'w:richText',
            self::TYPE_PICTURE => 'w:picture',
            default => 'w:richText',
        };
    }

    /**
     * Gera XML OOXML do Content Control conforme ISO/IEC 29500-1:2016 §17.5.2
     * 
     * Estrutura gerada:
     * <w:sdt xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
     *   <w:sdtPr>
     *     <w:id w:val="12345678"/>
     *     <w:alias w:val="Nome Amigável"/>
     *     <w:tag w:val="metadata-tag"/>
     *     <w:richText/>
     *     <w:lock w:val="sdtLocked"/>
     *   </w:sdtPr>
     *   <w:sdtContent>
     *     {conteúdo serializado}
     *   </w:sdtContent>
     * </w:sdt>
     * 
     * @return string XML do Content Control (sem declaração <?xml)
     * @throws \DOMException Se XML mal formado
     */
    public function getXml(): string
    {
        // Criar documento DOM
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;
        
        // Criar elemento raiz <w:sdt> com namespace WordprocessingML
        $sdt = $doc->createElementNS(self::WORDML_NAMESPACE, 'w:sdt');
        $doc->appendChild($sdt);
        
        // Adicionar propriedades (w:sdtPr)
        $sdtPr = $this->createSdtProperties($doc);
        $sdt->appendChild($sdtPr);
        
        // Adicionar conteúdo (w:sdtContent)
        $sdtContent = $doc->createElement('w:sdtContent');
        
        // Serializar elementos internos com XMLWriter
        $innerXml = $this->serializeInnerContent();
        
        if ($innerXml !== '') {
            // Criar fragment para injetar XML serializado
            $fragment = $doc->createDocumentFragment();
            // Suprimir warning de namespace (já definido no elemento raiz <w:sdt>)
            // O XMLWriter do PHPWord não inclui namespace em elementos, mas herdarão
            // do elemento pai quando integrados ao documento
            $previousUseInternalErrors = libxml_use_internal_errors(true);
            $success = $fragment->appendXML($innerXml);
            
            // Verificar se appendXML falhou
            // Namespace warnings são esperados e podem ser ignorados
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
                    'Failed to parse inner XML content: ' . $errorText
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
        // Assertion garante type narrowing para PHPStan Level 9
        assert($xml !== false, 'Failed to serialize Content Control to XML');
        
        return $xml;
    }

    /**
     * Serializa elementos internos do Content Control usando PHPWord Writers
     * 
     * Detecta tipo de elemento e aplica wrapper <w:p> apenas quando necessário:
     * - Table: SEM wrapper (serializa diretamente como <w:tbl>)
     * - PageBreak: SEM wrapper (gera próprio <w:p/>)
     * - Text, TextRun, Image: COM wrapper (requerem <w:p> container)
     * 
     * @return string XML dos elementos serializados
     */
    private function serializeInnerContent(): string
    {
        if (count($this->elements) === 0) {
            return '';
        }
        
        // Criar XMLWriter em modo memória
        $xmlWriter = new \PhpOffice\PhpWord\Shared\XMLWriter(
            \PhpOffice\PhpWord\Shared\XMLWriter::STORAGE_MEMORY,
            null,
            false
        );
        $xmlWriter->openMemory();
        
        // Serializar cada elemento usando PHPWord Writer Pattern
        foreach ($this->elements as $element) {
            $this->writeElement($xmlWriter, $element);
        }
        
        return $xmlWriter->getData();
    }

    /**
     * Escreve elemento PHPWord usando Writer correspondente
     * 
     * Detecta classe do elemento e instancia Writer apropriado:
     * - PhpOffice\PhpWord\Element\Text → PhpOffice\PhpWord\Writer\Word2007\Element\Text
     * - PhpOffice\PhpWord\Element\Table → PhpOffice\PhpWord\Writer\Word2007\Element\Table
     * - etc.
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
        
        // strrpos pode retornar false se não encontrar, mas toda classe PHPWord tem namespace
        if ($lastBackslashPos === false) {
            return; // Classe inválida, ignorar
        }
        
        $elementClass = substr($className, $lastBackslashPos + 1);
        
        // Containers (Section, Header, Footer) não devem ser serializados diretamente
        if (in_array($elementClass, ['Section', 'Header', 'Footer', 'Cell'], true)) {
            return;
        }
        
        // Montar nome da classe Writer
        $writerClass = "PhpOffice\\PhpWord\\Writer\\Word2007\\Element\\{$elementClass}";
        
        // Verificar se Writer existe (com cache)
        self::$writerCache[$writerClass] ??= class_exists($writerClass);
        if (!self::$writerCache[$writerClass]) {
            // Elemento não suportado - ignorar silenciosamente
            return;
        }
        
        // Determinar se elemento precisa de wrapper <w:p>
        $needsParagraphWrapper = $this->needsParagraphWrapper($element);

        // Writer espera um flag "sem wrapper de parágrafo" (true = não gerar <w:p>)
        $withoutParagraphWrapper = !$needsParagraphWrapper;
        
        // Instanciar Writer e serializar
        /** @var \PhpOffice\PhpWord\Writer\Word2007\Element\AbstractElement $writer */
        $writer = new $writerClass($xmlWriter, $element, $withoutParagraphWrapper);
        $writer->write();
    }

    /**
     * Verifica se elemento PHPWord precisa de wrapper <w:p>
     * 
     * Elementos que NÃO precisam de wrapper:
     * - Table: Serializa como <w:tbl> (já é block-level)
     * - PageBreak: Gera próprio <w:p/> vazio
     * - Section: Container não serializável
     * 
     * Elementos que PRECISAM de wrapper:
     * - Text: Requer <w:p><w:r><w:t>texto</w:t></w:r></w:p>
     * - TextRun: Requer <w:p> externo
     * - Image: Requer <w:p><w:r><w:drawing>...</w:drawing></w:r></w:p>
     * - Link: Similar a Text
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
}
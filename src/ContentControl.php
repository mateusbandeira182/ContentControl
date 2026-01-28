<?php

declare(strict_types=1);

namespace MkGrow\ContentControl;

use PhpOffice\Math\Element\AbstractElement;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory as PHPWordIOFactory;

/**
 * ContentControl v2.0 - Proxy Pattern para PhpWord com suporte a SDTs
 * 
 * Esta classe encapsula PhpWord e fornece API unificada para:
 * - Criar documentos Word
 * - Adicionar Content Controls (Structured Document Tags)
 * - Gerenciar IDs únicos automaticamente
 * - Salvar documentos com SDTs injetados
 * 
 * @since 2.0.0
 */
final class ContentControl
{
    // ==================== CONSTANTES DE TIPO ====================
    /**
     * Content Control tipo Group - Agrupa elementos sem permitir edição
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.15
     * Elemento XML: <w:group/>
     */
    public const TYPE_GROUP = 'group';

    /**
     * Content Control tipo Plain Text - Texto simples sem formatação
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.34
     * Elemento XML: <w:text/>
     */
    public const TYPE_PLAIN_TEXT = 'plainText';

    /**
     * Content Control tipo Rich Text - Texto com formatação completa
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.31
     * Elemento XML: <w:richText/>
     */
    public const TYPE_RICH_TEXT = 'richText';

    /**
     * Content Control tipo Picture - Controle para imagens
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.27
     * Elemento XML: <w:picture/>
     */
    public const TYPE_PICTURE = 'picture';

    // ==================== CONSTANTES DE LOCK ====================
    /**
     * Sem bloqueio - Content Control pode ser editado e deletado
     * 
     * Especificação: Valor padrão quando <w:lock> ausente
     */
    public const LOCK_NONE = '';

    /**
     * Content Control bloqueado - Não pode ser deletado, mas conteúdo é editável
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.23 Table 17-21
     * Valor: sdtLocked
     */
    public const LOCK_SDT_LOCKED = 'sdtLocked';

    /**
     * Conteúdo bloqueado - Content Control pode ser deletado, mas conteúdo não é editável
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.23 Table 17-21
     * Valor: sdtContentLocked
     */
    public const LOCK_CONTENT_LOCKED = 'sdtContentLocked';

    /**
     * Desbloqueado explicitamente
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.23 Table 17-21
     * Valor: unlocked
     */
    public const LOCK_UNLOCKED = 'unlocked';

    // ==================== PROPRIEDADES ====================
    
    /**
     * Instância PhpWord encapsulada
     */
    private PhpWord $phpWord;

    /**
     * Registry de Content Controls
     */
    private SDTRegistry $sdtRegistry;

    /**
     * Cria novo ContentControl (Proxy para PhpWord)
     * 
     * @param PhpWord|null $phpWord Instância PhpWord existente ou null para criar nova
     */
    public function __construct(?PhpWord $phpWord = null)
    {
        $this->phpWord = $phpWord ?? new PhpWord();
        $this->sdtRegistry = new SDTRegistry();
    }

    // ==================== DELEGAÇÃO PHPWORD ====================

    /**
     * Adiciona Section ao documento
     * 
     * @param mixed[] $style Estilo da seção
     * @return Section
     */
    public function addSection(array $style = []): Section
    {
        return $this->phpWord->addSection($style);
    }

    /**
     * Retorna propriedades do documento
     * 
     * @return \PhpOffice\PhpWord\Metadata\DocInfo
     */
    public function getDocInfo(): \PhpOffice\PhpWord\Metadata\DocInfo
    {
        return $this->phpWord->getDocInfo();
    }

    /**
     * Retorna configurações do documento
     * 
     * @return \PhpOffice\PhpWord\Metadata\Settings
     */
    public function getSettings(): \PhpOffice\PhpWord\Metadata\Settings
    {
        return $this->phpWord->getSettings();
    }

    /**
     * Adiciona estilo de fonte
     * 
     * @param string $name Nome do estilo
     * @param mixed[] $style Configuração do estilo
     * @return void
     */
    public function addFontStyle(string $name, array $style): void
    {
        $this->phpWord->addFontStyle($name, $style);
    }

    /**
     * Adiciona estilo de parágrafo
     * 
     * @param string $name Nome do estilo
     * @param mixed[] $style Configuração do estilo
     * @return void
     */
    public function addParagraphStyle(string $name, array $style): void
    {
        $this->phpWord->addParagraphStyle($name, $style);
    }

    /**
     * Adiciona estilo de tabela
     * 
     * @param string $name Nome do estilo
     * @param mixed[] $styleTable Estilo da tabela
     * @param mixed[]|null $styleFirstRow Estilo da primeira linha
     * @return void
     */
    public function addTableStyle(string $name, array $styleTable, ?array $styleFirstRow = null): void
    {
        $this->phpWord->addTableStyle($name, $styleTable, $styleFirstRow);
    }

    /**
     * Adiciona estilo de título
     * 
     * @param int $level Nível do título (1-9)
     * @param mixed[] $fontStyle Estilo da fonte
     * @param mixed[] $paragraphStyle Estilo do parágrafo
     * @return void
     */
    public function addTitleStyle(int $level, array $fontStyle, array $paragraphStyle = []): void
    {
        $this->phpWord->addTitleStyle($level, $fontStyle, $paragraphStyle);
    }

    /**
     * Retorna todas as seções do documento
     * 
     * @return Section[]
     */
    public function getSections(): array
    {
        return $this->phpWord->getSections();
    }

    // ==================== CONTENT CONTROL API ====================

    /**
     * Adiciona Content Control envolvendo um elemento
     * 
     * @template T of mixed
     * @param AbstractContainer | AbstractElement $element Elemento PHPWord (Section, Table, etc)
     * @param array{
     *     id?: string,
     *     alias?: string,
     *     tag?: string,
     *     type?: string,
     *     lockType?: string
     * } $options Configurações do Content Control
     * @return AbstractContainer | AbstractElement O mesmo elemento (para fluent API)
     * 
     * @example
     * ```php
     * $cc = new ContentControl();
     * $section = $cc->addSection();
     * $section->addText('Conteúdo protegido');
     * 
     * $cc->addContentControl($section, [
     *     'alias' => 'Cliente',
     *     'tag' => 'customer-name',
     *     'type' => ContentControl::TYPE_RICH_TEXT,
     *     'lockType' => ContentControl::LOCK_SDT_LOCKED
     * ]);
     * 
     * $cc->save('documento.docx');
     * ```
     */
    public function addContentControl(AbstractContainer | AbstractElement $element, array $options = []): AbstractContainer | AbstractElement
    {
        // Criar config a partir das opções
        $config = SDTConfig::fromArray($options);

        // Gerar ID se não fornecido
        if ($config->id === '') {
            $config = $config->withId($this->sdtRegistry->generateUniqueId());
        }

        // Registrar elemento com config
        $this->sdtRegistry->register($element, $config);

        // Retornar elemento para fluent API
        return $element;
    }

    /**
     * Retorna instância PhpWord encapsulada (para casos avançados)
     * 
     * @return PhpWord
     */
    public function getPhpWord(): PhpWord
    {
        return $this->phpWord;
    }

    /**
     * Retorna registry de SDTs (para casos avançados)
     * 
     * @return SDTRegistry
     */
    public function getSDTRegistry(): SDTRegistry
    {
        return $this->sdtRegistry;
    }

    // ==================== SAVE ====================

    /**
     * Salva documento com Content Controls
     * 
     * Workflow:
     * 1. Gera DOCX base com PhpWord
     * 2. Injeta SDTs no document.xml via SDTInjector
     * 3. Move para destino final
     * 
     * @param string $filename Caminho do arquivo de saída
     * @param string $format Formato do documento (padrão: 'Word2007')
     * @return void
     * @throws \RuntimeException Se diretório não gravável
     * @throws \PhpOffice\PhpWord\Exception\Exception Se erro no PHPWord
     * @throws Exception\ContentControlException Se erro na injeção de SDTs
     * 
     * @example
     * ```php
     * $cc = new ContentControl();
     * $section = $cc->addSection();
     * $section->addText('Hello World');
     * $cc->save('documento.docx');
     * ```
     */
    public function save(string $filename, string $format = 'Word2007'): void
    {
        // 1. Validar diretório
        $dir = dirname($filename);
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new \RuntimeException(
                'ContentControl: Target directory not writable: ' . $dir
            );
        }

        // 2. Gerar DOCX base
        $tempFile = sys_get_temp_dir() . '/phpword_' . uniqid() . '.docx';

        try {
            $writer = PHPWordIOFactory::createWriter($this->phpWord, $format);
            $writer->save($tempFile);

            // 3. Injetar SDTs se houver
            $sdts = $this->sdtRegistry->getAll();
            if (count($sdts) > 0) {
                $injector = new SDTInjector();
                $injector->inject($tempFile, $sdts);
            }

            // 4. Mover para destino
            if (!rename($tempFile, $filename)) {
                throw new \RuntimeException(
                    'ContentControl: Failed to move file from ' . $tempFile . ' to ' . $filename
                );
            }
        } finally {
            // Limpar arquivo temporário se ainda existir
            if (file_exists($tempFile)) {
                $this->unlinkWithRetry($tempFile);
            }
        }
    }

    /**
     * Tenta deletar arquivo com múltiplas tentativas
     * 
     * Em Windows, arquivos podem estar brevemente bloqueados após operações de ZIP.
     * 
     * @param string $filePath Caminho do arquivo a deletar
     * @param int $maxAttempts Número máximo de tentativas
     * @return void
     * @throws Exception\TemporaryFileException Se todas tentativas falharem
     */
    private function unlinkWithRetry(string $filePath, int $maxAttempts = 3): void
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            clearstatcache(true, $filePath);

            if (@unlink($filePath)) {
                return; // Sucesso
            }

            if (!file_exists($filePath)) {
                return; // Arquivo já não existe
            }

            // Esperar antes de próxima tentativa (exceto na última)
            if ($attempt < $maxAttempts) {
                usleep(100000); // 100ms
            }
        }

        // Todas tentativas falharam
        throw new Exception\TemporaryFileException($filePath);
    }
}

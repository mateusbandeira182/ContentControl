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
     * Injeta Content Controls em arquivo DOCX existente
     * 
     * @param string $docxPath Caminho do arquivo DOCX
     * @param array<int, array{element: mixed, config: SDTConfig}> $sdtTuples Tuplas elemento→config
     * @return void
     * @throws ZipArchiveException Se falhar ao abrir/manipular ZIP
     * @throws DocumentNotFoundException Se word/document.xml não existir
     */
    public function inject(string $docxPath, array $sdtTuples): void
    {
        // Abrir arquivo como ZIP
        $zip = new \ZipArchive();
        $openResult = $zip->open($docxPath);
        if ($openResult !== true) {
            throw new ZipArchiveException($openResult, $docxPath);
        }

        $zipOpened = true;
        try {
            // Ler document.xml
            $documentXml = $zip->getFromName('word/document.xml');
            if ($documentXml === false) {
                throw new DocumentNotFoundException('word/document.xml', $docxPath);
            }

            // Gerar XML dos SDTs
            $sdtsXml = '';
            foreach ($sdtTuples as $tuple) {
                $sdtsXml .= $this->createSDTElement($tuple['element'], $tuple['config']);
            }

            // Injetar antes de </w:body>
            $bodyClosePos = strpos($documentXml, '</w:body>');
            if ($bodyClosePos !== false) {
                $documentXml = substr_replace(
                    $documentXml,
                    $sdtsXml,
                    $bodyClosePos,
                    0
                );
            }

            // Atualizar document.xml
            $zip->deleteName('word/document.xml');
            $zip->addFromString('word/document.xml', $documentXml);
        } finally {
            if ($zipOpened) {
                $zip->close();
            }
        }
    }

    /**
     * Cria elemento XML <w:sdt> completo
     * 
     * @param mixed $element Elemento PHPWord
     * @param SDTConfig $config Configuração do SDT
     * @return string XML do Content Control
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
        $sdtPr = $doc->createElement('w:sdtPr');

        // ID (obrigatório) - §17.5.2.14
        $id = $doc->createElement('w:id');
        $id->setAttribute('w:val', $config->id);
        $sdtPr->appendChild($id);

        // Alias (opcional) - §17.5.2.6
        if ($config->alias !== '') {
            $alias = $doc->createElement('w:alias');
            $alias->setAttribute('w:val', $config->alias);
            $sdtPr->appendChild($alias);
        }

        // Tag (opcional) - §17.5.2.33
        if ($config->tag !== '') {
            $tag = $doc->createElement('w:tag');
            $tag->setAttribute('w:val', $config->tag);
            $sdtPr->appendChild($tag);
        }

        // Tipo de Content Control (obrigatório)
        $typeElement = $doc->createElement($this->getTypeElementName($config->type));
        $sdtPr->appendChild($typeElement);

        // Lock (condicional) - §17.5.2.23
        if ($config->lockType !== ContentControl::LOCK_NONE) {
            $lock = $doc->createElement('w:lock');
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
}

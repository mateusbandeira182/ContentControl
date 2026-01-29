<?php

declare(strict_types=1);

namespace MkGrow\ContentControl;

/**
 * Gerador de identificadores únicos para elementos PHPWord
 * 
 * Usado para tracking de elementos durante localização no DOM.
 * Implementa cache de marcadores e hashes para melhor performance.
 * 
 * @since 3.0.0
 */
final class ElementIdentifier
{
    /**
     * Cache de marcadores por object ID
     * 
     * @var array<int, string>
     */
    private static array $markerCache = [];

    /**
     * Cache de hashes de conteúdo por object ID
     * 
     * @var array<int, string>
     */
    private static array $hashCache = [];

    /**
     * Gera marcador único para elemento (com cache)
     * 
     * Formato: sdt-marker-{objectId}-{hash8}
     * 
     * Performance: O(1) para elementos já processados, O(n) para novos elementos
     * 
     * @param object $element Elemento PHPWord
     * @return string Marcador único (ex: "sdt-marker-12345-a1b2c3d4")
     */
    public static function generateMarker(object $element): string
    {
        $objectId = spl_object_id($element);
        
        // Retornar do cache se disponível
        if (isset(self::$markerCache[$objectId])) {
            return self::$markerCache[$objectId];
        }
        
        // Gerar novo marcador
        $hash = self::generateContentHash($element);
        $marker = sprintf('sdt-marker-%d-%s', $objectId, $hash);
        
        // Armazenar no cache
        self::$markerCache[$objectId] = $marker;
        
        return $marker;
    }

    /**
     * Gera hash MD5 truncado (8 chars) do conteúdo do elemento (com cache)
     * 
     * Hash é baseado em tipo + conteúdo serializado.
     * Elementos com conteúdo idêntico terão o mesmo hash.
     * 
     * Performance: O(1) para elementos já processados, O(n) para novos elementos
     * 
     * @param object $element Elemento PHPWord
     * @return string Hash de 8 caracteres hexadecimais
     */
    public static function generateContentHash(object $element): string
    {
        $objectId = spl_object_id($element);
        
        // Retornar do cache se disponível
        if (isset(self::$hashCache[$objectId])) {
            return self::$hashCache[$objectId];
        }
        
        // Gerar novo hash
        $serialized = self::serializeForHash($element);
        $hash = substr(md5($serialized), 0, 8);
        
        // Armazenar no cache
        self::$hashCache[$objectId] = $hash;
        
        return $hash;
    }

    /**
     * Limpa cache de marcadores e hashes (útil para testes)
     * 
     * @return void
     */
    public static function clearCache(): void
    {
        self::$markerCache = [];
        self::$hashCache = [];
    }

    /**
     * Retorna estatísticas do cache (para debug/testes)
     * 
     * @return array{markers: int, hashes: int} Contadores de cache
     */
    public static function getCacheStats(): array
    {
        return [
            'markers' => count(self::$markerCache),
            'hashes' => count(self::$hashCache),
        ];
    }

    /**
     * Serializa elemento para geração de hash
     * 
     * @param object $element Elemento PHPWord
     * @return string Representação serializada
     */
    private static function serializeForHash(object $element): string
    {
        $parts = [];

        // Text: incluir texto (sem className para compatibilidade com DOM)
        if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
            $parts[] = 'paragraph';  // Corresponde a w:p no DOM
            $parts[] = $element->getText();
        }

        // TextRun: incluir todos textos internos
        if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
            $parts[] = 'paragraph';  // TextRun também vira w:p
            $parts[] = $element->getText();
        }

        // Table: incluir número de linhas e colunas
        if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
            $parts[] = 'table';  // Corresponde a w:tbl no DOM
            $rowCount = count($element->getRows());
            $parts[] = "rows:{$rowCount}";
            
            // Primeira célula de cada linha para diferenciação
            foreach ($element->getRows() as $row) {
                $cells = $row->getCells();
                if (count($cells) > 0) {
                    $firstCell = $cells[0];
                    $cellElements = $firstCell->getElements();
                    foreach ($cellElements as $el) {
                        if ($el instanceof \PhpOffice\PhpWord\Element\Text) {
                            $parts[] = $el->getText();
                            break;
                        }
                    }
                }
            }
        }

        // Section: incluir tipo e conteúdo de elementos filhos
        if ($element instanceof \PhpOffice\PhpWord\Element\Section) {
            $parts[] = 'section';
            $childElements = $element->getElements();
            foreach ($childElements as $child) {
                // Tipo simplificado
                if ($child instanceof \PhpOffice\PhpWord\Element\Text) {
                    $parts[] = 'text';
                    $parts[] = $child->getText();
                } elseif ($child instanceof \PhpOffice\PhpWord\Element\TextRun) {
                    $parts[] = 'textrun';
                    $parts[] = $child->getText();
                } elseif ($child instanceof \PhpOffice\PhpWord\Element\Table) {
                    $parts[] = 'table';
                }
            }
        }

        // Cell: incluir tipo e conteúdo de elementos filhos
        if ($element instanceof \PhpOffice\PhpWord\Element\Cell) {
            $parts[] = 'cell';
            $childElements = $element->getElements();
            foreach ($childElements as $child) {
                if ($child instanceof \PhpOffice\PhpWord\Element\Text) {
                    $parts[] = 'text';
                    $parts[] = $child->getText();
                } elseif ($child instanceof \PhpOffice\PhpWord\Element\TextRun) {
                    $parts[] = 'textrun';
                    $parts[] = $child->getText();
                } elseif ($child instanceof \PhpOffice\PhpWord\Element\Table) {
                    $parts[] = 'table';
                }
            }
        }

        return implode('|', $parts);
    }
}

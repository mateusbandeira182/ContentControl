<?php

declare(strict_types=1);

namespace MkGrow\ContentControl;

/**
 * Gerador de identificadores únicos para elementos PHPWord
 * 
 * Usado para tracking de elementos durante localização no DOM.
 * 
 * @since 3.0.0
 */
final class ElementIdentifier
{
    /**
     * Gera marcador único para elemento
     * 
     * Formato: sdt-marker-{objectId}-{hash8}
     * 
     * @param object $element Elemento PHPWord
     * @return string Marcador único (ex: "sdt-marker-12345-a1b2c3d4")
     */
    public static function generateMarker(object $element): string
    {
        $objectId = spl_object_id($element);
        $hash = self::generateContentHash($element);
        
        return sprintf('sdt-marker-%d-%s', $objectId, $hash);
    }

    /**
     * Gera hash MD5 truncado (8 chars) do conteúdo do elemento
     * 
     * Hash é baseado em tipo + conteúdo serializado.
     * Elementos com conteúdo idêntico terão o mesmo hash.
     * 
     * @param object $element Elemento PHPWord
     * @return string Hash de 8 caracteres hexadecimais
     */
    public static function generateContentHash(object $element): string
    {
        $serialized = self::serializeForHash($element);
        return substr(md5($serialized), 0, 8);
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

        return implode('|', $parts);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;

/**
 * Class to manage reusable sample elements in tests.
 */
class SampleElements
{
    /**
     * Cria Section com Text simples
     */
    public static function createSectionWithText(string $text = 'Texto de teste'): Section
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText($text);
        
        return $section;
    }

    /**
     * Creates Section with formatted TextRun
     */
    public static function createSectionWithTextRun(): Section
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $textRun = $section->addTextRun();
        $textRun->addText('Texto normal ');
        $textRun->addText('Texto negrito', ['bold' => true]);
        $textRun->addText(' Texto itálico', ['italic' => true]);
        
        return $section;
    }

    /**
     * Creates a section with a table
     */
    public static function createSectionWithTable(int $rows = 2, int $cols = 2): Section
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        $table = $section->addTable();
        for ($r = 0; $r < $rows; $r++) {
            $table->addRow();
            for ($c = 0; $c < $cols; $c++) {
                $table->addCell(2000)->addText("R{$r}C{$c}");
            }
        }
        
        return $section;
    }
}


<?php

require "vendor/autoload.php";

use MkGrow\ContentControl\ContentControl;
use PhpOffice\PhpWord\PhpWord;

$phpWord = new PhpWord();
$contentControl = new ContentControl($phpWord);

$section = $contentControl->addSection();
$textRunTitle = $section->addTextRun();
$textRunTitle->addText("Documento de Teste", ['bold' => true, 'size' => 16]);
$contentControl->addContentControl($textRunTitle, [
    'alias' => 'Título do Documento',
    'tag' => 'document-title',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_UNLOCKED,
]);
$textRun = $section->addTextRun();
$textRun->addText("Este é um parágrafo de teste para o Content Control.");
$contentControl->addContentControl($textRun, [
    'alias' => 'Parágrafo de Teste',
    'tag' => 'test-paragraph',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_UNLOCKED,
]);

$table = $section->addTable();
$contentControl->addContentControl($table, [
    'alias' => 'Tabela de Teste',
    'tag' => 'test-table',
    'type' => ContentControl::TYPE_GROUP,
    'lockType' => ContentControl::LOCK_UNLOCKED,
]);

$row = $table->addRow();
// ❌ Não adicionar Content Control em Row - Row não é elemento de conteúdo válido no Word XML
// Content Controls só podem envolver: Table, Cell, Paragraph, etc.
$cell1 = $row->addCell();
$cell1 = $contentControl->addContentControl($cell1, [
    'alias' => 'Célula 1',
    'tag' => 'cell-1-container',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_UNLOCKED,
]);
$cell1->addText("Célula 1");

$cell2 = $row->addCell();
$cell2 = $contentControl->addContentControl($cell2, [
    'alias' => 'Célula 2',
    'tag' => 'cell-2-container',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_UNLOCKED,
]);
$cell2->addText("Célula 2");

$row1 = $table->addRow();
$cell3 = $row1->addCell();
$cell3 = $contentControl->addContentControl($cell3, [
    'alias' => 'Célula 3',
    'tag' => 'cell-3-container',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_UNLOCKED,
]);
$cell3->addText("Célula 3");
$cell4 = $row1->addCell();
$cell4 = $contentControl->addContentControl($cell4, [
    'alias' => 'Célula 4',
    'tag' => 'cell-4-container',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_UNLOCKED,
]);
$cell4->addText("Célula 4");

$contentControl->save("teste.docx");
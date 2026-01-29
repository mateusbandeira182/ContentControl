<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;

/**
 * ContentControl v3.0 - Demonstração de Zero Duplicação
 * 
 * Este exemplo mostra como a v3.0 elimina duplicação de conteúdo
 * usando manipulação DOM inline.
 */

echo "=== ContentControl v3.0 - Demonstração de Zero Duplicação ===\n\n";

$cc = new ContentControl();

// Configurar propriedades do documento
$cc->getDocInfo()->setCreator('ContentControl Demo');
$cc->getDocInfo()->setTitle('v3.0 No Duplication Demo');

// Adicionar seção
$section = $cc->addSection();

// Título
$section->addTitle('ContentControl v3.0 - Zero Duplicação', 1);

// Parágrafo introdutório
$section->addText(
    'Este documento demonstra a funcionalidade de Content Controls sem duplicação de conteúdo.'
);

// Parágrafo com Content Control
echo "✓ Adicionando parágrafo protegido...\n";
$paragraph = $section->addText(
    'Este texto está protegido por Content Control.',
    ['bold' => true]
);

$cc->addContentControl($paragraph, [
    'alias' => 'Texto Protegido',
    'tag' => 'protected-text',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED
]);

// Tabela com Content Control
echo "✓ Adicionando tabela de produtos...\n";
$section->addTextBreak();
$section->addText('Tabela de Produtos:', ['bold' => true]);

$table = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '999999',
    'cellMargin' => 80
]);

$table->addRow();
$table->addCell(2000)->addText('Produto', ['bold' => true]);
$table->addCell(2000)->addText('Preço', ['bold' => true]);

$table->addRow();
$table->addCell()->addText('Item A');
$table->addCell()->addText('R$ 10,00');

$table->addRow();
$table->addCell()->addText('Item B');
$table->addCell()->addText('R$ 20,00');

$table->addRow();
$table->addCell()->addText('Item C');
$table->addCell()->addText('R$ 30,00');

$cc->addContentControl($table, [
    'alias' => 'Tabela de Produtos',
    'tag' => 'product-table',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

// Célula protegida dentro de outra tabela
echo "✓ Adicionando tabela com célula protegida...\n";
$section->addTextBreak();
$section->addText('Formulário com Campo Protegido:', ['bold' => true]);

$formTable = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '666666',
]);

$formTable->addRow();
$formTable->addCell(3000)->addText('Nome do Cliente:');
$clientCell = $formTable->addCell(4000);
$clientCell->addText('[INSIRA O NOME AQUI]', ['italic' => true]);

$cc->addContentControl($clientCell, [
    'alias' => 'Nome do Cliente',
    'tag' => 'client-name',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

$formTable->addRow();
$formTable->addCell()->addText('Data:');
$formTable->addCell()->addText(date('d/m/Y'));

// Salvar documento
echo "✓ Salvando documento...\n";
$outputDir = __DIR__ . '/output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$outputFile = $outputDir . '/v3_no_duplication_demo.docx';
$cc->save($outputFile);

echo "\n✅ Documento salvo em: {$outputFile}\n\n";
echo "📊 Verifique que não há duplicação:\n";
echo "   1. Abra o documento no Microsoft Word\n";
echo "   2. Navegue pelos Content Controls (Developer Tab → Design Mode)\n";
echo "   3. Observe que cada conteúdo aparece apenas 1 vez\n";
echo "   4. Tente editar os campos protegidos\n\n";

// Validar tamanho do arquivo
$fileSize = filesize($outputFile);
$fileSizeKB = round($fileSize / 1024, 2);
echo "📦 Tamanho do arquivo: {$fileSizeKB} KB\n";
echo "   (v2.0 geraria ~50% maior devido à duplicação)\n\n";


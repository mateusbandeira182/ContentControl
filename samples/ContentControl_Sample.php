<?php

/**
 * ContentControl - Exemplo Completo de Uso
 * 
 * Demonstra todas as funcionalidades da biblioteca ContentControl
 * para criar Content Controls (Structured Document Tags) em documentos Word.
 * 
 * @package   MkGrow\ContentControl
 * @author    Mateus Bandeira
 * @license   MIT
 * @link      https://github.com/mateusbandeira182/ContentControl
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;
use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\IOFactory;

// =====================================================================
// 1. EXEMPLO BÁSICO - Content Control Simples
// =====================================================================

echo "=== EXEMPLO 1: Content Control Básico ===\n\n";

$phpWord = new PhpWord();
$section = $phpWord->addSection();
$section->addText('Digite o nome do cliente:', ['bold' => true]);

$controlNome = new ContentControl($section, [
    'alias' => 'Nome do Cliente',
    'tag' => 'customer-name',
]);

echo "✓ Content Control criado com alias e tag\n";
echo "✓ XML gerado: " . strlen($controlNome->getXml()) . " bytes\n\n";

// =====================================================================
// 2. TIPOS DE CONTENT CONTROL
// =====================================================================

echo "=== EXEMPLO 2: Diferentes Tipos de Content Control ===\n\n";

// 2.1 Rich Text (permite formatação)
$sectionRichText = $phpWord->addSection();
$sectionRichText->addText('Descrição detalhada com formatação', ['italic' => true]);

$controlRichText = new ContentControl($sectionRichText, [
    'alias' => 'Descrição',
    'tag' => 'description',
    'type' => ContentControl::TYPE_RICH_TEXT,
]);

echo "✓ Rich Text: Permite texto com formatação completa\n";

// 2.2 Plain Text (apenas texto sem formatação)
$sectionPlainText = $phpWord->addSection();
$sectionPlainText->addText('CPF do cliente');

$controlPlainText = new ContentControl($sectionPlainText, [
    'alias' => 'CPF',
    'tag' => 'customer-cpf',
    'type' => ContentControl::TYPE_PLAIN_TEXT,
]);

echo "✓ Plain Text: Apenas texto simples sem formatação\n";

// 2.3 Group (agrupa múltiplos elementos)
$sectionGroup = $phpWord->addSection();
$sectionGroup->addText('Seção 1', ['bold' => true]);
$sectionGroup->addText('Parágrafo 1');
$sectionGroup->addText('Parágrafo 2');

$controlGroup = new ContentControl($sectionGroup, [
    'alias' => 'Grupo de Parágrafos',
    'tag' => 'paragraph-group',
    'type' => ContentControl::TYPE_GROUP,
]);

echo "✓ Group: Agrupa múltiplos elementos\n";

// 2.4 Picture (para imagens)
$sectionPicture = $phpWord->addSection();
// Nota: PHPWord não suporta Image em Section diretamente, mas demonstramos o conceito

$controlPicture = new ContentControl($sectionPicture, [
    'alias' => 'Logo da Empresa',
    'tag' => 'company-logo',
    'type' => ContentControl::TYPE_PICTURE,
]);

echo "✓ Picture: Controle para imagens\n\n";

// =====================================================================
// 3. NÍVEIS DE BLOQUEIO (LOCK)
// =====================================================================

echo "=== EXEMPLO 3: Níveis de Bloqueio ===\n\n";

// 3.1 LOCK_NONE (sem bloqueio - pode editar e deletar)
$section1 = $phpWord->addSection();
$section1->addText('Campo editável e deletável');

$control1 = new ContentControl($section1, [
    'alias' => 'Campo Livre',
    'lockType' => ContentControl::LOCK_NONE,
]);

echo "✓ LOCK_NONE: Pode editar e deletar o Content Control\n";

// 3.2 LOCK_SDT_LOCKED (não pode deletar, mas pode editar conteúdo)
$section2 = $phpWord->addSection();
$section2->addText('Campo protegido contra deleção');

$control2 = new ContentControl($section2, [
    'alias' => 'Campo Protegido',
    'tag' => 'protected-field',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

echo "✓ LOCK_SDT_LOCKED: Não pode deletar, mas pode editar conteúdo\n";

// 3.3 LOCK_CONTENT_LOCKED (pode deletar, mas não pode editar conteúdo)
$section3 = $phpWord->addSection();
$section3->addText('Conteúdo bloqueado para edição');

$control3 = new ContentControl($section3, [
    'alias' => 'Conteúdo Bloqueado',
    'tag' => 'locked-content',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);

echo "✓ LOCK_CONTENT_LOCKED: Pode deletar, mas não pode editar\n";

// 3.4 LOCK_UNLOCKED (desbloqueado explicitamente)
$section4 = $phpWord->addSection();
$section4->addText('Explicitamente desbloqueado');

$control4 = new ContentControl($section4, [
    'alias' => 'Desbloqueado',
    'lockType' => ContentControl::LOCK_UNLOCKED,
]);

echo "✓ LOCK_UNLOCKED: Explicitamente desbloqueado\n\n";

// =====================================================================
// 4. EXEMPLO AVANÇADO - Tabela em Content Control
// =====================================================================

echo "=== EXEMPLO 4: Tabela em Content Control ===\n\n";

$sectionTable = $phpWord->addSection();

// Criar tabela
$table = $sectionTable->addTable([
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 80,
]);

// Cabeçalho
$table->addRow(900);
$table->addCell(3000, ['bgColor' => 'CCCCCC'])->addText('Item', ['bold' => true]);
$table->addCell(2000, ['bgColor' => 'CCCCCC'])->addText('Quantidade', ['bold' => true]);
$table->addCell(2000, ['bgColor' => 'CCCCCC'])->addText('Valor', ['bold' => true]);

// Linhas de dados
$table->addRow();
$table->addCell(3000)->addText('Produto A');
$table->addCell(2000)->addText('10');
$table->addCell(2000)->addText('R$ 100,00');

$table->addRow();
$table->addCell(3000)->addText('Produto B');
$table->addCell(2000)->addText('5');
$table->addCell(2000)->addText('R$ 50,00');

// Envolver tabela em Content Control com bloqueio de conteúdo
$controlTable = new ContentControl($sectionTable, [
    'alias' => 'Tabela de Produtos',
    'tag' => 'products-table',
    'type' => ContentControl::TYPE_GROUP,
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);

echo "✓ Tabela criada e envolvida em Content Control\n";
echo "✓ Bloqueio: Usuário não pode editar dados, mas pode deletar tabela\n\n";

// =====================================================================
// 5. VALIDAÇÃO E INSPEÇÃO
// =====================================================================

echo "=== EXEMPLO 5: Validação e Inspeção ===\n\n";

$section = $phpWord->addSection();
$section->addText('Campo para validação');

$control = new ContentControl($section, [
    'id' => '12345678',  // ID customizado
    'alias' => 'Campo Validado',
    'tag' => 'validated-field',
    'type' => ContentControl::TYPE_RICH_TEXT,
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Gerar e validar XML
$xml = $control->getXml();

echo "XML Gerado:\n";
echo "┌─────────────────────────────────────────┐\n";

// Validar com DOMDocument
$dom = new DOMDocument();
if ($dom->loadXML($xml)) {
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    
    echo "│ ✓ XML válido e bem formado              │\n";
    echo "│ ✓ Namespace: WordprocessingML          │\n";
    echo "│ ✓ ID: " . str_pad($xpath->query('//w:id/@w:val')->item(0)->nodeValue, 32) . "│\n";
    echo "│ ✓ Alias: " . str_pad($xpath->query('//w:alias/@w:val')->item(0)->nodeValue, 28) . "│\n";
    echo "│ ✓ Tag: " . str_pad($xpath->query('//w:tag/@w:val')->item(0)->nodeValue, 30) . "│\n";
    echo "│ ✓ Tipo: richText                        │\n";
    echo "│ ✓ Lock: sdtLocked                       │\n";
} else {
    echo "│ ✗ XML inválido                          │\n";
}

echo "└─────────────────────────────────────────┘\n\n";

// =====================================================================
// 6. SALVAR DOCUMENTO COM CONTENT CONTROLS
// =====================================================================

echo "=== EXEMPLO 6: Salvar Documento ===\n\n";

// Criar documento completo
$phpWordFinal = new PhpWord();

// Metadados
$phpWordFinal->getDocInfo()
    ->setCreator('ContentControl Library')
    ->setTitle('Documento com Content Controls')
    ->setDescription('Exemplo de uso da biblioteca ContentControl');

// Seção principal
$mainSection = $phpWordFinal->addSection();
$mainSection->addText(
    'DOCUMENTO COM CONTENT CONTROLS',
    ['bold' => true, 'size' => 16],
    ['alignment' => Jc::CENTER]
);
$mainSection->addTextBreak(2);

// Campo 1: Nome
$section1 = $phpWordFinal->addSection();
$section1->addText('Nome completo:', ['bold' => true]);
$section1->addText('Digite aqui...');

$controlFinal1 = new ContentControl($section1, [
    'alias' => 'Nome Completo',
    'tag' => 'full-name',
    'type' => ContentControl::TYPE_PLAIN_TEXT,
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Campo 2: Observações
$section2 = $phpWordFinal->addSection();
$section2->addText('Observações:', ['bold' => true]);
$section2->addText('Adicione observações aqui...');

$controlFinal2 = new ContentControl($section2, [
    'alias' => 'Observações',
    'tag' => 'notes',
    'type' => ContentControl::TYPE_RICH_TEXT,
]);

// Salvar documento
$outputFile = __DIR__ . '/exemplo-content-controls.docx';

$saved = IOFactory::saveWithContentControls(
    $phpWordFinal,
    [$controlFinal1, $controlFinal2],
    $outputFile
);

if ($saved) {
    echo "✓ Documento salvo com sucesso!\n";
    echo "✓ Arquivo: {$outputFile}\n";
    echo "✓ Tamanho: " . number_format(filesize($outputFile) / 1024, 2) . " KB\n";
    echo "\n🎉 Abra o arquivo no Microsoft Word para visualizar os Content Controls!\n";
} else {
    echo "✗ Erro ao salvar documento\n";
}

echo "\n";

// =====================================================================
// 7. CONSTANTES DISPONÍVEIS
// =====================================================================

echo "=== EXEMPLO 7: Referência de Constantes ===\n\n";

echo "Tipos de Content Control:\n";
echo "  • ContentControl::TYPE_GROUP        = '" . ContentControl::TYPE_GROUP . "'\n";
echo "  • ContentControl::TYPE_PLAIN_TEXT   = '" . ContentControl::TYPE_PLAIN_TEXT . "'\n";
echo "  • ContentControl::TYPE_RICH_TEXT    = '" . ContentControl::TYPE_RICH_TEXT . "'\n";
echo "  • ContentControl::TYPE_PICTURE      = '" . ContentControl::TYPE_PICTURE . "'\n";
echo "\n";

echo "Níveis de Bloqueio:\n";
echo "  • ContentControl::LOCK_NONE             = '" . ContentControl::LOCK_NONE . "' (vazio)\n";
echo "  • ContentControl::LOCK_SDT_LOCKED       = '" . ContentControl::LOCK_SDT_LOCKED . "'\n";
echo "  • ContentControl::LOCK_CONTENT_LOCKED   = '" . ContentControl::LOCK_CONTENT_LOCKED . "'\n";
echo "  • ContentControl::LOCK_UNLOCKED         = '" . ContentControl::LOCK_UNLOCKED . "'\n";
echo "\n";

// =====================================================================
// 8. TRATAMENTO DE ERROS
// =====================================================================

echo "=== EXEMPLO 8: Tratamento de Erros ===\n\n";

// 8.1 Tipo inválido
try {
    $section = $phpWord->addSection();
    new ContentControl($section, [
        'type' => 'tipo-invalido',  // ❌ Tipo não existe
    ]);
} catch (InvalidArgumentException $e) {
    echo "✓ Exceção capturada para tipo inválido:\n";
    echo "  → {$e->getMessage()}\n\n";
}

// 8.2 Lock type inválido
try {
    $section = $phpWord->addSection();
    new ContentControl($section, [
        'lockType' => 'lock-invalido',  // ❌ Lock type não existe
    ]);
} catch (InvalidArgumentException $e) {
    echo "✓ Exceção capturada para lockType inválido:\n";
    echo "  → {$e->getMessage()}\n\n";
}

echo "==============================================\n";
echo "        FIM DOS EXEMPLOS                     \n";
echo "==============================================\n";

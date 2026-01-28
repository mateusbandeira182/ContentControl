<?php

/**
 * ContentControl v2.0 - Sample de Uso Completo
 * 
 * Demonstra todas as funcionalidades da biblioteca ContentControl v2.0
 * para criar Content Controls (Structured Document Tags) em documentos Word.
 * 
 * @package   MkGrow\ContentControl
 * @author    Mateus Bandeira
 * @license   MIT
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;

echo "\n╔═══════════════════════════════════════════════════════════╗\n";
echo "║   ContentControl v2.0 - Demonstração Completa           ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

// =====================================================================
// 1. EXEMPLO BÁSICO - API Unificada
// =====================================================================

echo "═══ EXEMPLO 1: API Básica (Proxy Pattern) ═══\n\n";

$cc = new ContentControl();

$section = $cc->addSection();
$section->addText('Digite o nome do cliente:');

$cc->addContentControl($section, [
    'alias' => 'Nome do Cliente',
    'tag' => 'customer-name',
]);

$cc->save(__DIR__ . '/output/exemplo-01-basico.docx');

echo "✓ Documento criado com Content Control simples\n";
echo "✓ Arquivo: exemplo-01-basico.docx\n\n";

// =====================================================================
// 2. TIPOS DE CONTENT CONTROL
// =====================================================================

echo "═══ EXEMPLO 2: Tipos de Content Control ═══\n\n";

$cc2 = new ContentControl();

// 2.1 Rich Text (permite formatação)
$section1 = $cc2->addSection();
$section1->addText('Descrição do produto com formatação');
$cc2->addContentControl($section1, [
    'alias' => 'Descrição',
    'tag' => 'description',
    'type' => ContentControl::TYPE_RICH_TEXT,  // Padrão
]);
echo "✓ Rich Text: Texto com formatação completa\n";

// 2.2 Plain Text (texto simples)
$section2 = $cc2->addSection();
$section2->addText('CPF do cliente (apenas números)');
$cc2->addContentControl($section2, [
    'alias' => 'CPF',
    'tag' => 'customer-cpf',
    'type' => ContentControl::TYPE_PLAIN_TEXT,
]);
echo "✓ Plain Text: Apenas texto simples\n";

// 2.3 Group (agrupa elementos)
$section3 = $cc2->addSection();
$section3->addText('Título da Seção', ['bold' => true]);
$section3->addText('Parágrafo 1');
$section3->addText('Parágrafo 2');
$cc2->addContentControl($section3, [
    'alias' => 'Grupo de Parágrafos',
    'tag' => 'paragraph-group',
    'type' => ContentControl::TYPE_GROUP,
]);
echo "✓ Group: Agrupa múltiplos elementos\n";

$cc2->save(__DIR__ . '/output/exemplo-02-tipos.docx');
echo "\n✓ Arquivo: exemplo-02-tipos.docx\n\n";

// =====================================================================
// 3. NÍVEIS DE BLOQUEIO
// =====================================================================

echo "═══ EXEMPLO 3: Níveis de Bloqueio ═══\n\n";

$cc3 = new ContentControl();

// 3.1 Sem bloqueio (padrão)
$section1 = $cc3->addSection();
$section1->addText('Campo editável e deletável');
$cc3->addContentControl($section1, [
    'alias' => 'Campo Livre',
    'tag' => 'free-field',
    'lockType' => ContentControl::LOCK_NONE,  // Padrão
]);
echo "✓ LOCK_NONE: Pode editar e deletar\n";

// 3.2 SDT bloqueado (não pode deletar)
$section2 = $cc3->addSection();
$section2->addText('Conteúdo editável, mas controle não pode ser deletado');
$cc3->addContentControl($section2, [
    'alias' => 'Campo Protegido',
    'tag' => 'protected-field',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);
echo "✓ LOCK_SDT_LOCKED: Conteúdo editável, SDT não deletável\n";

// 3.3 Conteúdo bloqueado
$section3 = $cc3->addSection();
$section3->addText('Conteúdo bloqueado, mas controle pode ser deletado');
$cc3->addContentControl($section3, [
    'alias' => 'Conteúdo Fixo',
    'tag' => 'fixed-content',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);
echo "✓ LOCK_CONTENT_LOCKED: Conteúdo não editável, SDT deletável\n";

$cc3->save(__DIR__ . '/output/exemplo-03-bloqueio.docx');
echo "\n✓ Arquivo: exemplo-03-bloqueio.docx\n\n";

// =====================================================================
// 4. MÚLTIPLOS CONTENT CONTROLS
// =====================================================================

echo "═══ EXEMPLO 4: Múltiplos Content Controls ═══\n\n";

$cc4 = new ContentControl();

// Configurar documento
$cc4->getDocInfo()->setTitle('Formulário de Cadastro');
$cc4->getDocInfo()->setCreator('Sistema XYZ');

// Seção 1: Dados pessoais
$section1 = $cc4->addSection();
$section1->addText('DADOS PESSOAIS', ['bold' => true, 'size' => 14]);
$section1->addText('');
$section1->addText('Nome completo: _____________________');
$cc4->addContentControl($section1, [
    'alias' => 'Dados Pessoais',
    'tag' => 'personal-data',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Seção 2: Endereço
$section2 = $cc4->addSection();
$section2->addText('ENDEREÇO', ['bold' => true, 'size' => 14]);
$section2->addText('');
$section2->addText('Rua: _____________________');
$section2->addText('Número: _____  Complemento: _____');
$cc4->addContentControl($section2, [
    'alias' => 'Endereço',
    'tag' => 'address',
    'lockType' => ContentControl::LOCK_SDT_LOCKED,
]);

// Seção 3: Observações
$section3 = $cc4->addSection();
$section3->addText('OBSERVAÇÕES', ['bold' => true, 'size' => 14]);
$section3->addText('');
$section3->addText('Observações adicionais...');
$cc4->addContentControl($section3, [
    'alias' => 'Observações',
    'tag' => 'notes',
    'type' => ContentControl::TYPE_RICH_TEXT,
]);

$cc4->save(__DIR__ . '/output/exemplo-04-multiplos.docx');
echo "✓ Documento com 3 Content Controls criado\n";
echo "✓ Cada seção tem seu próprio SDT\n";
echo "✓ Arquivo: exemplo-04-multiplos.docx\n\n";

// =====================================================================
// 5. TABELAS COM CONTENT CONTROL
// =====================================================================

echo "═══ EXEMPLO 5: Tabelas Protegidas ═══\n\n";

$cc5 = new ContentControl();

$section = $cc5->addSection();
$section->addText('LISTA DE PRODUTOS', ['bold' => true, 'size' => 14]);
$section->addText('');

// Criar tabela
$table = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '999999',
]);

// Cabeçalho
$table->addRow();
$table->addCell(3000)->addText('Produto', ['bold' => true]);
$table->addCell(2000)->addText('Quantidade', ['bold' => true]);
$table->addCell(2000)->addText('Preço', ['bold' => true]);

// Dados
$table->addRow();
$table->addCell(3000)->addText('Produto A');
$table->addCell(2000)->addText('10');
$table->addCell(2000)->addText('R$ 100,00');

$table->addRow();
$table->addCell(3000)->addText('Produto B');
$table->addCell(2000)->addText('5');
$table->addCell(2000)->addText('R$ 50,00');

// Proteger a seção inteira (incluindo a tabela)
$cc5->addContentControl($section, [
    'alias' => 'Tabela de Produtos',
    'tag' => 'products-table',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED,
]);

$cc5->save(__DIR__ . '/output/exemplo-05-tabela.docx');
echo "✓ Tabela protegida com Content Control\n";
echo "✓ Conteúdo não editável (LOCK_CONTENT_LOCKED)\n";
echo "✓ Arquivo: exemplo-05-tabela.docx\n\n";

// =====================================================================
// 6. ESTILOS E FORMATAÇÃO
// =====================================================================

echo "═══ EXEMPLO 6: Estilos e Formatação ═══\n\n";

$cc6 = new ContentControl();

// Definir estilos
$cc6->addFontStyle('titulo', [
    'bold' => true,
    'size' => 16,
    'color' => '1F4E78',
]);

$cc6->addFontStyle('destaque', [
    'bold' => true,
    'color' => 'FF0000',
]);

$cc6->addParagraphStyle('centralizado', [
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
    'spaceAfter' => 200,
]);

// Usar estilos
$section = $cc6->addSection();
$section->addText('RELATÓRIO MENSAL', 'titulo', 'centralizado');
$section->addText('');
$section->addText('Este é um texto com destaque importante', 'destaque');
$section->addText('Texto normal...');

$cc6->addContentControl($section, [
    'alias' => 'Relatório',
    'tag' => 'monthly-report',
]);

$cc6->save(__DIR__ . '/output/exemplo-06-estilos.docx');
echo "✓ Documento com estilos customizados\n";
echo "✓ Fontes, cores e alinhamento configurados\n";
echo "✓ Arquivo: exemplo-06-estilos.docx\n\n";

// =====================================================================
// 7. TRATAMENTO DE ERROS
// =====================================================================

echo "═══ EXEMPLO 7: Tratamento de Erros ═══\n\n";

use MkGrow\ContentControl\Exception\ContentControlException;

try {
    $ccErro = new ContentControl();
    $section = $ccErro->addSection();
    $section->addText('Teste de erro');
    
    // Tentar salvar em diretório inválido
    $ccErro->save('/diretorio/invalido/documento.docx');
    
} catch (ContentControlException | \RuntimeException $e) {
    echo "✓ Exception capturada corretamente\n";
    echo "  Mensagem: " . $e->getMessage() . "\n";
}

echo "\n";

// =====================================================================
// 8. IDS AUTOMÁTICOS
// =====================================================================

echo "═══ EXEMPLO 8: IDs Únicos Automáticos ═══\n\n";

$cc8 = new ContentControl();

// ID gerado automaticamente
$section1 = $cc8->addSection();
$section1->addText('Campo com ID automático');
$cc8->addContentControl($section1, [
    'alias' => 'Campo 1',
    'tag' => 'auto-id-1',
    // 'id' omitido - será gerado automaticamente (8 dígitos)
]);

// ID especificado manualmente
$section2 = $cc8->addSection();
$section2->addText('Campo com ID manual');
$cc8->addContentControl($section2, [
    'id' => '12345678',  // ID explícito
    'alias' => 'Campo 2',
    'tag' => 'manual-id-2',
]);

$cc8->save(__DIR__ . '/output/exemplo-08-ids.docx');
echo "✓ IDs únicos de 8 dígitos\n";
echo "✓ Geração automática ou manual\n";
echo "✓ Arquivo: exemplo-08-ids.docx\n\n";

// =====================================================================
// 9. INTEGRAÇÃO COM PHPWORD EXISTENTE
// =====================================================================

echo "═══ EXEMPLO 9: Integração com PhpWord Existente ═══\n\n";

use PhpOffice\PhpWord\PhpWord;

// Criar PhpWord normalmente
$phpWord = new PhpWord();
$phpWord->getDocInfo()->setTitle('Documento Híbrido');

// Passar para ContentControl
$cc9 = new ContentControl($phpWord);

$section = $cc9->addSection();
$section->addText('Conteúdo adicionado via ContentControl');

$cc9->addContentControl($section, [
    'alias' => 'Campo Híbrido',
    'tag' => 'hybrid-field',
]);

$cc9->save(__DIR__ . '/output/exemplo-09-integracao.docx');
echo "✓ ContentControl aceita instância PhpWord existente\n";
echo "✓ Permite reutilizar documentos já configurados\n";
echo "✓ Arquivo: exemplo-09-integracao.docx\n\n";

// =====================================================================
// RESUMO
// =====================================================================

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║   ✓ Todos os exemplos executados com sucesso!           ║\n";
echo "║                                                           ║\n";
echo "║   Arquivos gerados em: samples/output/                   ║\n";
echo "║                                                           ║\n";
echo "║   - exemplo-01-basico.docx                               ║\n";
echo "║   - exemplo-02-tipos.docx                                ║\n";
echo "║   - exemplo-03-bloqueio.docx                             ║\n";
echo "║   - exemplo-04-multiplos.docx                            ║\n";
echo "║   - exemplo-05-tabela.docx                               ║\n";
echo "║   - exemplo-06-estilos.docx                              ║\n";
echo "║   - exemplo-08-ids.docx                                  ║\n";
echo "║   - exemplo-09-integracao.docx                           ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

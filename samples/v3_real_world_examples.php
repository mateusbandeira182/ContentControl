<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;

/**
 * ContentControl v3.0 - Exemplo de Casos de Uso Reais
 * 
 * Demonstra aplicações práticas de Content Controls
 */

echo "=== ContentControl v3.0 - Casos de Uso Reais ===\n\n";

// ========== CASO 1: Contrato Comercial ==========
echo "1️⃣ Gerando contrato comercial...\n";

$contract = new ContentControl();
$contract->getDocInfo()->setTitle('Contrato de Prestação de Serviços');

$section = $contract->addSection();
$section->addTitle('CONTRATO DE PRESTAÇÃO DE SERVIÇOS', 1);

// Partes do contrato (protegidas)
$section->addText('CONTRATANTE:', ['bold' => true]);
$contractorName = $section->addText('[NOME DA EMPRESA CONTRATANTE]', ['italic' => true]);
$contract->addContentControl($contractorName, [
    'alias' => 'Nome do Contratante',
    'tag' => 'contractor-name',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

$section->addTextBreak();
$section->addText('CONTRATADO:', ['bold' => true]);
$serviceName = $section->addText('[NOME DO PRESTADOR]', ['italic' => true]);
$contract->addContentControl($serviceName, [
    'alias' => 'Nome do Prestador',
    'tag' => 'provider-name',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

// Tabela de valores (bloqueada para edição)
$section->addTextBreak();
$section->addText('VALORES E CONDIÇÕES:', ['bold' => true]);

$pricing = $section->addTable(['borderSize' => 6]);
$pricing->addRow();
$pricing->addCell(3000)->addText('Descrição', ['bold' => true]);
$pricing->addCell(2000)->addText('Valor', ['bold' => true]);

$pricing->addRow();
$pricing->addCell()->addText('Serviços Mensais');
$pricing->addCell()->addText('R$ 5.000,00');

$pricing->addRow();
$pricing->addCell()->addText('Taxa de Manutenção');
$pricing->addCell()->addText('R$ 500,00');

$contract->addContentControl($pricing, [
    'alias' => 'Tabela de Preços',
    'tag' => 'pricing-table',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

// Salvar contrato
$contractFile = __DIR__ . '/output/contrato_exemplo.docx';
$contract->save($contractFile);
echo "   ✓ Salvo em: {$contractFile}\n\n";

// ========== CASO 2: Formulário de Cadastro ==========
echo "2️⃣ Gerando formulário de cadastro...\n";

$form = new ContentControl();
$form->getDocInfo()->setTitle('Formulário de Cadastro de Cliente');

$formSection = $form->addSection();
$formSection->addTitle('FORMULÁRIO DE CADASTRO', 1);

// Campos do formulário
$formTable = $formSection->addTable(['borderSize' => 6]);

// Nome Completo
$formTable->addRow();
$formTable->addCell(3000)->addText('Nome Completo:');
$nameCell = $formTable->addCell(4000);
$nameCell->addText('[Digite o nome completo]', ['italic' => true, 'color' => '666666']);
$form->addContentControl($nameCell, [
    'alias' => 'Nome Completo',
    'tag' => 'full-name',
    'type' => ContentControl::TYPE_PLAIN_TEXT
]);

// Email
$formTable->addRow();
$formTable->addCell()->addText('E-mail:');
$emailCell = $formTable->addCell();
$emailCell->addText('[email@exemplo.com]', ['italic' => true, 'color' => '666666']);
$form->addContentControl($emailCell, [
    'alias' => 'E-mail',
    'tag' => 'email-address',
    'type' => ContentControl::TYPE_PLAIN_TEXT
]);

// Telefone
$formTable->addRow();
$formTable->addCell()->addText('Telefone:');
$phoneCell = $formTable->addCell();
$phoneCell->addText('[(00) 00000-0000]', ['italic' => true, 'color' => '666666']);
$form->addContentControl($phoneCell, [
    'alias' => 'Telefone',
    'tag' => 'phone-number',
    'type' => ContentControl::TYPE_PLAIN_TEXT
]);

// Observações (área de texto livre)
$formSection->addTextBreak();
$formSection->addText('Observações:', ['bold' => true]);
$notes = $formSection->addText(
    'Insira aqui quaisquer observações adicionais sobre o cadastro do cliente.',
    ['italic' => true, 'color' => '999999']
);
$form->addContentControl($notes, [
    'alias' => 'Observações',
    'tag' => 'notes',
    'type' => ContentControl::TYPE_RICH_TEXT
]);

// Salvar formulário
$formFile = __DIR__ . '/output/formulario_cadastro.docx';
$form->save($formFile);
echo "   ✓ Salvo em: {$formFile}\n\n";

// ========== CASO 3: Relatório com Dados Fixos ==========
echo "3️⃣ Gerando relatório com seções fixas...\n";

$report = new ContentControl();
$report->getDocInfo()->setTitle('Relatório Mensal');

$reportSection = $report->addSection();
$reportSection->addTitle('RELATÓRIO MENSAL - ' . date('m/Y'), 1);

// Resumo Executivo (bloqueado)
$reportSection->addText('RESUMO EXECUTIVO', ['bold' => true, 'size' => 14]);
$summary = $reportSection->addText(
    'Este relatório apresenta os resultados consolidados do mês, incluindo métricas de performance e indicadores chave.'
);
$report->addContentControl($summary, [
    'alias' => 'Resumo Executivo',
    'tag' => 'executive-summary',
    'lockType' => ContentControl::LOCK_CONTENT_LOCKED
]);

// Tabela de KPIs (bloqueada)
$reportSection->addTextBreak();
$reportSection->addText('INDICADORES CHAVE DE PERFORMANCE', ['bold' => true, 'size' => 14]);

$kpiTable = $reportSection->addTable(['borderSize' => 6]);
$kpiTable->addRow();
$kpiTable->addCell(3000)->addText('Métrica', ['bold' => true]);
$kpiTable->addCell(2000)->addText('Valor', ['bold' => true]);
$kpiTable->addCell(2000)->addText('Meta', ['bold' => true]);

$kpiTable->addRow();
$kpiTable->addCell()->addText('Vendas Totais');
$kpiTable->addCell()->addText('R$ 150.000');
$kpiTable->addCell()->addText('R$ 120.000');

$kpiTable->addRow();
$kpiTable->addCell()->addText('Novos Clientes');
$kpiTable->addCell()->addText('25');
$kpiTable->addCell()->addText('20');

$kpiTable->addRow();
$kpiTable->addCell()->addText('Taxa de Conversão');
$kpiTable->addCell()->addText('35%');
$kpiTable->addCell()->addText('30%');

$report->addContentControl($kpiTable, [
    'alias' => 'Tabela de KPIs',
    'tag' => 'kpi-table',
    'lockType' => ContentControl::LOCK_SDT_LOCKED
]);

// Salvar relatório
$reportFile = __DIR__ . '/output/relatorio_mensal.docx';
$report->save($reportFile);
echo "   ✓ Salvo em: {$reportFile}\n\n";

// Resumo final
echo "✅ 3 documentos criados com sucesso!\n\n";
echo "📋 Casos de uso demonstrados:\n";
echo "   1. Contrato Comercial - Proteção de campos específicos\n";
echo "   2. Formulário de Cadastro - Campos editáveis controlados\n";
echo "   3. Relatório Mensal - Seções fixas com dados protegidos\n\n";
echo "💡 Dica: Abra os arquivos no Word e ative 'Design Mode' para\n";
echo "   visualizar os Content Controls (Developer Tab).\n\n";


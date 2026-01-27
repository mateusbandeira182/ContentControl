<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * @property string $tempDir
 */
describe('IOFactory - Save with Content Controls', function () {
    
    beforeEach(function () {
        // Criar diretório temporário para testes usando um nome mais robusto
        $this->tempDir = sys_get_temp_dir() . '/phpword_test_' . bin2hex(random_bytes(16));
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    });
    
    afterEach(function () {
        // Limpar arquivos temporários (falhas devem falhar o teste)
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files === false) {
                $files = [];
            }
            foreach ($files as $file) {
                if (is_file($file)) {
                    expect(unlink($file))->toBeTrue(
                        sprintf('Failed to delete temporary file "%s"', $file)
                    );
                }
            }
            expect(rmdir($this->tempDir))->toBeTrue(
                sprintf('Failed to remove temporary directory "%s"', $this->tempDir)
            );
        }
    });
    
    test('salva documento com Content Control único', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Documento com Content Control');
        
        $control = new ContentControl($section, [
            'alias' => 'Campo Principal',
            'tag' => 'main-field',
            'type' => ContentControl::TYPE_RICH_TEXT,
        ]);
        
        $filename = $this->tempDir . '/test-single-control.docx';
        
        $result = IOFactory::saveWithContentControls(
            $phpWord,
            [$control],
            $filename
        );
        
        expect($result)->toBeTrue();
        expect(file_exists($filename))->toBeTrue();
        expect(filesize($filename))->toBeGreaterThan(0);
    });
    
    test('salva documento com múltiplos Content Controls', function () {
        $phpWord = new PhpWord();
        
        // Control 1: Texto
        $section1 = $phpWord->addSection();
        $section1->addText('Campo 1');
        $control1 = new ContentControl($section1, ['tag' => 'field-1']);
        
        // Control 2: Tabela
        $section2 = $phpWord->addSection();
        $table = $section2->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Dados');
        $control2 = new ContentControl($section2, ['tag' => 'field-2']);
        
        $filename = $this->tempDir . '/test-multiple-controls.docx';
        
        $result = IOFactory::saveWithContentControls(
            $phpWord,
            [$control1, $control2],
            $filename
        );
        
        expect($result)->toBeTrue();
        expect(file_exists($filename))->toBeTrue();
    });
    
    test('valida estrutura XML do documento salvo', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Teste de validação');
        
        $control = new ContentControl($section, [
            'alias' => 'Validação',
            'tag' => 'validation-field',
            'lockType' => ContentControl::LOCK_SDT_LOCKED,
        ]);
        
        $filename = $this->tempDir . '/test-validation.docx';
        
        IOFactory::saveWithContentControls($phpWord, [$control], $filename);
        
        // Abrir ZIP e ler document.xml
        $zip = new ZipArchive();
        $openResult = $zip->open($filename);
        if ($openResult !== true) {
            throw new \RuntimeException('Failed to open DOCX file as ZIP for validation. Error code: ' . $openResult);
        }
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        // Validar presença de Content Control
        expect($documentXml)->toContain('<w:sdt')
            ->and($documentXml)->toContain('<w:alias w:val="Validação"/>')
            ->and($documentXml)->toContain('<w:tag w:val="validation-field"/>')
            ->and($documentXml)->toContain('<w:lock w:val="sdtLocked"/>');
    });
    
    test('retorna false para caminho inválido', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section);
        
        // Caminho inválido (diretório inexistente)
        $invalidPath = '/caminho/invalido/inexistente/arquivo.docx';
        
        // Use @ to suppress expected warnings from this method call
        $result = @IOFactory::saveWithContentControls(
            $phpWord,
            [$control],
            $invalidPath
        );
        
        expect($result)->toBeFalse();
    });
    
    test('ignora elementos que não são ContentControl', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Apenas texto');
        
        $control = new ContentControl($section);
        
        $filename = $this->tempDir . '/test-mixed-elements.docx';
        
        // Passar array com ContentControl e elemento inválido
        $result = IOFactory::saveWithContentControls(
            $phpWord,
            [$control, 'string-invalida', null],
            $filename
        );
        
        expect($result)->toBeTrue();
        expect(file_exists($filename))->toBeTrue();
    });
    
    test('createWriter retorna Writer válido', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Test content');
        
        $writer = IOFactory::createWriter($phpWord);
        
        expect($writer)->toBeInstanceOf(\PhpOffice\PhpWord\Writer\WriterInterface::class);
    });
});

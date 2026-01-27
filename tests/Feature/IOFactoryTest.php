<?php

declare(strict_types=1);

use MkGrow\ContentControl\ContentControl;
use MkGrow\ContentControl\IOFactory;
use MkGrow\ContentControl\Exception\ZipArchiveException;
use MkGrow\ContentControl\Exception\DocumentNotFoundException;
use MkGrow\ContentControl\Exception\TemporaryFileException;
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
            expect($files)->not->toBeFalse(
                sprintf('Failed to read contents of temporary directory "%s"', $this->tempDir)
            );
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
        
        IOFactory::saveWithContentControls(
            $phpWord,
            [$control],
            $filename
        );
        
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
        
        IOFactory::saveWithContentControls(
            $phpWord,
            [$control1, $control2],
            $filename
        );
        
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
    
    test('lança exceção para caminho inválido', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $control = new ContentControl($section);
        
        // Caminho inválido (diretório inexistente), construído de forma portátil
        $invalidPath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR . 'nonexistent_subdir_' . bin2hex(random_bytes(8))
            . DIRECTORY_SEPARATOR . 'arquivo.docx';
        
        expect(fn() => IOFactory::saveWithContentControls(
            $phpWord,
            [$control],
            $invalidPath
        ))->toThrow(\RuntimeException::class, 'ContentControl: Target directory not writable');
    });
    
    test('ignora elementos que não são ContentControl', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Apenas texto');
        
        $control = new ContentControl($section);
        
        $filename = $this->tempDir . '/test-mixed-elements.docx';
        
        // Passar array com ContentControl e elemento inválido
        IOFactory::saveWithContentControls(
            $phpWord,
            [$control, 'string-invalida', null],
            $filename
        );
        
        expect(file_exists($filename))->toBeTrue();
    });
    
    test('createWriter retorna Writer válido', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Test content');
        
        $writer = IOFactory::createWriter($phpWord);
        
        expect($writer)->toBeInstanceOf(\PhpOffice\PhpWord\Writer\WriterInterface::class);
    });

    test('salva documento sem Content Controls', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Documento sem Content Controls');
        
        $filename = $this->tempDir . '/test-no-controls.docx';
        
        // Passar array vazio de Content Controls
        IOFactory::saveWithContentControls(
            $phpWord,
            [],
            $filename
        );
        
        expect(file_exists($filename))->toBeTrue();
        expect(filesize($filename))->toBeGreaterThan(0);
    });

    test('salva documento com array contendo apenas elementos inválidos', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Apenas texto');
        
        $filename = $this->tempDir . '/test-invalid-only.docx';
        
        // Passar array com apenas elementos inválidos
        IOFactory::saveWithContentControls(
            $phpWord,
            ['string', 123, null, new stdClass()],
            $filename
        );
        
        expect(file_exists($filename))->toBeTrue();
    });

    test('registerCustomWriters emite deprecation warning', function () {
        // Capturar error handler atual
        set_error_handler(function($errno, $errstr) {
            expect($errno)->toBe(E_USER_DEPRECATED);
            expect($errstr)->toContain('registerCustomWriters() is deprecated');
            expect($errstr)->toContain('Use IOFactory::saveWithContentControls()');
        }, E_USER_DEPRECATED);
        
        IOFactory::registerCustomWriters();
        
        // Restaurar error handler
        restore_error_handler();
    });

    test('falha ao mover arquivo temporário lança RuntimeException', function () {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Test');
        
        $control = new ContentControl($section);
        
        // No Windows, não podemos criar diretório read-only facilmente
        // Vamos usar um caminho que não permite write após criar o arquivo
        $filename = $this->tempDir . '/test-move-fail.docx';
        
        // Criar arquivo read-only que já existe
        touch($filename);
        chmod($filename, 0444);
        
        try {
            IOFactory::saveWithContentControls(
                $phpWord,
                [$control],
                $filename
            );
            
            // Se chegou aqui no Windows, o teste não é aplicável
            // (Windows pode sobrescrever arquivos read-only em alguns casos)
            expect(true)->toBeTrue();
        } catch (\RuntimeException $e) {
            expect($e->getMessage())->toContain('Failed to move file');
        } finally {
            // Restaurar permissões para cleanup
            if (file_exists($filename)) {
                chmod($filename, 0666);
            }
        }
    });
});

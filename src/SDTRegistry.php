<?php

declare(strict_types=1);

namespace MkGrow\ContentControl;

/**
 * Registry para gerenciar IDs únicos e mapeamento elemento→config
 * 
 * Responsável por:
 * - Gerar IDs únicos de 8 dígitos sem colisão
 * - Registrar elementos com suas configurações SDT
 * - Detectar elementos duplicados
 * - Marcar IDs como usados
 * 
 * @since 2.0.0
 */
final class SDTRegistry
{
    /**
     * Registry de elementos e configurações
     * 
     * Estrutura: [['element' => $obj, 'config' => $cfg], ...]
     * 
     * @var array<int, array{element: mixed, config: SDTConfig}>
     */
    private array $registry = [];

    /**
     * IDs já utilizados (para evitar colisão)
     * 
     * Estrutura: ['12345678' => true, ...]
     * 
     * @var array<int|string, true>
     */
    private array $usedIds = [];

    /**
     * Gera ID único de 8 dígitos
     * 
     * Tenta até 100 vezes gerar um ID que não esteja em uso.
     * Probabilidade de colisão em 10.000 IDs: ~0.01%
     * 
     * @return string ID único de 8 dígitos
     */
    public function generateUniqueId(): string
    {
        $maxAttempts = 100;
        
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $id = (string) random_int(10000000, 99999999);
            
            if (!isset($this->usedIds[$id])) {
                $this->usedIds[$id] = true;
                return $id;
            }
        }
        
        // @phpstan-ignore-next-line - Código teoricamente inalcançável, mas mantido por segurança
        throw new \RuntimeException(
            sprintf(
                'SDTRegistry: Failed to generate unique ID after %d attempts. Total IDs in use: %d',
                $maxAttempts,
                count($this->usedIds)
            )
        );
    }

    /**
     * Registra elemento com sua configuração SDT
     * 
     * @param mixed $element Elemento PHPWord (Section, Table, etc)
     * @param SDTConfig $config Configuração do Content Control
     * @return void
     * @throws \InvalidArgumentException Se elemento já registrado
     * @throws \InvalidArgumentException Se ID da config já está em uso
     */
    public function register($element, SDTConfig $config): void
    {
        // Detectar elemento duplicado (comparação por identidade)
        foreach ($this->registry as $entry) {
            if ($entry['element'] === $element) {
                throw new \InvalidArgumentException(
                    'SDTRegistry: Element already registered'
                );
            }
        }

        // Verificar se ID já está em uso (se não for vazio)
        if ($config->id !== '' && isset($this->usedIds[$config->id])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'SDTRegistry: ID "%s" already in use',
                    $config->id
                )
            );
        }

        // Marcar ID como usado (se não for vazio)
        if ($config->id !== '') {
            $this->usedIds[$config->id] = true;
        }

        // Adicionar ao registry
        $this->registry[] = ['element' => $element, 'config' => $config];
    }

    /**
     * Retorna todas as tuplas (elemento, config) registradas
     * 
     * @return array<int, array{element: mixed, config: SDTConfig}>
     */
    public function getAll(): array
    {
        return $this->registry;
    }

    /**
     * Retorna configuração de um elemento específico
     * 
     * @param mixed $element Elemento a buscar
     * @return SDTConfig|null Configuração ou null se não encontrado
     */
    public function getConfig($element): ?SDTConfig
    {
        foreach ($this->registry as $entry) {
            if ($entry['element'] === $element) {
                return $entry['config'];
            }
        }
        
        return null;
    }

    /**
     * Verifica se elemento está registrado
     * 
     * @param mixed $element Elemento a verificar
     * @return bool true se registrado, false caso contrário
     */
    public function has($element): bool
    {
        foreach ($this->registry as $entry) {
            if ($entry['element'] === $element) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Verifica se ID está em uso
     * 
     * @param string $id ID a verificar
     * @return bool true se ID em uso, false caso contrário
     */
    public function isIdUsed(string $id): bool
    {
        return isset($this->usedIds[$id]);
    }

    /**
     * Retorna contagem de elementos registrados
     * 
     * @return int Número de elementos
     */
    public function count(): int
    {
        return count($this->registry);
    }

    /**
     * Limpa todos os registros (útil para testes)
     * 
     * @return void
     */
    public function clear(): void
    {
        $this->registry = [];
        $this->usedIds = [];
    }
}

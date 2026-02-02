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
     * Contador sequencial para fallback quando geração aleatória falhar
     * 
     * Inicia no ID mínimo permitido (10000000)
     * 
     * @var int
     */
    private int $sequentialCounter = 10000000;

    /**
     * Marcadores de elementos (para localização rápida no futuro)
     * 
     * Estrutura: [objectId => markerId]
     * 
     * @var array<int, string>
     */
    private array $elementMarkers = [];

    /**
     * Gera ID único de 8 dígitos
     * 
     * Tenta até 100 vezes gerar um ID aleatório que não esteja em uso.
     * Se todas as tentativas falharem, usa contador sequencial como fallback.
     * 
     * Probabilidade de colisão em 10.000 IDs: ~0.01%
     * Fallback garante sucesso mesmo em ranges saturados.
     * 
     * IMPORTANTE: O ID retornado NÃO é automaticamente marcado como usado.
     * Isso ocorre em register() para evitar marcar IDs que nunca serão registrados.
     * 
     * @return string ID único de 8 dígitos
     */
    public function generateUniqueId(): string
    {
        $maxAttempts = 100;
        
        // Tentar geração aleatória
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $id = IDValidator::generateRandom();
            
            if (!isset($this->usedIds[$id])) {
                return $id;
            }
        }
        
        // Fallback: usar contador sequencial
        while (isset($this->usedIds[(string) $this->sequentialCounter])) {
            $this->sequentialCounter++;
            
            // Proteção contra overflow (teoricamente inalcançável)
            if ($this->sequentialCounter > IDValidator::getMaxId()) {
                $maxId = IDValidator::getMaxId();  
                $minId = IDValidator::getMinId();  
                $rangeSize = $maxId - $minId + 1;  

                throw new \RuntimeException(  
                    sprintf(  
                        'SDTRegistry: ID range exhausted during sequential fallback. Sequential counter reached %d (min ID: %d, max ID: %d, range size: %d). Total IDs marked as used: %d',  
                        $this->sequentialCounter,  
                        $minId,  
                        $maxId,  
                        $rangeSize,
                        count($this->usedIds)
                    )
                );
            }
        }
        
        $id = str_pad((string) $this->sequentialCounter, 8, '0', STR_PAD_LEFT);
        $this->sequentialCounter++;
        
        return $id;
    }

    /**
     * Registra elemento com sua configuração SDT (v3.0 - com marcador)
     * 
     * @param object $element Elemento PHPWord (Section, Table, etc)
     * @param SDTConfig $config Configuração do Content Control
     * @return void
     * @throws \InvalidArgumentException Se elemento já registrado
     * @throws \InvalidArgumentException Se ID da config já está em uso
     */
    public function register(object $element, SDTConfig $config): void
    {
        // 1. Detectar elemento duplicado PRIMEIRO (comparação por identidade)
        foreach ($this->registry as $entry) {
            if ($entry['element'] === $element) {
                throw new \InvalidArgumentException(
                    'SDTRegistry: Element already registered'
                );
            }
        }

        // 2. Verificar ID duplicado ANTES de marcar como usado
        if ($config->id !== '' && isset($this->usedIds[$config->id])) {
            // Procurar se já existe outro elemento com esse ID no registry
                throw new \InvalidArgumentException(
                sprintf(
                    'SDTRegistry: ID "%s" is already in use and cannot be reused',
                    $config->id
                )
            );
        }

        // 3. Marcar ID como usado APENAS SE PASSAR nas validações
        if ($config->id !== '') {
            $this->usedIds[$config->id] = true;
        }

        // 4. Gerar marcador (v3.0)
        $marker = ElementIdentifier::generateMarker($element);
        $objectId = spl_object_id($element);
        $this->elementMarkers[$objectId] = $marker;

        // 5. Adicionar ao registry
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
     * @param object $element Elemento a buscar
     * @return SDTConfig|null Configuração ou null se não encontrado
     */
    public function getConfig(object $element): ?SDTConfig
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
     * @param object $element Elemento a verificar
     * @return bool true se registrado, false caso contrário
     */
    public function has(object $element): bool
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
     * Marca ID como usado (útil para testes)
     * 
     * @param string $id ID a marcar como usado
     * @return void
     * @throws \InvalidArgumentException Se ID inválido
     */
    public function markIdAsUsed(string $id): void
    {
        IDValidator::validate($id);
        $this->usedIds[$id] = true;
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
        $this->elementMarkers = [];
    }

    /**
     * Retorna marcador de um elemento (v3.0)
     * 
     * @param object $element Elemento PHPWord
     * @return string|null Marcador ou null se não registrado
     */
    public function getMarkerForElement(object $element): ?string
    {
        $objectId = spl_object_id($element);
        return $this->elementMarkers[$objectId] ?? null;
    }

    /**
     * Retorna todos os marcadores registrados (v3.0)
     * 
     * @return array<int, string> Mapa objectId → markerId
     */
    public function getAllMarkers(): array
    {
        return $this->elementMarkers;
    }

    /**
     * Retorna todas as configs registradas (v3.1)
     * 
     * Útil para testes e debugging.
     * 
     * @return list<SDTConfig> Array de configs
     */
    public function getAllConfigs(): array
    {
        return array_values(array_map(
            fn(array $entry): SDTConfig => $entry['config'],
            $this->registry
        ));
    }
}

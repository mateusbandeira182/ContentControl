<?php

declare(strict_types=1);

namespace MkGrow\ContentControl;

/**
 * Registry for managing unique IDs and element->config mapping
 * 
 * Responsible for:
 * - Generating unique 8-digit IDs without collision
 * - Registering elements with their SDT configurations
 * - Detecting duplicate elements
 * - Marking IDs as used
 * 
 * @since 2.0.0
 */
final class SDTRegistry
{
    /**
     * Registry of elements and configurations
     * 
     * Structure: [['element' => $obj, 'config' => $cfg], ...]
     * 
     * @var array<int, array{element: mixed, config: SDTConfig}>
     */
    private array $registry = [];

    /**
     * IDs already used (to avoid collision)
     * 
     * Structure: ['12345678' => true, ...]
     * 
     * @var array<int|string, true>
     */
    private array $usedIds = [];

    /**
     * Sequential counter for fallback when random generation fails
     * 
     * Starts at minimum allowed ID (10000000)
     * 
     * @var int
     */
    private int $sequentialCounter = 10000000;

    /**
     * Element markers (for fast lookup in the future)
     * 
     * Structure: [objectId => markerId]
     * 
     * @var array<int, string>
     */
    private array $elementMarkers = [];

    /**
     * Generates unique 8-digit ID
     * 
     * Tries up to 100 times to generate a random ID that is not in use.
     * If all attempts fail, uses sequential counter as fallback.
     * 
     * Collision probability in 10,000 IDs: ~0.01%
     * Fallback ensures success even in saturated ranges.
     * 
     * IMPORTANT: The returned ID is NOT automatically marked as used.
     * This occurs in register() to avoid marking IDs that will never be registered.
     * 
     * @return string Unique 8-digit ID
     */
    public function generateUniqueId(): string
    {
        $maxAttempts = 100;
        
        // Try random generation
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $id = IDValidator::generateRandom();
            
            if (!isset($this->usedIds[$id])) {
                return $id;
            }
        }
        
        // Fallback: use sequential counter
        while (isset($this->usedIds[(string) $this->sequentialCounter])) {
            $this->sequentialCounter++;
            
            // Overflow protection (theoretically unreachable)
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
     * Registers element with its SDT configuration (v3.0 - with marker)
     * 
     * @param object $element PHPWord Element (Section, Table, etc)
     * @param SDTConfig $config Content Control Configuration
     * @return void
     * @throws \InvalidArgumentException If element already registered
     * @throws \InvalidArgumentException If config ID is already in use
     */
    public function register(object $element, SDTConfig $config): void
    {
        // 1. Detect duplicate element FIRST (identity comparison)
        foreach ($this->registry as $entry) {
            if ($entry['element'] === $element) {
                throw new \InvalidArgumentException(
                    'SDTRegistry: Element already registered'
                );
            }
        }

        // 2. Verify duplicate ID BEFORE marking as used
        if ($config->id !== '' && isset($this->usedIds[$config->id])) {
            // Check if there is another element with this ID in the registry
                throw new \InvalidArgumentException(
                sprintf(
                    'SDTRegistry: ID "%s" is already in use and cannot be reused',
                    $config->id
                )
            );
        }

        // 3. Mark ID as used ONLY IF validations pass
        if ($config->id !== '') {
            $this->usedIds[$config->id] = true;
        }

        // 4. Generate marker (v3.0)
        $marker = ElementIdentifier::generateMarker($element);
        $objectId = spl_object_id($element);
        $this->elementMarkers[$objectId] = $marker;

        // 5. Add to registry
        $this->registry[] = ['element' => $element, 'config' => $config];
    }

    /**
     * Returns all registered (element, config) tuples
     * 
     * @return array<int, array{element: mixed, config: SDTConfig}>
     */
    public function getAll(): array
    {
        return $this->registry;
    }

    /**
     * Returns configuration for a specific element
     * 
     * @param object $element Element to search for
     * @return SDTConfig|null Configuration or null if not found
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
     * Checks if element is registered
     * 
     * @param object $element Element to check
     * @return bool true if registered, false otherwise
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
     * Checks if ID is in use
     * 
     * @param string $id ID to check
     * @return bool true if ID is in use, false otherwise
     */
    public function isIdUsed(string $id): bool
    {
        return isset($this->usedIds[$id]);
    }

    /**
     * Marks ID as used (useful for testing)
     * 
     * @param string $id ID to mark as used
     * @return void
     * @throws \InvalidArgumentException If ID is invalid
     */
    public function markIdAsUsed(string $id): void
    {
        IDValidator::validate($id);
        $this->usedIds[$id] = true;
    }

    /**
     * Returns count of registered elements
     * 
     * @return int Number of elements
     */
    public function count(): int
    {
        return count($this->registry);
    }

    /**
     * Clears all registries (useful for testing)
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
     * Returns element marker (v3.0)
     * 
     * @param object $element PHPWord Element
     * @return string|null Marker or null if not registered
     */
    public function getMarkerForElement(object $element): ?string
    {
        $objectId = spl_object_id($element);
        return $this->elementMarkers[$objectId] ?? null;
    }

    /**
     * Returns all registered markers (v3.0)
     * 
     * @return array<int, string> Map objectId -> markerId
     */
    public function getAllMarkers(): array
    {
        return $this->elementMarkers;
    }

    /**
     * Returns all registered configs (v3.1)
     * 
     * Useful for testing and debugging.
     * 
     * @return list<SDTConfig> Array of configs
     */
    public function getAllConfigs(): array
    {
        return array_values(array_map(
            fn(array $entry): SDTConfig => $entry['config'],
            $this->registry
        ));
    }
}

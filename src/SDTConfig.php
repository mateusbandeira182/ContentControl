<?php

declare(strict_types=1);

namespace MkGrow\ContentControl;

/**
 * Value Object para configuração de Content Control (SDT)
 * 
 * Classe imutável que encapsula todas as propriedades de um
 * Structured Document Tag conforme ISO/IEC 29500-1:2016 §17.5.2
 * 
 * @since 2.0.0
 */
final class SDTConfig
{
    /**
     * Cria configuração imutável de Content Control
     * 
     * @param string $id Identificador único (8 dígitos: 10000000-99999999)
     * @param string $alias Nome amigável exibido no Word (max 255 chars)
     * @param string $tag Tag de metadados para identificação programática
     * @param string $type Tipo do controle (TYPE_RICH_TEXT, TYPE_PLAIN_TEXT, etc)
     * @param string $lockType Nível de bloqueio (LOCK_NONE, LOCK_SDT_LOCKED, etc)
     * 
     * @throws \InvalidArgumentException Se algum parâmetro for inválido
     */
    public function __construct(
        public readonly string $id,
        public readonly string $alias = '',
        public readonly string $tag = '',
        public readonly string $type = ContentControl::TYPE_RICH_TEXT,
        public readonly string $lockType = ContentControl::LOCK_NONE,
    ) {
        $this->validateId($id);
        $this->validateAlias($alias);
        $this->validateTag($tag);
        $this->validateType($type);
        $this->validateLockType($lockType);
    }

    /**
     * Cria SDTConfig a partir de array de opções
     * 
     * @param array{
     *     id?: string,
     *     alias?: string,
     *     tag?: string,
     *     type?: string,
     *     lockType?: string
     * } $options Configurações do Content Control
     * 
     * @return self
     * @throws \InvalidArgumentException Se opções inválidas
     * 
     * @example
     * ```php
     * $config = SDTConfig::fromArray([
     *     'id' => '12345678',
     *     'alias' => 'Nome do Cliente',
     *     'tag' => 'customer-name',
     *     'type' => ContentControl::TYPE_RICH_TEXT
     * ]);
     * ```
     */
    public static function fromArray(array $options): self
    {
        return new self(
            id: $options['id'] ?? '',
            alias: $options['alias'] ?? '',
            tag: $options['tag'] ?? '',
            type: $options['type'] ?? ContentControl::TYPE_RICH_TEXT,
            lockType: $options['lockType'] ?? ContentControl::LOCK_NONE,
        );
    }

    /**
     * Retorna nova instância com ID diferente (imutabilidade)
     * 
     * @param string $id Novo identificador
     * @return self Nova instância com ID atualizado
     * @throws \InvalidArgumentException Se ID inválido
     */
    public function withId(string $id): self
    {
        return new self(
            id: $id,
            alias: $this->alias,
            tag: $this->tag,
            type: $this->type,
            lockType: $this->lockType,
        );
    }

    /**
     * Retorna nova instância com alias diferente (imutabilidade)
     * 
     * @param string $alias Novo nome amigável
     * @return self Nova instância com alias atualizado
     * @throws \InvalidArgumentException Se alias inválido
     */
    public function withAlias(string $alias): self
    {
        return new self(
            id: $this->id,
            alias: $alias,
            tag: $this->tag,
            type: $this->type,
            lockType: $this->lockType,
        );
    }

    /**
     * Retorna nova instância com tag diferente (imutabilidade)
     * 
     * @param string $tag Nova tag de metadados
     * @return self Nova instância com tag atualizada
     * @throws \InvalidArgumentException Se tag inválida
     */
    public function withTag(string $tag): self
    {
        return new self(
            id: $this->id,
            alias: $this->alias,
            tag: $tag,
            type: $this->type,
            lockType: $this->lockType,
        );
    }

    /**
     * Valida e normaliza o ID do Content Control
     * 
     * O ID deve ser um número de 8 dígitos entre 10000000 e 99999999.
     * 
     * Especificação: ISO/IEC 29500-1:2016 §17.5.2.14
     * 
     * @param string $id Valor a ser validado
     * @throws \InvalidArgumentException Se ID inválido
     * @return void
     */
    private function validateId(string $id): void
    {
        IDValidator::validate($id);
    }

    /**
     * Valida o valor do alias
     * 
     * O alias é um nome amigável exibido no Word. Esta validação garante:
     * - Comprimento máximo de 255 caracteres (limite prático para exibição)
     * - Não contém caracteres de controle (0x00-0x1F, 0x7F-0x9F)
     * 
     * @param string $alias Valor a ser validado
     * @throws \InvalidArgumentException Se alias inválido
     * @return void
     */
    private function validateAlias(string $alias): void
    {
        // Permitir string vazia
        if ($alias === '') {
            return;
        }

        // Limite de comprimento razoável para exibição
        $length = mb_strlen($alias, 'UTF-8');
        if ($length > 255) {
            throw new \InvalidArgumentException(
                sprintf('SDTConfig: Alias must not exceed 255 characters, got %d characters', $length)
            );
        }

        // Verificar caracteres de controle que podem causar problemas
        // Bloqueia C0 controls (0x00-0x1F) e C1 controls (0x7F-0x9F)
        if (preg_match('/[\x00-\x1F\x7F-\x9F]/u', $alias) === 1) {
            throw new \InvalidArgumentException(
                'SDTConfig: Alias must not contain control characters'
            );
        }

        // Verificar caracteres reservados XML que podem causar problemas de parsing
        if (preg_match('/[<>&"\']/', $alias) === 1) {
            throw new \InvalidArgumentException(
                'SDTConfig: Alias contains XML reserved characters'
            );
        }
    }

    /**
     * Valida o valor da tag
     * 
     * A tag é um identificador de metadados para uso programático. Esta validação garante:
     * - Comprimento máximo de 255 caracteres
     * - Apenas caracteres alfanuméricos, hífens, underscores e pontos
     * - Deve começar com letra ou underscore (convenção de identificadores)
     * 
     * @param string $tag Valor a ser validado
     * @throws \InvalidArgumentException Se tag inválida
     * @return void
     */
    private function validateTag(string $tag): void
    {
        // Permitir string vazia
        if ($tag === '') {
            return;
        }

        // Limite de comprimento
        $length = mb_strlen($tag, 'UTF-8');
        if ($length > 255) {
            throw new \InvalidArgumentException(
                sprintf('SDTConfig: Tag must not exceed 255 characters, got %d characters', $length)
            );
        }

        // Tag deve seguir padrão de identificador: começa com letra ou _, depois alfanumérico, -, _, .
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]*$/', $tag) !== 1) {
            throw new \InvalidArgumentException(
                'SDTConfig: Tag must start with a letter or underscore and contain only alphanumeric characters, hyphens, underscores, and periods'
            );
        }
    }

    /**
     * Valida tipo do Content Control
     * 
     * @param string $type Valor a ser validado
     * @throws \InvalidArgumentException Se tipo inválido
     * @return void
     */
    private function validateType(string $type): void
    {
        $validTypes = [
            ContentControl::TYPE_GROUP,
            ContentControl::TYPE_PLAIN_TEXT,
            ContentControl::TYPE_RICH_TEXT,
            ContentControl::TYPE_PICTURE,
        ];

        if (!in_array($type, $validTypes, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'SDTConfig: Invalid type "%s". Must be one of: %s',
                    $type,
                    implode(', ', $validTypes)
                )
            );
        }
    }

    /**
     * Valida nível de bloqueio do Content Control
     * 
     * @param string $lockType Valor a ser validado
     * @throws \InvalidArgumentException Se lockType inválido
     * @return void
     */
    private function validateLockType(string $lockType): void
    {
        $validLockTypes = [
            ContentControl::LOCK_NONE,
            ContentControl::LOCK_SDT_LOCKED,
            ContentControl::LOCK_CONTENT_LOCKED,
            ContentControl::LOCK_UNLOCKED,
        ];

        if (!in_array($lockType, $validLockTypes, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'SDTConfig: Invalid lock type "%s". Must be one of: %s',
                    $lockType,
                    implode(', ', $validLockTypes)
                )
            );
        }
    }
}

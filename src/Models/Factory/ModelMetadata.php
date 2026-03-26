<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Models\Factory;

class ModelMetadata
{
    /**
     * @var array<string, self>
     */
    protected static $instances = [];

    /**
     * @var string
     */
    protected $modelClass;

    /**
     * @var array
     */
    protected $classFields;

    /**
     * @var array<string, string>
     */
    protected $columnNames = [];

    /**
     * @var string
     */
    protected $primaryKey;

    /**
     * @var string
     */
    protected $handleField;

    /**
     * @var string
     */
    protected $tableName;

    /**
     * @var string
     */
    protected $rootClass;

    /**
     * @var bool
     */
    protected $hasClassField;

    /**
     * @var string|null
     */
    protected $classColumnName;

    /**
     * @var array
     */
    protected $handleExceptionCallback;

    /**
     * @var array<int, string>
     */
    protected $persistedFields = [];

    /**
     * @var array<string, array>
     */
    protected $persistedFieldConfigs = [];

    /**
     * @var bool
     */
    protected $versioned;

    /**
     * @var bool
     */
    protected $relational;

    /**
     * @var bool
     */
    protected $hasCreatedField;

    /**
     * @var bool
     */
    protected $integerPrimaryKey;

    public static function get(string $modelClass): self
    {
        if (!isset(static::$instances[$modelClass])) {
            static::$instances[$modelClass] = new static($modelClass);
        }

        return static::$instances[$modelClass];
    }

    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
        $this->classFields = $modelClass::getClassFields();
        $this->primaryKey = $modelClass::$primaryKey ?: 'ID';
        $this->handleField = $modelClass::$handleField;
        $this->tableName = $modelClass::$tableName;
        $this->rootClass = $modelClass::getRootClassName();
        $this->handleExceptionCallback = [$modelClass, 'handleException'];
        $this->versioned = $modelClass::isVersioned();
        $this->relational = $modelClass::isRelational();
        $this->hasClassField = array_key_exists('Class', $this->classFields);
        $this->classColumnName = $this->hasClassField ? $this->classFields['Class']['columnName'] : null;
        $this->hasCreatedField = array_key_exists('Created', $this->classFields);
        $this->integerPrimaryKey = (($this->classFields[$this->primaryKey]['type'] ?? null) === 'integer');

        foreach ($this->classFields as $field => $options) {
            $this->columnNames[$field] = $options['columnName'];

            if (!empty($options['autoincrement'])) {
                continue;
            }

            if ($this->versioned && $field === 'RevisionID') {
                continue;
            }

            $this->persistedFields[] = $field;
            $this->persistedFieldConfigs[$field] = $options;
        }
    }

    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    public function getClassFields(): array
    {
        return $this->classFields;
    }

    public function fieldExists(string $field): bool
    {
        return array_key_exists($field, $this->classFields);
    }

    public function getColumnName(string $field): string
    {
        return $this->columnNames[$field];
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function getHandleField(): string
    {
        return $this->handleField;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getRootClass(): string
    {
        return $this->rootClass;
    }

    public function hasClassField(): bool
    {
        return $this->hasClassField;
    }

    public function getClassColumnName(): ?string
    {
        return $this->classColumnName;
    }

    public function getHandleExceptionCallback(): array
    {
        return $this->handleExceptionCallback;
    }

    public function isVersioned(): bool
    {
        return $this->versioned;
    }

    public function isRelational(): bool
    {
        return $this->relational;
    }

    public function hasCreatedField(): bool
    {
        return $this->hasCreatedField;
    }

    public function hasIntegerPrimaryKey(): bool
    {
        return $this->integerPrimaryKey;
    }

    public function getPersistedFields(): array
    {
        return $this->persistedFields;
    }

    public function getPersistedFieldConfigs(): array
    {
        return $this->persistedFieldConfigs;
    }
}

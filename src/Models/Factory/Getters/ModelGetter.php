<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Models\Factory\Getters;

use Divergence\Helpers\Util;
use Divergence\Models\Factory;
use Divergence\IO\Database\Connections;
use Divergence\IO\Database\PostgreSQL;
use Divergence\IO\Database\Query\Select;

abstract class ModelGetter
{
    /**
     * @var Factory
     */
    protected $factory;

    public function __construct(Factory $factory)
    {
        $this->factory = $factory;
    }

    protected function getModelClass(): string
    {
        return $this->factory->getModelClass();
    }

    protected function getStorage()
    {
        return $this->factory->getStorage();
    }

    protected function instantiateRecord($record)
    {
        return $this->factory->instantiateRecord($record);
    }

    protected function instantiateRecords($records)
    {
        return $this->factory->instantiateRecords($records);
    }

    protected function fieldExists(string $field): bool
    {
        $className = $this->getModelClass();

        return $className::fieldExists($field);
    }

    protected function getColumnName(string $field)
    {
        $className = $this->getModelClass();

        return $className::getColumnName($field);
    }

    protected function mapFieldOrder($order)
    {
        $className = $this->getModelClass();

        return $className::mapFieldOrder($order);
    }

    protected function mapConditions($conditions)
    {
        $className = $this->getModelClass();

        return $className::mapConditions($conditions);
    }

    protected function getHandleExceptionCallback(): array
    {
        return [$this->getModelClass(), 'handleException'];
    }

    protected function getPrimaryKeyName(): string
    {
        $className = $this->getModelClass();

        return $className::getPrimaryKey();
    }

    protected function getHandleFieldName(): string
    {
        $className = $this->getModelClass();

        return $className::$handleField;
    }

    protected function getTableName(): string
    {
        $className = $this->getModelClass();

        return $className::$tableName;
    }

    protected function getSelectTableAlias(): string
    {
        return 'Record';
    }

    protected function prepareOptions($options, array $defaults)
    {
        return Util::prepareOptions($options, $defaults);
    }

    protected function newSelect(): Select
    {
        return new Select();
    }

    protected function buildExtraColumns($columns)
    {
        if (!empty($columns)) {
            if (is_array($columns)) {
                foreach ($columns as $key => $value) {
                    return ', '.$value.' AS '.$key;
                }
            } else {
                return ', ' . $columns;
            }
        }
    }

    protected function buildHaving($having, $extraColumns = null)
    {
        if (!empty($having)) {
            $having = $this->replaceExtraColumnAliasesInHaving($having, $extraColumns);

            return ' (' . (is_array($having) ? join(') AND (', $this->mapConditions($having)) : $having) . ')';
        }
    }

    protected function replaceExtraColumnAliasesInHaving($having, $extraColumns)
    {
        if (Connections::getConnectionType() !== PostgreSQL::class || empty($extraColumns)) {
            return $having;
        }

        $aliases = $this->extractExtraColumnAliases($extraColumns);

        if (empty($aliases)) {
            return $having;
        }

        $replaceAliases = function ($clause) use ($aliases) {
            foreach ($aliases as $alias => $expression) {
                $clause = str_replace(
                    ['`' . $alias . '`', '"' . $alias . '"', $alias],
                    ['(' . $expression . ')', '(' . $expression . ')', '(' . $expression . ')'],
                    $clause
                );
            }

            return $clause;
        };

        if (is_array($having)) {
            foreach ($having as $key => $clause) {
                if (is_string($clause)) {
                    $having[$key] = $replaceAliases($clause);
                }
            }

            return $having;
        }

        return is_string($having) ? $replaceAliases($having) : $having;
    }

    protected function extractExtraColumnAliases($columns): array
    {
        $aliases = [];
        $columns = is_array($columns) ? $columns : [$columns];

        foreach ($columns as $key => $value) {
            $column = is_string($key) ? $value . ' AS ' . $key : $value;

            if (!is_string($column)) {
                continue;
            }

            if (preg_match('/^(.*?)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', trim($column), $matches)) {
                $aliases[$matches[2]] = $matches[1];
            }
        }

        return $aliases;
    }
}

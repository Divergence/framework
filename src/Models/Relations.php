<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @phan-file-suppress PhanUndeclaredStaticProperty
 * @phan-file-suppress PhanUndeclaredStaticMethod
 * @phan-file-suppress PhanAccessSignatureMismatch
 */

namespace Divergence\Models;

use Exception;

/**
 * Relations.
 *
 * @package Divergence
 * @author  Henry Paradiz <henry.paradiz@gmail.com>
 *
 * @require-extends \Divergence\Models\ActiveRecord
 * @method mixed _getFieldValue($field, $useDefault = true)
 * @method mixed getPrimaryKeyValue()
 * @phan-type OneOneRelationship = array{type:'one-one', class:class-string<\Divergence\Models\ActiveRecord>, local:string, foreign:string}
 * @phan-type OneManyRelationship = array{type:'one-many', class:class-string<\Divergence\Models\ActiveRecord>, local:string, foreign:string, indexField:string|false, conditions:array<array-key, mixed>, order:array|string|false}
 * @phan-type ContextChildrenRelationship = array{type:'context-children', class:class-string<\Divergence\Models\ActiveRecord>, local:string, contextClass:class-string<\Divergence\Models\ActiveRecord>, indexField:string|false, conditions:array<array-key, mixed>, order:array|string|false}
 * @phan-type ContextParentRelationship = array{type:'context-parent', local:string, foreign:string, classField:string, allowedClasses:array|null}
 * @phan-type ManyManyRelationship = array{type:'many-many', class:class-string<\Divergence\Models\ActiveRecord>, linkClass:class-string<\Divergence\Models\ActiveRecord>, linkLocal:string, linkForeign:string, local:string, foreign:string, indexField:string|false, conditions:array<array-key, mixed>, order:array|string|false}
 * @phan-type HistoryRelationship = array{type:'history', class:class-string<\Divergence\Models\ActiveRecord>, order:array|string|false}
 * @phan-type NormalizedRelationship = OneOneRelationship|OneManyRelationship|ContextChildrenRelationship|ContextParentRelationship|ManyManyRelationship|HistoryRelationship
 */
trait Relations
{
    /**
     * @var array<string, mixed>
     */
    protected $_relatedObjects = [];

    /**
     * @param string $relationship
     * @return bool
     */
    public static function _relationshipExists($relationship)
    {
        if (is_array(static::$_classRelationships[get_called_class()])) {
            return array_key_exists($relationship, static::$_classRelationships[get_called_class()]);
        } else {
            return false;
        }
    }

    /**
     * Called when anything relationships related is used for the first time to define relationships before _initRelationships
     *
     * @return void
     */
    protected static function _defineRelationships()
    {
        $className = get_called_class();
        $classes = class_parents($className);
        array_unshift($classes, $className);
        static::$_classRelationships[$className] = [];
        while ($class = array_pop($classes)) {
            if (!empty($class::$relationships)) {
                static::$_classRelationships[$className] = array_merge(static::$_classRelationships[$className], $class::$relationships);
            }
            if (static::isVersioned() && !empty($class::$versioningRelationships)) {
                static::$_classRelationships[$className] = array_merge(static::$_classRelationships[$className], $class::$versioningRelationships);
            }
        }
    }


    /**
     * Called after _defineRelationships to initialize and apply defaults to the relationships property
     * Must be idempotent as it may be applied multiple times up the inheritence chain
     *
     * @return void
     */
    protected static function _initRelationships()
    {
        $className = get_called_class();
        if (!empty(static::$_classRelationships[$className])) {
            $relationships = [];
            foreach (static::$_classRelationships[$className] as $relationship => $options) {
                if (is_array($options)) {
                    $relationships[$relationship] = static::_initRelationship($relationship, $options);
                }
            }
            static::$_classRelationships[$className] = $relationships;
        }
    }

    /**
     * @param string $relationship
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected static function _prepareOneOne(string $relationship, array $options): array
    {
        $options['local'] = $options['local'] ?? $relationship . 'ID';
        $options['foreign'] = $options['foreign'] ?? 'ID';
        return $options;
    }

    /**
     * @param string $classShortName
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected static function _prepareOneMany(string $classShortName, array $options): array
    {
        $options['local'] = $options['local'] ?? 'ID';
        $options['foreign'] = $options['foreign'] ?? $classShortName. 'ID';
        $options['indexField'] = $options['indexField'] ?? false;
        $options['conditions'] = $options['conditions'] ?? [];
        $options['conditions'] = is_string($options['conditions']) ? [$options['conditions']] : $options['conditions'];
        $options['order'] = $options['order'] ?? false;
        return $options;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected static function _prepareContextChildren($options): array
    {
        $options['local'] = $options['local'] ?? 'ID';
        $options['contextClass'] = $options['contextClass'] ?? get_called_class();
        $options['indexField'] = $options['indexField'] ?? false;
        $options['conditions'] = $options['conditions'] ?? [];
        $options['conditions'] = is_string($options['conditions']) ? [$options['conditions']] : $options['conditions'];
        $options['order'] = $options['order'] ?? false;
        return $options;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected static function _prepareContextParent($options): array
    {
        $options['local'] = $options['local'] ?? 'ContextID';
        $options['foreign'] = $options['foreign'] ?? 'ID';
        $options['classField'] = $options['classField'] ?? 'ContextClass';
        $options['allowedClasses'] = $options['allowedClasses'] ?? (!empty(static::$contextClasses) ? static::$contextClasses : null);
        return $options;
    }

    /**
     * @param string $classShortName
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected static function _prepareManyMany($classShortName, $options): array
    {
        if (empty($options['class'])) {
            throw new Exception('Relationship type many-many option requires a class setting.');
        }

        if (empty($options['linkClass'])) {
            throw new Exception('Relationship type many-many option requires a linkClass setting.');
        }

        $options['linkLocal'] = $options['linkLocal'] ?? $classShortName . 'ID';
        $options['linkForeign'] = $options['linkForeign'] ?? basename(str_replace('\\', '/', $options['class']::getRootClassName())).'ID';
        $options['local'] = $options['local'] ?? 'ID';
        $options['foreign'] = $options['foreign'] ?? 'ID';
        $options['indexField'] = $options['indexField'] ?? false;
        $options['conditions'] = $options['conditions'] ?? [];
        $options['conditions'] = is_string($options['conditions']) ? [$options['conditions']] : $options['conditions'];
        $options['order'] = $options['order'] ?? false;
        return $options;
    }

    // TODO: Make relations getPrimaryKeyValue() instead of using ID all the time.
    /**
     * @param string $relationship
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected static function _initRelationship($relationship, $options)
    {
        $classShortName = basename(str_replace('\\', '/', static::getRootClassName()));

        // apply defaults
        if (empty($options['type'])) {
            $options['type'] = 'one-one';
        }

        switch ($options['type']) {
            case 'one-one':
                $options = static::_prepareOneOne($relationship, $options);
                break;
            case 'one-many':
                $options = static::_prepareOneMany($classShortName, $options);
                break;
            case 'context-children':
                $options = static::_prepareContextChildren($options);
                break;
            case 'context-parent':
                $options = static::_prepareContextParent($options);
                break;
            case 'many-many':
                $options = static::_prepareManyMany($classShortName, $options);
                break;
        }

        if (static::isVersioned() && $options['type'] == 'history') {
            if (empty($options['class'])) {
                $options['class'] = get_called_class();
            }
        }

        return $options;
    }

    /**
     * @return NormalizedRelationship|false
     */
    protected static function _getRelationshipDefinition(string $relationship)
    {
        return static::$_classRelationships[get_called_class()][$relationship];
    }

    /**
     * Retrieves given relationship's value
     * @param string $relationship Name of relationship
     * @return mixed value
     */
    protected function _getRelationshipValue($relationship)
    {
        if (!isset($this->_relatedObjects[$relationship])) {
            $rel = static::_getRelationshipDefinition($relationship);

            if ($rel === false) {
                $this->_relatedObjects[$relationship] = null;
            } elseif ($rel['type'] === 'one-one') {
                if ($value = $this->_getFieldValue($rel['local'])) {
                    $this->_relatedObjects[$relationship] = $rel['class']::getByField($rel['foreign'], $value);

                    // hook relationship for invalidation
                    static::$_classFields[get_called_class()][$rel['local']]['relationships'][$relationship] = true;
                } else {
                    $this->_relatedObjects[$relationship] = null;
                }
            } elseif ($rel['type'] === 'one-many') {
                if (!empty($rel['indexField']) && !$rel['class']::fieldExists($rel['indexField'])) {
                    $rel['indexField'] = false;
                }

                $this->_relatedObjects[$relationship] = $rel['class']::getAllByWhere(
                    array_merge($rel['conditions'], [
                        $rel['foreign'] => $this->_getFieldValue($rel['local']),
                    ]),
                    [
                        'indexField' => $rel['indexField'],
                        'order' => $rel['order'],
                        'conditions' => $rel['conditions'],
                    ]
                );


                // hook relationship for invalidation
                static::$_classFields[get_called_class()][$rel['local']]['relationships'][$relationship] = true;
            } elseif ($rel['type'] === 'context-children') {
                if (!empty($rel['indexField']) && !$rel['class']::fieldExists($rel['indexField'])) {
                    $rel['indexField'] = false;
                }

                $conditions = array_merge($rel['conditions'], [
                    'ContextClass' => $rel['contextClass'],
                    'ContextID' => $this->_getFieldValue($rel['local']),
                ]);

                $this->_relatedObjects[$relationship] = $rel['class']::getAllByWhere(
                    $conditions,
                    [
                        'indexField' => $rel['indexField'],
                        'order' => $rel['order'],
                    ]
                );

                // hook relationship for invalidation
                static::$_classFields[get_called_class()][$rel['local']]['relationships'][$relationship] = true;
            } elseif ($rel['type'] === 'context-parent') {
                $className = $this->_getFieldValue($rel['classField']);
                $this->_relatedObjects[$relationship] = $className ? $className::getByID($this->_getFieldValue($rel['local'])) : null;

                // hook both relationships for invalidation
                static::$_classFields[get_called_class()][$rel['classField']]['relationships'][$relationship] = true;
                static::$_classFields[get_called_class()][$rel['local']]['relationships'][$relationship] = true;
            } elseif ($rel['type'] === 'many-many') {
                if (!empty($rel['indexField']) && !$rel['class']::fieldExists($rel['indexField'])) {
                    $rel['indexField'] = false;
                }

                // TODO: support indexField, conditions, and order

                $this->_relatedObjects[$relationship] = $rel['class']::getAllByQuery(
                    'SELECT Related.* FROM `%s` Link JOIN `%s` Related ON (Related.`%s` = Link.%s) WHERE Link.`%s` = %u AND %s',
                    [
                        $rel['linkClass']::$tableName,
                        $rel['class']::$tableName,
                        $rel['foreign'],
                        $rel['linkForeign'],
                        $rel['linkLocal'],
                        $this->_getFieldValue($rel['local']),
                        $rel['conditions'] ? join(' AND ', $rel['conditions']) : '1',
                    ]
                );

                // hook relationship for invalidation
                static::$_classFields[get_called_class()][$rel['local']]['relationships'][$relationship] = true;
            } elseif ($rel['type'] === 'history' && static::isVersioned()) {
                $this->_relatedObjects[$relationship] = $rel['class']::getRevisionsByID($this->getPrimaryKeyValue(), $rel);
            }
        }

        return $this->_relatedObjects[$relationship];
    }
}

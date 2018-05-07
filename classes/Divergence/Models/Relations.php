<?php
namespace Divergence\Models;

use Divergence\IO\Database\MySQL as DB;

trait Relations
{
    public static function _relationshipExists($relationship)
    {
        if (is_array(static::$_classRelationships[get_called_class()])) {
            return array_key_exists($relationship, static::$_classRelationships[get_called_class()]);
        } else {
            return false;
        }
    }
    
    public function appendRelated($relationship, $values)
    {
        $rel = static::$_classRelationships[get_called_class()][$relationship];
        
        if ($rel['type'] != 'one-many') {
            throw new Exception('Can only append to one-many relationship');
        }
        
        if (!is_array($values)) {
            $values = [$values];
        }
        
        foreach ($values as $relatedObject) {
            if (!$relatedObject || !is_a($relatedObject, 'ActiveRecord')) {
                continue;
            }
            
            $relatedObject->_setFieldValue($rel['foreign'], $this->_getFieldValue($rel['local']));
            $this->_relatedObjects[$relationship][] = $relatedObject;
            $this->_isDirty = true;
        }
    }
    /**
     * Called when anything relationships related is used for the first time to define relationships before _initRelationships
     */
    protected static function _defineRelationships()
    {
        $className = get_called_class();
        
        // skip if fields already defined
        if (isset(static::$_classRelationships[$className])) {
            return;
        }
        
        // merge fields from first ancestor up
        $classes = class_parents($className);
        array_unshift($classes, $className);
        
        static::$_classRelationships[$className] = [];
        while ($class = array_pop($classes)) {
            if (!empty($class::$relationships)) {
                static::$_classRelationships[$className] = array_merge(static::$_classRelationships[$className], $class::$relationships);
            }
        }
    }

    
    /**
     * Called after _defineRelationships to initialize and apply defaults to the relationships property
     * Must be idempotent as it may be applied multiple times up the inheritence chain
     */
    protected static function _initRelationships()
    {
        $className = get_called_class();
        
        // apply defaults to relationship definitions
        if (!empty(static::$_classRelationships[$className])) {
            $relationships = [];
            
            foreach (static::$_classRelationships[$className] as $relationship => $options) {
                if (!$options) {
                    continue;
                }

                // store
                $relationships[$relationship] = static::_initRelationship($relationship, $options);
            }
            
            static::$_classRelationships[$className] = $relationships;
        }
    }
    
    
    protected static function _initRelationship($relationship, $options)
    {
        // sanity checks
        $className = get_called_class();
        
        if (is_string($options)) {
            $options = [
                'type' => 'one-one'
                ,'class' => $options,
            ];
        }
        
        if (!is_string($relationship) || !is_array($options)) {
            die('Relationship must be specified as a name => options pair');
        }
        
        // apply defaults
        if (empty($options['type'])) {
            $options['type'] = 'one-one';
        }
        
        if ($options['type'] == 'one-one') {
            if (empty($options['local'])) {
                $options['local'] = $relationship . 'ID';
            }
                
            if (empty($options['foreign'])) {
                $options['foreign'] = 'ID';
            }
        } elseif ($options['type'] == 'one-many') {
            if (empty($options['local'])) {
                $options['local'] = 'ID';
            }
                    
            if (empty($options['foreign'])) {
                $options['foreign'] = static::$rootClass . 'ID';
            }
                
            if (!isset($options['indexField'])) {
                $options['indexField'] = false;
            }
                
            if (!isset($options['conditions'])) {
                $options['conditions'] = [];
            } elseif (is_string($options['conditions'])) {
                $options['conditions'] = [$options['conditions']];
            }
                
            if (!isset($options['order'])) {
                $options['order'] = false;
            }
        } elseif ($options['type'] == 'context-children') {
            if (empty($options['local'])) {
                $options['local'] = 'ID';
            }
                    
            if (empty($options['contextClass'])) {
                $options['contextClass'] = get_called_class();
            }
                
            if (!isset($options['indexField'])) {
                $options['indexField'] = false;
            }
                
            if (!isset($options['conditions'])) {
                $options['conditions'] = [];
            }
                
            if (!isset($options['order'])) {
                $options['order'] = false;
            }
        } elseif ($options['type'] == 'context-child') {
            if (empty($options['local'])) {
                $options['local'] = 'ID';
            }
                    
            if (empty($options['contextClass'])) {
                $options['contextClass'] = get_called_class();
            }
                
            if (!isset($options['indexField'])) {
                $options['indexField'] = false;
            }
                
            if (!isset($options['conditions'])) {
                $options['conditions'] = [];
            }
                
            if (!isset($options['order'])) {
                $options['order'] = ['ID' => 'DESC'];
            }
        } elseif ($options['type'] == 'context-parent') {
            if (empty($options['local'])) {
                $options['local'] = 'ContextID';
            }
                    
            if (empty($options['foreign'])) {
                $options['foreign'] = 'ID';
            }

            if (empty($options['classField'])) {
                $options['classField'] = 'ContextClass';
            }

            if (empty($options['allowedClasses'])) {
                $options['allowedClasses'] = static::$contextClasses;
            }
        } elseif ($options['type'] == 'handle') {
            if (empty($options['local'])) {
                $options['local'] = 'Handle';
            }

            if (empty($options['class'])) {
                $options['class'] = 'GlobalHandle';
            }
        } elseif ($options['type'] == 'many-many') {
            if (empty($options['class'])) {
                die('required many-many option "class" missing');
            }
        
            if (empty($options['linkClass'])) {
                die('required many-many option "linkClass" missing');
            }
                
            if (empty($options['linkLocal'])) {
                $options['linkLocal'] = static::$rootClass . 'ID';
            }
        
            if (empty($options['linkForeign'])) {
                $options['linkForeign'] = $options['class']::$rootClass . 'ID';
            }
        
            if (empty($options['local'])) {
                $options['local'] = 'ID';
            }

            if (empty($options['foreign'])) {
                $options['foreign'] = 'ID';
            }

            if (!isset($options['indexField'])) {
                $options['indexField'] = false;
            }
                
            if (!isset($options['conditions'])) {
                $options['conditions'] = [];
            }
                
            if (!isset($options['order'])) {
                $options['order'] = false;
            }
        }
        
        if (static::isVersioned()) {
            if ($options['type'] == 'history') {
                if (empty($options['class'])) {
                    $options['class'] = get_called_class();
                }
            }
        }
                
        return $options;
    }
    
    /**
     * Retrieves given relationships' value
     * @param string $relationship Name of relationship
     * @return mixed value
     */
    protected function _getRelationshipValue($relationship)
    {
        if (!isset($this->_relatedObjects[$relationship])) {
            $rel = static::$_classRelationships[get_called_class()][$relationship];

            if ($rel['type'] == 'one-one') {
                if ($value = $this->_getFieldValue($rel['local'])) {
                    $this->_relatedObjects[$relationship] = $rel['class']::getByField($rel['foreign'], $value);
                
                    // hook relationship for invalidation
                    static::$_classFields[get_called_class()][$rel['local']]['relationships'][$relationship] = true;
                } else {
                    $this->_relatedObjects[$relationship] = null;
                }
            } elseif ($rel['type'] == 'one-many') {
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
            } elseif ($rel['type'] == 'context-children') {
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
            } elseif ($rel['type'] == 'context-child') {
                $conditions = array_merge($rel['conditions'], [
                    'ContextClass' => $rel['contextClass'],
                    'ContextID' => $this->_getFieldValue($rel['local']),
                ]);
            
                $this->_relatedObjects[$relationship] = $rel['class']::getByWhere(
                    $conditions,
            
                    [
                        'order' => $rel['order'],
                    ]
                );
            } elseif ($rel['type'] == 'context-parent') {
                $className = $this->_getFieldValue($rel['classField']);
                $this->_relatedObjects[$relationship] = $className ? $className::getByID($this->_getFieldValue($rel['local'])) : null;
                
                // hook both relationships for invalidation
                static::$_classFields[get_called_class()][$rel['classField']]['relationships'][$relationship] = true;
                static::$_classFields[get_called_class()][$rel['local']]['relationships'][$relationship] = true;
            } elseif ($rel['type'] == 'handle') {
                if ($handle = $this->_getFieldValue($rel['local'])) {
                    $this->_relatedObjects[$relationship] = $rel['class']::getByHandle($handle);
                
                    // hook relationship for invalidation
                    static::$_classFields[get_called_class()][$rel['local']]['relationships'][$relationship] = true;
                } else {
                    $this->_relatedObjects[$relationship] = null;
                }
            } elseif ($rel['type'] == 'many-many') {
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
            } elseif ($rel['type'] == 'history' && static::isRelational()) {
                $this->_relatedObjects[$relationship] = $rel['class']::getRevisionsByID($this->__get(static::$primaryKey), $rel);
            }
        }
        
        return $this->_relatedObjects[$relationship];
    }
}

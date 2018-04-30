<?php
namespace Divergence\Models;

use Exception;

class RecordValidator
{

    // configurables
    public static $autoTrim = true;
    
    // protected properties
    protected $_record;
    protected $_errors = [];


    // magic methods
    public function __construct(&$record, $autoTrim = null)
    {
        //init autoTrim option to static default
        if (!isset($autoTrim)) {
            $autoTrim = self::$autoTrim;
        }
        
        // apply autotrim
        if ($autoTrim) {
            self::trimArray($record);
        }

        // store record
        $this->_record = &$record;
    }


    // public instance methods
    public function getErrors($id = false)
    {
        if ($id === false) {
            return $this->_errors;
        } elseif (array_key_exists($id, $this->_errors)) {
            return $this->_errors[$id];
        } else {
            return false;
        }
    }

    
    public function hasErrors($id = false)
    {
        if ($id === false) {
            return (count($this->_errors) > 0);
        } elseif (array_key_exists($id, $this->_errors)) {
            return true;
        } else {
            return false;
        }
    }


    public function addError($id, $errorMessage)
    {
        $this->_errors[$id] = $errorMessage;
    }


    public function validate($options)
    {
        // apply default
        $options = array_merge([
            'validator' => 'string'
            , 'required' => true,
        ], $options);


        // check 'field'
        if (empty($options['field'])) {
            die('FormValidator: required option "field" missing');
        }
            
        // check 'id' and default to 'field'
        if (empty($options['id'])) {
            if (is_array($options['field'])) {
                throw new Exception('Option "id" is required when option "field" is an array');
            } else {
                $options['id'] = $options['field'];
            }
        }

        // check validator
        if (!is_callable('Validators::'.$options['validator'])) {
            die("Invalid form validator: $options[validator]");
        }
        

        // return false if any errors are already registered under 'id'
        if (array_key_exists($options['id'], $this->_errors)) {
            return false;
        }
        

        // parse 'field' for multiple values and array paths
        if (is_array($options['field'])) {
            $value = [];
            foreach ($options['field'] as $field_single) {
                $value[] = $this->resolveValue($field_single);
            }

            // skip validation for empty fields that aren't required
            if (!$options['required'] && !count(array_filter($value))) {
                return true;
            }
        } else {
            $value = $this->resolveValue($options['field']);

            // skip validation for empty fields that aren't required
            if (!$options['required'] && empty($value)) {
                return true;
            }
        }


        // call validator
        $isValid = call_user_func(['validators',$options['validator']], $value, $options);

        if ($isValid == false) {
            if (!empty($options['errorMessage'])) {
                $this->_errors[$options['id']] = $options['errorMessage'];
            } else {
                // default 'erroMessage' built from 'id'
                $this->_errors[$options['id']] = Inflector::spacifyCaps($options['id']) . ' is ' . ($options['required'] && empty($value) ? 'missing' : 'invalid');
            }
            return false;
        } else {
            return true;
        }
    }
    


    // protected instance methods
    protected function resolveValue($path)
    {
        // break apart path
        $crumbs = explode('.', $path);

        // resolve path recursively
        $cur = &$this->_record;
        while ($crumb = array_shift($crumbs)) {
            if (array_key_exists($crumb, $cur)) {
                $cur = &$cur[$crumb];
            } else {
                return null;
            }
        }

        // return current value
        return $cur;
    }
    

    
    // protected static methods
    protected static function trimArray(&$array)
    {
        if (!is_array($array)) {
            debug_print_backtrace();
        }
        foreach ($array as &$var) {
            if (is_string($var)) {
                $var = trim($var);
            } elseif (is_array($var)) {
                self::trimArray($var);
            }
        }
    }
}

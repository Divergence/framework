<?php
namespace Divergence\Models;

use Exception;

use Divergence\Helpers\Validate;

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
    public function resetErrors()
    {
        $this->_errors = [];
    }

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
            'validator' => 'string',
            'required' => true,
        ], $options);


        // check 'field'
        if (empty($options['field'])) {
            throw new Exception('Required option "field" missing');
        }

        // check 'id' and default to 'field'
        if (empty($options['id'])) {
            $options['id'] = $options['field'];
        }


        // get validator
        if (is_string($options['validator'])) {
            $validator = [Validate::class, $options['validator']];
        } else {
            $validator = $options['validator'];
        }

        // check validator
        if (!is_callable($validator)) {
            throw new Exception('Validator for field ' . $options['id'] . ' is not callable');
        }


        // return false if any errors are already registered under 'id'
        if (array_key_exists($options['id'], $this->_errors)) {
            return false;
        }


        $value = $this->resolveValue($options['field']);

        // skip validation for empty fields that aren't required
        if (!$options['required'] && empty($value)) {
            return true;
        }

        // call validator
        $isValid = call_user_func($validator, $value, $options);

        if ($isValid == false) {
            if (!empty($options['errorMessage'])) {
                $this->_errors[$options['id']] = gettext($options['errorMessage']);
            } else {
                // default 'errorMessage' built from 'id'
                $this->_errors[$options['id']] = sprintf($options['required'] && empty($value) ? _('%s is missing.') : _('%s is invalid.'), $options['id']);
            }
            return false;
        } else {
            return true;
        }
    }



    // protected instance methods
    protected function resolveValue($field)
    {
        $cur = &$this->_record;
        if (array_key_exists($field, $cur)) {
            $cur = &$cur[$field];
        } else {
            return null;
        }
        return $cur;
    }



    // protected static methods
    protected static function trimArray(&$array)
    {
        foreach ($array as &$var) {
            if (is_string($var)) {
                $var = trim($var);
            } elseif (is_array($var)) {
                self::trimArray($var);
            }
        }
    }
}

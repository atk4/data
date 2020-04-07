<?php

namespace atk4\data\Model\Scope;

use atk4\core\Exception;
use atk4\data\Field;
use atk4\data\Model;
use atk4\data\Persistence\Array_;
use atk4\dsql\Expression;
use atk4\dsql\Expressionable;

class Condition extends AbstractScope
{
    public $key;

    public $operator;

    public $value;

    protected static $opposites = [
        '='             => '!=',
        '!='            => '=',
        '<'             => '>=',
        '>'             => '<=',
        '>='            => '<',
        '<='            => '>',
        'LIKE'          => 'NOT LIKE',
        'NOT LIKE'      => 'LIKE',
        'IN'            => 'NOT IN',
        'NOT IN'        => 'IN',
        'REGEXP'        => 'NOT REGEXP',
        'NOT REGEXP'    => 'REGEXP',
    ];

    protected static $dictionary = [
        '='             => 'is equal to',
        '!='            => 'is not equal to',
        '<'             => 'is smaller than',
        '>'             => 'is greater than',
        '>='            => 'is greater or equal to',
        '<='            => 'is smaller or equal to',
        'LIKE'          => 'is like',
        'NOT LIKE'      => 'is not like',
        'IN'            => 'is one of',
        'NOT IN'        => 'is not one of',
        'REGEXP'        => 'is regular expression',
        'NOT REGEXP'    => 'is not regular expression'
    ];

    /**
     * Create Condition based on provided arguments
     * Arguments can also be passed to the $key as array.
     *
     * @param mixed $key
     * @param mixed $operator
     * @param mixed $value
     *
     * @return static
     */
    public static function create($key, $operator = null, $value = null)
    {
        if ($key instanceof AbstractScope) {
            return $key;
        }

        $args = is_array($key) ? $key : func_get_args();

        return new static (...$args);
    }

    public function __construct($key, $operator = null, $value = null)
    {
        switch (func_num_args()) {
            case 1:
                $key = is_string($key) ? new Expression($key) : $key;
                break;

            case 2:
                $value = $operator;
                $operator = '=';
                break;
        }

        if (is_bool($key)) {
            if ($key) {
                return;
            }

            $key = new Expression('false');
        }

        $this->key = $key;
        $this->operator = $operator;
        $this->value = $value;
    }
    
    public function setModel(Model $model = null)
    {
        if ($model && $model !== $this->model) {
            $this->model = $model;
            
            // key containing '/' means chained references and it is handled in toArray method
            if (is_string($field = $this->key) && stripos($field, '/') === false) {
                $field = $model->getField($field);
            }

            if ($field instanceof Field) {
                // sets default value for references
                if ($this->operator === '=' && !is_object($this->value) && !is_array($this->value)) {
                    $field->system = true;
                    $field->default = $this->value;
                }
            }

            $this->key = $field;
        }
        
        return $this;
    }

    public function toArray()
    {
        // make sure clones are used to avoid changes
        $condition = clone $this;
        
        $field = $condition->key;
        $operator = $condition->operator;
        $value = $condition->value;
        
        if ($model = $condition->model) {                   
            // replace placeholder can also disable the condition
            $value = $condition->replaceValue($model, $value);
           
            if (is_string($field)) {
                // shorthand for adding conditions on references
                // use chained reference names separated by "/"
                if (stripos($field, '/') !== false) {
                    $references = explode('/', $field);
                    
                    $field = array_pop($references);
                    
                    foreach ($references as $link) {
                        $model = $model->refLink($link);
                    }
                    
                    // '#' will apply condition directly on the record count (has # referenced records)
                    // otherwise applying condition on the referenced model field (has referenced records where)
                    if ($field !== '#') {
                        $model->addCondition($field, $this->operator, $this->value);
                        $operator = '>';
                        $value = 0;
                    }
                    
                    $field = $model->action('count');
                    
                    return [$field, $operator, $value];
                }
                
                $field = $model->getField($field);
            }

            if ($field instanceof Field) {
                // @todo: value is array
                if ($model->persistence && !in_array($operator, ['like', 'regexp'])) {
                    $value = $model->persistence->typecastSaveField($field, $value);
                }
            }
            
            if (!$this->isActive()) {
                return [];
            }
    
            // only expression contained in $field
            if (!$operator) {
                return [$field];
            }
            
            // skip explicitly using '=' as in some cases it is transformed to 'in'
            // for instance in dsql so let exact operator be handled by Persistence
            if ($operator === '=') {
                return [$field, $value];
            }
        }
            
        return [$field, $operator, $value];
    }

    public function isEmpty()
    {
        return array_filter([$this->key, $this->operator, $this->value]) ? false : true;
    }

    public function validate(Model $model, $values)
    {
        if (!$this->isActive()) {
            return [];
        }

        $model = $model->newInstance();

        $data = [1 => $values];

        $model->withPersistence(new Array_($data));

        $model->add($this);

        return $model->export() ? [] : [$this];

//     	$match = false;

//     	$model->atomic(function() use ($model, $values, & $match) {
//     	    $id = $model->persistence->insert($model, $values);

//     	    $model->withID($id)->set($this);

//     	    $match = (bool) $model->export();

//     	    throw new \Exception();
//     	});

//     	return $match ? [] : $this;
    }

    public function negate()
    {
        if ($this->operator && isset(self::$opposites[$this->operator])) {
            $this->operator = self::$opposites[$this->operator];
        } else {
            throw new Exception(['Negation of condition is not supported for '.($this->operator ?: 'no').' operator']);
        }

        return $this;
    }

    public function find($key)
    {
        return ($this->key === $key) ? $this : null;
    }

    public function toWords($asHtml = false)
    {
        if (!$this->model) {
            throw new Exception(['Model mist be set using setModel to convert to words']);
        }

        // make sure clones are used to avoid changes
        $condition = clone $this;
    
        $key = $condition->keyToWords($asHtml);
    
        $operator = $condition->operatorToWords($asHtml);
    
        $value = $condition->valueToWords($condition->value, $asHtml);
    
        $ret = trim("{$key} {$operator} {$value}");

        return $asHtml ? $ret : html_entity_decode($ret);
    }

    protected function keyToWords($asHtml = false)
    {
        $model = $this->model;
        
        $words = [];
        $key = $this->key;

        if (is_string($key)) {
            if (stripos($key, '/') !== false) {
                $references = explode('/', $key);

                $words[] = 'Record';

                $key = array_pop($references);

                foreach ($references as $link) {
                    $words[] = "that has reference $link";

                    $model = $model->refLink($link);
                }

                $words[] = 'where';

                if ($key === '#') {
                    $words[] = 'where number of records';
                    $key = '';
                }
            }

            try {
                $key = $key ? $model->getField($key) : '';
            } catch (\Exception $e) {
                // keep $key as it is
            }
        }

        if ($key instanceof Field) {
            $key = $key->getCaption();

            $words[] = $key;
        } elseif ($key instanceof Expression) {
            $words[] = "expression '{$key->getDebugQuery($asHtml)}'";
        }

        $string = implode(' ', array_filter($words));

        return $asHtml ? "<strong>$string</strong>" : $string;
    }

    protected function operatorToWords($asHtml = false)
    {
        return $this->operator ? (self::$dictionary[$this->operator] ?? 'is equal to') : '';
    }

    protected function valueToWords($value, $asHtml = false)
    {
        $model = $this->model;
        
        if (is_null($value)) {
            return $this->operator ? 'empty' : '';
        }

        if (is_array($values = $value)) {
            $ret = [];
            foreach ($values as $value) {
                $ret[] = $this->valueToWords($value, $asHtml);
            }

            return implode(' or ', $ret);
        }

        if (is_object($value)) {
            if ($value instanceof Field) {
                return $value->owner->getModelCaption().' '.$value->getCaption();
            }

            if ($value instanceof Expression || $value instanceof Expressionable) {
                return "expression '{$value->getDebugQuery()}'";
            }

            return 'object '.print_r($value, true);
        }

        // replace placeholders
        $value = $this->replaceValue($model, $value, true);

        // handling of scope on references
        if (is_string($field = $this->key)) {
            if (stripos($field, '/') !== false) {
                $references = explode('/', $field);

                $field = array_pop($references);

                foreach ($references as $link) {
                    $model = $model->refLink($link);
                }
            }

            $field = $model->hasField($field);
        }
        
        // use the referenced model title if such exists
        if ($field && ($field->reference ?? false)) {
            // make sure we set the value in the Model parent of the reference
            // it should be same class as $model but $model might be a clone
            $field->reference->owner->set($field->short_name, $value);
            
            $value = $field->reference->ref()->getTitle() ?: $value;
        }

        return "'".(string) $value."'";
    }

    protected function replaceValue(Model $model, $value, $toWords = false)
    {
        if (is_array($values = $value)) {
            foreach ($values as &$value) {
                $value = $this->replaceValue($model, $value, $toWords);
            }

            return $values;
        }

        if (is_scalar($value)) {
            if ($placeholder = self::$placeholders[$value] ?? null) {
                $value = $toWords ? $placeholder['label'] : $placeholder['value'];

                if (is_callable($fx = $value)) {
                    $value = call_user_func($fx, $model, $this);
                }
            }
        }

        return $value;
    }
}

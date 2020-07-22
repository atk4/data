<?php

declare(strict_types=1);

namespace atk4\data\Model\Scope;

use atk4\core\ReadableCaptionTrait;
use atk4\data\Exception;
use atk4\data\Field;
use atk4\dsql\Expression;
use atk4\dsql\Expressionable;

class BasicCondition extends AbstractCondition
{
    use ReadableCaptionTrait;

    /**
     * Stores the condition key.
     *
     * @var string|Field|Expression
     */
    public $key;

    /**
     * Stores the condition operator.
     *
     * @var string
     */
    public $operator;

    /**
     * Stores the condition value.
     */
    public $value;

    protected static $opposites = [
        '=' => '!=',
        '!=' => '=',
        '<' => '>=',
        '>' => '<=',
        '>=' => '<',
        '<=' => '>',
        'LIKE' => 'NOT LIKE',
        'NOT LIKE' => 'LIKE',
        'IN' => 'NOT IN',
        'NOT IN' => 'IN',
        'REGEXP' => 'NOT REGEXP',
        'NOT REGEXP' => 'REGEXP',
    ];

    protected static $dictionary = [
        '=' => 'is equal to',
        '!=' => 'is not equal to',
        '<' => 'is smaller than',
        '>' => 'is greater than',
        '>=' => 'is greater or equal to',
        '<=' => 'is smaller or equal to',
        'LIKE' => 'is like',
        'NOT LIKE' => 'is not like',
        'IN' => 'is one of',
        'NOT IN' => 'is not one of',
        'REGEXP' => 'is regular expression',
        'NOT REGEXP' => 'is not regular expression',
    ];

    protected static $skipValueTypecast = [
        'LIKE',
        'NOT LIKE',
        'REGEXP',
        'NOT REGEXP',
    ];

    public function __construct($key, $operator = null, $value = null)
    {
        if (func_num_args() == 2) {
            $value = $operator;
            $operator = '=';
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

    public function onChangeModel(): void
    {
        if ($model = $this->getModel()) {
            // if we have a definitive scalar value for a field
            // sets it as default value for field and locks it
            // new records will automatically get this value assigned for the field
            // @todo: consider this when condition is part of OR scope
            if ($this->operator === '=' && !is_object($this->value) && !is_array($this->value)) {
                // key containing '/' means chained references and it is handled in toArray method
                if (is_string($field = $this->key) && !str_contains($field, '/')) {
                    $field = $model->getField($field);
                }

                if ($field instanceof Field) {
                    $field->system = true;
                    $field->default = $this->value;
                }
            }
        }
    }

    public function toArray(): array
    {
        // make sure clones are used to avoid changes
        $condition = clone $this;

        $field = $condition->key;
        $operator = $condition->operator;
        $value = $condition->value;

        if ($condition->isEmpty()) {
            return [];
        }

        if ($model = $condition->getModel()) {
            if (is_string($field)) {
                // shorthand for adding conditions on references
                // use chained reference names separated by "/"
                if (stripos($field, '/') !== false) {
                    $references = explode('/', $field);

                    $field = array_pop($references);

                    foreach ($references as $link) {
                        $model = $model->refLink($link);
                    }

                    // '#' -> has # referenced records
                    // '?' -> has any referenced records
                    // '!' -> does not have any referenced records
                    if (in_array($field, ['#', '!', '?'], true)) {
                        // if no operator consider '#' as 'any record exists'
                        if ($field == '#' && !$operator) {
                            $field = '?';
                        }

                        if (in_array($field, ['!', '?'], true)) {
                            $operator = '=';
                            $value = $field == '?' ? 1 : 0;
                        }
                    } else {
                        // otherwise add the condition to the referenced model
                        // and check if any records exist matching the criteria
                        $model->addCondition($field, $operator, $value);
                        $operator = '=';
                        $value = 1;
                    }

                    // if not counting we check for existence only
                    $field = $field == '#' ? $model->action('count') : $model->action('exists');
                } else {
                    $field = $model->getField($field);
                }
            }

            // @todo: value is array
            // convert the value using the typecasting of persistence
            if ($field instanceof Field && $model->persistence && !in_array(strtoupper((string) $operator), self::$skipValueTypecast, true)) {
                $value = $model->persistence->typecastSaveField($field, $value);
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

    public function isEmpty(): bool
    {
        return array_filter([$this->key, $this->operator, $this->value]) ? false : true;
    }

    public function clear()
    {
        $this->key = $this->operator = $this->value = null;

        return $this;
    }

    public function negate()
    {
        if ($this->operator && isset(self::$opposites[$this->operator])) {
            $this->operator = self::$opposites[$this->operator];
        } else {
            throw new Exception('Negation of condition is not supported for ' . ($this->operator ?: 'no') . ' operator');
        }

        return $this;
    }

    public function toWords(bool $asHtml = false): string
    {
        if (!$this->getModel()) {
            throw new Exception('Condition must be associated with Model to convert to words');
        }

        // make sure clones are used to avoid changes
        $condition = clone $this;

        $key = $condition->keyToWords($asHtml);

        $operator = $condition->operatorToWords($asHtml);

        $value = $condition->valueToWords($condition->value, $asHtml);

        $ret = trim("{$key} {$operator} {$value}");

        return $asHtml ? $ret : html_entity_decode($ret);
    }

    protected function keyToWords(bool $asHtml = false): string
    {
        $model = $this->getModel();

        $words = [];

        if (is_string($field = $this->key)) {
            if (stripos($field, '/') !== false) {
                $references = explode('/', $field);

                $words[] = $model->getModelCaption();

                $field = array_pop($references);

                foreach ($references as $link) {
                    $words[] = "that has reference {$this->readableCaption($link)}";

                    $model = $model->refLink($link);
                }

                $words[] = 'where';

                if ($field === '#') {
                    $words[] = $this->operator ? 'number of records' : 'any referenced record exists';
                    $field = '';
                } elseif ($field === '?') {
                    $words[] = 'any referenced record exists';
                    $field = '';
                } elseif ($field === '!') {
                    $words[] = 'no referenced records exist';
                    $field = '';
                }
            }

            $field = $model->hasField($field) ? $model->getField($field) : null;
        }

        if ($field instanceof Field) {
            $words[] = $field->getCaption();
        } elseif ($field instanceof Expression) {
            $words[] = "expression '{$field->getDebugQuery()}'";
        }

        $string = implode(' ', array_filter($words));

        return $asHtml ? "<strong>{$string}</strong>" : $string;
    }

    protected function operatorToWords(bool $asHtml = false): string
    {
        return $this->operator ? (self::$dictionary[strtoupper((string) $this->operator)] ?? 'is equal to') : '';
    }

    protected function valueToWords($value, bool $asHtml = false): string
    {
        $model = $this->getModel();

        if ($value === null) {
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
                return $value->owner->getModelCaption() . ' ' . $value->getCaption();
            }

            if ($value instanceof Expression || $value instanceof Expressionable) {
                return "expression '{$value->getDebugQuery()}'";
            }

            return 'object ' . print_r($value, true);
        }

        // handling of scope on references
        if (is_string($field = $this->key)) {
            if (stripos($field, '/') !== false) {
                $references = explode('/', $field);

                $field = array_pop($references);

                foreach ($references as $link) {
                    $model = $model->refLink($link);
                }
            }

            $field = $model->hasField($field) ? $model->getField($field) : null;
        }

        // use the referenced model title if such exists
        if ($field && ($field->reference ?? false)) {
            // make sure we set the value in the Model parent of the reference
            // it should be same class as $model but $model might be a clone
            $field->reference->owner->set($field->short_name, $value);

            $value = $field->reference->ref()->getTitle() ?: $value;
        }

        return "'" . (string) $value . "'";
    }
}

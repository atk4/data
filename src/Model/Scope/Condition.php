<?php

declare(strict_types=1);

namespace atk4\data\Model\Scope;

use atk4\core\ReadableCaptionTrait;
use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\Model;
use atk4\dsql\Expression;
use atk4\dsql\Expressionable;

class Condition extends AbstractScope
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

    public const OPERATOR_EQUALS = '=';
    public const OPERATOR_DOESNOT_EQUAL = '!=';
    public const OPERATOR_GREATER = '>';
    public const OPERATOR_GREATER_EQUAL = '>=';
    public const OPERATOR_LESS = '<';
    public const OPERATOR_LESS_EQUAL = '<=';
    public const OPERATOR_LIKE = 'LIKE';
    public const OPERATOR_NOT_LIKE = 'NOT LIKE';
    public const OPERATOR_IN = 'IN';
    public const OPERATOR_NOT_IN = 'NOT IN';
    public const OPERATOR_REGEXP = 'REGEXP';
    public const OPERATOR_NOT_REGEXP = 'NOT REGEXP';

    protected static $operators = [
        self::OPERATOR_EQUALS => [
            'negate' => self::OPERATOR_DOESNOT_EQUAL,
            'label' => 'is equal to',
        ],
        self::OPERATOR_DOESNOT_EQUAL => [
            'negate' => self::OPERATOR_EQUALS,
            'label' => 'is not equal to',
        ],
        self::OPERATOR_LESS => [
            'negate' => self::OPERATOR_GREATER_EQUAL,
            'label' => 'is smaller than',
        ],
        self::OPERATOR_GREATER => [
            'negate' => self::OPERATOR_LESS_EQUAL,
            'label' => 'is greater than',
        ],
        self::OPERATOR_GREATER_EQUAL => [
            'negate' => self::OPERATOR_LESS,
            'label' => 'is greater or equal to',
        ],
        self::OPERATOR_LESS_EQUAL => [
            'negate' => self::OPERATOR_GREATER,
            'label' => 'is smaller or equal to',
        ],
        self::OPERATOR_LIKE => [
            'negate' => self::OPERATOR_NOT_LIKE,
            'label' => 'is like',
        ],
        self::OPERATOR_NOT_LIKE => [
            'negate' => self::OPERATOR_LIKE,
            'label' => 'is not like',
        ],
        self::OPERATOR_IN => [
            'negate' => self::OPERATOR_NOT_IN,
            'label' => 'is one of',
        ],
        self::OPERATOR_NOT_IN => [
            'negate' => self::OPERATOR_IN,
            'label' => 'is not one of',
        ],
        self::OPERATOR_REGEXP => [
            'negate' => self::OPERATOR_NOT_REGEXP,
            'label' => 'is regular expression',
        ],
        self::OPERATOR_NOT_REGEXP => [
            'negate' => self::OPERATOR_REGEXP,
            'label' => 'is not regular expression',
        ],
    ];

    public function __construct($key, $operator = null, $value = null)
    {
        if ($key instanceof AbstractScope) {
            throw new Exception('Only Scope can contain another conditions');
        } elseif ($key instanceof Field) { // for BC
            $key = $key->short_name;
        } elseif (!is_string($key) && !($key instanceof Expression) && !($key instanceof Expressionable)) {
            throw new Exception('Field must be a string or an instance of Expression');
        }

        if (func_num_args() === 1 && is_bool($key)) {
            if ($key) {
                return;
            }

            $key = new Expression('false');
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = self::OPERATOR_EQUALS;
        }

        $this->key = $key;
        $this->value = $value;

        if ($operator === null) {
            // at least MSSQL database always requires an operator
            if (!($key instanceof Expression) && !($key instanceof Expressionable)) {
                throw new Exception('Operator must be specified');
            }
        } else {
            $this->operator = strtoupper((string) $operator);

            if (!array_key_exists($this->operator, self::$operators)) {
                throw (new Exception('Operator is not supported'))
                    ->addMoreInfo('operator', $operator);
            }
        }

        if (is_array($value)) {
            if (array_filter($value, 'is_array')) {
                throw (new Exception('Multi-dimensional array as condition value is not supported'))
                    ->addMoreInfo('value', $value);
            }

            if (!in_array($this->operator, [
                self::OPERATOR_EQUALS,
                self::OPERATOR_IN,
                self::OPERATOR_DOESNOT_EQUAL,
                self::OPERATOR_NOT_IN,
            ], true)) {
                throw (new Exception('Operator is not supported for array condition value'))
                    ->addMoreInfo('operator', $operator)
                    ->addMoreInfo('value', $value);
            }
        }
    }

    protected function onChangeModel(): void
    {
        if ($model = $this->getModel()) {
            // if we have a definitive scalar value for a field
            // sets it as default value for field and locks it
            // new records will automatically get this value assigned for the field
            // @todo: consider this when condition is part of OR scope
            if ($this->operator === self::OPERATOR_EQUALS && !is_object($this->value) && !is_array($this->value)) {
                // key containing '/' means chained references and it is handled in toQueryArguments method
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

    public function toQueryArguments(): array
    {
        if ($this->isEmpty()) {
            return [];
        }

        $field = $this->key;
        $operator = $this->operator;
        $value = $this->value;

        if ($model = $this->getModel()) {
            if (is_string($field)) {
                // shorthand for adding conditions on references
                // use chained reference names separated by "/"
                if (str_contains($field, '/')) {
                    $references = explode('/', $field);
                    $field = array_pop($references);

                    $refModels = [];
                    $refModel = $model;
                    foreach ($references as $link) {
                        $refModel = $refModel->refLink($link);
                        $refModels[] = $refModel;
                    }

                    foreach (array_reverse($refModels) as $refModel) {
                        if ($field === '#') {
                            $field = $value ? $refModel->action('count') : $refModel->action('exists');
                        } else {
                            $refModel->addCondition($field, $operator, $value);
                            $field = $refModel->action('exists');
                            $operator = '>';
                            $value = 0;
                        }
                    }
                } else {
                    $field = $model->getField($field);
                }
            }

            // handle the query arguments using field
            if ($field instanceof Field) {
                [$field, $operator, $value] = $field->getQueryArguments($operator, $value);
            }

            // only expression contained in $field
            if (!$operator) {
                return [$field];
            }

            // skip explicitly using OPERATOR_EQUALS as in some cases it is transformed to OPERATOR_IN
            // for instance in dsql so let exact operator be handled by Persistence
            if ($operator === self::OPERATOR_EQUALS) {
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
        if ($this->operator && isset(self::$operators[$this->operator]['negate'])) {
            $this->operator = self::$operators[$this->operator]['negate'];
        } else {
            throw (new Exception('Negation of condition is not supported for this operator'))
                ->addMoreInfo('operator', $this->operator ?: 'no operator');
        }

        return $this;
    }

    public function toWords(Model $model = null): string
    {
        $model = $model ?: $this->getModel();

        if ($model === null) {
            throw new Exception('Condition must be associated with Model to convert to words');
        }

        $key = $this->keyToWords($model);
        $operator = $this->operatorToWords();
        $value = $this->valueToWords($model, $this->value);

        return trim("{$key} {$operator} {$value}");
    }

    protected function keyToWords(Model $model): string
    {
        $words = [];

        if (is_string($field = $this->key)) {
            if (str_contains($field, '/')) {
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
                }
            }

            if ($model->hasField($field)) {
                $field = $model->getField($field);
            }
        }

        if ($field instanceof Field) {
            $words[] = $field->getCaption();
        } elseif ($field instanceof Expression) {
            $words[] = "expression '{$field->getDebugQuery()}'";
        }

        return implode(' ', array_filter($words));
    }

    protected function operatorToWords(): string
    {
        return $this->operator ? self::$operators[$this->operator]['label'] : '';
    }

    protected function valueToWords(Model $model, $value): string
    {
        if ($value === null) {
            return $this->operator ? 'empty' : '';
        }

        if (is_array($values = $value)) {
            $ret = [];
            foreach ($values as $value) {
                $ret[] = $this->valueToWords($model, $value);
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
            if (str_contains($field, '/')) {
                $references = explode('/', $field);

                $field = array_pop($references);

                foreach ($references as $link) {
                    $model = $model->refLink($link);
                }
            }

            if ($model->hasField($field)) {
                $field = $model->getField($field);
            }
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

<?php

declare(strict_types=1);

namespace Atk4\Data\Model\Scope;

use Atk4\Core\ReadableCaptionTrait;
use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Expressionable;
use Atk4\Data\Persistence\Sql\Sqlite\Expression as SqliteExpression;

class Condition extends AbstractScope
{
    use ReadableCaptionTrait;

    /** @var string|Field|Expressionable */
    public $field;

    public ?string $operator = null;

    /** @var mixed */
    public $value;

    public const OPERATOR_EQUALS = '=';
    public const OPERATOR_DOESNOT_EQUAL = '!=';
    public const OPERATOR_GREATER = '>';
    public const OPERATOR_GREATER_EQUAL = '>=';
    public const OPERATOR_LESS = '<';
    public const OPERATOR_LESS_EQUAL = '<=';
    public const OPERATOR_IN = 'IN';
    public const OPERATOR_NOT_IN = 'NOT IN';
    public const OPERATOR_LIKE = 'LIKE';
    public const OPERATOR_NOT_LIKE = 'NOT LIKE';
    public const OPERATOR_REGEXP = 'REGEXP';
    public const OPERATOR_NOT_REGEXP = 'NOT REGEXP';

    /** @var array<string, array<string, string>> */
    protected static array $operators = [
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
        self::OPERATOR_IN => [
            'negate' => self::OPERATOR_NOT_IN,
            'label' => 'is one of',
        ],
        self::OPERATOR_NOT_IN => [
            'negate' => self::OPERATOR_IN,
            'label' => 'is not one of',
        ],
        self::OPERATOR_LIKE => [
            'negate' => self::OPERATOR_NOT_LIKE,
            'label' => 'is like',
        ],
        self::OPERATOR_NOT_LIKE => [
            'negate' => self::OPERATOR_LIKE,
            'label' => 'is not like',
        ],
        self::OPERATOR_REGEXP => [
            'negate' => self::OPERATOR_NOT_REGEXP,
            'label' => 'matches regex',
        ],
        self::OPERATOR_NOT_REGEXP => [
            'negate' => self::OPERATOR_REGEXP,
            'label' => 'does not match regex',
        ],
    ];

    /**
     * @param string|Expressionable                 $field
     * @param ($value is null ? mixed : string)     $operator
     * @param ($operator is string ? mixed : never) $value
     */
    public function __construct($field, $operator = null, $value = null)
    {
        if ($field instanceof AbstractScope) {
            throw new Exception('Only Scope can contain another conditions');
        } elseif ($field instanceof Field) { // for BC
            $field = $field->shortName;
        } elseif (!is_string($field) && !$field instanceof Expressionable) { // @phpstan-ignore instanceof.alwaysTrue, booleanAnd.alwaysFalse
            throw new Exception('Field must be a string or an instance of Expressionable');
        }

        if ('func_num_args'() === 2) {
            $value = $operator;
            $operator = self::OPERATOR_EQUALS;
        }

        $this->field = $field;
        $this->value = $value;

        if ($operator === null) {
            // at least MSSQL database always requires an operator
            if (!$field instanceof Expressionable || $value !== null) {
                throw new Exception('Operator must be specified');
            }
        } else {
            $this->operator = strtoupper($operator);

            if (!array_key_exists($this->operator, self::$operators)) {
                throw (new Exception('Operator is not supported'))
                    ->addMoreInfo('operator', $operator);
            }
        }

        if (is_array($value)) {
            foreach ($value as $v) {
                if (is_array($v)) {
                    throw (new Exception('Multi-dimensional array as condition value is not supported'))
                        ->addMoreInfo('value', $value);
                }
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

    #[\Override]
    protected function onChangeModel(): void
    {
        $model = $this->getModel();
        if ($model !== null) {
            // if we have a definitive equal condition set the value as default value for field
            // new records will automatically get this value assigned for the field
            // TODO: fix when condition is part of OR scope
            if ($this->operator === self::OPERATOR_EQUALS && !is_array($this->value)
                && !$this->value instanceof Expressionable
                && !$this->value instanceof Persistence\Array_\Action // needed to pass hintable tests
            ) {
                // field containing '/' means chained references and it is handled in toQueryArguments method
                $field = $this->field;
                if (is_string($field) && !str_contains($field, '/')) {
                    $field = $model->getField($field);
                }

                // TODO Model/field should not be mutated, see:
                // https://github.com/atk4/data/issues/662
                // for now, do not set default at least for PK/ID
                if ($field instanceof Field && $field->shortName !== $field->getOwner()->idField) {
                    $field->system = true;
                    $fakePersistence = new Persistence\Array_();
                    $valueCloned = $fakePersistence->typecastLoadField($field, $fakePersistence->typecastSaveField($field, $this->value));
                    $field->default = $valueCloned;
                }
            }
        }
    }

    /**
     * @return array{mixed}|array{mixed, string|null, mixed}
     */
    public function toQueryArguments(): array
    {
        $field = $this->field;
        $operator = $this->operator;
        $value = $this->value;

        $model = $this->getModel();
        if ($model !== null) {
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
                    unset($refModel);

                    foreach (array_reverse($refModels) as $refModel) {
                        if ($field === '#') {
                            if (is_string($value) && $value === (string) (int) $value) {
                                $value = (int) $value;
                            }

                            if ($value === 0) {
                                $field = $refModel->action('exists');
                                $value = false;
                            } elseif ($value === 1 && $operator === self::OPERATOR_GREATER_EQUAL) {
                                $field = $refModel->action('exists');
                                $operator = self::OPERATOR_EQUALS;
                                $value = true;
                            } else {
                                $field = $refModel->action('count');
                            }
                        } else {
                            $refModel->addCondition($field, $operator, $value);
                            $field = $refModel->action('exists');
                            $operator = self::OPERATOR_EQUALS;
                            $value = true;
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
            // for instance in DSQL so let exact operator be handled by Persistence
            if ($operator === self::OPERATOR_EQUALS) {
                return [$field, null, $value];
            }
        }

        return [$field, $operator, $value];
    }

    /**
     * @return false
     */
    #[\Override]
    public function isEmpty(): bool
    {
        return false;
    }

    /**
     * @return never
     */
    #[\Override]
    public function clear(): self
    {
        throw new Exception('Condition does not support clear operation');
    }

    #[\Override]
    public function negate(): self
    {
        if (isset(self::$operators[$this->operator]['negate'])) {
            $this->operator = self::$operators[$this->operator]['negate'];
        } else {
            throw (new Exception('Negation of condition is not supported for this operator'))
                ->addMoreInfo('operator', $this->operator);
        }

        return $this;
    }

    #[\Override]
    public function toWords(?Model $model = null): string
    {
        if ($model !== null) {
            $model->assertIsModel();
        } else {
            $model = $this->getModel();

            if ($model === null) {
                throw new Exception('Condition must be associated with model to convert to words');
            }
        }

        $field = $this->fieldToWords($model);
        $operator = $this->operatorToWords();
        $value = $this->valueToWords($model, $this->value);

        return trim($field . ' ' . $operator . ' ' . $value);
    }

    protected function fieldToWords(Model $model): string
    {
        $words = [];

        $field = $this->field;
        if (is_string($field)) {
            if (str_contains($field, '/')) {
                $references = explode('/', $field);

                $words[] = $model->getModelCaption();

                $field = array_pop($references);

                foreach ($references as $link) {
                    $words[] = 'that has reference ' . $this->readableCaption($link);

                    $model = $model->refLink($link);
                }

                $words[] = 'where';

                if ($field === '#') {
                    $words[] = $this->operator
                        ? 'number of records'
                        : 'any referenced record exists';
                }
            }

            if ($model->hasField($field)) {
                $field = $model->getField($field);
            }
        }

        if ($field instanceof Field) {
            $words[] = $field->getCaption();
        } elseif ($field instanceof Expressionable) {
            $words[] = $this->valueToWords($model, $field);
        }

        return implode(' ', array_filter($words, static fn ($v) => $v !== ''));
    }

    protected function operatorToWords(): string
    {
        return $this->operator
            ? self::$operators[$this->operator]['label']
            : '';
    }

    /**
     * @param mixed $value
     */
    protected function valueToWords(Model $model, $value): string
    {
        if ($value === null) {
            return $this->operator
                ? 'empty'
                : '';
        }

        if (is_array($value)) {
            $res = [];
            foreach ($value as $v) {
                $res[] = $this->valueToWords($model, $v);
            }

            return implode(' or ', $res);
        }

        if (is_object($value)) {
            if ($value instanceof Field) {
                return $value->getOwner()->getModelCaption() . ' ' . $value->getCaption();
            }

            if ($value instanceof Expressionable) {
                return 'expression \'' . $value->getDsqlExpression(new SqliteExpression())->getDebugQuery() . '\'';
            }

            return 'object ' . print_r($value, true);
        }

        // handling of scope on references
        $field = $this->field;
        if (is_string($field)) {
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
        $title = null;
        if ($field instanceof Field && $field->hasReference()) {
            // make sure we set the value in the Model
            $entity = $model->createEntity();
            $entity->set($field->shortName, $value);

            // then take the title
            $title = $entity->ref($field->getReference()->link)->getTitle();
            if ($title === $value) {
                $title = null;
            }
        }

        if (is_bool($value)) {
            $valueStr = $value ? 'true' : 'false';
        } elseif (is_int($value)) {
            $valueStr = (string) $value;
        } elseif (is_float($value)) {
            $valueStr = Expression::castFloatToString($value);
        } else {
            $valueStr = '\'' . (string) $value . '\'';
        }

        return $valueStr . ($title !== null ? ' (\'' . $title . '\')' : '');
    }
}

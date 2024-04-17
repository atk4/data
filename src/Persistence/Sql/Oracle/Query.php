<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Persistence\Sql\Query as BaseQuery;

class Query extends BaseQuery
{
    use ExpressionTrait;

    protected string $paramBase = 'xxaaaa';

    protected string $identifierEscapeChar = '"';
    protected string $expressionClass = Expression::class;

    #[\Override]
    protected function _renderConditionLikeOperator(bool $negated, string $sqlLeft, string $sqlRight): string
    {
        $sqlRight = 'regexp_replace(regexp_replace(' . $sqlRight
            . ', ' . $this->escapeStringLiteral('((\\\\\\\)*)(\\\([^_%]))?')
            . ', ' . $this->escapeStringLiteral('\1\4')
            . '), ' . $this->escapeStringLiteral('((^|[^\\\])(\\\\\\\)*\\\)$')
            . ', ' . $this->escapeStringLiteral('\1\\\\') . ')';

        return parent::_renderConditionLikeOperator($negated, $sqlLeft, $sqlRight);
    }

    #[\Override]
    protected function _renderConditionRegexpOperator(bool $negated, string $sqlLeft, string $sqlRight): string
    {
        return ($negated ? 'not ' : '') . 'regexp_like(' . $sqlLeft . ', ' . $sqlRight
            . ', ' . $this->escapeStringLiteral('in') . ')';
    }

    #[\Override]
    public function render(): array
    {
        if ($this->mode === 'select' && count($this->args['table'] ?? []) === 0) {
            try {
                $this->table('DUAL');

                return parent::render();
            } finally {
                unset($this->args['table']);
            }
        }

        return parent::render();
    }

    #[\Override]
    protected function _subrenderCondition(array $row): string
    {
        if (count($row) !== 1) {
            [$field, $operator, $value] = $row;

            if ($field instanceof Field && in_array($field->type, ['text', 'blob'], true)) {
                if (in_array($operator ?? '=', ['=', '!='], true)) {
                    if ($field->type === 'text') {
                        $field = $this->expr('LOWER([])', [$field]);
                        $value = $this->expr('LOWER([])', [$value]);
                    }

                    $row = [$this->expr('dbms_lob.compare([], [])', [$field, $value]), $operator, 0];
                } else {
                    throw (new Exception('Unsupported CLOB/BLOB field operator'))
                        ->addMoreInfo('operator', $operator)
                        ->addMoreInfo('type', $field->type);
                }
            }
        }

        return parent::_subrenderCondition($row);
    }

    #[\Override]
    protected function _renderLimit(): ?string
    {
        if (!isset($this->args['limit'])) {
            return null;
        }

        $cnt = (int) $this->args['limit']['cnt'];
        $shift = (int) $this->args['limit']['shift'];

        return ($shift ? ' offset ' . $shift . ' rows' : '')
            . ' fetch next ' . $cnt . ' rows only';
    }

    #[\Override]
    public function groupConcat($field, string $separator = ',')
    {
        return $this->expr('listagg({field}, []) within group (order by {field})', ['field' => $field, $separator]);
    }

    #[\Override]
    public function exists()
    {
        return $this->dsql()->mode('select')->field(
            $this->dsql()->expr('case when exists[] then 1 else 0 end', [$this])
        );
    }
}

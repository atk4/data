<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Query as BaseQuery;

class Query extends BaseQuery
{
    protected $paramBase = 'xxaaaa';

    public function render(): array
    {
        if ($this->mode === 'select' && $this->main_table === null) {
            $this->table('DUAL');
        }

        return parent::render();
    }

    protected function castStringToClobExpr(string $value): Expression
    {
        $exprArgs = [];
        $buildConcatExprFx = function (array $parts) use (&$buildConcatExprFx, &$exprArgs): string {
            if (count($parts) > 1) {
                $valueLeft = array_slice($parts, 0, intdiv(count($parts), 2));
                $valueRight = array_slice($parts, count($valueLeft));

                return 'CONCAT(' . $buildConcatExprFx($valueLeft) . ', ' . $buildConcatExprFx($valueRight) . ')';
            }

            $exprArgs[] = reset($parts);

            return 'TO_CLOB([])';
        };

        // Oracle SQL string literal is limited to 1332 bytes
        $parts = [];
        foreach (mb_str_split($value, 10_000) as $shorterValue) {
            $lengthBytes = strlen($shorterValue);
            $startBytes = 0;
            do {
                $part = mb_strcut($shorterValue, $startBytes, 1000);
                $startBytes += strlen($part);
                $parts[] = $part;
            } while ($startBytes < $lengthBytes);
        }

        $expr = $buildConcatExprFx($parts);

        return $this->expr($expr, $exprArgs);
    }

    protected function _sub_render_condition(array $row): string
    {
        if (count($row) === 2) {
            [$field, $value] = $row;
            $cond = '=';
        } elseif (count($row) >= 3) {
            [$field, $cond, $value] = $row;
        } else {
            // for phpstan only, remove else block once
            // https://github.com/phpstan/phpstan/issues/6017 is fixed
            $field = null;
            $cond = null;
            $value = null;
        }

        if (count($row) >= 2 && $field instanceof Field
            && in_array($field->getTypeObject()->getName(), ['text', 'blob'], true)) {
            $value = $this->castStringToClobExpr($value);

            if ($field->getTypeObject()->getName() === 'text') {
                $field = $this->expr('LOWER([])', [$field]);
                $value = $this->expr('LOWER([])', [$value]);
            }

            if (in_array($cond, ['=', '!=', '<>'], true)) {
                $row = [$this->expr('dbms_lob.compare([], [])', [$field, $value]), $cond, 0];
            } else {
                throw (new Exception('Unsupported CLOB/BLOB field operator'))
                    ->addMoreInfo('operator', $cond)
                    ->addMoreInfo('type', $field->type);
            }
        }

        return parent::_sub_render_condition($row);
    }

    public function groupConcat($field, string $delimiter = ',')
    {
        return $this->expr('listagg({field}, []) within group (order by {field})', ['field' => $field, $delimiter]);
    }

    // {{{ for Oracle 11 and lower to support LIMIT with OFFSET

    protected $template_select = '[with]select[option] [field] [from] [table][join][where][group][having][order]';
    /** @var string */
    protected $template_select_limit = 'select * from (select "__t".*, rownum "__dsql_rownum" [from] ([with]select[option] [field] [from] [table][join][where][group][having][order]) "__t") where "__dsql_rownum" > [limit_start][and_limit_end]';

    public function limit($cnt, $shift = null)
    {
        $this->template_select = $this->template_select_limit;

        return parent::limit($cnt, $shift);
    }

    public function _render_limit_start(): string
    {
        return (string) ($this->args['limit']['shift'] ?? 0);
    }

    public function _render_and_limit_end(): ?string
    {
        if (!$this->args['limit']['cnt']) {
            return '';
        }

        return ' and "__dsql_rownum" <= '
            . max((int) ($this->args['limit']['cnt'] + $this->args['limit']['shift']), (int) $this->args['limit']['cnt']);
    }

    public function getRowsIterator(): \Traversable
    {
        foreach (parent::getRowsIterator() as $row) {
            unset($row['__dsql_rownum']);

            yield $row;
        }
    }

    public function getRows(): array
    {
        return array_map(function ($row) {
            unset($row['__dsql_rownum']);

            return $row;
        }, parent::getRows());
    }

    public function getRow(): ?array
    {
        $row = parent::getRow();

        if ($row !== null) {
            unset($row['__dsql_rownum']);
        }

        return $row;
    }

    /// }}}

    public function exists()
    {
        return $this->dsql()->mode('select')->field(
            $this->dsql()->expr('case when exists[] then 1 else 0 end', [$this])
        );
    }
}

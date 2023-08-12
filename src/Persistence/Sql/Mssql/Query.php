<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mssql;

use Atk4\Data\Persistence\Sql\Query as BaseQuery;

class Query extends BaseQuery
{
    use ExpressionTrait;

    protected string $identifierEscapeChar = ']';
    protected string $expressionClass = Expression::class;

    protected string $templateInsert = <<<'EOF'
        begin try
          insert[option] into [tableNoalias] ([setFields]) values ([setValues]);
        end try begin catch
          if ERROR_NUMBER() = 544 begin
            set IDENTITY_INSERT [tableNoalias] on;
            begin try
              insert[option] into [tableNoalias] ([setFields]) values ([setValues]);
              set IDENTITY_INSERT [tableNoalias] off;
            end try begin catch
              set IDENTITY_INSERT [tableNoalias] off;
              throw;
            end catch
          end else begin
            throw;
          end
        end catch
        EOF;

    protected function _renderLimit(): ?string
    {
        if (!isset($this->args['limit'])) {
            return null;
        }

        $cnt = (int) $this->args['limit']['cnt'];
        $shift = (int) $this->args['limit']['shift'];

        if ($cnt === 0) {
            $cnt = 1;
            $shift = \PHP_INT_MAX;
        }

        return (!isset($this->args['order']) ? ' order by (select null)' : '')
            . ' offset ' . $shift . ' rows'
            . ' fetch next ' . $cnt . ' rows only';
    }

    public function groupConcat($field, string $separator = ',')
    {
        return $this->expr('string_agg({}, ' . $this->escapeStringLiteral($separator) . ')', [$field]);
    }

    public function exists()
    {
        return $this->dsql()->mode('select')->field(
            $this->dsql()->expr('case when exists[] then 1 else 0 end', [$this])
        );
    }
}

<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mssql;

use Atk4\Data\Persistence\Sql\Query as BaseQuery;

class Query extends BaseQuery
{
    use ExpressionTrait;

    protected $identifierEscapeChar = ']';

    protected $expressionClass = Expression::class;

    protected $template_insert = <<<'EOF'
        begin try
          insert[option] into [table_noalias] ([set_fields]) values ([set_values]);
        end try begin catch
          if ERROR_NUMBER() = 544 begin
            set IDENTITY_INSERT [table_noalias] on;
            begin try
              insert[option] into [table_noalias] ([set_fields]) values ([set_values]);
              set IDENTITY_INSERT [table_noalias] off;
            end try begin catch
              set IDENTITY_INSERT [table_noalias] off;
              throw;
            end catch
          end else begin
            throw;
          end
        end catch
        EOF;

    public function _render_limit(): ?string
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

    public function groupConcat($field, string $delimiter = ',')
    {
        return $this->expr('string_agg({}, ' . $this->escapeStringLiteral($delimiter) . ')', [$field]);
    }

    public function exists()
    {
        return $this->dsql()->mode('select')->field(
            $this->dsql()->expr('case when exists[] then 1 else 0 end', [$this])
        );
    }
}

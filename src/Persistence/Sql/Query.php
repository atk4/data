<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

/**
 * Perform query operation on SQL server (such as select, insert, delete, etc).
 */
abstract class Query extends Expression
{
    private const FIELD_INT_STRING_PREFIX = "\xff_int-string_";

    /** Template name for render. */
    public string $mode = 'select';

    /** @var string|Expression If no fields are defined, this field is used. */
    public $defaultField = '*';

    /** @var class-string<Expression> */
    protected string $expressionClass;

    public bool $wrapInParentheses = true;

    /** @var list<string> */
    protected array $supportedOperators = ['=', '!=', '<', '>', '<=', '>=', 'in', 'not in', 'like', 'not like', 'regexp', 'not regexp'];

    protected string $templateSelect = '[with]select[option] [field][from][table][join][where][group][having][order][limit]';
    protected string $templateInsert = 'insert[option] into [tableNoalias][setFields] [setValues]';
    protected string $templateReplace = 'replace[option] into [tableNoalias][setFields] [setValues]';
    protected string $templateDelete = '[with]delete[from][tableNoalias][where][having]';
    protected string $templateUpdate = '[with]update [tableNoalias] set [set] [where]';
    protected string $templateTruncate = 'truncate table [tableNoalias]';

    // {{{ Field specification and rendering

    /**
     * Adds new column to resulting select by querying $field.
     *
     * Examples:
     *  $q->field('name');
     *
     * You can use a dot to prepend table name to the field:
     *  $q->field('user.name');
     *  $q->field('user.name')->field('address.line1');
     *
     * You can pass first argument as Expression or Query
     *  $q->field($q->expr('2 + 2'), 'alias'); // must always use alias
     *
     * You can use $q->dsql() for subqueries. Subqueries will be wrapped in parentheses.
     *  $q->field($q->dsql()->table('x')..., 'alias');
     *
     * If you need to use funky name for the field (e.g, one containing
     * a dot or a space), you should wrap it into expression:
     *  $q->field($q->expr('{}', ['fun...ky.field']), 'f');
     *
     * @param string|Expressionable $field
     *
     * @return $this
     */
    public function field($field, ?string $alias = null)
    {
        $this->_setArgs('field', $alias, $field);

        return $this;
    }

    /**
     * Returns template component for [field].
     *
     * @param bool $addAlias Should we add aliases, see _renderFieldNoalias()
     *
     * @return string Parsed template chunk
     */
    protected function _renderField($addAlias = true): string
    {
        // if no fields were defined, use defaultField
        if (($this->args['field'] ?? []) === []) {
            if ($this->defaultField instanceof Expression) {
                return $this->consume($this->defaultField, self::ESCAPE_PARAM);
            }

            return $this->escapeIdentifierSoft($this->defaultField);
        }

        $res = [];
        foreach ($this->args['field'] as $alias => $field) {
            if (is_string($alias) && str_starts_with($alias, self::FIELD_INT_STRING_PREFIX)) {
                $alias = substr($alias, strlen(self::FIELD_INT_STRING_PREFIX));
            }

            if ($addAlias === false
                || $alias === $field
                || is_int($alias)
            ) {
                $alias = null;
            }

            // will parameterize the value and escape if necessary
            $field = $this->consume($field, self::ESCAPE_IDENTIFIER_SOFT);

            if ($alias !== null) {
                // field alias cannot be expression, so simply escape it
                $field .= ' ' . $this->escapeIdentifier($alias);
            }

            $res[] = $field;
        }

        return implode(', ', $res);
    }

    protected function _renderFieldNoalias(): string
    {
        return $this->_renderField(false);
    }

    // }}}

    // {{{ Table specification and rendering

    /**
     * Specify a table to be used in a query.
     *
     * @param string|Expressionable $table
     *
     * @return $this
     */
    public function table($table, ?string $alias = null)
    {
        if ($alias === null) {
            if ($table instanceof self) {
                throw (new Exception('Table alias is required when table is set as subquery'))
                    ->addMoreInfo('table', $table);
            }

            if (is_string($table)) {
                $alias = $table;
            }
        }

        $this->_setArgs('table', $alias, $table);

        return $this;
    }

    /**
     * Name or alias of base table to use when using default join().
     *
     * It is set by table(). If you are using multiple tables,
     * then false is returned as it is irrelevant.
     *
     * @return string|false|null
     */
    protected function getMainTable()
    {
        $c = count($this->args['table'] ?? []);
        if ($c === 0) {
            return null;
        } elseif ($c !== 1) {
            return false;
        }

        $alias = array_key_first($this->args['table']);
        if (!is_int($alias)) {
            return $alias;
        }

        return $this->args['table'][$alias];
    }

    /**
     * @param bool $addAlias Should we add aliases, see _renderTableNoalias()
     */
    protected function _renderTable($addAlias = true): ?string
    {
        $res = [];
        foreach ($this->args['table'] ?? [] as $alias => $table) {
            if ($addAlias === false && $table instanceof self) {
                throw new Exception('Table cannot be Query in UPDATE, INSERT etc. query modes');
            }

            // do not add alias when:
            //  - we don't want aliases
            //  - OR alias is the same as table name
            //  - OR alias is numeric
            if ($addAlias === false
                || (is_string($table) && $alias === $table)
                || is_int($alias)
            ) {
                $alias = null;
            }

            // consume or escape table
            $table = $this->consume($table, self::ESCAPE_IDENTIFIER_SOFT);

            // add alias if needed
            if ($alias) {
                $table .= ' ' . $this->escapeIdentifier($alias);
            }

            $res[] = $table;
        }

        return implode(', ', $res);
    }

    protected function _renderTableNoalias(): ?string
    {
        return $this->_renderTable(false);
    }

    protected function _renderFrom(): ?string
    {
        return isset($this->args['table']) ? ' from ' : '';
    }

    // }}}

    // {{{ with()

    /**
     * Specify WITH query to be used.
     *
     * @param array<int, string>|null $fields
     *
     * @return $this
     */
    public function with(Expressionable $cursor, string $alias, ?array $fields = null, bool $recursive = false)
    {
        $this->_setArgs('with', $alias, [
            'cursor' => $cursor,
            'fields' => $fields,
            'recursive' => $recursive,
        ]);

        return $this;
    }

    protected function _renderWith(): ?string
    {
        if (($this->args['with'] ?? []) === []) {
            return '';
        }

        $res = [];

        $isRecursive = false;
        foreach ($this->args['with'] as $alias => ['cursor' => $cursor, 'fields' => $fields, 'recursive' => $recursive]) {
            // cursor alias cannot be expression, so simply escape it
            $s = $this->escapeIdentifier($alias) . ' ';

            // set cursor fields
            if ($fields !== null) {
                $s .= '(' . implode(', ', array_map([$this, 'escapeIdentifier'], $fields)) . ') ';
            }

            // will parameterize the value and escape if necessary
            $s .= 'as ' . $this->consume($cursor, self::ESCAPE_IDENTIFIER_SOFT);

            if ($recursive) {
                $isRecursive = true;
            }

            $res[] = $s;
        }

        return 'with ' . ($isRecursive ? 'recursive ' : '') . implode(',' . "\n", $res) . "\n";
    }

    // }}}

    // {{{ join()

    /**
     * Joins your query with another table. Join will use $this->getMainTable()
     * to reference the main table, unless you specify it explicitly.
     *
     * Examples:
     *  $q->join('address'); // on user.address_id = address.id
     *  $q->join('address.user_id'); // on address.user_id = user.id
     *  $q->join('address a'); // with alias
     *
     * Second argument may specify the field of the master table
     *  $q->join('address', 'billing_id');
     *  $q->join('address.code', 'code');
     *  $q->join('address.code', 'user.code');
     *
     * Third argument may specify which kind of join to use.
     *  $q->join('address', null, 'left');
     *  $q->join('address.code', 'user.code', 'inner');
     *
     * You can use expression for more complex joins
     *  $q->join(
     *      'address',
     *      $q->orExpr()
     *          ->where('user.billing_id', 'address.id')
     *          ->where('user.technical_id', 'address.id')
     *  )
     *
     * @param string            $foreignTable Table to join with
     * @param string|Expression $masterField  Field in master table
     * @param string            $joinKind     'left' or 'inner', etc
     * @param string            $foreignAlias
     *
     * @return $this
     */
    public function join(
        $foreignTable,
        $masterField = null,
        $joinKind = null,
        $foreignAlias = null
    ) {
        $j = [];

        // try to find alias in foreign table definition
        // TODO this behavior should be deprecated
        if ($foreignAlias === null) {
            [$foreignTable, $foreignAlias] = array_pad(explode(' ', $foreignTable, 2), 2, null);
        }

        // split and deduce fields
        // TODO this will not allow table names with dots in there !!!
        [$f1, $f2] = array_pad(explode('.', $foreignTable, 2), 2, null);

        if (is_object($masterField)) {
            $j['expr'] = $masterField;
        } else {
            // split and deduce primary table
            if ($masterField === null) {
                [$m1, $m2] = [null, null];
            } else {
                [$m1, $m2] = array_pad(explode('.', $masterField, 2), 2, null);
            }
            if ($m2 === null) {
                $m2 = $m1;
                $m1 = null;
            }
            if ($m1 === null) {
                $m1 = $this->getMainTable();
            }

            // identify fields we use for joins
            if ($f2 === null && $m2 === null) {
                $m2 = $f1 . '_id';
            }
            if ($m2 === null) {
                $m2 = 'id';
            }
            $j['m1'] = $m1;
            $j['m2'] = $m2;
        }

        $j['f1'] = $f1;
        if ($f2 === null) {
            $f2 = 'id';
        }
        $j['f2'] = $f2;

        $j['t'] = $joinKind ?? 'left';
        $j['fa'] = $foreignAlias;

        $this->args['join'][] = $j;

        return $this;
    }

    protected function _renderJoin(): ?string
    {
        if (!isset($this->args['join'])) {
            return '';
        }
        $joins = [];
        foreach ($this->args['join'] as $j) {
            $jj = $j['t'] . ' join '
                . $this->escapeIdentifierSoft($j['f1'])
                . ($j['fa'] !== null ? ' ' . $this->escapeIdentifier($j['fa']) : '')
                . ' on ';

            if (isset($j['expr'])) {
                $jj .= $this->consume($j['expr'], self::ESCAPE_PARAM);
            } else {
                $jj .= $this->escapeIdentifier($j['fa'] ?? $j['f1']) . '.'
                    . $this->escapeIdentifier($j['f2']) . ' = '
                    . ($j['m1'] === null ? '' : $this->escapeIdentifier($j['m1']) . '.')
                    . $this->escapeIdentifier($j['m2']);
            }
            $joins[] = $jj;
        }

        return ' ' . implode(' ', $joins);
    }

    // }}}

    // {{{ where() and having() specification and rendering

    /**
     * Adds condition to your query.
     *
     * Examples:
     *  $q->where('id', 1);
     *
     * By default condition implies equality. You can specify a different comparison operator
     * by using 3-argument format:
     *  $q->where('id', '>', 1);
     *
     * You may use Expression as any part of the query.
     *  $q->where($q->expr('a = b'));
     *  $q->where('date', '>', $q->expr('now()'));
     *  $q->where($q->expr('length(password)'), '>', 5);
     *
     * If you specify Query as an argument, it will be automatically surrounded by parentheses:
     *  $q->where('user_id', $q->dsql()->table('users')->field('id'));
     *
     * To specify OR conditions:
     *  $q->where($q->orExpr()->where('a', 1)->where('b', 1));
     *
     * @param string|Expressionable                            $field    Field or Expression
     * @param ($value is null ? mixed : non-empty-string|null) $operator Condition such as '=', '>' or 'not like'
     * @param ($operator is string|null ? mixed : never)       $value    Value. Will be quoted unless you pass expression
     * @param 'where'|'having'                                 $kind     Do not use directly. Use having()
     * @param int                                              $numArgs  when $kind is passed, we can't determine number of
     *                                                                   actual arguments, so this argument must be specified
     *
     * @return $this
     */
    public function where($field, $operator = null, $value = null, $kind = 'where', $numArgs = null)
    {
        // number of passed arguments will be used to determine if arguments were specified or not
        if ($numArgs === null) {
            $numArgs = 'func_num_args'();
        }

        if (is_string($field) && preg_match('~([><!=]|(<!\w)(not|is|in|like))\s*$~i', $field)) {
            throw (new Exception('Field condition must be passed separately'))
                ->addMoreInfo('field', $field);
        }

        if ($numArgs === 1) {
            if (is_string($field)) {
                $field = $this->expr($field);
                $field->wrapInParentheses = true;
            } elseif (!$field instanceof Expression || !$field->wrapInParentheses) {
                $field = $this->expr('[]', [$field]);
                $field->wrapInParentheses = true;
            }

            $this->args[$kind][] = [$field];
        } else {
            if ($numArgs === 2) {
                $value = $operator;
                $operator = null;
            } elseif ($operator === null) {
                throw new \InvalidArgumentException();
            }

            if (is_object($value) && !$value instanceof Expressionable) {
                throw (new Exception('Value cannot be converted to SQL-compatible expression'))
                    ->addMoreInfo('field', $field)
                    ->addMoreInfo('value', $value);
            }

            $this->args[$kind][] = [$field, $operator, $value];
        }

        return $this;
    }

    /**
     * Same syntax as where().
     *
     * @param string|Expressionable                            $field    Field or Expression
     * @param ($value is null ? mixed : non-empty-string|null) $operator Condition such as '=', '>' or 'not like'
     * @param ($operator is string|null ? mixed : never)       $value    Value. Will be quoted unless you pass expression
     *
     * @return $this
     */
    public function having($field, $operator = null, $value = null)
    {
        return $this->where($field, $operator, $value, 'having', 'func_num_args'());
    }

    /**
     * Subroutine which renders either [where] or [having].
     *
     * @param 'where'|'having' $kind
     *
     * @return list<string>
     */
    protected function _subrenderWhere($kind): array
    {
        // where() might have been called multiple times
        // collect all conditions, then join them with AND keyword
        $res = [];
        foreach ($this->args[$kind] as $row) {
            $res[] = $this->_subrenderCondition($row);
        }

        return $res;
    }

    /**
     * @param \Closure(string, string): string $makeSqlFx
     */
    protected function _renderConditionBinaryReuse(
        string $sqlLeft,
        string $sqlRight,
        \Closure $makeSqlFx,
        bool $allowReuseLeft = true,
        bool $allowReuseRight = true,
        string $internalIdentifier = 'reuse'
    ): string {
        $nonTrivialSqlRegex = '~\s|\(~';
        $subqueryLeftColumnSql = $allowReuseLeft && preg_match($nonTrivialSqlRegex, $sqlLeft)
            ? $this->escapeIdentifier('__atk4_' . $internalIdentifier . '_left__')
            : null;
        $subqueryRightColumnSql = $allowReuseRight && preg_match($nonTrivialSqlRegex, $sqlRight)
            ? $this->escapeIdentifier('__atk4_' . $internalIdentifier . '_right__')
            : null;

        $subqueryFromSql = null;
        if ($subqueryLeftColumnSql !== null || $subqueryRightColumnSql !== null) {
            $subqueryFromSql = 'select ';
            if ($subqueryLeftColumnSql !== null) {
                $subqueryFromSql .= $sqlLeft . ' ' . $subqueryLeftColumnSql;
                $sqlLeft = $subqueryLeftColumnSql;
            }
            if ($subqueryRightColumnSql !== null) {
                if ($subqueryLeftColumnSql !== null) {
                    $subqueryFromSql .= ', ';
                }
                $subqueryFromSql .= $sqlRight . ' ' . $subqueryRightColumnSql;
                $sqlRight = $subqueryRightColumnSql;
            }
        }

        $res = $makeSqlFx($sqlLeft, $sqlRight);

        if ($subqueryFromSql !== null) {
            $isOracle = $this->escapeStringLiteral("\x00") === 'chr(0)';
            if ($isOracle) {
                $subqueryFromSql .= ' from DUAL';
            }

            $res = '(select ' . $res . ' from (' . $subqueryFromSql . ') '
                . $this->escapeIdentifier('__atk4_' . $internalIdentifier . '_tmp__') . ')';
        }

        return $res;
    }

    /**
     * @param non-empty-string                                                     $operator
     * @param string|($operator is 'in'|'not in' ? non-empty-list<string> : never) $sqlRight
     */
    protected function _renderConditionBinary(string $operator, string $sqlLeft, $sqlRight): string
    {
        return $sqlLeft . ' ' . $operator . ' ' . (is_array($sqlRight)
            ? '(' . implode(', ', $sqlRight) . ')'
            : $sqlRight);
    }

    protected function _renderConditionLikeOperator(bool $negated, string $sqlLeft, string $sqlRight): string
    {
        $sqlRightEscaped = 'regexp_replace(' . $sqlRight . ', '
            . $this->escapeStringLiteral('(\\\[\\\_%])|(\\\)') . ', '
            . $this->escapeStringLiteral('\1\2\2') . ')';

        return $sqlLeft . ($negated ? ' not' : '') . ' like ' . $sqlRightEscaped
            . ' escape ' . $this->escapeStringLiteral('\\');
    }

    protected function _renderConditionRegexpOperator(bool $negated, string $sqlLeft, string $sqlRight, bool $binary = false): string
    {
        return ($negated ? 'not ' : '') . 'regexp_like(' . $sqlLeft . ', ' . $sqlRight
            . ', ' . $this->escapeStringLiteral(($binary ? '' : 'i') . 's') . ')';
    }

    /**
     * @param array{mixed}|array{mixed, non-empty-string|null, mixed} $row
     */
    protected function _subrenderCondition(array $row): string
    {
        if (count($row) === 1) {
            [$field] = $row;
        } elseif (count($row) === 3) {
            [$field, $operator, $value] = $row;
        } else {
            throw new \InvalidArgumentException();
        }

        $field = $this->consume($field, self::ESCAPE_IDENTIFIER_SOFT);

        if (count($row) === 1) {
            return $field;
        }

        if ($operator === null) {
            $operator = '=';
        }

        $operator = strtolower($operator);

        if (!in_array($operator, $this->supportedOperators, true)) {
            throw (new Exception('Unsupported operator'))
                ->addMoreInfo('operator', $operator);
        }

        // special conditions (IS | IS NOT) if value is null
        if ($value === null) {
            if ($operator === '=') {
                return $field . ' is null';
            } elseif ($operator === '!=') {
                return $field . ' is not null';
            }

            throw (new Exception('Unsupported operator for null value'))
                ->addMoreInfo('operator', $operator);
        }

        // special conditions (IN | NOT IN) if value is array
        if (is_array($value)) {
            if (in_array($operator, ['in', 'not in'], true)) {
                // special treatment of empty array condition
                if (count($value) === 0) {
                    if ($operator === 'in') {
                        return '1 = 0'; // never true
                    }

                    return '1 = 1'; // always true
                }

                foreach ($value as $v) {
                    if ($v === null) {
                        throw (new Exception('Null value in IN operator is not supported'))
                            ->addMoreInfo('operator', $operator);
                    }
                }

                $values = array_map(fn ($v) => $this->consume($v, self::ESCAPE_PARAM), $value);

                return $this->_renderConditionBinary($operator, $field, $values);
            }

            throw (new Exception('Unsupported operator for array value'))
                ->addMoreInfo('operator', $operator);
        } elseif (!$value instanceof Expressionable && in_array($operator, ['in', 'not in'], true)) {
            throw (new Exception('Unsupported operator for non-array value'))
                ->addMoreInfo('operator', $operator);
        }

        // if value is object, then it should be Expression or Query itself
        // otherwise just escape value
        $value = $this->consume($value, self::ESCAPE_PARAM);

        if (in_array($operator, ['like', 'not like'], true)) {
            return $this->_renderConditionLikeOperator($operator === 'not like', $field, $value);
        } elseif (in_array($operator, ['regexp', 'not regexp'], true)) {
            return $this->_renderConditionRegexpOperator($operator === 'not regexp', $field, $value);
        }

        return $this->_renderConditionBinary($operator, $field, $value);
    }

    protected function _renderWhere(): ?string
    {
        if (!isset($this->args['where'])) {
            return null;
        }

        return ' where ' . implode(' and ', $this->_subrenderWhere('where'));
    }

    protected function _renderOrwhere(): ?string
    {
        if (isset($this->args['where']) && isset($this->args['having'])) {
            throw new Exception('Mixing of WHERE and HAVING conditions not allowed in query expression');
        }

        foreach (['where', 'having'] as $kind) {
            if (isset($this->args[$kind])) {
                return implode(' or ', $this->_subrenderWhere($kind));
            }
        }

        return null;
    }

    protected function _renderAndwhere(): ?string
    {
        if (isset($this->args['where']) && isset($this->args['having'])) {
            throw new Exception('Mixing of WHERE and HAVING conditions not allowed in query expression');
        }

        foreach (['where', 'having'] as $kind) {
            if (isset($this->args[$kind])) {
                return implode(' and ', $this->_subrenderWhere($kind));
            }
        }

        return null;
    }

    protected function _renderHaving(): ?string
    {
        if (!isset($this->args['having'])) {
            return null;
        }

        return ' having ' . implode(' and ', $this->_subrenderWhere('having'));
    }

    // }}}

    // {{{ group()

    /**
     * Implements GROUP BY functionality. Simply pass either field name
     * as string or expression.
     *
     * @param string|Expressionable $group
     *
     * @return $this
     */
    public function group($group)
    {
        $this->args['group'][] = $group;

        return $this;
    }

    protected function _renderGroup(): ?string
    {
        if (!isset($this->args['group'])) {
            return '';
        }

        $g = array_map(function ($v) {
            return $this->consume($v, self::ESCAPE_IDENTIFIER_SOFT);
        }, $this->args['group']);

        return ' group by ' . implode(', ', $g);
    }

    // }}}

    // {{{ Set field implementation

    /**
     * Sets field value for INSERT or UPDATE statements.
     *
     * @param string|Expressionable      $field Name of the field
     * @param scalar|Expressionable|null $value Value of the field
     *
     * @return $this
     */
    public function set($field, $value = null)
    {
        $this->args['set'][] = [$field, $value];

        return $this;
    }

    /**
     * @param array<string, scalar|Expressionable|null> $fields
     *
     * @return $this
     */
    public function setMulti($fields)
    {
        foreach ($fields as $k => $v) {
            $this->set($k, $v);
        }

        return $this;
    }

    /**
     * @param non-empty-array<string|Expressionable> $fields
     *
     * @return $this
     */
    public function setSelect(Expression $query, array $fields)
    {
        assert(!isset($this->args['set']));

        $this->args['set'] = [$query, $fields];

        return $this;
    }

    protected function _renderSet(): ?string
    {
        $res = [];
        foreach ($this->args['set'] as [$field, $value]) {
            $field = $this->consume($field, self::ESCAPE_IDENTIFIER);
            $value = $this->consume($value, self::ESCAPE_PARAM);

            $res[] = $field . '=' . $value;
        }

        return implode(', ', $res);
    }

    protected function _renderSetFields(): ?string
    {
        $fields = !is_array($this->args['set'][0])
            ? $this->args['set'][1]
            : array_map(static fn ($v) => $v[0], $this->args['set']);

        $res = [];
        foreach ($fields as $field) {
            $field = $this->consume($field, self::ESCAPE_IDENTIFIER);

            $res[] = $field;
        }

        return ' (' . implode(', ', $res) . ')';
    }

    protected function _renderSetValues(): ?string
    {
        if (!is_array($this->args['set'][0])) {
            $query = $this->args['set'][0];
            $wrapInParenthesesOrig = $query->wrapInParentheses;
            try {
                $query->wrapInParentheses = false;

                return $this->consume($query, self::ESCAPE_PARAM);
            } finally {
                $query->wrapInParentheses = $wrapInParenthesesOrig;
            }
        }

        $res = [];
        foreach ($this->args['set'] as $pair) {
            $value = $this->consume($pair[1], self::ESCAPE_PARAM);

            $res[] = $value;
        }

        return 'values (' . implode(', ', $res) . ')';
    }

    // }}}

    // {{{ Option

    /**
     * Set options for particular mode.
     *
     * @param string|Expressionable $option
     * @param string                $mode
     *
     * @return $this
     */
    public function option($option, $mode = 'select')
    {
        $this->args['option'][$mode][] = $option;

        return $this;
    }

    protected function _renderOption(): ?string
    {
        if (!isset($this->args['option'][$this->mode])) {
            return '';
        }

        return ' ' . implode(' ', $this->args['option'][$this->mode]);
    }

    // }}}

    // {{{ Order

    /**
     * Orders results by field or Expression. See documentation for full
     * list of possible arguments.
     *
     * $q->order('name');
     * $q->order('name desc');
     * $q->order(['name desc', 'id asc'])
     * $q->order('name', true);
     *
     * @param string|Expressionable|array<int, string|Expressionable> $order     order by
     * @param ($order is array ? never : string|bool)                 $direction true to sort descending
     *
     * @return $this
     */
    public function order($order, $direction = null)
    {
        if (is_string($order) && str_contains($order, ',')) {
            throw new Exception('Comma-separated fields list is no longer accepted, use array instead');
        }

        if (is_array($order)) {
            if ($direction !== null) {
                throw new Exception('If first argument is array, second argument must not be used');
            }

            foreach (array_reverse($order) as $o) {
                $this->order($o);
            }

            return $this;
        }

        // first argument may contain space, to divide field and ordering keyword
        if ($direction === null && is_string($order) && str_contains($order, ' ')) {
            $lastSpacePos = strrpos($order, ' ');
            if (in_array(strtolower(substr($order, $lastSpacePos + 1)), ['desc', 'asc'], true)) {
                $direction = substr($order, $lastSpacePos + 1);
                $order = substr($order, 0, $lastSpacePos);
            }
        }

        if (is_bool($direction)) {
            $direction = $direction ? 'desc' : '';
        } elseif (strtolower($direction ?? '') === 'asc') {
            $direction = '';
        }
        // no else - allow custom order like "order by name desc nulls last" for Oracle

        $this->args['order'][] = [$order, $direction];

        return $this;
    }

    /**
     * @param list<string> $sqls
     *
     * @return list<string>
     */
    protected function deduplicateRenderOrder(array $sqls): array
    {
        return $sqls;
    }

    protected function _renderOrder(): ?string
    {
        if (!isset($this->args['order'])) {
            return '';
        }

        $sqls = [];
        foreach ($this->args['order'] as $tmp) {
            [$arg, $desc] = $tmp;
            $sqls[] = $this->consume($arg, self::ESCAPE_IDENTIFIER_SOFT) . ($desc ? (' ' . $desc) : '');
        }

        $sqls = array_reverse($sqls);
        $sqlsDeduplicated = $this->deduplicateRenderOrder($sqls);

        return ' order by ' . implode(', ', $sqlsDeduplicated);
    }

    // }}}

    // {{{ Limit

    /**
     * Limit how many rows will be returned.
     *
     * @param int $cnt   Number of rows to return
     * @param int $shift Offset, how many rows to skip
     *
     * @return $this
     */
    public function limit($cnt, $shift = null)
    {
        $this->args['limit'] = [
            'cnt' => $cnt,
            'shift' => $shift,
        ];

        return $this;
    }

    protected function _renderLimit(): ?string
    {
        if (!isset($this->args['limit'])) {
            return null;
        }

        return ' limit ' . (int) $this->args['limit']['shift']
            . ', ' . (int) $this->args['limit']['cnt'];
    }

    // }}}

    // {{{ Exists

    /**
     * Creates 'select exists' query based on the query object.
     *
     * @return self
     */
    public function exists()
    {
        return $this->dsql()->mode('select')->option('exists')->field($this);
    }

    // }}}

    #[\Override]
    public function __debugInfo(): array
    {
        $arr = [
            // 'mode' => $this->mode,
            'R' => 'n/a',
            'R_params' => 'n/a',
            // 'template' => $this->template,
            // 'templateArgs' => $this->args,
        ];

        try {
            $arr['R'] = $this->getDebugQuery();
            $arr['R_params'] = $this->render()[1];
        } catch (\Exception $e) {
            $arr['R'] = get_class($e) . ': ' . $e->getMessage();
        }

        return $arr;
    }

    // {{{ Miscelanious

    /**
     * Renders query template. If the template is not explicitly use "select" mode.
     */
    #[\Override]
    public function render(): array
    {
        if ($this->template === null) {
            $modeBackup = $this->mode;
            $templateBackup = $this->template;
            try {
                $this->mode('select');

                return parent::render();
            } finally {
                $this->mode = $modeBackup;
                $this->template = $templateBackup;
            }
        }

        return parent::render();
    }

    #[\Override]
    protected function renderNested(): array
    {
        if (isset($this->args['order']) && !isset($this->args['limit'])) {
            $orderOrig = $this->args['order'];
            unset($this->args['order']);
        } else {
            $orderOrig = null;
        }
        try {
            [$sql, $params] = parent::renderNested();
        } finally {
            if ($orderOrig !== null) {
                $this->args['order'] = $orderOrig;
            }
        }

        return [$sql, $params];
    }

    /**
     * Switch template for this query. Determines what would be done
     * on execute.
     *
     * By default it is in SELECT mode
     *
     * @return $this
     */
    public function mode(string $mode)
    {
        $templatePropertyName = 'template' . ucfirst($mode);

        if (@isset($this->{$templatePropertyName})) {
            $this->mode = $mode;
            $this->template = $this->{$templatePropertyName};
        } else {
            throw (new Exception('Query does not have this mode'))
                ->addMoreInfo('mode', $mode);
        }

        return $this;
    }

    #[\Override]
    public function expr($template = [], array $arguments = []): Expression
    {
        $class = $this->expressionClass;
        $e = new $class($template, $arguments);
        $e->connection = $this->connection;

        return $e;
    }

    /**
     * Create Query object with the same connection.
     *
     * @param string|array<string, mixed> $defaults
     *
     * @return self
     */
    public function dsql($defaults = [])
    {
        $q = new static($defaults);
        $q->connection = $this->connection;

        return $q;
    }

    /**
     * Returns Expression object for NOW() or CURRENT_TIMESTAMP() method.
     */
    public function exprNow(?int $precision = null): Expression
    {
        return $this->expr(
            'current_timestamp(' . ($precision !== null ? '[]' : '') . ')',
            $precision !== null ? [$precision] : []
        );
    }

    /**
     * Returns new Query object of [or] expression.
     *
     * @return self
     */
    public function orExpr()
    {
        return $this->dsql(['template' => '[orwhere]']);
    }

    /**
     * Returns new Query object of [and] expression.
     *
     * @return self
     */
    public function andExpr()
    {
        return $this->dsql(['template' => '[andwhere]']);
    }

    /**
     * Returns a query for a function, which can be used as part of the GROUP
     * query which would concatenate all matching fields.
     *
     * @param string|Expressionable $field
     *
     * @return Expression
     */
    public function groupConcat($field, string $separator = ',')
    {
        throw new Exception('groupConcat() is SQL-dependent, so use a correct class');
    }

    /**
     * @param string|Expressionable ...$values
     *
     * @return Expression
     */
    public function fxConcat(...$values)
    {
        return $this->expr(
            'concat(' . implode(', ', array_fill(0, count($values), '[]')) . ')',
            $values
        );
    }

    private function valueToJson(Expressionable $value): Expressionable
    {
        $makeReplaceFx = function ($v, $from, $to) {
            return $this->expr('replace([], [], [])', [
                $v,
                new RawExpression($this->escapeStringLiteral($from)),
                new RawExpression($this->escapeStringLiteral($to)),
            ]);
        };

        $res = $makeReplaceFx($value, '"', '\"');
        $res = $makeReplaceFx($res, '\\', '\\\\');
        $res = $makeReplaceFx($res, '\\\"', '\"');

        foreach ([...range(1, 0x1F), 0x7F] as $i) {
            $res = $makeReplaceFx($res, chr($i), '\u' . str_pad(dechex($i), 4, '0', \STR_PAD_LEFT));
        }

        $res = $this->fxConcat(
            new RawExpression($this->escapeStringLiteral('"')),
            $res,
            new RawExpression($this->escapeStringLiteral('"'))
        );

        return $this->expr('case when [] is not null then [] else [] end', [
            $value,
            $res,
            new RawExpression($this->escapeStringLiteral('null')),
        ]);
    }

    /**
     * @param list<Expressionable> $values
     *
     * @return Expression
     */
    public function fxJsonArray(array $values)
    {
        $parts = [];
        $isFirst = true;
        foreach ($values as $v) {
            if ($isFirst) {
                $isFirst = false;
            } else {
                $parts[] = new RawExpression($this->escapeStringLiteral(', '));
            }

            $parts[] = $this->valueToJson($v);
        }

        return $this->fxConcat(
            new RawExpression($this->escapeStringLiteral('[')),
            ...$parts,
            ...[new RawExpression($this->escapeStringLiteral(']'))]
        );
    }

    /**
     * WARNING: MySQL 5.7.21 or lower and any MariaDB silently limit the output length
     * using `group_concat_max_len` server variable. The default value is only 1 MB.
     *
     * @return Expression
     */
    public function fxJsonArrayAgg(Expressionable $value)
    {
        $jsonExpr = $this->valueToJson($value);

        return $this->fxConcat(
            new RawExpression($this->escapeStringLiteral('[')),
            $this->expr('replace([], [], [])', [
                $this->groupConcat($jsonExpr, '-""-'),
                new RawExpression($this->escapeStringLiteral('-""-')),
                new RawExpression($this->escapeStringLiteral(', ')),
            ]),
            new RawExpression($this->escapeStringLiteral(']'))
        );
    }

    /**
     * @param mixed $data
     *
     * @return mixed
     */
    private function jsonArrayExtract($data, string $path, bool $requireRoot = true)
    {
        if ($requireRoot) {
            assert(str_starts_with($path, '$'));
            $path = substr($path, 1);
        }

        if ($path === '') {
            return $data;
        } elseif (!is_array($data)) {
            return null;
        }

        $matched = preg_match('~^((?:\.(")?((?(2)[^"\\\]+|[^.["\\\ (]+))(?(2)")|\[(\d+|\*)\])((?1)?))$~', $path, $matches, \PREG_UNMATCHED_AS_NULL) === 1;
        assert($matched);

        $k = $matches[3] ?? $matches[4];
        $remainingPath = $matches[5];

        if ($k === '*') {
            return array_values(array_map(fn ($v) => $this->jsonArrayExtract($v, $remainingPath, false), $data));
        }

        $v = $data[$k] ?? null;

        return $this->jsonArrayExtract($v, $remainingPath, false);
    }

    /**
     * @param non-empty-array<string, string> $columnPaths
     *
     * @return list<non-empty-array<string, mixed>>
     */
    private function jsonToArrayTable(Expressionable $json, array $columnPaths, string $rowsPath)
    {
        if ($json instanceof Expression && $json->template === 'concat([], [], [])') {
            assert(array_keys($json->args) === ['custom']);
            assert(array_keys($json->args['custom']) === [0, 1, 2]);
            assert($json->args['custom'][0] instanceof Expression);
            assert($json->args['custom'][0]->template === $this->escapeStringLiteral('['));
            assert($json->args['custom'][2] instanceof Expression);
            assert($json->args['custom'][2]->template === $this->escapeStringLiteral(']'));
            $json = $json->args['custom'][1];
            assert($json instanceof Expression);
            assert(is_string($json->args['custom'][0]));
            $json->args['custom'][0] = '[' . $json->args['custom'][0] . ']';
        }

        assert($json instanceof Expression);
        assert($json->template === '[]');
        assert(array_keys($json->args) === ['custom']);
        assert(array_keys($json->args['custom']) === [0]);
        assert(is_string($json->args['custom'][0]));

        $jsonData = json_decode($json->args['custom'][0], true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);

        $rows = $this->jsonArrayExtract($jsonData, $rowsPath);

        $res = [];
        foreach ($rows ?? [] as $row) {
            $res[] = array_map(fn ($path) => $this->jsonArrayExtract($row, $path), $columnPaths);
        }

        return $res;
    }

    /**
     * @param 'boolean'|'bigint'|'float'|'string'|'json' $type
     *
     * @return Expression
     */
    public function fxJsonValue(Expressionable $json, string $path, string $type)
    {
        return $this->jsonTable(
            $this->fxConcat(
                new RawExpression($this->escapeStringLiteral('[')),
                $json,
                new RawExpression($this->escapeStringLiteral(']')),
            ),
            ['cv' => ['path' => $path, 'type' => $type]]
        );
    }

    /**
     * @param non-empty-array<string, array{path: string, type: 'boolean'|'bigint'|'float'|'string'|'json'}> $columns
     *
     * @return Expression
     */
    public function jsonTable(Expressionable $json, array $columns, string $rowsPath = '$[*]')
    {
        $rows = $this->jsonToArrayTable($json, array_map(static fn ($v) => $v['path'], $columns), $rowsPath);

        if ($rows === []) {
            $query = $this->dsql();
            foreach ($columns as $k => $column) {
                $query->field($query->expr('[]', [null]), $k);
            }
            $query->limit(0);

            return $query;
        }

        // this fallback approach is resource intensive, limit the maximum row count
        // as it is limited by maximum unioned queries and maximum bound variables anyway
        assert(count($rows) <= 5_000);

        // TODO simplify once https://github.com/atk4/data/pull/677 is merged
        $queries = [];
        $isFirst = true;
        foreach ($rows as $row) {
            $query = $this->dsql();
            $query->wrapInParentheses = false;
            foreach ($columns as $k => $column) {
                $v = $row[$k];

                if ($v !== null) {
                    if ($column['type'] === 'json') {
                        $v = json_encode($v, \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
                    } elseif (is_array($v)) {
                        $v = null;
                    }
                }

                $query->field($query->expr('[]', [$v]), $isFirst ? $k : null);
            }

            $queries[] = $query;
            $isFirst = false;
        }

        return $this->expr([
            'template' => implode(' union all ', array_map(static fn () => '[]', $queries)),
            'wrapInParentheses' => true,
        ], $queries);
    }

    /**
     * @param list<non-empty-array<string, scalar|null>>                          $rows
     * @param non-empty-array<string, 'boolean'|'bigint'|'float'|'string'|'json'> $columnTypes
     *
     * @return Expression
     */
    protected function makeArrayTable(array $rows, array $columnTypes)
    {
        $columnNames = array_keys($columnTypes);

        $jsonRows = [];
        foreach ($rows as $row) {
            assert(array_keys($row) === $columnNames);

            $jsonRows[] = array_values($row);
        }

        $json = json_encode($jsonRows, \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        $columns = [];
        $i = 0;
        foreach ($columnTypes as $k => $type) {
            $columns[$k] = ['path' => '$[' . $i . ']', 'type' => $type];

            ++$i;
        }

        return $this->jsonTable($this->expr('[]', [$json]), $columns);
    }

    /**
     * Returns Query object of [case] expression.
     *
     * @param string|Expressionable|null $operand optional operand for case expression
     *
     * @return self
     */
    public function caseExpr($operand = null)
    {
        $q = $this->dsql(['template' => '[case]']);

        if ($operand !== null) {
            $q->args['case_operand'] = [$operand];
        }

        return $q;
    }

    /**
     * Add when/then condition for [case] expression.
     *
     * @param mixed $when Condition as array for normal form [case] statement or just value in case of short form [case] statement
     * @param mixed $then Then expression or value
     *
     * @return $this
     */
    public function caseWhen($when, $then)
    {
        if (is_array($when) && count($when) === 2) {
            $when = [$when[0], null, $when[1]];
        }

        $this->args['case_when'][] = [$when, $then];

        return $this;
    }

    /**
     * Add else condition for [case] expression.
     *
     * @param mixed $else Else expression or value
     *
     * @return $this
     */
    public function caseElse($else)
    {
        $this->args['case_else'] = [$else];

        return $this;
    }

    protected function _renderCase(): ?string
    {
        if (!isset($this->args['case_when'])) {
            return null;
        }

        $res = '';

        // operand
        $isShortForm = isset($this->args['case_operand']);
        if ($isShortForm) {
            $res .= ' ' . $this->consume($this->args['case_operand'][0], self::ESCAPE_IDENTIFIER_SOFT);
        }

        // when, then
        foreach ($this->args['case_when'] as $row) {
            if (!array_key_exists(0, $row) || !array_key_exists(1, $row)) {
                throw (new Exception('Incorrect use of "when" method parameters'))
                    ->addMoreInfo('row', $row);
            }

            $res .= ' when ';
            if ($isShortForm) {
                // short-form
                if (is_array($row[0])) {
                    throw (new Exception('When using short form CASE statement, then you should not set array as when() method 1st parameter'))
                        ->addMoreInfo('when', $row[0]);
                }
                $res .= $this->consume($row[0], self::ESCAPE_PARAM);
            } else {
                $res .= $this->_subrenderCondition($row[0]);
            }

            // then
            $res .= ' then ' . $this->consume($row[1], self::ESCAPE_PARAM);
        }

        // else
        if (array_key_exists('case_else', $this->args)) {
            $res .= ' else ' . $this->consume($this->args['case_else'][0], self::ESCAPE_PARAM);
        }

        return ' case' . $res . ' end';
    }

    /**
     * Sets value in args array. Doesn't allow duplicate aliases.
     *
     * @param mixed $value
     */
    protected function _setArgs(string $kind, ?string $alias, $value): void
    {
        if ($alias === null) {
            $this->args[$kind][] = $value;
        } else {
            if (isset($this->args[$kind][$alias])) {
                throw (new Exception('Alias must be unique'))
                    ->addMoreInfo('kind', $kind)
                    ->addMoreInfo('alias', $alias);
            }

            if ($alias === (string) (int) $alias) {
                if ($kind === 'field') {
                    $alias = self::FIELD_INT_STRING_PREFIX . $alias;
                } else {
                    throw (new Exception('Alias must be not int-string'))
                        ->addMoreInfo('kind', $kind)
                        ->addMoreInfo('alias', $alias);
                }
            }

            $this->args[$kind][$alias] = $value;
        }
    }

    // }}}
}

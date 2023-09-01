<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

/**
 * Perform query operation on SQL server (such as select, insert, delete, etc).
 */
abstract class Query extends Expression
{
    /** Template name for render. */
    public string $mode = 'select';

    /** @var string|Expression If no fields are defined, this field is used. */
    public $defaultField = '*';

    /** @var class-string<Expression> */
    protected string $expressionClass;

    public bool $wrapInParentheses = true;

    /** @var array<string> */
    protected array $supportedOperators = ['=', '!=', '<', '>', '<=', '>=', 'like', 'not like', 'in', 'not in'];

    protected string $templateSelect = '[with]select[option] [field] [from] [table][join][where][group][having][order][limit]';
    protected string $templateInsert = 'insert[option] into [tableNoalias] ([setFields]) values ([setValues])';
    protected string $templateReplace = 'replace[option] into [tableNoalias] ([setFields]) values ([setValues])';
    protected string $templateDelete = '[with]delete [from] [tableNoalias][where][having]';
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
     * You can use $q->dsql() for subqueries. Subqueries will be wrapped in
     * brackets.
     *  $q->field( $q->dsql()->table('x')..., 'alias');
     *
     * If you need to use funky name for the field (e.g, one containing
     * a dot or a space), you should wrap it into expression:
     *  $q->field($q->expr('{}', ['fun...ky.field']), 'f');
     *
     * @param string|Expressionable $field Specifies field to select
     * @param string                $alias Specify alias for this field
     *
     * @return $this
     */
    public function field($field, $alias = null)
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
                return $this->consume($this->defaultField);
            }

            return $this->defaultField;
        }

        $res = [];
        foreach ($this->args['field'] as $alias => $field) {
            // do not add alias when:
            //  - we don't want aliases
            //  - OR alias is the same as field
            //  - OR alias is numeric
            if ($addAlias === false
                || (is_string($field) && $alias === $field)
                || is_int($alias)
            ) {
                $alias = null;
            }

            // will parameterize the value and escape if necessary
            $field = $this->consume($field, self::ESCAPE_IDENTIFIER_SOFT);

            if ($alias) {
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
     * @param string|Expressionable $table Specifies table
     * @param string                $alias Specify alias for this table
     *
     * @return $this
     */
    public function table($table, $alias = null)
    {
        if ($table instanceof self && $alias === null) {
            throw new Exception('If table is set as subquery, then table alias is required');
        }

        if (is_string($table) && $alias === null) {
            $alias = $table;
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
        return isset($this->args['table']) ? 'from' : '';
    }

    // }}}

    // {{{ with()

    /**
     * Specify WITH query to be used.
     *
     * @param Query                   $cursor    Specifies cursor query or array [alias => query] for adding multiple
     * @param string                  $alias     Specify alias for this cursor
     * @param array<int, string>|null $fields    Optional array of field names used in cursor
     * @param bool                    $recursive Is it recursive?
     *
     * @return $this
     */
    public function with(self $cursor, string $alias, array $fields = null, bool $recursive = false)
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
     *  $q->join('address',
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
        // TODO this behaviour should be deprecated
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
                $jj .= $this->consume($j['expr']);
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
     * By default condition implies equality. You can specify a different comparison
     * operator by using 3-argument
     * format:
     *  $q->where('id', '>', 1);
     *
     * You may use Expression as any part of the query.
     *  $q->where($q->expr('a = b'));
     *  $q->where('date', '>', $q->expr('now()'));
     *  $q->where($q->expr('length(password)'), '>', 5);
     *
     * If you specify Query as an argument, it will be automatically
     * surrounded by brackets:
     *  $q->where('user_id', $q->dsql()->table('users')->field('id'));
     *
     * To specify OR conditions:
     *  $q->where($q->orExpr()->where('a', 1)->where('b', 1));
     *
     * @param string|Expressionable $field   Field or Expression
     * @param mixed                 $cond    Condition such as '=', '>' or 'not like'
     * @param mixed                 $value   Value. Will be quoted unless you pass expression
     * @param string                $kind    Do not use directly. Use having()
     * @param int                   $numArgs when $kind is passed, we can't determine number of
     *                                       actual arguments, so this argument must be specified
     *
     * @return $this
     */
    public function where($field, $cond = null, $value = null, $kind = 'where', $numArgs = null)
    {
        // Number of passed arguments will be used to determine if arguments were specified or not
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
                $value = $cond;
                unset($cond);
            }

            if (is_object($value) && !$value instanceof Expressionable) {
                throw (new Exception('Value cannot be converted to SQL-compatible expression'))
                    ->addMoreInfo('field', $field)
                    ->addMoreInfo('value', $value);
            }

            if ($numArgs === 2) {
                $this->args[$kind][] = [$field, $value];
            } else {
                $this->args[$kind][] = [$field, $cond, $value];
            }
        }

        return $this;
    }

    /**
     * Same syntax as where().
     *
     * @param string|Expressionable $field Field or Expression
     * @param mixed                 $cond  Condition such as '=', '>' or 'not like'
     * @param mixed                 $value Value. Will be quoted unless you pass expression
     *
     * @return $this
     */
    public function having($field, $cond = null, $value = null)
    {
        return $this->where($field, $cond, $value, 'having', 'func_num_args'());
    }

    /**
     * Subroutine which renders either [where] or [having].
     *
     * @param string $kind 'where' or 'having'
     *
     * @return array<int, string>
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
     * @param array<0|1|2, mixed> $row
     */
    protected function _subrenderCondition(array $row): string
    {
        if (count($row) === 3) {
            [$field, $cond, $value] = $row;
        } elseif (count($row) === 2) {
            [$field, $cond] = $row;
        } elseif (count($row) === 1) {
            [$field] = $row;
        } else {
            throw new \InvalidArgumentException();
        }

        $field = $this->consume($field, self::ESCAPE_IDENTIFIER_SOFT);

        if (count($row) === 1) {
            // only a single parameter was passed, so we simply include all
            return $field;
        }

        // if no condition defined - use default
        if (count($row) === 2) {
            $value = $cond;

            if ($value instanceof Expressionable) {
                $value = $value->getDsqlExpression($this);
            }

            if (is_array($value)) {
                $cond = 'in';
            } elseif ($value instanceof self && $value->mode === 'select') {
                $cond = 'in';
            } else {
                $cond = '=';
            }
        } else {
            $cond = strtolower($cond);
        }

        // below we can be sure that all 3 arguments has been passed

        if (!in_array($cond, $this->supportedOperators, true)) {
            throw (new Exception('Unsupported operator'))
                ->addMoreInfo('operator', $cond);
        }

        // special conditions (IS | IS NOT) if value is null
        if ($value === null) { // @phpstan-ignore-line see https://github.com/phpstan/phpstan/issues/4173
            if ($cond === '=') {
                return $field . ' is null';
            } elseif ($cond === '!=') {
                return $field . ' is not null';
            }

            throw (new Exception('Unsupported operator for null value'))
                ->addMoreInfo('operator', $cond);
        }

        // special conditions (IN | NOT IN) if value is array
        if (is_array($value)) {
            if (in_array($cond, ['in', 'not in'], true)) {
                // special treatment of empty array condition
                if (count($value) === 0) {
                    if ($cond === 'in') {
                        return '1 = 0'; // never true
                    }

                    return '1 = 1'; // always true
                }

                $value = '(' . implode(', ', array_map(fn ($v) => $this->consume($v), $value)) . ')';

                return $field . ' ' . $cond . ' ' . $value;
            }

            throw (new Exception('Unsupported operator for array value'))
                ->addMoreInfo('operator', $cond);
        } elseif (!$value instanceof Expressionable && in_array($cond, ['in', 'not in'], true)) {
            throw (new Exception('Unsupported operator for non-array value'))
                ->addMoreInfo('operator', $cond);
        }

        // if value is object, then it should be Expression or Query itself
        // otherwise just escape value
        $value = $this->consume($value, self::ESCAPE_PARAM);

        return $field . ' ' . $cond . ' ' . $value;
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
     * @param string|Expressionable $field Name of the field
     * @param mixed                 $value Value of the field
     *
     * @return $this
     */
    public function set($field, $value = null)
    {
        if (is_array($value)) {
            throw (new Exception('Array values are not supported by SQL'))
                ->addMoreInfo('field', $field)
                ->addMoreInfo('value', $value);
        }

        $this->args['set'][] = [$field, $value];

        return $this;
    }

    /**
     * @param array<string, mixed> $fields
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
        $res = [];
        foreach ($this->args['set'] as $pair) {
            $field = $this->consume($pair[0], self::ESCAPE_IDENTIFIER);

            $res[] = $field;
        }

        return implode(', ', $res);
    }

    protected function _renderSetValues(): ?string
    {
        $res = [];
        foreach ($this->args['set'] as $pair) {
            $value = $this->consume($pair[1], self::ESCAPE_PARAM);

            $res[] = $value;
        }

        return implode(', ', $res);
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
     * @param string|Expressionable|array<int, string|Expressionable> $order order by
     * @param string|bool                                             $desc  true to sort descending
     *
     * @return $this
     */
    public function order($order, $desc = null)
    {
        if (is_string($order) && str_contains($order, ',')) {
            throw new Exception('Comma-separated fields list is no longer accepted, use array instead');
        }

        if (is_array($order)) {
            if ($desc !== null) {
                throw new Exception('If first argument is array, second argument must not be used');
            }

            foreach (array_reverse($order) as $o) {
                $this->order($o);
            }

            return $this;
        }

        // first argument may contain space, to divide field and ordering keyword
        if ($desc === null && is_string($order) && str_contains($order, ' ')) {
            $lastSpacePos = strrpos($order, ' ');
            if (in_array(strtolower(substr($order, $lastSpacePos + 1)), ['desc', 'asc'], true)) {
                $desc = substr($order, $lastSpacePos + 1);
                $order = substr($order, 0, $lastSpacePos);
            }
        }

        if (is_bool($desc)) {
            $desc = $desc ? 'desc' : '';
        } elseif (strtolower($desc ?? '') === 'asc') {
            $desc = '';
        }
        // no else - allow custom order like "order by name desc nulls last" for Oracle

        $this->args['order'][] = [$order, $desc];

        return $this;
    }

    protected function _renderOrder(): ?string
    {
        if (!isset($this->args['order'])) {
            return '';
        }

        $x = [];
        foreach ($this->args['order'] as $tmp) {
            [$arg, $desc] = $tmp;
            $x[] = $this->consume($arg, self::ESCAPE_IDENTIFIER_SOFT) . ($desc ? (' ' . $desc) : '');
        }

        return ' order by ' . implode(', ', array_reverse($x));
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

    /**
     * Switch template for this query. Determines what would be done
     * on execute.
     *
     * By default it is in SELECT mode
     *
     * @param string $mode
     *
     * @return $this
     */
    public function mode($mode)
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

    /**
     * Create Expression object with the same connection.
     *
     * @param string|array<string, mixed> $template
     * @param array<int|string, mixed>    $arguments
     */
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
     * @return Query
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
    public function exprNow(int $precision = null): Expression
    {
        return $this->expr(
            'current_timestamp(' . ($precision !== null ? '[]' : '') . ')',
            $precision !== null ? [$precision] : []
        );
    }

    /**
     * Returns new Query object of [or] expression.
     *
     * @return Query
     */
    public function orExpr()
    {
        return $this->dsql(['template' => '[orwhere]']);
    }

    /**
     * Returns new Query object of [and] expression.
     *
     * @return Query
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
     * Returns Query object of [case] expression.
     *
     * @param mixed $operand optional operand for case expression
     *
     * @return Query
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
     * @param string      $what  Where to set it - table|field
     * @param string|null $alias Alias name
     * @param mixed       $value Value to set in args array
     */
    protected function _setArgs($what, $alias, $value): void
    {
        if ($alias === null) {
            $this->args[$what][] = $value;
        } else {
            if (isset($this->args[$what][$alias])) {
                throw (new Exception('Alias must be unique'))
                    ->addMoreInfo('what', $what)
                    ->addMoreInfo('alias', $alias);
            }

            $this->args[$what][$alias] = $value;
        }
    }

    // }}}
}

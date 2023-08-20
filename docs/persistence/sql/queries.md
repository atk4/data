:::{php:namespace} Atk4\Data\Persistence\Sql
:::

(query)=

:::{php:class} Query
:::

# Queries

Query class represents your SQL query in-the-making. Once you create object of
the Query class, call some of the methods listed below to modify your query. To
actually execute your query and start retrieving data, see {ref}`fetching-result`
section.

You should use {ref}`connect` if possible to create your query objects. All
examples below are using `$c->dsql()` method which generates Query linked to
your established database connection.

Once you have a query object you can execute modifier methods such as
{php:meth}`field()` or {php:meth}`table()` which will change the way how your
query will act.

Once the query is defined, you can either use it inside another query or
expression or you can execute it in exchange for result set.

Quick Example:

```
$query = $c->dsql();

$query->field('name');
$query->where('id', 123);

$name = $query->getOne();
```

## Method invocation principles

Methods of Query are designed to be flexible and concise. Most methods have a
variable number of arguments and some arguments can be skipped:

```
$query->where('id', 123);
$query->where('id', '=', 123); // the same
```

Most methods will accept {php:class}`Expression` or strings. Strings are
escaped or quoted (depending on type of argument). By using {php:class}`Expression`
you can bypass the escaping.

There are 2 types of escaping:

- {php:meth}`Expression::escapeIdentifier()`. Used for field and table names. Surrounds name with `` ` ``.
- {php:meth}`Expression::escapeParam()`. Will convert value into parameter and replace with `:a`

In the next example $a is escaped but $b is parameterized:

```
$query->where('a', 'b');

// where `a` = "b"
```

If you want to switch places and execute `where "b" = 'a'`, then you can resort
to Expressions:

```
$query->where($c->expr('{} = []', ['b', 'a']));
```

Parameters which you specify into Expression will be preserved and linked into
the `$query` properly.

(query-modes)=

## Query Modes

When you create new Query it always start in "select" mode. You can switch
query to a different mode using {php:meth}`mode`. f you don't switch the mode,
your Query remains in select mode and you can fetch results from it anytime.

The pattern of defining arguments for your Query and then executing allow you
to re-use your query efficiently:

```
$data = ['name' => 'John', 'surname' => 'Smith']

$query = $c->dsql();
$query
    ->where('id', 123)
    ->field('id')
    ->table('user')
    ->set($data);

$row = $query->getRow();

if ($row) {
    $query
        ->set('revision', $query->expr('revision + 1'))
        ->mode('update')->executeStatement();
} else {
    $query
        ->set('revision', 1)
        ->mode('insert')->executeStatement();
}
```

The example above will perform a select query first:

- `select id from user where id = 123`

If a single row can be retrieved, then the update will be performed:

- `update user set name = "John", surname = "Smith", revision = revision + 1 where id = 123`

Otherwise an insert operation will be performed:

- `insert into user (name, surname, revision) values ("John", "Smith", 1)`

## Chaining

Majority of methods return `$this` when called, which makes it pretty
convenient for you to chain calls by using `->fx()` multiple times as
illustrated in last example.

You can also combine creation of the object with method chaining:

```
$age = $c->dsql()->table('user')->where('id', 123)->field('age')->getOne();
```

## Using query as expression

You can use query as expression where applicable. The query will get a special
treatment where it will be surrounded in brackets. Here are few examples:

```
$q = $c->dsql()
    ->table('employee');

$q2 = $c->dsql()
    ->field('name')
    ->table($q);

$q->getRows();
```

This query will perform `select name from (select * from employee)`:

```
$q1 = $c->dsql()
    ->table('sales')
    ->field('date')
    ->field('amount', null, 'debit');

$q2 = $c->dsql()
    ->table('purchases')
    ->field('date')
    ->field('amount', null, 'credit');

$u = $c->dsql('[] union []', [$q1, $q2]);

$q = $c->dsql()
    ->field('date, debit, credit')
    ->table($u, 'derivedTable');

$q->getRows();
```

This query will perform union between 2 table selects resulting in the following
query:

```sql
select `date`, `debit`, `credit` from (
    (select `date`, `amount` `debit` from `sales`) union
    (select `date`, `amount` `credit` from `purchases`)
) `derivedTable`
```

## Modifying Select Query

### Setting Table

:::{php:method} table($table, $alias)
Specify a table to be used in a query.

```{eval-rst}
:param mixed $table: table such as "employees"
:param mixed $alias: alias of table
:returns: $this
```
:::

This method can be invoked using different combinations of arguments.
Follow the principle of specifying the table first, and then optionally provide
an alias. You can specify multiple tables at the same time by using comma or
array (although you won't be able to use the alias there).
Using keys in your array will also specify the aliases.

Basic Examples:

```
$c->dsql()->table('user');
    // SELECT * from `user`

$c->dsql()->table('user', 'u');
    // aliases table with "u"
    // SELECT * from `user` `u`

$c->dsql()->table('user')->table('salary');
    // specify multiple tables. Don't forget to link them by using "where"
    // SELECT * from `user`, `salary`

$c->dsql()->table(['user', 'salary']);
    // identical to previous example
    // SELECT * from `user`, `salary`

$c->dsql()->table(['u' => 'user', 's' => 'salary']);
    // specify aliases for multiple tables
    // SELECT * from `user` `u`, `salary` `s`
```

Inside your query table names and aliases will always be surrounded by backticks.
If you want to use a more complex expression, use {php:class}`Expression` as
table:

```
$c->dsql()->table(
    $c->expr('(SELECT id FROM user UNION select id from document)'),
    'tbl'
);
// SELECT * FROM (SELECT id FROM user UNION SELECT id FROM document) `tbl`
```

Finally, you can also specify a different query instead of table, by simply
passing another {php:class}`Query` object:

```
$subQuery = $c->dsql();
$subQuery->table('employee');
$subQuery->where('name', 'John');

$q = $c->dsql();
$q->field('surname');
$q->table($subQuery, 'sub');

// SELECT `surname` FROM (SELECT * FROM `employee` WHERE `name` = :a) `sub`
```

Method can be executed several times on the same Query object.

### Setting Fields

:::{php:method} field($fields, $alias = null)
Adds additional field that you would like to query. If never called, will
default to {php:attr}`Query::$defaultField`, which normally is `*`.

This method has several call options. $field can be array of fields and
also can be an {php:class}`Expression` or {php:class}`Query`

```{eval-rst}
:param string|array|object $fields: Specify list of fields to fetch
:param string $alias: Optionally specify alias of field in resulting query
:returns: $this
```
:::

Basic Examples:

```
$query = new Query();
$query->table('user');

$query->field('first_name');
    // SELECT `first_name` from `user`

$query->field('first_name, last_name');
    // SELECT `first_name`, `last_name` from `user`

$query->field('employee.first_name')
    // SELECT `employee`.`first_name` from `user`

$query->field('first_name', 'name')
    // SELECT `first_name` `name` from `user`

$query->field(['name' => 'first_name'])
    // SELECT `first_name` `name` from `user`

$query->field(['name' => 'employee.first_name']);
    // SELECT `employee`.`first_name` `name` from `user`
```

If the first parameter of field() method contains non-alphanumeric values
such as spaces or brackets, then field() will assume that you're passing an
expression:

```
$query->field('now()');

$query->field('now()', 'time_now');
```

You may also pass array as first argument. In such case array keys will be
used as aliases (if they are specified):

```
$query->field(['time_now' => 'now()', 'time_created']);
    // SELECT now() `time_now`, `time_created` ...

$query->field($query->dsql()->table('user')->field('max(age)'), 'max_age');
    // SELECT (SELECT max(age) from user) `max_age` ...
```

Method can be executed several times on the same Query object.

### Setting where and having clauses

:::{php:method} where($field, $operation, $value)
Adds WHERE condition to your query.

```{eval-rst}
:param mixed $field: field such as "name"
:param mixed $operation: comparison operation such as ">" (optional)
:param mixed $value: value or expression
:returns: $this
```
:::

:::{php:method} having($field, $operation, $value)
Adds HAVING condition to your query.

```{eval-rst}
:param mixed $field: field such as "name"
:param mixed $operation: comparison operation such as ">" (optional)
:param mixed $value: value or expression
:returns: $this
```
:::

Both methods use identical call interface. They support one, two or three
argument calls.

Pass string (field name), {php:class}`Expression` or even {php:class}`Query` as
first argument.

Operator can be specified through a second parameter - $operation. If unspecified,
will default to '='.

Last argument is value. You can specify number, string, array, expression or
even null (specifying null is not the same as omitting this argument).
This argument will always be parameterized unless you pass expression.
If you specify array, all elements will be parametrized individually.

Starting with the basic examples:

```
$q->where('id', 1);
$q->where('id', '=', 1); // same as above

$q->where('id', '>', 1);

$q->where('id', '=', null); // will render to "IS NULL" SQL
$q->where('id', null) // same as above

$q->where('now()', 1); // will not use backticks
$q->where($c->expr('now()'), 1); // same as above

$q->where('id', [1, 2]); // renders as id in (1, 2)
```

You may call where() multiple times, and conditions are always additive (uses AND).
The easiest way to supply OR condition is to specify multiple conditions
through array:

```
$q->where([['name', 'like', '%john%'], ['surname', 'like', '%john%']]);
    // .. WHERE `name` like '%john%' OR `surname` like '%john%'
```

You can also mix and match with expressions and strings:

```
$q->where([['name', 'like', '%john%'], 'surname is null']);
    // .. WHERE `name` like '%john%' AND `surname` is null

$q->where([['name', 'like', '%john%'], $q->expr('surname is null')]);
    // .. WHERE `name` like '%john%' AND surname is null
```

There is a more flexible way to use OR arguments:

:::{php:method} orExpr()
Returns new Query object with method "where()". When rendered all clauses
are joined with "OR".
:::

:::{php:method} andExpr()
Returns new Query object with method "where()". When rendered all clauses
are joined with "OR".
:::

Here is a sophisticated example:

```
$q = $c->dsql();

$q->table('employee')->field('name');
$q->where('deleted', 0);
$q->where(
    $q
        ->orExpr()
        ->where('a', 1)
        ->where('b', 1)
        ->where(
            $q->andExpr()
                ->where('a', 2)
                ->where('b', 2)
        )
);
```

The above code will result in the following query:

```sql
select
    `name`
from
    `employee`
where
    deleted = 0 and
    (`a` = :a or `b` = :b or (`a` = :c and `b` = :d))
```

Technically orExpr() generates a yet another object that is composed
and renders its calls to where() method:

```
$q->having(
    $q
        ->orExpr()
        ->where('a', 1)
        ->where('b', 1)
);
```

```sql
having
    (`a` = :a or `b` = :b)
```

### Grouping results by field

:::{php:method} group($field)
Group by functionality. Simply pass either field name as string or
{class}`Expression` object.

```{eval-rst}
:param mixed $field: field such as "name"
:returns: $this
```
:::

The "group by" clause in SQL query accepts one or several fields. It can also
accept expressions. You can call `group()` with one or several comma-separated
fields as a parameter or you can specify them in array. Additionally you can
mix that with {php:class}`Expression` or {php:class}`Expressionable` objects.

Few examples:

```
$q->group('gender');

$q->group('gender, age');

$q->group(['gender', 'age']);

$q->group('gender')->group('age');

$q->group($q->expr('year(date)'));
```

Method can be executed several times on the same Query object.

### Concatenate group of values

:::{php:method} groupConcat($field, $separator = ',')
Quite often when you use `group by` in your queries you also would like to
concatenate group of values.

```{eval-rst}
:param mixed $field: Field name or object
:param string $separator: Optional separator to use. It's comma by default
```
:::

Different SQL engines have different syntax for doing this.
In MySQL it's group_concat(), in Oracle it's listagg, but in PgSQL it's string_agg.
That's why we have this method which will take care of this.

```
$q->groupConcat('phone', ';');

// group_concat('phone', ';')
```

If you need to add more parameters for this method, then you can extend this class
and overwrite this simple method to support expressions like this, for example:

```
group_concat('phone' order by 'date' desc separator ';')
```

### Joining with other tables

:::{php:method} join($foreignTable, $masterField, $joinKind)
Join results with additional table using "JOIN" statement in your query.

```{eval-rst}
:param string|array $foreignTable: table to join (may include field and alias)
:param mixed $masterField: main field (and table) to join on or Expression
:param string $joinKind: 'left' (default), 'inner', 'right' etc - which join type to use
:returns: $this
```
:::

When joining with a different table, the results will be stacked by the SQL
server so that fields from both tables are available. The first argument can
specify the table to join, but may contain more information:

```
$q->join('address'); // address.id = address_id
    // JOIN `address` ON `address`.`id`=`address_id`

$q->join('address a'); // specifies alias for the table
    // JOIN `address` `a` ON `address`.`id`=`address_id`

$q->join('address.user_id'); // address.user_id = id
    // JOIN `address` ON `address`.`user_id`=`id`
```

You can also pass array as a first argument, to join multiple tables:

```
$q->table('user u');
$q->join(['a' => 'address', 'c' => 'credit_card', 'preferences']);
```

The above code will join 3 tables using the following query syntax:

```sql
join
    address as a on a.id = u.address_id
    credit_card as c on c.id = u.credit_card_id
    preferences on preferences.id = u.preferences_id
```

However normally you would have `user_id` field defined in your supplementary
tables so you need a different syntax:

```
$q->table('user u');
$q->join([
    'a' => 'address.user_id',
    'c' => 'credit_card.user_id',
    'preferences.user_id',
]);
```

The second argument to join specifies which existing table/field is
used in `on` condition:

```
$q->table('user u');
$q->join('user boss', 'u.boss_user_id');
    // JOIN `user` `boss` ON `boss`.`id`=`u`.`boss_user_id`
```

By default the "on" field is defined as `$table . "_id"`, as you have seen in the
previous examples where join was done on "address_id", and "credit_card_id".
If you have specified field explicitly in the foreign field, then the "on" field
is set to "id", like in the example above.

You can specify both fields like this:

```
$q->table('employees');
$q->join('salaries.emp_no', 'emp_no');
```

If you only specify field like this, then it will be automatically prefixed with
the name or alias of your main table. If you have specified multiple tables,
this won't work and you'll have to define name of the table explicitly:

```
$q->table('user u');
$q->join('user boss', 'u.boss_user_id');
$q->join('user super_boss', 'boss.boss_user_id');
```

The third argument specifies type of join and defaults to "left" join. You can
specify "inner", "straight" or any other join type that your database support.

Method can be executed several times on the same Query object.

#### Joining on expression

For a more complex join conditions, you can pass second argument as expression:

```
$q->table('user', 'u');
$q->join('address a', $q->expr('a.name like u.pattern'));
```

### Use WITH cursors

:::{php:method} with(Query $cursor, string $alias, ?array $fields = null, bool $recursive = false)
If you want to add `WITH` cursor statement in your SQL, then use this method.
First parameter defines sub-query to use. Second parameter defines alias of this cursor.
By using third, optional argument you can set aliases for columns in cursor.
And finally forth, optional argument set if cursors will be recursive or not.

You can add more than one cursor in your query.

Did you know: you can use these cursors when joining your query to other tables. Just join cursor instead.

Keep in mind that if any of cursors added in your query will be recursive, then all cursors will
be set recursive. That's how SQL want it to be.

Example:

```
$quotes = $q->table('quotes')
    ->field('emp_id')
    ->field($q->expr('sum([])', ['total_net']))
    ->group('emp_id');
$invoices = $q()->table('invoices')
    ->field('emp_id')
    ->field($q->expr('sum([])', ['total_net']))
    ->group('emp_id');
$employees = $q
    ->with($quotes, 'q', ['emp', 'quoted'])
    ->with($invoices, 'i', ['emp', 'invoiced'])
    ->table('employees')
    ->join('q.emp')
    ->join('i.emp')
    ->field(['name', 'salary', 'q.quoted', 'i.invoiced']);
```

This generates SQL below:
:::

```sql
with
    `q` (`emp`, `quoted`) as (select `emp_id`, sum(`total_net`) from `quotes` group by `emp_id`),
    `i` (`emp`, `invoiced`) as (select `emp_id`, sum(`total_net`) from `invoices` group by `emp_id`)
select `name`, `salary`, `q`.`quoted`, `i`.`invoiced`
from `employees`
    left join `q` on `q`.`emp` = `employees`.`id`
    left join `i` on `i`.`emp` = `employees`.`id`
```

### Limiting result-set

:::{php:method} limit($cnt, $shift)
Limit how many rows will be returned.

```{eval-rst}
:param int $cnt: number of rows to return
:param int $shift: offset, how many rows to skip
:returns: $this
```
:::

Use this to limit your {php:class}`Query` result-set:

```
$q->limit(5, 10);
    // .. LIMIT 10, 5

$q->limit(5);
    // .. LIMIT 0, 5
```

### Ordering result-set

:::{php:method} order($order, $desc)
Orders query result-set in ascending or descending order by single or
multiple fields.

```{eval-rst}
:param string $order: one or more field names, expression etc.
:param int $desc: pass true to sort descending
:returns: $this
```
:::

Use this to order your {php:class}`Query` result-set:

```
$q->order('name'); // .. order by name
$q->order('name desc'); // .. order by name desc
$q->order(['name desc', 'id asc']) // .. order by name desc, id asc
$q->order('name', true); // .. order by name desc
```

Method can be executed several times on the same Query object.

## Insert and Replace query

### Set value to a field

:::{php:method} set($field, $value)
Assigns value to the field during insert.

```{eval-rst}
:param string $field: name of the field
:param mixed $value: value or expression
:returns: $this
```
:::

Example:

```
$q->table('user')->set('name', 'john')->mode('insert')->executeStatement();
    // insert into user (name) values (john)

$q->table('log')->set('date', $q->expr('now()'))->mode('insert')->executeStatement();
    // insert into log (date) values (now())
```

Method can be executed several times on the same Query object.

### Set Insert Options

:::{php:method} option($option, $mode = 'select')
:::

It is possible to add arbitrary options for the query. For example this will fetch unique user birthdays:

```
$q->table('user');
$q->option('distinct');
$q->field('birthday');
$birthdays = $q->getRows();
```

Other possibility is to set options for delete or insert:

```
$q->option('delayed', 'insert');

// or

$q->option('ignore', 'insert');
```

See your SQL capabilities for additional options (low_priority, delayed, high_priority, ignore)

## Update Query

### Set Conditions

Same syntax as for Select Query.

### Set value to a field

Same syntax as for Insert Query.

### Other settings

Limit and Order are normally not included to avoid side-effects, but you can
modify {php:attr}`Query::$templateUpdate` to include those tags.

## Delete Query

### Set Conditions

Same syntax as for Select Query.

### Other settings

Limit and Order are normally not included to avoid side-effects, but you can
modify {php:attr}`Query::$templateUpdate` to include those tags.

## Dropping attributes

If you have called where() several times, there is a way to remove all the
where clauses from the query and start from beginning:

:::{php:method} reset($tag)
```{eval-rst}
:param string $tag: part of the query to delete/reset.
```
:::

Example:

```
$q
    ->table('user')
    ->where('name', 'John');
    ->reset('where')
    ->where('name', 'Peter');

// where name = 'Peter'
```

## Other Methods

:::{php:method} dsql($defaults)
Use this instead of `new Query()` if you want to automatically bind query
to the same connection as the parent.
:::

:::{php:method} expr($template, $arguments)
Method very similar to {php:meth}`Connection::expr` but will return a
corresponding Expression class for this query.
:::

:::{php:method} exprNow($precision)
Method will return current_timestamp(precision) sub-query.
:::

:::{php:method} option($option, $mode)
Use this to set additional options for particular query mode.
For example:

```
$q
    ->table('test')
    ->field('name')
    ->set('name', 'John')
    ->option('calc_found_rows') // for default select mode
    ->option('ignore', 'insert') // for insert mode;

$q->executeQuery(); // select calc_found_rows `name` from `test`
$q->mode('insert')->executeStatement(); // insert ignore into `test` (`name`) values (`name` = 'John')
```
:::

:::{php:method} _setArgs($what, $alias, $value)
Internal method which sets value in {php:attr}`Expression::$args` array.
It doesn't allow duplicate aliases and throws Exception in such case.
Argument $what can be 'table' or 'field'.
:::

:::{php:method} caseExpr($operand)
Returns new Query object with CASE template.
You can pass operand as parameter to create SQL like

```
CASE <operand> WHEN <expression> THEN <expression> END type of SQL statement.
```
:::

:::{php:method} caseWhen($when, $then)
Set WHEN condition and THEN expression for CASE statement.
:::

:::{php:method} otherwise($else)
Set ELSE expression for CASE statement.

Few examples:

```
$s = $this->q()->caseExpr()
    ->caseWhen(['status', 'New'], 't2.expose_new')
    ->caseWhen(['status', 'like', '%Used%'], 't2.expose_used')
    ->caseElse(null);
```

```sql
case when "status" = 'New' then "t2"."expose_new" when "status" like '%Used%' then "t2"."expose_used" else null end
```

```
$s = $this->q()->caseExpr('status')
    ->caseWhen('New', 't2.expose_new')
    ->caseWhen('Used', 't2.expose_used')
    ->caseElse(null);
```

```sql
case "status" when 'New' then "t2"."expose_new" when 'Used' then "t2"."expose_used" else null end
```
:::

## Properties

:::{php:attr} mode
Query will use one of the predefined "templates". The mode will contain
name of template used. Basically it's array key of $templates property.
See {ref}`Query Modes`.
:::

:::{php:attr} defaultField
If no fields are defined, this field is used.
:::

:::{php:attr} templateSelect
Template for SELECT query. See {ref}`Query Modes`.
:::

:::{php:attr} templateInsert
Template for INSERT query. See {ref}`Query Modes`.
:::

:::{php:attr} templateReplace
Template for REPLACE query. See {ref}`Query Modes`.
:::

:::{php:attr} templateUpdate
Template for UPDATE query. See {ref}`Query Modes`.
:::

:::{php:attr} templateDelete
Template for DELETE query. See {ref}`Query Modes`.
:::

:::{php:attr} templateTruncate
Template for TRUNCATE query. See {ref}`Query Modes`.
:::

:::{php:namespace} Atk4\Data\Persistence\Sql
:::

(expr)=

:::{php:class} Expression
:::

# Expressions

Expression class implements a flexible way for you to define any custom
expression then execute it as-is or as a part of another query or expression.
Expression is supported anywhere in DSQL to allow you to express SQL syntax
properly.

Quick Example:

```
$query->where('time', $query->expr(
    'between "[]" and "[]"',
    [$fromTime, $toTime]
));

// Produces: .. where `time` between :a and :b
```

Another use of expression is to supply field instead of value and vice versa:

```
$query->where($query->expr(
    '[] between time_from and time_to',
    [$time]
));

// Produces: where :a between time_from and time_to
```

Yet another curious use for the DSQL library is if you have certain object in
your ORM implementing {php:class}`Expressionable` interface. Then you can also
use it within expressions:

```
$query->where($query->expr(
    '[] between [] and []',
    [$time, $model->getElement('time_form'), $model->getElement('time_to')]
));

// Produces: where :a between `time_from` and `time_to`
```

:::{todo}
add more info or more precise example of Expressionable interface usage.
:::

Another uses for expressions could be:

- Sub-Queries
- SQL functions, e.g. IF, CASE
- nested AND / OR clauses
- vendor-specific queries - "describe table"
- non-traditional constructions, UNIONS or SELECT INTO

## Properties, Arguments, Parameters

Be careful when using those similar terms as they refer to different things:

- Properties refer to object properties, e.g. `$expr->template`,
  see {ref}`properties`
- Arguments refer to template arguments, e.g. `select * from [table]`,
  see {ref}`expression-template`
- Parameters refer to the way of passing user values within a query
  `where id=:a` and are further explained below.

### Parameters

Because some values are un-safe to use in the query and can contain dangerous
values they are kept outside of the SQL query string and are using
[PDO's bindValue](https://www.php.net/manual/en/pdostatement.bindvalue.php)
instead. DSQL can consist of multiple objects and each object may have
some parameters. During [rendering](#rendering) those parameters are joined together to
produce one complete query.

## Creating Expression

```
$expr = $connection->expr('NOW()');
```

You can also use {php:meth}`expr()` method to create expression, in which case
you do not have to define "use" block:

```
$query->where('time', '>', $query->expr('NOW()'));

// Produces: .. where `time` > NOW()
```

You can specify some of the expression properties through first argument of the
constructor:

```
$expr = $connection->expr(['template' => 'NOW()']);
```

{ref}`Scroll down <properties>` for full list of properties.

(expression-template)=

## Expression Template

When you create a template the first argument is the template. It will be stored
in {php:attr}`$template` property. Template string can contain arguments in a
square brackets:

- `coalesce([], [])` is same as `coalesce([0], [1])`
- `coalesce([one], [two])`

Arguments can be specified immediately through an array as a second argument
into constructor or you can specify arguments later:

```
$expr = $connection->expr(
    'coalesce([name], [surname])',
    ['name' => $name, 'surname' => $surname]
);

// is the same as

$expr = $connection->expr('coalesce([name], [surname])');
$expr['name'] = $name;
$expr['surname'] = $surname;
```

## Nested expressions

Expressions can be nested several times:

```
$age = $connection->expr('coalesce([age], [default_age])');
$age['age'] = $connection->expr("year(now()) - year(birth_date)");
$age['default_age'] = 18;

$query->table('user')->field($age, 'calculated_age');

// select coalesce(year(now()) - year(birth_date), :a) `calculated_age` from `user`
```

When you include one query into another query, it will automatically take care
of all user-defined parameters (such as value `18` above) which will make sure
that SQL injections could not be introduced at any stage.

## Rendering

An expression can be rendered into a valid SQL code by calling render() method.
The method will return an array with string and params.

:::{php:method} render()
Converts {php:class}`Expression` object to an array with string and params.
Parameters are replaced with :a, :b, etc.
:::

## Executing Expressions

If your expression is a valid SQL query, (such as `show databases`) you
might want to execute it. Expression class offers you various ways to execute
your expression. Before you do, however, you need to have {php:attr}`$connection`
property set. (See `Connecting to Database` on more details). In short the
following code will connect your expression with the database:

```
$expr = $connection->expr();
```

If you are looking to use connection {php:class}`Query` class, you may want to
consider using a proper vendor-specific subclass:

```
$query = new \Atk4\Data\Persistence\Sql\Mysql\Query('connection' => $connection);
```

Finally, you can pass connection class into {php:meth}`executeQuery` directly.

:::{php:method} executeQuery($connection = null)
Executes expression using current database connection or the one you
specify as the argument:

```
$stmt = $expr->executeQuery($connection);
```

returns `Doctrine\DBAL\Result`.
:::

:::{todo}
Complete this when ResultSet and Connection are implemented
:::

:::{php:method} expr($template, $arguments)
Creates a new {php:class}`Expression` object that will inherit current
{php:attr}`$connection` property. Also if you are creating a
vendor-specific expression/query support, this method must return
instance of your own version of Expression class.

The main principle here is that the new object must be capable of working
with database connection.
:::

:::{php:method} getRows()
Executes expression and return whole result-set in form of array of hashes:

```
$data = $connection->expr('show databases')->getRows();
echo json_encode($data);
```

The output would be

```json
[
    { "Database": "mydb1" },
    { "Database": "mysql" },
    { "Database": "test" },
]
```
:::

:::{php:method} getRow()
Executes expression and returns first row of data from result-set as a hash:

```
$data = $connection->expr('SELECT @@global.time_zone, @@session.time_zone')->getRow()

echo json_encode($data);
```

The output would be

```json
{ "@@global.time_zone": "SYSTEM", "@@session.time_zone": "SYSTEM" }
```
:::

:::{php:method} getOne()
Executes expression and return first value of first row of data from
result-set:

```
$time = $connection->expr('NOW()')->getOne();
```
:::

## Magic an Debug Methods

:::{php:method} __debugInfo()
This method is used to prepare a sensible information about your query
when you are executing `var_dump($expr)`. The output will be HTML-safe.
:::

:::{php:method} getDebugQuery()
Outputs query as a string by placing parameters into their respective
places. The parameters will be escaped, but you should still avoid using
generated query as it can potentially make you vulnerable to SQL injection.

This method will use HTML formatting if argument is passed.
:::

In order for HTML parsing to work and to make your debug queries better
formatted, install `sql-formatter`:

```
composer require jdorn/sql-formatter
```

## Escaping Methods

The following methods are useful if you're building your own code for rendering
parts of the query. You must not call them in normal circumstances.

:::{php:method} consume($expression, string $escapeMode = self::ESCAPE_PARAM)
Makes `$sqlCode` part of `$this` expression. Argument may be either a string
(which will be escaped) or another {php:class}`Expression` or {php:class}`Query`.
If specified {php:class}`Query` is in "select" mode, then it's automatically
placed inside brackets:

```
$query->consume('first_name'); // `first_name`
$query->consume($otherQuery); // will merge parameters and return string
```
:::

:::{php:method} escapeIdentifier($sqlCode)
Always surrounds `$sql code` with back-ticks.

This escaping method is automatically used for `{...}` expression template tags .
:::

:::{php:method} escapeIdentifierSoft($sqlCode)
Surrounds `$sql code` with back-ticks.

This escaping method is automatically used for `{{...}}` expression template tags .

It will smartly escape table.field type of strings resulting in `table`.`field`.

Will do nothing if it finds "*", "`" or "(" character in `$sqlCode`:

```
$query->escapeIdentifierSoft('first_name'); // `first_name`
$query->escapeIdentifierSoft('first.name'); // `first`.`name`
$query->escapeIdentifierSoft('(2 + 2)'); // (2 + 2)
$query->escapeIdentifierSoft('*'); // *
```
:::

:::{php:method} escapeParam($value)
Converts value into parameter and returns reference. Used only during query
rendering. Consider using {php:meth}`consume()` instead, which will also
handle nested expressions properly.

This escaping method is automatically used for `[...]` expression template tags .
:::

(properties)=

## Other Properties

:::{php:attr} template
Template which is used when rendering.
You can set this with either `$connection->expr('show tables')`
or `$connection->expr(['show tables'])`
or `$connection->expr(['template' => 'show tables'])`.
:::

:::{php:attr} connection
DB connection object.
:::

:::{php:attr} paramBase
Normally parameters are named :a, :b, :c. You can specify a different
param base such as :param_00 and it will be automatically increased
into :param_01 etc.
:::

:::{php:attr} debug
If true, then next call of {php:meth}`execute` will `echo` results
of {php:meth}`getDebugQuery`.
:::

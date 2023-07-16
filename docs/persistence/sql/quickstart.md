:::{php:namespace} Atk4\Data\Persistence\Sql
:::

(quickstart)=

# Quickstart

When working with DSQL you need to understand the following basic concepts:

## Basic Concepts

- Expression (see {ref}`expr`)

  {php:class}`Expression` object, represents a part of a SQL query. It can
  be used to express advanced logic in some part of a query, which
  {php:class}`Query` itself might not support or can express a full statement
  Never try to look for "raw" queries, instead build expressions and think
  about escaping.

- Query (see {ref}`query`)

  Object of a {php:class}`Query` class can be used for building and executing
  valid SQL statements such as SELECT, INSERT, UPDATE, etc. After creating
  {php:class}`Query` object you can call various methods to add "table",
  "where", "from" parts of your query.

- Connection

  Represents a connection to the database. If you already have a PDO object
  you can feed it into {php:class}`Expression` or {php:class}`Query`, but
  for your comfort there is a {php:class}`Connection` class with very little
  overhead.

## Getting Started

We will start by looking at the {php:class}`Query` building, because you do
not need a database to create a query:

```
$query = $connection->dsql();
```

Once you have a query object, you can add parameters by calling some of it's
methods:

```
$query
    ->table('employees')
    ->where('birth_date', '1961-05-02')
    ->field('count(*)');
```

Finally you can get the data:

```
$count = $query->getOne();
```

While DSQL is simple to use for basic queries, it also gives a huge power and
consistency when you are building complex queries. Unlike other query builders
that sometimes rely on "hacks" (such as method whereOr()) and claim to be useful
for "most" database operations, with DSQL, you can use DSQL to build ALL of your
database queries.

This is hugely beneficial for frameworks and large applications, where
various classes need to interact and inject more clauses/fields/joins into your
SQL query.

DSQL does not resolve conflicts between similarly named tables, but it gives you
all the options to use aliases.

The next example might be a bit too complex for you, but still read through and
try to understand what each section does to your base query:

```
// Establish a query looking for a maximum salary
$salary = $connection->dsql();

// Create few expression objects
$eMaxSalary = $salary->expr('max(salary)');
$eMonths = $salary->expr('TimeStampDiff(month, from_date, to_date)');

// Configure our basic query
$salary
    ->table('salary')
    ->field(['emp_no', 'max_salary' => $eMaxSalary, 'months' => $eMonths])
    ->group('emp_no')
    ->order('-max_salary');

// Define sub-query for employee "id" with certain birth-date
$employees = $salary->dsql()
    ->table('employees')
    ->where('birth_date', '1961-05-02')
    ->field('emp_no');

// Use sub-select to condition salaries
$salary->where('emp_no', $employees);

// Join with another table for more data
$salary
    ->join('employees.emp_id', 'emp_id')
    ->field('employees.first_name');

// Finally, fetch result
foreach ($salary as $row) {
    echo 'Data: ' . json_encode($row) . "\n";
}
```

The above query resulting code will look like this:

```sql
SELECT
    `emp_no`,
    max(salary) `max_salary`,
    TimeStampDiff(month, from_date, to_date) `months`
FROM
    `salary`
JOIN
    `employees` on `employees`.`emp_id` = `salary`.`emp_id`
WHERE
    `salary`.`emp_no` in(select `id` from `employees` where `birth_date` = :a)
GROUP BY `emp_no`
ORDER BY max_salary desc

:a = "1961-05-02"
```

Using DSQL in higher level ORM libraries and frameworks allows them to focus on
defining the database logic, while DSQL can perform the heavy-lifting of query
building and execution.

## Creating Objects and PDO

DSQL classes does not need database connection for most of it's work. Once you
create new instance of {ref}`Expression <expr>` or {ref}`Query <query>` you can
perform operation and finally call {php:meth}`Expression::render()` to get the
final query string with params:

```
use Atk4\Data\Persistence\Sql\Query;

$q = (new Query())->table('user')->where('id', 1)->field('name');
[$query, $params] = $q->render();
```

When used in application you would typically generate queries with the
purpose of executing them, which makes it very useful to create a
{php:class}`Connection` object. The usage changes slightly:

```
$c = Atk4\Data\Persistence\Sql\Connection::connect($dsn, $user, $password);
$q = $c->dsql()->table('user')->where('id', 1)->field('name');

$name = $q->getOne();
```

You no longer need "use" statement and {php:class}`Connection` class will
automatically do some of the hard work to adopt query building for your
database vendor.
There are more ways to create connection, see [Advanced Connections](#advanced-connections) section.

The format of the `$dsn` is the same as with for
[DBAL connection](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html).
If you need to execute query that is not supported by DSQL, you should always
use expressions:

```
$tables = $c->expr('show tables like []', [$likeStr])->getRows();
```

DSQL classes are mindful about your SQL vendor and it's quirks, so when you're
building sub-queries with {php:meth}`Query::dsql`, you can avoid some nasty
problems:

```
$sqliteConnection->dsql()->table('user')->mode('truncate')->executeStatement();
```

The above code will work even though SQLite does not support truncate. That's
because DSQL takes care of this.

## Query Building

Each Query object represents a query to the database in-the-making.
Calling methods such as {php:meth}`Query::table` or {php:meth}`Query::where`
affect part of the query you're making. At any time you can either execute your
query or use it inside another query.

{php:class}`Query` supports majority of SQL syntax out of the box.
Some unusual statements can be easily added by customizing template for specific
query and we will look into examples in {ref}`extending_query`

## Query Mode

When you create a new {php:class}`Query` object, it is going to be a `SELECT`
query by default. If you wish to execute `update` operation instead, you
cam simply call {php:meth}`Query::mode` to change it. For more information
see {ref}`query-modes`.
You can actually perform multiple operations:

```
$q = $c->dsql()->table('employee')->where('emp_no', 1234);
$backupData = $q->getRows();
$q->mode('delete')->executeStatement();
```

A good practice is to re-use the same query object before you branch out and
perform the action:

```
$q = $c->dsql()->table('employee')->where('emp_no', 1234);

if ($confirmed) {
    $q->mode('delete')->executeStatement();
} else {
    echo 'Are you sure you want to delete ' . $q->field('count(*)') . ' employees?';
}
```

(fething-result)=

## Fetching Result

When you are selecting data from your database, DSQL will prepare and execute
statement for you. Depending on the connection, there may be some magic
involved, but once the query is executed, you can start streaming your data:

```
foreach ($query->table('employee')->where('dep_no', 123) as $employee) {
    echo $employee['first_name'] . "\n";
}
```

When iterating you'll have `Doctrine\DBAL\Result`. Remember that DQSL can support vendors,
`$employee` will always contain associative array representing one row of data.
(See also [Manual Query Execution](#manual-query-execution)).

:::{php:namespace} Atk4\Data\Persistence\Sql
:::

# Overview

DSQL is a dynamic SQL query builder. You can write multi-vendor queries in PHP
profiting from better security, clean syntax and most importantly – sub-query
support. With DSQL you stay in control of when queries are executed and what
data is transmitted. DSQL is easily composable – build one query and use it as
a part of other query.

## Goals of DSQL

- simple and concise syntax
- consistently scalable (e.g. 5 levels of sub-queries, 10 with joins and 15
  parameters? no problem)
- "One Query" paradigm
- support for PDO vendors as well as NoSQL databases (with query language
  similar to SQL)
- small code footprint (over 50% less than competing frameworks)
- free, licensed under MIT
- no dependencies
- follows design paradigms:
  - "[PHP the Agile way](https://github.com/atk4/dsql/wiki/PHP-the-Agile-way)"
  - "[Functional ORM](https://github.com/atk4/dsql/wiki/Functional-ORM)"
  - "[Open to extend](https://github.com/atk4/dsql/wiki/Open-to-Extend)"
  - "[Vendor Transparency](https://github.com/atk4/dsql/wiki/Vendor-Transparency)"

## DSQL by example

The simplest way to explain DSQL is by example:

```
$query = $connection->dsql();
$query->table('employees')
    ->where('birth_date', '1961-05-02')
    ->field('count(*)');
echo 'Employees born on May 2, 1961: ' . $query->getOne();
```

The above code will execute the following query:

```sql
select count(*) from `salary` where `birth_date` = :a
    :a = "1961-05-02"
```

DSQL can also execute queries with multiple sub-queries, joins, expressions
grouping, ordering, unions as well as queries on result-set.

- See {ref}`quickstart` if you would like to start learning DSQL.
- See https://github.com/atk4/dsql-primer for various working
  examples of using DSQL with a real data-set.

## DSQL is Part of Agile Toolkit

DSQL is a stand-alone and lightweight library with no dependencies and can be
used in any PHP project, big or small.

:::{figure} ../../images/agiletoolkit.png
:alt: Agile Toolkit Stack
:::

DSQL is also a part of [Agile Toolkit](https://atk4.org/) framework and works best with
[Agile Models](https://github.com/atk4/models). Your project may benefit from a higher-level data abstraction
layer, so be sure to look at the rest of the suite.

# Agile Data

**PHP Framework for better Business Logic design and scalable database access.**

Using Agile Data inside your PHP app allows you to abstract database (SQL or NoSQL) access
without loosing access to power-features (grouping, aggregating, sub-queries, custom expressions,
cross-vendor references, entity-level joins).

You will be able to separate your business logic from schema domain, so that the rest of
your application could use expressions, imported fields and aggregate columns transparently.

Agile Data also offers you to implement enterprise-focused features such as ACL, Audit, Undo,
Scopes as well as transparently change database vendor for your entity storage.

Code Quality:

[![Build Status](https://travis-ci.org/atk4/data.png?branch=develop)](https://travis-ci.org/atk4/data)
[![Code Climate](https://codeclimate.com/github/atk4/data/badges/gpa.svg)](https://codeclimate.com/github/atk4/data)
[![StyleCI](https://styleci.io/repos/56442737/shield)](https://styleci.io/repos/56442737)
[![Test Coverage](https://codeclimate.com/github/atk4/data/badges/coverage.svg)](https://codeclimate.com/github/atk4/data/coverage)

Resources and Community:

[![Documentation Status](https://readthedocs.org/projects/agile-data/badge/?version=develop)](http://agile-data.readthedocs.io/en/develop/?badge=latest)
[![Gitter](https://img.shields.io/gitter/room/atk4/data.svg?maxAge=2592000)](https://gitter.im/atk4/dataset?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)
[![Stack Overlfow Community](https://img.shields.io/stackexchange/stackoverflow/t/atk4.svg?maxAge=2592000)](http://stackoverflow.com/questions/ask?tags=atk4)
[![Discord User forum](https://img.shields.io/badge/discord-User_Forum-green.svg)](https://forum.agiletoolkit.org/c/44)

Stats:

[![License](https://poser.pugx.org/atk4/data/license)](https://packagist.org/packages/atk4/data)
[![Version](https://badge.fury.io/gh/atk4%2Fdata.svg)](https://packagist.org/packages/atk4/data)

## Core Fundamental Differences

### Introducing Actions

ORM/Active Record is not designed for relational databases. There are too much potential that ORM
abstracts. The way how modern ORM get around it by offering ability to generate "Query-in-progress"
object off your model which you can tweak.

Agile Data significantly expands amount of interactions you can do between Model and Query Builder.
You can generate update/delete queries, aggregate queries and drop them into Query Builder:

![GitHub release](docs/images/action.gif)]

### Introducing Expressions

In Agile Data your field can be defined through user-defined SQL expression. That does not affect
the rest of the API, the field can still be used inside actions, conditions and inside your CRUD
operations with a model. Most expressions are read-only though.

![GitHub release](docs/images/expression.gif)]

### Good bye Eager/Lazy Loading

Have you felt that something is broken with Eager/Lazy loading approach? With Agile Data you'll
never have to use it again. If you have a reference between models, you can "import" fields
through this relationship.

![GitHub release](docs/images/import-field.gif)]

### 

Most ORM today are built by extending Query Builder. While this gives a benefit to build "Queries"
out of your entity this in a one-way operation. Additionally things you CAN build into a query
are quite limited.




Agile Data is a comprehensive framework for use in SaaS and Enterprise PHP projects, that focuses on solving these major goals:

### 1. To support all the features present in modern ORMs and Query Builders



Face it. Your SQL architecture does NOT fit your business model map precisely. There are many differences mainly focused on structure optimisation, that can complicate loading/saving data into SQL:

 - single SQL table storing multiple business objects (clients and suppliers)
 - multiple SQL tables joined for a single business object (company, company_stats)
 - [data normalization](https://en.wikipedia.org/wiki/Database_normalization)
 - [disjoined subtyping](https://en.wikipedia.org/wiki/Subtyping)
 - optional weak joins
 - field type / name mapping (name => user_name)

Agile Data allow you to map your business models with any of the above SQL techniques while using inheritance with very short and simple-to-read code.

### 2. Define your own data patterns and extend

 - Soft-delete
 - Record audit log
 - Update date, delete date, creation date
 - Access Control Lists
 
Those patterns can significantly complicate declaration of your business logic. Agile Data allows you to deal with them in a very flexible and re-usable way.

### 3. DataSet traversal

Traversing relations is not a new concept. Many ORM systems implement traversal with record-to-record-array approach and lazy-loading. In practice, that creates some serious scalability problems.

Agile data introduces Set-to-Set traversal allowing you to traverse not just a single record but multiple records at the same time:

``` php
$clients = new my\Model_Client($db);
$clients->addCondition('is_vip', true);
$p = $clients->ref('Order')->ref('Payment');
```

In the code snippet above, `$p` will be a model object containing all payments of all orders placed by VIP clients in scope. Traversal executes no queries but rather relies on sub-query logic.

### 4. Database Vendor Abstraction and Multi-record Actions

NoSQL databases are rapidly adding options to peform multi-record operations and aggregation. Agile Data basic operations, such as record manipulation, already works with NoSQL transparently. In addition to that, Actions introduce a unified interface that can be used across all supporting persistence drivers. Consider this as continuation of the example above:

``` php
// get count of all payments (see previous example for scope)
$cnt = $p->action('count')->getOne();

// delete all notifications for these payments
$n = new my\Model_Notification($db);
$n->addCondition('payment_id', $p->action('field', ['id']));

$n->action('delete')->execute();
```

When Action is executed or embedded, framework makes decision on how to best execute the strategy by using server-side capabilities of the database. If database is not capable of sub-select or multi-row operations, then it is still possible for Agile Data to simulate the action inside PHP.

The same business Model definition can work with multiple database types, making it easy to store your data in caches, session, files or access it through API. The next example shows example of database-agnostic code that will work with either MySQL or MongoDB:

``` php
$m = new my\Model_User($mysql_db);
// $m = new my\Model_User($mongo_db);


$m->loadBy('name', $user);
if ($m->verifyPassword($pass)) {
    $m->action('increment', [$m->id, 'logins']);
}
```

*Note: Support for MongoDB as of 1.0 is quite limited.*

### 5. Reducing number of queries

Business Logic designed with Agile Data can natively perform complex data operations such as joins, sub-selects, expressions which skilled developer can use to reduce total number of SQL queries per application request.
My next example will create export of Clients along with their "account balance" that will be calculated by just a single query:

``` php
$c = new my\Model_Client($db);

$c->getRef('Order')->addField('purchases', ['aggregate'=>'sum', 'field'=>'total']);
$c->getRef('Payment')->addField('payments', ['aggregate'=>'sum', 'field'=>'paid']);
$c->addExpression('balance', '[purchases]-[payments]');

echo json_encode($c->export(['name','balance']));

// purchases = sum(order.total) for specific client
// payments = sum(payment.paid) for specific client
// balance = sum(order.total) - sum(payment.paid)
```

### 6. Manipulating Records

Certainly you can also operate with your models on record-by-record basis:

``` php
$p->onlyFields(['is_paid']);
$p->loadBy('reference', $ref_id);
$p['is_paid'] = true;
$p->save();
``` 

There are two significant advantages specifically designed to reduce data transfer footprint and improve security:

 - you will only be able to load records from DataSet
 - with onlyFields() you can specify which model fields you are going to load

### 7. Business Model Aggregation

Most database mappers are good for accessing and modifying data only, however, Agile Data allows you to build aggregates from your business model. Regardless of how many tables you have joined, you can use one model as a source for another model thus embedding (or unioning) query source.

*Note: This feature is planned for 1.1 release.*


### 8. Extensions and Customisation

Agile Data already is a great framework, but it can be further extended:

 - Add new database driver support, including NoSQL and custom RestAPI
 - Add new field types
 - Add new relation types, including cross-database relations
 - Validation engines
 - ACL engines

See section below to learn more about commercial services and support options.
 

### Full documentation for Agile Data

[agile-data.readthedocs.io](http://agile-data.readthedocs.io).

### Getting Started Guides

 * [Follow the Quick Start guides](http://agile-data.readthedocs.io/en/develop/quickstart.html)
 * [Watch short introduction video on Youtube](https://youtu.be/ZekgUxdPWwc)

## Installing into existing project

Update your `composer.json` with 'require' and 'autoload' sections:

``` json
{
  "type":"project",
  "require":{
    "atk4/data": "^1.0.0",
    "psy/psysh": "*"
  },
  "autoload":{
    "psr-4": {
      "my\\": "src/my/"
    }
  }
}
```

Run `composer update` and create your first business model inside `src/my/Model_User.php`:

``` php
namespace my;
class Model_User extends \atk4\data\Model
{
    public $table = 'user';
    function init()
    {
        parent::init();
        
        $this->addFields(['email','name','password']);
    }
}
```

Use an existing table name and fields. Next create `console.php` file to start exploring Agile Data:

``` php
<?php
include'vendor/autoload.php';
$db = \atk4\data\Persistence::connect(PDO_DSN, USER, PASS);
$m = new my\Model_User($db);
eval(\Psy\sh());
```

Finally, run `console.php`:

```
$ php console.php
```

Now you can explore. Try typing:

``` php
> $m
> $m->loadBy('email', 'example@example.com')
> $m->get()
> $m->export(['email','name'])
> $m->action('count')->getOne()
> $m->action('count')->getDebugQuery()
```
Full documentation is available at [agile-core.readthedocs.io](http://agile-core.readthedocs.io/)

## Agile Toolkit

Agile Data is part of [Agile Toolkit - PHP UI Framework](http://agiletoolkit.org). If you like
this project, you should also look into:

 - [DSQL](https://github.com/atk4/dsql) - [![GitHub release](https://img.shields.io/github/release/atk4/dsql.svg?maxAge=2592000)]()
 - [Agile Core](https://github.com/atk4/core) - [![GitHub release](https://img.shields.io/github/release/atk4/core.svg?maxAge=2592000)]()

 
## Help us make Agile Data better!!

We wish to take on your feedback and improve Agile Data further. Here is how you can connect with developer team:

 - chat with us on [Gitter](https://gitter.im/atk4/data) and ask your questions directly.
 - ask or post suggestions on our forum [https://forum.agiletoolkit.org](https://forum.agiletoolkit.org)
 - **share Agile Data with your friends**, we need more people to use it. Blog. Tweet. Share.
 - work on some of the tickets marked with [help wanted](https://github.com/atk4/data/labels/help%20wanted) tag.

See [www.agiletoolkit.org](http://www.agiletoolkit.org/) for more frameworks and libraries that can make your PHP Web Application even more efficient.

## Roadmap

Follow pull-request history and activity of repository to see what's going on.

```
1.1   Add support for derived models (unions).
1.x   Add support for 3rd party vendor implementations.
1.x   Add support for MongoDB.
1.x   Add support and docs for Validators.
```

## Past Updates
* 20 Jul: Release of 1.0 with a new QuickStart guide
* 15 Jul: Rewrote README preparing for our first BETA release
* 05 Jul: Released 0.5 Expressions, Conditions, Relations
* 28 Jun: Released 0.4 join support for SQL and Array
* 24 Jun: Released 0.3 with general improvements
* 17 Jun: Finally shipping 0.2: With good starting support of SQL and Array 
* 29 May: Finished implementation of core logic for Business Model
* 11 May: Released 0.1: Implemented code climate, test coverage and travis
* 06 May: Revamped the concept, updated video and made it simpler
* 22 Apr: Finalized concept, created presentation slides.
* 17 Apr: Started working on concept draft (in wiki)
* 14 Apr: [Posted my concept on Reddit](https://www.reddit.com/r/PHP/comments/4f2epw/reinventing_the_faulty_orm_concept_subqueries/)

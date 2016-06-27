# Agile Data - Database access abstraction framework.


**PHP Framework for better Business Logic design and scalable database access.**

[![Gitter](https://img.shields.io/gitter/room/atk4/data.svg?maxAge=2592000)](https://gitter.im/atk4/dataset?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)
[![Documentation Status](https://readthedocs.org/projects/agile-data/badge/?version=latest)](http://agile-data.readthedocs.io/en/latest/?badge=latest)
[![License](https://poser.pugx.org/atk4/data/license)](https://packagist.org/packages/atk4/data)
[![GitHub release](https://img.shields.io/github/release/atk4/data.svg?maxAge=2592000)]()
[![Build Status](https://travis-ci.org/atk4/data.png?branch=develop)](https://travis-ci.org/atk4/data)
[![Code Climate](https://codeclimate.com/github/atk4/data/badges/gpa.svg)](https://codeclimate.com/github/atk4/data)
[![Test Coverage](https://codeclimate.com/github/atk4/data/badges/coverage.svg)](https://codeclimate.com/github/atk4/data/coverage)


The main goals of Agile Data are to be:

 - Simple - to learn, to read/write code and to extend. Even for beginners.
 
 - Efficient - solves scalability challenges you face with traditional ORM.
 
 - Database Agnostic - your code will work with SQL, NoSQL vendors or JSON files with optimal efficiency.
 
 - Enterprise Quality - ideal solution for large teams and complex business projects with DM/PM separation.

 - Works Anywhere - uses composer and namespaces and can be used in any PHP project.
 
 - Agile Toolkit - part of a full-stack PHP Web UI Framework for building your web apps.

 - Aggregate Models - build reports and analytics using Domain Models logic (not custom queries).

 - Extensible - add new vendor drivers to take full advantage of their query languages and capabilities.

 - Full Featured - support for SQL joins, sub-selects, expressions, multi-row updates, hooks, caching, REST proxies, validation, data-type normalization, debugging, profiling, migrators and schema generators.
 

## Agile Data in Practice

To achieve its goals Agile Data introduces two new concepts into Database Abstraction of your applications:

 - DataSet - Represents a scope of records located in one or several tables/collections in your database that can be addressed by a Business Model.
 
 - Action - Certain operation that will affect all records in a DataSet when executed.

### Real-life usage example
 
**Requirement: Calculate total outstanding amount on all orders placed by VIP clients.** 

A task that would normally require you to write a stored SQL procedure, cache data or sacrifice performance can be solved elegantly in Agile Data using database-independent declarative logic:

(note: [click here to see declaration of model classes](https://github.com/atk4/data/wiki/README-Example-Support-Files))

```
  $clients = new Model_Client($db);
  // Object representing all clients - DataSet

  $clients -> addCondition('is_vip', true);
  // Now DataSet is limited to VIP clients only

  $vip_client_orders = $clients->refSet('Order');
  // This DataSet will contain only orders placed by VIP clients

  $vip_client_orders->addExpression('item_price')->set(function($model, $query){
      return $model->ref('item_id')->fieldQuery('price');
  });
  // Defines a new field for a model expressed through relation with Item

  $vip_client_orders->addExpression('paid')->set(function($model, $query){
      return $model->ref('Payment')->sum('amount');
  });
  // Defines another field as sum of related payments
  
  $vip_client_orders->addExpression('due')->set(function($model, $query){
      return $query->expr('{item_price} * {qty} - {paid}');
  });
  // Defines third field for calculating due

  $total_due_payment = $vip_client_orders->sum('due')->getOne();
  // Defines and executes "sum" action on our expression across specified data-set
```

Only the final `getOne()` will build and execute database query. Depending on which database vendor you use ($db), execution strategy will be different:

 - SQL: single standard SELECT (with some sub-queries) is executed. Single field/row is fetched.
 
 - Array $data: composes the function, then executes it with $data. (see [Phamda](https://github.com/mpajunen/phamda))
 
 - NoSQL: total of 3 or less queries (depending on database capabilities) will be executed and data from separate queries merged together in PHP giving you a total value.

With the current implementation, the SQL query looks like is:

```
select sum(
  (select `price` from `item` where `item`.`id` = `order`.`item_id` )
  * `order`.`qty`
  - (select sum(`payment`.`amount`) `amount` 
     from `payment` where `payment`.`order_id` = `order`.`id` )
) `due` from `order` 
where `order`.`user_id` in (
  select `id` from `user` where `user`.`is_client` = 1 and `user`.`is_vip` = 1 
)
```

## Busines Benefits of Agile Data

It makes a lot of sense to have business logic of your application refactored with Agile Data. You will gain the following advantages:

 - Improve your development team efficiency by 70% without sacrifice of your code performance.

 - Express all your business logic in "Domain Model" — your code does not rely on particular database vendor and you can switch from MySQL to MongoDB (or other vendor) with minimum effort.

 - Taking full advantage of your database — Agile Data executes actions server-side such as multi-row updates - when vendor supports it. Your application always uses most-efficient approach.
 
 - Full support for Unit Tests — Achieve 100% coverage of your business logic by mocking operations with test-$data expressed through array or JSON.
 
 - Cross-vendor model traversing — Different parts of your business logic may use different databases. Agile Data supports cross-vendor relations.
  
 - Significantly lower code complexity and total cost of ownership.
 
 - Full data consistency when you need to build aggregation reports (group by, union, join)

 - Reliable foundation - Agile Data is highly tested and super-stable.
 
 - UI extension - Build your Admin UI within hours with Agile Toolkit once you have Agile Data in place.
 
 - Commercial Support - Agile Data is developed and backed by London-based tech company.


## Getting Started with Agile Data

The following video will introduce you to the basic concepts used by Agile Data:

<a target="_blank" href="https://www.youtube.com/watch?v=XUXZI7123B8"><img src="docs/images/presentation.png" width="100%"></a>

For more in-depth information on the concept itself, see the links:

 - [Business Models](https://github.com/atk4/dataset/wiki/Business-Models) - Class for implementing your business logic [DM].
 - [Active Record](https://github.com/atk4/dataset/wiki/Active-Record) - Simplified record-based access to your Model data. [DM].
 - [Explicit Loading and Saving](https://github.com/atk4/dataset/wiki/Explicit-Loading-and-Saving) - No auto-load/auto-save. Learn how to access your database data [PM].
 - [Relation Mapping](https://github.com/atk4/dataset/wiki/Relation-Mapping) - Traverse between related business data [DM].
 - [Persistence](https://github.com/atk4/dataset/wiki/Persistence) - Design and tweak how your Business Models are mapped into tables [DM->PM].
 - [Derived Queries](https://github.com/atk4/dataset/wiki/Derived-Queries) - Express your Business Models as SQL queries [PM].
 - [Expressions](https://github.com/atk4/dataset/wiki/Expressions) - Use Derived queries to add fields in your Business Models that are calculated in SQL [PM].
 - [Query Building](https://github.com/atk4/dataset/wiki/Query-Building) - Build an execute complex multi-row queries mapped from your Business Models [PM].
 - [Unit-testing](https://github.com/atk4/dataset/wiki/Unit-Testing) - Business Models can be decoupled from persistence layer for efficient Unit Testing [DM].
 - [Aggregation and Reports](https://github.com/atk4/dataset/wiki/Aggregation-and-Reports) - Support report generation techniques ndaggregations for your Business models [DM].

### Documentation for Agile Data

Read our full documentation at [agile-data.readthedocs.io](http://agile-data.readthedocs.io).

### Downloading Agile Data

You can install Agile Data through composer:

```
composer require atk4/data
```


## Quality promise from Agile Toolkit

We also care about your experience while using our toolkit, so we will:

 - work with [developer community](https://gitter.im/atk4/dataset?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge) and discuss main decisions collaboratively. 
 - write short and easy-to-read, standard-compliant code with high code-climate score.
 - unit-test our own code. Achieve a minimum of 95% code coverage for stable releases.
 - add code [through pull requests](https://github.com/atk4/data/pulls?utf8=✓&q=is%3Apr+) and discuss them before merging.
 - never break APIs in minor releases.
 - support composer, include minimum dependencies.
 - be friendly with all higher-level frameworks.
 - do not duplicate the code (e.g. in vendor drivers)
 - use MIT License

See [www.agiletoolkit.org](http://www.agiletoolkit.org/) for more frameworks and libraries that can make your PHP Web Application even more efficient.

 
## Mini-FAQ

### Q: Will Agile Data be here in 5 years time?

Yes.

The founder and lead developer for this library is: [Romans Malinovskis](https://www.openhub.net/accounts/romaninsh) who has been a long-time open-source developer and maintainer of Agile Toolkit (PHP UI Framemwork). Agile Data is developed as part of Agile Toolkit refactoring and will be bundled with Agile Toolkit 4.4.

### Q: Why not improve existing [ORM] project/framework X?

That's exactly what we are doing.

Agile Data is a refactor of "Model" implementation from Agile Toolkit that [was initially released 2011](https://github.com/atk4/atk4/commit/976ccce73c5c7bf5afbedc70aa3f72158dbf534b#diff-8e1db8ebf3425c345973e98193903012). Other projects are often using different "concept" and would not work optimally if we add "DataSet" and "Action" implementation.

### Q: When can I use Agile Data in my project?

Late July or August 2016.

You will find our road-map as well as recent updates at the bottom of this page. There are already a stable build of the framemwork which you can try, but some features are missing.

### Q: How can I contribute to this Open-Source framework?

First, Go [to our chat room](https://gitter.im/atk4/dataset?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge) to introduce yourself and tell us what you are willing to do to help.

See also list of issues that are specifically added for contributors to work on (Contains [help wanted](https://github.com/atk4/data/labels/help%20wanted) tag) 

### Q: My question is not answered, where can I ask?

See our Wiki page for additional questions and answers. Click [EDIT] button and add your question at the bottom of the page if it is missing. We will update Wiki with the answer:

[Full FAQ on our WIKI Page](https://github.com/atk4/dataset/wiki/Frequently-Asked-Questions)


## Roadmap

Follow pull-request history and activity of repository to see what's going on.

```
0.3   Add support for strong and weak join when persisting.
0.4   Add support for Conditions to implement DataSet.
0.5   Further integrate with the Query Builder, add Expression support.
0.6   Add relation traversal support.
0.7   Add support for hooks (before/after save) in (DM and PM).
0.8   Add support for dealing with multiple persistences.
0.9  Add ability to specify meta-information into fields.
0.10  Add support for derived models (unions).
0.11  Add support for 3rd party vendor implementations.
0.12  Achieve our test coverage, code quality and documentation standards.
1.0   First Stable Release.
1.1   Add support for MongoDB.
1.2   Add support and docs for Validators.
```

## Past Updates

* 27 Jun: Finally shipping 0.2: With good starting support of SQL and Array 
* 29 May: Finished implementation of core logic for Business Model
* 11 May: Released 0.1: Implemented code climate, test coverage and travis
* 06 May: Revamped the concept, updated video and made it simpler
* 22 Apr: Finalized concept, created presentation slides.
* 17 Apr: Started working on draft concept (in wiki)
* 14 Apr: [Posted my concept on Reddit](https://www.reddit.com/r/PHP/comments/4f2epw/reinventing_the_faulty_orm_concept_subqueries/)

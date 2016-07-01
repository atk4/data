# Agile Data - Database access abstraction framework.

Have you been frustrated with inefficiency of ORM or the bulkiness of Query Builders? Do you think that describing your business logic could be simpler, more consise and take full advantage of SQL an NoSQL features without vendor-specific code? Have you ever had to refactor your application because of database change?


**Agile Data is a framework designed with fresh ideas to solve efficiency, performance, clarity, testability and cross-compatibilty problems.**


[![Gitter](https://img.shields.io/gitter/room/atk4/data.svg?maxAge=2592000)](https://gitter.im/atk4/dataset?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)
[![Version](https://badge.fury.io/gh/atk4%2Fdata.svg)](https://packagist.org/packages/atk4/data)
[![Documentation Status](https://readthedocs.org/projects/agile-data/badge/?version=latest)](http://agile-data.readthedocs.io/en/latest/?badge=latest)
[![License](https://poser.pugx.org/atk4/data/license)](https://packagist.org/packages/atk4/data)
[![Build Status](https://travis-ci.org/atk4/data.png?branch=develop)](https://travis-ci.org/atk4/data)
[![Code Climate](https://codeclimate.com/github/atk4/data/badges/gpa.svg)](https://codeclimate.com/github/atk4/data)
[![Test Coverage](https://codeclimate.com/github/atk4/data/badges/coverage.svg)](https://codeclimate.com/github/atk4/data/coverage)

## Goals of Agile Data

The amount of features, capabilities and innovation in Agile Data is overwhelming, so I have moved it into a separate wiki pages that will introduce you to various aspects with code examples and links to in-depth documentation:

 - [describe any business logic](https://github.com/atk4/data/wiki/Business-Models)
 - [persist in wide range of SQL an NoSQL databases](https://github.com/atk4/data/wiki/Persistence)
 - [take full advantage of database capabilities](https://github.com/atk4/data/wiki/Capabilities)
 - [reduce number of queries](https://github.com/atk4/data/wiki/Actions)
 - [express aggregation for reports through business logic](https://github.com/atk4/data/wiki/Derived-Queries)
 
### What differetiates Agile Data?

<a target="_blank" href="https://www.youtube.com/watch?v=XUXZI7123B8"><img src="docs/images/presentation.png" style="float: right" width="30%"></a>

Before developing this framework we researched existing database access patterns and created a whitepaper proposing two new concepts that makes interraction between application and database more efficient. Those concepts allow us to abstract multi-row operations without relying on specific database query language

 - DataSet - Represents a scope of records stored in database and addressable by a Model object. 
  
 - Action - Describes operation that can be executed for entirety of DataSet by Database server. 

### Real-life usage example
 
**Requirement: Calculate total outstanding amount on all orders placed by VIP clients.** 

A task that would normally require you to write a stored SQL procedure, cache data or sacrifice performance can be solved elegantly in Agile Data using database-independent declarative logic:

(note: [click here to see declaration of model classes](https://github.com/atk4/data/wiki/README-Example-Support-Files))

```
  $clients = new Model_Client($db);
      // Object representing all clients - DataSet

  $clients->addCondition('is_vip', true);
      // Now DataSet is limited to VIP clients only

  $ref_order = $vip_client_orders = $clients->ref('Order');
      // This DataSet will contain only orders placed by VIP clients

  $ref_order->addField(
      'item_price', ['field'=>'price']
  );
      // Defines a new expressed through relation with Item (sub-select)

  $vip_client_orders->getRef('Payment')->addField(
      'paid', ['aggregate'=>'sum', 'field'=>'amount']
  );
      // Defines another field as sum of related payments
  
  $vip_client_orders->addExpression('due', '[item_price]*[qty]-[paid]');
      // Defines third field for calculating due

  $total_due_payment = $vip_client_orders->actioun('fx', ['sum', 'due'])->getOne();
      // Defines and executes "sum" action on our expression across specified data-set
```

Only the final `getOne()` will execute database query. Depending on which database vendor you use ($db), execution strategy will be different:

 - SQL: single standard SELECT (with some sub-queries) is executed. Single field/row is fetched.
 
 - Array $data: composes the function, then executes it with $data. (see [Phamda](https://github.com/mpajunen/phamda))
 
 - NoSQL: total of 3 or less queries (depending on database capabilities) will be executed and data from separate queries merged together in PHP giving you a total value.

With the current implementation, the SQL query will look like this:

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

## When to use Agile Data?

**Agile Data can be used in any application and any framework that needs to work with data**

For a simple tasks you can use a `inline` features of the framework, but for the bigger applications you can define entirety of their business logic without any exceptions. 

### Consistency in Business Logic

Your business logic will be clean and well organised if you implement it with Agile Data. Your business entities will map into classes nicely and your existing and new employees will be able to easily locate necessary functionality and change it.

If your presentation layer (views or controllers or helpers) currently contain any amount of logic, you certainly need Agile Data.

### Integration with UI layer

Agile Data does not offer UI layer, however it's unified design makes it really easy for it to be integrated into the UI layer. Connecting Model with Form or API is simple. Integrating complex PDF generator with Aggregated Model is just as simple.

If you are enjoying "Agile Data" then you should also wait for "Agile Toolkit 4.4" that will provide fully integrated UI on top of Agile Data, built by the same team and available under MIT License. 

[Sign up for our Beta-tester program for Agile Toolkit 4.4](http://goo.gl/forms/gXTiRrd7SOyENpLs1)

### Aggregation for your Reports

If you are currently using custom-written queries or stored procedures for generating sophisticated reports, then Agile Data will allow you to replace all of ths with a much more elegant aggregation expressions based around your Business Logic not database.

This will improve clarity and performance of your report generation.

### Unit-Testing

Agile Data allows you to easily mock your database through Array persistance and therefore your Unit Tests will execute much faster and will not rely on database at all.

### Extensibility

There are a lot of ways how to expand Agile Data and we plan to release several extensions:

 - API bridging - bridge your persistence across the RestAPI [late 2016]
 - External File Storage integration [late 2016]
 - Node4j integration [early 2017]
 - MemCache and Redis drivers [early 2017]
 - Array + [Pramda](https://github.com/kapolos/pramda) - Empower arrays with functional programming [early 2017]

If you are willing to see Agile Data driver for NoSQL database of your choice you can help us write an efficient driver to take on the capabilities of the engine - get in touch.

### Commercial Support

Agile Data is built by experienced developer team and released under open-source license (MIT) and can be used at no cost inside any personal or commercial project. If your company requires commercial support for Agile Data, please contact us throug [http://www.agiletoolkit.org/contact](http://www.agiletoolkit.org/contact) 

## Getting Started

Install Agile Data through composer:

```
composer require atk4/data
```

Agile Data relies on 2 other projects:

 * [DSQL](http://dsql.readthedocs.io) - Composable Query Builder (MIT, [GitHub](http://github.com/atk4/dsql))
 * [Agile Core](http://agile-core.readthedocs.io) - useful PHP traits for frameworks (MIT, [Github](https://github.com/atk4/core))

 
### Full documentation for Agile Data

[agile-data.readthedocs.io](http://agile-data.readthedocs.io).

### Getting Started Guides

 * [Watch the quick video on Youtube](https://youtu.be/HAWrviTSzNM)
 * (more guides are coming soon)


## Help us make Agile Data better!!

We wish to take on your feedback and improve Agile Data further. Here is how you can connect with developer team:

 - chat with us on [Gitter](https://gitter.im/atk4/data) and ask your questions directly.
 - ask or post suggestions on our forum [https://forum.agiletoolkit.org](https://forum.agiletoolkit.org)
 - **share Agile Data with your friends**, we need more people to use it. Blog. Tweet. Share.
 - work on some of the tickets marked with [[help wanted](https://github.com/atk4/data/labels/help%20wanted)] tag.

See [www.agiletoolkit.org](http://www.agiletoolkit.org/) for more frameworks and libraries that can make your PHP Web Application even more efficient.

## Roadmap

Follow pull-request history and activity of repository to see what's going on.

```
0.5   Add support for Conditions to implement DataSet.
0.6   Further integrate with the Query Builder, add Expression support.
0.7   Add relation traversal support.
0.8   Add support for hooks (before/after save) in (DM and PM).
0.9   Add support for dealing with multiple persistences.
0.10  Add ability to specify meta-information into fields.
0.11  Add support for derived models (unions).
0.12  Add support for 3rd party vendor implementations.
0.13  Achieve our test coverage, code quality and documentation standards.
1.0   First Stable Release.
1.1   Add support for MongoDB.
1.2   Add support and docs for Validators.
```

## Past Updates

* 28 Jun: Released join support for SQL and Array
* 24 Jun: Released 0.3 with general improvements
* 17 Jun: Finally shipping 0.2: With good starting support of SQL and Array 
* 29 May: Finished implementation of core logic for Business Model
* 11 May: Released 0.1: Implemented code climate, test coverage and travis
* 06 May: Revamped the concept, updated video and made it simpler
* 22 Apr: Finalized concept, created presentation slides.
* 17 Apr: Started working on draft concept (in wiki)
* 14 Apr: [Posted my concept on Reddit](https://www.reddit.com/r/PHP/comments/4f2epw/reinventing_the_faulty_orm_concept_subqueries/)

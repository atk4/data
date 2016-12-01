# Agile Data

[![Build Status](https://travis-ci.org/atk4/data.png?branch=develop)](https://travis-ci.org/atk4/data)
[![Code Climate](https://codeclimate.com/github/atk4/data/badges/gpa.svg)](https://codeclimate.com/github/atk4/data)
[![StyleCI](https://styleci.io/repos/56442737/shield)](https://styleci.io/repos/56442737)
[![Test Coverage](https://codeclimate.com/github/atk4/data/badges/coverage.svg)](https://codeclimate.com/github/atk4/data/coverage)
[![Version](https://badge.fury.io/gh/atk4%2Fdata.svg)](https://packagist.org/packages/atk4/data)

**Data Access Framework for high-latency databases (Cloud SQL/NoSQL).**

The #1 reason, why so many developers prefer Query Building over Active Record / Object Relational Mappers is **query-efficiency**.

Agile Data implements an entirely new pattern for data abstraction, that is specifically designed for remote databases such as RDS, Cloud SQL, BigQuery and other distributed data storage architectures. It focuses on **reducing** number of requests your App have to send to the Database by using more sophisticated queries while also offering full Domain Model mapping and Database vendor abstraction.

## Q&A

**Q: I fine-tune all my SQL queries. Why should I care?**

Agile Data is capable of fine-tuning queries for you, including being able to consistently use joins, sub-qureies, expressions and advanced SQL patterns such as UNION, full-text search conditions or aggregation without you explicitly writing SQL code.

Now, since your code is no longer SQL-reliant, it's much easier to transparently start using NoSQL vendors, such as Cloud Databases for storing "AuditLog" or In-memory database for caches.

Extensions can now also participate in query building and transparently add features such as "Soft-Delete", "AuditTrail", "Undo", "Scopes" and many others.

We are working on more extensions/vendor drivers, which you will be able to use without any code refactoring.

**Q: How is Agile Data different to ORM or Active Record?**

ORM is designed for persisting application data in local, low-latency storage such as SQLite. Most cloud applications are now separated from their databases through networks, virtual containers and physical distances. This often cripples performance and forces use of hacks (such as eager-loading) to solve scalability problems.

You may also run into memory problems when using ORM over extremely large databases. ORM are not designed to work with multi-user data shared inside same tables either.

Agile Data [retains all of the basic and advanced ORM features](http://socialcompare.com/en/comparison/php-data-access-libraries-orm-activerecord-persistence), has a very similar simple syntax yet it is much better at using advanced database features (such as aggregation frameworks).

**Q: What are benefits and risks if I start using Agile Data in my existing app?**

Agile Data has minimum dependencies and is designed to be compatible with any PHP architecture or application (even your custom-mvc-framework). You can use it along-side your existing Query logic and focus on performance bottlenecks as you gradually refactor you app. If your current application executes more than 2 SQL queries per request or contains at least 1 complex SQL statement, Agile Data is worth considering.

You will find that handling security and access control is much simpler with Agile Data and through the use of extension you will be saving lots of time. The footprint of your code will be reduced significantly and you will be able to easily build test-suite that focuses on your business rules rather then persistence rules.

**Q: I already know various ORMs and SQL. Agile Data is similar, right?**

No. Agile Data is not ORM. It has comparable features but it is designed from the ground-up in a different way. You would need to start at the very beginning.

Luckily we have designed Agile Data to be really easy-to-learn and use, so you will enjoy the learning experience.

## Agile Data at a Glance

Agile Data implements various advanced database access patterns such as Active Record, Persistence Mapping, Domain Model, Event sourcing, Actions, Hooks, DataSets and Query Building in a **practical way** that can be **easily learned**, used in any framework with SQL or NoSQL database and meeting all **enterprise**-specific requirements.

You get to manipulate your objects first before query is invoked. The next code snippet will work with your existing database of Cliens, Orders and Order Lines and will query total amount of all orders placed by VIP clients. Looking at the resulting query you will  notice an implementation detail - Line total is not stored physically inside the database but is rather expressed as multiplication of price and quantity:

``` php
$m = new Client($db);
echo $m->addCondition('vip', true)
  ->ref('Order')->ref('Line')->action('fx', ['sum', 'total'])->getOne();
```

Resulting Query will always use parametric variables if vendor driver supports them (such as PDO):

``` sql
select sum(`price`*`qty`) from `order_line` `O_L` where `order_id` in (
  select `id` from `order` `O` where `client_id` in (
    select `id` from `client` where `vip` = :a
  )
)

// :a is "Y"
```

Agile Data is not only for SQL databases. It can be used anywhere from decoding Form submission data ($_POST) or even work with custom RestAPIs.  Zero-configuration implementation for "AuditTrail", "ACL" and "Soft Delete" as well as new features such as "Undo", "Global Scoping" and "Cross-persistence" make your Agile Data code enterprise-ready out of the box.

All of the above does not add complexity to your business logic code. You don't need to create XML, YAML files or annotations. There is no mandatory caching either. 

My next example demonstrates how simple and clean your code looks when you store new Order data:

``` php
$m = new Client($db);
$m->loadBy('name', 'Pear Company');
$m->ref('Order')
   ->save(['ref'=>'TBL1', 'delivery'=>new DateTime('+1 month')])
   ->ref('Lines')->import([
      ['Table', 'category'=>'furniture', 'qty'=>2, 'price'=>10.50],
      ['Chair', 'category'=>'furniture', 'qty'=>10, 'price'=>3.25],
]);
```

Resulting queries (I have removed back-ticks and parametric variables for readability) use a consise syntax and demonstrate some of the "behind-the-scenes" logic:

-   New order must belong to the Company. Also company must not be soft-deleted.
-   `delivery` is stored in field `deliery_date`, also the DateTime type is mapped into SQL-friendly date.
-   `order_id` is automatically used with Lines.
-   `category_id` can be looked up directly inside the INSERT (standard feature of SQL reference fields).

```sql
select id, name from client where name = "Pear Company" and is_deleted = 0;
insert into order (company_id, ref, delivery_date)
  values (293, "TBL1", "2015-18-12");
insert into order_lines (order_id, title, category_id, qty, price) values
  (201, "Table", (select id from category where name = "furniture"), 2, 10.50),
  (201, "Chair", (select id from category where name = "furniture"), 19, 3.25);
```

If you have enjoyed those examples and would like to try them yourself, continue to https://github.com/atk4/data-primer.

## Getting Started with Agile Data

Depending on your learning preferences you can find the following resources useful:

- Documentation: http://agile-data.readthedocs.io
- Slides from my presentation: [Love and Hate Relationship between ORMs and Query Builders](http://www.slideshare.net/romaninsh/agile-data-presentation-3-cambridge).
- Download a sample application that uses Agile Data: https://github.com/atk4/data-primer
- Watch series of 5-minute Videos explaining core concepts: https://www.youtube.com/playlist?list=PLUUKFD-IBZWaaN_CnQuSP0iwWeHJxPXKS
- If you are interested in using Agile Data commercially, email <u>info</u> @ <u>agiletoolkit.org</u> for a **free** Skype presentation.

### Community Support Channels

![Gitter](https://img.shields.io/gitter/room/atk4/data.svg?maxAge=2592000)[![Stack Overlfow Community](https://img.shields.io/stackexchange/stackoverflow/t/atk4.svg?maxAge=2592000)](http://stackoverflow.com/questions/ask?tags=atk4)[![Discord User forum](https://img.shields.io/badge/discord-User_Forum-green.svg)](https://forum.agiletoolkit.org/c/44)

**Our community is still growing. HELP US GROW by telling your friends about Agile Data!!!**

### Learning the Concepts

There are 3 fundamental principles that separate Agile Data from other data access frameworks:

-   Smart Fields
-   DataSets
-   Actions

The best way to learn is by reading through [Quick Start Guide](http://agile-data.readthedocs.io/en/develop/quickstart.html), however I'll include a brief descriptions below:

#### Models

Agile Data uses vendor-independent and lightweight `Model` class to describe your business entities:

``` php
class Client extends \atk4\data\Model {
  public $table = 'client';
  function init() {
    parent::init();
    
    $this->addFields(['name','address']);
    
    $this->hasMany('Project', new Project());
  }
}
```

-   Documentation: http://agile-data.readthedocs.io/en/develop/model.html
-   Examples: https://github.com/atk4/data-primer/tree/master/src

#### Introducing Actions

 ![mapping](docs/images/mapping.png)

Anything related to a Model (Field, Condition, Reference) is an object that lives is the realm of "Domain Model" inside PHP memory. When you  `save()`, frameworks generates an "Action" that will actually update your SQL table, invoke RestAPI request or write that file to disk.

Each persistence implements actions differently. SQL is probably the most full-featured one:

![GitHub release](docs/images/action.gif)

-   Documentation: http://agile-data.readthedocs.io/en/develop/quickstart.html?highlight=action#actions

#### Introducing Expressions

Smart Fields in Agile Toolkit are represented as objects. Because of inheritance, Fields can be quite diverse at what they do. For example `Field_SQL_Expression` and `Field_Expression` can define field through custom SQL or PHP code:

![GitHub release](docs/images/expression.gif)

-   Documentation: http://agile-data.readthedocs.io/en/develop/expressions.html

#### Introducing References

Foreign keys and Relation are bread and butter of RDBMS. While it makes sense in "Persistence", not all databases support Relations.

Agile Data takes a different approach by introducing "References". It allow you to define relationships between Domain Models that can work with non-relational databases, yet allow you to perform various operations such as importing or aggregating fields. (use of JOIN is explained below)

![GitHub release](docs/images/import-field.gif)

-   Documentation: http://agile-data.readthedocs.io/en/develop/references.html

#### Model Conditions and DataSets

Conditions (or scopes) are rare and optional feature across ORMs but it is one of the most significant features in Agile Data. It allows you to create objects that represent multiple database records without actually loading them.  

Once condition is defined, it's will appear in actions and will also restrict you from adding non-compliant records.

![GitHub release](docs/images/reference-magic.gif)

-   Documentation: http://agile-data.readthedocs.io/en/develop/conditions.html

#### Build Reports inside Domain Model

With most frameworks when it comes to serious data aggregation you must make a choice - write in-efficient domain-model code or write RAW SQL query. Agile Data helps you tap into unique features of your DataBase while letting you stay inside Domain Model.

How do we create an efficient query to display total budget from all the projects grouped by client's country while entirely remaining in domain model? One line of code in Agile Data:

![GitHub release](docs/images/domain-model-reports.gif)

Did you notice the query has automatically excluded cancelled projects?

#### Model-level join

Most ORMs can define models that only work with a single SQL table. If you have
to store logical entity data into multiple tables - tough luck, you'll have to do
some linking yourself.

Agile Data allow you to define multiple joins right inside your model. As you join()
another table, you will be able to import fields from the joined table. If you
create a new record, data will automatically be distributed into the tables and
records will be linked up correctly.

![GitHub release](docs/images/model-join.gif)

The best part about joins is that you can add them to your existing model for specific queries. Some extensions can even do that.

-   Documentation: http://agile-data.readthedocs.io/en/develop/joins.html

#### Deep Model Traversal

Probably one of the best feature of Agile Data is deep traversal. Remember how
your ORM tried to implement varous many-to-many relationships? This is no longer
a problem in Agile Data.

Suppose you want to look at all the countries that have 2-letter name. How many
projects are there from the clients that are located in a country with 2-letter name?

Agile Data can answer with a query or with a result.

![GitHub release](docs/images/deep-traversal.gif)

-   Documentation: http://agile-data.readthedocs.io/en/develop/references.html#traversing-dataset

## Advanced Features and Extensions

The examples you saw so far are only a small fragment of the possibilities you can
achieve with Agile Data. You now have a new playground where you can design your
business logic around the very powerful concepts.

One of the virtues we value the most in Agile Data is ability to abstract and
add higher level features on our solid foundation.

### Explorability

If you pass a `$model` object inside any method, add-on or extension, it's possible for them to discover not only the data, but also field types and various meta-information, references to other models, supported actions and many more.

With that, creating a Dynamic Form UI object that automatically includes DropDown with list of allowed values is possible.

In fact - we have already stared work on [Agile UI](http://github.com/atk4/ui) project!

### Hooks

You now have a domain-level and persistence-level hooks. With a domain-level ones (afterLoad, beforeSave) you get to operate with your field data before or after an operation.

On other hand you can utilise persistence-level hooks ('beforeUpdateQuery', 'beforeSelectQuery') and you can interact with a powerful Query Builder to add a few SQL options (insert ignore or calc_found_rows)
if you need.

And guess what - should your model be saved into NoSQL database, the domain-level hooks will be executed, but SQL-specific ones will not.

-   Documentation: http://agile-data.readthedocs.io/en/develop/hooks.html

### Extensions

Most ORMs hard-code features like soft-delete, audit-log, timestamps. In Agile Data the implementation of base model is incredibly lightweight and all the necessary features are added through external objects.

We are still working on our Extension library but we plan to include:

- Audit Log - record all operations in a model (as well as previous field values)
- Undo - revert last few few operations on your model.
- ACL - flexible system to restrict access to certain records, fields or models based on
  permissions of your logged-in user or custom logic.
- Filestore - allow you to work with files inside your model. Files are actually
  stored in S3 (or other) but the references and meta-information remains in the database.
- Soft-Delete, purge and undelete - several strategies, custom fields, permissions.

More details on extensions: http://www.agiletoolkit.org/data/extensions

If you are interested in early access to Extensions, please contact us at <u>info</u> @ <u>agiletoolkit.org</u>.

### Performance

If you wonder how those advanced features may impact performance of loading and saving data, there is another pleasant surprise. Loading, saving, iterating and deleting records do not create new in-memory objects:


``` php
foreach($client->ref('Project') as $project) {
    echo $project->get('name')."\n"
}

// $project refers to same object at all times, but $project's active data
// is re-populated on each iteration.
```

Nothing unnecessary is pre-fetched. Only requested columns are queried. Rows are streamed and never ever we will try to squeeze a large collection of IDs into a variable or a query.

Agile Data works fast and efficient even if you have huge amount of records in the database.

### Security

When ORM promise you "security" they don't really extend it to the cases where you wish to perform a sub-query of a sort. Then you have to deal with RAW query components and glue them together yourself.

Agile Data provides a universal support for Expressions and each expression have support for `escaping` and `parameters`. My next example will add scope filtering the countries by their length. Automatic parameters will ensure that any nastiness will be properly escaped:

``` php
$country->addCondition($country->expr('length([name]) = []', [$_GET['len']])); // 2
```

Resulting query is:

``` php
where length(`name`) = :a  [:a=2]
```

Another great security feature is invoked when you try and add a new country:

```php
$country->insert('Latvia');
```

This code will fail, because our earlier condition that "Latvia" does not satisfy. This makes variety of other uses safe:

```php
$client->load(3);
$client->ref('Order')->insert($_POST);
```

Regardless of what's inside the `$_POST`, the new record will have `client_id = 3` .

Finally, the following is also possible:

``` php
$client->addCondition('is_vip');
$client->ref('Order')->insert($_POST);
```



Regardless of the content of the POST data, the order can only be created for the VIP client. Even if you perform a multi-row opetation such as `action('update')` or `action('delete')` it will only apply to records that match all of the conditions.

Those security measures are there to protect you against human errors. We think that input sanitization is still quite important and you should do that.

## Installing into existing project

Start by installing Agile Data through composer:

``` bash
composer require atk4/data
composer g require psy/psysh:@stable  # optional, but handy for debugging!
```

Define your first model class:

``` php
namespace my;
class User extends \atk4\data\Model
{
    public $table = 'user';
    function init()
    {
        parent::init();

        $this->addFields(['email','name','password']);
        // use your table fields here
    }
}
```

Next create `console.php`:

``` php
<?php
include'vendor/autoload.php';
$db = \atk4\data\Persistence::connect(PDO_DSN, USER, PASS);
eval(\Psy\sh());
```

Finally, run `console.php`:

```
$ php console.php
```

Now you can explore. Try typing:

``` php
> $m = new \my\User($db);
> $m->loadBy('email', 'example@example.com')
> $m->get()
> $m->export(['email','name'])
> $m->action('count')
> $m->action('count')->getOne()
```

## Agile Toolkit

Agile Data is part of [Agile Toolkit - PHP UI Framework](http://agiletoolkit.org). If you like
this project, you should also look into:

- [DSQL](https://github.com/atk4/dsql) - [![GitHub release](https://img.shields.io/github/release/atk4/dsql.svg?maxAge=2592000)]()
- [Agile Core](https://github.com/atk4/core) - [![GitHub release](https://img.shields.io/github/release/atk4/core.svg?maxAge=2592000)]()


## Current Status

Agile Data is **Stable since Jul 2016**. For more recent updates see [Changelog](https://github.com/atk4/data/blob/develop/CHANGELOG.md).

### Timeline to the first release

* 20 Jul 2016: Release of 1.0 with a new QuickStart guide
* 15 Jul 2016: Rewrote README preparing for our first BETA release
* 05 Jul 2016: Released 0.5 Expressions, Conditions, Relations
* 28 Jun 2016: Released 0.4 join support for SQL and Array
* 24 Jun 2016: Released 0.3 with general improvements
* 17 Jun 2016: Finally shipping 0.2: With good starting support of SQL and Array
* 29 May 2016: Finished implementation of core logic for Business Model
* 11 May 2016: Released 0.1: Implemented code climate, test coverage and travis
* 06 May 2016: Revamped the concept, updated video and made it simpler
* 22 Apr 2016: Finalized concept, created presentation slides.
* 17 Apr 2016: Started working on concept draft (in wiki)
* 14 Apr 2016: [Posted my concept on Reddit](https://www.reddit.com/r/PHP/comments/4f2epw/reinventing_the_faulty_orm_concept_subqueries/)

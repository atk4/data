# Agile Data - Database access abstraction framework.


**Agile Data is a unique SQL/NoSQL access library that promotes correct Business Logic design in your
PHP application and implements database access in a flexible and scalable way.**

[![Build Status](https://travis-ci.org/atk4/data.png?branch=develop)](https://travis-ci.org/atk4/data)
[![Code Climate](https://codeclimate.com/github/atk4/data/badges/gpa.svg)](https://codeclimate.com/github/atk4/data)
[![Test Coverage](https://codeclimate.com/github/atk4/data/badges/coverage.svg)](https://codeclimate.com/github/atk4/data/coverage)
[![Issue Count](https://codeclimate.com/github/atk4/data/badges/issue_count.svg)](https://codeclimate.com/github/atk4/data)

The key design concepts and the reason why we created Agile Data are:

 - Agile Data is simple to learn. We have designed our framework with aim to educate developers with
   2+ years of experience on how to properly design application logic.

 - We introduce fresh concepts - DataSet and Action, that result in a more efficient ways to
   interact with non-trivial databases (databases with some query language support).
 
 - Separation of Business Logic and Persistence. We do not allow your database schema to dictate your
   business logic design.

 - Major Databases are supported (SQL and NoSQL) and our framework will automatically use
   features of the database (expressions, sub-queries, multi-row operation) if available.

 - Extensibility. Our core concept is extended through with Joins, SQL Expressions and Sub-Selects,
   Calculated fields, Validation, REST proxies, Caches, etc.

 - Great for UI Frameworks. Agile Data integrates very well with compatible UI layer / widgets.

## Introducing the concept

<a target="_blank" href="https://www.youtube.com/watch?v=XUXZI7123B8"><img src="docs/images/presentation.png" width="100%"></a>

To learn and use Agile Data efficiently, you need to leave behind your prejudice towards some of the data access
patterns and read on how we are improving familiar concepts by implementing them correctly:

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

We have designed Agile data in a very simple-to-learn way. We seek to educate developers about the correct way to write code through intuitive pattern designs.

We also care about other technicalities, so we will:

 - work with PHP community and discuss main decisions collaboratively. 
 - write short and easy-to-read, standard-compliant code with high code-climate score.
 - unit-test our own code with minimum of 95% code coverage.
 - add code through pull requests and discuss them before merging.
 - never break APIs in minor releases.
 - support composer but include minimum dependencies.
 - be friendly with all higher-level frameworks.
 - avoid database query latency/overheads, pre-fetching or lazy loading.
 - do not duplicate the code (e.g. in vendor drivers)
 - use MIT License
 
## Project Credibility 

A new and "revolutionary" mini-ORM projects come and go every week. Will Agile Data be here in 5 years time?

Yes.

The founder and lead developer for this library is: [Romans Malinovskis](https://www.openhub.net/accounts/romaninsh) who has been a long-time open-source developer and maintainer of Agile Toolkit (PHP UI Framemwork). We have just completed beta of [DSQL - Query Builder for PHP](https://github.com/atk4/dsql) to the similar high standard of quality and it will be integral part of Agile Data.

This library is inspired by "Model" implementation in Agile Toolkit that Romans [has implemented 2011](https://github.com/atk4/atk4/commit/976ccce73c5c7bf5afbedc70aa3f72158dbf534b#diff-8e1db8ebf3425c345973e98193903012) and has been maintained since. Model implementation of Agile Toolkit has been well received by users and powers many production projects.

This project (Agile Data) is a major rewrite that separates "Models" from the rest of Agile Toolkit to make it beneficial for any PHP developer and framework. Once Agile Data is stable the Agile Toolkit will also be updated to depend on Agile Data ensuring the longevity of this project.

![image](docs/images/agiletoolkit.png)

[Frequently Asked Questions](https://github.com/atk4/dataset/wiki/Frequently-Asked-Questions)


## Sample Code

Usage syntax of Agile data is consise and readable. Start by defining your Business Models.

```
class Model_User extends \atk4\data\Model
{
    private $table = 'user';
    function init() {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
        $this->addField('email');

        $this->addField('type')->enum(['client', 'admin']);

        $this->hasMany('Order');
    }
}
```

Link your business model with persistence layer like this. Once linked you can
access records individulaly:

```
$m = $db->add('Model_User');
$m->load(10); // 1-qurey
```

or build SQL queries that affect multiple records:

```
$m->addCondition('email',null)->dsql()->delete(); // 1-query
```

You can traverse individual records:

```
$m = $db->add('Model_User');
$m->load(10); // 1-query

foreach ($m->ref('Order') as $order) {  // 1-query
    // iterate through order of user with id=10
}
```

or the whole data-set:

```
$m = $db->add('Model_User');
$m->addCondition('isOnline', true);

foreach ($m->ref('Order') as $order) {  // 1-query
    // iterate through order of all on-line users
}
```

You can define persistance logic rules for one or multiple databases based on
supported capabilities:

```
class Model_Client extends Model_User
{
    function init() {
        parent::init();
        
        if ($this->connection->supports('expressions')) {
            $this->addExpression('completed_orders')->set(
                $this->ref('Order')
                    ->addCondition('isCompleted',true)
                    ->sum('amount')
            );
        } else {
            $this->addField('completed_orders'); // does not support sub-queries
        }
    }
}
```

and use distinctive logic when using various vendors:

```
class Model_Order extends atk4\data\Model
{
    public $table='order';
    function init() {
        parent::init();

        $this->hasMany('Order_Line');
        $this->hasOne('Client');

        if ($this->connection->supports('expressions')) {
            $this->addExpression('amount')
                ->set($this->ref('Order_Line')->sum('amount'));
        } else {
            $this->addField('amount');
        }
    }
    function complete() {
        $this['isCompleted']=true;
        $this->saveAndUnload();

        if (!$this->connection->supports('expressions')) {
            $this->ref('Client')->incr('completed_orders', $this['amount']);
        }
    }
}
```

N.B. (ABOVE EXAMPLES MIGHT CHANGE AS WE IMPLEMEST THE CODE).


## Current Status

The Design Concept for Agile Data has been complete. Please join us on "Gitter" to discuss
and give us your feedback. Ask your friends to review and give us feedback. Once we have
enough feedback, we'll start implementation.

[Read our Development Wiki](https://github.com/atk4/dataset/wiki).

[![Join the chat at https://gitter.im/atk4/dataset](https://badges.gitter.im/atk4/dataset.svg)](https://gitter.im/atk4/dataset?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

## Roadmap

To implement Agile Data, we had to start from the very beginning.

```
0.0   Finalize concept, api-interface draft and lightweight documentation.
0.1   Set up CI, CodeClimate, Docs to keep development consistent.
0.2   Implement Active Record with Business Model class.
0.3   Implement SQL persistence mapping - storing and loading records.
0.4   Add support for Conditions to implement DataSet.
0.5   Further integrate with the Query Builder, add Expression support.
0.6   Add relation traversal support.
0.7   Add support for hooks (before/after save) in (DM and PM).
0.8   Add support for dealing with multiple persistences.
0.9   Add support for strong and weak join when persisting.
0.10  Add ability to specify meta-information into fields.
0.11  Add support for derived models (unions).
1.12  Add support for 3rd party vendor implementations.
0.13  Achieve our test coverage, code quality and documentation standards.
1.0   First Stable Release.
1.1   Add support for MongoDB.
1.2   Add support and docs for Validators.
```

## Past Updates

* 22 Apr: Finalized concept, created presentation slides.
* 17 Apr: Started working on draft concept (in wiki)
* 14 Apr: [Posted my concept on Reddit](https://www.reddit.com/r/PHP/comments/4f2epw/reinventing_the_faulty_orm_concept_subqueries/)




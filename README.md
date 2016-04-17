# dataset - Database Access Library

Most performance problems in apps are tend to arise due to inefficient use of database access patterns. Techniques such as ActiveRecord, ORM, Query Builder use different approaches, but developer must intelligently decide which of the above must be used in which circumstances. 

Unfortunately frameworks tend to implement only single database access approach or even if they do include both ORM and Query Builder they are not interoperating very well. For example, you may not easily be able to adjust ORM operation through query builder. 

This library is designed to provide developer with multiple Database Access Tools that work well together. We want to give developer a whole toolkit and educate them how to choose appropriate approach. We believe that user of this library can express their Business Logic in a very structured and clean way yet keep their application performance from decreasing when dealing with large amount of data.

This library will introduce you to the new clean design for fully-integrated:

 - [Business Models](https://github.com/atk4/dataset/wiki/Business-Models) - Implement a clean business logic [DM].
 - [Active Record](https://github.com/atk4/dataset/wiki/Active-Record) - Use this when you need individual record access [DM].
 - [Explicit Loading and Saving](https://github.com/atk4/dataset/wiki/Explicit-Loading-and-Saving) - Don't rely on framework to do loading magic for you. Load data yourself. [PM]
 - [Relation Mapping](https://github.com/atk4/dataset/wiki/Relation-Mapping) - Traverse between business data [DM].
 - [Persistence](https://github.com/atk4/dataset/wiki/Persistence) - Design and tweak how your Business Models are mapped into tables [DM->PM].
 - [Derived Queries](https://github.com/atk4/dataset/wiki/Derived-Queries) - Express your Business Models as SQL queries [PM].
 - [Expressions](https://github.com/atk4/dataset/wiki/Expressions) - Use Derived queries to add fields in your Business Models that are calculated in SQL [PM].
 - [Query Building](https://github.com/atk4/dataset/wiki/Query-Building) - Build an execute complex multi-row queries mapped from your Business Models [PM].
 - [Unit-testing](https://github.com/atk4/dataset/wiki/Unit-Testing) - Business Models can be decoupled from persistence layer for efficient Unit Testing [DM].
 - [Aggregation and Reports](https://github.com/atk4/dataset/wiki/Aggregaation-and-Reports) - Support report generation techniques, aggregation and unions for your Business models [DM].

All of the above concepts are designed and delivered in a very simple-to-learn way. Our main goal is to educate new programmers about the right way to write code through intuitive pattern designs.

We also care about other technicalities, so we will:

 - work with PHP community and discuss main decisions collaboratively. 
 - write short and easy-to-read, standard-compliant code with high code-climate score.
 - unit-test our own code with minimum of 95% code coverage.
 - never break APIs in minor releases.
 - include minimum dependencies.
 - be friendly with all higher-level frameworks.
 - avoid database query latency/overheads, pre-fetching or lazy loading.
 - do not duplicate the code (e.g. in vendor drivers)
 - use MIT License

The founder and lead developer for this library is: [Romans Malinovskis](https://www.openhub.net/accounts/romaninsh). To get in touch privately, [use my twitter](https://twitter.com/romaninsh).

[Frequently Asked Questions](https://github.com/atk4/dataset/wiki/Frequently-Asked-Questions)

## Current Status

We are currently working on "Concept Design". Feel free to discuss, contribute feedback or follow. 

[Read our Development Wiki](https://github.com/atk4/dataset/wiki).

You can also join us on Gitter to ask questions:

[![Join the chat at https://gitter.im/atk4/dataset](https://badges.gitter.im/atk4/dataset.svg)](https://gitter.im/atk4/dataset?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

## Past Updates

* 17 Apr: Started working on draft concept (in wiki)
* 14 Apr: [Posted my concept on Reddit](https://www.reddit.com/r/PHP/comments/4f2epw/reinventing_the_faulty_orm_concept_subqueries/)




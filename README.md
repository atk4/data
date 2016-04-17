# dataset - Database Access Library

Most performance problems in apps are usually due to incorrect data access design. As developers we tend to apply methods "that worked" in all circumstances. Active Record is a good concept as long as you are not update your records in foreach() loop. ORM is a good concept as long as it separates your business models (DM) from table structure (PM).

This framework is designed to approach your database access (persistence) with the correct tools and consists of several parts:

 - Business Models - Implement a clean business logic.
 - Active Record - Use this when you need individual record access.
 - Relation Mapping - Traverse between business data.
 - Persistence - Design and tweak how your Business Models are mapped into tables.
 - Derived Queries - Express your Business Models as SQL queries.
 - Expressions - Use Derived queries to add fields in your Business Models that are calculated in SQL
 - Explicit Loading - Don't rely on framework to do loading magic for you. Load data yourself.
 - Query Building - Build an execute complex multi-row queries mapped from your Business Models.
 - Unit-testing - Business Models can be decoupled from persistence layer for efficient Unit Testing.

All of the above concepts are designed and delivered in a very simple-to-learn way. Our main goal is to educate new programmers about the right way to write code through intuitive coding pattern design.

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

## Current Status

We are currently working on "Concept Design". Feel free to discuss / contribute or follow.

[Read our Development Wiki](https://github.com/atk4/dataset/wiki).

You can also join me on Gitter to discuss some ideas:

[![Join the chat at https://gitter.im/atk4/dataset](https://badges.gitter.im/atk4/dataset.svg)](https://gitter.im/atk4/dataset?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

## Past Updates

* 17 Apr: Started working on draft concept (in wiki)
* 14 Apr: [Posted my concept on Reddit](https://www.reddit.com/r/PHP/comments/4f2epw/reinventing_the_faulty_orm_concept_subqueries/)




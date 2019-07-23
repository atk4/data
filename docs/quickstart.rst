.. _quickstart:

==========
Quickstart
==========

The reason most developers dislike *Object Relational Mapper (ORM)* frameworks is because it is `slow, clumsy, limited and flawed <https://medium.com/@romaninsh/pragmatic-approach-to-reinventing-orm-d9e1bdc336e3>`_. The benefits are **consistency, compatibility and abstraction** it offers to larger projects.

ATK Data is a Data/Persistence mapping framework in PHP implementing **an alternative to ORM** to achieve the following goals:

 - offers consistency, compatibility and abstraction
 - avoid classical flaws of ORM pattern
 - remain SQL/NoSQL agnostic
 - offer ability to take advantage of vendor-specific features
   
The pattern implemented by ATK Data (DataSets) were proposed on *Apr 2016*. The design goals and concepts are further discussed in :ref:`Overview`.

Quick Intro
===========

ATK Data is focused on reducing number of database queries, moving CPU-intensive tasks into your database (if possible). It is well suited for Amazon RDS, Google Cloud SQL and ClearDB but thanks to abstraction will work transparently with Static data, NoSQL or RestAPIs backends.

When using ATK Data, it's possible to work with the data on a **higher level**, performing operations such as :ref:`DeepCopy`, :ref:`DeepTraversal` as well as :ref:`Aggregation`.

Core of ATK Data aims to:

 - allow you to describe your object entities without database specifics
 - save/load model data through generic database drivers for SQL and NoSQL
 - work with multiple record sets in your application
 - integrate with generic `User Interface <https://github.com/atk4/ui>`_ or `Application Interface <https://github.com/atk4/api>`_ extensions

ATK Data can do a lot more through add-ons.

Quick Links
===========

Model Definition:
   Defined in PHP class method init(). Supports PDO, NoSQL, Array, Session, CSV,
   and RestAPI through custom persistence classes. Column-specific structure but
   support nesting and containment. Inheritance.

Loading and Storing data:
   Generic integration with multiple SQL vendors. Support for Expressions. Table
   name mapping, field name mapping, type mapping, serialization, field-level
   encryption. Sub-Selects. Joins. Mapping to stored procedures. Recursive import.
   Hooks. Behaviours.

DataSet Operations:
   Additive conditions. Expression-based conditions. Limits. Fetching all data,
   selective columns or mapping. Expressing and re-using SQL statements. Updating
   multiple records. Aggregation functions. Inferring values. Global conditions.

Field Types:
   Native PHP types. Custom types. Typecasting settings (e.g. format). ENUMs.
   Key-value. PHP Calculated types.

References and Relations:
   hasOne and hasMany reference. Traversing without data loading. Cross-persistence
   traversal. Deep traversal. 

Utilities:
   Schema migration. Deep copy. Unions. Aggregate Models. 

Actions:
   Defining user actions. Action arguments. Action executor specs. ACL. Transactions.

Meta Information:
   Inferring field decorator. Validation rules. Captions, hints and description.
   Localization.

Security:
   Scopes. System fields and actions. Areas of concern.

Refactoring:
   Using with existing schema. Refactoring database.

Quick Tour
==========

First get the following:

- PHP 7.0 or above.
- MySQL or MariaDB
- Install `Agile Data Primer <https://github.com/atk4/data-primer/>`_

.. code-block:: bash

    git clone https://github.com/atk4/data-primer.git
    cd data-primer
    composer update
    cp config-example.php config.php

    # EDIT FILE CONFIG.PHP
    vim config.hpp

    php console.php


Enter statements into console one-by-one and carefully observe results. If you wish
to see SQL queries as they are being executed, be sure to include "dumper" proxy.

Persistence Driver
------------------

Persistence is a database, like MySQL. It could also be a CSV file. To interract with a
persistence you need a driver. `console.php` has already initialized persistence and
connected to database, but no queries were executed::

    > $db
    => atk4\data\Persistence\SQL {...}

The appropriate persistence class will be selected depending on your connection string (DSN).

Model Definition
----------------

Your application does not talk to database directly. Instead it requires an object, which
we call `Model`. You should create class for every business entity, for example:

 - `Client <https://github.com/atk4/data-primer/blob/master/src/inv/Client.php>`_
 - `Invoice <https://github.com/atk4/data-primer/blob/master/src/inv/Invoice.php>`_
 - `InvoiceLine <https://github.com/atk4/data-primer/blob/master/src/inv/InvoiceLine.php>`_

Model Instance
--------------

Return back to the console, and create instance of Client class::

   > $client = new inv\Client($db);
   => inv\Client {#170
        +id: null,
        +conditions: [],
      }

The object `$client` has a state and can be used to interact with single or multiple records.
Multi-record operations currently apply to entire set of data. Lets find out how many cliens
we have::

   > $client->action('count')->getOne();
   => "10"

Next, we can use :php:meth:`Model::loadAny` to load one record from persistence and then
get data with :php:meth:`Model::get`::

   > $client->loadAny();
   > $client->get();

The types returned by `get()` are automatically converted from database-specific to PHP-specific,
such as `DateTime`.

Fields
------

Model object will also populate :php:class:`Field` objects. You can get list of them with :php:meth:`Model::getFields`.
Observe that field objects may vary depending on definition or :ref:`Persistence` capabilities. 

Unlike other frameworks, Model object is reusable. You can unload and load data of another record
or even iterate through entire set::

   > $client->unload()
   > $client->loadBy('name', 'John');

Field objects also remain and can hold valuable information which may be relied on by other 
frameworks or add-ons on the fly.

References
----------

ATK Data uses term "reference" instead of "relation", because it's more broad. Think of it this way:

 - one record of Client has many Invoice records.

Reference is defined in :php:meth:`Model::init` method like this::

   $this->hasMany('Invoices', Invoice::class);

Go back to console and see which references your `$client` object has::

   > $client->getRefs();

Then traverse this reference::

   > $invoices = $client->ref('Invoices');
   => inv\Invoice {#226
        +id: null,
        +conditions: [
          [
            "inv_client_id",
            "45",
          ],
        ],
      }

Observe that the model returned by :php:meth:`Model::ref` does not have active record, but instead it
has condition set. This narrows down set of "All invoices" to the "Invoices of client John". We can
execute operation on John's invoices::

   > $invoices->action('count')->getOne();
   => "2"

You do not have to load record in order to traverse further. Try this::

   > $all_lines = $invoices->ref('Lines');

You will get a Line object conditioned to a DataSet corresponding to all invoices of client John. This
time lets calculate total amount of all the invoice lines::

   > $all_lines->action('fx', ['sum', 'total']);
   => "69"

The query used to fetch this value was constructed with our inferred conditions, but also taking into
account that there are no physical "total" field and instead it is a multiplication of `qty` and `price` fields.

Our invoices also have a `due` field, lets see how many invoices are due::

   > $due = clone $invoices;
   > $due->addCondition('due', '>', 0);
   > $due->export(['ref', 'total', 'due']);

This would give you list of due invocies and amount due.

User Actions
------------

ATK Data provides a way to describe User actions. Once described action can be invoked through generic
API, Add-on or UI. Lets find out which user actions `$invoices` offers::

   > $invoices->getActions()

You should see action `register_payment` here as well as description of it's arguments. Lets invoke this action::

   > $invoices->register_payment(30.0);

Now you can re-request list of due invoices::

   > $due->export(['ref', 'total', 'due']);

This time you should see a different picture, since the payment was allocated towards multiple invoices of client 'John'.

Quick UI
========

ATK UI contains enough information about your business model to actually be able to create a very nice
administration system for it. Not only that, but some elements can be used for the client-facing front-end
too with minimum code.

Install Dependencies
--------------------

ATK Data can be complimented by https://github.com/atk4/ui, which can be used in conjunction with any
other meta-framework. Here I'll present just a quick intro focused on building UI for existing data
structure, but for a more comprehensive intro, see https://agile-ui.readthedocs.io/en/latest/quickstart.html.

Use composer::

    composer install atk4/ui

Next create a simple file::

    $app = new \atk4\ui\App();
    $app->dbConnect('mysql://root:root@localhost/atk');

    // Specify which UI layout to use
    $app->initLayout('Centered');

    // Create new Form object
    $form = $app->add('Form');

    // Associate UI component with your model and persistence
    $form->setModel(new Client($app->db));

Opening the page will display a form consistent with the model/field definitions. A generic UI component will
find fields suitable for the form and present them accuratelly with a correct type. No extra files or code
is required.

Try using different views
-------------------------

ATK UI comes with varietty of different views, so try replacing $form creation with this::

    $table = $app->add('Table');
    $client = new Client($app->db);

    // Load existing client
    $client->load(1);

    // Show invoices of specific client inside a table
    $table->setModel($client->ref('Invoices'));

Next relace `Table` with `CRUD` and now your UI should allow you to add, edit and delete records too. Make note
that any new invoices you add will be associated with the client with `id=1`::


    $table = $app->add('Table');
    $client = new Client($app->db);

    // Load existing client
    $client->load(1);

    // Show invoices of specific client inside a table
    $table->setModel($client->ref('Invoices'));

Use Admin layout
----------------

Finally - ATK UI offers a hierarchical approach to rendering UI, so you can easily design layouts::

    $app = new \atk4\ui\App();
    $app->dbConnect('mysql://root:root@localhost/atk');

    // Admin layout offers menu for navigating
    $app->initLayout('Admin');


    // Load existing client
    $client = new Client($app->db);
    $client->load(1);

    $columns = $app->add('Columns');

    // Two column layout
    $c_left = $columns->addColumn();
    $c_right = $columns->addColumn();

    // Show client card on the left and invoices on the right
    $c_left->add('Card')->setModel($client);
    $c_right->add('CRUD')->setModel($client->ref('Invoices'));

Don't forget to authenticate
----------------------------

I leave it as an exercise to you to create authentication for the admin. There is a very good add-on
https://github.com/atk4/login which will make use of a Model to verify user access:

 - require atk4/ui
 - create 'User' model
 - implement auth checking
 - verify login/logout functionality
 - verify password change screen


Quick API
=========

If you need integration with React app or Mobile app, you might need an API. Once again - because ATK Data
models contain some useful information already, it can be linked up with the API end-points directly. Also
due to nature of https://github.com/atk4/api - it is a non-intrusive class, which follow standards and plays
nice with other frameworks.

Install Dependency
------------------

Install using composer::

    composer require atk4/api

Write the code
--------------

Create `api.php` file. You could mod_rewrite all requests into this file or use `api.php/clients/1` style
endpoints, which would work out of the box::

    
    $api = new \atk4\api\Api();

    // Create end-point route for clients
    $api->rest('/clients', new Client($db));

    // Create end-point route for client invoices
    $api->rest('/clients/:client_id/invoices', function($id) use($db) {
        $client = new Client($db);

        return $client->load($id)->ref('Invoices');
    });


Actions, ACL and More
=====================

In a normal situation, your UI code may have to deal with various cases and variance depending on permissions,
object state and more.

With ATK add-ons you can continue to focus your work on ATK Data models and simply have the UI / API reflect
your structure and business rules.

So don't ask "how to add new button to the table" but rather thing in terms "how to add new action to a model".
The benefit is that actions can also be accessed from the APIs if authentication and access control is
configured correctly. You'll learn how to do that as you continue reading this documentation.


Conclusion
==========

In ATK community there is a saying "way of ATK". This refers to an implementation which implements the
requirement with very small amount of effort from developers.

This QuickStart presented only the basics and demonstrated inter-component integration. I recommend that
as you continue to work on your models, keep "UI" and "API"

MasterCRUD Add-on
-----------------

I simply have to mention MasterCRUD add-on (https://github.com/atk4/mastercrud), which is designed to
simplify things even further. This add-on is ideal for Administration Systems and traversing relationships
automatically. I leave it to you to investigate how your entire Admin System code could be even shorter.




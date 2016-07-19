.. _quickstart:

==========
Quickstart
==========

Agile Data Framework is built around some unique concepts. Your knowledge
of other ORM, ActiveRecord and QueryBuilder tools could be helpful, but
you should carefully go through the basics resulting in inefficient use
of Agile Data.

Documentation for Agile Data is organised into chapters where each chapter
is dedicated to provide full documentation on all functionality of Agile
Data.

In this chapter, I'll only introduce you to the basics. You can use the rest
of the documentation as a reference.

.. warning:: If any examples in this guide do not work as expected, this is
    most probably a bug. Please report it so that our team could look into it.

Requirements
============

If you wish to try out some examples in this guide, you will need the following:

- PHP 5.5 or above.
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

Console is using `Psysh <http://psysh.org>`_ to help you interact with objects like this::

    > $db
    => atk4\data\Persistence_SQL {...}

    > exit

.. note:: I recommend that you enter statements into console one-by-one and
    carefully observe results. You should also experement where possible,
    try different conditions or no conditions at all.

    You can always create new model object if you mess up. If you change any
    of the classes, you'll have to restart console.

    There seem to be a bug inside Psysh where it looses MySQL connection,
    in this case restart the console.


Core Concepts
==============

Business Model (see :ref:`Model`)
    You define business logic inside your own classes that extend :php:class:`Model`.
    Each class you create represent one business entity. 

    Model has 3 major characteristic: Business Logic definition, DataSet mapping
    and Active Record.

    See: :php:class:`Model`

Persistence (see :ref:`Persistence`)
    Object representing a connection to database. Linking your Business Model
    to a persistence allows you to load/save individual records as well as
    execute multi-record operations (Actions)

DataSet (see :ref:`DataSet`)
    A set of physical records stored on your database server that correspond
    to the Business Model.

Active Record (see :ref:`Active Record`)
    Model can load individual record from DataSet, work with it and save
    it back into DataSet. While the record is loaded, we call it an Active
    Record.

Action (see :ref:`Action`)
    Operation that Model performs on all of DataSet record without loading
    them individually. Actions have 3 main purposes: data aggregation,
    referencing and multi-record operations.

Persistence Domain vs Business Domain
-------------------------------------

.. image:: images/bd-vs-pd.png

It is very important to understand that there are two "domains" when it comes
to your your data. If you have used ORM, ActiveRecord or QueryBuilders, you 
will be thinking in terms of "Persistence Domain". That means that your you
think in terms of "tables", "fields", "foreign keys" and "group by" operations.

In larger application developers does not necesserily have to know the
details of your database structure. In fact - structure can often change and
code that depend on specific field names or types can break. 

More importantly, if you decide to store some data in different database either
for caching (memcache), unique features (full-text search) or to handle large
amounts of data (BigData) you suddenly have to carefully consider that in
your application.

Business Domain is a layer that is designed to hide all the logic of data
storage and focus on represent your business model in great detail. In other
words - Business Logic is an API you and the rest of your developer team
can use without concerning about data storage.

Agile Data has a rich set of features to define how Business Domain maps
into Persistance Domain. It also allows you to perform most actions with
only knowledge of Business Domain, keeping the rest of your application
independent from your database choice, structure or patterns.

Class vs In-Line definition
---------------------------
Business model in Agile Data is represented through PHP object. While it is
advisable to create each entity in its own class, you do not have to do so. 

It might be handy to use in-line definition of a model. Try the following
inside console::

    $m = new \atk4\data\Model($db, 'contact_info');
    $m->addFields(['address_1','address_2']);
    $m->addCondition('address_1', 'not', null);
    $m->loadAny();
    $m->get();
    $m->action('count')->getOne();

Next, exit and create file `src/Model_ContactInfo.php`::

    <?php
    class Model_ContactInfo extends \atk4\data\Model
    {
        public $table = 'contact_info';
        function init() 
        {
            parent::init();

            $this->addFields(['address_1','address_2']);
            $this->addCondition('address_1','not', null);
        }
    }

Save, exit and run console again. You can now type this::

    $m = new Model_ContactInfo($db);
    $m->loadAny();
    $m->get();

.. note:: Should the "addCondition" be located inside model definition or
    inside your inline code? To answer this question - think - would
    Model_ContactInfo have application without the condition? If yes
    then either use addCondition in-line or create 2 classes.

Model State
-----------

When you create a new model object, you can change it's state to perform
various operations on your data. The state can be braken down into the
following categories:

Persistence
^^^^^^^^^^^

When you first create model using `new Model_` it will just exist as an
independent container. By passing `$db` as a parameter you are also
associating your model with that specific persistence. 

Once model is associated with one persistence, you cannot re-associate it.
Method Model::init() will be executed only after persistence is known, so
that method may make some decision based on chosen persistence. If you need
to store model inside a different persistence, this is achieved by creating
another instance of the same class and copying data over. You must however
remember that any fields that you have added in-line will not be recreated.


DataSet (Conditions)
^^^^^^^^^^^^^^^^^^^^

Model object may have one or several conditions applied. Conditions will
limit which records model can be loaded (made active) and saved. Once the
condition is added, it cannot be removed for safety reasons.

Suppose you have a method that converts DataSet into JSON. Ability to add
conditions is your way to specify which records to operate on::

    function myexport(\atk4\data\Model $m, $fields)
    {
        return json_encode($m->export($fields));
    }
    
    $m = new Model_User($db);
    $m->addCondition('country_id', '2');

    myexport($m, ['id','username','country_id']);

If you want to temporarily add conditions, then you can either clone the
model or use `tryLoadBy`.

Active Record
^^^^^^^^^^^^^

Active Record is a third essential piece of information that your model
stores. You can load / unload records like this::

    $m = new Model_User($db);
    $m->loadAny();

    $m->get();     // inisde console, this will show you what's inside your model

    $m['email'] = 'test@example.com';
    $m->save();

You can call `$m->loaded()` to see if there is active record and `$m->id`
will store the ID of active record. You can also un-load the record with
`$m->unload()`. 

By default no records are loaded and if you modify some field and attempt
to save unloaded model, it will create a new record.

Model may use some default values in order to make sure that your record
will be saved inside DataSet::

    $m = new Model_User($db);
    $m->addCondition('country_id', 2);
    $m['username'] = 'peter';
    $m->save();

    $m->get(); // will show country_id as 2
    $m['country_id'] = 3;
    $m->save();  // will generate exception


Other Parameters
^^^^^^^^^^^^^^^^

Apart from the main 3 pieces of "state" your Model holds there can also be
some other paramaters such as:

 - order
 - limit
 - only_fields

You can also define your own parameters like this::

    $m = new Model_User($db, ['audit'=>false]);

    $m->audit

This can be used internally for all sorts of decisions for model behaviour.


Getting Started
===============

It's time to create the first Model. Open `src/Model_User.php` which
should look like this::

    class Model_User extends \atk4\data\Model
    {
        public $table = 'user';

        function init() {
            parent::init();

            $this->addField('username');
            $this->addField('email');

            $j = $this->join('contact_info', 'contact_info_id');
            $j->addField('address_1');
            $j->addField('address_2');
            $j->addField('address_3');
            $j->hasOne('country_id', 'Country');

        }
    }

Extend either the base Model class or one of your existing classes
(like Model_Client). Define $table unless it is already defined by
parent. All the properties defined inside your model class are
considered "default" you can re-define them when you create model
instances::

    $m = new Model_User($db, 'user2'); // will use a different table

    $m = new Model_User($db, ['table'=>'user2']); // same

.. note:: If you're trying those lines, you will also have to
    create this new table inside your MySQL database::
    
        create table user2 as select * from user:

As I mentioned - init() is called when model is associated with
persistence. You could create model and associate it with persistence
later::

    $m = new Model_User();

    $db->add($m); // calls init()

You cannot add conditions just yet, although you can pass in some
of the defaults::

    $m = new Model_User(['table'=>'user2']);

    $db->add($m); // will use table user2

Adding Fields
-------------

Methods addField() and addFields() can declare model fields. You need
to declare them before you are able to use. You might think that
some SQL reverse-engineering could be good at this point, but this
would mimic your business logic after your presentation logic, while
the whole point of Agile Data is to separate them, so you should,
at least initially, avoid using generators.

In practice, addField() creates a new 'Field' object and then
links it up to your model. This object is used to store some
information about your field but it also participates in some
field-related acitivity.

Table Joins
-----------

Similarly, join() creates a Join object and stores it in
$j. The join object defines a relationship between the master $table and
some other table inside persistence domain. It looks to make sure
relationship is maintained when objects are saved / loaded::

    $j = $this->join('contact_info', 'contact_info_id');
    $j->addField('address_1');
    $j->addField('address_2');

That means that your business model will contain 'address_1' and 'address_2'
fields, but when it comes to storing those values, they will be sent
into a different table and the records should be automatically linked.

Lets once again load up the console for some excercises::

    $m = new Model_User($db);

    $m->loadBy('username','john');
    $m->get();

At this point you'll see that address has also been loaded for the user.
Agile Data makes management of related records transparent. In fact
you can introduce additional joins depending on class. See classes
Model_Invoice and Model_Payment that join table `document` with either
`payment` or `invoice`.

As you load or save models you should see actual queries in the console,
that should give you some idea what kind of information is sent to the
database.

Adding Fields, Joins, Expressions and Relations creates more objects
and 'adds' them into Model (to better understand how Model can behave
like a container for these objects, see `documentation on Agile Core
Containers <http://agile-core.readthedocs.io/en/develop/container.html>`_).
This architecture of
Agile Data allows database persistence to implement different logic that
will properly manipulate features of that specific database engine.


Understanding Persistence
-------------------------

To makes things simple, console has already created persistence 
inside variable `$db`. Load up `console.php` in your editor to look
at how persistence is set up::

    $app->db = new \atk4\data\Persistence::connect($dsn, $user, pass);

    // or

    $app->db = new \atk4\data\Persistence_SQL($pdo, $user, $pass); 

There are several Persistence classes that that deal with different
data sources. Lets load up our console and try out a different
persistence::

    $a=['user'=>[],'contact_info'=>[]];
    $ar = new \atk4\data\Persistence_Array($a);
    $m = new Model_User($ar);
    $m['username']='test';
    $m['address_1']='street'

    $m->save();

    var_dump($a); // shows you stored data

This time our Model_User logic has worked pretty well with Array-only
peristence logic.

.. note:: Persisting into Array or MongoDB are not fully functional as of 1.0 version
    we plan to expand this functionality soon, see our development roadmap.


Relations between Models
========================

Your application normally uses multiple business entities and they can be related
to each-other.

.. warning:: Do not mix-up business model relations with database relations (foreign
    keys). 

Relations are defined by calling hasOne() or hasMany(). You always specify destination
model and you can optionally specify which fields are used for conditioning.

One to Many
-----------

Launch up console again and let's create relationship between 'User' and 'System'.
As per our database design - one user can create multiple 'system' records::

    $m = new Model_User($db);
    $m->hasMany('System');

Next you can load a specific user and traverse into System::

    $m->loadBy('username', 'john');
    $s = $m->ref('System');

Unlike most ORM and ActiveRecord implementations today - instead of returning array
of objects, ref() actually returns another Model to you, however it will add
one extra Condition. This type of reference traversal is called "Active Record to DataSet"
or One to Many.

Your Active Record was user john and after traversal you get a model with DataSet corresponding
to all Systems that belong to user john. You can use the following to see number of records
in DataSet or export DataSet::

    $s->loaded();
    $s->action('count')->getOne();
    $s->export();
    $s->action('count')->getDebugQuery();

Many to Many
------------

Agile Data also supports another type of traversal - 'DataSet to DataSet' or Many to Many::

    $c = $m->ref('System')->ref('Client');

This will create a Model_Client instance with a DataSet corresponding to all the Clients that
are contained in all of the Systems that belong to user john. You can examine the this
model further::

    $c->loaded();
    $c->action('count')->getOne();
    $c->export();
    $c->action('count')->getDebugQuery();

By looking at the code - both MtM and OtM relations are defined with 'hasMany'. The only
difference is the loaded() state of the source model.

Calling ref()->ref() is also called Deep Traversal.

One to One
----------

The third and final reference traversal type is "Active Record to Active Record"::

    $cc = $m->ref('country_id');

This results in an instance of Model_Country with Active Record set to the country of
user john::

    $cc->loaded();
    $cc->id;
    $cc->get();

Actions
=======

Since NoSQL databases will always have some specific features, Agile Data uses the
concept of 'action' to map into vendor-specific operations.

Aggregation actions
-------------------

SQL implements methods such as sum(), count() or max() that can offer you some basic
aggregation without grouping. This type of aggregation provides some specific value from
a data-set. SQL persistence implements some of the operations::

    $m = new Model_Invoice($db);
    $m->action('count')->getOne();
    $m->action('fx', ['sum', 'total'])->getOne();
    $m->action('fx', ['max', 'shipping'])->getOne();

Aggregation actions can be used in Expressions with hasMany relations::

    $m = new Model_Client($db);
    $m->getRef('Invoice')->addField('max_delivery', ['aggregate'=>'max', 'field'=>'shipping']);
    $m->getRef('Payment')->addField('total_paid', ['aggregate'=>'sum', 'field'=>'amount']);
    $m->export(['name','max_delivery','total_paid']);

The above code is more consise and can be used together with relation declaration, although
this is how it works::

    $m = new Model_Client($db);
    $m->addExpression('max_delivery', $m->refLink('Invoice')->action('fx', ['max', 'shipping']));
    $m->addExpression('total_paid', $m->refLink('Payment')->action('fx', ['sum', 'amount']));
    $m->export(['name','max_delivery','total_paid']);

Expression is a special type of read-only Field that uses sub-query instead of a physical field.
Also, refLink() is a special type of reference transition that is designed for use
in sub-queries only.

Field-reference actions
-----------------------

Field referencing allows you to fetch a specific field from related model::

    $m = new Model_Country($db);
    $m->action('field', ['name'])->get();
    $m->action('field', ['name'])->getDebugQuery();

This is useful with hasMany relations::

    $m = new Model_User($db);
    $m->getRef('country_id')->addField('country', 'name');
    $m->loadAny();
    $m->get();  // look for 'country' field

hasMany::addField() again is a short-cut for creating expression, which you can also build
manually::

    $m->addExpression('country', $m->refLink('country_id')->action('field',['name']));

Multi-record actions
--------------------

Actions also allow you to perform operations on multiple records. This can be very
handy with some deep traversal to improve query efficiency. Suppose you need to change
Client/Supplier status to 'suspended' for a specific user. Fire up a concole once
away::

    $m = new Model_User($db);
    $m->loadBy('username','john');
    $m->hasMany('System');
    $c = $m->ref('System')->ref('Client');
    $s = $m->ref('System')->ref('Supplier');

    $c->action('update')->set('status', 'suspended')->execute();
    $s->action('update')->set('status', 'suspended')->execute();

Note that I had to perform 2 updates here, because Agile Data considers Client and
Supplier as separate models. In our implementation they happened to be in a same
table, but technically that could also be implemented differently by persistence
layer. 

Advanced Use of Actions
-----------------------

Actions proove to be very useful in various situations. For instance if
you are looking to add a new user::

    $m = new Model_User($db);
    $m['username'] = 'peter';
    $m['address_1'] = 'street 49';
    $m['country'] = 'UK';
    $m->save();

Normally this would not work, because country is read-only expression, however
if you wish to avoid creating an intermediate select to determine ID for 'UK',
you could do this::

    $m = new Model_User($db);
    $m['username'] = 'peter';
    $m['address_1'] = 'street 49';
    $m['country_id'] = (new Model_Country($db))->addCondition('name','UK')->action('field',['id']);
    $m->save();

This code with $m->ref() will not execute any code, but instead it will provide
expression that will then be used to lookup ID of 'UK' when inserting data into SQL table.

Expressions
===========

Expressions that are defined based on Actions (such as aggregate or field-reference)
will continue to work even without SQL (although might be more perormance-expensive), however
if you're stuck with SQL you can use free-form pattern-based expressions::

    $m = new Model_Client($db);
    $m->getRef('Invoice')->addField('total_purchase', ['aggregate'=>'sum', 'field'=>'total']);
    $m->getRef('Payment')->addField('total_paid', ['aggregate'=>'sum', 'field'=>'amount']);

    $m->addExpression('balance','[total_purchase]+[total_paid]');
    $m->export(['name','balance']);


Conclusion
==========

You should now be familiar with the basics in Agile Data. To find more information on
specific topics, use the rest of the documentaiton.

Agile Data is designed in an extensive pattern - by adding more objects inside Model a new
functionality can be introduced. The described functionality is never a limitation
and 3rd party code or you can add features that Agile Data authors are not even considered.



.. _Persistence:

=============================
Saving and Loading Model Data
=============================

.. php:class:: Model

In order to load and store data of your model inside the database your model should be
"associated" with persistence layer.

Associating with Persistence
============================

Create your persistence object first::

    $db = \atk4\data\Persistence::connect($dsn);

There are several ways to link your model up with the persistence::

    $m = new Model_Invoice($db);

    $m = $db->add(new Model_Invoice());

    $m = $db->add('Invoice');

.. php:method:: load

    Load active record from the DataSet::

        $m->load(10);
        echo $m['name'];

    If record not found, will throw exception.

.. php:method:: tryLoad

    Same as load() but will silently fail if record is not found::

        $m->tryLoad(10);
        $m->set($data);

        $m->save();     // will either create new record or update existing

.. php:method:: loadAny

    Attempt to load any matching record. You can use this in conjunciton with setOrder()::
    
        $m->loadAny();
        echo $m['name'];

.. php:method:: tryLoadAny

    Attempt to load any record, but silently fail if there are no records in the DataSet.

.. php:method:: unload

    Remove active record and restore model to default state::

        $m->load(10);
        $m->unload();

        $m['name'] = 'New User';
        $m->save();         // creates new user

.. php:method:: delete($id = null)

    Remove current record from DataSet. You can optionally pass ID if you wish to delete
    a different record. If you pass ID of a currently loaded record, it will be unloaded.

.. _Action:

Actions
=======

Action is a multi-row operation that will affect all the records inside DataSet. Actions
will not affect records outside of DataSet (records that do not match conditions)

.. php:method:: action($action, $args = [])

    Prepares a special object reperesnting "action" of a persistance layer based around
    your current model::

        $m = Model_User();
        $m->addCondition('last_login', '<', date('Y-m-d', strtotime('-2 months')));

        $m->action('delete')->execute();


Action Types
------------

Actions can be grouped by their result. Some action will be executed and will not
produce any results. Others will respond with either one value or multiple rows of
data.

 - no results
 - single value
 - single row
 - single column
 - array of hashes

Action can be executed at any time and that will return an expected result::

    $m = Model_Invoice();
    $val = $m->action('count')->getOne();

Most actions are sufficiently smart to understand what type of result you are expecting,
so you can have the following code::

    $m = Model_Invoice();
    $val = $m->action('count')();
    
When used inside the same Persistence, sometimes actions can be used without executing::

    $m = Model_Product($db);
    $m->addCondition('name', $product_name);
    $id_query_action = $m->action('getOne',['id']);

    $m = Model_Invoice($db);
    $m->insert(['qty'=>20, 'product_id'=>$id_query_action]);

Insert operation will check if you are using same persistence. If the persistence object
is different, it will execute action and will use result instead.

Being able to embed actions inside next query allows Agile Data to reduce number of
queries issued.

The default action type can be set when executing action, for example::

    $a = $m->action('field', 'user', 'getOne');

    echo $a();   // same as $a->getOne();

SQL Actions
-----------

The following actions are currently supported by Persistence_SQL:

 - select - produces query that returns DataSet  (array of hashes)
 - delete - produces query for deleting DataSet (no result)

The following two queries returns un-populated query, which means if you wish to use
it, you'll have to populate it yourself with some values:

 - insert - produces an un-populated insert query (no result).
 - update - produces query for updating DataSet (no result)

Example of using update::

    $m = Model_Invoice($db);
    $m->addCondition('has_discount', true);

    $m->action('update')
        ->set('has_dicount', false)
        ->execute();

You must be aware that set() operates on a DSQL object and will no longer
work with your model fields. You should use the object like this if you can::

    $m->action('update')
        ->set($m->getElement('has_discount'), false)
        ->execute();

See $actual for more details.

There are ability to execute aggregation functions::

    echo $m->action('fx', ['max', 'salary'])->getOne();

and finally you can also use count::

    echo $m->action('count')->getOne();


SQL Actions on Linked Records
-----------------------------

In conjunction with Model::refLink() you can produce expressions for creating
sub-selects. The functionality is nicely wrapped inside Field_SQL_Many::addField()::

    $client->hasMany('Invoice')
        ->addField('total_gross', ['aggregate'=>'sum', 'field'=>'gross']);

This operation is actually consisting of 3 following operations::

1. Related model is created and linked up using refLink that essentially places
   a condition between $client and $invoice assuming they will appear inside same query.

2. Action is created from $invoice using 'fx' and requested method / field.

3. Expression is created with name 'total_gross' that uses Action.

Here is a way how to intervene with the process::

    $client->hasMany('Invoice');
    $client->addExpression('last_sale', function($m) {
        return $m->refLink('Invoice')
            ->setOrder('date desc')
            ->setLimit(1)
            ->action('field', ['total_gross'], 'getOne');

    });

The code above uses refLink and also creates expression, but it tweaks the action used.

        
Action Matrix
--------------

SQL actions apply the following:

- insert: init, mode
- update: init, mode, conditions, limit, order, hook
- delete: init, mode, conditions
- select: init, fields, conditions, limit, order, hook
- count:  init, field, conditions, hook, 
- field:  init, field, conditions
- fx:     init, field, conditions




.. _DataSet:
.. _conditions:

======================
Conditions and DataSet
======================

.. php:class:: Model

When model is associated with the database, you can specify a default table
either explicitly or through a $table property inside a model::

    $m = new Model_User($db, 'user');
    $m->load(1);
    echo $m['gender'];   // "M"


Following this line, you can load ANY record from the table. It's possible to
narrow down set of "loadable" records by introducing a condition::

    $m = new Model_User($db, 'user');
    $m->addCondition('gender','F');
    $m->load(1);    // exception, user with ID=1 is M

Conditions serve important role and must be used to intelligently restrict
logically accessible data for a model before you attempt the loading.

Basic Usage
===========

.. php:method:: addCondition($field, $operator = null, $value = null)

There are many ways to execute addCondition. The most basic one that will be
supported by all the drivers consists of 2 arguments or if operator is '='::

    $m->addCondition('gender', 'F');         // or
    $m->addCondition('gender', '=', 'F');

Once you add a condition, you can't get rid of it, so if you want
to preserve the state of your model, you need to use clone::

    $m = new Model_User($db, 'user');
    $girls = (clone $m)->addCondition('gender','F');

    $m->load(1);        // success
    $girls->load(1);    // exception

Operations
----------

Most database drivers will support the following additional operations::

    >, <, >=, <=, !=, in, not in

The operation must be specified as second argument::

    $m = new Model_User($db, 'user');
    $girls = (clone $m)->addCondition('gender', 'F');
    $not_girls = (clone $m)->addCondition('gender', '!=', 'F');

When you use 'in' or 'not in' you should pass value as array::

    $m = new Model_User($db, 'user');
    $girls_or_boys = (clone $m)->addCondition('gender', 'in', ['F', 'M']);

Multiple Conditions
-------------------

You can set multiple conditions on the same field even if they are contradicting::

    $m = new Model_User($db, 'user');
    $noone = (clone $m)
        ->addCondition('gender', 'F')
        ->addCondition('gender', 'M');

Normally, however, you would use a different fields::

    $m = new Model_User($db, 'user');
    $girl_sue = (clone $m)
        ->addCondition('gender', 'F')
        ->addCondition('name', 'Sue');

You can have as many conditions as you like.

Adding OR Conditions
--------------------

In Agile Data all conditions are additive. This is done for security - no matter
what condition you are adding, it will not allow you to circumvent previously
added condition.

You can, however, add condition that contains multiple clauses joined with OR
operator::

    $m->addCondition([
        ['name', 'John'],
        ['surname', 'Smith']
    ]);

This will add condition that will match against records with either
name=John OR surname=Smith.
If you are building multiple conditions against the same field, you can use this
format::

    $m->addCondition('name', ['John', 'Joe']);

For all other cases you can implement them with :php:meth:`Model::expr`::

    $m->addCondition($m->expr("(day([birth_date]) = day([registration_date]) or day([birth_date]) = [])", 10));

This rather unusual condition will show user records who have registered on same
date when they were born OR if they were born on 10th. (This is really silly
condition, please don't judge, if you have a better example, I'd love to hear).

Defining your classes
---------------------

Although I have used in-line addition of the arguments, normally you would want
to set those conditions inside the init() method of your model::


    class Model_Girl extends Model_User
    {
        function init()
        {
            parent::init();

            $this->addCondition('gender', 'F');
        }
    }

Note that the field 'gender' should be defined inside Model_User::init().

Vendor-dependent logic
======================

There are many other ways to set conditions, but you must always check if they
are supported by the driver that you are using.

Field Matching
-------------

Supported by: SQL   (planned for Array, Mongo)

Usage::

    $m->addCondition('name', $m->getElement('surname'));

Will perform a match between two fields.


Expression Matching
-------------------

Supported by: SQL   (planned for Array)

Usage::

    $m->addCondition($m->expr('[name] > [surname]');

Allow you to define an arbitrary expression to be used with fields. Values
inside [blah] should correspond to field names.


SQL Expression Matching
-------------------

.. php:method:: expr($expression, $arguments = [])

    Basically is a wrapper to create DSQL Expression, however this will find any
    usage of identifiers inside the template that do not have a corresponding
    value inside $arguments and replace it with the field::

        $m->expr('[age] > 20'); // same as
        $m->expr('[age] > 20', ['age'=>$m->getElement('age')); // same as



Supported by: SQL

Usage::

    $m->addCondition($m->expr('[age] between [min_age] and [max_age]'));

Allow you to define an arbitrary expression using SQL language.


Custom Parameters in Expressions
--------------------------------

Supported by: SQL

Usage::

    $m->addCondition(
        $m->expr('[age] between [min_age] and [max_age]'),
        ['min_age'=>10, 'max_age'=>30]
    );

Allow you to pass parameters into expressions. Those can be nested and consist
of objects as well as actions::


    $m->addCondition(
        $m->expr('[age] between [min_age] and [max_age]'),
        [
            'min_age'=>$m->action('min', ['age']),
            'max_age'=>$m->expr('(20 + [])', [20])
        ]
    );

This will result in the following condition:

.. code-block:: sql

    WHERE
        `age` between
            (select min(`age`) from `user`)
            and
            (20 + :a)

where the other 20 is passed through parameter. Refer to
http://dsql.readthedocs.io/en/develop/expressions.html for full documentation
on expressions.


Expression as first argument
----------------------------

Supported by: SQL, (Planned: Array, Mongo)

The $field of addCondition() can be passed as either an expression or any
object implementing atk4\dsql\Expressionable interface. Same logic applies
to the $value::

    $m->addCondition($m->getElement('name'), '!=', $this->getElement('surname'));


Using withID
============

.. php:method:: withID($id)

This method is similar to load($id) but instead of loading the specified record,
it sets condition for ID to match. Technically that saves you one query if you
do not need actual record by are only looking to traverse::

    $u = new Model_User($db);
    $books = $u->withID(20)->ref('Books');


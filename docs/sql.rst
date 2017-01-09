
.. _SQL:

==============
SQL Extensions
==============

Databases that support SQL language can use :php:class:`Persistence_SQL`. This driver will
format queries to the database using SQL language.

In addition to normal operations you can extend and customise various queries.

Default Model Classes
=====================

When using Persistence_SQL model building will use different classes for fields, expressions, joins etc:

 - addField - :php:class:`Field_SQL` (field can be used as part of DSQL Expression)
 - hasOne - :php:class:`Reference_SQL_One` (allow importing fields)
 - addExpression - :php:class:`Field_SQL_Expression` (define expression through DSQL)
 - join - :php:class:`Join_SQL` (join tables query-time)


SQL Field
---------

.. php:class:: Field_SQL

.. php:attr:: actual

    :php:class:`Persistence_SQL` supports field name mapping. Your field could have
    different column name in your schema::

        $this->addField('name', ['actual'=>'first_name']);

    This will apply to load / save operations as well as query mapping.

.. php:method:: getDSQLExpression

    SQL Fields can be used inside other SQL expressions::

        $q = new \atk4\dsql\Expression('[age] + [birth_year]', [
                'age'        => $m->getElement('age'),
                'birth_year' => $m->getElement('birth_year'),
            ]);

SQL Reference
-------------

.. php:class:: Reference_SQL_One

    Extends :php:class:`Reference_One`

.. php:method:: addField

    Allows importing field from a referenced model::

        $model->hasOne('country_id', new Country())
            ->addField('country_name', 'name');

    Second argument could be array containing additional settings for the field::

        $model->hasOne('account_id', new Account())
            ->addField('account_balance', ['balance', 'type'=>'money']);

    Returns new field object.

.. php:method:: addFields

    Allows importing multiple fields::

        $model->hasOne('country_id', new Country())
            ->addFields(['country_name', 'country_code']);

    You can specify defaults to be applied on all fields::

        $model->hasOne('account_id', new Account())
            ->addFields([
                'opening_balance',
                'balance'
            ], ['type'=>'money']);

    You can also specify aliases::

        $model->hasOne('account_id', new Account())
            ->addFields([
                'opening_balance',
                'account_balance'=>'balance'
            ], ['type'=>'money']);

    If you need to pass more details to individual field, you can also use sub-array::

        $model->hasOne('account_id', new Account())
            ->addFields([
            [
                ['opening_balance', 'ui'=>['caption'=>'The Opening Balance']],
                'account_balance'=>'balance'
            ], ['type'=>'money']);

    Returns $this.

.. php:method:: ref

    While similar to :php:meth:`Reference_One::ref` this implementation implements deep traversal::

        $country_model = $customer_model->addCondition('is_vip', true)
            ->ref('country_id');           // $model was not loaded!

.. php:method:: refLink

    Creates a model for related entity with applied condition referencing field of a current model
    through SQL expression rather then value. This is usable if you are creating sub-queries.

.. php:method:: addTitle

    Similar to addField, but will import "title" field and will come up with good name for it::

        $model->hasOne('country_id', new Country())
            ->addTitle();

        // creates 'country' field as sub-query for country.name

    You may pass defaults::

        $model->hasOne('country_id', new Country())
            ->addTitle(['ui'=>['caption'=>'Country Name']]);

    Returns new field object.

.. php:method:: withTitle

    Similar to addTitle, but returns $this.

Expressions
-----------

.. php:class:: Field_SQL_Expression

    Extends :php:class:`Field_SQL`

Expression will map into the SQL code, but will perform as read-only field otherwise. 

.. php:attr:: expr

    Stores expression that you define through DSQL expression::

        $model->addExpression('age', 'year(now())-[birth_year]');
        // tag [birth_year] will be automatically replaced by respective model field

.. php:method:: getDSQLExpression

    SQL Expressions can be used inside other SQL expressions::

        $model->addExpression('can_buy_alcohol', ['if([age] > 25, 1, 0)', 'type'=>'boolean']);

Adding expressions to model will make it automatically reload itself after save as default behaviour, see :php:attr:`Model::reload_after_save`.

Transactions
============

.. php:class:: Persistence_SQL

.. php:method:: atomic

This method allows you to execute code within a 'START TRANSACTION / COMMIT' block::

    class Invoice {

        function applyPayment(Payment $p) {

            $this->persistence->atomic(function() use ($p) {

                $this['paid'] = true;
                $this->save();

                $p['applied'] = true;
                $p->save();

            });

        }
    }

Callback format of this method allows a more intuitive syntax and nested execution of various blocks. If
any exception is raised within the block, then transaction will be automatically rolled back. The return of
atomic() is same as return of user-defined callback.

Custom Expressions
==================

.. php:method:: expr

    This method is also injected into the model, that is associated with Persistence_SQL so the most convenient
    way to use this method is by calling `$model->expr('foo')`.

This method is quite similar to \atk4\dsql\Query::expr() method explained here: http://dsql.readthedocs.io/en/stable/expressions.html

There is, however, one difference. Expression class requires all named arguments to be specified. Use of Model::expr() allows you
to specify field names and those field expressions will be automatically substituted. Here is long / short format::

    $q = new \atk4\dsql\Expression('[age] + [birth_year]', ['age'=>$m->getElement('age'), 'birth_year'=>$m->getElement('birth_year')]);

    // identical to

    $q = $m->expr('[age] + [birth_year']);

This method is automatically used by :php:class:`Field_SQL_Expression`.


Actions
=======

The most basic action you can use with SQL persistence is 'select'::

    $action = $model->action('select');

Action is implemented by DSQL library, that is further documented at http://dsql.readthedocs.io (See section Queries).


Action: select
--------------

This action returns a basic select query. You may pass one argument - array containing list of fields::

    $action = $model->action('select', ['name', 'surname']);
    
Passing false will not include any fields into select (so that you can include them yourself)::

    $action = $model->action('select', [false]);
    $action->field('count(*)', 'c);


Action: insert
--------------

Will prepare query for performing insert of a new record.

Action: update, delete
----------------------

Will prepare query for performing update or delete of records. Applies conditions set.

Action: count
-------------

Returns query for `count(*)`::

    $action = $model->action('count');
    $cnt = $action->getOne();

You can also specify alias::

    $action = $model->action('count', ['alias'=>'cc']);
    $data = $action->getRow();
    $cnt = $data['cc'];

Action: field
-------------

Get query for a specific field::

    $action = $model->action('field', ['age']);
    $age = $action->limit(1)->getOne();

You can also specify alias::

    $action = $model->action('field', ['age', 'alias'=>'the_age']]);
    $age = $action->limit(1)->getRow()['the_age'];

Action: fx
----------

Executes single-argument SQL function on field::

    $action = $model->action('fx', ['avg', 'age']);
    $avg_age = $action->getOne();

This method also supports alias. Use of alias is handy if you are using those actions as part of other query (e.g. UNION)

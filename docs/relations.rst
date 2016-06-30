
=========
Relations
=========

.. php:class:: Model

Models can relate one to another. The logic of traversing relations, however, is
slightly different to the traditional ORM implementation, because in Agile Data
traversing also imposes :ref:`conditions`

There are two basic types of relations: hasOne() and hasMany(). You need to define
relation between the models before you can traverse it, which you can do either
inside the init() method or anywhere else::


    $m = new Model_User($db, 'user');
    $m->hasMany('Orders', new Model_Order());
    $m->load(1);

    $orders_for_user_1 = $m->ref('Orders');

As mentioned - $orders_for_user_1 will have it's DataSet automatically adjusted
so that you could only access orders for the user with ID=1. The following is
also possible::

    $m = new Model_User($db, 'user');
    $m->hasMany('Orders', new Model_Order());
    $m->addCondition('is_vip', true);

    $orders_for_vips = $m->ref('Orders');
    $orders_for_vips->loadAny();

Condition on the base model will be carried over to the orders and you will
only be able to access orders that belong to vip users. The query for loading
order will look like this::

    select * from order where user_id in (
        select id from user where is_vip = 1
    ) limit 1

Persistence
-----------

Agile Data supports traversal between persistences. The code above does not
explicitly assign database to Model_Order, therefore it will be associated
with the $db when traversing.

You can specify a different database though::

    $m = new Model_User($db_array_cache, 'user');
    $m->hasMany('Orders', new Model_Order($db_sql));
    $m->addCondition('is_vip', true);

    $orders_for_vips = $m->ref('Orders');

Now that a different databases are used, the queries can no longer be
joined so Agile Data will carry over list of IDs instead::

    $ids = select id from user where is_vip = 1
    select * from order where user_id in ($ids)

Since we are using ``$db_array_cache``, then field values will actually
be retrieved from memory.

Safety and Performance
----------------------

When using ref() on hasMany relation, it will always return a fresh clone
of the model. You can perform actions on the clone and next time you execute
ref() this will not be impcated. If you performing traversals inside
iterations, this can cause performance issues, for this reason you should
see refLink()




TODO: add hasOne() here


hasMany Relation
================

.. php:method:: hasMany($link, $model);

There are several ways how to link models with hasMany::

    $m->hasMany('Orders', new Model_Order());  // using object

    $m->hasMany('Order', function($m, $r) {    // using callback
        return new Model_Order();
    });

    $m->hasMany('Order');                      // will use factory new Model_Order


Dealing with many-to-many relations
-----------------------------------

It is possible to perform relation through an 3rd party table::

    $i = new Model_Invoice();
    $p = new Model_Payment();

    // table invoice_payment has 'invoice_id', 'payment_id' and 'amount_allocated'

    $p
        ->join('invoice_payment.payment_id')
        ->addFields(['amount_allocated','invoice_id']);

    $i->hasMany('Payments', $p);

Now you can fetch all the payments associated with the invoice through::

    $payments_for_invoice_1 = $i->load(1)->ref('Payments');

Dealing with NON-ID fields
--------------------------

Sometimes you have to use non-ID relations. For example we might have two models
describing list of currencies and for each currency we might have historic rates
available. Both models will relate throug ``currency.code = exchange.currency_code``::

    $c = new Model_Currency();
    $e = new Model_ExchangeRate();

    $c->hasMany('Exchanges', [$e, 'their_field'=>'currency_code', 'our_field'=>'code']);

    $c->addCondition('is_convertable',true);
    $e = $c->ref('Exchanges');

This will produce the following query::

    select * from exchange 
    where currency_code in 
        (select code form currency wehre is_convertable=1)


hasMany / refLink
=================

.. php:method:: refLink($link)

Normally ref() will return a usable model back to you, however if you use refLink then
the conditioning will be done differently. refLink is useful when defining
sub-queries::

    $m = new Model_User($db_array_cache, 'user');
    $m->hasMany('Orders', new Model_Order($db_sql));
    $m->addCondition('is_vip', true);

    $sum = $m->refLink('Orders')->action('sum', ['amount']);
    $m->addExpression('sum_amount')->set($action);

The refLink would define a condition on a query like this::

    select * from `order` where user_id = `user`.id

And it will not be viable on its own, however if you use it inside a sub-query,
then it now makes sense for generating expression::

    select 
        (select sum(amount) from `order` where user_id = `user`.id) sum_amount
    from user
    where is_vip = 1


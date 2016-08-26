
.. _Relations:

=========
Relations
=========

.. php:class:: Model

.. php:method:: ref($link, $details = []);

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
only be able to access orders that belong to VIP users. The query for loading
order will look like this::

    select * from order where user_id in (
        select id from user where is_vip = 1
    ) limit 1

Argument $defaults will be passed to the new model that will be used for
relation. This will not work if you have specified relation as existing
model that has a persistence set.

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
ref() this will not be impacted. If you performing traversals inside
iterations, this can cause performance issues, for this reason you should
see refLink()



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
available. Both models will relate through ``currency.code = exchange.currency_code``::

    $c = new Model_Currency();
    $e = new Model_ExchangeRate();

    $c->hasMany('Exchanges', [$e, 'their_field'=>'currency_code', 'our_field'=>'code']);

    $c->addCondition('is_convertable',true);
    $e = $c->ref('Exchanges');

This will produce the following query::

    select * from exchange
    where currency_code in
        (select code form currency where is_convertable=1)


Add Aggregate Fields
--------------------

Relation hasMany makes it a little simpler for you to define an aggregate fields::

    $u = new Model_User($db_array_cache, 'user');

    $u->hasMany('Orders', new Model_Order())
        ->addField('amount', ['aggregate'=>'sum']);

It's important to define aggregation functions here. This will add another field
inside ``$m`` that will correspond to the sum of all the orders. Here is another
example::

    $u->hasMany('PaidOrders', (new Model_Order())->addCondition('is_paid', true))
        ->addField('paid_amount', ['aggregate'=>'sum', 'field'=>'amount']);

You can also define multiple fields, although you must remember that this will
keep making your query bigger and bigger::

    $invoice->hasMany('Invoice_Line', new Model_Invoice_Line())
        ->addFields([
            ['total_vat', 'aggregate'=>'sum'],
            ['total_net', 'aggregate'=>'sum'],
            ['total_gross', 'aggregate'=>'sum'],
        ]);


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

hasOne relation
===============

.. php:method:: hasOne($link, $model)

    $model can be an array containing options: [$model, ...]


This relation allows you to attach a related model to a foreign key::

    $o = new Model_Order($db, 'order');
    $u = new Model_User($db, 'user');

    $o->hasOne('user_id', $u);

The relation is similar to hasMany, but it does behave slightly different. Also this
relation will define a system new field ``user_id`` if you haven't done so already.


Traversing loaded model
-----------------------

If your ``$o`` model is loaded, then traversing into user will also load the user,
because we specifically know the ID of that user. No conditions will be set::

    echo $o->load(3)->ref('user_id')['name'];   // will show name of the user, of order #3

Traversing DataSet
------------------

If your model is not loaded then using ref() will traverse by conditioning DataSet of the
user model::

    $o->unload(); // just to be sure!
    $o->addCondition('status', 'failed');
    $u = $o->ref('user_id');


    $u->loadAny();  // will load some user who has at least one failed order

The important point here is that no additional queries are generated in the process and
the loadAny() will look like this::

    select * from user where id in
        (select user_id from order where status = 'failed')

By passing options to hasOne() you can also differentiate field name::

    $o->addField('user_id');
    $o->hasOne('User', [$u, 'our_field'=>'user_id']);

    $o->load(1)->ref('User')['name'];

You can also use ``their_field`` if you need non-id matching (see example above for hasMany()).

Importing Fields
----------------

You can import some fields from related model. For example if you have list of invoices, and
each invoice contains "currency_id", but in order to get the currency name you need another
table, you can use this syntax to easily import the field::

    $i = new Model_Invoice($db)
    $c = new Model_Currency($db);

    $i->hasOne('currency_id', $c)
        ->addField('currency_name', 'name');


This code also resolves problem with a duplicate 'name' field. Since you might have a 'name' field
inside 'Invoice' already, you can name the field 'currency_name' which will reference 'name' field inside
Currency. You can also import multiple fields but keep in mind that this may make your query much longer.
The argument is associative array and if key is specified, then the field will be renamed, just as we
did above::

    $u = new Model_User($db)
    $a = new Model_Address($db);

    $u->hasOne('address_id', $a)
        ->addFields([
            'address_1',
            'address_2',
            'address_3',
            'address_notes'=>['notes', 'type'=>'text']
        ]);
Above, all ``address_`` fields are copied with the same name, however field 'notes' from Address model
will be called 'address_notes' inside user model.

Relation Discovery
==================

You can call getRefs() to fetch all the references of a model::

    $refs = $model->getRefs();
    $ref = $refs['owner_id'];

or if you know the reference you'd like to fetch, you can use getRef()::

    $ref = $model->getRef('owner_id');

While ref() returns a related model, getRef gives you the reference object itself so that you could
perform some changes on it, such as import more fields with addField()


Deep traversal
==============

.. warning:: NOT IMPLEMENTED

When operating with data-sets you can define relations that use deep traversal::

    $o = new Model_Order($db);
    $o->hasOne('user_id', new Model_User())
        ->hasOne('address_id', new Model_Address());

    echo $o->load(1)->ref('user_id/address_id')['address_1'];

The above example will actually perform 3 load operations, because as I have explained above,
ref() loads related model when called on a loaded model. To perform a single query instead,
you can use::

    echo $o->id(1)->ref('user_id/address_id')->loadAny()['address_1'];

Here ``id()`` will only set a condition without actually loading the record and traversal
will encapsulate sub-queries resulting in a query like this::

    select * from address where id in
        (select address_id from user where id in
            (select user_id from order where id=1 ))


Relation Aliases
================

When related entity relies on the same table it is possible to run into problem when SQL is
confused about which table to use.

.. code-block:: sql

    select name, (select name from item where item.parent_id = item.id) parent_name from item

To avoid this problem Agile Data will automatically alias tables in sub-queries. Here is how
it works::

    $item->hasMany('parent_item_id', new Model_Item())
        ->addField('parent', 'name');

When generating expression for 'parent', the sub-query will use alias ``pi`` consisting of
first letters in 'parent_item_id'. (except _id). You can actually specify a custom table alias
if you want::

    $item->hasMany('parent_item_id', [new Model_Item(), 'table_alias'=>'mypi'])
        ->addField('parent', 'name');

Additionally you can pass table_alias as second argument into ref() or refLink(). This can
help you in creating a recursive models that relate to itself. Here is example::

    class Model_Item3 extends \atk4\data\Model {
        public $table='item';
        function init() {
            parent::init();

            $m = new Model_Item3();

            $this->addField('name');
            $this->addField('age');
            $i2 = $this->join('item2.item_id');
            $i2->hasOne('parent_item_id', [$m, 'table_alias'=>'parent'])
                ->addTitle();

            $this->hasMany('Child', [$m, 'their_field'=>'parent_item_id', 'table_alias'=>'child'])
                ->addField('child_age',['aggregate'=>'sum', 'field'=>'age']);
        }
    }

Loading model like that can produce a pretty sophisticated query

.. code-block:: sql

    select
        `pp`.`id`,`pp`.`name`,`pp`.`age`,`pp_i`.`parent_item_id`,
        (select `parent`.`name`
         from `item` `parent`
         left join `item2` as `parent_i` on `parent_i`.`item_id` = `parent`.`id`
         where `parent`.`id` = `pp_i`.`parent_item_id`
         ) `parent_item`,
        (select sum(`child`.`age`) from `item` `child`
         left join `item2` as `child_i` on `child_i`.`item_id` = `child`.`id`
         where `child_i`.`parent_item_id` = `pp`.`id`
        ) `child_age`,`pp`.`id` `_i`
    from `item` `pp`left join `item2` as `pp_i` on `pp_i`.`item_id` = `pp`.`id`

Relations with New Records
==========================

Agile Data takes extra care to help you link your new records with new related entities.
Consider the following two models::

    class Model_User extends \atk4\data\Model {
        public $table = 'user';
        function init() {
            parent::init();
            $this->addField('name');

            $this->hasOne('contact_id', new Model_Contact());
        }
    }

    class Model_Contact extends \atk4\data\Model {
        public $table = 'contact';
        function init() {
            parent::init();

            $this->addField('address');
        }
    }

This is a classic one to one relation, but let's look what happens when you are working with
a new model::

    $m = new Model_User($db);

    $m['name'] = 'John';
    $m->save();

In this scenario, a new record will be added into 'user' with 'contact_id' equal to null. The
next example will traverse into the contact to set it up::

    $m = new Model_User($db);

    $m['name'] = 'John';
    $m->ref('address_id')->save(['address'=>'street']);
    $m->save();

When entity which you have referenced through ref() is saved, it will automatically populate
$m['contact_id'] field and the final $m->save() will also store the reference.

ID setting is implemented through a basic hook. Related model will have afterSave
hook, which will update address_id field of the $m.



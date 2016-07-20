

.. _Expressions:

===========
Expressions
===========

.. php:class:: Model

You already know that you can define fields inside your Model with addField. While
a regular field maps to physical field inside your database, sometimes you want
to do something different - execute expression or function inside SQL and use
result as an output.

Expressions solve this problem by adding a read-only field to your model that
corresponds to an expression:

.. php:method:: addExpression($link, $model);

Example will calculate "total_gross" by adding up values for "net" and "vat"::

    $m = new Model_Invoice($db);
    $m->addFields(['total_net', 'total_vat']);

    $m->addExpression('total_gross', '[total_net]+[total_vat]');
    $m->load(1);

    echo $m['total_gross'];

The query using during load() will look like this::

    select 
        `id`,`total_net`,`total_vat`,
        (`total_net`+`total_vat`) `total_gross` 
    from `invoice`',

Defining Expression
-------------------

The simplest format to define expression is by simply passing a string. The
argument is executed through Model::expr() which automatically substitutes
values for the other fields including other expressions. 

There are other ways how you can specify expression::

    $m->addExpression('total_gross', 
        $m->expr('[total_net]+[total_vat] + [fee]', ['fee'=>$fee])
    );

This format allow you to supply additional parametetrs inside expression.
You should always use parameters instead of appending values inside your
expression string (for safety)

You can also use expressions to pass a select action for a specific field::

No-table Model Expression
-------------------------

Agile Data allows you to define a model without table. While this may have
no purpose initially, it does come in handy in some cases, when you need
to unite a few statistical queries. Let's start by looking a at a very
basic example::

    $m = new Model($db, false);
    $m->addExpression('now', 'now()');
    $m->loadAny();
    echo $m['now'];

In this example the query will look like this::

    select (1) `id`, (now()) `now` limit 1

so that ``$m->id`` will always be 1 which will make it a model that you can
actually use consistently throughout the system. The real benefit from this
can be gained when you need to pull various statistical values from your
database at once::

    $m = new Model($db, false);
    $m->addExpression('total_orders', (new Model_Order($db))->action('count'));
    $m->addExpression('total_payments', (new Model_Payment($db))->action('count'));
    $m->addExpression('total_received', (new Model_Payment($db))->action('sum', ['amount']));

    $data = $m->loadAny()->get();

Of course you can also use a DSQL for this::

    $q = $db->dsql();
    $q->field(new Model_Order($db)->action('count'), 'total_orders');
    $q->field(new Model_Payment($db)->action('count'), 'total_orders');
    $q->field(new Model_Payment($db)->action('fx', ['sum', 'amount']), 'total_received');
    $data = $q->getRow();

You can decide for yourself based on circumstances.

Expression Callback
-------------------

You can use a callback method when defining expression::

    $m->addExpression('total_gross', function($m, $q) {
        return '[total_net]+[total_vat]';
    });


.. _Utilities:

=========
Utilities
=========

\atk4\data\Util is a namespace containing some valuable extensions that could be
very useful when working with ATK Data, but may not always be needed.


DeepCopy
========

.. php:class:: DeepCopy

.. php:method:: from
.. php:method:: to
.. php:method:: copy
.. php:method:: with

DeepCopy implements copying of structures with related records. First lets look at
example:

 - Client hasMany Invoices
 - Invoice hasMany Lines
 - Client with id=`10` and name 'John' needs to be duplicated along with related Invoices and Lines.

If you wished to implement this manually, you would have to recursively create data
and also maintain relationship. This is hard but with `DeepCopy` you can do it easily::

   use \atk4\data\Util\DeepCopy;


   $client = new Client($db;)
   $client->load(10);

   $dc = new DeepCopy();

   $dc->from($client);
   $dc->to(new Client());
   $dc->with([
      'Invoices'=> [
         'Lines'
      ],
   ]);
   $dc->copy();

During a lifecycle of a deep copy object, you may call various methods to configure
the operation then execute `copy()`.


Object Reference Mapping
------------------------

Lets extend previous example by adding Client hasMany Payments. Lets also allocate
payment to invoices: Payment hasMany Allocations, Invoice hasMany Allocations.

Now our setup includes many-to-many relationship::

   Invoice -< Allocation >- Payment

To copy this structrue, you would normally copy Invoices first, then Payments and
when you copy allocations, you would have to `map` old "invoice_id" values to the
new ones. Fortunatelly that is also implemented by DeepCopy::

   $dc = new DeepCopy();

   $dc->from($client);
   $dc->to(new Client());
   $dc->with([
      'Invoices'=> [
         'Lines'
      ],
      'Payments' => [
         'Allocations'=> [
            'invoice_id'
         ]
      ],
   ]);
   $dc->copy();

DeepCopy will go over the array you supply with line by line and every object which
is copied will also be recorded (old=>new id). If later the reference to object
is found, instead of copying it once again, it will be mapped.

Copying multiple times
----------------------

You an invoke `$dc->copy()` multiple times. Between the executions you can call
`from()` or `to()` or `with()`::

   $dc->to(new Client());
   $dc->from($client1)->copy();
   $dc->from($client2)->copy();

Copying to a different model
----------------------------

If you use similar models, you can copy object from one into another, for instance
if you have "Quote" defined like this::

   $quote->hasMany('Lines', QuoteLine::class);

and another object "Invoice"::

   $invoice->hasMany('Lines', InvoiceLine::class);

You can perform a deep copy of Quote into Invoice::

   $dc->from($quote);
   $dc->to(new Invoice());
   $dc->with(['Lines']);

   $dc->copy();

Using different field values
----------------------------

.. php:method:: excluding

Normally when you copy record, it keeps all the field values as-is, except for "id"
which will receive a new value from persistence. However, in some cases, you would
want to set a different value::

   $old = new Invoice($db);
   $old->load(10);

   $new = new Invoice();
   $new['name'] = 'Copy of '.$old['name'];

   $dc
      ->from($old)
      ->to($new)
      ->with(['Lines']);

   $dc->excluding(['name']);
   $dc->copy();

Using `exclude` here will skip a field and the current value will be used instead.

Copying into different persistence
----------------------------------

DeepCopy works perfectly across multiple persistences. Suppose you want to cache
your invoice data::

   $old = new Invoice($slow_db);
   $old->load(10);

   $dc
      ->from($old)
      ->to(new Invoice($cache_db))
      ->with(['Lines'])
      ->copy();
   

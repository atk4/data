
.. _Aggregates:

================
Model Aggregates
================

.. php:namespace:: Atk4\Data\Model

.. php:class:: Aggregate

In order to create model aggregates the Aggregate model needs to be used:

Grouping
--------

Aggregate model can be used for grouping::

   $aggregate = $orders->groupBy(['country_id']);

`$aggregate` above is a new object that is most appropriate for the model's persistence and which can be manipulated 
in various ways to fine-tune aggregation. Below is one sample use::

   $aggregate = $orders->withAggregateField('country')->groupBy(['country_id'], [
         'count' => ['expr' => 'count(*)', 'type' => 'integer'],
         'total_amount' => ['expr' => 'sum([amount])', 'type' => 'money']
      ],
   );

   // $aggregate will have following rows:
   // ['country'=>'UK', 'count'=>20, 'total_amount'=>123.20];
   // ..

Below is how opening balance can be built::

   $ledger = new GeneralLedger($db);
   $ledger->addCondition('date', '<', $from);
   
   // we actually need grouping by nominal
   $ledger->groupBy(['nominal_id'], [
      'opening_balance' => ['expr' => 'sum([amount])', 'type' => 'money']   
   ]);


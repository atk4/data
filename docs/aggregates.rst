
.. _Aggregates:

================
Model Aggregates
================

.. php:namespace:: atk4\data\Model

.. php:class:: Aggregate

In order to create model aggregates the Aggregate model needs to be used:

Grouping
--------

Aggregate model can be used for grouping::

   $orders->add(new \atk4\data\Model\Aggregate());

   $aggregate = $orders->action('group');

`$aggregate` above will return a new object that is most appropriate for the model persistence and which can be manipulated 
in various ways to fine-tune aggregation. Below is one sample use::

   $aggregate = $orders->action(
     'group',
     'country_id', 
     [
       'country',
       'count'=>'count',
       'total_amount'=>['sum', 'amount']
     ],
   );
   
   foreach($aggregate as $row) {
     var_dump(json_encode($row));
     // ['country'=>'UK', 'count'=>20, 'total_amount'=>123.20];
     // ..
   }

Below is how opening balance can be build::

   $ledger = new GeneralLedger($db);
   $ledger->addCondition('date', '<', $from);
   
   // we actually need grouping by nominal
   $ledger->add(new \atk4\data\Model\Aggregate());
   $byNominal = $ledger->action('group', 'nominal_id');
   $byNominal->addField('opening_balance', ['sum', 'amount']);
   $byNominal->join()
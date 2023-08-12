
.. _Unions:

============
Model Unions
============

.. php:namespace:: Atk4\Data\Model

.. php:class:: UnionModel

In some cases data from multiple models need to be combined. In this case the UnionModel model comes very handy.
In the case used below Client model schema may have multiple invoices and multiple payments. Payment is not related to the invoice.::

   class Client extends \Atk4\Data\Model {
        public $table = 'client';

        protected function init(): void
        {
            parent::init();

            $this->addField('name');

            $this->hasMany('Payment');
            $this->hasMany('Invoice');
        }
   }

(see tests/ModelUnionTest.php, tests/Model/Client.php, tests/Model/Payment.php and tests/Model/Invoice.php files).

Union Model Definition
----------------------

Normally a model is associated with a single table. Union model can have multiple nested models defined and it fetches
results from that. As a result, Union model will have no "id" field. Below is an example of inline definition of Union model.
The Union model can be separated in a designated class and nested model added within the init() method body of the new class::

   $unionPaymentInvoice = new \Atk4\Data\Model\Union();

   $nestedPayment = $unionPaymentInvoice->addNestedModel(new Invoice());
   $nestedInvoice = $unionPaymentInvoice->addNestedModel(new Payment());

Next, assuming that both models have common fields "name" and "amount", `$unionPaymentInvoice` fields can be set::

   $unionPaymentInvoice->addField('name');
   $unionPaymentInvoice->addField('amount', ['type' => 'atk4_money']);

Then data can be queried::

   $unionPaymentInvoice->export();

Define Fields
------------------

Below is an example of 3 different ways to define fields for the UnionModel model::

   // Will link the "name" field with all the nested models.
   $unionPaymentInvoice->addField('client_id');

   // Expression will not affect nested models in any way
   $unionPaymentInvoice->addExpression('name_capital', ['expr' => 'upper([name])']);

   // UnionModel model can be joined with extra tables and define some fields from those joins
   $unionPaymentInvoice
      ->join('client', 'client_id')
      ->addField('client_name', 'name');

:ref:`Expressions` and :ref:`Joins` are working just as they would on any other model.

Field Mapping
-------------

Sometimes the field that is defined in the UnionModel model may be named differently inside nested models.
E.g. Invoice has field "description" and payment has field "note".
When defining a nested model a field map array needs to be specified::

   $nestedPayment = $unionPaymentInvoice->addNestedModel(new Invoice());
   $nestedInvoice = $unionPaymentInvoice->addNestedModel(new Payment(), ['description' => '[note]']);
   $unionPaymentInvoice->addField('description');

The key of the field map array must match the UnionModel field. The value is an expression. (See :ref:`Model<addExpression>`).
This format can also be used to reverse sign on amounts. When we are creating "Transactions", then invoices would be
subtracted from the amount, while payments will be added::

   $nestedPayment = $m_uni->addNestedModel(new Invoice(), ['amount' => '-[amount]']);
   $nestedInvoice = $m_uni->addNestedModel(new Payment(), ['description' => '[note]']);
   $unionPaymentInvoice->addField('description');

Should more flexibility be needed, more expressions (or fields) can be added directly to nested models::

   $nestedPayment = $unionPaymentInvoice->addNestedModel(new Invoice(), ['amount' => '-[amount]']);
   $nestedInvoice = $unionPaymentInvoice->addNestedModel(new Payment(), ['description' => '[note]']);

   $nestedPayment->addExpression('type', ['expr' => '\'payment\'']);
   $nestedInvoice->addExpression('type', ['expr' => '\'invoice\'']);
   $unionPaymentInvoice->addField('type');

A new field "type" has been added that will be defined as a static constant.

Referencing an UnionModel Model
--------------------------

Like any other model, UnionModel model can be assigned through a reference. In the case here one Client can have multiple transactions.
Initially a related union can be defined::

   $client->hasMany('Transaction', new Transaction());


.. _SQL:

==================
Static Persistence
==================

.. php:class:: Persistence_Static

Static Persistence extends :php:class:`Persistence_Array` to implement
a user-friendly way of specifying data through an array.

Usage
=====

This is most useful when working with "sample" code, where you want to see your
results quick::

    $htmltable->setModel(new Model(new Persistence_Static([
        ['VAT_rate'=>'12.0%', 'VAT'=>'36.00', 'Net'=>'300.00'],
        ['VAT_rate'=>'10.0%', 'VAT'=>'52.00', 'Net'=>'520.00'],
    ])));

Lets unwrap the example:

.. php:method:: __construct

Constructor accepts array as an argument, but the array could be in various forms::

 - can be array of strings ['one', 'two']
 - can be array of hashes. First hash will be examined to pick up fields
 - can be array of arrays. Will name columns as 'field1', 'field2', 'field3'.

If you are using any fields without keys (numeric keys) it's important that all
your records have same number of elements.

Static Persistence will also make attempt to deduce a "title" field and will set
it automatically for the model. If you have a field with key "name" then it will
be used.
Alternative it will check key "title".

If neither are present you can still manually specify title field for your model.

Finally, static persistence (unlike :php:class:Persistence_Array) will automatically
populate fields for the model and will even attempt to deduce field types.

Currently it recognizes integer, date, boolean, float, array and object types.
Other fields will appear as-is.



Saving Records
--------------

Models that you specify against static persistence will not be marked as
"Read Only" (:php:attr:`Model::read_only`), and you will be allowed to save
data back. The data will only be stored inside persistence object and will be
discarded at the end of your PHP script.

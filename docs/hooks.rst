

=====
Hooks
=====

Hook is a mechanism for adding callbacks. The core features of Hook sub-system
(explained in detail here http://agile-core.readthedocs.io/en/develop/hook.html)
include:

 - ability to define "spots" in PHP code, such as "beforeLoad".
 - ability to add callbacks to be executed when PHP goes over the spot.
 - prioritization of callbacks
 - ability to pass arguments to callbacks
 - ability to collect response from callbacks
 - ability to break hooks (will stop any other hook execution)

:php:ref:`Model` implements hook trait and defines various hooks which will allow
you to execute code before or after various operations, such as save, load etc.

Model Operation Hooks
=====================

All of model operations (adding, updating, loading and deleting) have two
hooks - one that executes before operation and another that executes after.

Those hooks are database-agnostic, so regardless where you save your model data,
your `beforeSave` hook will be triggered.

If database has transaction support, then hooks will be executed while inside
the same transaction:

 - begin transaction
 - beforeSave
 - actual save
 - reload (see :php:attr:`Model::_reload_after_save`)
 - afterSave
 - commit

If your afterSave hook creates exception, then the entire operation will be
rolled back.

Example with beforeSave
-----------------------

The next code snippet demonstrates a basic usage of a `beforeSave` hook.
This one will update field values just before record is saved::

    $m->addHook('beforeSave', function($m) {
        $m['name'] = strtoupper($m['name']);
        $m['surname'] = strtoupper($m['surname']);
    });

    $m->insert(['name'=>'John', 'surname'=>'Smith']);

    // Will save into DB:  ['name'=>'JOHN', 'surname'=>'SMITH'];

Arguments
---------

When you define a callback, then you'll receive reference to model from all the
hooks.
It's important that you use this argument instead of $this to perform operation,
otherwise you can run into problems with cloned models.

Callbacks does non expect anything to be returned, but you can modify fields
of the model.

Interrupting
------------

You can also break all "before" hooks which will result in cancellation of the
original action::

    $m->breakHook(false);

If you break beforeSave, then the save operation will not take place, although
model will assume the operation was successful.

You can also break beforeLoad hook which can be used to skip rows::

    $model->addHook('afterLoad', function ($m) {
        if ($m['date'] < $m->date_from) {
            $m->breakHook(false); // will not yield such data row
        }
        // otherwise yields data row
    });

This will also prevent data from being loaded. If you return false from
afterLoad hook, then record which we just loaded will be instantly unloaded.
This can be helpful in some cases, although you should still use
:php:meth:`Model::addCondition` where possible as it is much more efficient.

Insert/Update Hooks
-------------------

Insert/Update are triggered from inside save() method but are based on current
state of :php:meth:`Model::loaded`:

 - beforeInsert($m, &$data) (creating new records only)
 - afterInsert($m, $id)
 - beforeUpdate($m, &$data) (updating existing records only. Not executed if model is not dirty)
 - afterUpdate($m)

The $data argument will contain array of actual data (field=>value) to be saved,
which you can use to withdraw certain fields from actually being saved into the
database (by unsetting it's value).

afterInsert will receive either $id of new record or null if model couldn't
provide ID field. Also, afterInsert is actually called before
:php:meth:`Model::_reload_after_save` reloading is done.

For some examples, see :ref:`soft_delete`

beforeSave, afterSave Hook
--------------------------

A good place to hook is beforeSave as it will be fired when adding new records
or modifying existing ones:

 - beforeSave($m) (saving existing or new records. Not executed if model is not dirty)
 - afterSave($m) (same as above)

You might consider "save" to be a higher level hook, as beforeSave is called
pretty early on during saving the record and afterSave is called at the very end
of save.

You may actually drop validation exception inside save, insert or update hooks::

    $m->addHook('beforeSave', function($m) {
        if ($m['name'] = 'Yagi') {
            throw new \atk4\data\ValidationException(['name'=>"We don't serve like you"]);
        }
    });

Loading, Deleting
-----------------

Those are relatively simple hooks:

 - beforeLoad($m, $id) ($m will be unloaded). Break for custom load or skip.
 - afterLoad($m). ($m will contain data). Break to unload and skip.

For the deletion it's pretty similar:

 - beforeDelete($m, $id). Unload and Break to preserve record.
 - afterDelete($m, $id).

A good place to clean-up delete related records would be inside afterDelete,
although if your database consistency requires those related records to be
cleaned up first, use beforeDelete instead.

For some examples, see :ref:`soft_delete`

Persistence Hooks
=================

Persistence has a few spots which it actually executes through $model->hook(),
so depending on where you save the data, there are some more hooks available.

Persistence_SQL
---------------

Those hooks can be used to affect queries before they are executed.
None of these are breakable:

 - beforeUpdateQuery($m, $dsql_query)
 - afterUpdateQuery($m, $statement). Executed before retrieving data.
 - beforeInsertQUery($m, $dsql_query)
 - afterInsertQuery($m, $statement). Executed before retrieving data.

The delete has only "before" hook:

 - beforeDeleteQuery($m, $dsql_query)

Finally for queries there is hook ``initSelectQuery($m, $query, $type)``.
It can be used to enhance queries generated by "action" for:

 - "count"
 - "update"
 - "delete"
 - "select"
 - "field"
 - "fx" or "fx0"

Other Hooks:
============


.. todo: The following hooks need documentation:

    - onlyFields
    - normalize
    - afterAdd

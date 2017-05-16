

=====
Hooks
=====

.. php:class:: Model

.. important::

Please never use ``$this`` inside your hook to refer to the model. The model
is always passed as a first argument. If you ever use ``$this`` then your
model will perform very weirdly when cloned::

    $m->addHook('beforeSave', function($m) {
        $this['name'] = 'John';  // WRONG!
        $m['surname'] = 'Smith'; // GOOD
    });

    $m->insert([]);
    // Will save into DB:  ['surname'=>'Smith'];

    echo $m['name'];   // Will contain 'John', which it shouldn't
                       // because insert() is not supposed to affect active record

afterLoad hook
--------------
You can return false from afterLoad hook to prevent yielding of particular data rows.

Use it like this::

$model->addHook('afterLoad', function ($m) {
    if ($m['date'] < $m->date_from) {
        $m->breakHook(false); // will not yield such data row
    }
    // otherwise yields data row
});

Also this approach can be used to prevent data row to be loaded. If you return false
from afterLoad hook, then record which we just loaded will be instantly unloaded.
This can be helpful in some cases.


More on hooks
===========

Coming soon

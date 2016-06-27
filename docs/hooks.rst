

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


More on hooks
===========

Coming soon

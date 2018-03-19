
.. _Model:

=====
Model
=====

.. note:: This documentation needs to be reworked to be easier to read!

.. php:class:: Model

Initialization
==============

Model class implements your Business Model - single entity of your business logic.
When you plan your business application you should create classes for all your
possible business entities by extending from "Model" class.

.. php:method:: init

Method init() will automatically be called when your Model is associated with
persistence driver. Use it to define fields of your model::

    class Model_User extends atk4\data\Model
    {
        function init() {
            parent::init();

            $this->addField('name');
            $this->addField('surname');
        }
    }

.. php:method:: addField($name, $defaults)

    Creates a new field objects inside your model (by default the class is 'Field').
    The fields are implemented on top of Containers from Agile Core.

    Second argument to addField() can supply field default properties::

        $this->addField('surname', ['default'=>'Smith']);

Read more about :php:class:`Field`

.. php:property:: strict_fields

    By default model will only allow you to operate with values for the fields
    that have been defined through addField(). If you attempt to get, set or
    otherwise access the value of any other field that has not been properly
    defined, you'll get exception. Read more about :php:class:`Field`

    If you set strict_field to false, then the check will not be performed.

Populating Data
===============

.. php:method:: insert($row)

    Inserts a new record into the database and returns $id. It does not affect
    currently loaded record and in practice would be similar to::

        $m_x = $m;
        $m_x->unload();
        $m_x->set($row);
        $m_x->save();
        return $m_x;

    The main goal for insert() method is to be as fast as possible, while still
    performing data validation. After inserting method will return cloned model.

.. php:method:: import($data)

    Similar to insert() however works across array of rows. This method will
    not return any IDs or models and is optimized for importing large amounts
    of data.

    The method will still convert the data needed and operate with joined
    tables as needed. If you wish to access tables directly, you'll have to look
    into Persistence::insert($m, $data, $table);

Associating Model with Database
===============================

Normally you should always associate your model with persistence layer (database)
when you create the instance like this::

    $m = new Model_User($db);

.. php:attr:: persistence

    Refers to the persistence driver in use by current model. Calling certain
    methods such as save(), addCondition() or action() will rely on this property.

.. php:attr:: persistence_data

    Array containing arbitrary data by a specific persistence layer.

.. php:attr:: table

    If $table property is set, then your persistence driver will use it as default
    table / collection when loading data. If you omit the table, you should specify
    it when associating model with database::

    $m = new Model_User($db, 'user');

.. php:method:: withPersistence($persistence, $id = null, $class = null)

    Creates a duplicate of a current model and associate new copy with a specified
    persistence. This method is useful for moving model data from one persistence
    to another.


Working with selective fields
=============================

When you normally work with your model then all fields are available and will be
loaded / saved. You may, however, specify that you wish to load only a sub-set
of fields.

(In ATK4.3 we call those fields "Actual Fields")

.. php:method:: onlyFields($fields)

    Specify array of fields. Only those fields will be accessible and will be
    loaded / saved. Attempt to access any other field will result in exception.

.. php:method:: allFields()

    Restore to full set of fields. This will also unload active record.

.. php:attr:: only_fields

    Contains list of fields to be loaded / accessed.

.. _Active Record:

Setting and Getting active record data
======================================

When your record is loaded from database, record data is stored inside the $data
property:

.. php:attr:: data

    Contains the data for an active record.

Model allows you to work with the data of single a record directly. You should
use the following syntax when accessing fields of an active record::

    $m['name'] = 'John';
    $m['surname'] = 'Peter';

When you modify active record, it keeps the original value in the $dirty array:

.. php:method:: set

    Set field to a specified value. The original value will be stored in
    $dirty property. If you pass non-array, then the value will be assigned
    to the :ref:`title_field`.

.. php:method:: unset

    Restore field value to it's original::

        $m['name'] = 'John';
        echo $m['name']; // John

        unset($m['name']);
        echo $m['name']; // Original value is shown

    This will restore original value of the field.

.. php:method:: get

    Returns one of the following:

     - If value was set() to the field, this value is returned
     - If field was loaded from database, return original value
     - if field had default set, returns default
     - returns null.

.. php:method:: isset

    Return true if field contains unsaved changes (dirty)::

        isset($m['name']); // returns false
        $m['name'] = 'Other Name';
        isset($m['name']); // returns true


.. php:method:: isDirty

    Return true if one or multiple fields contain unsaved changes (dirty)::

        if ($m->isDirty(['name','surname'])) {
           $m['full_name'] = $m['name'].' '.$m['surname'];
        }

    When the code above is placed in beforeSave hook, it will only be executed
    when certain fields have been changed. If your recalculations are expensive,
    it's pretty handy to rely on "dirty" fields to avoid some complex logic.

.. php:attr:: dirty

    Contains list of modified fields since last loading and their original
    values.

Full example::

    $m = new Model_User($db, 'user');

    // Fields can be added after model is created
    $m->addField('salary', ['default'=>1000]);

    echo isset($m['salary']);   // false
    echo $m['salary'];          // 1000

    // Next we load record from $db
    $m->load(1);

    echo $m['salary'];          // 2000 (from db)
    echo isset($m['salary']);   // false, was not changed

    $m['salary'] = 3000;

    echo $m['salary'];          // 3000 (changed)
    echo isset($m['salary']);   // true

    unset($m['salary']);        // return to original value

    echo $m['salary'];          // 2000
    echo isset($m['salary']);   // false

    $m['salary'] = 3000;
    $m->save();

    echo $m['salary'];          // 3000 (now in db)
    echo isset($m['salary']);   // false

.. php:method:: protected normalizeFieldName

    Verify and convert first argument got get / set;

Title Field, ID Field and Model Caption
=======================================

Those are three properties that you can specify in the model or pass it through
defaults::

    class MyModel ..
        public $title_field = 'full_name';

or as defaults::

    $m = new MyModel($db, ['title_field'=>'full_name']);


.. _id_field:

ID Field
--------

.. php:attr:: id_field

    If your data storage uses field different than ``id`` to keep the ID of your
    records, then you can specify that in $id_field property.

.. tip:: You can change ID value of the current ID field by calling::

        $m['id'] = $new_id;
        $m->save();

    This will update existing record with new $id. If you want to save your
    current field over another existing record then::

        $m->id = $new_id;
        $m->save();

    You must remember that only dirty fields are saved, though. (We might add
    replace() function though).

.. _title_field:

Title Field
-----------

.. php:attr:: title_field

    This field by default is set to 'name' will act as a primary title field of
    your table. This is especially handy if you use model inside UI framework,
    which can automatically display value of your title field in the header,
    or inside drop-down.

    If you don't have field 'name' but you want some other field to be title,
    you can specify that in the property. If title_field is not needed, set it
    to false or point towards a non-existent field.

    See: :php:meth::`hasOne::addTitle()` and :php:meth::`hasOne::withTitle()`

.. php:method:: public getTitle

    Return title field value of currently loaded record.

.. _caption:

Model Caption
-------------

.. php:attr:: caption

    This is caption of your model. You can use it in your UI components.

.. php:method:: public getModelCaption

    Returns model caption. If caption is not set, then try to generate one from
    model class name.


Hooks
=====

- beforeSave [not currently working]

  - beforeInsert [only if insert]
    - beforeInsertQuery [sql only] (query)
    - afterInsertQuery (query, statement)

  - beforeUpdate [only if update]
    - beforeUpdateQuery [sql only] (query)
    - afterUpdateQuery (query, statement)


  - afterUpdate [only if existing record]
  - afterInsert [only if new record]

  - beforeUnload
  - afterUnload

- afterSave

How to verify Updates
---------------------

The model is only being saved if any fields have been changed (dirty).
Sometimes it's possible that the record in the database is no longer available
and your update() may not actually update anything. This does not normally
generate an error, however if you want to actually make sure that update() was
effective, you can implement this through a hook::

    $m->addHook('afterUpdateQuery',function($m, $update, $st) {
        if (!$st->rowCount()) {
            throw new \atk4\core\Exception([
                'Update didn\'t affect any records',
                'query'      => $update->getDebugQuery(false),
                'statement'  => $st,
                'model'      => $m,
                'conditions' => $m->conditions,
            ]);
        }
    });


How to prevent actions
----------------------

In some cases you want to prevent default actions from executing.
Suppose you want to check 'memcache' before actually loading the record from
the database. Here is how you can implement this functionality::

    $m->addHook('beforeLoad',function($m, $id) {
        $data = $m->app->cacheFetch($m->table, $id);
        if ($data) {
            $m->data = $data;
            $m->id = $id;
            $m->breakHook(false);
        }
    });

$app property is injected through your $db object and is passed around to all
the models. This hook, if successful, will prevent further execution of other
beforeLoad hooks and by specifying argument as 'false' it will also prevent call
to $persistence for actual loading of the data.

Similarly you can prevent deletion if you wish to implement
:ref:`soft-delete` or stop insert/modify from occurring.

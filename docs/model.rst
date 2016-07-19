
.. _Model:

============================
Working With Business Models
============================

.. note:: This documentation needs to be reworked to be easier to read!

.. php:class:: Model

Initialization
==============

Model class implements your Business Model - single entity of your business logic. When
you plan your business application you should create classes for all your possible
business entities by extending from "Model" class. 

.. php:method:: init

Method init() will automatically be called when your Model is associated with persistence
driver. Use it to define fields of your model::

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

Populating Data
===============

.. php:method:: insert($row)

    Inserts a new record into the database and returns $id. It does not
    affect currently loaded record and in practice would be similar to::

        $m_x = $m;
        $m_x->unload();
        $m_x->set($row);
        $m_x->save();
        return $m_x;

    The main goal for insert() method is to be as fast as possible, while
    still performing data validation. After inserting method will return
    cloned model.

.. php:method:: import($data)

    Similar to insert() however works across array of rows. This method
    will not return any IDs or models and is optimized for importing large
    amounts of data.

    The method will still convert the data needed and operate with joined
    tables as needed. If you wish to access tables directly, you'll 
    have to look into Persistence::insert($m, $data, $table);

Associating Model with Database
===============================

Normally you should always associate your model with persistance layer (database) when
you create the instance like this::

    $m = new Model_User($db);

.. php:attr:: persistence

    Refers to the persistence driver in use by current model. Calling certain methods
    such as save(), addCondition() or action() will rely on this property.

.. php:attr:: persistence_data

    Array containing arbitrary data by a specific persistance layer.

.. php:attr:: table

    If $table property is set, then your persistance driver will use it as default
    table / colleciton when loading data. If you omit the table, you should specify
    it when assoicating model with database::

    $m = new Model_User($db, 'user');


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

When your record is loaded from database, record data is stored inside
the $data property:

.. php:attr:: data

    Contains the data for an active record.

Model allows you to work with the data of single a record directly. You should
use the following syntax when accessing fields of an active record::

    $m['name'] = 'John';
    $m['surname'] = 'Peter';

When you modify active record, it keeps the original value in the $dirty
array:

.. php:attr:: dirty

    Contains list of modified fields since last loading and their original
    valies.

.. php:method:: set

    Set field to a specified value. The original value will be stored in
    $dirty property

.. php:method:: unset

    Restore field value to it's original::

        $m['name'] = 'John';
        echo $m['name']; // John

        unset($m['name']);
        echo $m['name']; // Original value is shown

    This will restore original value of the field.

.. php:method:: isset

    Return true if field contains unsaved changes::

        isset($m['name']); // returns false
        $m['name'] = 'Other Name';
        isset($m['name']); // returns true 

.. php:method:: get

    Returns one of the following:

     - If value was set() to the field, this value is returned
     - If field was loaded from database, return original value
     - if field had default set, returns default
     - returns null.

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

Hooks
=====

- beforeSave [not currently worknig]

  - beforeInsert [only if insert]
    - beforeInsertQuery [sql only]

  - beforeUpdate [only ift update]
    - beforeUpdateQuery [sql only]



  - afterUpdate [only if existing record]
  - afterInsert [only if new record]

- afterSave

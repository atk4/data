
.. _Fields:

.. php:namespace:: atk4\data

.. php:class:: Field


=====
Field
=====

Field represents a model `property` that can hold information about your entity.
In Agile Data we call it a Field, to distinguish it from object properties. Fields
inside a model normally have a corresponding instance of Field class.

See :php:meth:`Model::addField()` on how fields are added. By default,
persistence sets the property _default_seed_addField which should correspond
to a field object that has enough capabilities for performing field-specific
mapping into persistence-logic.

.. php:class:: Field


Field represents a `property` of your business entity or `column` if you think
of your data in a tabular way. Once you have defined Field for your Model, you
can set and read value of that field::

    $model->addField('name');
    $model->set('name', 'John');

    echo $model->get('name');  // john

Agile Data supports and prefers a ArrayAccess format of interacting with fields::

    $model->addField('age');
    $model['age'] = 29;

    echo $model['age'];

Just like you can reuse :php:class:`Model` to access multiple data records,
:php:class:`Field` object will be reused also.

Purpose of Field
================

Implementation of Field in Agile Data is a very powerful and distinctive feature.
While :php:attr:`Model::data` store your field values, the job of :php:class:`Field`
is to interpret that value, normalize it, type-cast it, validate it and decide
on how to store or present it.

The implementation of Fields is tightly integrated with :php:class:`Model` and
:php:class:`Persistence`.

Field Type
----------

.. php:attr:: type


Probably a most useful quality of Field is that it has a clear type::

    $model->addField('age', ['type'=>'integer']);
    $model['age'] = "123";

    var_dump($model['age']);   // int(123)

Agile Data defines some basic types to make sure that values:

 - can be safely stored and manipulated.
 - can be saved (Persistence)
 - can be presented to user (UI)

A good example would be a `date` type::

    $model->addField('birth', ['type' => 'date']);
    $model['birth'] = DateTime::createFromFormat('m/d/Y', '1/10/2014');

    $model->save();

When used with SQL persistence the value will be automatically converted into a
format preferred by the database `2014-10-01`. Because PHP has only a single
type for storing date, time and datetime, this can lead to various problems such
as handling of timezones or DST. Agile Data takes care of those issues for you
automatically.

Conversions between types is what we call :ref:`Typecasting` and there is a
documentation section dedicated to it.

Finally, because Field is a class, it can be further extended. For some
interesting examples, check out :php:class:`Field\Password`. I'll explain how to
create your own field classes and where they can be beneficial.

Valid types are: string, integer, boolean, datetime, date, time.

You can specify unsupported type too. It will be untouched by Agile Data so you
would have to implement your own handling of a new type.

Persistence implements two methods:
 - :php:meth:`Persistence::typecastSaveRow()`
 - :php:meth:`Persistence::typecastLoadRow()`

Those are responsible for converting PHP native types to persistence specific
formats as defined in fields. Those methods will also change name of the field
if needed (see Field::actual)

.. php:attr:: typecast

This property can be used to override typecasting for your field. See
:ref:`Typecasting`


Basic Properties
----------------

Fields have properties, which define its behaviour. Some properties apply on how
the values are handled or restrictions on interraction, other values can even
help with data vizualization. For example if :php:attr:`Field::enum` is used
with Agile UI form, it will be displayed as radio button or a drop-down::

    $model->addField('gender', ['enum' => ['F', 'M']]);

    // Agile UI code:
    $app = new \atk4\ui\App('my app');
    $app->initLayout('Centered');
    $app->add('Form')->setModel($model);

You will also not be able to set value which is not one of the `enum` values
even if you don't use UI.

This allows you to define your data fields once and have those rules respected
everywhere in your app - in your manual code, in UI and in API.

.. php:attr:: default

When no value is specified for a field, default value is used when inserting.
This value will also appear pre-filled inside a Form.

.. php:attr:: enum

Specifies array containing all the possible options for the value.
You can set only to one of the values (loosely typed comparison is used).

.. php:attr:: mandatory

Set this to true if field value must not be NULL. You can set the NULL value to
the field, but you won't be able to save it.

Example::

    $model['age'] = 0;
    $model->save();

    $model['age'] = null;
    $model->save();  // exception


.. php:attr:: required

Set this to true for field that may not contain "empty" value.
You can't use NULL or any value that is considered ``empty()`` by PHP.
Some examples that are not allowed are:

 - empty string ""
 - 0 numerical value or 0.00
 - boolean false

Example::

    $model['age'] = 0;
    $model->save();  // exception

    $model['age'] = null;
    $model->save();  // exception


.. php:attr:: read_only

Modifying field that is read-only through set() methods (or array access) will
result in exception. :php:class:`Field_SQL_Expression` is read-only by default.

.. php:attr:: actual

Specify name of the Table Row Field under which field will be persisted.

.. php:attr:: join

This property will point to :php:class:`Join` object if field is associated
with a joined table row.

.. php:attr:: system

System flag is intended for fields that are important to have inside hooks
or some core logic of a model. System fields will always be appended to
:php:attr:`Model::onlyFields`, however by default they will not appear on forms
or grids (see :php:meth:`Model::isVisible`, :php:meth:`Model::isEditable`).

Adding condition on a field will also make it system.

.. php:attr:: never_persist

Field will never be loaded or saved into persistence. You can use this flag
for fields that physically are not located in the database, yet you want to see
this field in beforeSave hooks.

.. php:attr:: never_save

This field will be loaded normally, but will not be saved in a database.
Unlike "read_only" which has a similar effect, you can still change the value
of this field. It will simply be ignored on save. You can create some logic in
beforeSave hook to read this value.

.. php:attr:: ui

This field contains certain arguments that may be needed by the UI layer to know
if user should be allowed to edit this field.

.. php:attr:: loadCallback

Specify a callback that will be executed when the field is loaded and it is
necessary to decode or do something else with loaded the value.

You can use this callback if you are storing data in some unusual format and
need to convert it into PHP value. Format of callback is::

    function ($value) {
        return str_rot13($value);
    }

There are additional arguments in case you want to have a common callback::

    $encrypt = function ($value, $key, $persistence) {

        // load encrypted data from SQL
        if ($persistence instanceof \atk4\data\Persistence_SQL) {
            return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key->key, $value);
        }

        return $value;
    }

Note that if you use a call-back this will by-pass normal field typecasting.

See :ref:`Advanced::EncryptedField` for full example.

.. php:attr:: saveCallback

Same as loadCallback property but will be executed when saving data. Arguments
are still the same::

    function ($value) {
        return str_rot13($value);
    }

There are additional arguments in case you want to have a common callback::

    $decrypt = function ($value, $key, $persistence) {

        // load encrypted data from SQL
        if ($persistence instanceof \atk4\data\Persistence_SQL) {
            return mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key->key, $value);
        }

        return $value;
    }


See :ref:`Advanced::EncryptedField` for full example.

.. php:method:: set

Set the value of the field. Same as $model->set($field_name, $value);

.. php:method:: get

Get the value of the field. Same as $model->get($field_name, $value);

UI Presentation
---------------

Agile Data does not deal directly with formatting your data for the user.
There may be various items to consider, for instance the same date can be
presented in a short or long format for the user.

The UI framework such as Agile Toolkit can make use of the :php:attr:`Field::ui`
property to allow user to define default formats or input parsing rules, but
Agile Data does not regulate the :php:attr:`Field::ui` property and different
UI frameworks may use it differently.


.. php:method:: isEditable

Returns true if UI should render this field as editable and include inside
forms by default.

.. php:method:: isVisible

Returns true if UI should render this field in Grid and other read_only display
views by default.

.. php:method:: isHidden

Returns true if UI should not render this field in views.


Password
========

.. php:namespace:: atk4\data\Field

.. php:class:: Password

Field\Password is a class that implements proper handling of data passwords.
Without this class your password will be stored **unencrypted**.
Here is how to use it properly::

    $user->addField('mypass', ['Password']);

    $user['mypass'] = 'secret';
    $user->save();

Password is automatically hashed with `password_encrypt` before storing. If you
attempt to load existing record from database and `$user->get('mypass')` you
will always get `NULL`.

There is another way to verify passwords using :php:meth:`Model::compare`::

    $user->loadBy('email', $email);
    return $user->compare('password', $password);

This should return `true` if your supplied password matches the one that is
stored. Final example::

    // class User extends Model

    function changePass($old_pass, $new_pass) {

        if (!$this->compare('password', $old_pass)) {
            throw new Exception('Old password is incorrect');
        }

        $this['password'] = $new_pass;
        $this->save();
    }

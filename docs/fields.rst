

.. _Fields:

======
Fields
======

Field represents a model `property` which we do refer to as field throughout
Agile Data, to distinguish it from object properties. Fields inside a model
normally have a corresponding instance of Field class.

See :php:meth:`Model::addField()` on how fields are added. By default,
persistence sets the property _default_class_addField which should correspond
to a field object that has enough capabilities for performing field-specific
mapping into persistence-logic.

.. php:class:: Field

.. php:property:: default

When no value is specified for a field, default value is used
when inserting.

.. php:property:: type

Valid types are: string, integer, boolean, datetime, date, time.

You can specify unsupported type. It will be untouched by Agile Data
so you would have to implement your own handling of a new type.

Persistence implements two methods:
 - :php:meth:`Persistence::typecastSaveRow()`
 - :php:meth:`Persistence::typecastLoadRow()`

Those are responsible for converting PHP native types to persistence
specific formats as defined in fields. Those methods will also change
name of the field if needed (see Field::actual)

.. php:property:: enum

Specifies array containing all the possible options for the value.
You can set only to one of the values (loosely typed comparison
is used)

.. php:property:: mandatory

Set this to true if field property is mandatory and must be non-null in order
for model to properly exist. Note that you can still set the NULL value to the
field, but you won't be able to save it.

.. php:property:: read_only

Modifying field that is read-only through set() methods (or array access) will
result in exception. :php:class:`Field_SQL_Expression` is read-only by default.

.. php:property:: actual

Specify name of the Table Row Field under which field will be persisted.

.. php:property:: join

This property will point to :php:class:`Join` object if field is associated
with a joined table row.

.. php:property:: system

System flag is intended for fields that are important to have inside hooks
or some core logic of a model. System fields will always be appended to
:php:attr:`Model::onlyFields`, however by default they will not appear on forms
or grids (see :php:meth:`Model::isVisible`, :php:meth:`Model::isEditable`).

Adding condition on a field will also make it system.

.. php:property:: never_persist

Field will never be loaded or saved into persistence. You can use this flag
for fields that physically are not located in the database, yet you want
to see this field in beforeSave hooks.

.. php:property:: never_save

This field will be loaded normally, but will not be saved in a database.
Unlike "read_only" which has a similar effect, you can still change the
value of this field. It will simply be ignored on save. You can create
some logic in beforeSave hook to read this value.

.. php:property:: ui

This field contains certain arguments that may be needed by the UI layer
to know if user should be allowed to edit this field.

.. php:property:: loadCallback

Specify a callback that will be executed when the field is loaded and
it is necessary to decode or do something else with loaded the value.

You can use this callback if you are storing data in some unusual format
and need to convert it into PHP value. Format of callback is::

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

.. php:property:: saveCallback

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

Agile Data does not deal directly with formatting your data
for the user. There may be various items to consider, for instance
the same date can be presented in a short or long format for the user.

The UI framework such as Agile Toolkit can make use of the :php:attr:`Field::ui`
property to allow user to define default formats or input parsing
rules, but Agile Data does not regulate the :php:attr:`Field::ui` property and
different UI frameworks may use it differently.


.. php:method:: isEditable

Returns true if UI should render this field as editable and include inside
forms by default.

.. php:method:: isVisible

Returns true if UI should render this field in Grid and other read_only
display views by default.

.. php:method:: isHidden

Returns true if UI should not render this field in views.



Model
=====

Data model class.

:Qualified name: ``atk4\data\Model``

.. php:class:: Model

  .. php:method:: __clone ()

    Clones model object.


  .. php:method:: __construct ([])

    Creation of the new model can be done in two ways:.
$m = $db->add(new Model());
or
$m = new :class:`Model`($db);
The second use actually calls add() but is preferred usage because:

    :param $persistence:
      Default: ``null``
    :param $defaults:
      Default: ``[]``

  .. php:method:: __debugInfo () -> array

    Returns array with useful debug info for var_dump.

    :returns: array -- 

  .. php:method:: _unset ($name)

    Remove current field value and use default.

    :param $name:
    :returns: $this

  .. php:method:: action ($mode[, $args])

    Execute action.

    :param $mode:
    :param $args:
      Default: ``[]``
    :returns: \atk4\dsql\Query

  .. php:method:: addCalculatedField ($name, $expression)

    Add expression field which will calculate its value by using callback.

    :param $name:
    :param $expression:

  .. php:method:: addCondition ($field[, $operator, $value])

    Narrow down data-set of the current model by applying additional condition. There is no way to remove condition once added, so if you need - clone model.
This is the most basic for defining condition: ->addCondition('my_field', $value);
This condition will work across all persistence drivers universally.
In some cases a more complex logic can be used: ->addCondition('my_field', '>', $value); ->addCondition('my_field', '!=', $value); ->addCondition('my_field', 'in', [$value1, $value2]);
Second argument could be '=', '>', '<', '>=', '<=', '!=' or 'in'. Those conditions are still supported by most of persistence drivers.
There are also vendor-specific expression support: ->addCondition('my_field', $expr); ->addCondition($expr);
To use those, you should consult with documentation of your persistence driver.

    :param $field:
    :param $operator:
      Default: ``null``
    :param $value:
      Default: ``null``
    :returns: $this

  .. php:method:: addExpression ($name, $expression)

    Add expression field.

    :param $name:
    :param $expression:

  .. php:method:: addField ($field[, $defaults]) -> Field

    Adds new field into model.

    :param $field:
    :param $defaults:
      Default: ``[]``
    :returns: :class:`Field` -- 

  .. php:method:: addFields ([])

    Adds multiple fields into model.

    :param $fields:
      Default: ``[]``
    :param $defaults:
      Default: ``[]``
    :returns: $this

  .. php:method:: addRef ($link, $callback)

    Add generic relation. Provide your own call-back that will return the model.

    :param $link:
    :param $callback:

  .. php:method:: allFields ()

    Sets that we should select all available fields.

    :returns: $this

  .. php:method:: asModel ($class[, $options]) -> Model

    This will cast :class:`Model` into another class without loosing state of your active record.

    :param $class:
    :param $options:
      Default: ``[]``
    :returns: :class:`Model` -- 

  .. php:method:: atomic ($f[, Persistence $persistence]) -> mixed

    Atomic executes operations within one begin/end transaction, so if the code inside callback will fail, then all of the transaction will be also rolled back.

    :param $f:
    :param Persistence $persistence:
      Default: ``null``
    :returns: mixed -- 

  .. php:method:: compare ($name, $value) -> bool

    You can compare new value of the field with existing one without retrieving. In the trivial case it's same as ($value == $model[$name]) but this method can be used for:.

    :param $name:
    :param $value:
    :returns: bool -- true if $value matches saved one

  .. php:method:: containsMany ($link[, $defaults])

    Add containsMany field.

    :param $link:
    :param $defaults:
      Default: ``[]``

  .. php:method:: containsOne ($link[, $defaults])

    Add containsOne field.

    :param $link:
    :param $defaults:
      Default: ``[]``

  .. php:method:: delete ([])

    Delete record with a specified id. If no ID is specified then current record is deleted.

    :param $id:
      Default: ``null``
    :returns: $this

  .. php:method:: duplicate ([])

    Keeps the model data, but wipes out the ID so when you save it next time, it ends up as a new record in the database.

    :param $new_id:
      Default: ``null``
    :returns: $this

  .. php:method:: each ($method)

    Executes specified method or callback for each record in DataSet.

    :param $method:
    :returns: $this

  .. php:method:: export ([]) -> array

    Export DataSet as array of hashes.

    :param $fields:
      Default: ``null``
    :param $key_field:
      Default: ``null``
    :param $typecast_data:
      Default: ``true``
    :returns: array -- 

  .. php:method:: get ([]) -> mixed

    Returns field value. If no field is passed, then returns array of all field values.

    :param $field:
      Default: ``null``
    :returns: mixed -- 

  .. php:method:: getField ($name) -> Field

    Same as hasField, but will throw exception if field not found. Similar to getElement().

    :param $name:
    :returns: :class:`Field` -- 

  .. php:method:: getIterator ()

    Returns iterator (yield values).


  .. php:method:: getModelCaption () -> string

    Return (possibly localized) $model->caption.

    :returns: string -- 

  .. php:method:: getRef ($link)

    Return reference field.

    :param $link:

  .. php:method:: getRefs () -> array

    Returns all reference fields.

    :returns: array -- 

  .. php:method:: getTitle () -> mixed

    Return value of $model[$model->title_field]. If not set, returns id value.

    :returns: mixed -- 

  .. php:method:: hasField ($name)

    Finds a field with a corresponding name. Returns false if field not found. Similar to hasElement() but with extra checks to make sure it's certainly a field you are getting.

    :param $name:
    :returns: Field|false

  .. php:method:: hasMany ($link[, $defaults])

    Add hasMany field.

    :param $link:
    :param $defaults:
      Default: ``[]``

  .. php:method:: hasOne ($link[, $defaults])

    Add hasOne field.

    :param $link:
    :param $defaults:
      Default: ``[]``

  .. php:method:: hasRef ($link)

    Return reference field or false if reference field does not exist.

    :param $link:
    :returns: Field|bool

  .. php:method:: import ($rows)

    Even more faster method to add data, does not modify your current record and will not return anything.
Will be further optimized in the future.

    :param $rows:
    :returns: $this

  .. php:method:: init ()

    Extend this method to define fields of your choice.


  .. php:method:: insert ($row) -> mixed

    Faster method to add data, that does not modify active record.
Will be further optimized in the future.

    :param $row:
    :returns: mixed -- 

  .. php:method:: isDirty ([]) -> bool

    Will return true if any of the specified fields are dirty.

    :param $fields:
      Default: ``[]``
    :returns: bool -- 

  .. php:method:: join ($foreign_table[, $defaults])

    Creates an objects that describes relationship between multiple tables (or collections).
When object is loaded, then instead of pulling all the data from a single table, join will also query $foreign_table in order to find additional fields. When inserting the record will be also added inside $foreign_table and relationship will be maintained.

    :param $foreign_table:
    :param $defaults:
      Default: ``[]``

  .. php:method:: lastInsertID () -> mixed

    Last ID inserted.

    :returns: mixed -- 

  .. php:method:: leftJoin ($foreign_table[, $defaults]) -> join()

    Left :class:`Join` support.

    :param $foreign_table:
    :param $defaults:
      Default: ``[]``
    :returns: :class:`join()` -- 

  .. php:method:: load ($id[, Persistence $from_persistence])

    Load model.

    :param $id:
    :param Persistence $from_persistence:
      Default: ``null``
    :returns: $this

  .. php:method:: loadAny ()

    Load any record.

    :returns: $this

  .. php:method:: loadBy ($field, $value)

    Load record by condition.

    :param $field:
    :param $value:
    :returns: $this

  .. php:method:: loaded () -> bool

    Is model loaded?

    :returns: bool -- 

  .. php:method:: newInstance ([]) -> Model

    Create new model from the same base class as $this.

    :param $class:
      Default: ``null``
    :param $options:
      Default: ``[]``
    :returns: :class:`Model` -- 

  .. php:method:: offsetExists ($name) -> bool

    Do field exist?

    :param $name:
    :returns: bool -- 

  .. php:method:: offsetGet ($name) -> mixed

    Returns field value.

    :param $name:
    :returns: mixed -- 

  .. php:method:: offsetSet ($name, $val)

    Set field value.

    :param $name:
    :param $val:

  .. php:method:: offsetUnset ($name)

    Redo field value.

    :param $name:

  .. php:method:: onlyFields ([])

    Sets which fields we will select.

    :param $fields:
      Default: ``[]``
    :returns: $this

  .. php:method:: rawIterator ()

    Returns iterator.

    :returns: Iterator

  .. php:method:: ref ($link[, $defaults])

    Traverse to related model.

    :param $link:
    :param $defaults:
      Default: ``[]``

  .. php:method:: refLink ($link[, $defaults])

    Returns model that can be used for generating sub-query actions.

    :param $link:
    :param $defaults:
      Default: ``[]``

  .. php:method:: refModel ($link[, $defaults])

    Return related model.

    :param $link:
    :param $defaults:
      Default: ``[]``

  .. php:method:: reload ()

    Reload model by taking its current ID.

    :returns: $this

  .. php:method:: save ([])

    :param $data:
      Default: ``[]``
    :param Persistence $to_persistence:
      Default: ``null``

  .. php:method:: saveAndUnload ([])

    Store the data into database, but will never attempt to reload the data. Additionally any data will be unloaded. Use this instead of save() if you want to squeeze a little more performance out.

    :param $data:
      Default: ``[]``
    :returns: $this

  .. php:method:: saveAs ($class[, $options]) -> Model

    Saves the current record by using a different model class. This is similar to:.
$m2 = $m->newInstance($class); $m2->load($m->id); $m2->set($m->:class:`get()`); $m2->save();
but will assume that both models are compatible, therefore will not perform any loading.

    :param $class:
    :param $options:
      Default: ``[]``
    :returns: :class:`Model` -- 

  .. php:method:: set ($field[, $value])

    Set field value.

    :param $field:
    :param $value:
      Default: ``null``
    :returns: $this

  .. php:method:: setLimit ($count[, $offset])

    Set limit of DataSet.

    :param $count:
    :param $offset:
      Default: ``null``
    :returns: $this

  .. php:method:: setOrder ($field[, $desc])

    Set order for model records. Multiple calls.

    :param $field:
    :param $desc:
      Default: ``null``
    :returns: $this

  .. php:method:: tryLoad ($id)

    Try to load record. Will not throw exception if record doesn't exist.

    :param $id:
    :returns: $this

  .. php:method:: tryLoadAny ()

    Try to load any record. Will not throw exception if record doesn't exist.

    :returns: $this

  .. php:method:: tryLoadBy ($field, $value)

    Try to load record by condition. Will not throw exception if record doesn't exist.

    :param $field:
    :param $value:
    :returns: $this

  .. php:method:: unload ()

    Unload model.

    :returns: $this

  .. php:method:: validate ([]) -> array

    Perform validation on a currently loaded values, must return Array in format: ['field'=>'must be 4 digits exactly'] or empty array if no errors were present.
You may also use format: ['field'=>['must not have character [ch]', 'ch'=>$bad_character']] for better localization of error message.
Always use return array_merge(parent::validate($intent), $errors);

    :param $intent:
      Default: ``null``
    :returns: array -- ['field'=> err_spec]

  .. php:method:: withID ($id)

    Shortcut for using addCondition(id_field, $id).

    :param $id:
    :returns: $this

  .. php:method:: withPersistence ($persistence[, $id, string $class])

    Create new model from the same base class as $this. If you omit $id,then when saving a new record will be created with default ID. If you specify $id then it will be used to save/update your record. If set $id to true then model will assume that there is already record like that in the destination persistence.
If you wish to fully copy the data from one model to another you should use:
$m->withPersistence($p2, false)->set($m)->save();
See https://github.com/atk4/data/issues/111 for use-case examples.

    :param $persistence:
    :param $id:
      Default: ``null``
    :param string $class:
      Default: ``null``
    :returns: $this


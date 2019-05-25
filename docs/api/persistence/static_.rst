Static_
=======

Implements a very basic array-access pattern:.
$m = new :class:`Model`(:class:`Persistence`\Static_(['hello', 'world'])); $m->load(1);
echo $m['name']; // world

:Qualified name: ``atk4\data\Persistence\Static_``
:Extends: :class:`Array_`

.. php:class:: Static_

  .. php:method:: __construct ([])

    Constructor. Can pass array of data in parameters.

    :param $data:
      Default: ``null``

  .. php:method:: _serializeLoadField (Field $f, $value) -> mixed

    Override this to fine-tune un-serialization for your persistence.

    :param Field $f:
    :param $value:
    :returns: mixed -- 

  .. php:method:: _serializeSaveField (Field $f, $value) -> mixed

    Override this to fine-tune serialization for your persistence.

    :param Field $f:
    :param $value:
    :returns: mixed -- 

  .. php:method:: _typecastLoadField (Field $f, $value) -> mixed

    This is the actual field typecasting, which you can override in your persistence to implement necessary typecasting.

    :param Field $f:
    :param $value:
    :returns: mixed -- 

  .. php:method:: _typecastSaveField (Field $f, $value) -> mixed

    This is the actual field typecasting, which you can override in your persistence to implement necessary typecasting.

    :param Field $f:
    :param $value:
    :returns: mixed -- 

  .. php:method:: action ($m, $type[, $args]) -> mixed

    Various actions possible here, mostly for compatibility with SQLs.

    :param $m:
    :param $type:
    :param $args:
      Default: ``[]``
    :returns: mixed -- 

  .. php:method:: add ($m[, $defaults]) -> Model

    Associate model with the data driver.

    :param $m:
    :param $defaults:
      Default: ``[]``
    :returns: :class:`Model` -- 

  .. php:method:: afterAdd ($p, $m)

    Automatically adds missing model fields. Called from AfterAdd hook.

    :param $p:
    :param $m:

  .. php:method:: applyConditions (Model $model, \:class:`atk4\data\Action\Iterator` $iterator)

    Will apply conditions defined inside $m onto query $q.

    :param Model $model:
    :param \:class:`atk4\data\Action\Iterator` $iterator:
    :returns: \atk4\data\Action\Iterator|null

  .. php:method:: atomic ($f) -> mixed

    Atomic executes operations within one begin/end transaction. Not all persistences will support atomic operations, so by default we just don't do anything.

    :param $f:
    :returns: mixed -- 

  .. php:method:: delete (Model $m, $id[, $table])

    Deletes record in data array.

    :param Model $m:
    :param $id:
    :param $table:
      Default: ``null``

  .. php:method:: disconnect ()

    Disconnect from database explicitly.


  .. php:method:: export (Model $m[, $fields, $typecast_data]) -> array

    Export all DataSet.

    :param Model $m:
    :param $fields:
      Default: ``null``
    :param $typecast_data:
      Default: ``true``
    :returns: array -- 

  .. php:method:: generateNewID ($m[, $table]) -> string

    Generates new record ID.

    :param $m:
    :param $table:
      Default: ``null``
    :returns: string -- 

  .. php:method:: initAction (Model $m[, $fields])

    Typecast data and return Iterator of data array.

    :param Model $m:
    :param $fields:
      Default: ``null``
    :returns: \atk4\data\Action\Iterator

  .. php:method:: insert (Model $m, $data[, $table]) -> mixed

    Inserts record in data array and returns new record ID.

    :param Model $m:
    :param $data:
    :param $table:
      Default: ``null``
    :returns: mixed -- 

  .. php:method:: jsonDecode (Field $f, $value[, $assoc]) -> mixed

    JSON decoding with proper error treatment.

    :param Field $f:
    :param $value:
    :param $assoc:
      Default: ``true``
    :returns: mixed -- 

  .. php:method:: jsonEncode (Field $f, $value) -> string

    JSON encoding with proper error treatment.

    :param Field $f:
    :param $value:
    :returns: string -- 

  .. php:method:: load (Model $m, $id[, $table])

    Loads model and returns data record.

    :param Model $m:
    :param $id:
    :param $table:
      Default: ``null``
    :returns: array|false

  .. php:method:: prepareIterator (Model $m) -> array

    Prepare iterator.

    :param Model $m:
    :returns: array -- 

  .. php:method:: serializeLoadField (Field $f, $value) -> mixed

    Provided with a value, will perform field un-serialization. Can be used for the purposes of encryption or storing unsupported formats.

    :param Field $f:
    :param $value:
    :returns: mixed -- 

  .. php:method:: serializeSaveField (Field $f, $value) -> mixed

    Provided with a value, will perform field serialization. Can be used for the purposes of encryption or storing unsupported formats.

    :param Field $f:
    :param $value:
    :returns: mixed -- 

  .. php:method:: tryLoad (Model $m, $id[, $table])

    Tries to load model and return data record. Doesn't throw exception if model can't be loaded.

    :param Model $m:
    :param $id:
    :param $table:
      Default: ``null``
    :returns: array|false

  .. php:method:: tryLoadAny (Model $m[, $table])

    Tries to load first available record and return data record. Doesn't throw exception if model can't be loaded or there are no data records.

    :param Model $m:
    :param $table:
      Default: ``null``
    :returns: array|false

  .. php:method:: typecastLoadField (Field $f, $value) -> mixed

    Cast specific field value from the way how it's stored inside persistence to a PHP format.

    :param Field $f:
    :param $value:
    :returns: mixed -- 

  .. php:method:: typecastLoadRow (Model $m, $row) -> array

    Will convert one row of data from Persistence-specific types to PHP native types.
NOTE: Please DO NOT perform "actual" field mapping here, because data may be "aliased" from :class:`SQL` persistences or mapped depending on persistence driver.

    :param Model $m:
    :param $row:
    :returns: array -- 

  .. php:method:: typecastSaveField (Field $f, $value) -> mixed

    Prepare value of a specific field by converting it to persistence-friendly format.

    :param Field $f:
    :param $value:
    :returns: mixed -- 

  .. php:method:: typecastSaveRow (Model $m, $row) -> array

    Will convert one row of data from native PHP types into persistence types. This will also take care of the "actual" field keys. Example:.
In: [ 'name'=>' John Smith', 'age'=>30, 'password'=>'abc', 'is_married'=>true, ]
Out: [ 'first_name'=>'John Smith', 'age'=>30, 'is_married'=>1 ]

    :param Model $m:
    :param $row:
    :returns: array -- 

  .. php:method:: update (Model $m, $id, $data[, $table]) -> mixed

    Updates record in data array and returns record ID.

    :param Model $m:
    :param $id:
    :param $data:
    :param $table:
      Default: ``null``
    :returns: mixed -- 


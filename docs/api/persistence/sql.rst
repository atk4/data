SQL
===

:class:`Persistence`\SQL class.

:Qualified name: ``atk4\data\Persistence\SQL``
:Extends: :class:`Persistence`

.. php:class:: SQL

  .. php:method:: __construct ($connection[, $user, $password, $args])

    Constructor.

    :param $connection:
    :param $user:
      Default: ``null``
    :param $password:
      Default: ``null``
    :param $args:
      Default: ``[]``

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

  .. php:method:: action ($m, $type[, $args])

    Executing $model->action('update') will call this method.

    :param $m:
    :param $type:
    :param $args:
      Default: ``[]``
    :returns: \atk4\dsql\Query

  .. php:method:: add ($m[, $defaults]) -> Model

    Associate model with the data driver.

    :param $m:
    :param $defaults:
      Default: ``[]``
    :returns: :class:`Model` -- 

  .. php:method:: atomic ($f) -> mixed

    Atomic executes operations within one begin/end transaction, so if the code inside callback will fail, then all of the transaction will be also rolled back.

    :param $f:
    :returns: mixed -- 

  .. php:method:: delete (Model $m, $id)

    Deletes record from database.

    :param Model $m:
    :param $id:

  .. php:method:: disconnect ()

    Disconnect from database explicitly.


  .. php:method:: dsql ()

    Returns Query instance.

    :returns: \atk4\dsql\Query

  .. php:method:: export (Model $m[, $fields, $typecast_data]) -> array

    Export all DataSet.

    :param Model $m:
    :param $fields:
      Default: ``null``
    :param $typecast_data:
      Default: ``true``
    :returns: array -- 

  .. php:method:: expr (Model $m, $expr[, $args])

    Creates new Expression object from expression string.

    :param Model $m:
    :param $expr:
    :param $args:
      Default: ``[]``
    :returns: \atk4\dsql\Expression

  .. php:method:: getFieldSQLExpression (Field $field, Expression $expression)

    :param Field $field:
    :param Expression $expression:

  .. php:method:: initField ($q, $field)

    Adds :class:`Field` in Query.

    :param $q:
    :param $field:

  .. php:method:: initQuery ($m)

    Initializes base query for model $m.

    :param $m:
    :returns: \atk4\dsql\Query

  .. php:method:: initQueryConditions ($m, $q)

    Will apply conditions defined inside $m onto query $q.

    :param $m:
    :param $q:
    :returns: \atk4\dsql\Query

  .. php:method:: initQueryFields ($m, $q[, $fields])

    Adds model fields in Query.

    :param $m:
    :param $q:
    :param $fields:
      Default: ``null``

  .. php:method:: insert (Model $m, $data) -> mixed

    Inserts record in database and returns new record ID.

    :param Model $m:
    :param $data:
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

  .. php:method:: load (Model $m, $id) -> array

    Loads a record from model and returns a associative array.

    :param Model $m:
    :param $id:
    :returns: array -- 

  .. php:method:: loadAny (Model $m) -> array

    Loads any one record.

    :param Model $m:
    :returns: array -- 

  .. php:method:: prepareIterator (Model $m)

    Prepare iterator.

    :param Model $m:
    :returns: \PDOStatement

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

  .. php:method:: tryLoad (Model $m, $id) -> array

    Tries to load data record, but will not fail if record can't be loaded.

    :param Model $m:
    :param $id:
    :returns: array -- 

  .. php:method:: tryLoadAny (Model $m) -> array

    Tries to load any one record.

    :param Model $m:
    :returns: array -- 

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

  .. php:method:: update (Model $m, $id, $data)

    Updates record in database.

    :param Model $m:
    :param $id:
    :param $data:


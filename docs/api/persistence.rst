Persistence
===========

:class:`Persistence` class.

:Qualified name: ``atk4\data\Persistence``

.. php:class:: Persistence

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

  .. php:method:: add ($m[, $defaults]) -> Model

    Associate model with the data driver.

    :param $m:
    :param $defaults:
      Default: ``[]``
    :returns: :class:`Model` -- 

  .. php:method:: atomic ($f) -> mixed

    Atomic executes operations within one begin/end transaction. Not all persistences will support atomic operations, so by default we just don't do anything.

    :param $f:
    :returns: mixed -- 

  .. php:method:: disconnect ()

    Disconnect from database explicitly.


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

  .. php:staticmethod:: connect ($dsn[, $user, $password, $args])

    Connects database.

    :param $dsn:
    :param $user:
      Default: ``null``
    :param $password:
      Default: ``null``
    :param $args:
      Default: ``[]``


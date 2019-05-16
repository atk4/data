CSV
===

Implements persistence driver that can save data and load from :class:`CSV` file. This basic driver only offers the load/save. It does not offer conditions or id-specific operations. You can only use a single persistence object with a single file.
$p = new :class:`Persistence`\CSV('file.csv'); $m = new MyModel($p); $data = $m->:class:`export()`;
Alternatively you can write into a file. First operation you perform on the persistence will determine the mode.
$p = new :class:`Persistence`\CSV('file.csv'); $m = new MyModel($p); $m->import($data);

:Qualified name: ``atk4\data\Persistence\CSV``
:Extends: :class:`Persistence`

.. php:class:: CSV

  .. php:method:: __construct ($file)

    Constructor. Can pass array of data in parameters.

    :param $file:

  .. php:method:: __destruct ()

    Destructor. close files correctly.


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

  .. php:method:: closeFile ()

    Close :class:`CSV` file.


  .. php:method:: delete (Model $m, $id[, $table])

    Deletes record in data array.

    :param Model $m:
    :param $id:
    :param $table:
      Default: ``null``

  .. php:method:: disconnect ()

    Disconnect from database explicitly.


  .. php:method:: export (Model $m[, $fields]) -> array

    Export all DataSet.

    :param Model $m:
    :param $fields:
      Default: ``null``
    :returns: array -- 

  .. php:method:: generateNewID ($m[, $table]) -> string

    Generates new record ID.

    :param $m:
    :param $table:
      Default: ``null``
    :returns: string -- 

  .. php:method:: getLine () -> array

    Returns one line of :class:`CSV` file as array.

    :returns: array -- 

  .. php:method:: initializeHeader ($header)

    Remembers $this->header so that the data can be easier mapped.

    :param $header:

  .. php:method:: insert (Model $m, $data) -> mixed

    Inserts record in data array and returns new record ID.

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

  .. php:method:: loadAny (Model $m) -> array

    Loads any one record.

    :param Model $m:
    :returns: array -- 

  .. php:method:: loadHeader ()

    When load operation starts, this will open file and read the first line. This line is then used to identify columns.


  .. php:method:: openFile ([])

    Open :class:`CSV` file.
Override this method and open handle yourself if you want to reposition or load some extra columns on the top.

    :param $mode:
      Default: ``'r'``

  .. php:method:: prepareIterator (Model $m) -> array

    Prepare iterator.

    :param Model $m:
    :returns: array -- 

  .. php:method:: putLine ($data)

    Writes array as one record to :class:`CSV` file.

    :param $data:

  .. php:method:: saveHeader (Model $m)

    When load operation starts, this will open file and read the first line. This line is then used to identify columns.

    :param Model $m:

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

  .. php:method:: tryLoadAny (Model $m)

    Tries to load model and return data record. Doesn't throw exception if model can't be loaded.

    :param Model $m:
    :returns: array|null

  .. php:method:: typecastLoadField (Field $f, $value) -> mixed

    Cast specific field value from the way how it's stored inside persistence to a PHP format.

    :param Field $f:
    :param $value:
    :returns: mixed -- 

  .. php:method:: typecastLoadRow (Model $m, $row) -> array

    Typecasting when load data row.

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

  .. php:method:: update (Model $m, $id, $data[, $table])

    Updates record in data array and returns record ID.

    :param Model $m:
    :param $id:
    :param $data:
    :param $table:
      Default: ``null``


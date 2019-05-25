Join
====

Class description?

:Qualified name: ``atk4\data\Join``

.. php:class:: Join

  .. php:method:: __construct ([])

    Default constructor. Will copy argument into properties.

    :param $foreign_table:
      Default: ``null``

  .. php:method:: add ($object[, $defaults])

    Adds any object to owner model.

    :param $object:
    :param $defaults:
      Default: ``[]``
    :returns: object

  .. php:method:: addField ($n[, $defaults]) -> Field

    Adding field into join will automatically associate that field with this join. That means it won't be loaded from $table, but form the join instead.

    :param $n:
    :param $defaults:
      Default: ``[]``
    :returns: :class:`Field` -- 

  .. php:method:: addFields ([])

    Adds multiple fields.

    :param $fields:
      Default: ``[]``
    :returns: $this

  .. php:method:: afterUnload ()

    Clears id and save buffer.


  .. php:method:: getDesiredName () -> string

    Will use either foreign_alias or create #join_<table>.

    :returns: string -- 

  .. php:method:: hasMany ($link[, $defaults]) -> Reference_Many

    Creates reference based on the field from the join.

    :param $link:
    :param $defaults:
      Default: ``[]``
    :returns: :class:`Reference_Many` -- 

  .. php:method:: hasOne ($link[, $defaults]) -> Reference_One

    weakJoin will be attached to a current join.

    :param $link:
    :param $defaults:
      Default: ``[]``
    :returns: :class:`Reference_One` -- 

  .. php:method:: init ()

    Initialization.


  .. php:method:: join ($foreign_table[, $defaults]) -> Join

    Another join will be attached to a current join.

    :param $foreign_table:
    :param $defaults:
      Default: ``[]``
    :returns: :class:`Join` -- 

  .. php:method:: leftJoin ($foreign_table[, $defaults]) -> Join

    Another leftJoin will be attached to a current join.

    :param $foreign_table:
    :param $defaults:
      Default: ``[]``
    :returns: :class:`Join` -- 

  .. php:method:: set ($field, $value)

    Wrapper for containsOne that will associate field with join.

    :param $field:
    :param $value:
    :returns: ???Wrapper for containsMany that will associate field with join.


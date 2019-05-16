Field
=====

Class description?

:Qualified name: ``atk4\data\Field``

.. php:class:: Field

  .. php:method:: __construct ([])

    Constructor. You can pass field properties as array.

    :param $defaults:
      Default: ``[]``

  .. php:method:: __debugInfo () -> array

    Returns array with useful debug info for var_dump.

    :returns: array -- 

  .. php:method:: compare ($value) -> bool

    This method can be extended. See :class:`Model::compare` for use examples.

    :param $value:
    :returns: bool -- 

  .. php:method:: get () -> mixed

    Returns field value.

    :returns: mixed -- 

  .. php:method:: getCaption () -> string

    Returns field caption for use in UI.

    :returns: string -- 

  .. php:method:: getDSQLExpression ($expression)

    When field is used as expression, this method will be called. Universal way to convert ourselves to expression. Off-load implementation into persistence.

    :param $expression:
    :returns: Expression

  .. php:method:: isEditable () -> bool

    Returns if field should be editable in UI.

    :returns: bool -- 

  .. php:method:: isHidden () -> bool

    Returns if field should be hidden in UI.

    :returns: bool -- 

  .. php:method:: isVisible () -> bool

    Returns if field should be visible in UI.

    :returns: bool -- 

  .. php:method:: normalize ($value) -> mixed

    Depending on the type of a current field, this will perform some normalization for strict types. This method must also make sure that $f->required is respected when setting the value, e.g. you can't set value to '' if type=string and required=true.

    :param $value:
    :returns: mixed -- 

  .. php:method:: set ($value)

    Sets field value.

    :param $value:
    :returns: $this

  .. php:method:: toString ([]) -> string

    Casts field value to string.

    :param $value:
      Default: ``null``
    :returns: string -- 

  .. php:method:: useAlias () -> bool

    Should this field use alias?

    :returns: bool -- 


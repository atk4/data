Field_SQL_Expression
====================

Class description?

:Qualified name: ``atk4\data\Field_SQL_Expression``
:Extends: :class:`Field_SQL`

.. php:class:: Field_SQL_Expression

  .. php:method:: __construct ([])

    Constructor. You can pass field properties as array.

    :param $defaults:
      Default: ``[]``

  .. php:method:: __debugInfo () -> array

    Returns array with useful debug info for var_dump.

    :returns: array -- 

  .. php:method:: afterSave ($m)

    Possibly that user will attempt to insert values here. If that is the case, then we would need to inject it into related hasMany relationship.

    :param $m:

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

    When field is used as expression, this method will be called.

    :param $expression:
    :returns: \atk4\dsql\Expression

  .. php:method:: init ()

    Initialization.


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

    SQL fields are allowed to have expressions inside of them.

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

    Should this field use alias? Expression fields always need alias.

    :returns: bool -- 


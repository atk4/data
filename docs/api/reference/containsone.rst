ContainsOne
===========

:class:`ContainsOne` reference.

:Qualified name: ``atk4\data\Reference\ContainsOne``
:Extends: :class:`Reference`

.. php:class:: ContainsOne

  .. php:method:: __construct ($link)

    Default constructor. Will copy argument into properties.

    :param $link:

  .. php:method:: __debugInfo () -> array

    Returns array with useful debug info for var_dump.

    :returns: array -- 

  .. php:method:: getDesiredName () -> string

    Will use #ref_<link>.

    :returns: string -- 

  .. php:method:: getModel ([]) -> Model

    Returns destination model that is linked through this reference. Will apply necessary conditions.

    :param $defaults:
      Default: ``[]``
    :returns: :class:`Model` -- 

  .. php:method:: init ()

    :class:`Reference`\ContainsOne will also add a field corresponding to 'our_field' unless it exists of course.


  .. php:method:: ref ([]) -> Model

    Returns referenced model with loaded data record.

    :param $defaults:
      Default: ``[]``
    :returns: :class:`Model` -- 

  .. php:method:: refModel ([]) -> Model

    Returns referenced model without any extra conditions. Ever when extended must always respond with :class:`Model` that does not look into current record or scope.

    :param $defaults:
      Default: ``[]``
    :returns: :class:`Model` -- 


HasOne
======

:class:`Reference`\HasOne class.

:Qualified name: ``atk4\data\Reference\HasOne``
:Extends: :class:`Reference`

.. php:class:: HasOne

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

    :class:`Reference_One` will also add a field corresponding to 'our_field' unless it exists of course.


  .. php:method:: ref ([]) -> Model

    If owner model is loaded, then return referenced model with respective record loaded.
If owner model is not loaded, then return referenced model with condition set. This can happen in case of deep traversal $m->ref('Many')->ref('one_id'), for example.

    :param $defaults:
      Default: ``[]``
    :returns: :class:`Model` -- 

  .. php:method:: refModel ([]) -> Model

    Returns referenced model without any extra conditions. Ever when extended must always respond with :class:`Model` that does not look into current record or scope.

    :param $defaults:
      Default: ``[]``
    :returns: :class:`Model` -- 


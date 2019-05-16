Reference
=========

:class:`Reference` implements a link between one model and another. The basic components for a reference is ability to generate the destination model, which is returned through :class:`getModel()` and that's pretty much it.
It's possible to extend the basic reference with more meaningful references.

:Qualified name: ``atk4\data\Reference``

.. php:class:: Reference

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

    Initialization.


  .. php:method:: ref ([]) -> Model

    Returns referenced model without any extra conditions. However other relationship types may override this to imply conditions.

    :param $defaults:
      Default: ``[]``
    :returns: :class:`Model` -- 

  .. php:method:: refModel ([]) -> Model

    Returns referenced model without any extra conditions. Ever when extended must always respond with :class:`Model` that does not look into current record or scope.

    :param $defaults:
      Default: ``[]``
    :returns: :class:`Model` -- 


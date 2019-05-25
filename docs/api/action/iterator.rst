Iterator
========

Class Array_ is returned by $model->action(). Compatible with DSQL to a certain point as it implements specific actions such as :class:`getOne()` or :class:`get()`.

:Qualified name: ``atk4\data\Action\Iterator``

.. php:class:: Iterator

  .. php:method:: __construct (array $data)

    :class:`Iterator` constructor.

    :param array $data:

  .. php:method:: count ()

    Counts number of rows and replaces our generator with just a single number.

    :returns: $this

  .. php:method:: get () -> array

    Return all data inside array.

    :returns: array -- 

  .. php:method:: getOne () -> mixed

    Return one value from one row of data.

    :returns: mixed -- 

  .. php:method:: getRow () -> array

    Return one row of data.

    :returns: array -- 

  .. php:method:: like ($field, $value)

    Applies FilterIterator condition imitating the sql LIKE operator - $field LIKE $value% | $value% | $value.

    :param $field:
    :param $value:
    :returns: $this

  .. php:method:: limit ($cnt[, $shift])

    Limit :class:`Iterator`.

    :param $cnt:
    :param $shift:
      Default: ``0``
    :returns: $this

  .. php:method:: order ($fields)

    Applies sorting on :class:`Iterator`.

    :param $fields:
    :returns: $this

  .. php:method:: where ($field, $value)

    Applies FilterIterator making sure that values of $field equal to $value.

    :param $field:
    :param $value:
    :returns: $this


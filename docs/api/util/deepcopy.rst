DeepCopy
========

Class :class:`DeepCopy` implements copying records between two models.
$dc = new DeepCopy();
$dc->from($user); $dc->to(new ArchivedUser()); $dc->with('AuditLog'); $dc->:class:`copy()`;

:Qualified name: ``atk4\data\Util\DeepCopy``

.. php:class:: DeepCopy

  .. php:method:: copy () -> Model

    Copy records.

    :returns: :class:`Model` -- Destination model

  .. php:method:: excluding (array $exclusions)

    Specifies which fields shouldn't be copied. May also contain arrays for related entries. ->excluding(['name', 'address_id'=>['city']]);.

    :param array $exclusions:
    :returns: $this

  .. php:method:: from (Model $source)

    Set model from which to copy records.

    :param Model $source:
    :returns: $this

  .. php:method:: to (Model $destination)

    Set model in which to copy records into.

    :param Model $destination:
    :returns: $this

  .. php:method:: with (array $references)

    Set references to copy.

    :param array $references:
    :returns: $this


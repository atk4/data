HasOne_SQL
==========

:class:`Reference`\HasOne_SQL class.

:Qualified name: ``atk4\data\Reference\HasOne_SQL``
:Extends: :class:`HasOne`

.. php:class:: HasOne_SQL

  .. php:method:: __construct ($link)

    Default constructor. Will copy argument into properties.

    :param $link:

  .. php:method:: __debugInfo () -> array

    Returns array with useful debug info for var_dump.

    :returns: array -- 

  .. php:method:: addField ($field[, $their_field]) -> Field_SQL_Expression

    Creates expression which sub-selects a field inside related model.
Returns Expression in case you want to do something else with it.

    :param $field:
    :param $their_field:
      Default: ``null``
    :returns: :class:`Field_SQL_Expression` -- 

  .. php:method:: addFields ([])

    Add multiple expressions by calling addField several times. Fields may contain 3 types of elements:.
[ 'name', 'surname' ] - will import those fields as-is [ 'full_name' => 'name', 'day_of_birth' => ['dob', 'type'=>'date'] ] - use alias and options [ ['dob', 'type' => 'date'] ] - use options
You may also use second param to specify parameters:
addFields(['from', 'to'], ['type' => 'date']);

    :param $fields:
      Default: ``[]``
    :param $defaults:
      Default: ``[]``
    :returns: $this

  .. php:method:: addTitle ([]) -> Field_SQL_Expression

    Add a title of related entity as expression to our field.
$order->hasOne('user_id', 'User')->:class:`addTitle()`;
This will add expression 'user' equal to ref('user_id')['name'];
This method returns newly created expression field.

    :param $defaults:
      Default: ``[]``
    :returns: :class:`Field_SQL_Expression` -- 

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

    Navigate to referenced model.

    :param $defaults:
      Default: ``[]``
    :returns: :class:`Model` -- 

  .. php:method:: refLink ([]) -> Model

    Creates model that can be used for generating sub-query actions.

    :param $defaults:
      Default: ``[]``
    :returns: :class:`Model` -- 

  .. php:method:: refModel ([]) -> Model

    Returns referenced model without any extra conditions. Ever when extended must always respond with :class:`Model` that does not look into current record or scope.

    :param $defaults:
      Default: ``[]``
    :returns: :class:`Model` -- 

  .. php:method:: withTitle ([])

    Add a title of related entity as expression to our field.
$order->hasOne('user_id', 'User')->:class:`addTitle()`;
This will add expression 'user' equal to ref('user_id')['name'];

    :param $defaults:
      Default: ``[]``
    :returns: $this


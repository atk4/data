
.. _DataSet:
.. _conditions:

======================
Conditions and DataSet
======================

.. php:class:: Model

When model is associated with the database, you can specify a default table
either explicitly or through a $table property inside a model::

    $m = new Model_User($db, 'user');
    $m->load(1);
    echo $m->get('gender');   // "M"


Following this line, you can load ANY record from the table. It's possible to
narrow down set of "loadable" records by introducing a condition::

    $m = new Model_User($db, 'user');
    $m->addCondition('gender','F');
    $m->load(1);    // exception, user with ID=1 is M

Conditions serve important role and must be used to intelligently restrict
logically accessible data for a model before you attempt the loading.

Basic Usage
===========

.. php:method:: addCondition($field, $operator = null, $value = null)

There are many ways to execute addCondition. The most basic one that will be
supported by all the drivers consists of 2 arguments or if operator is '='::

    $m->addCondition('gender', 'F');         // or
    $m->addCondition('gender', '=', 'F');

Once you add a condition, you can't get rid of it, so if you want
to preserve the state of your model, you need to use clone::

    $m = new Model_User($db, 'user');
    $girls = (clone $m)->addCondition('gender','F');

    $m->load(1);        // success
    $girls->load(1);    // exception

Operations
----------

Most database drivers will support the following additional operations::

    >, <, >=, <=, !=, in, not in, like, not like, regexp, not regexp

The operation must be specified as second argument::

    $m = new Model_User($db, 'user');
    $girls = (clone $m)->addCondition('gender', 'F');
    $not_girls = (clone $m)->addCondition('gender', '!=', 'F');

When you use 'in' or 'not in' you should pass value as array::

    $m = new Model_User($db, 'user');
    $girls_or_boys = (clone $m)->addCondition('gender', 'in', ['F', 'M']);

Multiple Conditions
-------------------

You can set multiple conditions on the same field even if they are contradicting::

    $m = new Model_User($db, 'user');
    $noone = (clone $m)
        ->addCondition('gender', 'F')
        ->addCondition('gender', 'M');

Normally, however, you would use a different fields::

    $m = new Model_User($db, 'user');
    $girl_sue = (clone $m)
        ->addCondition('gender', 'F')
        ->addCondition('name', 'Sue');

You can have as many conditions as you like.

Adding OR Conditions
--------------------

In Agile Data all conditions are additive. This is done for security - no matter
what condition you are adding, it will not allow you to circumvent previously
added condition.

You can, however, add condition that contains multiple clauses joined with OR
operator::

    $m->addCondition([
        ['name', 'John'],
        ['surname', 'Smith']
    ]);

This will add condition that will match against records with either
name=John OR surname=Smith.
If you are building multiple conditions against the same field, you can use this
format::

    $m->addCondition('name', ['John', 'Joe']);

For all other cases you can implement them with :php:meth:`Model::expr`::

    $m->addCondition($m->expr("(day([birth_date]) = day([registration_date]) or day([birth_date]) = [])", 10));

This rather unusual condition will show user records who have registered on same
date when they were born OR if they were born on 10th. (This is really silly
condition, please don't judge, if you have a better example, I'd love to hear).

Defining your classes
---------------------

Although I have used in-line addition of the arguments, normally you would want
to set those conditions inside the init() method of your model::


    class Model_Girl extends Model_User
    {
        function init(): void
        {
            parent::init();

            $this->addCondition('gender', 'F');
        }
    }

Note that the field 'gender' should be defined inside Model_User::init().

Advanced Usage
==============

Scopes
------

Using the Model::addCondition method is the basic way to limit the model scope of records. Under the hood
Agile Data utilizes a special set of classes (Condition and Scope) to apply the conditions as filters on records retrieved.
These classes can be used directly and independently from Model class to define and store Model scope.

.. php:class:: Condition

Condition represents a simple scope in a form [field, operation, value], similar to the functionality of the 
Model::addCondition method

.. php:method:: create($key, $operator = null, $value = null);

Creates condition object based on provided arguments. It acts similar to Model::addCondition

$key can be Model field name, Field object, Expression object, FALSE (interpreted as Expression('false')), TRUE (interpreted as empty condition) or an array in the form of [$key, $operator, $value]
$operator can be one of the supported operators >, <, >=, <=, !=, in, not in, like, not like, regexp, not regexp
$value can be Field object, Expression object, array (interpreted as 'any of the values') or other scalar value

If $value is omitted as argument then $operator is considered as $value and '=' is used as operator

.. php:method:: negate();

Negates the condition, e.g::

	// results in 'name is not John'
	$condition = Condition::create('name', 'John')->negate(); 

.. php:method:: setModel(?Model $model = null);

Sets the Model to use when interpreting the condition. When adding condition object to a model it is automatically asigned to the condition

.. php:method:: on(Model $model);

Sets the model of Condition to a clone of $model to avoid changes to the original object.::

   // uses the $contact model to conver the condition to human readable words
   $condition->on($contact)->toWords();

.. php:method:: toWords($asHtml = false);

Converts the condition object to human readable words. Model must be set first. Recommended is use of Condition::on method to set the model
as it clones the model object first::

	// results in 'Contact where Name is John'
	Condition::create('name', 'John')->on($contactModel)->toWords(); 

.. php:method:: validate($values);

Validates $values array if matching the condition when applied on $model. Returns array of conditions that are not met or empty if $values fit ::

	$condition = Condition::create('name', 'John');
	
	// as condition is that only contacts named John are valid
	// $result will return array($condition) as $condition is not met
	// this array can be used to display validation errors
	$result = $condition->on($contactModel)->validate(['name' => 'Peter']);	

.. php:method:: find($key);

Returns array of conditions whose key property matches the $key parameter. Useful when renaming fields and updating saved scope objects

.. php:class:: Scope

Scope object has a single defined junction (AND or OR) and can contain multiple children of Condition and/or Scope objects referred to as components.
This makes creating Model scopes with deep nested conditions possible, 
e.g ((Name like 'ABC%' and Country = 'US') or (Name like 'CDE%' and (Country = 'DE' or Surname = 'XYZ')))

Scope can be created using Scope::create method from array or joining Condition objects::

   // $condition1 will be used as child-component
	$condition1 = Condition::create('name', 'like', 'ABC%');
   
   // $condition1 will be used as child-component
	$condition2 = Condition::create('country', 'US');
	
   // $scope1 is created using AND as junction and $condition1 and $condition2 as components
	$scope1 = Scope::mergeAnd($condition1, $condition2);
	
	$condition3 = Condition::create('country', 'DE');
	$condition4 = Condition::create('surname', 'XYZ');
	
   // $scope2 is created using OR as junction and $condition3 and $condition4 as components
	$scope2 = Scope::mergeOr($condition3, $condition4);

	$condition5 = Condition::create('name', 'like', 'CDE%');
	
   // $scope3 is created using AND as junction and $condition5 and $scope2 as components
	$scope3 = Scope::mergeAnd($condition5, $scope2);

   // $scope is created using OR as junction and $scope1 and $scope3 as components
	$scope = Scope::mergeOr($scope1, $scope3);
	
	
Scope is independent object not related to any model. Applying scope to model is using the Model::add method::

	$contact->add($scope); // adding scope to model
	$contact->scope()->and($conditionXYZ); // adding more conditions
	
.. php:method::	create($scopeOrArray = null, $junction = Scope::AND);

Creates a scope object from array. If scope is passed as first argument uses this as result::

	// below will create 2 conditions and join them as scope components with AND junction
	$scope1 = Scope::create([
		['name', 'like', 'ABC%'],
		['country', 'US']
	]);
	
.. php:method:: negate();

Negate method has behind the full map of conditions so any condition / scope can be negated, e.g negating '>=' results in '<', etc.
For nested scopes this method is using De Morgan's laws, e.g::

	// using $scope1 defined above
	// results in "(Name not like 'ABC%') or (Country does not equal 'US')"
	$scope1->negate();

.. php:method:: and(AbstractScope $scope);

Merge $scope into current scope using AND as junction. If current scope junction is AND simply adds a component.
If it is OR then changes it to AND and uses current scope and $scope as two sub-components

.. php:method:: or(AbstractScope $scope);

Merge $scope into current scope using OR as junction. If current scope junction is OR simply adds a component.
If it is AND then changes it to OR and uses current scope and $scope as two sub-components

.. php:method:: mergeAnd(AbstractScope $scopeA, AbstractScope $scopeB, $_ = null);

Merge number of scopes using AND as junction. Returns the resulting scope.

.. php:method:: mergeOr(AbstractScope $scopeA, AbstractScope $scopeB, $_ = null);

Merge number of scopes using OR as junction. Returns the resulting scope.

.. php:method:: merge(AbstractScope $scopeA, AbstractScope $scopeB, $junction = self::AND);

Merge two scopes using $junction. Returns the resulting scope.

.. php:method:: find($key);

Returns array of conditions whose key property matches the $key parameter.
The scope conditions use field names to identify which field the condition applies to.
In some cases the scope object can be saved in database, etc. and it needs to be kept up-to-date with migrations on the model
when field names have been changed. 
The `AbstractScope::find` method can be used to identify if the scope contains a nested condition on the old field name so that the
saved scope can be updated accordingly.

.. php:method:: addComponent(AbstractScope $scope);

Add a component (sub-scope or sub-condition) to the scope. The scope junction (either AND or OR remains same)

.. php:method:: getAllComponents();

Get array of all scope components.

.. php:method:: getActiveComponents();

Get array of only active scope components.

.. php:method:: peel();

Peels off nested scopes with single contained component. Useful for converting (((field = value))) to field = value.

.. php:method:: clear();

Clears the scope from components.

.. php:method:: any();

Checks if scope components are joined by OR

.. php:method:: all();

Checks if scope components are joined by AND

Conditions on Referenced Models
-------------------------------

Agile Data allows for adding conditions on related models for retrieval of type 'model has references where'.

Setting conditions on references can be done utilizing the Model::refLink method but there is a shorthand format 
directly integrated with addCondition method using "/" to chain the reference names::

	$contact->addCondition('company/country', 'US');
	
This will limit the $contact model to those whose company is in US.
'company' is the name of the reference in $contact model and 'country' is a field in the referenced model.

If a condition must be set directly on the existence or number of referenced records the special symbol "#" can be
utilized to indicate the condition is on the number of records::

	$contact->addCondition('company/tickets/#', '>', 3);
	
This will limit the $contact model to those whose company have more than 3 tickets.
'company' and 'tickets' are the name of the chained references ('company' is a reference in the $contact model and
'tickets' is a reference in Company model)

For applying conditions on existence of records the '?' (has any) and '!' (doesn't have any) special symbols can be used.
Although it is similar in functionality to checking ('company/tickets/#', '>', 0) or ('company/tickets/#', '=', 0)
'?' and '!' special symbols use optimized query and are much faster::

	// Contact whose company has any tickets
	$contact->addCondition('company/tickets/?');

	// Contact whose company doesn't have any tickets
	$contact->addCondition('company/tickets/!');

Condition Value Placeholder
---------------------------

Condition class enables defining placeholder for a condition value. This functionlity can be useful by defining a single scope object
which can be applied with different conditions depending on environment factors (like current user, etc)
E.g when defining access to record using scope we may want to define thatuser has access to the record if he/she created it::

	// First we register the placeholder using an anonymous function as value
	Condition::registerPlaceholder('__USER__', [
    	'label' => 'User', // the value that will be used by toWords method
    	'value' => function(Condition $condition) {
    		return $this->app->user; // the current user logged into the system
    	},
    ]);
    
    // Then we can use the placeholder in conditions
    // This will limit the records to those created by the user
    $condition = Condition::create('created_by', '__USER__');
	

Vendor-dependent logic
======================

There are many other ways to set conditions, but you must always check if they
are supported by the driver that you are using.

Field Matching
--------------

Supported by: SQL   (planned for Array, Mongo)

Usage::

    $m->addCondition('name', $m->getField('surname'));

Will perform a match between two fields.


Expression Matching
-------------------

Supported by: SQL   (planned for Array)

Usage::

    $m->addCondition($m->expr('[name] > [surname]');

Allow you to define an arbitrary expression to be used with fields. Values
inside [blah] should correspond to field names.


SQL Expression Matching
-----------------------

.. php:method:: expr($expression, $arguments = [])

    Basically is a wrapper to create DSQL Expression, however this will find any
    usage of identifiers inside the template that do not have a corresponding
    value inside $arguments and replace it with the field::

        $m->expr('[age] > 20'); // same as
        $m->expr('[age] > 20', ['age'=>$m->getField('age')); // same as



Supported by: SQL

Usage::

    $m->addCondition($m->expr('[age] between [min_age] and [max_age]'));

Allow you to define an arbitrary expression using SQL language.


Custom Parameters in Expressions
--------------------------------

Supported by: SQL

Usage::

    $m->addCondition(
        $m->expr('[age] between [min_age] and [max_age]'),
        ['min_age'=>10, 'max_age'=>30]
    );

Allow you to pass parameters into expressions. Those can be nested and consist
of objects as well as actions::


    $m->addCondition(
        $m->expr('[age] between [min_age] and [max_age]'),
        [
            'min_age'=>$m->action('min', ['age']),
            'max_age'=>$m->expr('(20 + [])', [20])
        ]
    );

This will result in the following condition:

.. code-block:: sql

    WHERE
        `age` between
            (select min(`age`) from `user`)
            and
            (20 + :a)

where the other 20 is passed through parameter. Refer to
http://dsql.readthedocs.io/en/develop/expressions.html for full documentation
on expressions.


Expression as first argument
----------------------------

Supported by: SQL, (Planned: Array, Mongo)

The $field of addCondition() can be passed as either an expression or any
object implementing atk4\dsql\Expressionable interface. Same logic applies
to the $value::

    $m->addCondition($m->getField('name'), '!=', $this->getField('surname'));


Using withID
============

.. php:method:: withID($id)

This method is similar to load($id) but instead of loading the specified record,
it sets condition for ID to match. Technically that saves you one query if you
do not need actual record by are only looking to traverse::

    $u = new Model_User($db);
    $books = $u->withID(20)->ref('Books');


.. _quickstart:

==========
Quickstart
==========

Before starting the code with Agile Data, I need to introduce a few important
concepts:


Basic Concepts
==============

Business Model (see :ref:`Busines Model`)
    A class you create (extended from :php:class:`Model`) that represents
    a business entity for your application. Instance of a business model
    class has three characteristic: DataSet, Active Record and Source

Source (see :ref:`Source`)
    Object representing a database connection. Linking your business model
    to a source allows it to load/save (persist) and also to define DataSet

DataSet (see :ref:`DataSet`)
    Logical set of entities defined through conditions (scope) that your
    Business Model object can access inside the Source. 

Active Record (see :ref:`Active Record`)
    Model can load individual record from DataSet, work with it and save
    it back into DataSet. While the record is loaded, we call it an Active
    Record.

Action (see :ref:`Action`)
    Operation that Model performs on all of DataSet (such as delete all
    records). Action can be implemented through a single query or multiple
    atomic operations (if database does not support multi-record queries)


Getting Started
===============

When planning your application, your "Business Model" design comes first.
Try not to think about tables, collections, joins but instead think in
terms of "Business" logic only.

Create a simple class like this::

    use atk4\data\Model;

    class Model_User extends Model {
        public $table = 'user';

        function init()
        {
            parent::init();

            $this->addField('name');
            $this->addField('email');
            $this->addField('is_email_confirmed')->type('boolean');
            $this->addField('type')->enum(['client','admin']);
        }
    }

In this example I have added few regular :ref:`Field` and defined
:ref:`Meta-data` for them.

As a second step, we should set up Data :ref:`Source`. You would
typically do that inside your application class::

    use atk4\data;
    
    $dsn = 'mysql:host=localhost;dbname=cy1';
    $username = 'username';
    $password = 'password';
    
    $pdo = new PDO($dsn, $username, $password);

    $app->db = new data\Connection\PDO\MySQL(['pdo' => $pdo]); 

Your presentation logic (page) can now associate Business Model with
Source::

    $data_set = new Model_User($app->db);

Now you can perform operations on a full data-set::

    $data_set->action('update')
        ->set('is_email_confirmed', false)
        ->execute();

Or you can set Active Record and perform operations on it directly::

    $data_set->load(1);
    $data_set['name'] = 'John';
    $data_set->save();

Those are the basic examples of the fundamental concepts.

Add More Business Objects
=========================

Your application normally uses multiple business entities, so I am going
to define them now. I will use inheritance to define Client class::

    class Model_Client extends Model_User {
        function init()
        {
            parent::init();

            $this->hasMany('Order');

            $this->addCondition('type', 'client');
        }
    }

    class Model_Order extends Model {
        public $table = 'order';

        function init()
        {
            parent::init();

            $this->hasOne('Client');
            $this->addField('description')->type('text');
            $this->addField('price')->type('money');
        }
    }

Now our business logic consists of 3 entity types. Note that "Client" model extends "User"
model and also re-uses same table. To distinguish "Client" from "Admin" we define scope by
adding condition "type=client".

The relation is defined between Client and Order. As per our design, Admin may not have
Orders and Order cannot belong to Admin.

Scope of Business Model
=======================

Your Business Model is associated with DataSet. When you create a client model, you will
now operate on a sub-set of a User DataSet::

    $client = new Model_Client($app->db);

Lets create a few clients::

    $client
        ->set(['name'=>'John', 'email'=>'john@google.com'])
        ->saveAndUnload();

    $client
        ->set(['name'=>'Peter', 'email'=>'peter@google.com'])
        ->saveAndUnload();

This adds records by setting the values of the Active Record then saving it into the
database. After save is completed Active Record will be un-loaded so the next set()
will initialize new record.

The database mapper will also automatically set type of those records in the database
to "client" because that's a condition which must be met by all members of Client DataSet.

Loading and Saving Active Record
================================

There are various ways how you can load the record::

    $client->load(1);                 // by ID
    $client->loadBy('name', 'John');  // by field
    $client->loadAny();               // first matching record

When Active Record is loaded, you can work with it's fields::

    if ($client->loaded()) {
        $client['is_email_confirmed'] = false;
        $client->save();
    }

You can also use "tryLoad" methods. Those will silently fail if record is not found::

    // Tighten our scope
    $client->addCondition('name', 'John');
    $client->tryLoadAny();

    $client['email'] = 'john@google.com';
    $client->save();

This code will insert a new record with name=John and email=john@google.com unless
it already exist, in which case only email will be updated.

Using addCondition() outside of the init() method is permitted and it will tighten
scope of your model even further. 

For further information read :ref:`Active Record`

Traversing Relations
====================

Business Model relates to other models in various ways. Traversing this relation will
return another Business Model with tightened scope::

    $client = new Model_Client($app->db);
    $client->load(1);
    $orders = $client->ref('Order');

    // similar to

    $orders = new Model_Order($app->db);
    $orders->addCondition('user_id', 1);
    
Although the code above is similar, there are two differences. First code will actually
retrieve the client from the database. If the client cannot be found load() will
throw exception. Additionally if type of user_id=1 is "admin" exception will be thrown.

The second section will not perform any queries, but potentially this can cause some
business logic issues (for example if you attempt to add new $order next).

There is a way how to address this correctly::

    $client = new Model_Client($app->db);
    $client->withID(1);
    $orders = $client->refSet('Order');

This type of traversal is different, becasue it traverses DataSet into DataSet. Perhaps
a more interesting example would be::

    $clients = new Model_Client($app->db);
    $clients->addCondition('is_email_confirmed', false);
    $orders = $clients->refSet('Order');

This gives you a sub-set of orders that contains all the records by the users who 
are clients and have not confirmed their email yet.

For further information read :ref:`Traversal`

DataSet Basic and Vendor-specific Actions
=========================================

There are various actions you can easily perform on the DataSet. In the previous
examples we used Client DataSet to perform multi-row update setting "is_email_confirmed"
to false, but there are many different actions you can perform. There are actions
that retrieve data and some that change data:

* Read / Query (sum, count, average, get, etc)
* Update, Delete, etc

Model does not handle actions on it's own, but the logic of building actions reside
in `$db` data source class. All the candidates for Data Source agree on standard
set of "actions" that is possible to implement in the database query language or
simulate in PHP:

* Standard Actions
  * sum, count, min, max, avg
  * update
    * set(value|action), incr(amount|action)
  * insert
    * set([ id=>[], id=>[] ]
  * delete

For a full list: :ref:`Standard Actions`

Other actions can be made available but your busines code gets a chance to
confirm support of a specific feature::

    $client = new Model_Client($app->db);

    if ($client->supports('sql')) {

        $act = $client->action();
        $act->set('is_vip', $act->expr(
            'IF ({} like "%john%", 1, 0)', 
            [$client->getElement('name')]
        )->execute();

    } else {

        foreach ($client as $row) {
            $row['is_vip'] = preg_match('/john/i',$row['name']);
            $row->save();
        }
    }

If you never plan to support NoSQL in your application, then you can simply declare::

    $client->require('sql');

And this will produce exception demanding model to be used only with SQL Data Source.

Gradually more features may be standartised and all of the database drivers will
have to either provide native support or emulate the support::

    $client = new Model_Client($app->db);

    if ($client->supports('sql')) {

        $act = $client->action();
        $act->set('is_vip', $act->expr(
            'IF ({} like "%john%", 1, 0)', 
            [$client->getElement('name')]
        )->execute();

    } elseif ($client->supports('match')) {

        $johns = clone $client;
        $johns->action('update')
            ->match('name', 'john')
            ->set('is_vip', true)
            ->execute();

        $others = clone $client;
        $others->action('update')
            ->noMatch('name', 'john')
            ->set('is_vip', true)
            ->execute();

    } else {

        foreach ($client as $row) {
            $row['is_vip'] = preg_match('/john/i',$row['name']);
            $row->save();
        }
    }




Refactoring Database and Expressions
====================================

So far our database has been rather trivial. We had "user" table and "order" table.
Now it is time to change our database structure by adding "order_item" table.
We need to move "price" field from "order" into "order_item".

However, our application already relies on $order['price'] too much and we
do not wish to refactor application now.

We can take advantage of the fact that $order['price'] can be expressed
through standard Action (sum) and rewrite our business logic like this::

    class Model_Order extends Model {
        public $table = 'order';

        function init()
        {
            parent::init();

            $this->hasOne('Client');
            $this->addField('description')->type('text');

            $this->hasMany('OrderLine');

            $this->addExpression('price')->type('money')
                ->set($this->ref('OrderLine')->sum('price'));
        }
    }

    class Model_OrderLine extends Model {
        public $table = 'order_line';

        function init()
        {
            parent::init();

            $this->hasOne('Order');
            $this->addField('item');
            $this->addField('price');
        }
    }

Now, desptie the fact that the physical "price" is gone from the "order",
the following code will still work correctly::

    foreach($client->ref('Order') as $order) {
        echo "Order: {$order['description']} for the price of {$order['price']}\n";
    }

The implementation will use sub-query support if database supports it to fetch
the price on all the items. Alternatively, the action will be executed when
field is actually accessed providing you with consistent code.

Read more about :ref:`Expressions`

SQL-specific Features 
=====================

So far the code has been vendor-agnostic and would work with any standard Data Source.
If you are only interested in SQL DataSources you can do a lot of interesting things
in your code:

Use DSQL
--------

`DSQL <http://github.com/atk4/dsql>`_ is a Query Bulider library used by SQL drivers. It is very powerful and can
perform any query. By calling :php:meth:`Model::action` of SQL database you get back an instance
of DSQL Query class and can perform a lot of interesting things with it::

    $client->requires('sql');
    $dsql = $client->action();

    $dsql->where('name', 'like', '%john%');               // name = surname
    $dsql->group('is_vip');                               // group results

    $data = $dsql->get();

`DSQL Documentation <http://dsql.readthedocs.io/en/develop/>`_ will give you further information.

Advanced examples
-----------------

Fields of a model automatically can become parts of the query. This is true for regular
fields and expressions. Same can be said for actions::

    $client->requires('sql');
    $dsql = $client->action();

    $dsql->where('name', $client->getField('surname'));   // name = surname

    $paid_orders = $client->ref('Order')->addCondition('is_paid', true)->sum();
    $due_orders = $client->ref('Order')->addCondition('is_paid', false)->sum();

    $dsql->where($dsql->expr('[] > [] * 2', [$paid_orders, $due_orders]));

    $data = $dsql->get();

You can also use addCondition() too::

    $client->requires('sql');

    $client->addCondition('name', $client->getField('surname'));   // name = surname

    $paid_orders = $client->ref('Order')->addCondition('is_paid', true)->sum();
    $due_orders = $client->ref('Order')->addCondition('is_paid', false)->sum();

    $client->addCondition($dsql->expr('[] > [] * 2', [$paid_orders, $due_orders]));

Actually addCondition is quite smilar to dsql->where(), but if name of the first
argument ('name' in example above) is actually an expression, addCondition will
handle that correctly, while where() will have no knowledge of model fields and
will add condition as-is.



<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;


/**
 * Country. 
 *
 * We will have few of them. You can lookup country by name or by code. We will also try looking up country by
 * multiple fields (e.g. code and 'is_eu') to see if those work are used as AND conditions. For instance
 * is_eu=true, code='US', should not be able to lookup the country. 
 *
 * Users is a reference. You can specify it as an array containing import data for and that will be inserted
 * recursively.
 *
 * We also introduced 'user_names' field, which will concatinate all user names for said country. It can also be
 * used when importing, simply provide a comma-separated string of user names and they will be CREATED for you.
 */
class LCountry extends \atk4\data\Model {
    public $table = 'country';

    function init() {
        parent::init();

        $this->addField('name');
        $this->addField('code');

        $this->addField('is_eu', ['type'=>'boolean']);

        $this->hasMany('Users', new LUser())
            ->addField('user_names', 'name');
    }
}

/**
 * User. 
 *
 * User has one country and may have friends. Friend is a many-to-many relationship between users.
 *
 * When importing users, you should be able to specify country using 'country_id' or using some of the 
 * lookup fields: 'country', 'country_code' or 'is_eu'.
 *
 * If ID is not specified (unlike specifying null!) we will rely on the lookup fields to try and find
 * a country. If multiple lookup fields are set, we should find a country that matches them all. If country
 * cannot be found then null should be set for country_id.
 *
 * Fiends is many-to-many relationship. We have 'user_names' field which may be similar to the one we had
 * for a country. However specifying user_names as a comma-separated value will not create any friends.
 * Instead it will look up existing records and will create "Friend" record for all of them.
 *
 * Like before Friends can also be specified as an array.
 */
class LUser extends \atk4\data\Model {
    public $table = 'user';

    function init() {
        parent::init();

        $this->addField('name');

        $this->hasOne('country_id', new LCountry())
            ->withTitle()
            ->addFields(['country_code'=>'code', 'is_eu']);

        $this->hasMany('Friends', new LFriend())
            ->addField('user_names', 'friend_name');
    }
}

/**
 * Friend is a many-to-many binder that connects User with another User.
 *
 * In our case, however, we want each friendship to be reciprocial. If John
 * is a friend of Sue, then Sue must be a friend of John.
 *
 * To implement that, we include insert / delete handlers which would create
 * a reverse record.
 *
 * The challenge here is to make sure that those handlers are executed automatically
 * while importing User and Friends.
 */
class LFriend extends \atk4\data\Model {

    public $skip_reverse = false;

    function init() {
        parent::init();

        $this->hasOne('user_id', new User())
            ->addField('my_name', 'name');
        $this->hasOne('friend_id', new User())
            ->addField('friend_name', 'name');

        // add or remove reverse friendships
        $this->addHook('afterInsert', function($m) {
            if ($m->skip_reverse) {
                return;
            }

            $c = clone $m;
            $c->skip_reverse = true;
            $this->insert([
                'user_id'=>$m['friend_id'], 
                'friend_id'=>$m['user_id']
            ]);
        });

        $this->addHook('beforeDelete', function($m) {
            if ($m->skip_reverse) {
                return;
            }

            $c = clone $m;
            $c->skip_reverse = true;

            $c->loadBy([
                'user_id'=>$m['friend_id'],
                'friend_id'=>$m['user_id']
            ])->delete();


        });
    }
}

/**
 * @coversDefaultClass \atk4\data\Model
 *
 * ATK Data has an option to lookup ID values if their "lookup" values are specified.
 */
class LookupSQLTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testBasicLookup()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'code'=>'JN'],
                2 => ['id' => 2, 'name' => 'Peter', 'code'=>'PT'],
                3 => ['id' => 3, 'name' => 'Joe', 'code'=>'JN'],
            ], 'order' => [
                ['amount' => '20', 'user_id' => 1],
                /*
                ['amount' => '15', 'user_id' => 2],
                ['amount' => '5', 'user_id' => 1],
                ['amount' => '3', 'user_id' => 1],
                ['amount' => '8', 'user_id' => 3],
                 */
            ], ];
        $this->setDB($a);

        //$db = new Persistence_SQL($this->db->connection);
        $u = (new Model($this->db, 'user'))->addFields(['name']);
        $o = (new Model($this->db, 'order'))->addFields(['amount']);

        $o->hasOne('user_id')->withTitle()->addField('code');

        $o->save([
            'amount'=>11,
            'user_id'=>2
        ]);


        /*
        $o->insert([
            'amount'=>12,
            'user_id'=>2
        ]);

        // lookup by user title
        $o->insert([
            'amount'=>13,
            'user'=>'Peter'
        ]);

        // lookup by code
        $o->insert([
            'amount'=>14,
            'code'=>'JN'
        ]);
         */

        var_Dump($this->getDB()['order']);
    }
}

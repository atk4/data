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

        $this->addField('is_eu', ['type'=>'boolean', 'default'=>false]);

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

        $this->addField('name');
        $this->addField('is_vip', ['type'=>'boolean', 'default'=>false]);

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
    public function setUp()
    {
        parent::setUp();

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

    }

    /**
     * test various ways to import countries
     */
    public function importCountriesBasic()
    {
        $country = new LCountry($this->db);

        // should be OK, will set country name, rest of fields will be null
        $c->save('Canada');

        // adds another country
        $c->save(['Latvia', 'code'=>'LV', 'is_eu'=>true]);

        // is_eu will BLEED into this record, because save() is just set+save
        $c->save(['Estonia', 'code'=>'ES']);

        // is_eu will NOT BLEED into this record, because insert() does not make use of current model values.
        $c->insert(['Korea', 'code'=>'KR']);

        // is_eu will NOT BLEED into Japan or Russia, because import() treats all records individually
        $c->import([
            ['Japan', 'code'=>'JP'],
            ['Lithuania', 'code'=>'LT', 'is_eu'=>true],
            ['Russia', 'code'=>'KR'],
        ]);

        // Test DB contents here
        // $this->getDB()
    }

    public function importInternationalUsers()
    {
        $country = new LCountry($this->db);

        // Specifying hasMany here will perform input
        $c->insert(['Canada', 'Users'=>['Alain', ['Duncan', 'is_vip'=>true]]]);

        // Both lines will work quite similar
        $c->insert(['Latvia', 'user_names'=>'imants,juris']);
    }

    public function importInternationalFriends()
    {
        $country = new LCountry($this->db);

        // Specifying hasMany here will perform input
        $c->insert(['Canada', 'Users'=>['Alain', ['Duncan', 'is_vip'=>true]]]);


        // Inserting Users into Latvia can also specify Friends. In this case Friend name will be looked up
        $c->insert(['Latvia', 'Users'=>['Imants', ['Juris', 'friend_names'=>'Alain,Imants']]]);

        // Inserting This time explicitly specify friend attributes
        $c->insert(['UK', 'Users'=>[
            ['Romans', 'Friends'=>[
                ['friend_id'=>1], 
                ['friend_name'=>'Juris']
                ['Alain']
            ]]
        ]]);

        // BTW - Alain should have 3 friends here
    }

}

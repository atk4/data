<?php

namespace atk4\data\tests;

use atk4\data\Model;

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
 * We also introduced 'user_names' field, which will concatenate all user names for said country. It can also be
 * used when importing, simply provide a comma-separated string of user names and they will be CREATED for you.
 */
class LCountry extends Model
{
    public $table = 'country';

    public function init()
    {
        parent::init();

        $this->addField('name');
        $this->addField('code');

        $this->addField('is_eu', ['type'=>'boolean', 'default'=>false]);

        $this->hasMany('Users', new LUser())
            ->addField('user_names', ['field'=>'name', 'concat'=>',']);
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
 * Friends is many-to-many relationship. We have 'friend_names' field which may be similar to the one we had
 * for a country. However specifying friend_names as a comma-separated value will not create any friends.
 * Instead it will look up existing records and will create "Friend" record for all of them.
 *
 * Like before Friends can also be specified as an array.
 */
class LUser extends Model
{
    public $table = 'user';

    public function init()
    {
        parent::init();

        $this->addField('name');
        $this->addField('is_vip', ['type'=>'boolean', 'default'=>false]);

        $this->hasOne('country_id', new LCountry())
            ->withTitle()
            ->addFields(['country_code'=>'code', 'is_eu']);

        $this->hasMany('Friends', new LFriend())
            ->addField('friend_names', ['field'=>'friend_name', 'concat'=>',']);
    }
}

/**
 * Friend is a many-to-many binder that connects User with another User.
 *
 * In our case, however, we want each friendship to be reciprocal. If John
 * is a friend of Sue, then Sue must be a friend of John.
 *
 * To implement that, we include insert / delete handlers which would create
 * a reverse record.
 *
 * The challenge here is to make sure that those handlers are executed automatically
 * while importing User and Friends.
 */
class LFriend extends Model
{
    public $skip_reverse = false;
    public $table = 'friend';
    public $title_field = 'friend_name';

    public function init()
    {
        parent::init();

        $this->hasOne('user_id', new LUser())
            ->addField('my_name', 'name');
        $this->hasOne('friend_id', new LUser())
            ->addField('friend_name', 'name');

        // add or remove reverse friendships
        /*
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
         */
    }
}

/**
 * @coversDefaultClass Model
 *
 * ATK Data has an option to lookup ID values if their "lookup" values are specified.
 */
class LookupSQLTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function varexport($expression, $return = false)
    {
        $export = var_export($expression, true);
        $export = preg_replace('/^([ ]*)(.*)/m', '$1$1$2', $export);
        $array = preg_split("/\r\n|\n|\r/", $export);
        $array = preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [null, ']$1', ' => ['], $array);
        $export = implode(PHP_EOL, array_filter(['['] + $array));
        if ((bool) $return) {
            return $export;
        } else {
            echo $export;
        }
    }

    public function setUp()
    {
        parent::setUp();

        // populate database for our three models
        $this->getMigration(new LCountry($this->db))->drop()->create();
        $this->getMigration(new LUser($this->db))->drop()->create();
        $this->getMigration(new LFriend($this->db))->drop()->create();
    }

    /**
     * test various ways to import countries.
     */
    public function testImportCountriesBasic()
    {
        $c = new LCountry($this->db);

        $results = [];

        // should be OK, will set country name, rest of fields will be null
        $c->saveAndUnload('Canada');

        // adds another country, but with more fields
        $c->saveAndUnload(['Latvia', 'code'=>'LV', 'is_eu'=>true]);

        // setting field prior will affect save()
        $c['is_eu'] = true;
        $c->save(['Estonia', 'code'=>'ES']);

        // is_eu will NOT BLEED into this record, because insert() does not make use of current model values.
        $c->insert(['Korea', 'code'=>'KR']);

        // is_eu will NOT BLEED into Japan or Russia, because import() treats all records individually
        $c->import([
            ['Japan', 'code'=>'JP'],
            ['Lithuania', 'code'=>'LT', 'is_eu'=>true],
            ['Russia', 'code'=>'RU'],
        ]);

        $this->assertEquals([
            'country' => [
                1 => [
                    'id'    => '1',
                    'name'  => 'Canada',
                    'code'  => null,
                    'is_eu' => '0',
                ],
                2 => [
                    'id'    => '2',
                    'name'  => 'Latvia',
                    'code'  => 'LV',
                    'is_eu' => '1',
                ],
                3 => [
                    'id'    => '3',
                    'name'  => 'Estonia',
                    'code'  => 'ES',
                    'is_eu' => '1',
                ],
                4 => [
                    'id'    => '4',
                    'name'  => 'Korea',
                    'code'  => 'KR',
                    'is_eu' => '0',
                ],
                5 => [
                    'id'    => '5',
                    'name'  => 'Japan',
                    'code'  => 'JP',
                    'is_eu' => '0',
                ],
                6 => [
                    'id'    => '6',
                    'name'  => 'Lithuania',
                    'code'  => 'LT',
                    'is_eu' => '1',
                ],
                7 => [
                    'id'    => '7',
                    'name'  => 'Russia',
                    'code'  => 'RU',
                    'is_eu' => '0',
                ],
            ],
        ], $this->getDB('country'));
    }

    public function testImportInternationalUsers()
    {
        $c = new LCountry($this->db);

        // Specifying hasMany here will perform input
        $c->insert(['name'=>'Canada', 'Users'=>['Alain', ['Duncan', 'is_vip'=>true]]]);

        // Both lines will work quite similar
        $c->insert(['Latvia', 'user_names'=>'imants,juris']);

        //$this->varexport($this->getDB(['country','user']));
        $this->assertEquals([
            'country' => [
                1 => [
                    'id'    => '1',
                    'name'  => 'Canada',
                    'code'  => null,
                    'is_eu' => '0',
                ],
                2 => [
                    'id'    => '2',
                    'name'  => 'Latvia',
                    'code'  => null,
                    'is_eu' => '0',
                ],
            ],
            'user' => [
                1 => [
                    'id'         => '1',
                    'name'       => 'Alain',
                    'is_vip'     => '0',
                    'country_id' => '1',
                ],
                2 => [
                    'id'         => '2',
                    'name'       => 'Duncan',
                    'is_vip'     => '1',
                    'country_id' => '1',
                ],
                3 => [
                    'id'         => '3',
                    'name'       => 'imants',
                    'is_vip'     => '0',
                    'country_id' => '2',
                ],
                4 => [
                    'id'         => '4',
                    'name'       => 'juris',
                    'is_vip'     => '0',
                    'country_id' => '2',
                ],
            ],
        ], $this->getDB(['country', 'user']));
    }

    /*
     *
     * TODO - that's left for hasMTM implementation..., to be coming later
     *
    public function testImportInternationalFriends()
    {
        $c = new LCountry($this->db);

        // Specifying hasMany here will perform input
        $c->insert(['Canada', 'Users'=>['Alain', ['Duncan', 'is_vip'=>true]]]);

        // Inserting Users into Latvia can also specify Friends. In this case Friend name will be looked up
        $c->insert(['Latvia', 'Users'=>['Imants', ['Juris', 'friend_names'=>'Alain,Imants']]]);

        // Inserting This time explicitly specify friend attributes
        $c->insert(['UK', 'Users'=>[
            ['Romans', 'Friends'=>[
                ['friend_id'=>1],
                ['friend_name'=> 'Juris'],
                'Alain',
            ]],
        ]]);

        // BTW - Alain should have 3 friends here
    }
    */
}

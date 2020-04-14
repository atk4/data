<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Model\Scope\Condition;
use atk4\data\Model\Scope\Scope;
use atk4\dsql\Expression;

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
class SCountry extends Model
{
    public $table = 'country';

    public $caption = 'Country';

    public function init()
    {
        parent::init();

        $this->addField('name');
        $this->addField('code');

        $this->addField('is_eu', ['type'=>'boolean', 'default'=>false]);

        $this->hasMany('Users', new SUser())
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
class SUser extends Model
{
    public $table = 'user';

    public $caption = 'User';

    public function init()
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
        $this->addField('is_vip', ['type'=>'boolean', 'default'=>false]);

        $this->hasOne('country_id', new SCountry())
        ->withTitle()
        ->addFields(['country_code'=>'code', 'is_eu']);
    }
}

/**
 * @coversDefaultClass \atk4\data\Model
 */
class ScopeTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    protected $user;
    protected $country;

    public function setUp()
    {
        parent::setUp();

        $this->country = new SCountry($this->db);

        $this->getMigrator($this->country)->drop()->create();

        // Specifying hasMany here will perform input
        $this->country->import([
            ['Canada', 'code'=>'CA'],
            ['Latvia', 'code'=>'LV'],
            ['Japan', 'code'=>'JP'],
            ['Lithuania', 'code'=>'LT', 'is_eu'=>true],
            ['Russia', 'code'=>'RU'],
            ['France', 'code'=>'FR'],
            ['Brazil', 'code'=>'BR'],
        ]);

        $this->user = new SUser($this->db);

        $this->getMigrator($this->user)->drop()->create();

        $this->user->import([
            ['name'       => 'John', 'surname' => 'Smith', 'country_code'=>'CA'],
            ['name'       => 'Jane', 'surname' => 'Doe', 'country_code'=>'LV'],
            ['name'       => 'Alain', 'surname' => 'Prost', 'country_code'=>'FR'],
            ['name'       => 'Aerton', 'surname' => 'Senna', 'country_code'=>'BR'],
            ['name'       => 'Rubens', 'surname' => 'Barichello', 'country_code'=>'BR'],
        ]);
    }

    public function testCondition()
    {
        $user = clone $this->user;

        $condition = Condition::create('name', 'John');

        $user->add($condition);

        $user->loadAny();

        $this->assertEquals('Smith', $user['surname']);
    }

    public function testContitionToWords()
    {
        $user = clone $this->user;

        $condition = Condition::create(new Expression('false'));

        $this->assertEquals('expression \'false\'', $condition->on($user)->toWords());

        $condition = Condition::create('country_id/code', 'US');

        $this->assertEquals('User that has reference country_id where Code is equal to \'US\'', $condition->on($user)->toWords());

        $condition = Condition::create('country_id', 2);

        $this->assertEquals('Country Id is equal to \'Latvia\'', $condition->on($user)->toWords());

        if ($this->driverType == 'sqlite') {
            $condition = Condition::create('name', $user->expr('[surname]'));

            $this->assertEquals('Name is equal to expression \'"surname"\'', $condition->on($user)->toWords());
        }

        $condition = Condition::create('country_id', null);

        $this->assertEquals('Country Id is equal to empty', $condition->on($user)->toWords());

        $condition = Condition::create('name', '>', 'Test');

        $this->assertEquals('Name is greater than \'Test\'', $condition->on($user)->toWords());

        $condition = Condition::create('country_id', 2)->negate();

        $this->assertEquals('Country Id is not equal to \'Latvia\'', $condition->on($user)->toWords());

        $condition = Condition::create($user->getField('surname'), $user->getField('name'));

        $this->assertEquals('Surname is equal to User Name', $condition->on($user)->toWords());

        $country = clone $this->country;

        $country->addCondition('Users/#');

        $this->assertEquals('Country that has reference Users where any referenced record exists', $country->scope()->toWords());
        
        $country = clone $this->country;
        
        $country->addCondition('Users/!');
        
        $this->assertEquals('Country that has reference Users where no referenced records exist', $country->scope()->toWords());
    }
    
    public function testContitionOnReferencedRecords()
    {
        $country = clone $this->country;
        
        $country->addCondition('Users/!');

        foreach ($country as $c) {
            $this->assertEmpty($c['user_names']);
        }

        $country = clone $this->country;
        
        $country->addCondition('Users/?');
        
        foreach ($country as $c) {
            $this->assertNotEmpty($c['user_names']);
        }
        
        $country = clone $this->country;
        
        $country->addCondition('Users/#', '>', 1);
        
        foreach ($country as $c) {
            $this->assertEquals('BR', $c['code']);
        }
    }

    public function testConditionValuePlaceholder()
    {
        $user = clone $this->user;

        Condition::registerValuePlaceholder('__PERSPECTIVE__', [
            'label' => 'User Perspective',
            'value' => 1,
        ]);

        $condition = Condition::create('country_id', '__PERSPECTIVE__');

        $this->assertEquals('Country Id is equal to \'User Perspective\'', $condition->on($user)->toWords());

        $user->add($condition);

        $this->assertEquals(1, $user->loadAny()->id);

        Condition::registerValuePlaceholder('__PERSPECTIVE__', [
            'label' => 'User Perspective',
            'value' => function (Condition $condition) {
                $condition->deactivate();

                return null;
            },
        ]);

        $condition = Condition::create('id', '__PERSPECTIVE__');

        $this->assertEmpty($condition->on($user)->toArray());
    }

    public function testScope()
    {
        $user = clone $this->user;

        $condition1 = Condition::create('name', 'John');
        $condition2 = Condition::create('country_code', 'CA');

        $condition3 = Condition::create('surname', 'Doe');
        $condition4 = Condition::create('country_code', 'LV');

        $scope1 = Scope::mergeAnd($condition1, $condition2);
        $scope2 = Scope::mergeAnd($condition3, $condition4);

        $scope = Scope::mergeOr($scope1, $scope2);

        $this->assertEquals(Scope::OR, $scope->getJunction());

        $this->assertEquals('(Name is equal to \'John\' and Code is equal to \'CA\') or (Surname is equal to \'Doe\' and Code is equal to \'LV\')', $scope->on($user)->toWords());

        $user->add($scope);

        $this->assertEquals(2, count($user->export()));

        $this->assertEquals($scope->on($user)->toWords(), $user->scope()->toWords());

        $condition5 = Condition::create('country_code', 'BR');

        $scope = Scope::mergeOr($scope, $condition5);

        $this->assertEquals('((Name is equal to \'John\' and Code is equal to \'CA\') or (Surname is equal to \'Doe\' and Code is equal to \'LV\')) or Code is equal to \'BR\'', $scope->on($user)->toWords());

        $user = clone $this->user;

        $user->add($scope);

        $this->assertEquals(4, count($user->export()));
    }

    public function testScopeToWords()
    {
        $user = clone $this->user;

        $condition1 = Condition::create('name', 'Alain');
        $condition2 = Condition::create('country_code', 'CA');

        $scope1 = Scope::mergeAnd($condition1, $condition2);
        $condition3 = Condition::create('surname', 'Prost')->negate();

        $scope = Scope::mergeAnd($scope1, $condition3);

        $this->assertEquals('(Name is equal to \'Alain\' and Code is equal to \'CA\') and Surname is not equal to \'Prost\'', $scope->on($user)->toWords());
    }

    public function testFind()
    {
        $user = clone $this->user;

        $condition1 = Condition::create('name', 'Alain');
        $condition2 = Condition::create('country_code', 'CA');

        $scope1 = Scope::mergeAnd($condition1, $condition2);
        $condition3 = Condition::create('surname', 'Prost')->negate();

        $scope = Scope::mergeAnd($scope1, $condition3);

        foreach ($scope->find('name') as $condition) {
            $condition->key = 'surname';
        }

        $this->assertEquals('(Surname is equal to \'Alain\' and Code is equal to \'CA\') and Surname is not equal to \'Prost\'', $scope->on($user)->toWords());
    }

    public function testNegate()
    {
        $user = clone $this->user;

        $condition1 = Condition::create('name', '!=', 'Alain');
        $condition2 = Condition::create('country_code', '!=', 'FR');

        $scope = Scope::mergeOr($condition1, $condition2)->negate();

        $user->add($scope);

        foreach ($user as $u) {
            $this->assertTrue($u['name'] == 'Alain' && $u['country_code'] == 'FR');
        }
    }

    public function testAnd()
    {
        $user = clone $this->user;

        $condition1 = Condition::create('name', 'Alain');
        $condition2 = Condition::create('country_code', 'FR');

        $scope = Scope::mergeAnd($condition1, $condition2);

        $scope->or(Condition::create('name', 'John'));

        $this->assertEquals('(Name is equal to \'Alain\' and Code is equal to \'FR\') or Name is equal to \'John\'', $scope->on($user)->toWords());
    }

    public function testOr()
    {
        $user = clone $this->user;

        $condition1 = Condition::create('name', 'Alain');
        $condition2 = Condition::create('country_code', 'FR');

        $scope = Scope::mergeOr($condition1, $condition2);

        $scope->and(Condition::create('name', 'John'));

        $this->assertEquals('(Name is equal to \'Alain\' or Code is equal to \'FR\') and Name is equal to \'John\'', $scope->on($user)->toWords());
    }

    public function testMerge()
    {
        $user = clone $this->user;

        $condition1 = Condition::create('name', 'Alain');
        $condition2 = Condition::create('country_code', 'FR');

        $scope = Scope::merge($condition1, $condition2);

        $this->assertEquals('Name is equal to \'Alain\' and Code is equal to \'FR\'', $scope->on($user)->toWords());
    }

    public function testVarExport()
    {
        $user = clone $this->user;

        $condition1 = Condition::create('name', 'Alain');
        $condition2 = Condition::create('country_code', 'FR');

        $scope = Scope::merge($condition1, $condition2);

        eval('$resurected = '.var_export($scope, true).';');

        $this->assertEquals($scope, $resurected);
    }

    public function testActiveEmpty()
    {
        $user = clone $this->user;

        $condition1 = Condition::create('name', 'Alain');
        $condition2 = Condition::create('country_code', 'FR');

        $scope = Scope::merge($condition1, $condition2);

        $scope->deactivate();

        $this->assertFalse($scope->isEmpty());

        $this->assertFalse($scope->isActive());

        $this->assertEmpty($scope->on($user)->toWords());

        $scope->activate();

        $this->assertTrue($scope->isActive());

        $condition1 = Condition::create('name', 'Alain')->deactivate();
        $condition2 = Condition::create('country_code', 'FR')->deactivate();

        $scope = Scope::merge($condition1, $condition2);

        $this->assertFalse($scope->isActive());

        $this->assertTrue($scope->isEmpty());
    }

//     public function testValuesToScopeValidation()
//     {
//         $user = clone $this->user;

//         $condition1 = Condition::create('name', 'James');
//         $condition2 = Condition::create('surname', 'Smith');

//         $scope = Scope::mergeAnd($condition1, $condition2);

//         foreach ($scope->on($user)->validate([
//             'name' => 'John',
//             'surname' => 'Smith',
//         ]) as $failedCondition) {
//             $this->assertEquals('Name is equal to \'James\'', $failedCondition->on($user)->toWords());
//         }

//         $this->assertEmpty($condition1->validate($user, [
//             'name' => 'James',
//             'surname' => 'Arthur',
//         ]));
//     }
}

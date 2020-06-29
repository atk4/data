<?php

declare(strict_types=1);

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Model\Scope\Condition;
use atk4\data\Model\Scope\Scope;
use atk4\dsql\Expression;

class SCountry extends Model
{
    public $table = 'country';

    public $caption = 'Country';

    public function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('code');

        $this->addField('is_eu', ['type' => 'boolean', 'default' => false]);

        $this->hasMany('Users', new SUser())
            ->addField('user_names', ['field' => 'name', 'concat' => ',']);
    }
}

class SUser extends Model
{
    public $table = 'user';

    public $caption = 'User';

    public function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
        $this->addField('is_vip', ['type' => 'boolean', 'default' => false]);

        $this->hasOne('country_id', new SCountry())
            ->withTitle()
            ->addFields(['country_code' => 'code', 'is_eu']);
    }
}

class ScopeTest extends \atk4\schema\PhpunitTestCase
{
    protected $user;
    protected $country;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = new SCountry($this->db);

        $this->getMigrator($this->country)->drop()->create();

        // Specifying hasMany here will perform input
        $this->country->import([
            ['name' => 'Canada', 'code' => 'CA'],
            ['name' => 'Latvia', 'code' => 'LV'],
            ['name' => 'Japan', 'code' => 'JP'],
            ['name' => 'Lithuania', 'code' => 'LT', 'is_eu' => true],
            ['name' => 'Russia', 'code' => 'RU'],
            ['name' => 'France', 'code' => 'FR'],
            ['name' => 'Brazil', 'code' => 'BR'],
        ]);

        $this->user = new SUser($this->db);

        $this->getMigrator($this->user)->drop()->create();

        $this->user->import([
            ['name' => 'John', 'surname' => 'Smith', 'country_code' => 'CA'],
            ['name' => 'Jane', 'surname' => 'Doe', 'country_code' => 'LV'],
            ['name' => 'Alain', 'surname' => 'Prost', 'country_code' => 'FR'],
            ['name' => 'Aerton', 'surname' => 'Senna', 'country_code' => 'BR'],
            ['name' => 'Rubens', 'surname' => 'Barichello', 'country_code' => 'BR'],
        ]);
    }

    public function testCondition()
    {
        $user = clone $this->user;

        $condition = new Condition('name', 'John');

        $user->add($condition);

        $user->loadAny();

        $this->assertEquals('Smith', $user->get('surname'));
    }

    public function testContitionToWords()
    {
        $user = clone $this->user;

        $condition = new Condition(new Expression('false'));

        $this->assertEquals('expression \'false\'', $condition->on($user)->toWords());

        $condition = new Condition('country_id/code', 'US');

        $this->assertEquals('User that has reference Country Id where Code is equal to \'US\'', $condition->on($user)->toWords());

        $condition = new Condition('country_id', 2);

        $this->assertEquals('Country Id is equal to \'Latvia\'', $condition->on($user)->toWords());

        if ($this->driverType == 'sqlite') {
            $condition = new Condition('name', $user->expr('[surname]'));

            $this->assertEquals('Name is equal to expression \'"surname"\'', $condition->on($user)->toWords());
        }

        $condition = new Condition('country_id', null);

        $this->assertEquals('Country Id is equal to empty', $condition->on($user)->toWords());

        $condition = new Condition('name', '>', 'Test');

        $this->assertEquals('Name is greater than \'Test\'', $condition->on($user)->toWords());

        $condition = (new Condition('country_id', 2))->negate();

        $this->assertEquals('Country Id is not equal to \'Latvia\'', $condition->on($user)->toWords());

        $condition = new Condition($user->getField('surname'), $user->getField('name'));

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
        $user = clone $this->user;

        $user->addCondition('country_id/code', 'LV');

        $this->assertEquals(1, $user->action('count')->getOne());

        foreach ($user as $u) {
            $this->assertEquals('LV', $u->get('country_code'));
        }

        $country = clone $this->country;

        // countries with no users
        $country->addCondition('Users/!');

        foreach ($country as $c) {
            $this->assertEmpty($c->get('user_names'));
        }

        $country = clone $this->country;

        // countries with any user
        $country->addCondition('Users/?');

        foreach ($country as $c) {
            $this->assertNotEmpty($c->get('user_names'));
        }

        $country = clone $this->country;

        // countries with more than one user
        $country->addCondition('Users/#', '>', 1);

        foreach ($country as $c) {
            $this->assertEquals('BR', $c->get('code'));
        }
    }

    public function testConditionValuePlaceholder()
    {
        $user = clone $this->user;

        Condition::registerPlaceholder('__PERSPECTIVE__', [
            'label' => 'User Perspective',
            'value' => 1,
        ]);

        $condition = new Condition('country_id', '__PERSPECTIVE__');

        $this->assertEquals('Country Id is equal to \'User Perspective\'', $condition->on($user)->toWords());

        $user->add($condition);

        $this->assertEquals(1, $user->loadAny()->id);

        Condition::registerPlaceholder('__PERSPECTIVE__', [
            'label' => 'User Perspective',
            'value' => function (Condition $condition) {
                $condition->deactivate();

                return null;
            },
        ]);

        $condition = new Condition('id', '__PERSPECTIVE__');

        $this->assertEmpty($condition->on($user)->toArray());
    }

    public function testScope()
    {
        $user = clone $this->user;

        $condition1 = new Condition('name', 'John');
        $condition2 = new Condition('country_code', 'CA');

        $condition3 = new Condition('surname', 'Doe');
        $condition4 = new Condition('country_code', 'LV');

        $scope1 = Scope::mergeAnd($condition1, $condition2);
        $scope2 = Scope::mergeAnd($condition3, $condition4);

        $scope = Scope::mergeOr($scope1, $scope2);

        $this->assertEquals(Scope::OR, $scope->getJunction());

        $this->assertEquals('(Name is equal to \'John\' and Code is equal to \'CA\') or (Surname is equal to \'Doe\' and Code is equal to \'LV\')', $scope->on($user)->toWords());

        $user->add($scope);

        $this->assertEquals(2, count($user->export()));

        $this->assertEquals($scope->on($user)->toWords(), $user->scope()->toWords());

        $condition5 = new Condition('country_code', 'BR');

        $scope = Scope::mergeOr($scope, $condition5);

        $this->assertEquals('((Name is equal to \'John\' and Code is equal to \'CA\') or (Surname is equal to \'Doe\' and Code is equal to \'LV\')) or Code is equal to \'BR\'', $scope->on($user)->toWords());

        $user = clone $this->user;

        $user->add($scope);

        $this->assertEquals(4, count($user->export()));
    }

    public function testScopeToWords()
    {
        $user = clone $this->user;

        $condition1 = new Condition('name', 'Alain');
        $condition2 = new Condition('country_code', 'CA');

        $scope1 = Scope::mergeAnd($condition1, $condition2);
        $condition3 = (new Condition('surname', 'Prost'))->negate();

        $scope = Scope::mergeAnd($scope1, $condition3);

        $this->assertEquals('(Name is equal to \'Alain\' and Code is equal to \'CA\') and Surname is not equal to \'Prost\'', $scope->on($user)->toWords());
    }

    public function testFind()
    {
        $user = clone $this->user;

        $condition1 = new Condition($user->getField('name'), 'Alain');
        $condition2 = new Condition('country_code', 'CA');

        $scope1 = Scope::mergeAnd($condition1, $condition2);
        $condition3 = (new Condition('surname', 'Prost'))->negate();

        $scope = Scope::mergeAnd($scope1, $condition3);

        foreach ($scope->find('name') as $condition) {
            $condition->key = 'surname';
        }

        $this->assertEquals('(Surname is equal to \'Alain\' and Code is equal to \'CA\') and Surname is not equal to \'Prost\'', $scope->on($user)->toWords());
    }

    public function testNegate()
    {
        $user = clone $this->user;

        $condition1 = new Condition('name', '!=', 'Alain');
        $condition2 = new Condition('country_code', '!=', 'FR');

        $scope = Scope::mergeOr($condition1, $condition2)->negate();

        $user->add($scope);

        foreach ($user as $u) {
            $this->assertTrue($u->get('name') == 'Alain' && $u->get('country_code') == 'FR');
        }
    }

    public function testAnd()
    {
        $user = clone $this->user;

        $condition1 = new Condition('name', 'Alain');
        $condition2 = new Condition('country_code', 'FR');

        $scope = Scope::mergeAnd($condition1, $condition2);

        $scope->or(new Condition('name', 'John'));

        $this->assertEquals('(Name is equal to \'Alain\' and Code is equal to \'FR\') or Name is equal to \'John\'', $scope->on($user)->toWords());
    }

    public function testOr()
    {
        $user = clone $this->user;

        $condition1 = new Condition('name', 'Alain');
        $condition2 = new Condition('country_code', 'FR');

        $scope = Scope::mergeOr($condition1, $condition2);

        $scope->and(new Condition('name', 'John'));

        $this->assertEquals('(Name is equal to \'Alain\' or Code is equal to \'FR\') and Name is equal to \'John\'', $scope->on($user)->toWords());
    }

    public function testMerge()
    {
        $user = clone $this->user;

        $condition1 = new Condition('name', 'Alain');
        $condition2 = new Condition('country_code', 'FR');

        $scope = Scope::merge($condition1, $condition2);

        $this->assertEquals('Name is equal to \'Alain\' and Code is equal to \'FR\'', $scope->on($user)->toWords());
    }

    public function testVarExport()
    {
        $user = clone $this->user;

        $condition1 = new Condition('name', 'Alain');
        $condition2 = new Condition('country_code', 'FR');

        $scope = Scope::merge($condition1, $condition2);

        eval('$resurected = ' . var_export($scope, true) . ';');

        $this->assertEquals($scope, $resurected);
    }

    public function testActiveEmpty()
    {
        $user = clone $this->user;

        $condition1 = new Condition('name', 'Alain');
        $condition2 = new Condition('country_code', 'FR');

        $scope = Scope::merge($condition1, $condition2);

        $scope->deactivate();

        $this->assertFalse($scope->isEmpty());

        $this->assertFalse($scope->isActive());

        $this->assertEmpty($scope->on($user)->toWords());

        $scope->activate();

        $this->assertTrue($scope->isActive());

        $condition1 = (new Condition('name', 'Alain'))->deactivate();
        $condition2 = (new Condition('country_code', 'FR'))->deactivate();

        $scope = Scope::merge($condition1, $condition2);

        $this->assertFalse($scope->isActive());

        $this->assertTrue($scope->isEmpty());
    }

//     public function testValuesToScopeValidation()
//     {
//         $user = clone $this->user;

//         $condition1 = new Condition('name', 'James');
//         $condition2 = new Condition('surname', 'Smith');

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

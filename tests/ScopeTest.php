<?php

declare(strict_types=1);

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Model\Scope\BasicCondition;
use atk4\data\Model\Scope\CompoundCondition;
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

        $condition = new BasicCondition('name', 'John');

        $user->scope()->add($condition);

        $user->loadAny();

        $this->assertEquals('Smith', $user->get('surname'));
    }

    public function testContitionToWords()
    {
        $user = clone $this->user;

        $condition = new BasicCondition(new Expression('false'));

        $this->assertEquals('expression \'false\'', $condition->on($user)->toWords());

        $condition = new BasicCondition('country_id/code', 'US');

        $this->assertEquals('User that has reference Country Id where Code is equal to \'US\'', $condition->on($user)->toWords());

        $condition = new BasicCondition('country_id', 2);

        $this->assertEquals('Country Id is equal to \'Latvia\'', $condition->on($user)->toWords());

        if ($this->driverType == 'sqlite') {
            $condition = new BasicCondition('name', $user->expr('[surname]'));

            $this->assertEquals('Name is equal to expression \'"surname"\'', $condition->on($user)->toWords());
        }

        $condition = new BasicCondition('country_id', null);

        $this->assertEquals('Country Id is equal to empty', $condition->on($user)->toWords());

        $condition = new BasicCondition('name', '>', 'Test');

        $this->assertEquals('Name is greater than \'Test\'', $condition->on($user)->toWords());

        $condition = (new BasicCondition('country_id', 2))->negate();

        $this->assertEquals('Country Id is not equal to \'Latvia\'', $condition->on($user)->toWords());

        $condition = new BasicCondition($user->getField('surname'), $user->getField('name'));

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

    public function testScope()
    {
        $user = clone $this->user;

        $condition1 = new BasicCondition('name', 'John');
        $condition2 = new BasicCondition('country_code', 'CA');

        $condition3 = new BasicCondition('surname', 'Doe');
        $condition4 = new BasicCondition('country_code', 'LV');

        $compoundCondition1 = CompoundCondition::mergeAnd($condition1, $condition2);
        $compoundCondition2 = CompoundCondition::mergeAnd($condition3, $condition4);

        $compoundCondition = CompoundCondition::mergeOr($compoundCondition1, $compoundCondition2);

        $this->assertEquals(CompoundCondition::OR, $compoundCondition->getJunction());

        $this->assertEquals('(Name is equal to \'John\' and Code is equal to \'CA\') or (Surname is equal to \'Doe\' and Code is equal to \'LV\')', $compoundCondition->on($user)->toWords());

        $user->scope()->add($compoundCondition);

        $this->assertSame($user, $compoundCondition->getModel());

        $this->assertEquals(2, count($user->export()));

        $this->assertEquals($compoundCondition->on($user)->toWords(), $user->scope()->toWords());

        $condition5 = new BasicCondition('country_code', 'BR');

        $compoundCondition = CompoundCondition::mergeOr($compoundCondition1, $compoundCondition2, $condition5);

        $this->assertEquals('(Name is equal to \'John\' and Code is equal to \'CA\') or (Surname is equal to \'Doe\' and Code is equal to \'LV\') or Code is equal to \'BR\'', $compoundCondition->on($user)->toWords());

        $user = clone $this->user;

        $user->scope()->add($compoundCondition);

        $this->assertEquals(4, count($user->export()));
    }

    public function testScopeToWords()
    {
        $user = clone $this->user;

        $condition1 = new BasicCondition('name', 'Alain');
        $condition2 = new BasicCondition('country_code', 'CA');

        $compoundCondition1 = CompoundCondition::mergeAnd($condition1, $condition2);
        $condition3 = (new BasicCondition('surname', 'Prost'))->negate();

        $compoundCondition = CompoundCondition::mergeAnd($compoundCondition1, $condition3);

        $this->assertEquals('(Name is equal to \'Alain\' and Code is equal to \'CA\') and Surname is not equal to \'Prost\'', $compoundCondition->on($user)->toWords());
    }

    public function testNegate()
    {
        $user = clone $this->user;

        $condition1 = new BasicCondition('name', '!=', 'Alain');
        $condition2 = new BasicCondition('country_code', '!=', 'FR');

        $condition = CompoundCondition::mergeOr($condition1, $condition2)->negate();

        $user->scope()->add($condition);

        foreach ($user as $u) {
            $this->assertTrue($u->get('name') == 'Alain' && $u->get('country_code') == 'FR');
        }
    }

    public function testAnd()
    {
        $user = clone $this->user;

        $condition1 = new BasicCondition('name', 'Alain');
        $condition2 = new BasicCondition('country_code', 'FR');

        $compoundCondition = CompoundCondition::mergeAnd($condition1, $condition2);

        $compoundCondition = CompoundCondition::mergeOr($compoundCondition, new BasicCondition('name', 'John'));

        $this->assertEquals('(Name is equal to \'Alain\' and Code is equal to \'FR\') or Name is equal to \'John\'', $compoundCondition->on($user)->toWords());
    }

    public function testOr()
    {
        $user = clone $this->user;

        $condition1 = new BasicCondition('name', 'Alain');
        $condition2 = new BasicCondition('country_code', 'FR');

        $compoundCondition = CompoundCondition::mergeOr($condition1, $condition2);

        $compoundCondition = CompoundCondition::mergeAnd($compoundCondition, new BasicCondition('name', 'John'));

        $this->assertEquals('(Name is equal to \'Alain\' or Code is equal to \'FR\') and Name is equal to \'John\'', $compoundCondition->on($user)->toWords());
    }

    public function testMerge()
    {
        $user = clone $this->user;

        $condition1 = new BasicCondition('name', 'Alain');
        $condition2 = new BasicCondition('country_code', 'FR');

        $compoundCondition = CompoundCondition::mergeAnd($condition1, $condition2);

        $this->assertEquals('Name is equal to \'Alain\' and Code is equal to \'FR\'', $compoundCondition->on($user)->toWords());
    }

    public function testDestroyEmpty()
    {
        $user = clone $this->user;

        $condition1 = new BasicCondition('name', 'Alain');
        $condition2 = new BasicCondition('country_code', 'FR');

        $compoundCondition = CompoundCondition::mergeAnd($condition1, $condition2);

        $compoundCondition->clear();

        $this->assertTrue($compoundCondition->isEmpty());

        $this->assertEmpty($compoundCondition->on($user)->toWords());
    }
}

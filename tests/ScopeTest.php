<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Model\Scope;
use Atk4\Data\Model\Scope\Condition;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;

class SCountry extends Model
{
    public $table = 'country';
    public $caption = 'Country';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('code');

        $this->addField('is_eu', ['type' => 'boolean', 'default' => false]);

        $this->hasMany('Users', ['model' => [SUser::class]])
            ->addField('user_names', ['field' => 'name', 'concat' => ',']);
    }
}

class SUser extends Model
{
    public $table = 'user';
    public $caption = 'User';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
        $this->addField('is_vip', ['type' => 'boolean', 'default' => false]);

        $this->hasOne('country_id', ['model' => [SCountry::class]])
            ->addFields(['country_code' => 'code', 'is_eu'])
            ->addTitle();

        $this->hasMany('Tickets', ['model' => [STicket::class], 'theirField' => 'user']);
    }
}

class STicket extends Model
{
    public $table = 'ticket';
    public $caption = 'Ticket';

    protected function init(): void
    {
        parent::init();

        $this->addField('number');
        $this->addField('venue');
        $this->addField('is_vip', ['type' => 'boolean', 'default' => false]);

        $this->hasOne('user', ['model' => [SUser::class]]);
    }
}

class ScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $country = new SCountry($this->db);
        $this->createMigrator($country)->create();
        $country->import([
            ['name' => 'Canada', 'code' => 'CA'],
            ['name' => 'Latvia', 'code' => 'LV'],
            ['name' => 'Japan', 'code' => 'JP'],
            ['name' => 'Lithuania', 'code' => 'LT', 'is_eu' => true],
            ['name' => 'Russia', 'code' => 'RU'],
            ['name' => 'France', 'code' => 'FR'],
            ['name' => 'Brazil', 'code' => 'BR'],
        ]);

        $user = new SUser($this->db);
        $this->createMigrator($user)->create();
        $user->import([
            ['name' => 'John', 'surname' => 'Smith', 'country_code' => 'CA'],
            ['name' => 'Jane', 'surname' => 'Doe', 'country_code' => 'LV'],
            ['name' => 'Alain', 'surname' => 'Prost', 'country_code' => 'FR'],
            ['name' => 'Aerton', 'surname' => 'Senna', 'country_code' => 'BR'],
            ['name' => 'Rubens', 'surname' => 'Barichello', 'country_code' => 'BR'],
        ]);

        $ticket = new STicket($this->db);
        $this->createMigrator($ticket)->create();
        $ticket->import([
            ['number' => '001', 'venue' => 'Best Stadium', 'user' => 1],
            ['number' => '002', 'venue' => 'Best Stadium', 'user' => 2],
            ['number' => '003', 'venue' => 'Best Stadium', 'user' => 2],
            ['number' => '004', 'venue' => 'Best Stadium', 'user' => 4],
            ['number' => '005', 'venue' => 'Best Stadium', 'user' => 5],
        ]);
    }

    public function testCondition(): void
    {
        $user = new SUser($this->db);

        $condition = new Condition('name', 'John');

        $user->scope()->add($condition);

        $user = $user->loadOne();

        self::assertSame('Smith', $user->get('surname'));
    }

    public function testUnexistingFieldException(): void
    {
        $m = new Model();
        $m->addField('name');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Field is not defined');
        $m->addCondition('last_name', 'Smith');
    }

    public function testBasicDiscrimination(): void
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('gender', ['enum' => ['M', 'F']]);
        $m->addField('foo');

        $m->addCondition('gender', 'M');

        self::assertCount(1, $m->scope()->getNestedConditions());

        $m->addCondition('gender', 'F');

        self::assertCount(2, $m->scope()->getNestedConditions());
    }

    public function testEditableAfterCondition(): void
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('gender');

        $m->addCondition('gender', 'M');

        self::assertTrue($m->getField('gender')->system);
        self::assertFalse($m->getField('gender')->isEditable());
    }

    public function testEditableHasOne(): void
    {
        $gender = new Model();
        $gender->addField('name');

        $m = new Model();
        $m->addField('name');
        $m->hasOne('gender_id', ['model' => $gender]);

        self::assertFalse($m->getField('gender_id')->system);
        self::assertTrue($m->getField('gender_id')->isEditable());
    }

    public function testConditionToWords(): void
    {
        $user = new SUser($this->db);

        $condition = new Condition($this->getConnection()->expr('false'));
        self::assertSame('expression \'false\'', $condition->toWords($user));

        $condition = new Condition('country_id/code', 'US');
        self::assertSame('User that has reference Country ID where Code is equal to \'US\'', $condition->toWords($user));

        $condition = new Condition('country_id', 2);
        self::assertSame('Country ID is equal to 2 (\'Latvia\')', $condition->toWords($user));

        if ($this->getDatabasePlatform() instanceof SQLitePlatform || $this->getDatabasePlatform() instanceof MySQLPlatform) {
            $condition = new Condition('name', $user->expr('[surname]'));
            self::assertSame('Name is equal to expression \'`surname`\'', $condition->toWords($user));
        }

        $condition = new Condition('country_id', null);
        self::assertSame('Country ID is equal to empty', $condition->toWords($user));

        $condition = new Condition('name', '>', 'Test');
        self::assertSame('Name is greater than \'Test\'', $condition->toWords($user));

        $condition = (new Condition('country_id', 2))->negate();
        self::assertSame('Country ID is not equal to 2 (\'Latvia\')', $condition->toWords($user));

        $condition = new Condition($user->getField('surname'), $user->getField('name'));
        self::assertSame('Surname is equal to User Name', $condition->toWords($user));

        $country = new SCountry($this->db);
        $country->addCondition('Users/#', '>', 0);
        self::assertSame('Country that has reference Users where number of records is greater than 0', $country->scope()->toWords());
    }

    public function testConditionUnsupportedToWords(): void
    {
        $condition = new Condition('name', 'abc');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Condition must be associated with Model to convert to words');
        $condition->toWords();
    }

    public function testConditionUnsupportedOperator(): void
    {
        $country = new SCountry($this->db);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Operator is not supported');
        $country->addCondition('name', '==', 'abc');
    }

    public function testConditionUnsupportedNegate(): void
    {
        $condition = new Condition($this->getConnection()->expr('1 = 1'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Negation of condition is not supported for this operator');
        $condition->negate();
    }

    public function testRootScopeUnsupportedNegate(): void
    {
        $country = new SCountry($this->db);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Model scope cannot be negated');
        $country->scope()->negate();
    }

    public function testConditionOnReferencedRecords(): void
    {
        $user = new SUser($this->db);
        $user->addCondition('country_id/code', 'LV');

        self::assertSame(1, $user->executeCountQuery());

        foreach ($user as $u) {
            self::assertSame('LV', $u->get('country_code'));
        }

        $user = new SUser($this->db);

        // users that have no ticket
        $user->addCondition('Tickets/#', 0);

        self::assertSame(1, $user->executeCountQuery());

        foreach ($user as $u) {
            self::assertTrue(in_array($u->get('name'), ['Alain', 'Aerton', 'Rubens'], true));
        }

        $country = new SCountry($this->db);

        // countries with more than one user
        $country->addCondition('Users/#', '>', 1);

        foreach ($country as $c) {
            self::assertSame('BR', $c->get('code'));
        }

        $country = new SCountry($this->db);

        // countries with users that have ticket number 001
        $country->addCondition('Users/Tickets/number', '001');

        foreach ($country as $c) {
            self::assertSame('CA', $c->get('code'));
        }

        $country = new SCountry($this->db);

        // countries with users that have more than one ticket
        $country->addCondition('Users/Tickets/#', '>', 1);

        foreach ($country as $c) {
            self::assertSame('LV', $c->get('code'));
        }

        $country = new SCountry($this->db);

        // countries with users that have any tickets
        $country->addCondition('Users/Tickets/#', '>', 0);

        self::assertSame(3, $country->executeCountQuery());

        foreach ($country as $c) {
            self::assertTrue(in_array($c->get('code'), ['LV', 'CA', 'BR'], true));
        }

        $country = new SCountry($this->db);

        // countries with users that have no tickets
        $country->addCondition('Users/Tickets/#', 0);

        self::assertSame(1, $country->executeCountQuery());

        foreach ($country as $c) {
            self::assertTrue(in_array($c->get('code'), ['FR'], true));
        }

        $user = new SUser($this->db);

        // users with tickets that have more than two users per country
        // test if a model can be referenced multiple times
        // and if generated query has no duplicate column names
        // because of counting/# field if added multiple times
        $user->addCondition('Tickets/user/country_id/Users/#', '>', 1);
        $user->addCondition('Tickets/user/country_id/Users/#', '>', 1);
        $user->addCondition('Tickets/user/country_id/Users/#', '>=', 2);
        $user->addCondition('Tickets/user/country_id/Users/country_id/Users/#', '>', 1);
        if (!$this->getDatabasePlatform() instanceof SQLitePlatform) {
            // not supported because of limitation/issue in Sqlite, the generated query fails
            // with error: "parser stack overflow"
            $user->addCondition('Tickets/user/country_id/Users/country_id/Users/name', '!=', null); // should be always true
        }

        self::assertSame(2, $user->executeCountQuery());
        foreach ($user as $u) {
            self::assertTrue(in_array($u->get('name'), ['Aerton', 'Rubens'], true));
        }
    }

    public function testScope(): void
    {
        $user = new SUser($this->db);

        $condition1 = ['name', 'John'];
        $condition2 = new Condition('country_code', 'CA');

        $condition3 = ['surname', 'Doe'];
        $condition4 = new Condition('country_code', 'LV');

        $scope1 = Scope::createAnd($condition1, $condition2);
        $scope2 = Scope::createAnd($condition3, $condition4);

        $scope = Scope::createOr($scope1, $scope2);

        self::assertSame(Scope::OR, $scope->getJunction());
        self::assertSame('(Name is equal to \'John\' and Country Code is equal to \'CA\') or (Surname is equal to \'Doe\' and Country Code is equal to \'LV\')', $scope->toWords($user));

        $user->scope()->add($scope);

        self::assertSame($user, $scope->getModel());
        self::assertCount(2, $user->export());
        self::assertSame($scope->toWords($user), $user->scope()->toWords());

        $scope1 = clone $scope1;
        $scope2 = clone $scope2;
        $scope = Scope::createOr($scope1, $scope2);

        $scope->addCondition('country_code', 'BR');

        self::assertSame('(Name is equal to \'John\' and Country Code is equal to \'CA\') or (Surname is equal to \'Doe\' and Country Code is equal to \'LV\') or Country Code is equal to \'BR\'', $scope->toWords($user));

        $user = new SUser($this->db);
        $user->scope()->add($scope);

        self::assertCount(4, $user->export());
    }

    public function testScopeToWords(): void
    {
        $user = new SUser($this->db);

        $condition1 = new Condition('name', 'Alain');
        $condition2 = new Condition('country_code', 'CA');

        $scope1 = Scope::createAnd($condition1, $condition2);
        $condition3 = (new Condition('surname', 'Prost'))->negate();

        $scope = Scope::createAnd($scope1, $condition3);

        self::assertSame('(Name is equal to \'Alain\' and Country Code is equal to \'CA\') and Surname is not equal to \'Prost\'', $scope->toWords($user));
    }

    public function testNegate(): void
    {
        $user = new SUser($this->db);

        $condition1 = new Condition('name', '!=', 'Alain');
        $condition2 = new Condition('country_code', '!=', 'FR');

        $condition = Scope::createOr($condition1, $condition2)->negate();

        $user->scope()->add($condition);

        foreach ($user as $u) {
            self::assertTrue($u->get('name') === 'Alain' && $u->get('country_code') === 'FR');
        }
    }

    public function testAnd(): void
    {
        $user = new SUser($this->db);

        $condition1 = new Condition('name', 'Alain');
        $condition2 = new Condition('country_code', 'FR');

        $scope = Scope::createAnd($condition1, $condition2);
        $scope = Scope::createOr($scope, new Condition('name', 'John'));

        self::assertSame('(Name is equal to \'Alain\' and Country Code is equal to \'FR\') or Name is equal to \'John\'', $scope->toWords($user));
    }

    public function testOr(): void
    {
        $user = new SUser($this->db);

        $condition1 = new Condition('name', 'Alain');
        $condition2 = new Condition('country_code', 'FR');

        $scope = Scope::createOr($condition1, $condition2);
        $scope = Scope::createAnd($scope, new Condition('name', 'John'));

        self::assertSame('(Name is equal to \'Alain\' or Country Code is equal to \'FR\') and Name is equal to \'John\'', $scope->toWords($user));
    }

    public function testMerge(): void
    {
        $user = new SUser($this->db);

        $condition1 = new Condition('name', 'Alain');
        $condition2 = new Condition('country_code', 'FR');

        $scope = Scope::createAnd($condition1, $condition2);

        self::assertSame('Name is equal to \'Alain\' and Country Code is equal to \'FR\'', $scope->toWords($user));
    }

    public function testDestroyEmpty(): void
    {
        $user = new SUser($this->db);

        $condition1 = new Condition('name', 'Alain');
        $condition2 = new Condition('country_code', 'FR');

        $scope = Scope::createAnd($condition1, $condition2);

        $scope->clear();

        self::assertTrue($scope->isEmpty());
        self::assertEmpty($scope->toWords($user));
    }

    public function testInvalid1(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Operator is not supported for array condition value');
        new Condition('name', '>', ['a', 'b']);
    }

    public function testInvalid2(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Multi-dimensional array as condition value is not supported');
        new Condition('name', ['a', 'b' => ['c']]);
    }
}

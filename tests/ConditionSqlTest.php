<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence\Sql\Mysql\Connection as MysqlConnection;
use Atk4\Data\Persistence\Sql\Query;
use Atk4\Data\Persistence\Sql\RawExpression;
use Atk4\Data\Schema\TestCase;
use Atk4\Data\Tests\Schema\MigratorTest;
use Atk4\Data\ValidationException;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;

class ConditionSqlTest extends TestCase
{
    public function testBasic(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('gender');

        $mm = $m->load(1);
        self::assertSame('John', $mm->get('name'));
        $mm = $m->load(2);
        self::assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition('gender', 'M');
        $mm2 = $mm->load(1);
        self::assertSame('John', $mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        self::assertNull($mm2);

        $mm = clone $m;
        $mm->addCondition('id', 2);
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->load(2);
        self::assertSame('Sue', $mm2->get('name'));
    }

    public function testEntityNoScopeCloning(): void
    {
        $m = new Model($this->db, ['table' => 'user']);
        $scope = $m->scope();
        self::assertSame($scope, $m->createEntity()->getModel()->scope());

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Expected model, but instance is an entity');
        $m->createEntity()->scope();
    }

    public function testEntityReloadWithDifferentIdException(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('gender');

        $m = $m->load(1);
        self::assertSame('John', $m->get('name'));
        \Closure::bind(static function () use ($m) {
            $m->_entityId = 2;
        }, null, Model::class)();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Model instance is an entity, ID cannot be changed to a different one');
        $m->reload();
    }

    public function testConditionWithNull(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
                ['id' => 3, 'name' => 'Null1', 'gender' => null],
                ['id' => 4, 'name' => 'Null2', 'gender' => null],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('gender');

        $m->addCondition('gender', null);

        $nullCount = 0;
        foreach ($m as $user) {
            self::assertNull($user->get('gender'));
            self::assertStringContainsString('Null', $user->get('name'));

            ++$nullCount;
        }

        self::assertSame(2, $nullCount);
    }

    public function testConditionWithNullOnNotNullableField(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
                ['id' => 3, 'name' => 'Niki', 'gender' => null],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('gender', ['nullable' => false]);
        $m->setOrder('id');

        self::assertCount(3, $m->export());

        $m->addCondition('gender', '!=', null);
        self::assertCount(2, $m->export());

        $m->addCondition('id', '!=', null);
        self::assertCount(2, $m->export());

        $m->addCondition('id', '=', null);
        self::assertCount(0, $m->export());
    }

    public function testInConditionWithNullException(): void
    {
        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');

        $m->addCondition('name', 'in', [null]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to load due to query error');
        try {
            $m->loadOne();
        } catch (Exception $e) {
            self::assertSame('Null value in IN operator is not supported', $e->getPrevious()->getMessage());

            throw $e;
        }
    }

    public function testOperations(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('gender');

        $mm = $m->load(1);
        self::assertSame('John', $mm->get('name'));
        $mm = $m->load(2);
        self::assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition('gender', 'M');
        $mm2 = $mm->load(1);
        self::assertSame('John', $mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        self::assertNull($mm2);

        $mm = clone $m;
        $mm->addCondition('gender', '!=', 'M');
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->load(2);
        self::assertSame('Sue', $mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition('id', '>', 1);
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->load(2);
        self::assertSame('Sue', $mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition('id', 'in', [1, 3]);
        $mm2 = $mm->load(1);
        self::assertSame('John', $mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        self::assertNull($mm2);

        $mm = clone $m;
        $mm->addCondition('id', 'not in', [1, 3]);
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->tryLoad(2);
        self::assertSame('Sue', $mm2->get('name'));
    }

    public function testExpressions1(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('gender');

        $mm = $m->load(1);
        self::assertSame('John', $mm->get('name'));
        $mm = $m->load(2);
        self::assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[] > 1', [$mm->getField('id')]));
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->load(2);
        self::assertSame('Sue', $mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[id] > 1'));
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->load(2);
        self::assertSame('Sue', $mm2->get('name'));
    }

    public function testExpressions2(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
                ['id' => 2, 'name' => 'Sue', 'surname' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('gender');
        $m->addField('surname');

        $mm = $m->load(1);
        self::assertSame('John', $mm->get('name'));
        $mm = $m->load(2);
        self::assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] = [surname]'));
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->load(2);
        self::assertSame('Sue', $mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition($m->getField('name'), $m->getField('surname'));
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->load(2);
        self::assertSame('Sue', $mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] != [surname]'));
        $mm2 = $mm->load(1);
        self::assertSame('John', $mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        self::assertNull($mm2);

        $mm = clone $m;
        $mm->addCondition($m->getField('name'), '!=', $m->getField('surname'));
        $mm2 = $mm->load(1);
        self::assertSame('John', $mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        self::assertNull($mm2);
    }

    public function testExpressionJoin(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'gender' => 'M', 'contact_id' => 1],
                ['id' => 2, 'name' => 'Sue', 'surname' => 'Sue', 'gender' => 'F', 'contact_id' => 2],
                ['id' => 3, 'name' => 'Peter', 'surname' => 'Smith', 'gender' => 'M', 'contact_id' => 1],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123 smiths'],
                ['id' => 2, 'contact_phone' => '+321 sues'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('gender');
        $m->addField('surname');

        $m->join('contact')
            ->addField('contact_phone');

        $mm2 = $m->load(1);
        self::assertSame('John', $mm2->get('name'));
        self::assertSame('+123 smiths', $mm2->get('contact_phone'));
        $mm2 = $m->load(2);
        self::assertSame('Sue', $mm2->get('name'));
        self::assertSame('+321 sues', $mm2->get('contact_phone'));
        $mm2 = $m->load(3);
        self::assertSame('Peter', $mm2->get('name'));
        self::assertSame('+123 smiths', $mm2->get('contact_phone'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] = [surname]'));
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->load(2);
        self::assertSame('Sue', $mm2->get('name'));
        self::assertSame('+321 sues', $mm2->get('contact_phone'));
        $mm2 = $mm->tryLoad(3);
        self::assertNull($mm2);

        $mm = clone $m;
        $mm->addCondition($mm->expr('\'+123 smiths\' = [contact_phone]'));
        $mm2 = $mm->load(1);
        self::assertSame('John', $mm2->get('name'));
        self::assertSame('+123 smiths', $mm2->get('contact_phone'));
        $mm2 = $mm->tryLoad(2);
        self::assertNull($mm2);
        $mm2 = $mm->load(3);
        self::assertSame('Peter', $mm2->get('name'));
        self::assertSame('+123 smiths', $mm2->get('contact_phone'));
    }

    public function testArrayCondition(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Johhny'],
                ['id' => 3, 'name' => 'Mary'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addCondition('name', ['John', 'Doe']);
        self::assertCount(1, $m->export());

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addCondition('name', 'in', ['Johhny', 'Doe', 'Mary']);
        self::assertCount(2, $m->export());

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addCondition('name', []); // this should not fail, should be always false
        self::assertCount(0, $m->export());

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addCondition('name', 'not in', []); // this should not fail, should be always true
        self::assertCount(3, $m->export());
    }

    public function testDateCondition(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '1981-12-08'],
                ['id' => 2, 'name' => 'Sue', 'date' => '1982-12-08'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('date', ['type' => 'date']);

        $m = $m->loadBy('date', new \DateTime('1982-12-08'));
        self::assertSame('Sue', $m->get('name'));
    }

    public function testDateCondition2(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '1981-12-08'],
                ['id' => 2, 'name' => 'Sue', 'date' => '1982-12-08'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('date', ['type' => 'date']);

        $m->addCondition('date', new \DateTime('1982-12-08'));
        $m = $m->loadOne();
        self::assertSame('Sue', $m->get('name'));
    }

    public function testDateConditionFailure(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '1981-12-08'],
                ['id' => 2, 'name' => 'Sue', 'date' => '1982-12-08'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('date', ['type' => 'date']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Must be scalar');
        $m->tryLoadBy('name', new \DateTime('1982-12-08'));
    }

    public function testAndFromArrayCondition(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Peter'],
                ['id' => 3, 'name' => 'Joe'],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');

        $u->addCondition([
            ['name', 'like', 'J%'],
            ['name', 'like', '%e%'],
        ]);
        self::assertSameExportUnordered([
            ['id' => 3, 'name' => 'Joe'],
        ], $u->export());
    }

    public function testOrCondition(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Peter'],
                ['id' => 3, 'name' => 'Joe'],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');

        $u->addCondition(Model\Scope::createOr(
            ['name', 'John'],
            ['name', 'Peter'],
        ));
        self::assertSame(2, $u->executeCountQuery());

        $u->addCondition(Model\Scope::createOr(
            ['name', 'Peter'],
            ['name', 'Joe'],
        ));
        self::assertSame(1, $u->executeCountQuery());
    }

    public function testLoadByRestoreCondition(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Peter'],
                ['id' => 3, 'name' => 'Joe'],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');
        $scope = $u->scope();

        $u2 = $u->loadBy('name', 'John');
        self::assertSame(['id' => 1, 'name' => 'John'], $u2->get());
        self::assertSame($scope, $u->scope());
        self::assertTrue($u->scope()->isEmpty());
        self::assertFalse($u->getField('name')->system); // should not set field as system
        self::assertNull($u->getField('name')->default); // should not set field default value

        $u2 = $u->tryLoadBy('name', 'Joe');
        self::assertSame(['id' => 3, 'name' => 'Joe'], $u2->get());
        self::assertSame($scope, $u->scope());
        self::assertTrue($u->scope()->isEmpty());
        self::assertFalse($u->getField('name')->system); // should not set field as system
        self::assertNull($u->getField('name')->default); // should not set field default value
    }

    /**
     * @dataProvider \Atk4\Data\Tests\Schema\MigratorTest::provideCharacterTypeFieldCaseSensitivityCases
     */
    #[DataProviderExternal(MigratorTest::class, 'provideCharacterTypeFieldCaseSensitivityCases')]
    public function testLikeCondition(string $type, bool $isBinary): void
    {
        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name', ['type' => $type]);
        $u->addField('c', ['type' => 'integer']);

        $this->createMigrator($u)->create();

        $u->import([
            ['name' => 'John', 'c' => 1],
            ['name' => 'Peter', 'c' => 2000],
            ['name' => 'Joe', 'c' => 50],
            ['name' => ''],
            ['name' => 'Ca_ro%li\ne'],
            ['name' => "Ca\nro.li\\\\ne"],
            ['name' => 'Ca*ro^li$ne'],
            ['name' => 'Ja[n]e'],
            ['name' => 'Ja\[^n]e'],
            ['name' => 'heiß'],
            ['name' => 'hei\ß'],
            ['name' => 'hei\\\ß'],
            ['name' => 'hei\123'],
            ['name' => '123ß'],
        ]);

        $findIdsLikeFx = function (string $field, string $value, bool $negated = false) use ($u, $isBinary) {
            $t = (clone $u)->addCondition($field, ($negated ? 'not ' : '') . 'like', $value);
            $res = array_keys($t->export(null, 'id'));

            $t = (clone $u)->addCondition($field, ($negated ? 'not ' : '') . 'like', $u->dsql()->field($u->expr('[]', [$value])));
            if (!$this->getConnection()->getConnection()->getNativeConnection() instanceof \mysqli // https://bugs.mysql.com/bug.php?id=114659
                && (!$this->getDatabasePlatform() instanceof SQLServerPlatform || !$isBinary) // string encoding of bound variable is UTF-16
            ) {
                self::assertSame(array_keys($t->export(null, 'id')), $res);
            }

            return $res;
        };

        if ($this->getDatabasePlatform() instanceof OraclePlatform && $isBinary) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Unsupported binary field operator');
        }

        self::assertSame([1], $findIdsLikeFx('name', 'John'));
        self::assertSame($isBinary ? [] : [1], $findIdsLikeFx('name', 'john'));
        self::assertSame([10], $findIdsLikeFx('name', 'heiß'));
        self::assertSame($isBinary ? [] : [10], $findIdsLikeFx('name', 'Heiß'));
        self::assertSame([], $findIdsLikeFx('name', 'Joh'));
        self::assertSame([1, 3], $findIdsLikeFx('name', 'Jo%'));
        self::assertSame(array_values(array_diff(range(1, 14), [1, 3], $this->getDatabasePlatform() instanceof OraclePlatform ? [4] : [])), $findIdsLikeFx('name', 'Jo%', true));
        self::assertSame([1], $findIdsLikeFx('name', '%John%'));
        self::assertSame([1], $findIdsLikeFx('name', 'Jo%n'));
        self::assertSame([1], $findIdsLikeFx('name', 'J%n'));
        self::assertSame([1], $findIdsLikeFx('name', 'Jo_n'));
        self::assertSame([], $findIdsLikeFx('name', 'J_n'));
        self::assertSame($isBinary && !$this->getDatabasePlatform() instanceof OraclePlatform ? [] : [14], $findIdsLikeFx('name', '123_'));
        self::assertSame($isBinary && !$this->getDatabasePlatform() instanceof PostgreSQLPlatform ? [14] : [], $findIdsLikeFx('name', '123__'));
        self::assertSame([], $findIdsLikeFx('name', '123___'));

        self::assertSame([1], $findIdsLikeFx('c', '%1%'));
        self::assertSame([2], $findIdsLikeFx('c', '%2000%'));
        self::assertSame([2, 3], $findIdsLikeFx('c', '%0%'));
        self::assertSame([1], $findIdsLikeFx('c', '%0%', true));
        self::assertSame([5, 6, 7], $findIdsLikeFx('name', '%Ca_ro%'));
        self::assertSame([5], $findIdsLikeFx('name', '%Ca\_ro%'));
        self::assertSame([5, 6, 7], $findIdsLikeFx('name', '%ro%li%'));
        self::assertSame([5], $findIdsLikeFx('name', '%ro\%li%'));

        self::assertSame([], $findIdsLikeFx('name', '%line%'));
        self::assertSame([5], $findIdsLikeFx('name', '%li\ne%'));
        self::assertSame([5], $findIdsLikeFx('name', '%li\\\ne%'));
        self::assertSame([6], $findIdsLikeFx('name', '%li\\\\\ne%'));
        self::assertSame([6], $findIdsLikeFx('name', '%li\\\\\\\ne%'));
        self::assertSame([], $findIdsLikeFx('name', '%li\\\\\\\\\ne%'));
        self::assertSame([], $findIdsLikeFx('name', '%li\\\\\\\\\\\ne%'));
        self::assertSame([5, 6, 7], $findIdsLikeFx('name', '%li%ne%'));
        self::assertSame([5, 6], $findIdsLikeFx('name', '%li%\ne%'));
        self::assertSame([5, 6], $findIdsLikeFx('name', '%li%\\\ne%'));
        self::assertSame([6], $findIdsLikeFx('name', '%li%\\\\\ne%'));
        self::assertSame([6], $findIdsLikeFx('name', '%li%\\\\\\\ne%'));
        self::assertSame([], $findIdsLikeFx('name', '%li%\\\\\\\\\ne%'));
        self::assertSame([], $findIdsLikeFx('name', '%li%\\\\\\\\\\\ne%'));
        self::assertSame([], $findIdsLikeFx('name', '%li\%ne%'));
        self::assertSame([5, 6], $findIdsLikeFx('name', '%li\\\%ne%'));
        self::assertSame([], $findIdsLikeFx('name', '%li\\\\\%ne%'));
        self::assertSame([6], $findIdsLikeFx('name', '%li\\\\\\\%ne%'));
        self::assertSame([], $findIdsLikeFx('name', '%li\\\\\\\\\%ne%'));
        self::assertSame([], $findIdsLikeFx('name', '%li\%e%'));
        self::assertSame([5, 6], $findIdsLikeFx('name', '%li\\\%e%'));
        self::assertSame([], $findIdsLikeFx('name', '%li\\\\\%e%'));
        self::assertSame([6], $findIdsLikeFx('name', '%li\\\\\\\%e%'));
        self::assertSame([], $findIdsLikeFx('name', '%li\\\\\\\\\%e%'));
        self::assertSame([11], $findIdsLikeFx('name', 'hei\ß'));
        self::assertSame([11], $findIdsLikeFx('name', 'hei\\\ß'));
        self::assertSame([12], $findIdsLikeFx('name', 'hei\\\\\ß'));
        self::assertSame([12], $findIdsLikeFx('name', 'hei\\\\\\\ß'));
        self::assertSame([], $findIdsLikeFx('name', 'hei\\\\\\\\\ß'));
        self::assertSame([13], $findIdsLikeFx('name', 'hei\123'));
        self::assertSame([13], $findIdsLikeFx('name', 'hei\\\123'));
        self::assertSame([], $findIdsLikeFx('name', 'hei\\\\\123'));

        self::assertSame([5], $findIdsLikeFx('name', '%l_\ne%'));
        self::assertSame([6], $findIdsLikeFx('name', '%l__\ne%'));
        self::assertSame([5, 6], $findIdsLikeFx('name', '%li%%\ne%'));
        self::assertSame([6], $findIdsLikeFx('name', '%.%'));
        self::assertSame([6], $findIdsLikeFx('name', '%.li%ne'));
        self::assertSame([], $findIdsLikeFx('name', '%.li%ne\\'));
        self::assertSame([], $findIdsLikeFx('name', '%.li%ne\\\\'));
        self::assertSame([7], $findIdsLikeFx('name', '%*%'));
        self::assertSame([], $findIdsLikeFx('name', '%*li%ne'));
        self::assertSame([7, 9], $findIdsLikeFx('name', '%^%'));
        self::assertSame([7], $findIdsLikeFx('name', '%$%'));
        self::assertSame([8, 9], $findIdsLikeFx('name', '%[%'));
        self::assertSame([9], $findIdsLikeFx('name', '%\[%'));
        self::assertSame([9], $findIdsLikeFx('name', '%\\\[%'));
        self::assertSame([], $findIdsLikeFx('name', '%\\\\\[%'));
        self::assertSame([8, 9], $findIdsLikeFx('name', '%]%'));
        self::assertSame([8], $findIdsLikeFx('name', '%[n]%'));
        self::assertSame([9], $findIdsLikeFx('name', '%^n%'));
        self::assertSame([9], $findIdsLikeFx('name', '%[^n]%'));

        if ($type !== 'string') {
            self::assertStringStartsWith("Ca\nro", $u->load(6)->get('name'));
            self::assertSame([6], $findIdsLikeFx('name', "Ca\n%"));
            self::assertSame([], $findIdsLikeFx('name', "Ca\\\n%"));
            self::assertSame([], $findIdsLikeFx('name', 'Ca %'));
        }

        self::assertSame($this->getDatabasePlatform() instanceof OraclePlatform ? [] : [4], array_keys((clone $u)->addCondition('name', 'like', $u->expr('\'\''))->export(null, 'id')));
        self::assertSame([], array_keys((clone $u)->addCondition('name', 'like', $u->expr('null'))->export(null, 'id')));
    }

    /**
     * @dataProvider \Atk4\Data\Tests\Schema\MigratorTest::provideCharacterTypeFieldCaseSensitivityCases
     */
    #[DataProviderExternal(MigratorTest::class, 'provideCharacterTypeFieldCaseSensitivityCases')]
    public function testRegexpCondition(string $type, bool $isBinary): void
    {
        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name', ['type' => $type]);
        $u->addField('c', ['type' => 'integer']);
        $u->addField('rating', ['type' => 'float']);

        $this->createMigrator($u)->create();

        $u->import([
            ['name' => 'John', 'c' => 1, 'rating' => 1.5],
            ['name' => 'Peter', 'c' => 2000, 'rating' => 2.5],
            ['name' => 'Joe', 'c' => 50],
            ['name' => ''],
            ['name' => 'Sa ra'],
            ['name' => "Sa\nra"],
            ['name' => 'Sa.ra'],
            ['name' => 'Sa/ra'],
            ['name' => 'Sa\ra'],
            ['name' => 'Sa\\\ra'],
            ['name' => 'Sa~ra'],
            ['name' => 'Sa$ra'],
            ['name' => 'heiß'],
            ['name' => 'hei\ß'],
            ['name' => 'hei\\\ß'],
            ['name' => 'hei\123'],
            ['name' => '123ß'],
        ]);

        $findIdsRegexFx = function (string $field, string $value, bool $negated = false) use ($u) {
            $t = (clone $u)->addCondition($field, ($negated ? 'not ' : '') . 'regexp', $value);
            $res = array_keys($t->export(null, 'id'));

            $t = (clone $u)->addCondition($field, ($negated ? 'not ' : '') . 'regexp', $u->dsql()->field($u->expr('[]', [$value])));
            if (!$this->getConnection()->getConnection()->getNativeConnection() instanceof \mysqli) { // https://bugs.mysql.com/bug.php?id=114659
                self::assertSame(array_keys($t->export(null, 'id')), $res);
            }

            return $res;
        };

        if ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            // https://devblogs.microsoft.com/azure-sql/introducing-regular-expression-regex-support-in-azure-sql-db/
            self::markTestIncomplete('MSSQL has no REGEXP support yet');
        }

        if ($this->getDatabasePlatform() instanceof OraclePlatform && $isBinary) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Unsupported binary field operator');
        }

        $isMariadb = $this->getDatabasePlatform() instanceof MySQLPlatform
            && MysqlConnection::isServerMariaDb($this->getConnection());
        $isMysql5x = $this->getDatabasePlatform() instanceof MySQLPlatform && !$isMariadb
            && MysqlConnection::getServerMinorVersion($this->getConnection()) < 600;

        $this->markTestIncompleteOnMySQL8xPlatformAsBinaryLikeIsBroken($isBinary);

        self::assertSame([1], $findIdsRegexFx('name', 'John'));
        self::assertSame($isBinary ? [] : [1], $findIdsRegexFx('name', 'john'));
        self::assertSame([13], $findIdsRegexFx('name', 'heiß'));
        self::assertSame($isBinary ? [] : [13], $findIdsRegexFx('name', 'Heiß'));
        self::assertSame([1], $findIdsRegexFx('name', 'Joh'));
        self::assertSame([1], $findIdsRegexFx('name', 'ohn'));
        self::assertSame(array_values(array_diff(range(1, 17), [...($this->getDatabasePlatform() instanceof OraclePlatform ? [4] : []), 5, 6, 7, 8, 9, 10, 11, 12])), $findIdsRegexFx('name', 'a', true));

        self::assertSame([1], $findIdsRegexFx('c', '1'));
        self::assertSame([2], $findIdsRegexFx('c', '2000'));
        self::assertSame([2, 3], $findIdsRegexFx('c', '0'));
        self::assertSame([1], $findIdsRegexFx('c', '0', true));
        self::assertSame([1, 2], $findIdsRegexFx('rating', '\.5'));
        self::assertSame([2], $findIdsRegexFx('rating', '2\.5'));

        self::assertSame([9, 10, 14, 15, 16], $findIdsRegexFx('name', '\\\\'));
        self::assertSame([10, 15], $findIdsRegexFx('name', '\\\\\\\\'));
        self::assertSame([], $findIdsRegexFx('name', '\\\\\\\\\\\\'));
        self::assertSame([14], $findIdsRegexFx('name', 'hei\\\ß'));
        self::assertSame([15], $findIdsRegexFx('name', 'hei\\\\\\\ß'));
        self::assertSame([], $findIdsRegexFx('name', 'hei\\\\\\\\\\\ß'));
        self::assertSame([16], $findIdsRegexFx('name', 'hei\\\123'));
        self::assertSame([], $findIdsRegexFx('name', 'hei\\\\\\\123'));
        self::assertSame([7], $findIdsRegexFx('name', '\.'));
        self::assertSame([12], $findIdsRegexFx('name', '\$'));
        self::assertSame([8], $findIdsRegexFx('name', '/ra'));
        self::assertSame([8], $findIdsRegexFx('name', '\/ra'));
        self::assertSame([11], $findIdsRegexFx('name', '~ra'));
        self::assertSame([11], $findIdsRegexFx('name', '\~ra'));

        if ($type !== 'string') {
            self::assertSame("Sa\nra", $u->load(6)->get('name'));
            self::assertSame([6], $findIdsRegexFx('name', "Sa\nra"));
            self::assertSame([6], $findIdsRegexFx('name', "Sa\\\nra"));
            self::assertSame([6], $findIdsRegexFx('name', "\nra"));
            self::assertSame([6], $findIdsRegexFx('name', "\\\nra"));
            self::assertSame([5], $findIdsRegexFx('name', ' ra'));
            self::assertSame([5], $findIdsRegexFx('name', '\ ra'));
        }

        self::assertSame([2, 3, 13, 14, 15, 16], $findIdsRegexFx('name', '.e'));
        self::assertSame(array_values(array_diff(range(1, 17), [4])), $findIdsRegexFx('name', '.'));
        self::assertSame([5, 6, 7, 8, 9, 11, 12], $findIdsRegexFx('name', 'Sa.ra'));
        self::assertSame([2, 3, 13, 14, 15, 16], $findIdsRegexFx('name', '[e]'));
        self::assertSame([1, 2, 3, 13, 14, 15, 16], $findIdsRegexFx('name', '[eo]'));
        self::assertSame([1, 2, 3, ...($isBinary ? [] : [13, 14, 15, 16])], $findIdsRegexFx('name', '[A-P][aeo]'));
        self::assertSame([3], $findIdsRegexFx('name', 'o[^h]'));
        self::assertSame([5, 6, 7, 8, 9, 10, 11, 12], $findIdsRegexFx('name', '^Sa'));
        self::assertSame([], $findIdsRegexFx('name', '^ra'));
        self::assertSame([5, 6, 7, 8, 9, 10, 11, 12], $findIdsRegexFx('name', 'ra$'));
        self::assertSame([], $findIdsRegexFx('name', 'Sa$'));
        self::assertSame(($isBinary && !$this->getDatabasePlatform() instanceof OraclePlatform) || $isMysql5x ? [] : [17], $findIdsRegexFx('name', '123.$'));
        self::assertSame(($isBinary && !$this->getDatabasePlatform() instanceof PostgreSQLPlatform) || $isMysql5x ? [17] : [], $findIdsRegexFx('name', '123..$'));
        self::assertSame([], $findIdsRegexFx('name', '123...$'));

        self::assertSame([1, 3], $findIdsRegexFx('name', 'John|e$'));
        self::assertSame([1], $findIdsRegexFx('name', '((John))()'));
        self::assertSame([1, 3, 11], $findIdsRegexFx('name', '(J|Sa~ra)'));

        self::assertSame([1], $findIdsRegexFx('name', 'J.+n'));
        self::assertSame([], $findIdsRegexFx('name', 'John.+'));
        self::assertSame([2], $findIdsRegexFx('c', '20+$'));
        self::assertSame([1], $findIdsRegexFx('name', 'J.*n'));
        self::assertSame([1], $findIdsRegexFx('name', 'John.*'));
        self::assertSame([2], $findIdsRegexFx('c', '20*$'));
        self::assertSame([1], $findIdsRegexFx('name', 'J.?hn'));
        self::assertSame([], $findIdsRegexFx('name', 'J.?n'));
        self::assertSame([], $findIdsRegexFx('c', '20?$'));
        self::assertSame([2], $findIdsRegexFx('c', '20{3}$'));
        self::assertSame([], $findIdsRegexFx('c', '20{2}$'));
        self::assertSame([], $findIdsRegexFx('c', '20{4}'));
        self::assertSame([1], $findIdsRegexFx('name', 'Jx{0}ohn'));
        self::assertSame([2], $findIdsRegexFx('c', '20{2,4}$'));
        self::assertSame([], $findIdsRegexFx('c', '20{4,4}'));
        self::assertSame([2], $findIdsRegexFx('c', '20{2,}$'));

        if (!$this->getDatabasePlatform() instanceof MySQLPlatform || !$isMysql5x) {
            self::assertSame($this->getDatabasePlatform() instanceof PostgreSQLPlatform && $isBinary ? [13, 14, 15, 16, 17] : [16, 17], $findIdsRegexFx('name', '\d'));
            self::assertSame([2, 3], $findIdsRegexFx('c', '\d0'));
            self::assertSame([1], $findIdsRegexFx('c', '^\d$'));
            self::assertSame([1, 3], $findIdsRegexFx('name', 'J\D'));
            self::assertSame([5, 6], $findIdsRegexFx('name', 'Sa\s'));
            self::assertSame([7, 8, 9, 10, 11, 12], $findIdsRegexFx('name', 'Sa\S'));
            self::assertSame([1, 3], $findIdsRegexFx('name', '\wo'));
            self::assertSame($isBinary && !$this->getDatabasePlatform() instanceof OraclePlatform ? [] : [13], $findIdsRegexFx('name', 'hei\w$'));
            self::assertSame($isBinary && !$this->getDatabasePlatform() instanceof OraclePlatform ? [] : [17], $findIdsRegexFx('name', '123\w$'));
            self::assertSame([10, 15], $findIdsRegexFx('name', '\W\\\\'));
            if ($type !== 'string' && !$this->getDatabasePlatform() instanceof OraclePlatform) {
                self::assertSame([5], $findIdsRegexFx('name', '\x20'));
                self::assertSame([6], $findIdsRegexFx('name', '\n'));
                self::assertSame([], $findIdsRegexFx('name', '\r'));
            }
        }

        if (!$this->getDatabasePlatform() instanceof MySQLPlatform || $isMariadb) {
            self::assertSame([2, 5, 6, 7, 8, 9, 10, 11, 12], $findIdsRegexFx('name', '([ae]).+\1'));
        }

        if ((!$this->getDatabasePlatform() instanceof MySQLPlatform || !$isMysql5x) && !$this->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertSame([11], $findIdsRegexFx('name', 'Sa(?=~).r'));
            self::assertSame([5, 6, 7, 8, 9, 12], $findIdsRegexFx('name', 'Sa(?!~).r'));
            self::assertSame([11], $findIdsRegexFx('name', 'a.(?<=~)ra'));
            self::assertSame([5, 6, 7, 8, 9, 12], $findIdsRegexFx('name', 'a.(?<!~)ra'));
        }

        $hugeList = array_map(static fn ($i) => 'foo' . $i, range(0, $this->getDatabasePlatform() instanceof OraclePlatform ? 19 : 2_000));
        self::assertSame([1], $findIdsRegexFx('name', implode('|', $hugeList) . '|John'));
        if (!$this->getDatabasePlatform() instanceof PostgreSQLPlatform) { // very slow on PostgreSQL 14 and lower, on PostgreSQL 15 and 16 the queries are still slow (~10 seconds)
            self::assertSame([1], $findIdsRegexFx('name', str_repeat('(', 99) . implode('|', $hugeList) . '|John' . str_repeat(')', 99)));
            self::assertSame([1], $findIdsRegexFx('name', implode('', array_map(static fn ($v) => '(' . $v . ')?', $hugeList)) . 'John'));
        }
        self::assertSame([1], $findIdsRegexFx('name', implode('', array_map(static fn ($v) => '((' . $v . ')?', array_slice($hugeList, 0, 98))) . 'John' . str_repeat(')', min(count($hugeList), 98))));
    }

    /**
     * @dataProvider provideNullLikeRegexpConditionCases
     *
     * @param 'like'|'regexp' $operator
     */
    #[DataProvider('provideNullLikeRegexpConditionCases')]
    public function testNullLikeRegexpCondition(string $operator, ?bool $expectedResult, ?string $value, ?string $pattern, bool $negated): void
    {
        if ($this->getDatabasePlatform() instanceof SQLServerPlatform && $operator === 'regexp') {
            // https://devblogs.microsoft.com/azure-sql/introducing-regular-expression-regex-support-in-azure-sql-db/
            self::markTestIncomplete('MSSQL has no REGEXP support yet');
        }

        // TODO Oracle always converts empty string to null
        // https://stackoverflow.com/questions/13278773/null-vs-empty-string-in-oracle#13278879
        if ($this->getDatabasePlatform() instanceof OraclePlatform && ($value === '' || $pattern === '') && $expectedResult !== null) {
            self::assertTrue(true); // @phpstan-ignore-line

            return;
        }

        $makeWhereExprFx = function ($value, $pattern, $negated) use ($operator) {
            $dsql = $this->getConnection()->dsql();

            return new RawExpression(\Closure::bind(static function () use ($operator, $value, $pattern, $negated, $dsql) {
                $escapeStringLiteralFx = static function ($value) use ($dsql) {
                    return $value === null
                        ? 'null'
                        : $dsql->escapeStringLiteral($value);
                };

                // workaround Oracle "expr is null" limitation
                // https://dbfiddle.uk/9rtTvTDH
                $boolToIntFx = static function ($sql) {
                    $oneIfTrue = 'case when (' . $sql . ') then 1 else 0 end';
                    $oneIfFalse = 'case when not(' . $sql . ') then 1 else 0 end';

                    return 'case when ' . $oneIfTrue . ' + ' . $oneIfFalse . ' = 0 then null else ' . $oneIfTrue . ' end';
                };

                $res = $operator === 'like'
                    ? $dsql->_renderConditionLikeOperator($negated, $escapeStringLiteralFx($value), $escapeStringLiteralFx($pattern))
                    : $dsql->_renderConditionRegexpOperator($negated, $escapeStringLiteralFx($value), $escapeStringLiteralFx($pattern));

                return $boolToIntFx($res);
            }, null, Query::class)());
        };

        $dsql = $this->getConnection()->dsql()
            ->field($makeWhereExprFx($value, $pattern, $negated), 'v');

        self::assertSame(['v' => $expectedResult === null ? null : ($expectedResult ? '1' : '0')], $dsql->getRow());
    }

    /**
     * @return iterable<list<mixed>>
     */
    public static function provideNullLikeRegexpConditionCases(): iterable
    {
        foreach (['like', 'regexp'] as $operator) {
            foreach ([false, true] as $negated) {
                yield [$operator, !$negated, '', '', $negated];
                yield [$operator, $negated, '', 'x', $negated];
                yield [$operator, $negated, 'x', $operator === 'regexp' ? '^$' : '', $negated];
                yield [$operator, null, null, null, $negated];
                yield [$operator, null, null, '', $negated];
                yield [$operator, null, '', null, $negated];
            }
        }
    }
}

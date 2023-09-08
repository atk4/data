<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Tests\Model\Female;
use Atk4\Data\Tests\Model\Male;

class ArrayTest extends TestCase
{
    /**
     * @return array<string, array<mixed, mixed>>
     */
    private function getInternalPersistenceData(Persistence\Array_ $db): array
    {
        $data = [];
        /** @var Persistence\Array_\Db\Table $table */
        foreach ($this->getProtected($db, 'data') as $table) {
            foreach ($table->getRows() as $row) {
                $rowData = $row->getData();
                $id = $rowData['id'];
                unset($rowData['id']);
                $data[$table->getTableName()][$id] = $rowData;
            }
        }

        return $data;
    }

    public function testLoadArray(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($p, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $mm = $m->load(1);
        self::assertSame('John', $mm->get('name'));

        $mm->unload();
        self::assertFalse($mm->isLoaded());

        $mm = $m->tryLoadAny();
        self::assertTrue($mm->isLoaded());

        $mm = $m->load(2);
        self::assertSame('Jones', $mm->get('surname'));
        $mm->set('surname', 'Smith');
        $mm->save();

        $mm = $m->load(1);
        self::assertSame('John', $mm->get('name'));

        $mm = $m->load(2);
        self::assertSame('Smith', $mm->get('surname'));
    }

    public function testSaveAndUnload(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
                ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ]);

        $m = new Male($p, ['table' => 'user']);

        $m = $m->load(1);
        self::assertTrue($m->isLoaded());
        $m->set('gender', 'F');
        $m->saveAndUnload();
        self::assertFalse($m->isLoaded());

        $m = new Female($p, ['table' => 'user']);
        $m = $m->load(1);
        self::assertTrue($m->isLoaded());

        self::assertSame([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'F'],
                ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ], $this->getInternalPersistenceData($p));
    }

    public function testUpdateArray(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($p, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $mm = $m->load(1);
        $mm->set('name', 'Peter');
        $mm->save();

        $mm = $m->load(2);
        $mm->set('surname', 'Smith');
        $mm->save();
        $mm->set('surname', 'QQ');
        $mm->save();

        self::assertSame([
            'user' => [
                1 => ['name' => 'Peter', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'QQ'],
            ],
        ], $this->getInternalPersistenceData($p));

        $m = $m->createEntity();
        $m->setMulti(['name' => 'Foo', 'surname' => 'Bar']);
        $m->save();

        self::assertSame([
            'user' => [
                1 => ['name' => 'Peter', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'QQ'],
                ['name' => 'Foo', 'surname' => 'Bar'],
            ],
        ], $this->getInternalPersistenceData($p));
    }

    public function testInsert(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($p, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $m->insert(['name' => 'Foo', 'surname' => 'Bar']);

        self::assertSame([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
                ['name' => 'Foo', 'surname' => 'Bar'],
            ],
        ], $this->getInternalPersistenceData($p));

        self::assertSame(3, $p->lastInsertId());
    }

    public function testIterator(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($p, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $output = '';

        foreach ($m as $row) {
            $output .= $row->get('name');
        }

        self::assertSame('JohnSarah', $output);
    }

    public function testShortFormat(): void
    {
        $p = new Persistence\Array_([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ]);

        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $mm = $m->load(1);
        self::assertSame('John', $mm->get('name'));

        $mm = $m->load(2);
        self::assertSame('Jones', $mm->get('surname'));
        $mm->set('surname', 'Smith');
        $mm->save();

        $mm = $m->load(1);
        self::assertSame('John', $mm->get('name'));

        $mm = $m->load(2);
        self::assertSame('Smith', $mm->get('surname'));
    }

    public function testExport(): void
    {
        $p = new Persistence\Array_([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ]);

        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        self::assertSame([
            1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ['id' => 2, 'name' => 'Sarah', 'surname' => 'Jones'],
        ], $m->export());

        self::assertSame([
            1 => ['surname' => 'Smith'],
            ['surname' => 'Jones'],
        ], $m->export(['surname']));
    }

    public function testActionCount(): void
    {
        $p = new Persistence\Array_([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ]);

        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        self::assertSame(2, $m->action('count')->getOne());
        self::assertSame(2, $m->executeCountQuery());
    }

    public function testActionField(): void
    {
        $p = new Persistence\Array_([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ]);

        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        // use alias as array key if it is set
        $q = $m->action('field', ['name', 'alias' => 'first_name']);
        self::assertSame([
            1 => ['first_name' => 'John'],
            ['first_name' => 'Sarah'],
        ], $q->getRows());

        // if alias is not set, then use field name as key
        $q = $m->action('field', ['name']);
        self::assertSame([
            1 => ['name' => 'John'],
            ['name' => 'Sarah'],
        ], $q->getRows());
    }

    public function testConditionLike(): void
    {
        $dbData = ['countries' => [
            1 => ['name' => 'ABC9', 'code' => 11, 'country' => 'Ireland', 'active' => 1],
            ['name' => 'ABC8', 'code' => 12, 'country' => 'Ireland', 'active' => 0],
            ['name' => null, 'code' => 13, 'country' => 'Latvia', 'active' => 1],
            ['name' => 'ABC6', 'code' => 14, 'country' => 'UK', 'active' => 0],
            ['name' => 'ABC5', 'code' => 15, 'country' => 'UK', 'active' => 0],
            ['name' => 'ABC4', 'code' => 16, 'country' => 'Ireland', 'active' => 1],
            ['name' => 'ABC3', 'code' => 17, 'country' => 'Latvia', 'active' => 0],
            ['name' => 'ABC2', 'code' => 18, 'country' => 'Russia', 'active' => 1],
            ['name' => null, 'code' => 19, 'country' => 'Latvia', 'active' => 1],
            ['code' => null, 'country' => 'Germany', 'active' => 1],
        ]];

        $dbDataCountries = $dbData['countries'];
        foreach ($dbDataCountries as $k => $v) {
            $dbDataCountries[$k] = array_merge(['id' => $k], array_diff_key($v, ['name' => true]));
        }

        $p = new Persistence\Array_($dbData);
        $m = new Model($p, ['table' => 'countries']);
        $m->addField('code', ['type' => 'integer']);
        $m->addField('country');
        $m->addField('active', ['type' => 'boolean']);

        // case str%
        $m->addCondition('country', 'LIKE', 'La%');
        $result = $m->action('select')->getRows();
        self::assertCount(3, $result);
        self::assertSame($dbDataCountries[3], $result[3]);
        self::assertSame($dbDataCountries[7], $result[7]);
        self::assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        // case str% NOT LIKE
        $m->scope()->clear();
        $m->addCondition('country', 'NOT LIKE', 'La%');
        $result = $m->action('select')->getRows();
        self::assertCount(7, $m->export());
        self::assertSame($dbDataCountries[1], $result[1]);
        self::assertSame($dbDataCountries[2], $result[2]);
        self::assertSame($dbDataCountries[4], $result[4]);
        self::assertSame($dbDataCountries[5], $result[5]);
        self::assertSame($dbDataCountries[6], $result[6]);
        self::assertSame($dbDataCountries[8], $result[8]);
        unset($result);

        // case %str
        $m->scope()->clear();
        $m->addCondition('country', 'LIKE', '%ia');
        $result = $m->action('select')->getRows();
        self::assertCount(4, $result);
        self::assertSame($dbDataCountries[3], $result[3]);
        self::assertSame($dbDataCountries[7], $result[7]);
        self::assertSame($dbDataCountries[8], $result[8]);
        self::assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        // case %str%
        $m->scope()->clear();
        $m->addCondition('country', 'LIKE', '%a%');
        $result = $m->action('select')->getRows();
        self::assertCount(8, $result);
        self::assertSame($dbDataCountries[1], $result[1]);
        self::assertSame($dbDataCountries[2], $result[2]);
        self::assertSame($dbDataCountries[3], $result[3]);
        self::assertSame($dbDataCountries[6], $result[6]);
        self::assertSame($dbDataCountries[7], $result[7]);
        self::assertSame($dbDataCountries[8], $result[8]);
        self::assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        // case boolean field
        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '0');
        self::assertCount(4, $m->export());

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '1');
        self::assertCount(6, $m->export());

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '%0%');
        self::assertCount(4, $m->export());

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '%1%');
        self::assertCount(6, $m->export());

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '%999%');
        self::assertCount(0, $m->export());

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '%ABC%');
        self::assertCount(0, $m->export());

        // null value
        $m->scope()->clear();
        $m->addCondition('code', '=', null);
        self::assertCount(1, $m->export());

        $m->scope()->clear();
        $m->addCondition('code', '!=', null);
        self::assertCount(9, $m->export());
    }

    public function testConditionRegexp(): void
    {
        $dbData = ['countries' => [
            1 => ['name' => 'ABC9', 'code' => 11, 'country' => 'Ireland', 'active' => 1],
            ['name' => 'ABC8', 'code' => 12, 'country' => 'Ireland', 'active' => 0],
            ['name' => null, 'code' => 13, 'country' => 'Latvia', 'active' => 1],
            ['name' => 'ABC6', 'code' => 14, 'country' => 'UK', 'active' => 0],
            ['name' => 'ABC5', 'code' => 15, 'country' => 'UK', 'active' => 0],
            ['name' => 'ABC4', 'code' => 16, 'country' => 'Ireland', 'active' => 1],
            ['name' => 'ABC3', 'code' => 17, 'country' => 'Latvia', 'active' => 0],
            ['name' => 'ABC2', 'code' => 18, 'country' => 'Russia', 'active' => 1],
            ['name' => null, 'code' => 19, 'country' => 'Latvia', 'active' => 1],
        ]];

        $dbDataCountries = $dbData['countries'];
        foreach ($dbDataCountries as $k => $v) {
            $dbDataCountries[$k] = array_merge(['id' => $k], array_diff_key($v, ['name' => true]));
        }

        $p = new Persistence\Array_($dbData);
        $m = new Model($p, ['table' => 'countries']);
        $m->addField('code', ['type' => 'integer']);
        $m->addField('country');
        $m->addField('active', ['type' => 'boolean']);

        $m->scope()->clear();
        $m->addCondition('country', 'REGEXP', 'Ireland|UK');
        $result = $m->action('select')->getRows();
        self::assertCount(5, $result);
        self::assertSame($dbDataCountries[1], $result[1]);
        self::assertSame($dbDataCountries[2], $result[2]);
        self::assertSame($dbDataCountries[4], $result[4]);
        self::assertSame($dbDataCountries[5], $result[5]);
        self::assertSame($dbDataCountries[6], $result[6]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('country', 'NOT REGEXP', 'Ireland|UK|Latvia');
        $result = $m->action('select')->getRows();
        self::assertCount(1, $result);
        self::assertSame($dbDataCountries[8], $result[8]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', '>', 18);
        $result = $m->action('select')->getRows();
        self::assertCount(1, $result);
        self::assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', '>=', 18);
        $result = $m->action('select')->getRows();
        self::assertCount(2, $result);
        self::assertSame($dbDataCountries[8], $result[8]);
        self::assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', '<', 12);
        $result = $m->action('select')->getRows();
        self::assertCount(1, $result);
        self::assertSame($dbDataCountries[1], $result[1]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', '<=', 12);
        $result = $m->action('select')->getRows();
        self::assertCount(2, $result);
        self::assertSame($dbDataCountries[1], $result[1]);
        self::assertSame($dbDataCountries[2], $result[2]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', [11, 12]);
        $result = $m->action('select')->getRows();
        self::assertCount(2, $result);
        self::assertSame($dbDataCountries[1], $result[1]);
        self::assertSame($dbDataCountries[2], $result[2]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', 'IN', []);
        $result = $m->action('select')->getRows();
        self::assertCount(0, $result);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', 'NOT IN', [11, 12, 13, 14, 15, 16, 17]);
        $result = $m->action('select')->getRows();
        self::assertCount(2, $result);
        self::assertSame($dbDataCountries[8], $result[8]);
        self::assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', '!=', [11, 12, 13, 14, 15, 16, 17]);
        $result = $m->action('select')->getRows();
        self::assertCount(2, $result);
        self::assertSame($dbDataCountries[8], $result[8]);
        self::assertSame($dbDataCountries[9], $result[9]);
        unset($result);
    }

    public function testAggregates(): void
    {
        $p = new Persistence\Array_(['invoices' => [
            1 => ['number' => 'ABC9', 'items' => 11, 'active' => 1],
            ['number' => 'ABC8', 'items' => 12, 'active' => 0],
            ['items' => 13, 'active' => 1],
            ['number' => 'ABC6', 'items' => 14, 'active' => 0],
            ['number' => 'ABC5', 'items' => 15, 'active' => 0],
            ['number' => 'ABC4', 'items' => 16, 'active' => 1],
            ['number' => 'ABC3', 'items' => 17, 'active' => 0],
            ['number' => 'ABC2', 'items' => 18, 'active' => 1],
            ['items' => 19, 'active' => 1],
            ['items' => 0, 'active' => 1],
            ['items' => null, 'active' => 1],
        ]]);
        $m = new Model($p, ['table' => 'invoices']);
        $m->addField('items', ['type' => 'integer']);

        self::assertSame(13.5, $m->action('fx', ['avg', 'items'])->getOne());
        self::assertSame(12.272727272727273, $m->action('fx0', ['avg', 'items'])->getOne());
        self::assertSame(0, $m->action('fx', ['min', 'items'])->getOne());
        self::assertSame(19, $m->action('fx', ['max', 'items'])->getOne());
        self::assertSame(135, $m->action('fx', ['sum', 'items'])->getOne());
    }

    public function testExists(): void
    {
        $p = new Persistence\Array_(['invoices' => [
            1 => ['number' => 'ABC9', 'items' => 11, 'active' => 1],
        ]]);
        $m = new Model($p, ['table' => 'invoices']);
        $m->addField('items', ['type' => 'integer']);

        self::assertSame(1, $m->action('exists')->getOne());

        $m->delete(1);

        self::assertSame(0, $m->action('exists')->getOne());
    }

    /**
     * Returns exported data, but will use get() instead of export().
     *
     * @param array<int, string>|null $fields
     *
     * @return array<int, array<string, mixed>>
     */
    protected function _getRows(Model $model, array $fields = null): array
    {
        $d = [];
        foreach ($model as $row) {
            $rowData = $row->get();
            if ($fields !== null) {
                $rowData = array_intersect_key($rowData, array_flip($fields));
            }
            $d[] = $rowData;
        }

        return $d;
    }

    public function testOrder(): void
    {
        $dbData = [
            1 => ['f1' => 'A', 'f2' => 'B'],
            ['f1' => 'D', 'f2' => 'A'],
            ['f1' => 'D', 'f2' => 'C'],
            ['f1' => 'A', 'f2' => 'C'],
            ['f1' => 'E', 'f2' => 'A'],
            ['f1' => 'C', 'f2' => 'A'],
        ];

        // order by one field ascending
        $p = new Persistence\Array_($dbData);
        $m = new Model($p);
        $m->addField('f1');
        $m->addField('f2');
        $m->addField('f3');
        $m->setOrder('f1');
        $d = $this->_getRows($m, ['f1']);
        self::assertSame([
            ['f1' => 'A'],
            ['f1' => 'A'],
            ['f1' => 'C'],
            ['f1' => 'D'],
            ['f1' => 'D'],
            ['f1' => 'E'],
        ], $d);
        self::assertSame($d, array_values($m->export(['f1']))); // array_values to get rid of keys

        // order by one field descending
        $p = new Persistence\Array_($dbData);
        $m = new Model($p, ['idField' => 'myid']);
        $m->idField = 'id';
        $m->removeField('myid');
        $m->addField('id');
        $m->getField('id')->actual = 'myid';
        $m->addField('f1');
        $m->addField('f2');
        $m->addField('f3');
        $m->setOrder('f1', 'desc');
        $d = $this->_getRows($m, ['f1']);
        self::assertSame([
            ['f1' => 'E'],
            ['f1' => 'D'],
            ['f1' => 'D'],
            ['f1' => 'C'],
            ['f1' => 'A'],
            ['f1' => 'A'],
        ], $d);
        self::assertSame($d, array_values($m->export(['f1']))); // array_values to get rid of keys

        // order by two fields ascending
        $p = new Persistence\Array_($dbData);
        $m = new Model($p);
        $m->addField('f1');
        $m->addField('f2');
        $m->addField('f3');

        $m->setOrder('f1', 'desc');
        $m->setOrder('f2', 'desc');
        $d = $this->_getRows($m, ['f1', 'f2', 'id']);
        self::assertSame([
            ['id' => 5, 'f1' => 'E', 'f2' => 'A'],
            ['id' => 3, 'f1' => 'D', 'f2' => 'C'],
            ['id' => 2, 'f1' => 'D', 'f2' => 'A'],
            ['id' => 6, 'f1' => 'C', 'f2' => 'A'],
            ['id' => 4, 'f1' => 'A', 'f2' => 'C'],
            ['id' => 1, 'f1' => 'A', 'f2' => 'B'],
        ], $d);
        self::assertSame($d, array_values($m->export(['f1', 'f2', 'id']))); // array_values to get rid of keys
    }

    public function testNoKeyException(): void
    {
        $p = new Persistence\Array_([
            ['id' => 3, 'f1' => 'A'],
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Must not be a zero');
        new Model($p);
    }

    public function testImportAndAutoincrement(): void
    {
        $p = new Persistence\Array_([]);
        $m = new Model($p);
        $m->addField('f1');

        $m->import([
            ['id' => 1, 'f1' => 'A'],
            ['id' => 2, 'f1' => 'B'],
        ]);
        self::assertSame(2, $m->executeCountQuery());

        $m->import([
            ['f1' => 'C'],
            ['f1' => 'D'],
        ]);
        self::assertSame(4, $m->executeCountQuery());

        $m->import([
            ['id' => 6, 'f1' => 'E'],
            ['id' => 7, 'f1' => 'F'],
        ]);
        self::assertSame(6, $m->executeCountQuery());

        $m->delete(6);
        self::assertSame(5, $m->executeCountQuery());

        $m->import([
            ['f1' => 'G'],
            ['f1' => 'H'],
        ]);
        self::assertSame(7, $m->executeCountQuery());

        $m->import([
            ['id' => 99, 'f1' => 'I'],
            ['id' => 20, 'f1' => 'J'],
        ]);
        self::assertSame(9, $m->executeCountQuery());

        $m->import([
            ['f1' => 'K'],
            ['f1' => 'L'],
        ]);
        self::assertSame(11, $m->executeCountQuery());

        $m->delete(100);
        $m->createEntity()->set('f1', 'M')->save();

        self::assertSame([
            1 => ['id' => 1, 'f1' => 'A'],
            ['id' => 2, 'f1' => 'B'],
            ['id' => 3, 'f1' => 'C'],
            ['id' => 4, 'f1' => 'D'],
            7 => ['id' => 7, 'f1' => 'F'],
            ['id' => 8, 'f1' => 'G'],
            ['id' => 9, 'f1' => 'H'],
            99 => ['id' => 99, 'f1' => 'I'],
            20 => ['id' => 20, 'f1' => 'J'],
            101 => ['id' => 101, 'f1' => 'L'],
            ['id' => 102, 'f1' => 'M'],
        ], $m->export());
    }

    public function testLimit(): void
    {
        // order by one field ascending
        $p = new Persistence\Array_([
            1 => ['f1' => 'A'],
            ['f1' => 'D'],
            ['f1' => 'E'],
            ['f1' => 'C'],
        ]);
        $m = new Model($p);
        $m->addField('f1');

        self::assertSame(4, $m->executeCountQuery());

        $m->setLimit(3);
        self::assertSame(3, $m->executeCountQuery());
        self::assertSame([
            ['id' => 1, 'f1' => 'A'],
            ['id' => 2, 'f1' => 'D'],
            ['id' => 3, 'f1' => 'E'],
        ], array_values($m->export()));

        $m->setLimit(2, 1);
        self::assertSame(2, $m->executeCountQuery());
        self::assertSame([
            ['id' => 2, 'f1' => 'D'],
            ['id' => 3, 'f1' => 'E'],
        ], array_values($m->export()));

        // well, this is strange, that you can actually change limit on-the-fly and then previous
        // limit is not taken into account, but most likely you will never set it multiple times
        $m->setLimit(3);
        self::assertSame(3, $m->executeCountQuery());
    }

    public function testCondition(): void
    {
        $p = new Persistence\Array_($dbData = [
            1 => ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'QQ'],
            ['name' => 'Sarah', 'surname' => 'XX'],
            ['name' => 'Sarah', 'surname' => 'Smith'],
        ]);

        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        self::assertSame(4, $m->executeCountQuery());
        self::assertSame(['data' => $dbData], $this->getInternalPersistenceData($p));

        $m->addCondition('name', 'Sarah');
        self::assertSame(3, $m->executeCountQuery());

        $m->addCondition('surname', 'Smith');
        self::assertSame(1, $m->executeCountQuery());
        self::assertSame([
            4 => ['id' => 4, 'name' => 'Sarah', 'surname' => 'Smith'],
        ], $m->export());
        self::assertSame([
            4 => ['id' => 4, 'name' => 'Sarah', 'surname' => 'Smith'],
        ], $m->action('select')->getRows());

        $m->addCondition('surname', 'Siiiith');
        self::assertSame(0, $m->executeCountQuery());
    }

    public function testUnsupportedAction(): void
    {
        $p = new Persistence\Array_([1 => ['name' => 'John']]);
        $m = new Model($p);
        $m->addField('name');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported action mode');
        $m->action('foo');
    }

    public function testUnsupportedAggregate(): void
    {
        $p = new Persistence\Array_([1 => ['name' => 'John']]);
        $m = new Model($p);
        $m->addField('name');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Array persistence driver action unsupported format');
        $m->action('fx', ['UNSUPPORTED', 'name']);
    }

    public function testUnsupportedCondition1(): void
    {
        $p = new Persistence\Array_([1 => ['name' => 'John']]);
        $m = new Model($p);
        $m->addField('name');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Operator must be specified');
        $m->addCondition('name');
    }

    public function testUnsupportedCondition2(): void
    {
        $p = new Persistence\Array_([1 => ['name' => 'John']]);
        $m = new Model($p);
        $m->addField('name');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Field must be a string or an instance of Expressionable');
        $m->addCondition(new Model(), 'like', '%o%'); // @phpstan-ignore-line
    }

    public function testHasOne(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'country_id' => 1],
                ['name' => 'Sarah', 'surname' => 'Jones', 'country_id' => 2],
            ],
            'country' => [
                1 => ['name' => 'Latvia'],
                ['name' => 'UK'],
            ],
        ]);

        $user = new Model($p, ['table' => 'user']);
        $user->addField('name');
        $user->addField('surname');

        $country = new Model();
        $country->table = 'country';
        $country->addField('name');

        $user->hasOne('country_id', ['model' => $country]);

        $uu = $user->load(1);
        self::assertSame('Latvia', $uu->ref('country_id')->get('name'));

        $uu = $user->load(2);
        self::assertSame('UK', $uu->ref('country_id')->get('name'));
    }

    public function testHasMany(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'country_id' => 1],
                ['name' => 'Sarah', 'surname' => 'Jones', 'country_id' => 2],
                ['name' => 'Janis', 'surname' => 'Berzins', 'country_id' => 1],
            ],
            'country' => [
                1 => ['name' => 'Latvia'],
                ['name' => 'UK'],
            ],
        ]);

        $country = new Model($p, ['table' => 'country']);
        $country->addField('name');

        $user = new Model();
        $user->table = 'user';
        $user->addField('name');
        $user->addField('surname');

        $country->hasMany('Users', ['model' => $user]);
        $user->hasOne('country_id', ['model' => $country]);

        $cc = $country->load(1);
        self::assertSame(2, $cc->ref('Users')->executeCountQuery());

        $cc = $country->load(2);
        self::assertSame(1, $cc->ref('Users')->executeCountQuery());
    }

    public function testLoadAnyReturnsFirstRecord(): void
    {
        $a = [
            2 => ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m = $m->loadAny();
        self::assertSame(2, $m->getId());
    }

    public function testLoadAnyThrowsExceptionOnRecordNotFound(): void
    {
        $p = new Persistence\Array_();
        $m = new Model($p);
        $m->addField('name');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No record was found');
        $m->loadAny();
    }

    public function testTryLoadAnyNotThrowsExceptionOnRecordNotFound(): void
    {
        $p = new Persistence\Array_();
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $m = $m->tryLoadAny();
        self::assertNull($m);
    }
}

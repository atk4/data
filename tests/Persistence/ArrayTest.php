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
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($p, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $mm = $m->load(1);
        static::assertSame('John', $mm->get('name'));

        $mm->unload();
        static::assertFalse($mm->isLoaded());

        $mm = $m->tryLoadAny();
        static::assertTrue($mm->isLoaded());

        $mm = $m->load(2);
        static::assertSame('Jones', $mm->get('surname'));
        $mm->set('surname', 'Smith');
        $mm->save();

        $mm = $m->load(1);
        static::assertSame('John', $mm->get('name'));

        $mm = $m->load(2);
        static::assertSame('Smith', $mm->get('surname'));
    }

    public function testSaveAndUnload(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ]);

        $m = new Male($p, ['table' => 'user']);

        $m = $m->load(1);
        static::assertTrue($m->isLoaded());
        $m->set('gender', 'F');
        $m->saveAndUnload();
        static::assertFalse($m->isLoaded());

        $m = new Female($p, ['table' => 'user']);
        $m = $m->load(1);
        static::assertTrue($m->isLoaded());

        static::assertSame([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'F'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ], $this->getInternalPersistenceData($p));
    }

    public function testUpdateArray(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
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

        static::assertSame([
            'user' => [
                1 => ['name' => 'Peter', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'QQ'],
            ],
        ], $this->getInternalPersistenceData($p));

        $m = $m->createEntity();
        $m->setMulti(['name' => 'Foo', 'surname' => 'Bar']);
        $m->save();

        static::assertSame([
            'user' => [
                1 => ['name' => 'Peter', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'QQ'],
                3 => ['name' => 'Foo', 'surname' => 'Bar'],
            ],
        ], $this->getInternalPersistenceData($p));
    }

    public function testInsert(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($p, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $m->insert(['name' => 'Foo', 'surname' => 'Bar']);

        static::assertSame([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
                3 => ['name' => 'Foo', 'surname' => 'Bar'],
            ],
        ], $this->getInternalPersistenceData($p));

        static::assertSame(3, $p->lastInsertId());
    }

    public function testIterator(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($p, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $output = '';

        foreach ($m as $row) {
            $output .= $row->get('name');
        }

        static::assertSame('JohnSarah', $output);
    }

    public function testShortFormat(): void
    {
        $p = new Persistence\Array_([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ]);

        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $mm = $m->load(1);
        static::assertSame('John', $mm->get('name'));

        $mm = $m->load(2);
        static::assertSame('Jones', $mm->get('surname'));
        $mm->set('surname', 'Smith');
        $mm->save();

        $mm = $m->load(1);
        static::assertSame('John', $mm->get('name'));

        $mm = $m->load(2);
        static::assertSame('Smith', $mm->get('surname'));
    }

    public function testExport(): void
    {
        $p = new Persistence\Array_([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ]);

        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        static::assertSame([
            1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            2 => ['id' => 2, 'name' => 'Sarah', 'surname' => 'Jones'],
        ], $m->export());

        static::assertSame([
            1 => ['surname' => 'Smith'],
            2 => ['surname' => 'Jones'],
        ], $m->export(['surname']));
    }

    public function testActionCount(): void
    {
        $p = new Persistence\Array_([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ]);

        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        static::assertSame(2, $m->action('count')->getOne());
        static::assertSame(2, $m->executeCountQuery());
    }

    public function testActionField(): void
    {
        $p = new Persistence\Array_([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ]);

        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        // use alias as array key if it is set
        $q = $m->action('field', ['name', 'alias' => 'first_name']);
        static::assertSame([
            1 => ['first_name' => 'John'],
            2 => ['first_name' => 'Sarah'],
        ], $q->getRows());

        // if alias is not set, then use field name as key
        $q = $m->action('field', ['name']);
        static::assertSame([
            1 => ['name' => 'John'],
            2 => ['name' => 'Sarah'],
        ], $q->getRows());
    }

    public function testConditionLike(): void
    {
        $dbData = ['countries' => [
            1 => ['name' => 'ABC9', 'code' => 11, 'country' => 'Ireland', 'active' => 1],
            2 => ['name' => 'ABC8', 'code' => 12, 'country' => 'Ireland', 'active' => 0],
            3 => ['name' => null, 'code' => 13, 'country' => 'Latvia', 'active' => 1],
            4 => ['name' => 'ABC6', 'code' => 14, 'country' => 'UK', 'active' => 0],
            5 => ['name' => 'ABC5', 'code' => 15, 'country' => 'UK', 'active' => 0],
            6 => ['name' => 'ABC4', 'code' => 16, 'country' => 'Ireland', 'active' => 1],
            7 => ['name' => 'ABC3', 'code' => 17, 'country' => 'Latvia', 'active' => 0],
            8 => ['name' => 'ABC2', 'code' => 18, 'country' => 'Russia', 'active' => 1],
            9 => ['name' => null, 'code' => 19, 'country' => 'Latvia', 'active' => 1],
            10 => ['code' => null, 'country' => 'Germany', 'active' => 1],
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

        // case : str%
        $m->addCondition('country', 'LIKE', 'La%');
        $result = $m->action('select')->getRows();
        static::assertCount(3, $result);
        static::assertSame($dbDataCountries[3], $result[3]);
        static::assertSame($dbDataCountries[7], $result[7]);
        static::assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        // case : str% NOT LIKE
        $m->scope()->clear();
        $m->addCondition('country', 'NOT LIKE', 'La%');
        $result = $m->action('select')->getRows();
        static::assertCount(7, $m->export());
        static::assertSame($dbDataCountries[1], $result[1]);
        static::assertSame($dbDataCountries[2], $result[2]);
        static::assertSame($dbDataCountries[4], $result[4]);
        static::assertSame($dbDataCountries[5], $result[5]);
        static::assertSame($dbDataCountries[6], $result[6]);
        static::assertSame($dbDataCountries[8], $result[8]);
        unset($result);

        // case : %str
        $m->scope()->clear();
        $m->addCondition('country', 'LIKE', '%ia');
        $result = $m->action('select')->getRows();
        static::assertCount(4, $result);
        static::assertSame($dbDataCountries[3], $result[3]);
        static::assertSame($dbDataCountries[7], $result[7]);
        static::assertSame($dbDataCountries[8], $result[8]);
        static::assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        // case : %str%
        $m->scope()->clear();
        $m->addCondition('country', 'LIKE', '%a%');
        $result = $m->action('select')->getRows();
        static::assertCount(8, $result);
        static::assertSame($dbDataCountries[1], $result[1]);
        static::assertSame($dbDataCountries[2], $result[2]);
        static::assertSame($dbDataCountries[3], $result[3]);
        static::assertSame($dbDataCountries[6], $result[6]);
        static::assertSame($dbDataCountries[7], $result[7]);
        static::assertSame($dbDataCountries[8], $result[8]);
        static::assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        // case : boolean field
        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '0');
        static::assertCount(4, $m->export());

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '1');
        static::assertCount(6, $m->export());

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '%0%');
        static::assertCount(4, $m->export());

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '%1%');
        static::assertCount(6, $m->export());

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '%999%');
        static::assertCount(0, $m->export());

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '%ABC%');
        static::assertCount(0, $m->export());

        // null value
        $m->scope()->clear();
        $m->addCondition('code', '=', null);
        static::assertCount(1, $m->export());

        $m->scope()->clear();
        $m->addCondition('code', '!=', null);
        static::assertCount(9, $m->export());
    }

    public function testConditionRegexp(): void
    {
        $dbData = ['countries' => [
            1 => ['name' => 'ABC9', 'code' => 11, 'country' => 'Ireland', 'active' => 1],
            2 => ['name' => 'ABC8', 'code' => 12, 'country' => 'Ireland', 'active' => 0],
            3 => ['name' => null, 'code' => 13, 'country' => 'Latvia', 'active' => 1],
            4 => ['name' => 'ABC6', 'code' => 14, 'country' => 'UK', 'active' => 0],
            5 => ['name' => 'ABC5', 'code' => 15, 'country' => 'UK', 'active' => 0],
            6 => ['name' => 'ABC4', 'code' => 16, 'country' => 'Ireland', 'active' => 1],
            7 => ['name' => 'ABC3', 'code' => 17, 'country' => 'Latvia', 'active' => 0],
            8 => ['name' => 'ABC2', 'code' => 18, 'country' => 'Russia', 'active' => 1],
            9 => ['name' => null, 'code' => 19, 'country' => 'Latvia', 'active' => 1],
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
        static::assertCount(5, $result);
        static::assertSame($dbDataCountries[1], $result[1]);
        static::assertSame($dbDataCountries[2], $result[2]);
        static::assertSame($dbDataCountries[4], $result[4]);
        static::assertSame($dbDataCountries[5], $result[5]);
        static::assertSame($dbDataCountries[6], $result[6]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('country', 'NOT REGEXP', 'Ireland|UK|Latvia');
        $result = $m->action('select')->getRows();
        static::assertCount(1, $result);
        static::assertSame($dbDataCountries[8], $result[8]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', '>', 18);
        $result = $m->action('select')->getRows();
        static::assertCount(1, $result);
        static::assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', '>=', 18);
        $result = $m->action('select')->getRows();
        static::assertCount(2, $result);
        static::assertSame($dbDataCountries[8], $result[8]);
        static::assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', '<', 12);
        $result = $m->action('select')->getRows();
        static::assertCount(1, $result);
        static::assertSame($dbDataCountries[1], $result[1]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', '<=', 12);
        $result = $m->action('select')->getRows();
        static::assertCount(2, $result);
        static::assertSame($dbDataCountries[1], $result[1]);
        static::assertSame($dbDataCountries[2], $result[2]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', [11, 12]);
        $result = $m->action('select')->getRows();
        static::assertCount(2, $result);
        static::assertSame($dbDataCountries[1], $result[1]);
        static::assertSame($dbDataCountries[2], $result[2]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', 'IN', []);
        $result = $m->action('select')->getRows();
        static::assertCount(0, $result);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', 'NOT IN', [11, 12, 13, 14, 15, 16, 17]);
        $result = $m->action('select')->getRows();
        static::assertCount(2, $result);
        static::assertSame($dbDataCountries[8], $result[8]);
        static::assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', '!=', [11, 12, 13, 14, 15, 16, 17]);
        $result = $m->action('select')->getRows();
        static::assertCount(2, $result);
        static::assertSame($dbDataCountries[8], $result[8]);
        static::assertSame($dbDataCountries[9], $result[9]);
        unset($result);
    }

    public function testAggregates(): void
    {
        $p = new Persistence\Array_(['invoices' => [
            1 => ['number' => 'ABC9', 'items' => 11, 'active' => 1],
            2 => ['number' => 'ABC8', 'items' => 12, 'active' => 0],
            3 => ['items' => 13, 'active' => 1],
            4 => ['number' => 'ABC6', 'items' => 14, 'active' => 0],
            5 => ['number' => 'ABC5', 'items' => 15, 'active' => 0],
            6 => ['number' => 'ABC4', 'items' => 16, 'active' => 1],
            7 => ['number' => 'ABC3', 'items' => 17, 'active' => 0],
            8 => ['number' => 'ABC2', 'items' => 18, 'active' => 1],
            9 => ['items' => 19, 'active' => 1],
            10 => ['items' => 0, 'active' => 1],
            11 => ['items' => null, 'active' => 1],
        ]]);
        $m = new Model($p, ['table' => 'invoices']);
        $m->addField('items', ['type' => 'integer']);

        static::assertSame(13.5, $m->action('fx', ['avg', 'items'])->getOne());
        static::assertSame(12.272727272727273, $m->action('fx0', ['avg', 'items'])->getOne());
        static::assertSame(0, $m->action('fx', ['min', 'items'])->getOne());
        static::assertSame(19, $m->action('fx', ['max', 'items'])->getOne());
        static::assertSame(135, $m->action('fx', ['sum', 'items'])->getOne());
    }

    public function testExists(): void
    {
        $p = new Persistence\Array_(['invoices' => [
            1 => ['number' => 'ABC9', 'items' => 11, 'active' => 1],
        ]]);
        $m = new Model($p, ['table' => 'invoices']);
        $m->addField('items', ['type' => 'integer']);

        static::assertSame(1, $m->action('exists')->getOne());

        $m->delete(1);

        static::assertSame(0, $m->action('exists')->getOne());
    }

    /**
     * Returns exported data, but will use get() instead of export().
     */
    protected function _getRows(Model $model, array $fields = []): array
    {
        $d = [];
        foreach ($model as $row) {
            $rowData = $row->get();
            $rowData = $fields ? array_intersect_key($rowData, array_flip($fields)) : $rowData;
            $d[] = $rowData;
        }

        return $d;
    }

    public function testOrder(): void
    {
        $dbData = [
            1 => ['f1' => 'A', 'f2' => 'B'],
            2 => ['f1' => 'D', 'f2' => 'A'],
            3 => ['f1' => 'D', 'f2' => 'C'],
            4 => ['f1' => 'A', 'f2' => 'C'],
            5 => ['f1' => 'E', 'f2' => 'A'],
            6 => ['f1' => 'C', 'f2' => 'A'],
        ];

        // order by one field ascending
        $p = new Persistence\Array_($dbData);
        $m = new Model($p);
        $m->addField('f1');
        $m->addField('f2');
        $m->addField('f3');
        $m->setOrder('f1');
        $d = $this->_getRows($m, ['f1']);
        static::assertSame([
            ['f1' => 'A'],
            ['f1' => 'A'],
            ['f1' => 'C'],
            ['f1' => 'D'],
            ['f1' => 'D'],
            ['f1' => 'E'],
        ], $d);
        static::assertSame($d, array_values($m->export(['f1']))); // array_values to get rid of keys

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
        static::assertSame([
            ['f1' => 'E'],
            ['f1' => 'D'],
            ['f1' => 'D'],
            ['f1' => 'C'],
            ['f1' => 'A'],
            ['f1' => 'A'],
        ], $d);
        static::assertSame($d, array_values($m->export(['f1']))); // array_values to get rid of keys

        // order by two fields ascending
        $p = new Persistence\Array_($dbData);
        $m = new Model($p);
        $m->addField('f1');
        $m->addField('f2');
        $m->addField('f3');

        $m->setOrder('f1', 'desc');
        $m->setOrder('f2', 'desc');
        $d = $this->_getRows($m, ['f1', 'f2', 'id']);
        static::assertSame([
            ['id' => 5, 'f1' => 'E', 'f2' => 'A'],
            ['id' => 3, 'f1' => 'D', 'f2' => 'C'],
            ['id' => 2, 'f1' => 'D', 'f2' => 'A'],
            ['id' => 6, 'f1' => 'C', 'f2' => 'A'],
            ['id' => 4, 'f1' => 'A', 'f2' => 'C'],
            ['id' => 1, 'f1' => 'A', 'f2' => 'B'],
        ], $d);
        static::assertSame($d, array_values($m->export(['f1', 'f2', 'id']))); // array_values to get rid of keys
    }

    public function testNoKeyException(): void
    {
        $p = new Persistence\Array_([
            ['id' => 3, 'f1' => 'A'],
        ]);

        $this->expectException(Exception::class);
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
        static::assertSame(2, $m->executeCountQuery());

        $m->import([
            ['f1' => 'C'],
            ['f1' => 'D'],
        ]);
        static::assertSame(4, $m->executeCountQuery());

        $m->import([
            ['id' => 6, 'f1' => 'E'],
            ['id' => 7, 'f1' => 'F'],
        ]);
        static::assertSame(6, $m->executeCountQuery());

        $m->delete(6);
        static::assertSame(5, $m->executeCountQuery());

        $m->import([
            ['f1' => 'G'],
            ['f1' => 'H'],
        ]);
        static::assertSame(7, $m->executeCountQuery());

        $m->import([
            ['id' => 99, 'f1' => 'I'],
            ['id' => 20, 'f1' => 'J'],
        ]);
        static::assertSame(9, $m->executeCountQuery());

        $m->import([
            ['f1' => 'K'],
            ['f1' => 'L'],
        ]);
        static::assertSame(11, $m->executeCountQuery());

        $m->delete(100);
        $m->createEntity()->set('f1', 'M')->save();

        static::assertSame([
            1 => ['id' => 1, 'f1' => 'A'],
            2 => ['id' => 2, 'f1' => 'B'],
            3 => ['id' => 3, 'f1' => 'C'],
            4 => ['id' => 4, 'f1' => 'D'],
            7 => ['id' => 7, 'f1' => 'F'],
            8 => ['id' => 8, 'f1' => 'G'],
            9 => ['id' => 9, 'f1' => 'H'],
            99 => ['id' => 99, 'f1' => 'I'],
            20 => ['id' => 20, 'f1' => 'J'],
            101 => ['id' => 101, 'f1' => 'L'],
            102 => ['id' => 102, 'f1' => 'M'],
        ], $m->export());
    }

    public function testLimit(): void
    {
        // order by one field ascending
        $p = new Persistence\Array_([
            1 => ['f1' => 'A'],
            2 => ['f1' => 'D'],
            3 => ['f1' => 'E'],
            4 => ['f1' => 'C'],
        ]);
        $m = new Model($p);
        $m->addField('f1');

        static::assertSame(4, $m->executeCountQuery());

        $m->setLimit(3);
        static::assertSame(3, $m->executeCountQuery());
        static::assertSame([
            ['id' => 1, 'f1' => 'A'],
            ['id' => 2, 'f1' => 'D'],
            ['id' => 3, 'f1' => 'E'],
        ], array_values($m->export()));

        $m->setLimit(2, 1);
        static::assertSame(2, $m->executeCountQuery());
        static::assertSame([
            ['id' => 2, 'f1' => 'D'],
            ['id' => 3, 'f1' => 'E'],
        ], array_values($m->export()));

        // well, this is strange, that you can actually change limit on-the-fly and then previous
        // limit is not taken into account, but most likely you will never set it multiple times
        $m->setLimit(3);
        static::assertSame(3, $m->executeCountQuery());
    }

    public function testCondition(): void
    {
        $p = new Persistence\Array_($dbData = [
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'QQ'],
            3 => ['name' => 'Sarah', 'surname' => 'XX'],
            4 => ['name' => 'Sarah', 'surname' => 'Smith'],
        ]);

        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        static::assertSame(4, $m->executeCountQuery());
        static::assertSame(['data' => $dbData], $this->getInternalPersistenceData($p));

        $m->addCondition('name', 'Sarah');
        static::assertSame(3, $m->executeCountQuery());

        $m->addCondition('surname', 'Smith');
        static::assertSame(1, $m->executeCountQuery());
        static::assertSame([
            4 => ['id' => 4, 'name' => 'Sarah', 'surname' => 'Smith'],
        ], $m->export());
        static::assertSame([
            4 => ['id' => 4, 'name' => 'Sarah', 'surname' => 'Smith'],
        ], $m->action('select')->getRows());

        $m->addCondition('surname', 'Siiiith');
        static::assertSame(0, $m->executeCountQuery());
    }

    public function testUnsupportedAction(): void
    {
        $p = new Persistence\Array_([1 => ['name' => 'John']]);
        $m = new Model($p);
        $m->addField('name');

        $this->expectException(Exception::class);
        $m->action('foo');
    }

    public function testUnsupportedAggregate(): void
    {
        $p = new Persistence\Array_([1 => ['name' => 'John']]);
        $m = new Model($p);
        $m->addField('name');

        $this->expectException(Exception::class);
        $m->action('fx', ['UNSUPPORTED', 'name']);
    }

    public function testUnsupportedCondition1(): void
    {
        $p = new Persistence\Array_([1 => ['name' => 'John']]);
        $m = new Model($p);
        $m->addField('name');

        $this->expectException(Exception::class);
        $m->addCondition('name');
    }

    public function testUnsupportedCondition2(): void
    {
        $p = new Persistence\Array_([1 => ['name' => 'John']]);
        $m = new Model($p);
        $m->addField('name');

        $this->expectException(Exception::class);
        $m->addCondition(new Model(), 'like', '%o%');
    }

    public function testHasOne(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'country_id' => 1],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'country_id' => 2],
            ],
            'country' => [
                1 => ['name' => 'Latvia'],
                2 => ['name' => 'UK'],
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
        static::assertSame('Latvia', $uu->ref('country_id')->get('name'));

        $uu = $user->load(2);
        static::assertSame('UK', $uu->ref('country_id')->get('name'));
    }

    public function testHasMany(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'country_id' => 1],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'country_id' => 2],
                3 => ['name' => 'Janis', 'surname' => 'Berzins', 'country_id' => 1],
            ],
            'country' => [
                1 => ['name' => 'Latvia'],
                2 => ['name' => 'UK'],
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
        static::assertSame(2, $cc->ref('Users')->executeCountQuery());

        $cc = $country->load(2);
        static::assertSame(1, $cc->ref('Users')->executeCountQuery());
    }

    public function testLoadAnyReturnsFirstRecord(): void
    {
        $a = [
            2 => ['name' => 'John', 'surname' => 'Smith'],
            3 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m = $m->loadAny();
        static::assertSame(2, $m->getId());
    }

    public function testLoadAnyThrowsExceptionOnRecordNotFound(): void
    {
        $p = new Persistence\Array_();
        $m = new Model($p);
        $m->addField('name');

        $this->expectExceptionCode(404);
        $m->loadAny();
    }

    public function testTryLoadAnyNotThrowsExceptionOnRecordNotFound(): void
    {
        $p = new Persistence\Array_();
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $m = $m->tryLoadAny();
        static::assertNull($m);
    }
}

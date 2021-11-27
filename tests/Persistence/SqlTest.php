<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

class SqlTest extends TestCase
{
    public function testLoadArray(): void
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
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

    public function testModelLoadOneAndAny(): void
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $mm = (clone $m)->addCondition($m->idField, 1);
        self::assertSame('John', $mm->load(1)->get('name'));
        self::assertNull($mm->tryLoad(2));
        self::assertSame('John', $mm->loadOne()->get('name'));
        self::assertSame('John', $mm->tryLoadOne()->get('name'));
        self::assertSame('John', $mm->loadAny()->get('name'));
        self::assertSame('John', $mm->tryLoadAny()->get('name'));

        $mm = (clone $m)->addCondition('surname', 'Jones');
        self::assertSame('Sarah', $mm->load(2)->get('name'));
        self::assertNull($mm->tryLoad(1));
        self::assertSame('Sarah', $mm->loadOne()->get('name'));
        self::assertSame('Sarah', $mm->tryLoadOne()->get('name'));
        self::assertSame('Sarah', $mm->loadAny()->get('name'));
        self::assertSame('Sarah', $mm->tryLoadAny()->get('name'));

        $m->loadAny();
        $m->tryLoadAny();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Ambiguous conditions, more than one record can be loaded');
        $m->tryLoadOne();
    }

    public function testPersistenceInsert(): void
    {
        $dbData = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];

        $this->setDb($dbData);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $ids = [];
        foreach ($dbData['user'] as $id => $row) {
            $ids[] = $this->db->insert($m, $row);
        }

        $mm = $m->load($ids[0]);
        self::assertSame('John', $mm->get('name'));

        $mm = $m->load($ids[1]);
        self::assertSame('Jones', $mm->get('surname'));
        $mm->set('surname', 'Smith');
        $mm->save();

        $mm = $m->load($ids[0]);
        self::assertSame('John', $mm->get('name'));

        $mm = $m->load($ids[1]);
        self::assertSame('Smith', $mm->get('surname'));
    }

    public function testModelInsert(): void
    {
        $dbData = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];
        $this->setDb($dbData);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $ms = [];
        foreach ($dbData['user'] as $id => $row) {
            $ms[] = $m->insert($row);
        }

        self::assertSame('John', $m->load($ms[0])->get('name'));

        self::assertSame('Jones', $m->load($ms[1])->get('surname'));
    }

    public function testModelSaveNoReload(): void
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        // insert new record, model id field
        $m->reloadAfterSave = false;
        $m = $m->createEntity();
        $m->save(['name' => 'Jane', 'surname' => 'Doe']);
        self::assertSame('Jane', $m->get('name'));
        self::assertSame('Doe', $m->get('surname'));
        // ID field is set with new value even if reloadAfterSave = false
        self::assertSame(3, $m->getId());
    }

    public function testModelInsertRows(): void
    {
        $dbData = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];
        $this->setDb($dbData, false); // create empty table

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        self::assertSame('0', $m->action('exists')->getOne());

        $m->import($dbData['user']); // import data

        self::assertSame('1', $m->action('exists')->getOne());

        self::assertSame(2, $m->executeCountQuery());
    }

    public function testPersistenceDelete(): void
    {
        $dbData = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];
        $this->setDb($dbData);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $ids = [];
        foreach ($dbData['user'] as $id => $row) {
            $ids[] = $this->db->insert($m, $row);
        }

        $m->delete($ids[0]);

        $m2 = $m->load($ids[1]);
        self::assertSame('Jones', $m2->get('surname'));
        $m2->set('surname', 'Smith');
        $m2->save();

        $m2 = $m->tryLoad($ids[0]);
        self::assertNull($m2);

        $m2 = $m->load($ids[1]);
        self::assertSame('Smith', $m2->get('surname'));
    }

    public function testExport(): void
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        self::assertSameExportUnordered([
            ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ['id' => 2, 'name' => 'Sarah', 'surname' => 'Jones'],
        ], $m->export());

        self::assertSameExportUnordered([
            ['surname' => 'Smith'],
            ['surname' => 'Jones'],
        ], $m->export(['surname']));
    }

    public function testSameRowFieldStability(): void
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            $randSqlFunc = 'rand()';
        } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            $randSqlFunc = 'checksum(newid())';
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            $randSqlFunc = 'dbms_random.random';
        } else {
            $randSqlFunc = 'random()';
        }

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');
        $m->addExpression('rand', ['expr' => $randSqlFunc]);
        $m->addExpression('rand_independent', ['expr' => $randSqlFunc]);
        $m->scope()->addCondition('rand', '!=', null);
        $m->setOrder('rand');
        $m->addExpression('rand2', ['expr' => $m->expr('([] + 1) - 1', [$m->getField('rand')])]);
        $createSeedForSelfHasOne = static function (Model $model, string $alias, $joinByFieldName) {
            return ['model' => $model, 'table_alias' => $alias, 'our_field' => $joinByFieldName, 'their_field' => $joinByFieldName];
        };
        // $m->hasOne('one', $createSeedForSelfHasOne($m, 'one', 'name'))
        //     ->addField('rand3', 'rand2');
        // $m->hasOne('one_one', $createSeedForSelfHasOne($m->ref('one'), 'one_one', 'surname'))
        //     ->addField('rand4', 'rand3');
        // $manyModel = $m/* ->ref('one') */; // TODO MySQL Subquery returns more than 1 row
        // $manyModel->addExpression('rand_many', ['expr' => $manyModel->getField('rand3')]);
        // $m->hasMany('many_one', ['model' => $manyModel, 'our_field' => 'name', 'their_field' => 'name']);
        // $m->hasOne('one_many_one', $createSeedForSelfHasOne($m->ref('many_one'), 'one_many_one', 'surname'))
        //     ->addField('rand5', 'rand_many');

        $this->debug = true; // TODO

        $export = $m->export();
        self::assertSame([0, 1], array_keys($export));
        $randRow0 = $export[0]['rand'];
        $randRow1 = $export[1]['rand'];
        self::assertNotSame($randRow0, $randRow1); // self::assertGreaterThan($randRow0, $randRow1);
        // TODO this can be the same, depending on how we implement it
        // already stable under some circumstances on PostgreSQL http://sqlfiddle.com/#!17/4b040/4
        // self::assertNotSame($randRow0, $export[0]['rand_independent']);

        self::assertSame($randRow0, $export[0]['rand2']);
        self::assertSame($randRow1, $export[1]['rand2']);
        // self::assertSame($randRow0, $export[0]['rand3']);
        // self::assertSame($randRow1, $export[1]['rand3']);
        // self::assertSame($randRow0, $export[0]['rand4']);
        // self::assertSame($randRow1, $export[1]['rand4']);
        // self::assertSame($randRow0, $export[0]['rand5']);
        // self::assertSame($randRow1, $export[1]['rand5']);

        // TODO test with hasOne group by
    }
}

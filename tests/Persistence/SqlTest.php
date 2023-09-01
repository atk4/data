<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

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
}

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
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $mm = $m->load(1);
        $this->assertSame('John', $mm->get('name'));

        $mm = $m->load(2);
        $this->assertSame('Jones', $mm->get('surname'));
        $mm->set('surname', 'Smith');
        $mm->save();

        $mm = $m->load(1);
        $this->assertSame('John', $mm->get('name'));

        $mm = $m->load(2);
        $this->assertSame('Smith', $mm->get('surname'));
    }

    public function testModelLoadOneAndAny(): void
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $mm = (clone $m)->addCondition($m->id_field, 1);
        $this->assertSame('John', $mm->load(1)->get('name'));
        $this->assertNull($mm->tryLoad(2));
        $this->assertSame('John', $mm->tryLoadOne()->get('name'));
        $this->assertSame('John', $mm->loadOne()->get('name'));
        $this->assertSame('John', $mm->tryLoadAny()->get('name'));
        $this->assertSame('John', $mm->loadAny()->get('name'));

        $mm = (clone $m)->addCondition('surname', 'Jones');
        $this->assertSame('Sarah', $mm->load(2)->get('name'));
        $this->assertNull($mm->tryLoad(1));
        $this->assertSame('Sarah', $mm->tryLoadOne()->get('name'));
        $this->assertSame('Sarah', $mm->loadOne()->get('name'));
        $this->assertSame('Sarah', $mm->tryLoadAny()->get('name'));
        $this->assertSame('Sarah', $mm->loadAny()->get('name'));

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
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
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
        $this->assertSame('John', $mm->get('name'));

        $mm = $m->load($ids[1]);
        $this->assertSame('Jones', $mm->get('surname'));
        $mm->set('surname', 'Smith');
        $mm->save();

        $mm = $m->load($ids[0]);
        $this->assertSame('John', $mm->get('name'));

        $mm = $m->load($ids[1]);
        $this->assertSame('Smith', $mm->get('surname'));
    }

    public function testModelInsert(): void
    {
        $dbData = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
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

        $this->assertSame('John', $m->load($ms[0])->get('name'));

        $this->assertSame('Jones', $m->load($ms[1])->get('surname'));
    }

    public function testModelSaveNoReload(): void
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        // insert new record, model id field
        $m->reload_after_save = false;
        $m = $m->createEntity();
        $m->save(['name' => 'Jane', 'surname' => 'Doe']);
        $this->assertSame('Jane', $m->get('name'));
        $this->assertSame('Doe', $m->get('surname'));
        $this->assertEquals(3, $m->getId());
        // id field value is set with new id value even if reload_after_save = false
        $this->assertEquals(3, $m->getId());
    }

    public function testModelInsertRows(): void
    {
        $dbData = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];
        $this->setDb($dbData, false); // create empty table

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $this->assertSame('0', $m->action('exists')->getOne());

        $m->import($dbData['user']); // import data

        $this->assertSame('1', $m->action('exists')->getOne());

        $this->assertSame('2', $m->action('count')->getOne());
    }

    public function testPersistenceDelete(): void
    {
        $dbData = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
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
        $this->assertSame('Jones', $m2->get('surname'));
        $m2->set('surname', 'Smith');
        $m2->save();

        $m2 = $m->tryLoad($ids[0]);
        $this->assertNull($m2);

        $m2 = $m->load($ids[1]);
        $this->assertSame('Smith', $m2->get('surname'));
    }

    public function testExport(): void
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $this->assertSameExportUnordered([
            ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ['id' => 2, 'name' => 'Sarah', 'surname' => 'Jones'],
        ], $m->export());

        $this->assertSameExportUnordered([
            ['surname' => 'Smith'],
            ['surname' => 'Jones'],
        ], $m->export(['surname']));
    }
}

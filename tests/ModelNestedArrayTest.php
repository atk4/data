<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\HookBreaker;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Schema\TestCase;

class ModelNestedArrayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->db->connection->connection()->close();

        $this->db = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', '_birthday' => '1980-02-01'],
                ['name' => 'Sue', '_birthday' => '2005-04-03'],
                ['name' => 'Veronica', '_birthday' => '2005-04-03'],
            ],
        ]);
    }

    /** @var array */
    public $hookLog = [];

    protected function createTestModel(): Model
    {
        $mWithLoggingClass = get_class(new class() extends Model {
            /** @var \WeakReference<ModelNestedArrayTest> */
            protected $testCaseWeakRef;
            /** @var string */
            protected $testModelAlias;

            /**
             * @param mixed $v
             *
             * @return mixed
             */
            protected function convertValueToLog($v)
            {
                if (is_array($v)) {
                    return array_map(fn ($v) => $this->convertValueToLog($v), $v);
                } elseif (is_scalar($v) || $v === null) {
                    return $v;
                } elseif ($v instanceof self) {
                    return $this->testModelAlias;
                }

                return get_debug_type($v);
            }

            public function hook(string $spot, array $args = [], HookBreaker &$brokenBy = null)
            {
                if (!str_starts_with($spot, '__atk__method__') && $spot !== Model::HOOK_NORMALIZE) {
                    $this->testCaseWeakRef->get()->hookLog[] = [$this->convertValueToLog($this), $spot, $this->convertValueToLog($args)];
                }

                return parent::hook($spot, $args, $brokenBy);
            }

            public function atomic(\Closure $fx)
            {
                $this->testCaseWeakRef->get()->hookLog[] = [$this->convertValueToLog($this), '>>>'];

                $res = parent::atomic($fx);

                $this->testCaseWeakRef->get()->hookLog[] = [$this->convertValueToLog($this), '<<<'];

                return $res;
            }
        });

        $mInner = new $mWithLoggingClass($this->db, [
            'testCaseWeakRef' => \WeakReference::create($this),
            'testModelAlias' => 'inner',
            'table' => 'user',
            'id_field' => '_id',
        ]);
        $mInner->removeField('_id');
        $mInner->id_field = 'uid';
        $mInner->addField('uid', ['actual' => '_id', 'type' => 'integer']);
        $mInner->addField('name');
        $mInner->addField('y', ['actual' => '_birthday', 'type' => 'date']);
        $mInner->addCondition('uid', '!=', 3);

        $m = new $mWithLoggingClass($this->db, [
            'testCaseWeakRef' => \WeakReference::create($this),
            'testModelAlias' => 'main',
            'table' => $mInner,
        ]);
        $m->removeField('id');
        $m->id_field = 'birthday';
        $m->addField('name');
        $m->addField('birthday', ['actual' => 'y', 'type' => 'date']);

        return $m;
    }

    public function testSelectExport(): void
    {
        $m = $this->createTestModel();

        $this->assertSameExportUnordered([
            1 => ['name' => 'John', 'birthday' => new \DateTime('1980-2-1')],
            ['name' => 'Sue', 'birthday' => new \DateTime('2005-4-3')],
        ], $m->export());

        $this->assertSame([
        ], $this->hookLog);
    }

    public function testInsert(): void
    {
        $m = $this->createTestModel();

        $entity = $m->createEntity()
            ->setMulti([
                'name' => 'Karl',
                'birthday' => new \DateTime('2000-6-1'),
            ])->save();

        $this->assertSame([
            ['main', '>>>'],
            ['main', Model::HOOK_VALIDATE, ['save']],
            ['main', Model::HOOK_BEFORE_SAVE, [false]],
            ['main', Model::HOOK_BEFORE_INSERT, [['name' => 'Karl', 'birthday' => \DateTime::class]]],
            ['inner', '>>>'],
            ['inner', Model::HOOK_VALIDATE, ['save']],
            ['inner', Model::HOOK_BEFORE_SAVE, [false]],
            ['inner', Model::HOOK_BEFORE_INSERT, [['uid' => null, 'name' => 'Karl', 'y' => \DateTime::class]]],
            ['inner', Model::HOOK_AFTER_INSERT, []],
            ['inner', Model::HOOK_AFTER_SAVE, [false]],
            ['inner', '<<<'],
            ['main', Model::HOOK_AFTER_INSERT, []],
            ['main', Model::HOOK_BEFORE_UNLOAD, []],
            ['main', Model::HOOK_AFTER_UNLOAD, []],
            ['main', Model::HOOK_BEFORE_LOAD, [\DateTime::class]],
            ['main', Model::HOOK_AFTER_LOAD, []],
            ['main', Model::HOOK_AFTER_SAVE, [false]],
            ['main', '<<<'],
        ], $this->hookLog);

        $this->assertSame(4, $m->table->loadBy('name', 'Karl')->getId());
        $this->assertSameExportUnordered([[new \DateTime('2000-6-1')]], [[$entity->getId()]]);

        $this->assertSameExportUnordered([
            1 => ['name' => 'John', 'birthday' => new \DateTime('1980-2-1')],
            ['name' => 'Sue', 'birthday' => new \DateTime('2005-4-3')],
            4 => ['name' => 'Karl', 'birthday' => new \DateTime('2000-6-1')],
        ], $m->export());
    }

    public function testUpdate(): void
    {
        $m = $this->createTestModel();

        $m->load(new \DateTime('2005-4-3'))
            ->setMulti([
                'name' => 'Susan',
            ])->save();

        $this->assertSame([
            ['main', Model::HOOK_BEFORE_LOAD, [\DateTime::class]],
            ['main', Model::HOOK_AFTER_LOAD, []],

            ['main', '>>>'],
            ['main', Model::HOOK_VALIDATE, ['save']],
            ['main', Model::HOOK_BEFORE_SAVE, [true]],
            ['main', Model::HOOK_BEFORE_UPDATE, [['name' => 'Susan']]],
            ['inner', Model::HOOK_BEFORE_LOAD, [null]],
            ['inner', Model::HOOK_AFTER_LOAD, []],
            ['inner', '>>>'],
            ['inner', Model::HOOK_VALIDATE, ['save']],
            ['inner', Model::HOOK_BEFORE_SAVE, [true]],
            ['inner', Model::HOOK_BEFORE_UPDATE, [['name' => 'Susan']]],
            ['inner', Model::HOOK_AFTER_UPDATE, [['name' => 'Susan']]],
            ['inner', Model::HOOK_AFTER_SAVE, [true]],
            ['inner', '<<<'],
            ['main', Model::HOOK_AFTER_UPDATE, [['name' => 'Susan']]],
            ['main', Model::HOOK_AFTER_SAVE, [true]],
            ['main', '<<<'],
        ], $this->hookLog);

        $this->assertSameExportUnordered([
            1 => ['name' => 'John', 'birthday' => new \DateTime('1980-2-1')],
            ['name' => 'Susan', 'birthday' => new \DateTime('2005-4-3')],
        ], $m->export());
    }

    public function testDelete(): void
    {
        $m = $this->createTestModel();

        $m->delete(new \DateTime('2005-4-3'));

        $this->assertSame([
            ['main', Model::HOOK_BEFORE_LOAD, [\DateTime::class]],
            ['main', Model::HOOK_AFTER_LOAD, []],

            ['main', '>>>'],
            ['main', Model::HOOK_BEFORE_DELETE, []],
            ['inner', Model::HOOK_BEFORE_LOAD, [null]],
            ['inner', Model::HOOK_AFTER_LOAD, []],
            ['inner', '>>>'],
            ['inner', Model::HOOK_BEFORE_DELETE, []],
            ['inner', Model::HOOK_AFTER_DELETE, []],
            ['inner', '<<<'],
            ['inner', Model::HOOK_BEFORE_UNLOAD, []],
            ['inner', Model::HOOK_AFTER_UNLOAD, []],
            ['main', Model::HOOK_AFTER_DELETE, []],
            ['main', '<<<'],
            ['main', Model::HOOK_BEFORE_UNLOAD, []],
            ['main', Model::HOOK_AFTER_UNLOAD, []],
        ], $this->hookLog);

        $this->assertSameExportUnordered([
            1 => ['name' => 'John', 'birthday' => new \DateTime('1980-2-1')],
        ], $m->export());
    }
}

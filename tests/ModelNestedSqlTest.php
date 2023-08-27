<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\HookBreaker;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql\Query;
use Atk4\Data\Schema\TestCase;

class ModelNestedSqlTest extends TestCase
{
    /** @var array<array{string, string, 2?: array<int, mixed>}> */
    public array $hookLog = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setDb([
            'user' => [
                ['_id' => 1, 'name' => 'John', '_birthday' => '1980-02-01'],
                ['_id' => 2, 'name' => 'Sue', '_birthday' => '2005-04-03'],
                ['_id' => 3, 'name' => 'Veronica', '_birthday' => '2005-04-03'],
            ],
        ]);
    }

    protected function createTestModel(): Model
    {
        $mWithLoggingClass = get_class(new class() extends Model {
            /** @var \WeakReference<ModelNestedSqlTest> */
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

                $res = preg_replace('~(?<=^Atk4\\\\Data\\\\Persistence\\\\Sql\\\\)\w+\\\\(?=\w+$)~', '', get_debug_type($v));

                return $res;
            }

            public function hook(string $spot, array $args = [], HookBreaker &$brokenBy = null)
            {
                if (!str_starts_with($spot, '__atk4__dynamic_method__') && $spot !== Model::HOOK_NORMALIZE) {
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
        ]);
        $mInner->removeField('id');
        $mInner->idField = 'uid';
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
        $m->idField = 'birthday';
        $m->addField('name');
        $m->addField('birthday', ['actual' => 'y', 'type' => 'date']);

        return $m;
    }

    public function testSelectSql(): void
    {
        $m = $this->createTestModel();
        $m->table->setOrder('name', 'desc');
        $m->table->setLimit(5);
        $m->setOrder('birthday');

        self::assertSame(
            $this->getConnection()->dsql()
                ->table(
                    $this->getConnection()->dsql()
                        ->table('user')
                        ->field('_id', 'uid')
                        ->field('name')
                        ->field('_birthday', 'y')
                        ->where('_id', '!=', 3)
                        ->order('name', true)
                        ->limit(5),
                    '_tm'
                )
                ->field('name')
                ->field('y', 'birthday')
                ->order('y')
                ->render()[0],
            $m->action('select')->render()[0]
        );

        self::assertSame([
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
        ], $this->hookLog);
    }

    public function testSelectExport(): void
    {
        $m = $this->createTestModel();

        self::assertSameExportUnordered([
            ['name' => 'John', 'birthday' => new \DateTime('1980-2-1 UTC')],
            ['name' => 'Sue', 'birthday' => new \DateTime('2005-4-3 UTC')],
        ], $m->export());

        self::assertSame([
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
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

        self::assertSame([
            ['main', '>>>'],
            ['main', Model::HOOK_VALIDATE, ['save']],
            ['main', Model::HOOK_BEFORE_SAVE, [false]],
            ['main', Model::HOOK_BEFORE_INSERT, [['name' => 'Karl', 'birthday' => \DateTime::class]]],
            ['inner', '>>>'],
            ['inner', Model::HOOK_VALIDATE, ['save']],
            ['inner', Model::HOOK_BEFORE_SAVE, [false]],
            ['inner', Model::HOOK_BEFORE_INSERT, [['uid' => null, 'name' => 'Karl', 'y' => \DateTime::class]]],
            ['inner', Persistence\Sql::HOOK_BEFORE_INSERT_QUERY, [Query::class]],
            ['inner', Persistence\Sql::HOOK_AFTER_INSERT_QUERY, [Query::class]],
            ['inner', Model::HOOK_AFTER_INSERT, []],
            ['inner', Model::HOOK_AFTER_SAVE, [false]],
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['inner', '<<<'],
            ['main', Model::HOOK_AFTER_INSERT, []],
            ['main', Model::HOOK_BEFORE_UNLOAD, []],
            ['main', Model::HOOK_AFTER_UNLOAD, []],
            ['main', Model::HOOK_BEFORE_LOAD, [\DateTime::class]],
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Model::HOOK_AFTER_LOAD, []],
            ['main', Model::HOOK_AFTER_SAVE, [false]],
            ['main', '<<<'],
        ], $this->hookLog);

        self::assertSame(4, $m->table->loadBy('name', 'Karl')->getId());
        self::assertSameExportUnordered([[new \DateTime('2000-6-1 UTC')]], [[$entity->getId()]]);

        self::assertSameExportUnordered([
            ['name' => 'John', 'birthday' => new \DateTime('1980-2-1 UTC')],
            ['name' => 'Sue', 'birthday' => new \DateTime('2005-4-3 UTC')],
            ['name' => 'Karl', 'birthday' => new \DateTime('2000-6-1 UTC')],
        ], $m->export());
    }

    public function testUpdate(): void
    {
        $m = $this->createTestModel();

        $m->load(new \DateTime('2005-4-3'))
            ->set('name', 'Sue')->save() // no change
            ->set('name', 'Susan')->save();

        self::assertSame([
            ['main', Model::HOOK_BEFORE_LOAD, [\DateTime::class]],
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Model::HOOK_AFTER_LOAD, []],

            ['main', '>>>'],
            ['main', Model::HOOK_VALIDATE, ['save']],
            ['main', Model::HOOK_BEFORE_SAVE, [true]],
            ['main', '<<<'],

            ['main', '>>>'],
            ['main', Model::HOOK_VALIDATE, ['save']],
            ['main', Model::HOOK_BEFORE_SAVE, [true]],
            ['main', Model::HOOK_BEFORE_UPDATE, [['name' => 'Susan']]],
            ['inner', Model::HOOK_BEFORE_LOAD, [null]],
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['inner', Model::HOOK_AFTER_LOAD, []],
            ['inner', '>>>'],
            ['inner', Model::HOOK_VALIDATE, ['save']],
            ['inner', Model::HOOK_BEFORE_SAVE, [true]],
            ['inner', Model::HOOK_BEFORE_UPDATE, [['name' => 'Susan']]],
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['inner', Persistence\Sql::HOOK_BEFORE_UPDATE_QUERY, [Query::class]],
            ['inner', Persistence\Sql::HOOK_AFTER_UPDATE_QUERY, [Query::class]],
            ['inner', Model::HOOK_AFTER_UPDATE, [['name' => 'Susan']]],
            ['inner', Model::HOOK_AFTER_SAVE, [true]],
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['inner', '<<<'],
            ['inner', Model::HOOK_BEFORE_UNLOAD, []],
            ['inner', Model::HOOK_AFTER_UNLOAD, []],
            ['main', Model::HOOK_AFTER_UPDATE, [['name' => 'Susan']]],

            ['main', Model::HOOK_BEFORE_UNLOAD, []],
            ['main', Model::HOOK_AFTER_UNLOAD, []],
            ['main', Model::HOOK_BEFORE_LOAD, [\DateTime::class]],
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Model::HOOK_AFTER_LOAD, []],

            ['main', Model::HOOK_AFTER_SAVE, [true]],
            ['main', '<<<'],
        ], $this->hookLog);

        self::assertSameExportUnordered([
            ['name' => 'John', 'birthday' => new \DateTime('1980-2-1 UTC')],
            ['name' => 'Susan', 'birthday' => new \DateTime('2005-4-3 UTC')],
        ], $m->export());
    }

    public function testDelete(): void
    {
        $m = $this->createTestModel();

        $m->delete(new \DateTime('2005-4-3'));

        self::assertSame([
            ['main', Model::HOOK_BEFORE_LOAD, [\DateTime::class]],
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['main', Model::HOOK_AFTER_LOAD, []],

            ['main', '>>>'],
            ['main', Model::HOOK_BEFORE_DELETE, []],
            ['inner', Model::HOOK_BEFORE_LOAD, [null]],
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['inner', Model::HOOK_AFTER_LOAD, []],
            ['inner', '>>>'],
            ['inner', Model::HOOK_BEFORE_DELETE, []],
            ['inner', Persistence\Sql::HOOK_INIT_SELECT_QUERY, [Query::class, 'select']],
            ['inner', Persistence\Sql::HOOK_BEFORE_DELETE_QUERY, [Query::class]],
            ['inner', Persistence\Sql::HOOK_AFTER_DELETE_QUERY, [Query::class]],
            ['inner', Model::HOOK_AFTER_DELETE, []],
            ['inner', '<<<'],
            ['inner', Model::HOOK_BEFORE_UNLOAD, []],
            ['inner', Model::HOOK_AFTER_UNLOAD, []],
            ['main', Model::HOOK_AFTER_DELETE, []],
            ['main', '<<<'],
            ['main', Model::HOOK_BEFORE_UNLOAD, []],
            ['main', Model::HOOK_AFTER_UNLOAD, []],
        ], $this->hookLog);

        self::assertSameExportUnordered([
            ['name' => 'John', 'birthday' => new \DateTime('1980-2-1 UTC')],
        ], $m->export());
    }
}

<?php

declare(strict_types=1);

namespace atk4\schema\tests;

use atk4\schema\PhpunitTestCase;
use Doctrine\DBAL\Platforms\SqlitePlatform;

class ModelTest extends PhpunitTestCase
{
    /**
     * @doesNotPerformAssertions
     */
    public function testSetModelCreate()
    {
        $this->dropTable('user');
        $user = new TestUser($this->db);

        $this->getMigrator($user)->create();

        // now we can use user
        $user->save(['name' => 'john', 'is_admin' => true, 'notes' => 'some long notes']);
    }

    public function testImportTable()
    {
        $this->dropTable('user');

        $migrator = $this->getMigrator();

        $migrator->table('user')->id()
            ->field('foo')
            ->field('str', ['type' => 'string'])
            ->field('bool', ['type' => 'boolean'])
            ->field('int', ['type' => 'integer'])
            ->field('mon', ['type' => 'money'])
            ->field('flt', ['type' => 'float'])
            ->field('date', ['type' => 'date'])
            ->field('datetime', ['type' => 'datetime'])
            ->field('time', ['type' => 'time'])
            ->field('txt', ['type' => 'text'])
            ->field('arr', ['type' => 'array'])
            ->field('obj', ['type' => 'object'])
            ->create();

        $this->db->dsql()->table('user')
            ->set([
                'id' => 1,
                'foo' => 'quite short value, max 255 characters',
                'str' => 'quite short value, max 255 characters',
                'bool' => true,
                'int' => 123,
                'mon' => 123.45,
                'flt' => 123.456789,
                'date' => (new \DateTime())->format('Y-m-d'),
                'datetime' => (new \DateTime())->format('Y-m-d H:i:s'),
                'time' => (new \DateTime())->format('H:i:s'),
                'txt' => 'very long text value' . str_repeat('-=#', 1000), // 3000+ chars
                'arr' => 'very long text value' . str_repeat('-=#', 1000), // 3000+ chars
                'obj' => 'very long text value' . str_repeat('-=#', 1000), // 3000+ chars
            ])->insert();

        $migrator2 = $this->getMigrator();
        $migrator2->importTable('user');

        $migrator2->mode('create');

        $q1 = preg_replace('/\([0-9,]*\)/i', '', $migrator->render()); // remove parenthesis otherwise we can't differ money from float etc.
        $q2 = preg_replace('/\([0-9,]*\)/i', '', $migrator2->render());
        $this->assertSame($q1, $q2);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMigrateTable()
    {
        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            // http://www.sqlitetutorial.net/sqlite-alter-table/
            $this->markTestSkipped('SQLite does not support DROP COLUMN in ALTER TABLE');
        }

        $this->dropTable('user');
        $migrator = $this->getMigrator($this->db);
        $migrator->table('user')->id()
            ->field('foo')
            ->field('bar', ['type' => 'integer'])
            ->field('baz', ['type' => 'text'])
            ->create();
        $this->db->dsql()->table('user')
            ->set([
                'id' => 1,
                'foo' => 'foovalue',
                'bar' => 123,
                'baz' => 'long text value',
            ])->insert();

        $m2 = $this->getMigrator($this->db);
        $m2->table('user')->id()
            ->field('xx')
            ->field('bar', ['type' => 'integer'])
            ->field('baz')
            ->run();
    }

    public function testCreateModel()
    {
        $this->dropTable('user');

        \atk4\schema\Migration::of(new TestUser($this->db))->run();

        $user_model = $this->getMigrator($this->db)->createModel($this->db, 'user');

        $this->assertSame(
            [
                'name',
                'password',
                'is_admin',
                'notes',
                'main_role_id', // our_field here not role_id (reference name)
            ],
            array_keys($user_model->getFields())
        );
    }
}

class TestUser extends \atk4\data\Model
{
    public $table = 'user';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('password', ['type' => 'password']);
        $this->addField('is_admin', ['type' => 'boolean']);
        $this->addField('notes', ['type' => 'text']);

        $this->hasOne('role_id', [TestRole::class, 'our_field' => 'main_role_id', 'their_field' => 'id']);
    }
}

class TestRole extends \atk4\data\Model
{
    public $table = 'role';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->hasMany('Users', [TestUser::class, 'our_field' => 'id', 'their_field' => 'main_role_id']);
    }
}

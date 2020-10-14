<?php

declare(strict_types=1);

namespace atk4\schema\tests;

use atk4\core\Exception;
use atk4\schema\Migration;
use atk4\schema\PhpunitTestCase;
use Doctrine\DBAL\Platforms\SqlitePlatform;

class CustomMysqlMigrator extends Migration\Mysql
{
}

class CustomMigrator
{
}

class BasicTest extends PhpunitTestCase
{
    /**
     * Test constructor.
     *
     * @doesNotPerformAssertions
     */
    public function testCreateAndAlter()
    {
        $this->dropTable('user');

        $this->getMigrator()->table('user')->id()
            ->field('foo')
            ->field('bar', ['type' => 'integer'])
            ->field('baz', ['type' => 'text'])
            ->field('bl', ['type' => 'boolean'])
            ->field('tm', ['type' => 'time'])
            ->field('dt', ['type' => 'date'])
            ->field('dttm', ['type' => 'datetime'])
            ->field('dbl', ['type' => 'double'])
            ->field('fl', ['type' => 'float'])
            ->field('mn', ['type' => 'money'])
            ->field('en', ['type' => 'enum'])
            ->create();

        $this->getMigrator()->table('user')
            ->newField('zed', ['type' => 'integer'])
            ->alter();
    }

    /**
     * Tests creating and dropping of tables.
     *
     * @doesNotPerformAssertions
     */
    public function testCreateAndDrop()
    {
        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->markTestSkipped('SQLite does not support DROP');
        }

        $this->dropTable('user');

        $this->getMigrator()->table('user')->id()
            ->field('foo')
            ->field('bar', ['type' => 'integer'])
            ->field('baz', ['type' => 'text'])
            ->field('bl', ['type' => 'boolean'])
            ->field('tm', ['type' => 'time'])
            ->field('dt', ['type' => 'date'])
            ->field('dttm', ['type' => 'datetime'])
            ->field('dbl', ['type' => 'double'])
            ->field('fl', ['type' => 'float'])
            ->field('mn', ['type' => 'money'])
            ->field('en', ['type' => 'enum'])
            ->create();

        $this->getMigrator()->table('user')
            ->dropField('bar', ['type' => 'integer'])
            ->alter();
    }

    /**
     * Tests creating direct migrator.
     */
    public function testDirectMigratorResolving()
    {
        $migrator = $this->getMigrator();

        $migratorClass = get_class($migrator);

        $directMigrator = $migratorClass::of($this->db);

        $this->assertSame($migratorClass, get_class($directMigrator));
    }

    /**
     * Tests registering migrator.
     */
    public function testMigratorRegistering()
    {
        // get original migrator registration
        $origMigratorClass = get_class($this->getMigrator());

        Migration::register(get_class($this->getDatabasePlatform()), CustomMysqlMigrator::class);

        $this->assertSame(CustomMysqlMigrator::class, get_class($this->getMigrator()));

        CustomMysqlMigrator::register(get_class($this->getDatabasePlatform()));

        $this->assertSame(CustomMysqlMigrator::class, get_class($this->getMigrator()));

        // restore original migrator registration
        Migration::register(get_class($this->getDatabasePlatform()), $origMigratorClass);

        $this->expectException(Exception::class);

        Migration::register(get_class($this->getDatabasePlatform()), CustomMigrator::class);
    }
}

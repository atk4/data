<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Schema;

use Atk4\Data\Schema\TestCase;

class BasicTest extends TestCase
{
    /**
     * @doesNotPerformAssertions
     */
    public function testCreate(): void
    {
        $this->dropTableIfExists('user');

        $this->createMigrator()->table('user')->id()
            ->field('foo')
            ->field('bar', ['type' => 'integer'])
            ->field('baz', ['type' => 'text'])
            ->field('bl', ['type' => 'boolean'])
            ->field('tm', ['type' => 'time'])
            ->field('dt', ['type' => 'date'])
            ->field('dttm', ['type' => 'datetime'])
            ->field('fl', ['type' => 'float'])
            ->field('mn', ['type' => 'atk4_money'])
            ->create();
    }

    /**
     * Tests creating and dropping of tables.
     *
     * @doesNotPerformAssertions
     */
    public function testCreateAndDrop(): void
    {
        $this->dropTableIfExists('user');

        $this->createMigrator()->table('user')->id()
            ->field('foo')
            ->field('bar', ['type' => 'integer'])
            ->field('baz', ['type' => 'text'])
            ->field('bl', ['type' => 'boolean'])
            ->field('tm', ['type' => 'time'])
            ->field('dt', ['type' => 'date'])
            ->field('dttm', ['type' => 'datetime'])
            ->field('fl', ['type' => 'float'])
            ->field('mn', ['type' => 'atk4_money'])
            ->create();

        $this->createMigrator()->table('user')->drop();
    }
}

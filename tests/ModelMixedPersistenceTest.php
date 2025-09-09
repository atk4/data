<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\OraclePlatform;

class ModelMixedPersistenceTest extends TestCase
{
    public function testArrayTable(): void
    {
        $dbArray = new Persistence\Array_([
            'user' => [
                10 => ['id' => 10, 'name' => 'John'],
                20 => ['id' => 20, 'name' => 'Peter'],
            ],
        ]);

        $mArray = new Model($dbArray, ['table' => 'user']);
        $mArray->addField('name');

        $m = new Model($this->db, ['table' => $mArray]);
        $m->addField('name');

        $fromClause = $this->getDatabasePlatform() instanceof OraclePlatform
            ? ' from "DUAL"'
            : '';

        $this->assertSameSql(
            'select `id`, `name` from (select :a `id`, :b `name`' . $fromClause . ' union all select :c, :d' . $fromClause . ') `_tm`',
            $m->action('select')->render()[0]
        );

        self::assertSameExportUnordered([
            ['id' => 10, 'name' => 'John'],
            ['id' => 20, 'name' => 'Peter'],
        ], $m->export());

        $john = $m->load(10);
        $john->set('name', 'Johny');
        $john->save();

        self::assertSameExportUnordered([
            ['id' => 10, 'name' => 'Johny'],
            ['id' => 20, 'name' => 'Peter'],
        ], $m->export());
    }

    public function testArrayWithJoinUsingId(): void
    {
        $this->setDb([
            'user' => [
                10 => ['id' => 10, 'name' => 'John'],
                20 => ['id' => 20, 'name' => 'Peter'],
                30 => ['id' => 30, 'name' => 'Maria'],
            ],
        ]);

        $mUser = new Model($this->db, ['table' => 'user']);
        $mUser->addField('name');

        $dbArray = new Persistence\Array_([
            'config' => [
                1 => ['id' => 1, 'user_id' => 10, 'path' => '/home/john', 'float' => 0.0],
                ['id' => 2, 'user_id' => 20, 'path' => '/home/p', 'float' => 2.0],
            ],
        ]);

        $mArray = new Model($dbArray, ['table' => 'config']);
        $mArray->addField('userId', ['type' => 'bigint', 'actual' => 'user_id']);
        $mArray->addField('path');
        $mArray->addField('float', ['type' => 'float']);

        $m = clone $mUser;
        $m->addCteModel('config', $mArray);
        $jConfig = $m->join('config.userId');
        $jConfig->addField('path');
        $jConfig->addField('float', ['type' => 'float']);

        $fromClause = $this->getDatabasePlatform() instanceof OraclePlatform
            ? ' from "DUAL"'
            : '';

        $this->assertSameSql(
            'with `config` as (select :a `id`, :b `userId`, :c `path`, :d `float`' . $fromClause . ' union all select :e, :f, :g, :h' . $fromClause . ')' . "\n"
                . 'select `user`.`id`, `user`.`name`, `_config`.`path`, `_config`.`float` from `user` inner join `config` `_config` on `_config`.`userId` = `user`.`id`',
            $m->action('select')->render()[0]
        );

        self::markTestIncompleteOnMySQL5xPlatformAsWithClauseIsNotSupported();

        self::assertSameExportUnordered([
            ['id' => 10, 'name' => 'John', 'path' => '/home/john', 'float' => 0.0],
            ['id' => 20, 'name' => 'Peter', 'path' => '/home/p', 'float' => 2.0],
        ], $m->export());

        $john = $m->load(10);
        $john->set('path', 'new path');
        $john->save();

        self::assertSameExportUnordered([
            ['id' => 10, 'name' => 'John', 'path' => 'new path', 'float' => 0.0],
            ['id' => 20, 'name' => 'Peter', 'path' => '/home/p', 'float' => 2.0],
        ], $m->export());
    }

    public function testArrayWithJoinUsingName(): void
    {
        $this->setDb([
            'user' => [
                10 => ['id' => 10, 'name' => 'John'],
                20 => ['id' => 20, 'name' => 'Peter'],
                30 => ['id' => 30, 'name' => 'Maria'],
            ],
        ]);

        $mUser = new Model($this->db, ['table' => 'user']);
        $mUser->addField('name');

        $dbArray = new Persistence\Array_([
            'config' => [
                1 => ['id' => 1, 'user_name' => 'John', 'path' => '/home/john'],
                ['id' => 2, 'user_name' => 'Peter', 'path' => '/home/p'],
            ],
        ]);

        $mArray = new Model($dbArray, ['table' => 'config']);
        $mArray->addField('userName', ['actual' => 'user_name']);
        $mArray->addField('path');

        $m = clone $mUser;
        $m->addCteModel('config', $mArray);
        $jConfig = $m->join('config.userName', ['masterField' => 'name', 'reverse' => false]);
        $jConfig->addField('path');

        $fromClause = $this->getDatabasePlatform() instanceof OraclePlatform
            ? ' from "DUAL"'
            : '';

        $this->assertSameSql(
            'with `config` as (select :a `id`, :b `userName`, :c `path`' . $fromClause . ' union all select :d, :e, :f' . $fromClause . ')' . "\n"
                . 'select `user`.`id`, `user`.`name`, `_config`.`path` from `user` inner join `config` `_config` on `_config`.`userName` = `user`.`name`',
            $m->action('select')->render()[0]
        );

        self::markTestIncompleteOnMySQL5xPlatformAsWithClauseIsNotSupported();

        self::assertSameExportUnordered([
            ['id' => 10, 'name' => 'John', 'path' => '/home/john'],
            ['id' => 20, 'name' => 'Peter', 'path' => '/home/p'],
        ], $m->export());

        $john = $m->load(10);
        $john->set('path', 'new path');
        $john->save();

        self::assertSameExportUnordered([
            ['id' => 10, 'name' => 'John', 'path' => 'new path'],
            ['id' => 20, 'name' => 'Peter', 'path' => '/home/p'],
        ], $m->export());
    }
}

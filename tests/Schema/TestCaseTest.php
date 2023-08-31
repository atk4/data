<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Schema;

use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;

class TestCaseTest extends TestCase
{
    public function testLogQuery(): void
    {
        $m = new Model($this->db, ['table' => 't']);
        $m->addField('name');
        $m->addField('int', ['type' => 'integer']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('null');
        $m->addCondition('int', '>', -1);

        ob_start();
        try {
            $this->createMigrator($m)->create();

            $this->debug = true;

            $m->atomic(static function () use ($m) {
                $m->insert(['name' => 'Ewa', 'int' => 1, 'float' => 1]);
            });

            self::assertSame(1, $m->loadAny()->getId());

            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        if (!$this->getDatabasePlatform() instanceof SQLitePlatform && !$this->getDatabasePlatform() instanceof MySQLPlatform) {
            return;
        }

        $this->assertSameSql(<<<'EOF'

            "START TRANSACTION";


            insert into `t` (`name`, `int`, `float`, `null`)
            values
              ('Ewa', 1, '1.0', NULL);


            select
              `id`,
              `name`,
              `int`,
              `float`,
              `null`
            from
              `t`
            where
              `int` > -1
              and `id` = 1
            limit
              0,
              2;


            "COMMIT";


            select
              `id`,
              `name`,
              `int`,
              `float`,
              `null`
            from
              `t`
            where
              `int` > -1
            limit
              0,
              1;
            EOF . "\n\n", $output);
    }

    public function testGetSetDropDb(): void
    {
        self::assertSame([], $this->getDb([]));
        self::assertSame([], $this->getDb());

        $dbData = [
            'user' => [
                ['name' => 'John', 'age' => '25'],
                ['name' => 'Steve', 'age' => '30'],
            ],
        ];
        $dbDataWithId = array_map(static function (array $rows) {
            $rowsWithId = [];
            $id = 1;
            foreach ($rows as $row) {
                $rowsWithId[$id] = array_merge(['id' => $id], $row);
                ++$id;
            }

            return $rowsWithId;
        }, $dbData);

        $this->setDb($dbData);
        $dbDataGet1 = $this->getDb(['user']);
        self::assertSameExportUnordered($dbDataWithId, $dbDataGet1);
        self::assertSameExportUnordered($dbDataWithId, $this->getDb());
        self::assertSameExportUnordered($dbData, $this->getDb(null, true));

        $this->dropCreatedDb();
        $this->setDb($dbData);
        $dbDataGet2 = $this->getDb(['user']);
        self::assertSameExportUnordered($dbDataWithId, $dbDataGet2);
        self::assertSameExportUnordered($dbDataWithId, $this->getDb());
        self::assertSame($dbDataGet1, $dbDataGet2);

        $this->dropCreatedDb();
        $this->setDb($dbDataGet1);
        $dbDataGet3 = $this->getDb(['user']);
        self::assertSameExportUnordered($dbDataWithId, $dbDataGet3);
        self::assertSameExportUnordered($dbDataWithId, $this->getDb());
        self::assertSame($dbDataGet1, $dbDataGet3);
    }
}

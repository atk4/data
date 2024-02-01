<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Schema;

use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

class TestCaseTest extends TestCase
{
    public function testLogQuery(): void
    {
        $m = new Model($this->db, ['table' => 't']);
        $m->addField('name');
        $m->addField('int', ['type' => 'integer']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('bool', ['type' => 'boolean']);
        $m->addField('null');
        $m->addCondition('int', '>', -1);

        ob_start();
        try {
            $this->createMigrator($m)->create();

            $this->debug = true;

            $m->atomic(static function () use ($m) {
                $m->insert(['name' => 'Ewa', 'int' => 1, 'float' => 1, 'bool' => 1]);
            });

            self::assertSame(1, $m->loadAny()->getId());

            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $makeLimitSqlFx = function (int $maxCount) {
            if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                return "\nlimit\n  " . $maxCount . "\noffset\n  0";
            } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
                return "\norder by\n  (\n    select\n      null\n  )\noffset\n  0 rows\nfetch\n  next " . $maxCount . ' rows only';
            } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
                return "\nfetch\n  next " . $maxCount . ' rows only';
            }

            return "\nlimit\n  0,\n  " . $maxCount;
        };

        $this->assertSameSql(
            <<<'EOF'

                "START TRANSACTION";


                "SAVEPOINT";
                EOF . "\n\n"
            . ($this->getDatabasePlatform() instanceof SQLServerPlatform
                ? <<<'EOF'

                    begin try insert into `t` (
                      `name`, `int`, `float`, `bool`, `null`
                    )
                    values
                      ('Ewa', 1, 1.0, 1, NULL); end try begin catch if ERROR_NUMBER() = 544 begin
                    set
                      IDENTITY_INSERT `t` on; begin try insert into `t` (
                        `name`, `int`, `float`, `bool`, `null`
                      )
                    values
                      ('Ewa', 1, 1.0, 1, NULL);
                    set
                      IDENTITY_INSERT `t` off; end try begin catch
                    set
                      IDENTITY_INSERT `t` off; throw; end catch end else begin throw; end end catch;
                    EOF . "\n\n"
                : ($this->getDatabasePlatform() instanceof PostgreSQLPlatform ? "\n\"SAVEPOINT\";\n\n" : '')
                . <<<'EOF'

                    insert into `t` (
                      `name`, `int`, `float`, `bool`, `null`
                    )
                    values
                    EOF
                . "\n  ('Ewa', 1, 1.0, "
                . ($this->getDatabasePlatform() instanceof PostgreSQLPlatform ? 'true' : '1')
                . ", NULL);\n\n"
                . ($this->getDatabasePlatform() instanceof PostgreSQLPlatform ? "\n\"RELEASE SAVEPOINT\";\n\n" : ''))
            . ($this->getDatabasePlatform() instanceof OraclePlatform ? <<<'EOF'

                select
                  "t_SEQ".CURRVAL
                from
                  "DUAL";
                EOF . "\n\n" : '')
            . <<<'EOF'

                select
                  `id`,
                  `name`,
                  `int`,
                  `float`,
                  `bool`,
                  `null`
                from
                  `t`
                where
                  `int` > -1
                  and `id` = 1
                EOF
            . $makeLimitSqlFx(2)
            . ";\n\n"
            . ($this->getDatabasePlatform()->supportsReleaseSavepoints() ? "\n\"RELEASE SAVEPOINT\";\n\n" : '')
            . <<<'EOF'

                "COMMIT";


                select
                  `id`,
                  `name`,
                  `int`,
                  `float`,
                  `bool`,
                  `null`
                from
                  `t`
                where
                  `int` > -1
                EOF
            . $makeLimitSqlFx(1)
            . ";\n\n",
            $this->getDatabasePlatform() instanceof SQLServerPlatform
                ? str_replace('(\'Ewa\', 1, 1.0, 1, NULL)', '(N\'Ewa\', 1, 1.0, 1, NULL)', $output)
                : $output
        );
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

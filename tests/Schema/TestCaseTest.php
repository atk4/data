<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Schema;

use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;

class TestCaseTest extends TestCase
{
    public function testNoCircularConnectionReference(): void
    {
        $isMysql = $this->getDatabasePlatform() instanceof MySQLPlatform;
        $this->db = new Persistence\Sql(new Persistence\Sql\Sqlite\Connection());
        gc_collect_cycles();

        $dbDsn = preg_replace('~^(pdo_mysql|mysqli)(?=:)~', 'mysql', $_ENV['DB_DSN']);
        $dbUser = $_ENV['DB_USER'];
        $dbPassword = $_ENV['DB_PASSWORD'];
        $dbRootPassword = 'atk4_pass_root';

        if (isset($_ENV['CI']) && $isMysql) {
            $rootPdo = new \PDO($dbDsn, 'root', $dbRootPassword);
            $rootPdo->exec('ALTER USER \'' . $dbUser . '\'@\'%\' WITH MAX_USER_CONNECTIONS 6');
        }

        try {
            foreach (range(0, 2_000 /* test with like 1M for max. 5 connections */) as $i) {
                $pdo = null;
                try {
                    $pdo = new \PDO($dbDsn, $dbUser, $dbPassword);
                    $this->assertSame([['v' => 'test']], $pdo->query('SELECT \'test\' as v')->fetchAll(\PDO::FETCH_ASSOC));
                } catch (\Exception $e) {
                    throw new \Exception('Failed after ' . $i . ' iterations', 0, $e);
                }

                if (($i % 100) === 0) {
                    usleep(100_000);
                }
            }
        } finally {
            if (isset($_ENV['CI']) && $isMysql) {
                $rootPdo->exec('ALTER USER \'' . $dbUser . '\'@\'%\' WITH MAX_USER_CONNECTIONS 5'); // @phpstan-ignore-line
            }
        }
    }

    public function testNoCircularConnectionReferencePurePhpMysqlClientImpl(): void
    {
        $isMysql = $this->getDatabasePlatform() instanceof MySQLPlatform;
        $this->db = new Persistence\Sql(new Persistence\Sql\Sqlite\Connection());
        gc_collect_cycles();

        $dbDsn = preg_replace('~^(pdo_mysql|mysqli)(?=:)~', 'mysql', $_ENV['DB_DSN']);
        $dbUser = $_ENV['DB_USER'];
        $dbPassword = $_ENV['DB_PASSWORD'];
        $dbRootPassword = 'atk4_pass_root';

        if (isset($_ENV['CI']) && $isMysql) {
            $rootPdo = new \PDO($dbDsn, 'root', $dbRootPassword);
            $rootPdo->exec('ALTER USER \'' . $dbUser . '\'@\'%\' WITH MAX_USER_CONNECTIONS 1');
        } else {
            $this->markTestSkipped('Pure MySQL client impl can be tested with MySQL database only');
        }

        preg_match('~host=([^;]+).*dbname=([^;]+)~', $dbDsn, $matches);
        $dbHost = $matches[1];
        $dbDatabase = $matches[2];

        try {
            foreach (range(0, 10 /* very slow - https://github.com/amphp/mysql/issues/118 */) as $i) {
                $pdo = null;
                try {
                    $res = null;
                    \Amp\Loop::run(function () use ($dbHost, $dbUser, $dbPassword, $dbDatabase, &$res) {
                        $db = yield \Amp\Mysql\connect(\Amp\Mysql\ConnectionConfig::fromString('host=' . $dbHost . ';user=' . $dbUser . ';pass=' . $dbPassword . ';db=' . $dbDatabase));

                        /** @var \Amp\Mysql\ResultSet $result */
                        $result = yield $db->query('SELECT \'test\' as v');

                        $res = [];
                        while (true) {
                            $hasData = yield $result->advance();
                            if (!$hasData) {
                                break;
                            }

                            $res[] = $result->getCurrent();
                        }

                        $db->close();
                    });
                    $this->assertSame([['v' => 'test']], $res);
                } catch (\Exception $e) {
                    throw new \Exception('Failed after ' . $i . ' iterations', 0, $e);
                }
            }
        } finally {
            $rootPdo->exec('ALTER USER \'' . $dbUser . '\'@\'%\' WITH MAX_USER_CONNECTIONS 5');
        }
    }

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

            $m->atomic(function () use ($m) {
                $m->insert(['name' => 'Ewa', 'int' => 1, 'float' => 1]);
            });

            $this->assertSame(1, $m->loadAny()->getId());

            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        if (!$this->getDatabasePlatform() instanceof SqlitePlatform && !$this->getDatabasePlatform() instanceof MySQLPlatform) {
            return;
        }

        $this->assertSameSql(
            <<<'EOF'

                "START TRANSACTION";


                insert into "t" ("name", "int", "float", "null")
                values
                  ('Ewa', 1, '1.0', NULL);


                "COMMIT";


                select
                  "id",
                  "name",
                  "int",
                  "float",
                  "null"
                from
                  "t"
                where
                  "int" > -1
                limit
                  0,
                  1;
                EOF . "\n\n",
            $output
        );
    }

    public function testGetSetDropDb(): void
    {
        $this->assertSame([], $this->getDb([]));
        $this->assertSame([], $this->getDb());

        $dbData = [
            'user' => [
                ['name' => 'John', 'age' => '25'],
                ['name' => 'Steve', 'age' => '30'],
            ],
        ];
        $dbDataWithId = array_map(function ($rows) {
            $rowsWithId = [];
            $id = 1;
            foreach ($rows as $row) {
                $rowsWithId[$id] = array_merge(['id' => (string) $id], $row);
                ++$id;
            }

            return $rowsWithId;
        }, $dbData);

        $this->setDb($dbData);
        $dbDataGet1 = $this->getDb(['user']);
        $this->assertSameExportUnordered($dbDataWithId, $dbDataGet1);
        $this->assertSameExportUnordered($dbDataWithId, $this->getDb());
        $this->assertSameExportUnordered($dbData, $this->getDb(null, true));

        $this->dropCreatedDb();
        $this->setDb($dbData);
        $dbDataGet2 = $this->getDb(['user']);
        $this->assertSameExportUnordered($dbDataWithId, $dbDataGet2);
        $this->assertSameExportUnordered($dbDataWithId, $this->getDb());
        $this->assertSame($dbDataGet1, $dbDataGet2);

        $this->dropCreatedDb();
        $this->setDb($dbDataGet1);
        $dbDataGet3 = $this->getDb(['user']);
        $this->assertSameExportUnordered($dbDataWithId, $dbDataGet3);
        $this->assertSameExportUnordered($dbDataWithId, $this->getDb());
        $this->assertSame($dbDataGet1, $dbDataGet3);
    }
}

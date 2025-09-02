<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Ssh\ForUpdateLocking;

use Atk4\Data\Exception;

class Tester
{
    /** @var list<ConnectionWithValue> */
    protected array $conns = [];

    /**
     * @param \Closure(): ConnectionWithValue $connectionFactoryFx
     * @param positive-int                    $connCount
     */
    public function __construct(\Closure $connectionFactoryFx, int $connCount)
    {
        $iniConn = $connectionFactoryFx();

        $iniConn->sendQuery(<<<'EOD'
            CREATE TABLE $TTT (
              `name` varchar(50) CHARACTER SET ascii NOT NULL,
              `value` bigint UNSIGNED NOT NULL,
              PRIMARY KEY (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            EOD);
        $iniConn->readResult();

        $iniConn->sendQuery('insert into $TTT values (\'a\', 100), (\'b\', 100)');
        $iniConn->readResult();

        for ($i = 0; $i < $connCount; ++$i) {
            $conn = $connectionFactoryFx();

            if (random_int(0, 3) === 0) {
                $conn->enableAssertInTransactionUsingQuery = true;
            }

            $this->conns[] = $conn;
        }
    }

    /**
     * @template T
     *
     * @param list<T> $values
     *
     * @return T
     */
    public function pick(array $values)
    {
        $i = random_int(0, count($values) - 1);

        return $values[$i];
    }

    public function run(float $maxTime): void
    {
        $startTs = microtime(true);
        $lastDumpTs = 0;
        $queryCount = 0;

        while (true) {
            $ts = microtime(true);
            if ($ts >= $startTs + $maxTime) {
                return;
            } elseif ($ts > $lastDumpTs + 1) {
                $lastDumpTs = $ts;
                echo "\n*** elapsed: " . date('H:i:s', (int) ($ts - $startTs)) . ', total queries: ' . $queryCount . " ***\n";
            }

            $conn = $this->pick($this->conns);

            if ($conn->inQuerySinceTs !== null) {
                if ($ts > $conn->inQuerySinceTs + 100) {
                    throw new Exception('Detected connection without any activity for too long');
                }

                if ($conn->hasMoreData()) {
                    $inTransactionBeforeRead = $conn->inTransaction;
                    $res = $conn->readResult();

                    // fix Test::testIssueTransactionTemporaryTurnedOffAfterDeadlock()
                    if ($res->error !== null && $inTransactionBeforeRead && !$conn->inTransaction && (str_starts_with($res->error, 'ERROR 1213 (') || str_starts_with($res->error, 'ERROR 1020 ('))) {
                        $conn->sendQuery('rollback');
                        $conn->readResult();
                    }
                }

                continue;
            }

            $possibleQueries = [];
            if (!$conn->inTransaction) {
                $possibleQueries[] = 'SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE';
                $possibleQueries[] = 'SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ';
                $possibleQueries[] = 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED';
                $possibleQueries[] = 'SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED';
                $possibleQueries[] = 'start transaction';
            } else {
                if (random_int(0, 3) === 0) {
                    $possibleQueries[] = 'commit';
                    $possibleQueries[] = 'rollback';
                }
            }

            if ($conn->inTransaction || random_int(0, 5) === 0) {
                $possibleQueries[] = 'update $TTT set value = $XXX';
                $possibleQueries[] = 'update $TTT set value = $XXX where name = \'a\'';
                $possibleQueries[] = 'update $TTT set value = $XXX where name = \'b\'';
                $possibleQueries[] = 'update $TTT set value = $XXX where name != \'b\'';
                $possibleQueries[] = 'update $TTT set value = value + $XXX';
                $possibleQueries[] = 'update $TTT set value = value + $XXX where name = \'a\'';
                $possibleQueries[] = 'update $TTT set value = value + $XXX where name = \'b\'';
                $possibleQueries[] = 'update $TTT set value = value + $XXX where name != \'b\'';

                // TODO !
                foreach ([
                    'select * from $TTT',
                    'select * from $TTT where name = \'a\'',
                    'select * from $TTT where name = \'b\'',
                    'select * from $TTT where name != \'b\'',
                    'select * from $TTT where value > 50',
                ] as $q) {
                    if (random_int(0, 3) === 0) {
                        $possibleQueries[] = $q;
                        $possibleQueries[] = $q . ' lock in share mode';
                    }
                    $possibleQueries[] = $q . ' for update';
                }
            }

            $sql = $this->pick($possibleQueries);

            $conn->sendQuery($sql);
            ++$queryCount;
        }
    }
}

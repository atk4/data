<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Ssh\ForUpdateLocking;

use Atk4\Data\Exception;
use Atk4\Data\Ssh\MysqlException;

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
        for ($i = 0; $i < $connCount; ++$i) {
            $this->conns[] = $connectionFactoryFx();
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

    public function run(float $maxTime, int $maxQueries, bool $allowSleep): void
    {
        $startTs = microtime(true);
        $queryCount = 0;

        while (true) {
            $ts = microtime(true);
            if ($ts >= $startTs + $maxTime || $queryCount >= $maxQueries) {
                return;
            }

            if ($allowSleep && random_int(0, 3) === 0) {
                $sleepMs = 10 ** random_int(1, 3);
                echo '    sleeping ' . $sleepMs . ' ms' . "\n";
                usleep($sleepMs * 1_050);
            }

            $conn = $this->pick($this->conns);

            if ($conn->inQuerySinceTs !== null) {
                if ($ts > $conn->inQuerySinceTs + 100) {
                    throw new Exception('Detected connection without any activity for too long');
                }

                if ($conn->hasMoreData()) {
                    $inTransactionBeforeRead = $conn->inTransaction;
                    try {
                        $conn->readResult();
                    } catch (MysqlException $e) {
                        // fix Test::testIssueTransactionTemporaryTurnedOffAfterDeadlock()
                        if ($inTransactionBeforeRead && !$conn->inTransaction && in_array($e->getCode(), [1020, 1213], true)) {
                            $conn->executeAndRetryOnInterruptedQuery(static function () use ($conn) {
                                $conn->sendQuery('rollback');
                                $conn->readResult();
                            });
                        }

                        // throw on any other than expected/known error
                        if (!in_array($e->getCode(), [1020, 1205, 1213, 1317], true)) {
                            throw $e;
                        }
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

            if (random_int(0, 25) === 0) {
                $possibleQueries[] = 'kill query ' . $this->pick($this->conns)->threadId;
            }

            $sql = $this->pick($possibleQueries);

            $conn->sendQuery($sql);
            ++$queryCount;
        }
    }
}

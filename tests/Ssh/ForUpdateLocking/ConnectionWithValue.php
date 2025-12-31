<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Ssh\ForUpdateLocking;

use Atk4\Data\Exception;
use Atk4\Data\Ssh\MysqlConnectionWithState;
use Atk4\Data\Ssh\MysqlResult;

class ConnectionWithValue extends MysqlConnectionWithState
{
    public string $table;

    public ?int $lockedValue = null;

    public function __construct(string $sshHost, string $sshUser, string $dbHost, int $dbPort, string $dbUser, string $dbPassword, string $dbDatabase, string $table)
    {
        parent::__construct($sshHost, $sshUser, $dbHost, $dbPort, $dbUser, $dbPassword, $dbDatabase);

        $this->table = $table;

        // recover from deadlocks as fast as possible
        $this->sendQuery('SET SESSION innodb_lock_wait_timeout = 1');
        $this->readResult();
    }

    #[\Override]
    public function sendQuery(string $sql): void
    {
        if (str_contains($sql, '$TTT')) {
            $sql = str_replace('$TTT', $this->table, $sql);
        }

        if (str_contains($sql, '$XXX')) {
            $sql = str_replace('$XXX', (string) random_int(0, 1000), $sql);
        }

        parent::sendQuery($sql);
    }

    #[\Override]
    public function readResult(): MysqlResult
    {
        $res = parent::readResult();

        if ($res->error !== null) {
            // all errors except deadlock do throw in parent class
            return $res;
        }

        if (!$this->inTransaction) {
            $this->lockedValue = null;

            return $res;
        }

        $sqlLower = trim(preg_replace('~\s+~', ' ', strtolower($this->lastQuery)));

        if (str_starts_with($sqlLower, 'update ')) {
            if (!preg_match('~^update tt_\w+ set value = (?:(\d+)|value \+ (\d+))(?: where name (=|!=) \'(a|b)\')?$~i', $sqlLower, $matches)) {
                throw (new Exception('Failed to parse update query'))
                    ->addMoreInfo('sql', $this->lastQuery);
            }

            $updateOperator = $matches[3] ?? '=';
            $updateKey = $matches[4] ?? 'a';
            if (($updateOperator === '=' && $updateKey === 'a') || ($updateOperator === '!=' && $updateKey === 'b')) { // tracked row was updated
                if ($this->lockedValue !== null) {
                    $this->lockedValue = $matches[1] !== ''
                        ? (int) $matches[1]
                        : ($this->lockedValue + (int) $matches[2]);
                } elseif ($matches[1] !== '') {
                    // the isolation level does not matter as any UPDATE statement implies "SELECT ... FOR UPDATE" on the updated rows
                    $this->lockedValue = (int) $matches[1];
                }
            }
        }

        if (str_starts_with($sqlLower, 'select ')) {
            $aValue = null;
            foreach ($res->rows as $row) {
                if (($row['name'] ?? null) === 'a') {
                    $aValue = (int) $row['value'];
                }
            }
            if ($aValue !== null && str_contains($sqlLower, 'for update')) { // tracked row was selected using "SELECT ... FOR UPDATE"
                if ($this->enableDebugPrint) {
                    print_r(['a_locked' => $this->lockedValue, 'a_actual' => $aValue, 'isolation_level' => $this->isolationLevel]);
                }

                if ($this->lockedValue !== null && $this->lockedValue !== $aValue) {
                    if ($this->enableDebugPrint) {
                        echo '************************************************************' . "\n";
                    }

                    throw new Exception('Tracked locked value does not match actual value');
                }

                $this->lockedValue = $aValue;
            }
        }

        return $res;
    }
}

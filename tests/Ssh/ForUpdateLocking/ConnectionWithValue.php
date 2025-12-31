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

        if (str_starts_with($sqlLower, 'update')) {
            if (!preg_match('~^update tt_\w+ set value = (?:(\d+)|value \+ (\d+))(?: where name (=|!=) \'(a|b)\')?$~i', $sqlLower, $matches)) {
                throw (new Exception('Failed to parse update query'))
                    ->addMoreInfo('sql', $this->lastQuery);
            }

            $op = $matches[3] ?? '=';
            $name = $matches[4] ?? 'a';
            if (($op === '=' && $name === 'a') || ($op === '!=' && $name === 'b')) { // tracked row was updated
                if ($this->lockedValue !== null) {
                    $this->lockedValue = $matches[1] !== ''
                        ? (int) $matches[1]
                        : ($this->lockedValue + (int) $matches[2]);
                } elseif ($matches[1] !== '') {
                    // if the value was not locked, even if the update value is known/fixed, we cannot rely the value will be the same on the next select for all isolation levels
                    // TODO or does any update imply "SELECT ... FOR UPDATE" first?
                    if ($this->isolationLevel === self::ISOLATION_LEVEL_REPEATABLE_READ || $this->isolationLevel === self::ISOLATION_LEVEL_SERIALIZABLE) {
                        // DEFENSIVE for now $this->lockedValue = (int) $matches[1];
                    }
                }
            }
        }

        $aValue = null;
        foreach ($res->rows as $row) {
            if (($row['name'] ?? null) === 'a') {
                $aValue = (int) $row['value'];
            }
        }
        if ($aValue !== null && str_contains($sqlLower, 'for update') /*  && str_contains($sqlLower, 'where name = \'a\'') */) { // tracked row was selected & the select is of "for update" type - even if a row was selected with "for update" before, later selects without "for update" can return different result, at least if the condition is different like "n != 'b'" later
            print_r(['a_locked' => $this->lockedValue, 'a_actual' => $aValue, 'isolation_level' => $this->isolationLevel]);

            if ($this->lockedValue !== null && $this->lockedValue !== $aValue) {
                echo '************************************************************' . "\n";

                throw new Exception('Tracked locked value does not match actual value');
            }

            if (str_contains($sqlLower, 'for update')) {
                $this->lockedValue = $aValue;
            }
        }

        return $res;
    }
}

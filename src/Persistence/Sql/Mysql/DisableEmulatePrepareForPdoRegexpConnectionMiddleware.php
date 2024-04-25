<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Statement;

class DisableEmulatePrepareForPdoRegexpConnectionMiddleware extends AbstractConnectionMiddleware
{
    #[\Override]
    public function prepare(string $sql): Statement
    {
        // workaround MySQL v8.0.22 and higher SQLSTATE[HY000]: General error: 3995 Character set 'binary'
        // cannot be used in conjunction with 'utf8mb4_0900_ai_ci' in call to regexp_like.
        // https://github.com/mysql/mysql-server/blob/72136a6d15/sql/item_regexp_func.cc#L115-L120
        // https://dbfiddle.uk/9SA-omyF
        $pdo = $this->getNativeConnection();
        if ($pdo instanceof \PDO && $pdo->getAttribute(\PDO::ATTR_EMULATE_PREPARES)
            && preg_match('~\sregexp\s|(?<!\w)regexp_[a-z]+\s*\(~i', preg_replace('~' . Expression::QUOTED_TOKEN_REGEX . '~', '', $sql))
        ) {
            $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            try {
                return parent::prepare($sql);
            } finally {
                $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
            }
        }

        return parent::prepare($sql);
    }
}

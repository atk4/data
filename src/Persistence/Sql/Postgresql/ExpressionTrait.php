<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Postgresql;

use Doctrine\DBAL\Statement;

trait ExpressionTrait
{
    #[\Override]
    protected function updateRenderBeforeExecute(array $render): array
    {
        [$sql, $params] = parent::updateRenderBeforeExecute($render);

        $sql = preg_replace_callback(
            '~' . self::QUOTED_TOKEN_REGEX . '\K|:\w+~',
            static function ($matches) use ($params) {
                if ($matches[0] === '') {
                    return '';
                }

                $sql = $matches[0];
                $value = $params[$sql];

                // fix pgsql/pdo_pgsql param type bind
                // TODO open php-src issue
                if (is_bool($value)) {
                    $sql = 'cast(' . $sql . ' as BOOLEAN)';
                } elseif (is_int($value)) {
                    $sql = 'cast(' . $sql . ' as BIGINT)';
                } elseif (is_float($value)) {
                    $sql = 'cast(' . $sql . ' as DOUBLE PRECISION)';
                }

                return $sql;
            },
            $sql
        );

        return [$sql, $params];
    }

    #[\Override]
    protected function _executeStatement(Statement $statement, bool $fromExecuteStatement)
    {
        $sql = \Closure::bind(static fn () => $statement->sql, null, Statement::class)();
        if (preg_match('~^\s*+select(?=\s|$)~i', $sql)) {
            return parent::_executeStatement($statement, $fromExecuteStatement);
        }

        return $this->connection->atomic(function () use ($statement, $fromExecuteStatement) {
            return parent::_executeStatement($statement, $fromExecuteStatement);
        });
    }
}

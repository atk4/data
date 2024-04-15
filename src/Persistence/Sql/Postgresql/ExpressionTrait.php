<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Postgresql;

use Atk4\Data\Persistence;
use Doctrine\DBAL\Statement;

trait ExpressionTrait
{
    #[\Override]
    protected function escapeStringLiteral(string $value): string
    {
        $dummyPersistence = (new \ReflectionClass(Persistence\Sql::class))->newInstanceWithoutConstructor();
        if (\Closure::bind(static fn () => $dummyPersistence->binaryTypeValueIsEncoded($value), null, Persistence\Sql::class)()) {
            $value = \Closure::bind(static fn () => $dummyPersistence->binaryTypeValueDecode($value), null, Persistence\Sql::class)();

            return 'decode(\'' . bin2hex($value) . '\', \'hex\')';
        }

        $parts = [];
        foreach (explode("\0", $value) as $i => $v) {
            if ($i > 0) {
                // will raise SQL error, PostgreSQL does not support \0 character
                $parts[] = 'convert_from(decode(\'00\', \'hex\'), \'UTF8\')';
            }

            if ($v !== '') {
                // workaround https://github.com/php/php-src/issues/13958
                foreach (preg_split('~(\\\+)(?=\'|$)~', $v, -1, \PREG_SPLIT_DELIM_CAPTURE) as $i2 => $v2) {
                    if (($i2 % 2) === 1) {
                        if (strlen($v2) === 1) {
                            $parts[] = 'chr(' . ord('\\') . ')';
                        } else {
                            $parts[] = 'repeat(chr(' . ord('\\') . '), ' . strlen($v2) . ')';
                        }
                    } elseif ($v2 !== '') {
                        $parts[] = '\'' . str_replace('\'', '\'\'', $v2) . '\'';
                    }
                }
            }
        }

        if ($parts === []) {
            $parts = ['\'\''];
        }

        $buildConcatSqlFx = static function (array $parts) use (&$buildConcatSqlFx): string {
            if (count($parts) > 1) {
                $partsLeft = array_slice($parts, 0, intdiv(count($parts), 2));
                $partsRight = array_slice($parts, count($partsLeft));

                return 'CONCAT(' . $buildConcatSqlFx($partsLeft) . ', ' . $buildConcatSqlFx($partsRight) . ')';
            }

            return reset($parts);
        };

        return $buildConcatSqlFx($parts);
    }

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

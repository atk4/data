<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

trait ExpressionTrait
{
    #[\Override]
    protected function escapeStringLiteral(string $value): string
    {
        $parts = [];
        foreach (explode("\0", $value) as $i => $v) {
            if ($i > 0) {
                $parts[] = 'x\'00\'';
            }

            if ($v !== '') {
                $parts[] = '\'' . str_replace(['\'', '\\'], ['\'\'', '\\\\'], $v) . '\'';
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
    protected function hasNativeNamedParamSupport(): bool
    {
        $dbalConnection = $this->connection->getConnection();

        return !$dbalConnection->getNativeConnection() instanceof \mysqli;
    }

    #[\Override]
    protected function updateRenderBeforeExecute(array $render): array
    {
        [$sql, $params] = $render;

        $sql = preg_replace_callback(
            '~' . self::QUOTED_TOKEN_REGEX . '\K|:\w+~',
            static function ($matches) use ($params) {
                if ($matches[0] === '') {
                    return '';
                }

                $sql = $matches[0];
                $value = $params[$sql];

                // emulate bind param support for float type
                // TODO open php-src feature request
                if (is_float($value)) {
                    $sql = '(' . $sql . ' + 0.00)';
                }

                return $sql;
            },
            $sql
        );

        return parent::updateRenderBeforeExecute([$sql, $params]);
    }
}

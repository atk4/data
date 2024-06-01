<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

trait ExpressionTrait
{
    #[\Override]
    protected function escapeStringLiteral(string $value): string
    {
        $parts = [];
        foreach (preg_split('~((?:\x00+[^\x00]{1,100})*\x00+)~', $value, -1, \PREG_SPLIT_DELIM_CAPTURE) as $i => $v) {
            if (($i % 2) === 1) {
                $parts[] = 'x\'' . bin2hex($v) . '\'';
            } elseif ($v !== '') {
                $parts[] = '\'' . str_replace(['\'', '\\'], ['\'\'', '\\\\'], $v) . '\'';
            }
        }

        if ($parts === []) {
            $parts = ['\'\''];
        }

        return $this->makeNaryTree($parts, 10, static function (array $parts) {
            if (count($parts) === 1) {
                return reset($parts);
            }

            return 'concat(' . implode(', ', $parts) . ')';
        });
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

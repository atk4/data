<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

trait ExpressionTrait
{
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
                if (is_int($value)) {
                    $sql = 'cast(' . $sql . ' as INTEGER)';
                } elseif (is_float($value)) {
                    $sql = 'cast(' . $sql . ' as DOUBLE PRECISION)';
                }

                return $sql;
            },
            $sql
        );

        return parent::updateRenderBeforeExecute([$sql, $params]);
    }
}

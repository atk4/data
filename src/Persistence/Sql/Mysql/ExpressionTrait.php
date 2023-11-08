<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

trait ExpressionTrait
{
    protected function escapeStringLiteral(string $value): string
    {
        return str_replace('\\', '\\\\', parent::escapeStringLiteral($value));
    }

    protected function hasNativeNamedParamSupport(): bool
    {
        $dbalConnection = $this->connection->getConnection();

        return !$dbalConnection->getNativeConnection() instanceof \mysqli;
    }

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

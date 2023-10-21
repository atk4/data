<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mssql;

trait ExpressionTrait
{
    public function render(): array
    {
        [$sql, $params] = parent::render();

        // convert all string literals to NVARCHAR, eg. 'text' to N'text'
        $sql = preg_replace_callback('~N?\'(?:\'\'|\\\\\'|[^\'])*+\'~s', static function ($matches) {
            return (substr($matches[0], 0, 1) === 'N' ? '' : 'N') . $matches[0];
        }, $sql);

        return [$sql, $params];
    }

    protected function hasNativeNamedParamSupport(): bool
    {
        return false;
    }
}

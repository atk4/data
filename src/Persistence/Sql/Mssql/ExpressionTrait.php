<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mssql;

trait ExpressionTrait
{
    protected function escapeIdentifier(string $value): string
    {
        return $this->fixOpenEscapeChar(parent::escapeIdentifier($value));
    }

    protected function escapeIdentifierSoft(string $value): string
    {
        return $this->fixOpenEscapeChar(parent::escapeIdentifierSoft($value));
    }

    private function fixOpenEscapeChar(string $v): string
    {
        return preg_replace('~(?:\'(?:\'\'|\\\\\'|[^\'])*\')?+\K\]([^\[\]\'"(){}]*?)\]~s', '[$1]', $v);
    }

    public function renderWithParams(): array
    {
        [$sql, $params] = parent::renderWithParams();

        // convert all SQL strings to NVARCHAR, eg 'text' to N'text'
        $sql = preg_replace_callback('~(^|.)(\'(?:\'\'|\\\\\'|[^\'])*\')~s', function ($matches) {
            return $matches[1] . (!in_array($matches[1], ['N', '\'', '\\'], true) ? 'N' : '') . $matches[2];
        }, $sql);

        // MSSQL does not support named parameters, so convert them to numerical when called from execute
        $trace = debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT | \DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $calledFromExecute = false;
        foreach ($trace as $frame) {
            if (($frame['object'] ?? null) === $this) {
                if (($frame['function'] ?? null) === 'renderWithParams') {
                    continue;
                } elseif (($frame['function'] ?? null) === 'execute') {
                    $calledFromExecute = true;
                }
            }

            break;
        }

        if ($calledFromExecute) {
            $numParams = [];
            $i = 0;
            $j = 0;
            $sql = preg_replace_callback(
                '~(?:\'(?:\'\'|\\\\\'|[^\'])*\')?+\K(?:\?|:\w+)~s',
                function ($matches) use ($params, &$numParams, &$i, &$j) {
                    $numParams[++$i] = $params[$matches[0] === '?' ? ++$j : $matches[0]];

                    return '?';
                },
                $sql
            );
            $params = $numParams;
        }

        return [$sql, $params];
    }
}

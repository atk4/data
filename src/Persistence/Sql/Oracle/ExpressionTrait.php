<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

trait ExpressionTrait
{
    protected function convertLongStringToClobExpr(string $value): Expression
    {
        $exprArgs = [];
        $buildConcatExprFx = function (array $parts) use (&$buildConcatExprFx, &$exprArgs): string {
            if (count($parts) > 1) {
                $valueLeft = array_slice($parts, 0, intdiv(count($parts), 2));
                $valueRight = array_slice($parts, count($valueLeft));

                return 'CONCAT(' . $buildConcatExprFx($valueLeft) . ', ' . $buildConcatExprFx($valueRight) . ')';
            }

            $exprArgs[] = count($parts) > 0 ? reset($parts) : '';

            return 'TO_CLOB([])';
        };

        // Oracle SQL (multibyte) string literal is limited to 1332 bytes
        $parts = [];
        foreach (mb_str_split($value, 10_000) as $shorterValue) {
            $lengthBytes = strlen($shorterValue);
            $startBytes = 0;
            do {
                $part = mb_strcut($shorterValue, $startBytes, 1000);
                $startBytes += strlen($part);
                $parts[] = $part;
            } while ($startBytes < $lengthBytes);
        }

        $expr = $buildConcatExprFx($parts);

        return $this->expr($expr, $exprArgs); // @phpstan-ignore-line
    }

    protected function updateRenderBeforeExecute(array $render): array
    {
        [$sql, $params] = parent::updateRenderBeforeExecute($render);

        $newParamBase = $this->paramBase;
        $newParams = [];
        $sql = preg_replace_callback(
            '~\'(?:\'\'|\\\\\'|[^\'])*+\'\K|:\w+~s',
            function ($matches) use ($params, &$newParams, &$newParamBase) {
                if ($matches[0] === '') {
                    return '';
                }

                $value = $params[$matches[0]];
                if (is_string($value) && strlen($value) > 4000) {
                    $expr = $this->convertLongStringToClobExpr($value);
                    unset($value);
                    [$exprSql, $exprParams] = $expr->render();
                    $sql = preg_replace_callback(
                        '~\'(?:\'\'|\\\\\'|[^\'])*+\'\K|:\w+~s',
                        function ($matches) use ($exprParams, &$newParams, &$newParamBase) {
                            if ($matches[0] === '') {
                                return '';
                            }

                            $name = ':' . $newParamBase;
                            ++$newParamBase;
                            $newParams[$name] = $exprParams[$matches[0]];

                            return $name;
                        },
                        $exprSql
                    );
                } else {
                    $sql = ':' . $newParamBase;
                    ++$newParamBase;

                    $newParams[$sql] = $value;
                }

                return $sql;
            },
            $sql
        );

        return [$sql, $newParams];
    }
}

<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

trait ExpressionTrait
{
    /**
     * Like mb_str_split() function, but split by length in bytes.
     *
     * @return array<string>
     */
    private function splitLongString(string $value, int $lengthBytes): array
    {
        $res = [];
        $value = array_reverse(str_split($value, 2 * $lengthBytes));
        $i = count($value) - 1;
        $buffer = '';
        while (true) {
            if (strlen($buffer) <= $lengthBytes && $i >= 0) {
                $buffer .= array_pop($value);
                --$i;
            }

            if (strlen($buffer) <= $lengthBytes) {
                $res[] = $buffer;
                $buffer = '';

                break;
            }

            $l = $lengthBytes;
            for ($j = 0; $j < 4; ++$j) {
                $ordNextChar = ord(substr($buffer, $l - $j, 1));
                if ($ordNextChar < 0x80 || $ordNextChar >= 0xC0) {
                    $l -= $j;

                    break;
                }
            }
            $res[] = substr($buffer, 0, $l);
            $buffer = substr($buffer, $l);
        }

        return $res;
    }

    protected function convertLongStringToClobExpr(string $value): Expression
    {
        // Oracle (multibyte) string literal is limited to 1332 bytes
        $parts = $this->splitLongString($value, 1000);

        $exprArgs = [];
        $buildConcatExprFx = static function (array $parts) use (&$buildConcatExprFx, &$exprArgs): string {
            if (count($parts) > 1) {
                $partsLeft = array_slice($parts, 0, intdiv(count($parts), 2));
                $partsRight = array_slice($parts, count($partsLeft));

                return 'CONCAT(' . $buildConcatExprFx($partsLeft) . ', ' . $buildConcatExprFx($partsRight) . ')';
            }

            $exprArgs[] = reset($parts);

            return 'TO_CLOB([])';
        };

        $expr = $buildConcatExprFx($parts);

        // @phpstan-ignore-next-line https://github.com/phpstan/phpstan/issues/9022
        return $this->expr($expr, $exprArgs); // @phpstan-ignore-line
    }

    protected function updateRenderBeforeExecute(array $render): array
    {
        [$sql, $params] = parent::updateRenderBeforeExecute($render);

        $newParamBase = $this->paramBase;
        $newParams = [];
        $sql = preg_replace_callback(
            '~\'(?:\'\'|\\\\\'|[^\'])*+\'|:\w+~s',
            function ($matches) use ($params, &$newParams, &$newParamBase) {
                if (str_starts_with($matches[0], '\'')) {
                    $value = str_replace('\'\'', '\'', substr($matches[0], 1, -1));
                    if (strlen($value) <= 4000) {
                        return $matches[0];
                    }
                } else {
                    $value = $params[$matches[0]];
                }

                if (is_string($value) && strlen($value) > 4000) {
                    $expr = $this->convertLongStringToClobExpr($value);
                    unset($value);
                    [$exprSql, $exprParams] = $expr->render();
                    $sql = preg_replace_callback(
                        '~\'(?:\'\'|\\\\\'|[^\'])*+\'\K|:\w+~s',
                        static function ($matches) use ($exprParams, &$newParams, &$newParamBase) {
                            if ($matches[0] === '') {
                                return '';
                            }

                            $name = ':' . $newParamBase;
                            ++$newParamBase; // @phpstan-ignore-line
                            $newParams[$name] = $exprParams[$matches[0]];

                            return $name;
                        },
                        $exprSql
                    );
                } else {
                    $sql = ':' . $newParamBase;
                    ++$newParamBase; // @phpstan-ignore-line

                    $newParams[$sql] = $value;
                }

                return $sql;
            },
            $sql
        );

        return [$sql, $newParams];
    }
}

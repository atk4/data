<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

trait ExpressionTrait
{
    #[\Override]
    protected function escapeStringLiteral(string $value): string
    {
        // Oracle (multibyte) string literal is limited to 1332 bytes
        $parts = $this->splitLongString($value, 1000);
        if (count($parts) > 1) {
            return $this->makeNaryTree($parts, 2, function (array $parts) {
                if (count($parts) === 1) {
                    return 'TO_CLOB(' . $this->escapeStringLiteral(reset($parts)) . ')';
                }

                return 'concat(' . implode(', ', $parts) . ')';
            });
        }

        $parts = [];
        foreach (preg_split('~(\x00+)~', $value, -1, \PREG_SPLIT_DELIM_CAPTURE) as $i => $v) {
            if (($i % 2) === 1) {
                $parts[] = strlen($v) === 1
                    ? 'chr(0)'
                    : 'rpad(chr(0), ' . strlen($v) . ', chr(0))';
            } elseif ($v !== '') {
                // workaround https://github.com/php/php-src/issues/13958
                foreach (preg_split('~(\\\+)(?=\'|$)~', $v, -1, \PREG_SPLIT_DELIM_CAPTURE) as $i2 => $v2) {
                    if (($i2 % 2) === 1) {
                        $parts[] = strlen($v2) === 1
                            ? 'chr(' . ord('\\') . ')'
                            : 'rpad(chr(' . ord('\\') . '), ' . strlen($v2) . ', chr(' . ord('\\') . '))';
                    } elseif ($v2 !== '') {
                        $parts[] = '\'' . str_replace('\'', '\'\'', $v2) . '\'';
                    }
                }
            }
        }

        if ($parts === []) {
            $parts = ['\'\''];
        }

        return $this->makeNaryTree($parts, 2, static function (array $parts) {
            if (count($parts) === 1) {
                return reset($parts);
            }

            return 'concat(' . implode(', ', $parts) . ')';
        });
    }

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

        $sqlArgs = [];
        $sql = $this->makeNaryTree($parts, 2, static function (array $parts) use (&$sqlArgs) {
            if (count($parts) === 1) {
                $sqlArgs[] = reset($parts);

                return 'TO_CLOB([])';
            }

            return 'concat(' . implode(', ', $parts) . ')';
        });

        return $this->expr($sql, $sqlArgs); // @phpstan-ignore return.type
    }

    #[\Override]
    protected function updateRenderBeforeExecute(array $render): array
    {
        [$sql, $params] = parent::updateRenderBeforeExecute($render);

        $newParamBase = $this->paramBase;
        $newParams = [];
        $sql = preg_replace_callback(
            '~(?!\')' . self::QUOTED_TOKEN_REGEX . '\K|' . self::QUOTED_TOKEN_REGEX . '|:\w+~',
            function ($matches) use ($params, &$newParams, &$newParamBase) {
                if ($matches[0] === '') {
                    return '';
                }

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
                        '~' . self::QUOTED_TOKEN_REGEX . '\K|:\w+~',
                        static function ($matches) use ($exprParams, &$newParams, &$newParamBase) {
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

                    // fix oci8 param type bind
                    // TODO create a DBAL PR - https://github.com/doctrine/dbal/blob/3.7.1/src/Driver/OCI8/Statement.php#L135
                    // fix pdo_oci param type bind
                    // https://github.com/php/php-src/issues/12578
                    if (is_bool($value) || is_int($value)) {
                        $sql = 'cast(' . $sql . ' as INTEGER)';
                    } elseif (is_float($value)) {
                        $sql = 'cast(' . $sql . ' as BINARY_DOUBLE)';
                    }
                }

                return $sql;
            },
            $sql
        );

        return [$sql, $newParams];
    }
}

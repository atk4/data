<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mssql;

use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Result as DbalResult;

trait ExpressionTrait
{
    private function fixOpenEscapeChar(string $v): string
    {
        return preg_replace('~(?:\'(?:\'\'|\\\\\'|[^\'])*\')?+\K\]([^\[\]\'"(){}]*?)\]~s', '[$1]', $v);
    }

    protected function escapeIdentifier(string $value): string
    {
        return $this->fixOpenEscapeChar(parent::escapeIdentifier($value));
    }

    protected function escapeIdentifierSoft(string $value): string
    {
        return $this->fixOpenEscapeChar(parent::escapeIdentifierSoft($value));
    }

    public function render(): array
    {
        [$sql, $params] = parent::render();

        // convert all SQL strings to NVARCHAR, eg 'text' to N'text'
        $sql = preg_replace_callback('~(^|.)(\'(?:\'\'|\\\\\'|[^\'])*\')~s', function ($matches) {
            return $matches[1] . (!in_array($matches[1], ['N', '\'', '\\'], true) ? 'N' : '') . $matches[2];
        }, $sql);

        return [$sql, $params];
    }

    protected function hasNativeNamedParamSupport(): bool
    {
        return false;
    }

    /**
     * Fix exception throwing for MSSQL TRY/CATCH SQL (for Query::$template_insert).
     *
     * Remove once https://github.com/microsoft/msphpsql/issues/1387 is fixed and released.
     */
    public function execute(object $connection = null): DbalResult
    {
        $templateStr = preg_replace('~^\s*begin\s+(.+)\s+end\s*$~is', '$1', $this->template ?? 'select...'); // @phpstan-ignore-line
        if (preg_match('~^(.*?)begin\s+try(.+?)end\s+try\s+begin\s+catch(.+)end\s+catch(.*?)$~is', $templateStr, $matches)) {
            $executeFx = function (string $template) use ($connection): DbalResult {
                $thisCloned = clone $this;
                $thisCloned->template = !str_contains(trim(trim($template), ';'), ';')
                    ? $template
                    : 'BEGIN' . "\n" . $template . "\n" . 'END';

                return $thisCloned->execute($connection);
            };

            $templateBefore = trim($matches[1]);
            $templateTry = trim($matches[2]);
            $templateAfter = trim($matches[4]);

            if ($templateBefore === '' && $templateAfter === '' && preg_match('~^\s+if ERROR_NUMBER\(\)\s*=\s*544\s+begin\s*(.+?)\s*end\s+else\s+begin\s+throw;\s*end\s+$~is', $matches[3], $matches2)) {
                $templateCatch = 'set IDENTITY_INSERT [table_noalias] on;'
                    . "\n" . 'insert[option] into [table_noalias] ([set_fields]) values ([set_values]);';
                $templateCatchFinally = 'set IDENTITY_INSERT [table_noalias] off;';
                $executeCatchFx = function (\Exception $e) use ($executeFx, $templateCatch, $templateCatchFinally): DbalResult {
                    $eDriver = $e->getPrevious();
                    if ($eDriver !== null && $eDriver instanceof DriverException && $eDriver->getCode() === 544) {
                        try {
                            return $executeFx($templateCatch);
                        } finally {
                            $executeFx($templateCatchFinally);
                        }
                    }

                    throw $e;
                };
            } else {
                throw new \Error('Unexpected MSSQL TRY/CATCH SQL: ' . $templateStr);
            }

            try {
                $res = $executeFx($templateTry);

                return $res;
            } catch (\Exception $e) {
                return $executeCatchFx($e);
            }
        }

        return parent::execute($connection);
    }
}

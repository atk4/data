<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mssql;

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
    public function execute(object $connection = null): \Doctrine\DBAL\Result
    {
        $templateStr = $this->template ?? 'select...'; // @phpstan-ignore-line
        if (preg_match('~^\s*begin\s+try(.+?)end\s+try\s+begin\s+catch(.+)end\s+catch\s*$~is', $templateStr, $matches)) {
            $templateTry = trim($matches[1]);
            if (preg_match('~^\s+if ERROR_NUMBER\(\)\s*=\s*544\s+begin(.+)end\s+else\s+begin\s+throw;\s*end\s+$~is', $matches[2], $matches)) {
                $templateCatch = trim($matches[1]);
            } else {
                throw new \Error('Unexpected MSSQL TRY/CATCH SQL');
            }

            try {
                $thisCloned = clone $this;
                $thisCloned->template = $templateTry;

                return $thisCloned->execute($connection);
            } catch (\Exception $e) {
                $eDbal = $e->getPrevious();
                if ($eDbal !== null && $eDbal instanceof \Doctrine\DBAL\Exception\DriverException && $eDbal->getCode() === 544) {
                    $thisCloned = clone $this;
                    $thisCloned->template = $templateCatch;

                    return $thisCloned->execute($connection);
                }

                throw $e;
            }
        }

        return parent::execute($connection);
    }
}

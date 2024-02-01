<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mssql;

use Atk4\Data\Persistence\Sql\ExecuteException;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Driver\PDO\Exception as DbalDriverPdoException;
use Doctrine\DBAL\Driver\PDO\Result as DbalDriverPdoResult;
use Doctrine\DBAL\Result as DbalResult;

trait ExpressionTrait
{
    #[\Override]
    public function render(): array
    {
        [$sql, $params] = parent::render();

        // convert all string literals to NVARCHAR, eg. 'text' to N'text'
        $sql = preg_replace_callback(
            '~(?!\')' . self::QUOTED_TOKEN_REGEX . '\K|N?' . self::QUOTED_TOKEN_REGEX . '~',
            static function ($matches) {
                if ($matches[0] === '') {
                    return '';
                }

                return (substr($matches[0], 0, 1) === 'N' ? '' : 'N') . $matches[0];
            },
            $sql
        );

        return [$sql, $params];
    }

    #[\Override]
    protected function hasNativeNamedParamSupport(): bool
    {
        return false;
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
                    $sql = 'cast(' . $sql . ' as DOUBLE PRECISION)';
                }

                return $sql;
            },
            $sql
        );

        return parent::updateRenderBeforeExecute([$sql, $params]);
    }

    #[\Override]
    protected function _execute(?object $connection, bool $fromExecuteStatement)
    {
        // fix exception throwing for MSSQL TRY/CATCH SQL (for Query::$templateInsert)
        // https://github.com/microsoft/msphpsql/issues/1387
        if ($fromExecuteStatement && $connection instanceof DbalConnection) {
            // mimic https://github.com/doctrine/dbal/blob/3.7.1/src/Statement.php#L249
            $result = $this->_execute($connection, false);

            $driverResult = \Closure::bind(static fn (): DbalDriverPdoResult => $result->result, null, DbalResult::class)(); // @phpstan-ignore-line
            $driverPdoResult = \Closure::bind(static fn () => $driverResult->statement, null, DbalDriverPdoResult::class)();
            try {
                while ($driverPdoResult->nextRowset());
            } catch (\PDOException $e) {
                $e = $connection->convertException(DbalDriverPdoException::new($e));

                $firstException = $e;
                while ($firstException->getPrevious() !== null) {
                    $firstException = $firstException->getPrevious();
                }
                $errorInfo = $firstException instanceof \PDOException ? $firstException->errorInfo : null;

                $eNew = (new ExecuteException('Dsql execute error', $errorInfo[1] ?? $e->getCode(), $e));
                if ($errorInfo !== null && $errorInfo !== []) {
                    $eNew->addMoreInfo('error', $errorInfo[2] ?? 'n/a (' . $errorInfo[0] . ')');
                }
                $eNew->addMoreInfo('query', $this->getDebugQuery());

                throw $eNew;
            }

            return $result->rowCount();
        }

        return parent::_execute($connection, $fromExecuteStatement);
    }
}

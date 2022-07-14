<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\API\SQLite\ExceptionConverter as SqliteExceptionConverter;
use Doctrine\DBAL\Driver\API\SQLSrv\ExceptionConverter as SQLServerExceptionConverter;
use Doctrine\DBAL\Driver\Exception as DbalDriverException;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Exception\DriverException as DbalDriverConvertedException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Query as DbalQuery;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SqliteSchemaManager;

class DbalDriverMiddleware extends AbstractDriverMiddleware
{
    protected function replaceDatabasePlatform(AbstractPlatform $platform): AbstractPlatform
    {
        if ($platform instanceof SqlitePlatform) {
            $platform = new class() extends SqlitePlatform {
                use Sqlite\PlatformTrait;
            };
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $platform = new class() extends \Doctrine\DBAL\Platforms\PostgreSQL94Platform { // @phpstan-ignore-line
                use Postgresql\PlatformTrait;
            };
        } elseif ($platform instanceof SQLServerPlatform) {
            $platform = new class() extends \Doctrine\DBAL\Platforms\SQLServer2012Platform { // @phpstan-ignore-line
                use Mssql\PlatformTrait;
            };
        } elseif ($platform instanceof OraclePlatform) {
            $platform = new class() extends OraclePlatform {
                use Oracle\PlatformTrait;
            };
        }

        return $platform;
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->replaceDatabasePlatform(parent::getDatabasePlatform());
    }

    public function createDatabasePlatformForVersion($version): AbstractPlatform
    {
        return $this->replaceDatabasePlatform(parent::createDatabasePlatformForVersion($version));
    }

    /**
     * @phpstan-return AbstractSchemaManager<AbstractPlatform>
     */
    public function getSchemaManager(DbalConnection $connection, AbstractPlatform $platform): AbstractSchemaManager
    {
        if ($platform instanceof SqlitePlatform) {
            return new class($connection, $platform) extends SqliteSchemaManager {
                use Sqlite\SchemaManagerTrait;
            };
        }

        return parent::getSchemaManager($connection, $platform);
    }

    /**
     * @param \Closure(DbalDriverConvertedException, ?DbalQuery): DbalDriverConvertedException $convertFx
     */
    protected function createExceptionConvertorMiddleware(ExceptionConverter $wrappedExceptionConverter, \Closure $convertFx): ExceptionConverter
    {
        return new class($wrappedExceptionConverter, $convertFx) implements ExceptionConverter {
            private ExceptionConverter $wrappedExceptionConverter;
            private \Closure $convertFx;

            /**
             * @param \Closure(DbalDriverConvertedException, ?DbalQuery): DbalDriverConvertedException $convertFx
             */
            public function __construct(ExceptionConverter $wrappedExceptionConverter, \Closure $convertFx)
            {
                $this->wrappedExceptionConverter = $wrappedExceptionConverter;
                $this->convertFx = $convertFx;
            }

            public function convert(DbalDriverException $exception, ?DbalQuery $query): DbalDriverConvertedException
            {
                $convertedException = $this->wrappedExceptionConverter->convert($exception, $query);

                return ($this->convertFx)($convertedException, $query);
            }
        };
    }

    final protected static function getUnconvertedException(DbalDriverConvertedException $convertedException): DbalDriverException
    {
        return $convertedException->getPrevious(); // @phpstan-ignore-line
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        $exceptionConverter = parent::getExceptionConverter();
        if ($exceptionConverter instanceof SqliteExceptionConverter) {
            $exceptionConverter = $this->createExceptionConvertorMiddleware(
                $exceptionConverter,
                function (DbalDriverConvertedException $convertedException, ?DbalQuery $query): DbalDriverConvertedException {
                    // fix FK violation exception conversion
                    // https://github.com/doctrine/dbal/issues/5496
                    $exception = self::getUnconvertedException($convertedException);
                    $exceptionMessageLc = strtolower($exception->getMessage());
                    if (str_contains($exceptionMessageLc, 'integrity constraint violation')) {
                        return new ForeignKeyConstraintViolationException($exception, $query);
                    }

                    return $convertedException;
                }
            );
        } elseif ($exceptionConverter instanceof SQLServerExceptionConverter) {
            $exceptionConverter = $this->createExceptionConvertorMiddleware(
                $exceptionConverter,
                function (DbalDriverConvertedException $convertedException, ?DbalQuery $query): DbalDriverConvertedException {
                    // fix table not found exception conversion
                    // https://github.com/doctrine/dbal/pull/5492
                    $exception = self::getUnconvertedException($convertedException);
                    if ($exception instanceof DatabaseObjectNotFoundException) {
                        $exceptionMessageLc = strtolower($exception->getMessage());
                        if (str_contains($exceptionMessageLc, 'cannot drop the table')) {
                            return new TableNotFoundException($exception, $query);
                        }
                    }

                    return $convertedException;
                }
            );
        }

        return $exceptionConverter;
    }
}

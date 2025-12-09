<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\API\SQLSrv\ExceptionConverter as SQLServerExceptionConverter;
use Doctrine\DBAL\Driver\Exception as DbalDriverException;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Exception\DriverException as DbalDriverConvertedException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Query as DbalQuery;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

class DbalDriverMiddleware extends AbstractDriverMiddleware
{
    protected function replaceDatabasePlatform(AbstractPlatform $platform): AbstractPlatform
    {
        if ($platform instanceof SQLitePlatform) {
            $platform = new class extends SQLitePlatform {
                use Sqlite\PlatformTrait;
            };
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $platform = new class extends PostgreSQL94Platform { // @phpstan-ignore class.extendsDeprecatedClass
                use Postgresql\PlatformTrait;
            };
        } elseif ($platform instanceof SQLServerPlatform) {
            $platform = new class extends SQLServer2012Platform { // @phpstan-ignore class.extendsDeprecatedClass
                use Mssql\PlatformTrait;
            };
        } elseif ($platform instanceof OraclePlatform) {
            $platform = new class extends OraclePlatform {
                use Oracle\PlatformTrait;
            };
        }

        return $platform;
    }

    #[\Override]
    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->replaceDatabasePlatform(parent::getDatabasePlatform());
    }

    #[\Override]
    public function createDatabasePlatformForVersion($version): AbstractPlatform
    {
        return $this->replaceDatabasePlatform(parent::createDatabasePlatformForVersion($version));
    }

    /**
     * @return AbstractSchemaManager<AbstractPlatform>
     *
     * @deprecated remove once DBAL 3.5 support is dropped
     */
    #[\Override]
    public function getSchemaManager(DbalConnection $connection, AbstractPlatform $platform): AbstractSchemaManager
    {
        return (new DbalSchemaManagerFactory())->createSchemaManager($connection);
    }

    /**
     * @param \Closure(DbalDriverConvertedException, ?DbalQuery): DbalDriverConvertedException $convertFx
     */
    protected function createExceptionConvertorMiddleware(ExceptionConverter $wrappedExceptionConverter, \Closure $convertFx): ExceptionConverter
    {
        return new class($wrappedExceptionConverter, $convertFx) implements ExceptionConverter {
            private ExceptionConverter $wrappedExceptionConverter;

            /**
             * @param \Closure(DbalDriverConvertedException, ?DbalQuery): DbalDriverConvertedException $convertFx
             */
            private \Closure $convertFx;

            /**
             * @param \Closure(DbalDriverConvertedException, ?DbalQuery): DbalDriverConvertedException $convertFx
             */
            public function __construct(ExceptionConverter $wrappedExceptionConverter, \Closure $convertFx)
            {
                $this->wrappedExceptionConverter = $wrappedExceptionConverter;
                $this->convertFx = $convertFx;
            }

            #[\Override]
            public function convert(DbalDriverException $exception, ?DbalQuery $query): DbalDriverConvertedException
            {
                $convertedException = $this->wrappedExceptionConverter->convert($exception, $query);

                return ($this->convertFx)($convertedException, $query);
            }
        };
    }

    final protected static function getUnconvertedException(DbalDriverConvertedException $convertedException): DbalDriverException
    {
        return $convertedException->getPrevious(); // @phpstan-ignore return.type
    }

    #[\Override]
    public function getExceptionConverter(): ExceptionConverter
    {
        $exceptionConverter = parent::getExceptionConverter();
        if ($exceptionConverter instanceof SQLServerExceptionConverter) { // @phpstan-ignore instanceof.internalClass
            $exceptionConverter = $this->createExceptionConvertorMiddleware(
                $exceptionConverter,
                static function (DbalDriverConvertedException $convertedException, ?DbalQuery $query): DbalDriverConvertedException {
                    // fix table not found exception conversion
                    // https://github.com/doctrine/dbal/pull/5492
                    if ($convertedException instanceof DatabaseObjectNotFoundException) {
                        $exception = self::getUnconvertedException($convertedException);
                        $exceptionMessageLc = strtolower($exception->getMessage());
                        if (str_contains($exceptionMessageLc, 'cannot drop the table') && !$convertedException instanceof TableNotFoundException) {
                            return new TableNotFoundException($exception, $query); // @phpstan-ignore method.internal
                        }
                    }

                    return $convertedException;
                }
            );
        }

        return $exceptionConverter;
    }
}

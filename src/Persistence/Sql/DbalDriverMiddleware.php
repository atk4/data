<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\API\SQLSrv\ExceptionConverter as SQLServerExceptionConverter;
use Doctrine\DBAL\Driver\Exception as DbalDriverException;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Exception\DriverException as DbalDriverConvertedException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Query as DbalQuery;
use Doctrine\DBAL\ServerVersionProvider;

class DbalDriverMiddleware extends AbstractDriverMiddleware
{
    protected function replaceDatabasePlatform(AbstractPlatform $platform, ?string $version): AbstractPlatform
    {
        if ($platform instanceof SQLitePlatform) {
            $platform = new class extends SQLitePlatform {
                use Sqlite\PlatformTrait;
            };
        } elseif ($platform instanceof MySQLPlatform && !Connection::isDbal3x() && $version !== null && version_compare($version, '5.7.8') < 0) {
            $platform = new class extends MySQLPlatform {
                #[\Override]
                public function getJsonTypeDeclarationSQL(array $column): string
                {
                    return 'TEXT';
                }
            };
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $platform = new class extends PostgreSQLPlatform {
                use Postgresql\PlatformTrait;
            };
        } elseif ($platform instanceof SQLServerPlatform) {
            $platform = new class extends SQLServerPlatform {
                use Mssql\PlatformTrait;
            };
        } elseif ($platform instanceof OraclePlatform) { // @phpstan-ignore method.notFound, method.notFound, method.notFound (https://github.com/phpstan/phpstan/issues/11030)
            $platform = new class extends OraclePlatform {
                use Oracle\PlatformTrait;
            };
        }

        return $platform;
    }

    #[\Override]
    public function getDatabasePlatform(?ServerVersionProvider $versionProvider = null): AbstractPlatform
    {
        if (Connection::isDbal3x()) { // @phpstan-ignore method.notFound, method.notFound, method.notFound (https://github.com/phpstan/phpstan/issues/11030)
            return $this->replaceDatabasePlatform(parent::getDatabasePlatform(), null); // @phpstan-ignore arguments.count
        }

        assert($versionProvider !== null);

        return $this->replaceDatabasePlatform(parent::getDatabasePlatform($versionProvider), $versionProvider->getServerVersion());
    }

    /**
     * @param string $version
     *
     * @deprecated remove once DBAL 3.x support is dropped
     */
    public function createDatabasePlatformForVersion($version): AbstractPlatform
    {
        return $this->replaceDatabasePlatform(parent::createDatabasePlatformForVersion($version), $version); // @phpstan-ignore staticMethod.notFound
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

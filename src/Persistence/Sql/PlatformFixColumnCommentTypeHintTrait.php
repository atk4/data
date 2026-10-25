<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

/**
 * Fix AbstractPlatform::markDoctrineTypeCommented() no longer supported.
 *
 * https://github.com/doctrine/dbal/issues/5194#issuecomment-1018790220
 *
 * @internal
 */
trait PlatformFixColumnCommentTypeHintTrait
{
    /**
     * @deprecated remove once DBAL 3.x support is dropped
     */
    protected function getColumnComment(Column $column): ?string
    {
        $tmpType = new class extends Type { // @phpstan-ignore method.internal
            private Type $type;

            private bool $requireCommentHint;

            public function setData(Type $type, bool $requireCommentHint): void
            {
                $this->type = $type;
                $this->requireCommentHint = $requireCommentHint;
            }

            #[\Override]
            public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
            {
                return $this->type->getSQLDeclaration($column, $platform);
            }

            /**
             * @deprecated remove once DBAL 3.x support is dropped
             */
            public function getName(): string
            {
                return $this->type->getName(); // @phpstan-ignore method.notFound
            }

            /**
             * @deprecated remove once DBAL 3.x support is dropped
             */
            public function requiresSQLCommentHint(AbstractPlatform $platform): bool
            {
                if ($this->requireCommentHint) {
                    return true;
                }

                return $this->type->requiresSQLCommentHint($platform); // @phpstan-ignore method.notFound
            }
        };
        $tmpType->setData(
            $column->getType(),
            in_array($column->getType()->getName(), $this->requireCommentHintTypes, true) // @phpstan-ignore method.notFound
        );

        $columnWithTmpType = clone $column;
        $columnWithTmpType->setType($tmpType);

        return parent::getColumnComment($columnWithTmpType); // @phpstan-ignore staticMethod.notFound
    }
}

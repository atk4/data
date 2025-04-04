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
    #[\Override]
    protected function getColumnComment(Column $column)
    {
        $tmpType = new class extends Type {
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

            #[\Override]
            public function getName(): string
            {
                return $this->type->getName();
            }

            #[\Override]
            public function requiresSQLCommentHint(AbstractPlatform $platform): bool
            {
                if ($this->requireCommentHint) {
                    return true;
                }

                return $this->type->requiresSQLCommentHint($platform);
            }
        };
        $tmpType->setData(
            $column->getType(),
            in_array($column->getType()->getName(), $this->requireCommentHintTypes, true)
        );

        $columnWithTmpType = clone $column;
        $columnWithTmpType->setType($tmpType);

        return parent::getColumnComment($columnWithTmpType);
    }
}

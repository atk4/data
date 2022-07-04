<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mssql;

use Doctrine\DBAL\Exception\DriverException;

trait ExpressionTrait
{
    private function fixOpenEscapeChar(string $v): string
    {
        return preg_replace_callback(
            '~\'(?:\'\'|\\\\\'|[^\'])*+\'\K|\]([^\[\]\'"(){}]*?)\]~s',
            function ($matches) {
                if ($matches[0] === '') {
                    return '';
                }

                return '[' . $matches[1] . ']';
            },
            $v
        );
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
    public function execute(object $connection = null, bool $fromExecuteStatement = null)
    {
        $templateStr = preg_replace('~^\s*begin\s+(.+?)\s+end\s*$~is', '$1', $this->template ?? 'select...'); // @phpstan-ignore-line
        if (preg_match('~^(.*?)begin\s+try(.+?)end\s+try\s+begin\s+catch(.+)end\s+catch(.*?)$~is', $templateStr, $matches)) {
            $executeFx = function (string $template) use ($connection, $fromExecuteStatement) {
                $thisCloned = clone $this;
                $thisCloned->template = !str_contains(trim(trim($template), ';'), ';')
                    ? $template
                    : 'BEGIN' . "\n" . $template . "\n" . 'END';

                return $thisCloned->execute($connection, $fromExecuteStatement);
            };

            $templateBefore = trim($matches[1]);
            $templateTry = trim($matches[2]);
            $templateAfter = trim($matches[4]);

            $expectedInsertTemplate = <<<'EOF'
                begin try
                  insert[option] into [table_noalias] ([set_fields]) values ([set_values]);
                end try begin catch
                  if ERROR_NUMBER() = 544 begin
                    set IDENTITY_INSERT [table_noalias] on;
                    begin try
                      insert[option] into [table_noalias] ([set_fields]) values ([set_values]);
                      set IDENTITY_INSERT [table_noalias] off;
                    end try begin catch
                      set IDENTITY_INSERT [table_noalias] off;
                      throw;
                    end catch
                  end else begin
                    throw;
                  end
                end catch
                EOF;

            if ($templateBefore === '' && $templateAfter === '' && $templateStr === $expectedInsertTemplate) {
                $executeCatchFx = function (\Exception $e) use ($executeFx) {
                    $eDriver = $e->getPrevious();
                    if ($eDriver !== null && $eDriver instanceof DriverException && $eDriver->getCode() === 544) {
                        try {
                            return $executeFx('set IDENTITY_INSERT [table_noalias] on;' . "\n"
                                . 'insert[option] into [table_noalias] ([set_fields]) values ([set_values]);');
                        } finally {
                            $executeFx('set IDENTITY_INSERT [table_noalias] off;');
                        }
                    }

                    throw $e;
                };
            } else {
                throw new \Error('Unexpected MSSQL TRY/CATCH SQL: ' . $templateStr);
            }

            try {
                return $executeFx($templateTry);
            } catch (\Exception $e) {
                return $executeCatchFx($e);
            }
        }

        return parent::execute($connection, $fromExecuteStatement);
    }
}

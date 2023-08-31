<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mssql;

use Doctrine\DBAL\Exception\DriverException;

trait ExpressionTrait
{
    public function render(): array
    {
        [$sql, $params] = parent::render();

        // convert all string literals to NVARCHAR, eg. 'text' to N'text'
        $sql = preg_replace_callback('~N?\'(?:\'\'|\\\\\'|[^\'])*+\'~s', static function ($matches) {
            return (substr($matches[0], 0, 1) === 'N' ? '' : 'N') . $matches[0];
        }, $sql);

        return [$sql, $params];
    }

    protected function hasNativeNamedParamSupport(): bool
    {
        return false;
    }

    /**
     * Fix exception throwing for MSSQL TRY/CATCH SQL (for Query::$templateInsert).
     *
     * Remove once https://github.com/microsoft/msphpsql/issues/1387 is fixed and released.
     */
    protected function _execute(?object $connection, bool $fromExecuteStatement)
    {
        $templateStr = preg_replace('~^\s*begin\s+(.+?)\s+end\s*$~is', '$1', $this->template ?? 'select...'); // @phpstan-ignore-line
        if (preg_match('~^(.*?)begin\s+try(.+?)end\s+try\s+begin\s+catch(.+)end\s+catch(.*?)$~is', $templateStr, $matches)) {
            $executeFx = function (string $template) use ($connection, $fromExecuteStatement) {
                $thisCloned = clone $this;
                $thisCloned->template = !str_contains(trim(trim($template), ';'), ';')
                    ? $template
                    : 'BEGIN' . "\n" . $template . "\n" . 'END';

                return $thisCloned->_execute($connection, $fromExecuteStatement);
            };

            $templateBefore = trim($matches[1]);
            $templateTry = trim($matches[2]);
            $templateAfter = trim($matches[4]);

            $expectedInsertTemplate = <<<'EOF'
                begin try
                  insert[option] into [tableNoalias] ([setFields]) values ([setValues]);
                end try begin catch
                  if ERROR_NUMBER() = 544 begin
                    set IDENTITY_INSERT [tableNoalias] on;
                    begin try
                      insert[option] into [tableNoalias] ([setFields]) values ([setValues]);
                      set IDENTITY_INSERT [tableNoalias] off;
                    end try begin catch
                      set IDENTITY_INSERT [tableNoalias] off;
                      throw;
                    end catch
                  end else begin
                    throw;
                  end
                end catch
                EOF;

            if ($templateBefore === '' && $templateAfter === '' && $templateStr === $expectedInsertTemplate) {
                $executeCatchFx = static function (\Exception $e) use ($executeFx) {
                    $eDriver = $e->getPrevious();
                    if ($eDriver !== null && $eDriver instanceof DriverException && $eDriver->getCode() === 544) {
                        try {
                            return $executeFx('set IDENTITY_INSERT [tableNoalias] on;' . "\n"
                                . 'insert[option] into [tableNoalias] ([setFields]) values ([setValues]);');
                        } finally {
                            $executeFx('set IDENTITY_INSERT [tableNoalias] off;');
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

        return parent::_execute($connection, $fromExecuteStatement);
    }
}

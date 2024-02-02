<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Data\Model;

trait ContainsSeedHackTrait
{
    // TODO horrible getter for our possibly entity, for ContainsOne/ContainsMany, remove asap
    public function getOurModelOrEntityPassedToRefXxx(): Model
    {
        $trace = debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT, 10);
        $expectedCalls = [
            'getOurModelOrEntityPassedToRefXxx',
            'getDefaultPersistence',
            'addToPersistence',
            'createTheirModel',
        ];
        $lastExpectedCall = null;
        foreach ($trace as $frame) {
            if ($frame['object'] === $this) {
                if ($frame['function'] === $lastExpectedCall) {
                    continue;
                }

                $expectedCall = array_shift($expectedCalls);
                if ($frame['function'] === $expectedCall) {
                    $lastExpectedCall = $expectedCall;

                    continue;
                }

                if (in_array($frame['function'], ['ref', 'refModel', 'refLink'], true)) {
                    $res = $frame['args'][0];
                    $this->assertOurModelOrEntity($res);

                    return $res;
                }
            }

            throw new \Error('Unexpected "' . $frame['function'] . '" method call in stacktrace');
        }

        throw new \Error('"createTheirModel" call not found in stacktrace');
    }
}

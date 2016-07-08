<?php

namespace atk4\data\tests;

class TestCase extends \PHPUnit_Framework_TestCase
{
    public function runBare()
    {
        try {
            return parent::runBare();
        } catch (\atk4\core\Exception $e) {
            throw new \atk4\data\tests\AgileExceptionWrapper($e->getMessage(), 0, $e);
        }
    }

    public function callProtected($obj, $name, array $args = [])
    {
        $class = new \ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method->invokeArgs($obj, $args);
    }

    public function getProtected($obj, $name)
    {
        $class = new \ReflectionClass($obj);
        $method = $class->getProperty($name);
        $method->setAccessible(true);

        return $method->getValue($obj);
    }
}

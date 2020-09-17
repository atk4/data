<?php

declare(strict_types=1);

namespace atk4\data;

trait SuperCloneTrait
{
    /** @var static|null Exactly the same as $this */
    private $_thisBackup;
    /** @var static|null Original $this, null if not cloned */
    private $_clonedFrom;

    protected function saveThisBackup(): void
    {
        if ($this->_thisBackup !== null) {
            throw new Exception('$this already backed up');
        }

        $this->_thisBackup = $this;
    }

    public function walkObjectDeeply(object $object, \Closure $filterFx, \Closure $fx, string $path = '', array &$walkedObjects = []): void
    {
        $objectId = spl_object_id($object);
        if (isset($walkedObjects[$objectId])) {
            return;
        }
        $walkedObjects[$objectId] = $object;

        if (!$filterFx($object)) {
            return;
        }

        $class = get_class($object);
        do {
            $self = $this;
            \Closure::bind(function() use ($self, $object, $filterFx, $fx, $class, $path, &$walkedObjects) {
                foreach (array_keys(get_object_vars($object)) as $propName) {
                    $pathWithName = $path . '.' . $class . '->' . $propName;

                    $fx($object, $propName, null, $object->{$propName}, $pathWithName);
                    if (is_object($object->{$propName})) {
                        $self->walkObjectDeeply($object->{$propName}, $filterFx, $fx, $pathWithName, $walkedObjects);
                    } elseif (is_array($object->{$propName})) {
                        $self->walkArrayDeeply($object, $filterFx, $propName, $object->{$propName}, $fx, [], $pathWithName, $walkedObjects);
                    }
                }
            }, $object, $class)();

            $class = get_parent_class($class);
        } while ($class);
    }

    public function walkArrayDeeply(object $object, \Closure $filterFx, string $propName, array &$array, \Closure $fx, array $keys, string $path, array &$walkedObjects): void
    {
        foreach (array_keys($array) as $k) {
            $keysWithK = $keys + [count($keys) => $k];
            $pathWithK = $path . '[' . implode(', ', $keysWithK) . ']';

            $fx($object, $propName, $keysWithK, $array[$k], $pathWithK);
            if (is_object($array[$k])) {
                $this->walkObjectDeeply($array[$k], $filterFx, $fx, $pathWithK, $walkedObjects);
            } elseif (is_array($array[$k])) {
                $this->walkArrayDeeply($object, $filterFx, $propName, $array[$k], $fx, $keysWithK, $pathWithK, $walkedObjects);
            }
        }
    }

    private function createSuperShallowClone(object $object): object
    {
        $objectRefl = new \ReflectionObject($object);
        $objectCloned = $objectRefl->newInstanceWithoutConstructor();

        $class = get_class($object);
        do {
            \Closure::bind(function() use ($object, $objectCloned) {
                foreach (array_keys(get_object_vars($object)) as $propName) {
                    $objectCloned->{$propName} = $object->{$propName};
                }
            }, $object, $class)();

            $class = get_parent_class($class);
        } while ($class);

        return $objectCloned;
    }

    /**
     * Expected to be called only from __clone() in this trait, $this was already cloned by php.
     */
    private function processSuperClone(object $origThis, object $newThis): void
    {
        $filterFx = static function(object $object) {
            if ($object instanceof \PHPUnit\Framework\TestCase
                || $object instanceof \Closure
                || $object instanceof \PDO
                || $object instanceof Persistence
            ) {
                return false;
            }

            // to handle anonymous classes
            $isFromAtk4Namespace = false;
            $class = get_class($object);
            do {
                if (preg_match('~^atk4\\\\|@anonymous(?!\w)~is', $class)) {
                    $isFromAtk4Namespace = true;
                }

//                if (!isset(CloningSingleton::$printedClasses[$class])) {
//                    var_dump($class);
//                }
//                CloningSingleton::$printedClasses[$class] = $class;

                $class = get_parent_class($class);
            } while ($class);

            return $isFromAtk4Namespace;
        };

        // discover original instance structure
        $origData = [];
        $this->walkObjectDeeply($newThis, $filterFx, static function(object $object, string $propName, ?array $keys, &$value, string $path) use (&$origData) {
            $origData[$path] = $value;
        });

        // clone every unique object
        $newObjects = [spl_object_id($origThis) => $newThis, spl_object_id($newThis) => $newThis];
        $self = $this;
        $this->walkObjectDeeply($newThis, $filterFx, static function(object $object, string $propName, ?array $keys, &$value, string $path) use ($self, $filterFx, &$newObjects) {
            if (is_object($value) && $filterFx($value)) {
                $objectId = spl_object_id($value);
                if (!isset($newObjects[$objectId])) {
                    $valueCloned = $self->createSuperShallowClone($value);
                    $newObjects[$objectId] = $valueCloned;
                    $newObjects[spl_object_id($valueCloned)] = $valueCloned;
                }

                $value = $newObjects[$objectId];
            }
        });

        // fix links between cloned objects
        $this->walkObjectDeeply($newThis, $filterFx, static function(object $object, string $propName, ?array $keys, &$value, string $path) use ($filterFx, $origData, $newObjects) {
            if (is_object($value) && $filterFx($value) && isset($origData[$path])) {

//                if (!isset($newObjects[spl_object_id($origData[$path])])) {
//                    var_dump($path);
//                }

                $value = $newObjects[spl_object_id($origData[$path])];

                if ($propName === '_thisBackup') {
                    $value = $object;

                    $clonedFrom = $origData[$path];
                    \Closure::bind(function() use($object, $clonedFrom) {
                        $object->_clonedFrom = $clonedFrom;
                    }, $object, get_class($object))();
                }
            }
        });
        $this->_clonedFrom = $origThis;

        // rebind closures
        $newClosures = [];
        $this->walkObjectDeeply($newThis, $filterFx, static function(object $object, string $propName, ?array $keys, &$value, string $path) use ($newObjects, &$newClosures) {
            if ($value instanceof \Closure) {
                $closure = $value;
                $closureId = spl_object_id($closure);
                if (!isset($newClosures[$closureId])) {
                    $closureRefl = new \ReflectionFunction($closure);
                    if ($closureRefl->getClosureThis() !== null) {
                        $closureThisId = spl_object_id($closureRefl->getClosureThis());
                        if (isset($newObjects[$closureThisId])) {
                            $closure = \Closure::bind($closure, $newObjects[$closureThisId]);
                        }
                    }

                    $newClosures[$closureId] = $closure;
                }

                $value = $newClosures[$closureId];
            }
        });
    }

    public function __clone()
    {
        if ($this->_thisBackup === null) {
            throw new Exception('$this was not backed up');
        }

        $this->processSuperClone($this->_thisBackup, $this);
    }

    /**
     * @return static|null
     */
    public function getClonedFrom()
    {
        return $this->_clonedFrom;
    }

//    public function assertThisBackupMatches(self $expectedThis): void
//    {
//        if ($this !== $expectedThis && $this->_thisBackup !== $expectedThis) {
//            throw new Exception('$this does not match');
//        }
//    }
//
//    public function assertClonedFrom(self $expectedSource, bool $allowMultipleClones = false): void
//    {
//        if ($this->_clonedFrom !== $expectedSource) {
//            if ($this->_clonedFrom !== null && $allowMultipleClones) {
//                $this->_clonedFrom->assertClonedFrom($expectedSource, $allowMultipleClones);
//            } else {
//                throw new Exception('source instance does not match');
//            }
//        }
//    }
}

final class CloningSingleton {
    public static $printedClasses = [];
}

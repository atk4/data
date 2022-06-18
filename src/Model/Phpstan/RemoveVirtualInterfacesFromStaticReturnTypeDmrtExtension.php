<?php

declare(strict_types=1);

namespace Atk4\Data\Model\Phpstan;

use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\Dummy\ChangedTypeMethodReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ResolvedMethodReflection;
use PHPStan\Reflection\Type\CalledOnTypeUnresolvedMethodPrototypeReflection;
use PHPStan\Reflection\Type\UnionTypeMethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\MethodTypeSpecifyingExtension;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticMethodTypeSpecifyingExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Modify return types by reresolving static/$this type with virtual interfaces removed.
 */
class RemoveVirtualInterfacesFromStaticReturnTypeDmrtExtension implements DynamicMethodReturnTypeExtension, DynamicStaticMethodReturnTypeExtension, TypeSpecifierAwareExtension, MethodTypeSpecifyingExtension, StaticMethodTypeSpecifyingExtension
{
    protected string $className;
    protected string $virtualInterfaceName;

    public function __construct(string $className, string $virtualInterfaceName)
    {
        $this->className = (new \ReflectionClass($className))->getName();
        $this->virtualInterfaceName = (new \ReflectionClass($virtualInterfaceName))->getName();
    }

    public function getClass(): string
    {
        return $this->className;
    }

    public function isMethodSupported(MethodReflection $methodReflection, MethodCall $methodCall = null, TypeSpecifierContext $context = null): bool
    {
        return $methodReflection->getName() !== '__construct';
//        return !$methodReflection instanceof \PHPStan\Reflection\Php\PhpMethodReflection/*$methodReflection instanceof ResolvedMethodReflection
//            || $methodReflection instanceof UnionTypeMethodReflection*/; // TODO why not all?
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection, StaticCall $methodCall = null, TypeSpecifierContext $context = null): bool
    {
        return $this->isMethodSupported($methodReflection);
    }

    protected function unresolveMethodReflection(ResolvedMethodReflection $methodReflection): MethodReflection
    {
        $methodReflection = \Closure::bind(fn () => $methodReflection->reflection, null, ResolvedMethodReflection::class)();
        if (!$methodReflection instanceof ChangedTypeMethodReflection) {
            throw new \Exception('Unexpected method reflection class: ' . get_class($methodReflection));
        }

        $methodReflection = \Closure::bind(fn () => $methodReflection->reflection, null, ChangedTypeMethodReflection::class)();

        return $methodReflection;
    }

    protected function resolveMethodReflection(MethodReflection $methodReflection, Type $calledOnType): MethodReflection
    {
        $resolver = (new CalledOnTypeUnresolvedMethodPrototypeReflection(
            $methodReflection,
            $methodReflection->getDeclaringClass(),
            false,
            $calledOnType
        ));

        return $resolver->getTransformedMethod();
    }

    protected function reresolveMethodReflection(MethodReflection $methodReflection, Type $calledOnType): MethodReflection
    {
        if ($methodReflection instanceof UnionTypeMethodReflection) {
            $methodReflection = new UnionTypeMethodReflection(
                $methodReflection->getName(),
                array_map(
                    fn ($v) => $this->reresolveMethodReflection($v, $calledOnType),
                    \Closure::bind(fn () => $methodReflection->methods, null, UnionTypeMethodReflection::class)()
                )
            );
        } else {
            $methodReflection = $this->unresolveMethodReflection($methodReflection);
            $methodReflection = $this->resolveMethodReflection($methodReflection, $calledOnType);
        }

        return $methodReflection;
    }

    /**
     * @param MethodCall|StaticCall $methodCall
     */
    protected function getObjectTypeFromMethodCallClass(CallLike $methodCall, Scope $scope): Type
    {
        if ($methodCall instanceof StaticCall) {
            return $scope->getType(new New_($methodCall->class));
        }

        return $scope->getType($methodCall->var);
    }

    protected function removeVirtualInterfacesFromType(Type $type): Type
    {
        if ($type instanceof IntersectionType) {
            $types = [];
            foreach ($type->getTypes() as $t) {
                $t = $this->removeVirtualInterfacesFromType($t);
                if (!$t instanceof NeverType) {
                    $types[] = $t;
                }
            }

            return count($types) === 0 ? new NeverType() : TypeCombinator::intersect(...$types);
        }

        if ($type instanceof ObjectType && $type->isInstanceOf($this->virtualInterfaceName)->yes()) {
            return new NeverType();
        }

        return $type->traverse(\Closure::fromCallable([$this, 'removeVirtualInterfacesFromType']));
    }

    /**
     * @param MethodCall|StaticCall $methodCall
     */
    public function getTypeFromMethodCall(MethodReflection $methodReflection, CallLike $methodCall, Scope $scope): Type
    {
        // resolve static type and remove all virtual interfaces from it
        $calledOnOrigType = $this->getObjectTypeFromMethodCallClass($methodCall, $scope);
        $calledOnType = $this->removeVirtualInterfacesFromType($calledOnOrigType);
        $methodReflectionReresolved = $this->reresolveMethodReflection($methodReflection, $calledOnType);

        return ParametersAcceptorSelector::selectFromArgs(
            $scope,
            $methodCall->getArgs(),
            $methodReflectionReresolved->getVariants()
        )->getReturnType();
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type
    {
        return $this->getTypeFromMethodCall($methodReflection, $methodCall, $scope);
    }

    /*
     * Implement https://github.com/phpstan/phpstan/issues/7385 until supported officially.
     *
     * Then remove TypeSpecifierAwareExtension, MethodTypeSpecifyingExtension, StaticMethodTypeSpecifyingExtension
     * and remove optional parameters from isMethodSupported/isStaticMethodSupported prototypes.
     */

    protected TypeSpecifier $typeSpecifier;

    public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
    {
        $this->typeSpecifier = $typeSpecifier;
    }

    /**
     * @param MethodCall|StaticCall $methodCall
     */
    public function specifyTypes(MethodReflection $methodReflection, CallLike $methodCall, Scope $scope, TypeSpecifierContext $context): SpecifiedTypes
    {
        $calledOnType = $this->getObjectTypeFromMethodCallClass($methodCall, $scope);

        if ($methodCall instanceof StaticCall) {
            if ($methodReflection->getName() === 'assertInstanceOf' && isset($methodCall->getArgs()[0])) {
                $expr = $methodCall->getArgs()[0]->value;
                $type = TypeCombinator::intersect($scope->getType($expr), $calledOnType);

                return $this->typeSpecifier->create($expr, $type, TypeSpecifierContext::createNull());
            }
        } else {
            if ($methodReflection->getName() === 'assertIsModel') {
                $expr = $methodCall->var;
                $type = $this->removeVirtualInterfacesFromType($scope->getType($expr));

                return $this->typeSpecifier->create($expr, $type, TypeSpecifierContext::createNull());
            } elseif ($methodReflection->getName() === 'assertIsEntity') {
                $expr = $methodCall->var;
                $type = TypeCombinator::intersect(
                    $scope->getType($expr),
                    new ObjectType(IsEntity::class)
                );

                return $this->typeSpecifier->create($expr, $type, TypeSpecifierContext::createNull());
            } elseif ($methodReflection->getName() === 'assertIsLoaded') {
                $expr = $methodCall->var;
                $type = TypeCombinator::intersect(
                    $scope->getType($expr),
                    new ObjectType(IsLoaded::class)
                );

                return $this->typeSpecifier->create($expr, $type, TypeSpecifierContext::createNull());
            } elseif ($methodReflection->getName() === 'unload') {
                $expr = $methodCall->var;
                $type = TypeCombinator::remove(
                    TypeCombinator::intersect(
                        $scope->getType($expr),
                        new ObjectType(IsEntity::class)
                    ),
                    new ObjectType(IsLoaded::class)
                );

                return $this->typeSpecifier->create($expr, $type, TypeSpecifierContext::createNull());
            }
        }

        return new SpecifiedTypes();
    }
}

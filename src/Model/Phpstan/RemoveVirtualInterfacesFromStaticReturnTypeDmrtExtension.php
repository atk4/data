<?php

declare(strict_types=1);

namespace Atk4\Data\Model\Phpstan;

use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\Dummy\ChangedTypeMethodReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ResolvedMethodReflection;
use PHPStan\Reflection\Type\CalledOnTypeUnresolvedMethodPrototypeReflection;
use PHPStan\Reflection\Type\UnionTypeMethodReflection;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericClassStringType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Modify return types by reresolving static/$this type with virtual interfaces removed.
 */
class RemoveVirtualInterfacesFromStaticReturnTypeDmrtExtension implements DynamicMethodReturnTypeExtension, DynamicStaticMethodReturnTypeExtension
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

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() !== '__construct';
//        return !$methodReflection instanceof \PHPStan\Reflection\Php\PhpMethodReflection/*$methodReflection instanceof ResolvedMethodReflection
//            || $methodReflection instanceof UnionTypeMethodReflection*/; // TODO why not all?
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
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
    protected function getMethodCallScopeType(CallLike $methodCall, Scope $scope): Type
    {
        if ($methodCall instanceof StaticCall) {
            // while loop needed to fix https://github.com/phpstan/phpstan/issues/7391
            $clExpr = $methodCall->class;
            while ($clExpr instanceof ClassConstFetch && $clExpr->name instanceof Identifier && strtolower($clExpr->name->name) === 'class') {
                $clExpr = $clExpr->class;
            }
            $classNameType = $scope->getType(new ClassConstFetch($clExpr, 'class'));
            if ($classNameType instanceof ConstantStringType) {
                return new ObjectType($classNameType->getValue());
            } elseif ($classNameType instanceof GenericClassStringType) {
                return $classNameType->getGenericType();
            }

            throw new \Exception('Unexpected scope class name class: ' . get_class($classNameType));
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
    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        CallLike $methodCall,
        Scope $scope
    ): Type {
        // resolve static type and remove all virtual interfaces from it
        $calledOnOrigType = $this->getMethodCallScopeType($methodCall, $scope);
        $calledOnType = $this->removeVirtualInterfacesFromType($calledOnOrigType);
        $methodReflectionReresolved = $this->reresolveMethodReflection($methodReflection, $calledOnType);

        return ParametersAcceptorSelector::selectFromArgs(
            $scope,
            $methodCall->getArgs(),
            $methodReflectionReresolved->getVariants()
        )->getReturnType();
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope
    ): Type {
        return $this->getTypeFromMethodCall($methodReflection, $methodCall, $scope);
    }
}

<?php

declare(strict_types=1);

namespace Rector\Core\NodeManipulator;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeTraverser;
use Rector\Core\NodeAnalyzer\PropertyFetchAnalyzer;
use Rector\Core\PhpParser\Node\BetterNodeFinder;
use Rector\Core\ValueObject\MethodName;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\PhpDocParser\NodeTraverser\SimpleCallableNodeTraverser;

final class PropertyFetchAssignManipulator
{
    public function __construct(
        private readonly SimpleCallableNodeTraverser $simpleCallableNodeTraverser,
        private readonly NodeNameResolver $nodeNameResolver,
        private readonly BetterNodeFinder $betterNodeFinder,
        private readonly PropertyFetchAnalyzer $propertyFetchAnalyzer
    ) {
    }

    public function isAssignedMultipleTimesInConstructor(Property $property): bool
    {
        $classLike = $this->betterNodeFinder->findParentType($property, ClassLike::class);
        if (! $classLike instanceof ClassLike) {
            return false;
        }

        $classMethod = $classLike->getMethod(MethodName::CONSTRUCT);
        if (! $classMethod instanceof ClassMethod) {
            return false;
        }

        $count = 0;
        $propertyName = $this->nodeNameResolver->getName($property);

        $this->simpleCallableNodeTraverser->traverseNodesWithCallable(
            (array) $classMethod->getStmts(),
            function (Node $node) use ($propertyName, $classLike, &$count): ?int {
                if (! $node instanceof Assign) {
                    return null;
                }

                if (! $this->propertyFetchAnalyzer->isLocalPropertyFetchName($node->var, $propertyName)) {
                    return null;
                }

                $parentClassLike = $this->betterNodeFinder->findParentType($node, ClassLike::class);
                if ($parentClassLike !== $classLike) {
                    return null;
                }

                ++$count;

                if ($count === 2) {
                    return NodeTraverser::STOP_TRAVERSAL;
                }

                return null;
            }
        );

        return $count === 2;
    }
}

<?php

namespace Mkaram\Snap\Core;

use PhpParser\Comment\Doc;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class CodeSkeletonizer
{
    /**
     * Convert PHP source into an empty blueprint skeleton.
     */
    public function skeletonize(string $code): string
    {
        try {
            $parser = (new ParserFactory())->createForHostVersion();
            $ast = $parser->parse($code);

            if ($ast === null) {
                return $code;
            }

            $traverser = new NodeTraverser();
            $traverser->addVisitor(new class extends NodeVisitorAbstract {
                public function enterNode(Node $node)
                {
                    // Target methods that have a body (skip abstract methods and interfaces)
                    if ($node instanceof ClassMethod && $node->stmts !== null) {
                        $node->stmts = $this->createStubStatements($node);
                    }
                    return null;
                }

                protected function createStubStatements(ClassMethod $node): array
                {
                    $returnType = $node->getReturnType();
                    $comment = new Doc("/**\n     * TODO: Implement method logic.\n     */");

                    // Methods with no return type or a void return type
                    if ($returnType === null || $this->getTypeName($returnType) === 'void') {
                        return [
                            new Stmt\Nop(['comments' => [$comment]]),
                        ];
                    }

                    $typeString = $this->getTypeName($returnType);
                    $returnValue = $this->resolveReturnValue($typeString);

                    return [
                        new Stmt\Return_($returnValue, ['comments' => [$comment]]),
                    ];
                }

                protected function getTypeName($typeNode): string
                {
                    if ($typeNode instanceof Node\NullableType) {
                        return '?' . $this->getTypeName($typeNode->type);
                    }
                    if ($typeNode instanceof Node\Identifier || $typeNode instanceof Node\Name) {
                        return (string) $typeNode;
                    }
                    return '';
                }

                protected function resolveReturnValue(string $typeString): Expr
                {
                    if (str_starts_with($typeString, '?')) {
                        return new Expr\ConstFetch(new Node\Name('null'));
                    }

                    return match (strtolower($typeString)) {
                        'bool' => new Expr\ConstFetch(new Node\Name('false')),
                        'int' => new Scalar\Int_(0),
                        'float' => new Scalar\Float_(0.0),
                        'string' => new Scalar\String_(''),
                        'array' => new Expr\Array_(),
                        default => new Expr\ConstFetch(new Node\Name('null')),
                    };
                }
            });

            $ast = $traverser->traverse($ast);
            $prettyPrinter = new Standard();

            return "<?php\n\n" . ltrim($prettyPrinter->prettyPrint($ast));
        } catch (Error $e) {
            // If parsing fails, return the original source unchanged
            return $code;
        }
    }
}
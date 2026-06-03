<?php

declare(strict_types=1);

namespace LaravelDoctor\Fix;

use LaravelDoctor\Scanner\SourceType;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Greater;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

/**
 * Arregla `X->count() > 0` → `X->exists()` preservando el formato del resto del archivo
 * (impresión format-preserving de nikic/php-parser).
 */
final class PreferExistsOverCountFixer implements Fixer
{
    public function ruleId(): string
    {
        return 'prefer-exists-over-count';
    }

    public function fix(string $contents, SourceType $type): ?string
    {
        if ($type !== SourceType::Php) {
            return null;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        try {
            $old = $parser->parse($contents);
        } catch (\PhpParser\Error) {
            return null;
        }
        if ($old === null) {
            return null;
        }
        $tokens = $parser->getTokens();

        $new = (new NodeTraverser(new CloningVisitor()))->traverse($old);

        $visitor = new class extends NodeVisitorAbstract {
            public int $fixes = 0;

            public function leaveNode(Node $node)
            {
                if ($node instanceof Greater
                    && $node->right instanceof Int_ && $node->right->value === 0
                    && $node->left instanceof MethodCall && $node->left->name instanceof Identifier
                    && $node->left->name->toString() === 'count') {
                    $this->fixes++;

                    return new MethodCall($node->left->var, new Identifier('exists'));
                }

                return null;
            }
        };
        $new = (new NodeTraverser($visitor))->traverse($new);

        if ($visitor->fixes === 0) {
            return null;
        }

        return (new Standard())->printFormatPreserving($new, $old, $tokens);
    }
}

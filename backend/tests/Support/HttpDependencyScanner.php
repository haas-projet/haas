<?php

namespace Tests\Support;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final class HttpDependencyScanner
{
    /** @return list<string> */
    public static function violations(string $source): array
    {
        $parser = (new ParserFactory)->createForHostVersion();
        $traverser = new NodeTraverser(new NameResolver);
        $nodes = $traverser->traverse($parser->parse($source) ?? []);
        $finder = new NodeFinder;
        $violations = [];

        foreach ($finder->findInstanceOf($nodes, Name::class) as $name) {
            $dependency = strtolower($name->toString());
            foreach (self::HTTP_NAMESPACES as $prefix) {
                if (str_starts_with($dependency.'\\', $prefix)) {
                    $violations[] = $name->toString().' (ligne '.$name->getStartLine().')';
                    break;
                }
            }
        }

        foreach ($finder->findInstanceOf($nodes, FuncCall::class) as $call) {
            if ($call->name instanceof Name && in_array(strtolower($call->name->toString()), self::HTTP_HELPERS, true)) {
                $violations[] = $call->name->toString().'() (ligne '.$call->getStartLine().')';
            }
        }

        return array_values(array_unique($violations));
    }

    private const array HTTP_NAMESPACES = [
        'app\\http\\',
        'illuminate\\http\\',
        'illuminate\\routing\\',
        'illuminate\\foundation\\http\\',
        'illuminate\\contracts\\routing\\',
        'illuminate\\contracts\\http\\',
        'illuminate\\contracts\\view\\',
        'illuminate\\support\\facades\\auth\\',
        'illuminate\\support\\facades\\http\\',
        'illuminate\\support\\facades\\view\\',
        'illuminate\\support\\facades\\url\\',
        'illuminate\\support\\facades\\request\\',
        'illuminate\\support\\facades\\response\\',
        'illuminate\\support\\facades\\redirect\\',
        'illuminate\\support\\facades\\route\\',
        'illuminate\\support\\facades\\cookie\\',
        'illuminate\\support\\facades\\session\\',
        'symfony\\component\\httpfoundation\\',
        'symfony\\component\\httpkernel\\',
    ];

    private const array HTTP_HELPERS = ['auth', 'request', 'response', 'redirect', 'back', 'cookie', 'session', 'view', 'route', 'url'];
}

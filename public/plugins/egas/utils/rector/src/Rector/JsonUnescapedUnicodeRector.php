<?php

declare (strict_types=1);

namespace Utils\Rector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use Rector\Contract\PhpParser\Node\StmtsAwareInterface;
use Rector\PhpParser\Node\BetterNodeFinder;
use Rector\PhpParser\Node\Value\ValueResolver;
use Rector\Rector\AbstractRector;
use Rector\ValueObject\PhpVersionFeature;
use Rector\VersionBonding\Contract\MinPhpVersionInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use function is_array;
use function is_string;

/**
 * @see \Rector\Tests\TypeDeclaration\Rector\JsonUnescapedUnicodeRector\JsonUnescapedUnicodeRectorTest
 */
final class JsonUnescapedUnicodeRector extends AbstractRector implements MinPhpVersionInterface
{
    private const FLAGS = ['JSON_THROW_ON_ERROR', 'JSON_UNESCAPED_UNICODE'];
    /**
     * @readonly
     * @var ValueResolver
     */
    private $valueResolver;
    /**
     * @readonly
     * @var BetterNodeFinder
     */
    private $betterNodeFinder;
    /**
     * @var bool
     */
    private $hasChanged = false;

    public function __construct(ValueResolver $valueResolver, BetterNodeFinder $betterNodeFinder)
    {
        $this->valueResolver = $valueResolver;
        $this->betterNodeFinder = $betterNodeFinder;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Adds JSON_UNESCAPED_UNICODE to json_encode() and json_decode() to throw JsonException on error', [new CodeSample(<<<'CODE_SAMPLE'
json_encode($content);
json_decode($json);
CODE_SAMPLE
            , <<<'CODE_SAMPLE'
json_encode($content, JSON_UNESCAPED_UNICODE);
json_decode($json, null, 512, JSON_UNESCAPED_UNICODE);
CODE_SAMPLE
        )]);
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [StmtsAwareInterface::class];
    }

    /**
     * @param StmtsAwareInterface $node
     */
    public function refactor(Node $node): ?Node
    {
        $this->hasChanged = false;
        $this->traverseNodesWithCallable($node, function (Node $currentNode): ?FuncCall {
            if (!$currentNode instanceof FuncCall) {
                return null;
            }
            if ($this->shouldSkipFuncCall($currentNode)) {
                return null;
            }
            if ($this->isName($currentNode, 'json_encode')) {
                return $this->processJsonEncode($currentNode);
            }
            if ($this->isName($currentNode, 'json_decode')) {
                return $this->processJsonDecode($currentNode);
            }
            return null;
        });
        if ($this->hasChanged) {
            return $node;
        }
        return null;
    }

    private function shouldSkipFuncCall(FuncCall $funcCall): bool
    {
        if ($funcCall->isFirstClassCallable()) {
            return true;
        }
        if ($funcCall->args === null) {
            return true;
        }
        foreach ($funcCall->args as $arg) {
            if (!$arg instanceof Arg) {
                continue;
            }
            if ($arg->name instanceof Identifier) {
                return true;
            }
        }
        return $this->isFirstValueStringOrArray($funcCall);
    }

    private function isFirstValueStringOrArray(FuncCall $funcCall): bool
    {
        if (!isset($funcCall->getArgs()[0])) {
            return false;
        }
        $firstArg = $funcCall->getArgs()[0];
        $value = $this->valueResolver->getValue($firstArg->value);
        if (is_string($value)) {
            return true;
        }
        return is_array($value);
    }

    private function processJsonEncode(FuncCall $funcCall): ?FuncCall
    {
        $flags = [];
        if (isset($funcCall->args[1])) {
            $flags = $this->getFlags($funcCall->args[1]);
        }
        if (!is_null($newArg = $this->getArgWithFlags($flags))) {
            $this->hasChanged = true;
            $funcCall->args[1] = $newArg;
        }
        return $funcCall;
    }

    /**
     * @return string[]
     */
    private function getFlags(Arg|Node\Expr\BinaryOp\BitwiseOr|ConstFetch $arg, array $result = []): array
    {
        if ($arg instanceof ConstFetch) {
            $constFetch = $arg;
        } else {
            if ($arg instanceof Arg) {
                $array = $arg->value->jsonSerialize();
            } else {
                $array = $arg->jsonSerialize();
            }
            if ($arg->value instanceof ConstFetch) { // single flag
                $constFetch = $arg->value;
            } else { // multiple flag
                $result = $this->getFlags($array['left'], $result);
                $constFetch = $array['right'];
            }
        }
        if (!is_null($constFetch)) {
            $result[] = $constFetch->jsonSerialize()['name']->getFirst();
        }
        return $result;
    }

    private function getArgWithFlags(array $flags): Arg|null
    {
        $oldNbFlags = count($flags);
        $flags = array_values(array_unique([...$flags, ...self::FLAGS]));
        $newNbFlags = count($flags);
        if ($oldNbFlags === $newNbFlags) {
            return null;
        }
        if ($newNbFlags === 1) {
            return new Arg($this->createConstFetch($flags[0]));
        }
        /** @var ConstFetch[] $constFetches */
        $constFetches = [];
        foreach ($flags as $flag) {
            $constFetches[] = $this->createConstFetch($flag);
        }
        $result = null;
        foreach ($constFetches as $i => $constFetch) {
            if ($i === 1) {
                continue;
            }
            if (is_null($result)) {
                $result = new Node\Expr\BinaryOp\BitwiseOr(
                    $constFetch,
                    $constFetches[$i + 1],
                );
            } else {
                $result = new Node\Expr\BinaryOp\BitwiseOr(
                    $result,
                    $constFetch
                );
            }
        }
        return new Arg($result);
    }

    private function createConstFetch(string $name): ConstFetch
    {
        return new ConstFetch(new Name($name));
    }

    private function processJsonDecode(FuncCall $funcCall): ?FuncCall
    {
        $flags = [];
        if (isset($funcCall->args[3])) {
            $flags = $this->getFlags($funcCall->args[3]);
        }
        // set default to inter-args
        if (!isset($funcCall->args[1])) {
            $funcCall->args[1] = new Arg($this->nodeFactory->createNull());
        }
        if (!isset($funcCall->args[2])) {
            $funcCall->args[2] = new Arg(new LNumber(512));
        }
        if (!is_null($newArg = $this->getArgWithFlags($flags))) {
            $this->hasChanged = true;
            $funcCall->args[3] = $newArg;
        }
        return $funcCall;
    }

    public function provideMinPhpVersion(): int
    {
        return PhpVersionFeature::JSON_EXCEPTION;
    }
}

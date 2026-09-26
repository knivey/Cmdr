<?php
namespace knivey\cmdr\test;

use knivey\cmdr\Cmdr;
use knivey\cmdr\exceptions\MiddlewareNotFound;
use knivey\cmdr\Request;
use PHPUnit\Framework\TestCase;

class MiddlewareTest extends TestCase
{
    private function cmdrWithCounter(int &$calls, array $extra = []): Cmdr
    {
        $cmdr = new Cmdr();
        $cmdr->add('hello', function (...$args) use (&$calls) {
            $calls++;
            return 'ran';
        }, syntax: '[name]');
        return $cmdr;
    }

    public function testNoMiddlewareKeepsExistingBehavior(): void
    {
        $calls = 0;
        $cmdr = $this->cmdrWithCounter($calls);
        $this->assertSame('ran', $cmdr->call('hello', 'world', 'ctx'));
        $this->assertSame(1, $calls);
    }

    public function testRequestCarriesExtraArgs(): void
    {
        $cmdr = new Cmdr();
        $seen = null;
        $cmdr->addMiddleware(function (Request $req, callable $next) use (&$seen) {
            $seen = $req->extraArgs;
            return $next($req);
        });
        $cmdr->add('hello', fn(...$a) => 'ran', syntax: '[name]');
        $cmdr->call('hello', 'x', 'ctx1', 'ctx2');
        $this->assertSame(['ctx1', 'ctx2'], $seen);
    }

    public function testGlobalMiddlewareOrderAndBubbling(): void
    {
        $order = [];
        $cmdr = new Cmdr();
        $cmdr->addMiddleware(function (Request $r, callable $n) use (&$order) { $order[] = 'a-before'; $v = $n($r); $order[] = 'a-after'; return $v . '-a'; });
        $cmdr->addMiddleware(function (Request $r, callable $n) use (&$order) { $order[] = 'b-before'; $v = $n($r); $order[] = 'b-after'; return $v . '-b'; });
        $cmdr->add('hello', fn(...$a) => 'ran', syntax: '');
        $this->assertSame('ran-b-a', $cmdr->call('hello', ''));
        $this->assertSame(['a-before', 'b-before', 'b-after', 'a-after'], $order);
    }

    public function testShortCircuitSkipsHandler(): void
    {
        $calls = 0;
        $cmdr = new Cmdr();
        $cmdr->addMiddleware(function (Request $r, callable $next) { return 'denied'; });
        $cmdr->add('hello', function (...$a) use (&$calls) { $calls++; return 'ran'; }, syntax: '');
        $this->assertSame('denied', $cmdr->call('hello', ''));
        $this->assertSame(0, $calls);
    }

    public function testPerCommandMiddlewareRunsAfterGlobal(): void
    {
        $order = [];
        $cmdr = new Cmdr();
        $cmdr->addMiddleware(function (Request $r, callable $n) use (&$order) { $order[] = 'global'; return $n($r); });
        $cmdr->addMiddleware(function (Request $r, callable $n) use (&$order) { $order[] = 'per-cmd'; return $n($r); }, 'hello');
        $cmdr->addMiddleware(function (Request $r, callable $n) use (&$order) { $order[] = 'other'; return $n($r); }, 'nope');
        $cmdr->add('hello', fn(...$a) => 'ran', syntax: '');
        $this->assertSame('ran', $cmdr->call('hello', ''));
        $this->assertSame(['global', 'per-cmd'], $order);
    }

    public function testMiddlewareExceptionPropagates(): void
    {
        $cmdr = new Cmdr();
        $cmdr->addMiddleware(function (Request $r, callable $n) { throw new \RuntimeException('boom'); });
        $cmdr->add('hello', fn(...$a) => 'ran', syntax: '');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $cmdr->call('hello', '');
    }

    public function testExtraArgsMutationVisibleToHandler(): void
    {
        $cmdr = new Cmdr();
        $cmdr->addMiddleware(function (Request $r, callable $next) {
            $r->extraArgs = array_map(fn($v) => "wrapped:$v", $r->extraArgs);
            return $next($r);
        });
        $seen = null;
        $cmdr->add('hello', function (...$a) use (&$seen) { $seen = $a; return 'ran'; }, syntax: '');
        $cmdr->call('hello', '', 'ctx');
        $this->assertContains('wrapped:ctx', $seen);
    }

    public function testPrivCommandsGoThroughPipeline(): void
    {
        $cmdr = new Cmdr();
        $hit = false;
        $cmdr->addMiddleware(function (Request $r, callable $next) use (&$hit) { $hit = true; return $next($r); });
        $cmdr->add('hello', fn(...$a) => 'ran', syntax: '', priv: true);
        $this->assertSame('ran', $cmdr->callPriv('hello', ''));
        $this->assertTrue($hit);
    }

    public function testDirectCmdCallSkipsMiddleware(): void
    {
        $cmdr = new Cmdr();
        $hit = false;
        $cmdr->addMiddleware(function (Request $r, callable $next) use (&$hit) { $hit = true; return $next($r); });
        $cmdr->add('hello', fn(...$a) => 'ran', syntax: '');
        $req = $cmdr->get('hello', '');
        $this->assertSame('ran', $req->cmd->call($req->args));
        $this->assertFalse($hit);
    }

    public function testPerCommandMiddlewareCaseInsensitive(): void
    {
        $hit = false;
        $cmdr = new Cmdr();
        $cmdr->addMiddleware(function (Request $r, callable $next) use (&$hit) { $hit = true; return $next($r); }, 'hello');
        $cmdr->add('hello', fn(...$a) => 'ran', syntax: '');
        $this->assertSame('ran', $cmdr->call('HELLO', ''));
        $this->assertTrue($hit);
    }

    public function testPerCommandMiddlewareCaseInsensitivePriv(): void
    {
        $hit = false;
        $cmdr = new Cmdr();
        $cmdr->addMiddleware(function (Request $r, callable $next) use (&$hit) { $hit = true; return $next($r); }, 'hello');
        $cmdr->add('hello', fn(...$a) => 'ran', syntax: '', priv: true);
        $this->assertSame('ran', $cmdr->callPriv('HELLO', ''));
        $this->assertTrue($hit);
    }

    public function testCmdMiddlewareAttributeRunsAliasedMiddlewareWithArgs(): void
    {
        $cmdr = new Cmdr();
        $gotArgs = null;
        $cmdr->aliasMiddleware('gate', function (Request $r, callable $next, ...$mwArgs) use (&$gotArgs) {
            $gotArgs = $mwArgs;
            if ($mwArgs[0] !== 'ok') return 'denied';
            return $next($r);
        });
        $cmdr->add('hello', #[\knivey\cmdr\attributes\Cmd('hello')]
            #[\knivey\cmdr\attributes\CmdMiddleware('gate', 'ok')]
            fn(...$a) => 'ran');
        $this->assertSame('ran', $cmdr->call('hello', ''));
        $this->assertSame(['ok'], $gotArgs);
    }

    public function testCustomMiddlewareAttributeImplementingInterface(): void
    {
        $gotArgs = null;
        $cmdr = new Cmdr();
        $cmdr->aliasMiddleware('acl', function (Request $r, callable $next, ...$mwArgs) use (&$gotArgs) {
            $gotArgs = $mwArgs;
            return $next($r);
        });
        $cmdr->add('sec', #[\knivey\cmdr\attributes\Cmd('sec')]
            #[Acl('admin')]
            fn(...$a) => 'ran');
        $this->assertSame('ran', $cmdr->call('sec', ''));
        $this->assertSame(['admin'], $gotArgs);
    }

    public function testUnknownMiddlewareAliasThrowsAtCallTime(): void
    {
        $cmdr = new Cmdr();
        $cmdr->add('nope', #[\knivey\cmdr\attributes\Cmd('nope')]
            #[\knivey\cmdr\attributes\CmdMiddleware('ghost')]
            fn(...$a) => 'ran');
        $this->expectException(MiddlewareNotFound::class);
        $this->expectExceptionMessage("middleware 'ghost' is not registered");
        $cmdr->call('nope', '');
    }

    public function testAttrMiddlewareAliasResolutionIsLazy(): void
    {
        $ran = false;
        $cmdr = new Cmdr();
        // Register the command before its alias exists: resolution must be
        // deferred to call time, so calling now surfaces MiddlewareNotFound.
        $cmdr->add('late', #[\knivey\cmdr\attributes\Cmd('late')]
            #[\knivey\cmdr\attributes\CmdMiddleware('gate', 'ok')]
            fn(...$a) => $ran = true);
        try {
            $cmdr->call('late', '');
            $this->fail('Expected MiddlewareNotFound before the alias was registered');
        } catch (MiddlewareNotFound) {
        }
        $this->assertFalse($ran);
        $cmdr->aliasMiddleware('gate', fn(Request $r, callable $next, ...$a) => $next($r));
        $this->assertTrue($cmdr->call('late', ''));
    }

    public function testRepeatableCmdMiddlewareRunsInDeclarationOrder(): void
    {
        $order = [];
        $cmdr = new Cmdr();
        $cmdr->aliasMiddleware('m1', function (Request $r, callable $next) use (&$order) { $order[] = 'm1'; return $next($r); });
        $cmdr->aliasMiddleware('m2', function (Request $r, callable $next) use (&$order) { $order[] = 'm2'; return $next($r); });
        $cmdr->add('rep', #[\knivey\cmdr\attributes\Cmd('rep')]
            #[\knivey\cmdr\attributes\CmdMiddleware('m1')]
            #[\knivey\cmdr\attributes\CmdMiddleware('m2')]
            fn(...$a) => 'ran');
        $this->assertSame('ran', $cmdr->call('rep', ''));
        $this->assertSame(['m1', 'm2'], $order);
    }

    public function testLoadFuncsCollectsAttrMiddleware(): void
    {
        $gotArgs = null;
        $cmdr = new Cmdr();
        $cmdr->aliasMiddleware('gate', function (Request $r, callable $next, ...$mwArgs) use (&$gotArgs) {
            $gotArgs = $mwArgs;
            if ($mwArgs[0] !== 'ok') return 'denied';
            return $next($r);
        });
        $cmdr->loadFuncs();
        $this->assertSame('ran', $cmdr->call('mwTestHello', ''));
        $this->assertSame(['ok'], $gotArgs);
    }

    public function testLoadMethodsCollectsAttrMiddleware(): void
    {
        $gotArgs = null;
        $cmdr = new Cmdr();
        $cmdr->aliasMiddleware('gate', function (Request $r, callable $next, ...$mwArgs) use (&$gotArgs) {
            $gotArgs = $mwArgs;
            return $next($r);
        });
        $cmdr->loadMethods(new mwWeeClass());
        $this->assertSame('ran', $cmdr->call('mwWee', ''));
        $this->assertSame(['ok'], $gotArgs);
    }

    public function testReregistrationKeepsFreshAttrMiddleware(): void
    {
        $cmdr = new Cmdr();
        $cmdr->aliasMiddleware('gate', fn(Request $r, callable $next, ...$a) => $next($r));
        $cmdr->loadFuncs(); // registers mwTestHello carrying one #[CmdMiddleware('gate', 'ok')]
        $this->assertCount(1, $cmdr->cmds['mwTestHello']->attrMiddleware);
        $cmdr->loadFuncs(); // re-registration must replace the Cmd, not stack middleware
        $this->assertCount(1, $cmdr->cmds['mwTestHello']->attrMiddleware);
        $this->assertSame('ran', $cmdr->call('mwTestHello', ''));

        // loadFuncs() then add(): manual re-registration keeps a fresh Cmd too
        unset($cmdr->cmds['mwTestHello']);
        $cmdr->add('mwTestHello', fn(...$a) => 'ran2',
            attrMiddleware: [['name' => 'gate', 'args' => ['ok']]]);
        $this->assertCount(1, $cmdr->cmds['mwTestHello']->attrMiddleware);
        $this->assertSame('ran2', $cmdr->call('mwTestHello', ''));
    }
}

#[\knivey\cmdr\attributes\Cmd('mwTestHello')]
#[\knivey\cmdr\attributes\CmdMiddleware('gate', 'ok')]
function mwTestHello(...$args) {
    return 'ran';
}

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_FUNCTION | \Attribute::TARGET_METHOD)]
class Acl implements \knivey\cmdr\MiddlewareAttribute
{
    public function __construct(public string $role)
    {
    }

    public function name(): string { return 'acl'; }
    public function args(): array { return [$this->role]; }
}

class mwWeeClass {
    #[\knivey\cmdr\attributes\Cmd('mwWee')]
    #[\knivey\cmdr\attributes\CmdMiddleware('gate', 'ok')]
    public function wee(...$args) {
        return 'ran';
    }
}

<?php
namespace knivey\cmdr\test;

use knivey\cmdr\Cmdr;
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
}

<?php

namespace knivey\cmdr\test;

use knivey\cmdr\attributes\Desc;
use knivey\cmdr\attributes\Options;
use knivey\cmdr\attributes\PrivCmd;
use knivey\cmdr\Cmdr;
use knivey\cmdr\Option as Opt;
use knivey\cmdr\Request;
use knivey\cmdr\attributes\Cmd;
use knivey\cmdr\attributes\Syntax;
use knivey\cmdr\attributes\CallWrap;
use knivey\cmdr\attributes\Option;
use PHPUnit\Framework\TestCase;

class CmdrTest extends TestCase
{
    public function testUsingAdd()
    {
        $cmdr = new Cmdr();
        $cnt = 0;
        $lol = function (...$args) use(&$cnt) {
            $this->assertEquals('testing', $args[0]);
            $this->assertCount(2, $args);
            $this->assertInstanceOf(Request::class, $args[1]);
            $cnt++;
            return 'banana';
        };
        $cmdr->add('test', $lol);
        $rval = $cmdr->call('test', 'abc def', 'testing');
        $this->assertEquals('banana', $rval);
        $this->assertEquals(1, $cnt);


        $cmdr = new Cmdr();
        $cnt = 0;
        $lol = function ($req) use(&$cnt) {
            $this->assertInstanceOf(Request::class, $req);
            $this->assertEquals('abc def', $req->args['stuff']);
            $this->assertTrue($req->args->getOpt('--bar'));
            $cnt++;
        };
        $cmdr->add('test', $lol, syntax: '<stuff>...', opts: [new Opt('--bar')]);
        $cmdr->call('test', 'abc def --bar');
        $this->assertEquals(1, $cnt);

        $cnt = 0;
        $cmdr->add('test', $lol, syntax: '<stuff>...', opts: [new Opt('--bar')], priv: true);
        $cmdr->callPriv('test', 'abc def --bar');
        $this->assertEquals(1, $cnt);
    }

    public function testExistsException()
    {
        $cmdr = new Cmdr();
        $lol = function ($req) use(&$cnt) {
        };
        $lolb = function ($req) use(&$cnt) {
        };
        $cmdr->add('test', $lol, syntax: '<stuff>...');
        $this->expectException(\Exception::class);
        $cmdr->add('test', $lolb);
    }

    public function testAddPrivExistsException()
    {
        $cmdr = new Cmdr();
        $lol = function ($req) use(&$cnt) {
        };
        $lolb = function ($req) use(&$cnt) {
        };
        $cmdr->add('test', $lol, syntax: '<stuff>...', priv: true);
        $this->expectException(\Exception::class);
        $cmdr->add('test', $lolb, priv: true);
    }

    public function testAddPrivSameNameAsPub()
    {
        $cmdr = new Cmdr();
        $lol = function ($req) use(&$cnt) {
        };
        $lolb = function ($req) use(&$cnt) {
        };
        $cmdr->add('test', $lol, syntax: '<stuff>...');
        $cmdr->add('test', $lolb, priv: true);
        $this->assertEquals($cmdr->cmds['test']->method, $lol);
        $this->assertEquals($cmdr->privCmds['test']->method, $lolb);
        $cmdr->add('test2', $lol, syntax: '<stuff>...', priv: true);
        $cmdr->add('test2', $lolb,);
    }

    public function testGet()
    {
        $cmdr = new Cmdr();
        $lol = function () {};
        $cmdr->add('test', $lol, syntax: '<stuff>...');
        $cmdr->add('test', $lol, syntax: '<yarr>...', priv: true);
        $req = $cmdr->get('test', 'abc def');
        $this->assertInstanceOf(Request::class, $req);
        $this->assertEquals('abc def', $req->args['stuff']);

        $req = $cmdr->get('test', 'abc def', priv: true);
        $this->assertEquals('abc def', $req->args['yarr']);
    }

    public function testSameFuncOK()
    {
        $cmdr = new Cmdr();
        $lol = function ($req) use(&$cnt) {
        };
        $cmdr->add('test', $lol);
        //You would want the same syntax on all I think, this is left to user
        $cmdr->add('t', $lol);
        $this->expectNotToPerformAssertions();
    }

    public function testBadNameException()
    {
        $cmdr = new Cmdr();
        $lol = function ($req) use(&$cnt) {
        };
        $this->expectException(\Exception::class);
        $cmdr->add('#test', $lol);
    }

    public function testLoadFuncs()
    {
        global $testFunc;
        $cnt = 0;
        $cmdr = new Cmdr();
        $testFunc =
            function ($req) use(&$cnt) {
                $this->assertEquals('abc def', $req->args['foo']);
                $this->assertTrue($req->args->getOpt('--bar'));
                $this->assertTrue($req->args->getOpt('--baz'));
                $cnt++;
            };
        $cmdr->loadFuncs();
        $cmdr->call('testattrs', 'abc --bar def --baz');
        $cmdr->call('noexist', 'abc def');
        $this->assertEquals(1, $cnt);
        $cmdr->call('testpubpriv', 'abc --bar def --baz');
        $this->assertEquals(2, $cnt);
        $cmdr->callPriv('testpubpriv', 'abc --bar def --baz');
        $this->assertEquals(3, $cnt);
        $cmdr->callPriv('testpriv', 'abc --bar def --baz');
        $this->assertEquals(4, $cnt);
        $cmdr->call('testpriv', 'abc --bar def --baz');
        $this->assertEquals(4, $cnt);

        $this->assertEquals("test cmd", $cmdr->privCmds["testPubPriv"]->desc);
        $this->assertEquals("test cmd", $cmdr->cmds["testPubPriv"]->desc);

        $this->assertEquals("test opt desc", $cmdr->privCmds["testpriv"]->opts[1]->desc);
    }

    public function testLoadMethods()
    {
        global $testFunc;
        $cnt = 0;
        $cmdr = new Cmdr();
        $testFunc =
            function ($req) use(&$cnt) {
                $cnt++;
            };
        $obj = new weeclass();
        $cmdr->loadMethods($obj);
        $cmdr->call('wee', '');
        $this->assertEquals(1, $cnt);
        $cmdr->callPriv('weee', '');
        $this->assertEquals(2, $cnt);
    }

    public function testPrePost()
    {
        $cmdr = new Cmdr();
        $cnt = 0;
        $lol  = function ($pre, $post, $req) use(&$cnt) {
            $this->assertInstanceOf(Request::class, $req);
            $this->assertEquals('foobar', $pre);
            $this->assertEquals('moobaz', $post);
            $cnt++;
        };
        $cmdr->add('test', $lol, preArgs: ['foobar'], postArgs: ['moobaz'], syntax: '<stuff>...');
        $cmdr->call('test', 'abc def');
        $this->assertEquals(1, $cnt);
    }

    public function testCallWrapAttribute()
    {
        global $testFunc;
        $cmdr = new Cmdr();
        $cnt = 0;

        $testFunc = function ($pre, $func, $post, $req) use(&$cnt) {
            $this->assertInstanceOf(Request::class, $req);
            $this->assertEquals('foobar', $pre);
            //php namespace&functions are lowered internally
            $this->assertEquals(strtolower(__namespace__.'\testCallWrap'), $func);
            $this->assertEquals('moobaz', $post);
            $cnt++;
        };

        $cmdr->loadFuncs();
        $cmdr->call('testCallWrap', 'abc def');
        $this->assertEquals(1, $cnt);


        $obj = new weeclass();
        $testFunc = function ($func, $req) use(&$cnt, $obj) {
            //php namespace&functions are lowered internally
            $this->assertEquals([$obj, 'eew'], $func);
            $cnt++;
        };
        $cmdr = new Cmdr();
        $cnt = 0;
        $cmdr->loadMethods($obj);
        $cmdr->call('eew', 'abc def');
        $this->assertEquals(1, $cnt);
    }
}

$testFunc = function () {};

#[Cmd("testAttrs")]
#[Syntax("<foo>...")]
#[Options("--bar")]
#[Option("--baz")]
function testAttrs(...$args) {
    global $testFunc;
    $testFunc(...$args);
};

#[Cmd("testCallWrap")]
#[Syntax("<foo>...")]
#[CallWrap(__namespace__.'\wrapTestFunc', ['foobar'], ['moobaz'])]
function testCallWrap(...$args) {
};
function wrapTestFunc(...$args) {
    global $testFunc;
    $testFunc(...$args);
};

#[Cmd("testPubPriv")]
#[PrivCmd("testPubPriv")]
#[Syntax("<foo>...")]
#[Options("--bar")]
#[Option("--baz")]
#[Desc("test cmd")]
function testPubPriv(...$args) {
    global $testFunc;
    $testFunc(...$args);
};

#[PrivCmd("testPriv")]
#[Syntax("<foo>...")]
#[Options("--bar")]
#[Option("--baz", "test opt desc")]
function testPriv(...$args) {
    global $testFunc;
    $testFunc(...$args);
};

// not sure phpunit supports mocking with attributes yet
class weeclass {
    #[Cmd('wee')]
    public function example($req) {
        global $testFunc;
        $testFunc($req);
    }

    #[PrivCmd('weee')]
    public function pexample($req) {
        global $testFunc;
        $testFunc($req);
    }

    #[Cmd('eew')]
    #[CallWrap(__namespace__.'\wrapTestFunc')]
    public function eew($req) {
    }
}


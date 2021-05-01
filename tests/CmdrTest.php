<?php

namespace knivey\cmdr\test;

use knivey\cmdr\attributes\Options;
use knivey\cmdr\Cmdr;
use knivey\cmdr\Request;
use knivey\cmdr\attributes\Cmd;
use knivey\cmdr\attributes\Syntax;
use knivey\cmdr\attributes\CallWrap;
use PHPUnit\Framework\TestCase;

class CmdrTest extends TestCase
{
    public function testGet()
    {
        $cmdr = new Cmdr();
        $lol = function () {};
        $cmdr->add('test', $lol, syntax: '<stuff>...');
        $req = $cmdr->get('test', 'abc def');
        $this->assertInstanceOf(Request::class, $req);
        $this->assertEquals('abc def', $req->args['stuff']);
    }


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
        $cmdr->add('test', $lol, syntax: '<stuff>...', opts: ['--bar']);
        $cmdr->call('test', 'abc def --bar');
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
                $cnt++;
            };
        $cmdr->loadFuncs();
        $cmdr->call('testattrs', 'abc --bar def');
        $cmdr->call('noexist', 'abc def');
        $this->assertEquals(1, $cnt);
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


// not sure phpunit supports mocking with attributes yet
class weeclass {
    #[Cmd('wee')]
    public function example($req) {
        global $testFunc;
        $testFunc($req);
    }

    #[Cmd('eew')]
    #[CallWrap(__namespace__.'\wrapTestFunc')]
    public function eew($req) {
    }
}


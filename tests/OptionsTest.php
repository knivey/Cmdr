<?php
namespace knivey\cmdr\test;

use knivey\cmdr\attributes\Cmd;
use knivey\cmdr\attributes\Option;
use knivey\cmdr\attributes\Options;
use knivey\cmdr\attributes\Syntax;
use knivey\cmdr\Cmdr;
use knivey\cmdr\exceptions\OptAlreadyDefined;
use knivey\cmdr\exceptions\OptNotFound;
use knivey\cmdr\Request;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{
    function testOptNotFound()
    {
        $cmdr = new Cmdr();
        $obj = new optsA();
        $cmdr->loadMethods($obj);
        $req = $cmdr->call('testOpts', 'abc def');
        $this->assertInstanceOf(Request::class, $req);
        $this->expectException(OptNotFound::class);
        $req->args->getOpt("--nonexists");
    }

    function testOptionAndOptions()
    {
        $cmdr = new Cmdr();
        $obj = new optsA();
        $cmdr->loadMethods($obj);

        $req = $cmdr->call('testOpts', 'abc def');
        $this->assertInstanceOf(Request::class, $req);
        $this->assertEquals('abc def', $req->args[0]);
        $this->assertFalse($req->args->optEnabled("--bar"));
        $this->assertFalse($req->args->optEnabled("--baz"));


        $req = $cmdr->call('testOpts', 'abc --bar def');
        $this->assertInstanceOf(Request::class, $req);
        $this->assertEquals('abc def', $req->args[0]);
        $this->assertTrue($req->args->optEnabled("--bar"));
        $this->assertFalse($req->args->optEnabled("--baz"));

        $req = $cmdr->call('testOpts', 'abc --baz=hello def');
        $this->assertInstanceOf(Request::class, $req);
        $this->assertEquals('abc def', $req->args[0]);
        $this->assertFalse($req->args->optEnabled("--bar"));
        $this->assertTrue($req->args->optEnabled("--baz"));
        $this->assertEquals("hello", $req->args->getOpt("--baz"));
    }

    function testMultipleOptions()
    {
        $cmdr = new Cmdr();
        $obj = new optsB();
        $cmdr->loadMethods($obj);

        $req = $cmdr->call('testOpts', 'abc --bar def');
        $this->assertInstanceOf(Request::class, $req);
        $this->assertEquals('abc def', $req->args[0]);
        $this->assertTrue($req->args->optEnabled("--bar"));
        $this->assertFalse($req->args->optEnabled("--baz"));

        $req = $cmdr->call('testOpts', 'abc --baz=hello def');
        $this->assertInstanceOf(Request::class, $req);
        $this->assertEquals('abc def', $req->args[0]);
        $this->assertFalse($req->args->optEnabled("--bar"));
        $this->assertTrue($req->args->optEnabled("--baz"));
        $this->assertEquals("hello", $req->args->getOpt("--baz"));

        $req = $cmdr->call('testOpts', 'abc --foo=hello def');
        $this->assertInstanceOf(Request::class, $req);
        $this->assertEquals('abc def', $req->args[0]);
        $this->assertFalse($req->args->optEnabled("--bar"));
        $this->assertFalse($req->args->optEnabled("--baz"));
        $this->assertEquals("hello", $req->args->getOpt("--foo"));
    }

    function testMultipleOption()
    {
        $cmdr = new Cmdr();
        $obj = new optsC();
        $cmdr->loadMethods($obj);

        $req = $cmdr->call('testOpts', 'abc --bar def');
        $this->assertInstanceOf(Request::class, $req);
        $this->assertEquals('abc def', $req->args[0]);
        $this->assertTrue($req->args->optEnabled("--bar"));
        $this->assertFalse($req->args->optEnabled("--baz"));

        $req = $cmdr->call('testOpts', 'abc --baz=hello def');
        $this->assertInstanceOf(Request::class, $req);
        $this->assertEquals('abc def', $req->args[0]);
        $this->assertFalse($req->args->optEnabled("--bar"));
        $this->assertTrue($req->args->optEnabled("--baz"));
        $this->assertEquals("hello", $req->args->getOpt("--baz"));

        $req = $cmdr->call('testOpts', 'abc --foo=hello def');
        $this->assertInstanceOf(Request::class, $req);
        $this->assertEquals('abc def', $req->args[0]);
        $this->assertFalse($req->args->optEnabled("--bar"));
        $this->assertFalse($req->args->optEnabled("--baz"));
        $this->assertEquals("hello", $req->args->getOpt("--foo"));
    }

    function testRedefineOptionsException()
    {
        $cmdr = new Cmdr();
        $obj = new optsD();
        $this->expectException(OptAlreadyDefined::class);
        $cmdr->loadMethods($obj);
    }
    function testRedefineOptionException()
    {
        $cmdr = new Cmdr();
        $obj = new optsF();
        $this->expectException(OptAlreadyDefined::class);
        $cmdr->loadMethods($obj);
    }
}

class optsA {
    #[Cmd("testOpts")]
    #[Syntax("<foo>...")]
    #[Options("--bar")]
    #[Option("--baz")]
    function testOpts(Request $req): Request {
        return $req;
    }
}

class optsB {
    #[Cmd("testOpts")]
    #[Syntax("<foo>...")]
    #[Options("--bar", "--foo")]
    #[Option("--baz")]
    function testOpts(Request $req): Request {
        return $req;
    }
}

class optsC {
    #[Cmd("testOpts")]
    #[Syntax("<foo>...")]
    #[Options("--bar")]
    #[Option("--foo", "foo!")]
    #[Option("--baz", "baz!")]
    function testOpts(Request $req): Request {
        return $req;
    }
}

class optsD {
    #[Cmd("testOpts")]
    #[Syntax("<foo>...")]
    #[Options("--bar")]
    #[Option("--foo", "foo!")]
    #[Option("--bar", "bar!")] //redefine exception
    #[Option("--baz")]
    function testOpts(Request $req): Request {
        return $req;
    }
}

class optsF {
    #[Cmd("testOpts")]
    #[Syntax("<foo>...")]
    #[Options("--bar")]
    #[Option("--foo", "foo!")]
    #[Option("--foo", "bar!")] //redefine exception
    #[Option("--baz")]
    function testOpts(Request $req): Request {
        return $req;
    }
}
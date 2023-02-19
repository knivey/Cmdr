<?php
namespace knivey\cmdr\test;

use knivey\cmdr\Args;
use knivey\cmdr\attributes\Cmd;
use knivey\cmdr\attributes\Option;
use knivey\cmdr\attributes\Options;
use knivey\cmdr\attributes\Syntax;
use knivey\cmdr\Cmdr;
use knivey\cmdr\exceptions\OptAlreadyDefined;
use knivey\cmdr\exceptions\OptNotFound;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{
    function testOptNotFound()
    {
        $cmdr = new Cmdr();
        $obj = new optsA();
        $cmdr->loadMethods($obj);
        $args = $cmdr->call('testOpts', 'abc def');
        $this->assertInstanceOf(Args::class, $args);
        $this->expectException(OptNotFound::class);
        $args->getOpt("--nonexists");
    }

    function testOptionAndOptions()
    {
        $cmdr = new Cmdr();
        $obj = new optsA();
        $cmdr->loadMethods($obj);

        $args = $cmdr->call('testOpts', 'abc def');
        $this->assertInstanceOf(Args::class, $args);
        $this->assertEquals('abc def', $args[0]);
        $this->assertFalse($args->optEnabled("--bar"));
        $this->assertFalse($args->optEnabled("--baz"));


        $args = $cmdr->call('testOpts', 'abc --bar def');
        $this->assertInstanceOf(Args::class, $args);
        $this->assertEquals('abc def', $args[0]);
        $this->assertTrue($args->optEnabled("--bar"));
        $this->assertFalse($args->optEnabled("--baz"));

        $args = $cmdr->call('testOpts', 'abc --baz=hello def');
        $this->assertInstanceOf(Args::class, $args);
        $this->assertEquals('abc def', $args[0]);
        $this->assertFalse($args->optEnabled("--bar"));
        $this->assertTrue($args->optEnabled("--baz"));
        $this->assertEquals("hello", $args->getOpt("--baz"));
    }

    function testMultipleOptions()
    {
        $cmdr = new Cmdr();
        $obj = new optsB();
        $cmdr->loadMethods($obj);

        $args = $cmdr->call('testOpts', 'abc --bar def');
        $this->assertInstanceOf(Args::class, $args);
        $this->assertEquals('abc def', $args[0]);
        $this->assertTrue($args->optEnabled("--bar"));
        $this->assertFalse($args->optEnabled("--baz"));

        $args = $cmdr->call('testOpts', 'abc --baz=hello def');
        $this->assertInstanceOf(Args::class, $args);
        $this->assertEquals('abc def', $args[0]);
        $this->assertFalse($args->optEnabled("--bar"));
        $this->assertTrue($args->optEnabled("--baz"));
        $this->assertEquals("hello", $args->getOpt("--baz"));

        $args = $cmdr->call('testOpts', 'abc --foo=hello def');
        $this->assertInstanceOf(Args::class, $args);
        $this->assertEquals('abc def', $args[0]);
        $this->assertFalse($args->optEnabled("--bar"));
        $this->assertFalse($args->optEnabled("--baz"));
        $this->assertEquals("hello", $args->getOpt("--foo"));
    }

    function testMultipleOption()
    {
        $cmdr = new Cmdr();
        $obj = new optsC();
        $cmdr->loadMethods($obj);

        $args = $cmdr->call('testOpts', 'abc --bar def');
        $this->assertInstanceOf(Args::class, $args);
        $this->assertEquals('abc def', $args[0]);
        $this->assertTrue($args->optEnabled("--bar"));
        $this->assertFalse($args->optEnabled("--baz"));

        $args = $cmdr->call('testOpts', 'abc --baz=hello def');
        $this->assertInstanceOf(Args::class, $args);
        $this->assertEquals('abc def', $args[0]);
        $this->assertFalse($args->optEnabled("--bar"));
        $this->assertTrue($args->optEnabled("--baz"));
        $this->assertEquals("hello", $args->getOpt("--baz"));

        $args = $cmdr->call('testOpts', 'abc --foo=hello def');
        $this->assertInstanceOf(Args::class, $args);
        $this->assertEquals('abc def', $args[0]);
        $this->assertFalse($args->optEnabled("--bar"));
        $this->assertFalse($args->optEnabled("--baz"));
        $this->assertEquals("hello", $args->getOpt("--foo"));
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
    function testOpts(Args $args): Args {
        return $args;
    }
}

class optsB {
    #[Cmd("testOpts")]
    #[Syntax("<foo>...")]
    #[Options("--bar", "--foo")]
    #[Option("--baz")]
    function testOpts(Args $args): Args {
        return $args;
    }
}

class optsC {
    #[Cmd("testOpts")]
    #[Syntax("<foo>...")]
    #[Options("--bar")]
    #[Option("--foo", "foo!")]
    #[Option("--baz", "baz!")]
    function testOpts(Args $args): Args {
        return $args;
    }
}

class optsD {
    #[Cmd("testOpts")]
    #[Syntax("<foo>...")]
    #[Options("--bar")]
    #[Option("--foo", "foo!")]
    #[Option("--bar", "bar!")] //redefine exception
    #[Option("--baz")]
    function testOpts(Args $args): Args {
        return $args;
    }
}

class optsF {
    #[Cmd("testOpts")]
    #[Syntax("<foo>...")]
    #[Options("--bar")]
    #[Option("--foo", "foo!")]
    #[Option("--foo", "bar!")] //redefine exception
    #[Option("--baz")]
    function testOpts(Args $args): Args {
        return $args;
    }
}
<?php
namespace knivey\cmdr\test;

use knivey\cmdr\attributes\Cmd;
use knivey\cmdr\attributes\Desc;
use knivey\cmdr\attributes\Option;
use knivey\cmdr\attributes\Options;
use knivey\cmdr\attributes\Syntax;
use knivey\cmdr\Cmdr;
use knivey\cmdr\Request;
use PHPUnit\Framework\TestCase;

class DescriptionsTest extends TestCase
{
    function testCmdDescriptions()
    {
        $cmdr = new Cmdr();
        $obj = new descA();
        $cmdr->loadMethods($obj);
        $this->assertEquals("test command", $cmdr->cmds["testOpts"]->desc);
        $this->assertEquals("No description", $cmdr->cmds["testc"]->desc);
    }

    function testOptionDescriptions()
    {
        $cmdr = new Cmdr();
        $obj = new descA();
        $cmdr->loadMethods($obj);
        $this->assertEquals("foo!", $cmdr->cmds["testOpts"]->opts["--foo"]->desc);
        $this->assertEquals("--foo foo!", (string)$cmdr->cmds["testOpts"]->opts["--foo"]);
        $this->assertEquals("No description", $cmdr->cmds["testOpts"]->opts["--bar"]->desc);
    }

    function testCmdFullDescriptions()
    {
        $cmdr = new Cmdr();
        $obj = new descA();
        $cmdr->loadMethods($obj);
        $expected = "testc\n    --bar No description\nNo description";
        $this->assertEquals($expected, (string)$cmdr->cmds["testc"]);
        $expected =
            "testOpts <foo>...\n".
            "    --bar No description\n".
            "    --foo foo!\n".
            "    --baz baz!\n".
            "test command";
        $this->assertEquals($expected, (string)$cmdr->cmds["testopts"]);
    }
}

class descA {
    #[Cmd("testOpts")]
    #[Syntax("<foo>...")]
    #[Desc("test command")]
    #[Options("--bar")]
    #[Option("--foo", "foo!")]
    #[Option("--baz", "baz!")]
    function testOpts(Request $req): Request {
        return $req;
    }

    #[Cmd("testc")]
    #[Options("--bar")]
    function testc(Request $req): Request {
        return $req;
    }
}

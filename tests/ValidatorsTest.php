<?php

namespace knivey\cmdr\test;

use knivey\cmdr\Arg;
use knivey\cmdr\attributes\Cmd;
use knivey\cmdr\attributes\Syntax;
use knivey\cmdr\Cmdr;
use knivey\cmdr\exceptions\ParseException;
use knivey\cmdr\Validate;
use PHPUnit\Framework\TestCase;

class ValidatorsTest extends TestCase
{
    public function cmdrProvider(): array {
        $cmdr = new Cmdr();
        $o = new example();
        $cmdr->loadMethods($o);
        return [[$cmdr]];
    }

    public function testArgSyntaxParsing() {
        $arg = new Arg(1, "test", 0);
        $this->assertEquals("test", $arg->name);
        $this->assertNull($arg->validator);
        $this->assertEmpty($arg->validatorArgs);

        $arg = new Arg(1, "test: int", 0);
        $this->assertEquals("test", $arg->name);
        $this->assertEquals("int", $arg->validator);
        $this->assertEmpty($arg->validatorArgs);

        $arg = new Arg(1, "test: int max=17", 0);
        $this->assertEquals("test", $arg->name);
        $this->assertEquals("int", $arg->validator);
        $this->assertEquals(["max"=>"17"], $arg->validatorArgs);
    }

    /** @dataProvider cmdrProvider */
    public function testLoadingMethodsSyntax(Cmdr $cmdr)
    {
        $arg = $cmdr->cmds['test']->cmdArgs->getUnparsedArg('idx');
        $this->assertEquals("idx", $arg->name);
        $this->assertEquals("int", $arg->validator);
        $this->assertEquals(['max'=>5], $arg->validatorArgs);

        $arg = $cmdr->cmds['choice']->cmdArgs->getUnparsedArg('idx');
        $this->assertEquals("idx", $arg->name);
    }

    /** @dataProvider cmdrProvider */
    public function testValidArgs(Cmdr $cmdr)
    {
        $this->expectNotToPerformAssertions();
        $cmdr->call('test', '3');
    }

    /** @dataProvider cmdrProvider */
    public function testInValidArgs1(Cmdr $cmdr)
    {
        $this->expectException(ParseException::class);
        $cmdr->call('test', '6');
    }

    /** @dataProvider cmdrProvider */
    public function testInValidArgs2(Cmdr $cmdr)
    {
        $this->expectException(ParseException::class);
        $cmdr->call('test', 'arst');
    }

    /** @dataProvider cmdrProvider */
    public function testValidBools(Cmdr $cmdr)
    {
        //just doing a few, real testing in validator class
        $r = $cmdr->call('bools', 'yes');
        $this->assertTrue($r['arg']);
        $r = $cmdr->call('bools', 'yes');
        $this->assertTrue($r['arg']);
        $r = $cmdr->call('bools', '1');
        $this->assertTrue($r['arg']);
        $r = $cmdr->call('bools', 'off');
        $this->assertFalse($r['arg']);
        $r = $cmdr->call('bools', 'false');
        $this->assertFalse($r['arg']);
    }

    /** @dataProvider cmdrProvider */
    public function testInvalidBool(Cmdr $cmdr)
    {
        $this->expectException(ParseException::class);
        $cmdr->call('bools', 'arst');
    }

    /** @dataProvider cmdrProvider */
    public function testValidChoice(Cmdr $cmdr)
    {
        $this->expectNotToPerformAssertions();
        $cmdr->call('choice', 'one');
        $cmdr->call('choice', 'two');
        $cmdr->call('choice', 'three');
    }

    /** @dataProvider cmdrProvider */
    public function testInvalidChoice(Cmdr $cmdr)
    {
        $this->expectException(ParseException::class);
        $cmdr->call('choice', 'foo');
    }
}

class example {
    #[Cmd("test")]
    #[Syntax("<idx: int max=5>")]
    function foo($args) {
        return $args;
    }
    #[Cmd("choice")]
    #[Syntax("<idx: (one|two|three)>")]
    function fooo($args) {
        return $args;
    }
    #[Cmd("bools")]
    #[Syntax("<arg: bool>")]
    function fooof($args) {
        return $args;
    }
}
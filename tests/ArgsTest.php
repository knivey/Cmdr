<?php

namespace knivey\cmdr\test;

use knivey\cmdr\Args;
use knivey\cmdr\exceptions\BadArgName;
use knivey\cmdr\exceptions\ParseException;
use knivey\cmdr\exceptions\SyntaxException;
use knivey\cmdr\Option;
use PHPUnit\Framework\TestCase;
use \Ayesh\CaseInsensitiveArray\Strict as CIArray;

class ArgsTest extends TestCase
{
    function testNoArgs()
    {
        $args = new Args('');
        $this->assertEmpty($args);
        $this->assertCount(0, $args);
        $args->parse("arst tsra moo");
        $this->assertEmpty($args);
        $this->assertCount(0, $args);
    }

    function testReqArg()
    {
        $args = new Args('<foo>');
        $args->parse('moo boo poo');
        $this->assertEquals('moo', $args->getArg('foo'));
        $this->assertCount(1, $args);

        $args = new Args('<foo>');
        $args->parse('m)_M@"');
        $this->assertEquals('m)_M@"', $args->getArg('foo'));
        $this->assertCount(1, $args);

        $args = new Args('<foo>');
        $this->expectException(ParseException::class);
        $args->parse('');
    }

    function testLeadingSpaces()
    {
        $args = new Args('<foo>');
        $args->parse('   moo boo poo');
        $this->assertEquals('moo', $args->getArg('foo'));

        $args = new Args('<foo> <shoe>');
        $args->parse('  moo  boo poo');
        $this->assertEquals('moo', $args->getArg('foo'));
        $this->assertEquals('boo', $args->getArg('shoe'));
    }

    function testReqAndOpt()
    {
        $args = new Args('<foo> [bar]');
        $args->parse('moo boo poo');
        $this->assertEquals('moo', $args->getArg('foo'));
        $this->assertEquals('boo', $args->getArg('bar'));
        $this->assertCount(2, $args);

        $args = new Args('<foo> [bar]');
        $args->parse('moo');
        $this->assertEquals('moo', $args->getArg('foo'));
        $this->assertEquals('', $args->getArg('bar'));
        //Even tho its not parsed its accessible as null str
        $this->assertCount(2, $args);

        $args = new Args('<foo> [bar]');
        $this->expectException(ParseException::class);
        $args->parse('');
    }

    function testArrayAccess()
    {
        $args = new Args("<Account> <barf> [a_r]");
        $args->parse('moo boo poo');
        $this->assertEquals('moo', $args['Account']);
        $this->assertEquals('moo', $args[0]);
        $this->assertEquals('boo', $args['barf']);
        $this->assertEquals('boo', $args[1]);
        $this->assertEquals('poo', $args['a_r']);
        $this->assertEquals('poo', $args[2]);
        $this->assertEquals(null, $args[5]);
        $this->assertTrue(isset($args['a_r']));
        $this->assertFalse(isset($args['f']));
        $this->assertFalse(isset($args[9]));
        $args = new Args("<Account> <barf> [a_r]");
        $args->parse('moo boo');
        $this->assertFalse(isset($args['a_r']));
        $this->assertEquals(null, $args['a_r']);
        $this->assertFalse(isset($args[2]));
        $this->assertEquals(null, $args[2]);
    }

    function testBadArgnameAccess()
    {
        $args = new Args("<Account> <barf> [a_r]");
        $args->parse('moo boo poo');
        $this->expectException(BadArgName::class);
        $this->assertEquals(null, $args['a']);
    }

    function testReqMultiword()
    {
        $args = new Args('<foo>...');
        $args->parse('moo boo poo');
        $this->assertEquals('moo boo poo', $args->getArg('foo'));

        $args = new Args('<foo>...');
        $args->parse('moo');
        $this->assertEquals('moo', $args->getArg('foo'));

        $this->expectException(ParseException::class);
        $args = new Args('<foo>...');
        $args->parse('');
    }

    function testOptMultiword()
    {
        $args = new Args('[foo]...');
        $args->parse('moo boo poo');
        $this->assertEquals('moo boo poo', $args->getArg('foo'));

        $args = new Args('[foo]...');
        $args->parse('moo');
        $this->assertEquals('moo', $args->getArg('foo'));

        $args = new Args('[foo]...');
        $args->parse('');
        $this->assertEquals('', $args->getArg('foo'));
    }

    function testOptBeforeReq()
    {
        $this->expectException(SyntaxException::class);
        $args = new Args('[bar] <foo>');
    }

    function testOptBeforeReqMulti()
    {
        $this->expectException(SyntaxException::class);
        $args = new Args('[bar] <foo>...');
    }

    function testOptBeforeOpt()
    {
        $args = new Args('[bar] [foo]');
        $args->parse('test');
        $this->assertFalse(isset($args['foo']));
        $this->assertEquals('test', $args['bar']);
        $args->parse('test moo');
        $this->assertEquals('test', $args['bar']);
        $this->assertEquals('moo', $args['foo']);
    }

    function testOptBeforeOptMulti()
    {
        $args = new Args('[bar] [foo] [moo]...');
        $args->parse('test');
        $this->assertFalse(isset($args['foo']));
        $this->assertFalse(isset($args['moo']));
        $this->assertEquals('test', $args['bar']);
        $args->parse('test moo');
        $this->assertEquals('test', $args['bar']);
        $this->assertEquals('moo', $args['foo']);
        $this->assertFalse(isset($args['moo']));
        $args->parse('test moo blah blah blah');
        $this->assertEquals('test', $args['bar']);
        $this->assertEquals('moo', $args['foo']);
        $this->assertEquals('blah blah blah', $args['moo']);
    }

    function testOptBeforeOptMultiReq()
    {
        $this->expectException(SyntaxException::class);
        $args = new Args('[bar] [foo]... <moo>');
    }

    function testMultiBefore()
    {
        $this->expectException(SyntaxException::class);
        $args = new Args('[foo]... [bar]');
    }

    function testMultiBefore2()
    {
        $this->expectException(SyntaxException::class);
        $args = new Args('<bar>... [foo]');
    }

    function testMultiBefore3()
    {
        $this->expectException(SyntaxException::class);
        $args = new Args('<bar>... <foo>');
    }

    function testMultiAfter()
    {
        $args = new Args('<foo> <moo> <bar>...');
        $args->parse('test zoo blahr blahz blah');
        $this->assertEquals('test', $args['foo']);
        $this->assertEquals('zoo', $args['moo']);
        $this->assertEquals('blahr blahz blah', $args['bar']);
    }

    /**
     * @doesNotPerformAssertions
     */
    function testTrailingSpace()
    {
        $args = new Args('<foo> <moo> <bar>... ');
    }

    /**
     * @doesNotPerformAssertions
     */
    function testExtraSpace()
    {
        $args = new Args('<foo> <moo>   <bar>... ');
    }

    function testMissingSecReqArg()
    {
        $args = new Args('<foo> <blah>');
        $this->expectException(ParseException::class);
        $args->parse('moo');
    }

    //Exception tests all need own method
    function testInvalidSyntax()
    {
        $this->expectException(SyntaxException::class);
        $args = new Args('<foo>>');
    }

    function testInvalidSyntax2()
    {
        $this->expectException(SyntaxException::class);
        $args = new Args('foo');
    }

    function testInalidSyntax3()
    {
        $this->expectException(SyntaxException::class);
        $args = new Args('<[arst]>');
    }

    function testInvalidSyntax4()
    {
        $this->expectException(SyntaxException::class);
        $args = new Args('[<arst>]');
    }
/*
    function testInvalidSyntax5()
    {
        $this->expectException(SyntaxException::class);
        $args = new Args('<moo lol>');
    }
*/
    function testInvalidSyntax6()
    {
        $this->expectException(SyntaxException::class);
        $args = new Args('<foo><bar>');
    }

    function testValidSyntax1()
    {
        $args = new Args("<Account|Chan> <OldMod.OldSetName> <NewMod.NewSetName> [a_r]");
        $args->parse('moo boo poo woo');
        $this->assertEquals('moo', $args->getArg('Account|Chan'));
        $this->assertEquals('boo', $args[1]);
        $this->assertEquals('poo', $args->getArg('NewMod.NewSetName'));
        $this->assertEquals('woo', $args->getArg('a_r'));
        $this->assertCount(4, $args);
    }

    function testArgWhenNotReq()
    {
        $args = new Args("");
        $args->parse('moo boo poo woo');
        $this->assertCount(0, $args);
    }


    function testOptions()
    {
        $args = new Args('<foo>...', [new Option('--nes')]);
        $args->parse('moo boo poo');
        $this->assertEquals('moo boo poo', $args[0]);
        $this->assertEmpty($args->getOpts());
        $args->parse('moo --nes poo');
        $this->assertEquals('moo poo', $args[0]);
        $this->assertEquals(new CIArray(['--nes'=>null]), $args->getOpts());

        $args = new Args('<foo>', [new Option('--nes')]);
        $args->parse('moo boo poo');
        $this->assertEquals('moo', $args[0]);
        $this->assertEmpty($args->getOpts());
        $args->parse('moo --nes poo');
        $this->assertEquals('moo', $args[0]);
        $this->assertEquals(new CIArray(['--nes'=>null]), $args->getOpts());

        $args = new Args('[foo]', [new Option('--nes')]);
        $args->parse('moo boo poo');
        $this->assertEquals('moo', $args[0]);
        $this->assertEmpty($args->getOpts());
        $args->parse('--nes moo');
        $this->assertEquals('moo', $args[0]);
        $this->assertTrue($args->optEnabled('--nes'));

        $args = new Args('[foo]', [new Option('--nes'), new Option('--bar')]);
        $args->parse('moo --nes boo poo');
        $this->assertEquals('moo', $args[0]);
        $this->assertTrue($args->optEnabled('--nes'));
        $this->assertFalse($args->optEnabled('--bar'));
        $args->parse('--nes moo --bar');
        $this->assertEquals('moo', $args[0]);
        $this->assertTrue($args->optEnabled('--nes'));
        $this->assertTrue($args->optEnabled('--bar'));
    }

    function testOptionsCase()
    {
        $args = new Args('', ['--nes'=>new Option('--nes'), new Option('--NES')]);
        $args->parse('--nes');
        $this->assertTrue($args->optEnabled("--nes"));
        $this->assertTrue($args->optEnabled("--NES"));

        $args = new Args('', [new Option('--nes'), new Option('--NES')]);
        $args->parse('--NES');
        $this->assertTrue($args->optEnabled("--nes"));
        $this->assertTrue($args->optEnabled("--NES"));
    }

    function testOptionsValue()
    {
        $args = new Args('', [new Option('--nes'), new Option('--jam')]);
        $args = $args->parse('--nes');
        $this->assertTrue($args->optEnabled("--nes"));
        $this->assertEquals(true, $args->getOpt("--nes"));
        $this->assertFalse($args->getOpt("--jam"));

        $args = $args->parse('--nes=lol');
        $this->assertTrue($args->optEnabled("--nes"));
        $this->assertEquals("lol", $args->getOpt("--nes"));
        $this->assertFalse($args->optEnabled("--jam"));
        $this->assertFalse($args->getOpt("--jam"));

        $args = $args->parse('--nes=LOL');
        $this->assertTrue($args->optEnabled("--nes"));
        $this->assertEquals("LOL", $args->getOpt("--nes"));
        $this->assertFalse($args->optEnabled("--jam"));
        $this->assertFalse($args->getOpt("--jam"));

        $args = $args->parse('--nes=LOL --jam=yeah');
        $this->assertTrue($args->optEnabled("--nes"));
        $this->assertEquals("LOL", $args->getOpt("--nes"));
        $this->assertTrue($args->optEnabled("--jam"));
        $this->assertEquals("yeah", $args->getOpt("--jam"));

        $args = $args->parse('--nes=0');
        $this->assertTrue($args->optEnabled("--nes"));
        $this->assertEquals("0", $args->getOpt("--nes"));
    }
}

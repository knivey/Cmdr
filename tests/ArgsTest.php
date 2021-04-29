<?php

namespace knivey\cmdr\test;

use knivey\cmdr\Args;
use knivey\cmdr\ParseException;
use knivey\cmdr\SyntaxException;
use PHPUnit\Framework\TestCase;

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
        $this->assertEquals(null, $args['a']);
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
        $args = new Args('<foo> <moo> <bar>... ');
        $args->parse('test zoo blahr blahz blah');
        $this->assertEquals('test', $args['foo']);
        $this->assertEquals('zoo', $args['moo']);
        $this->assertEquals('blahr blahz blah', $args['bar']);
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

    function testInvalidSyntax5()
    {
        $this->expectException(SyntaxException::class);
        $args = new Args('<moo lol>');
    }

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
}

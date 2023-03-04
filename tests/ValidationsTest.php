<?php

namespace knivey\cmdr\test;

use PHPUnit\Framework\TestCase;
use knivey\cmdr\Validate;

class ValidationsTest extends TestCase
{
    public function testBuiltinIntValidator()
    {
        //just some random ints i guess
        $this->assertTrue(Validate::int(10));
        $this->assertTrue(Validate::int(-100));
        $this->assertTrue(Validate::int(PHP_INT_MAX));
        $this->assertTrue(Validate::int(PHP_INT_MIN));
        $i = PHP_INT_MIN;
        $this->assertTrue(Validate::int("$i"));
        $i = PHP_INT_MAX;
        $this->assertTrue(Validate::int("$i"));
        $i = PHP_INT_MIN -1;
        $this->assertFalse(Validate::int("$i"));
        $i = PHP_INT_MAX +1;
        $this->assertFalse(Validate::int("$i"));
        $this->assertFalse(Validate::int("999999999999999999999999999999999999999999999999999999999999999999"));
        $this->assertFalse(Validate::int("-999999999999999999999999999999999999999999999999999999999999999999"));

        $this->assertTrue(Validate::int(10, max: 10, min: 10));
        $this->assertTrue(Validate::int(10, min: 5, max: 10));
        $this->assertTrue(Validate::int(10, min: 5));
        $this->assertFalse(Validate::int(10, max: 5));
        $this->assertFalse(Validate::int(6, max: 5));
        $this->assertFalse(Validate::int(6, min: 7));

        $this->assertTrue(Validate::int("10"));
        $this->assertFalse(Validate::int("10.0"));
        $this->assertTrue(Validate::int("0010"));
        $this->assertTrue(Validate::int("0"));
        $this->assertTrue(Validate::int("-0"));
        $this->assertFalse(Validate::number(""));

        //uint is just min: 0
        $this->assertTrue(Validate::uint("10"));
        $this->assertTrue(Validate::uint("-0"));
        $this->assertFalse(Validate::uint("-1"));
    }

    public function testBuiltinNumberValidator()
    {
        $this->assertTrue(Validate::number(10));
        $this->assertTrue(Validate::number(-100));
        $this->assertTrue(Validate::number(PHP_INT_MAX));
        $this->assertTrue(Validate::number(PHP_INT_MIN));
        $i = PHP_INT_MIN;
        $this->assertTrue(Validate::number("$i"));
        $i = PHP_INT_MAX;
        $this->assertTrue(Validate::number("$i"));
        $i = PHP_INT_MIN -1;
        $this->assertTrue(Validate::number("$i"));
        $i = PHP_INT_MAX +1;
        $this->assertTrue(Validate::number("$i"));
        $this->assertTrue(Validate::number("999999999999999999999999999999999999999999999999999999999999999999"));
        $this->assertTrue(Validate::number("-999999999999999999999999999999999999999999999999999999999999999999"));
        $this->assertTrue(Validate::number("999999999999999999999999999999999999999999999999999999999999999999.0000"));
        $this->assertTrue(Validate::number("-999999999999999999999999999999999999999999999999999999999999999999.9999"));

        $this->assertTrue(Validate::number(10, max: 10, min: 10));
        $this->assertTrue(Validate::number(10, min: 5, max: 10));
        $this->assertTrue(Validate::number(10, min: 5));
        $this->assertFalse(Validate::number(10, max: 5));
        $this->assertFalse(Validate::number(6, max: 5));
        $this->assertFalse(Validate::number(6, min: 7));

        $this->assertTrue(Validate::number("10"));
        $this->assertTrue(Validate::number("10.0"));
        $this->assertTrue(Validate::number("0010"));
        $this->assertTrue(Validate::number("0"));
        $this->assertTrue(Validate::number("-0"));
        $this->assertFalse(Validate::number(""));

        //uint is just min: 0
        $this->assertTrue(Validate::number("10", min: 0));
        $this->assertTrue(Validate::number("-0", min: 0));
        $this->assertFalse(Validate::number("-1", min: 0));
    }

    public function testOptions()
    {
        //not must to test its just in_array()
        $this->assertTrue(Validate::options('test', ['blah', 'test']));
        $this->assertFalse(Validate::options('tes', ['blah', 'test']));
    }

    private static array $trues = ['yes', 'on','1','true', 1, true];
    private static array $falses = ['no','off','0','false', 0, false];
    public function testBool() {
        foreach([...self::$trues, ...self::$falses] as $val) {
            $this->assertTrue(Validate::bool($val), "Tested Value: " . \json_encode($val));
            if(is_string($val))
                $val = strtoupper($val);
            $this->assertTrue(Validate::bool($val),"Tested Value: " . \json_encode($val));
        }

        foreach(["blah", "moo", ["true"], null] as $val) {
            $this->assertFalse(Validate::bool($val), "Tested Value: " . \json_encode($val));
        }
    }

    public function testCustomValidatorMissing() {
        $validator = "test";
        $this->expectException(\Exception::class);
        Validate::runValidation($validator, "moo", ["moo"]);
    }

    public function testCustomFilterMissing() {
        $validator = "test";
        $this->assertEquals(Validate::runFilter($validator,"moo", ["moo"]), "moo");
    }

    public function testCustomValidator() {
        Validate::setValidator("test", function($val) {return $val;});
        Validate::setValidator("test2", function($val, $moo) {return $val + $moo;});
        $this->assertTrue(Validate::runValidation("test", true));
        $this->assertTrue(Validate::runValidation("test2", 0, [1]));
    }

    public function testCustomFilter() {
        Validate::setFilter("test", function($val, $moo) {
            if($moo) return strtoupper($val);
            return $val;
        });
        $this->assertEquals(Validate::runValidation("test", false, ["moo" => "moo"]), "moo");
        $this->assertEquals(Validate::runValidation("test", true, ["moo" => "moo"]), "MOO");
    }
}

<?php

namespace knivey\cmdr\test;

use knivey\cmdr\Arg;
use knivey\cmdr\Validate;
use PHPUnit\Framework\TestCase;

class TestValidatorsFromArgs extends TestCase
{
    public function testParsing() {
        $arg = new Arg(1, "<test>", 0);
        $this->assertEquals("test", $arg->name);
        $this->assertNull($arg->validator);
        $this->assertEmpty($arg->validatorArgs);

        $arg = new Arg(1, "<test: int>", 0);
        $this->assertEquals("test", $arg->name);
        $this->assertEquals("int", $arg->validator);
        $this->assertEmpty($arg->validatorArgs);
    }

}

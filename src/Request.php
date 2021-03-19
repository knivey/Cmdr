<?php


namespace knivey\cmdr;


class Request
{
    public Args $args;
    public Cmd $cmd;

    function __construct($args, $cmd)
    {
        $this->args = $args;
        $this->cmd = $cmd;
    }
}
<?php
namespace knivey\cmdr;

use \Ayesh\CaseInsensitiveArray\Strict as CIArray;

class Cmdr
{
    public CIArray $cmds;

	public function __construct()
    {
        $this->cmds = new CIArray();
    }

    function add(string $command, callable $method, array $callArgs = [], string $syntax = '') {
        if (str_contains($command, '#')) {
            throw new \Exception('Command name cannot contain #');
        }
        $this->cmds[$command] = new Cmd($command, $method, $callArgs, $syntax);
    }

    function get(string $command, string $text) : Request|false {
	    if(!isset($this->cmds[$command])) {
	        return false;
        }
	    $cmd = $this->cmds[$command];
	    $args = new Args($cmd->syntax);
	    $args->parse($text);
	    return new Request($args, $cmd);
    }

    function call(string $command, string $text, array $extraArgs) {
	    $req = $this->get($command, $text);
	    if(!$req)
	        return false;
	    return call_user_func_array($req->cmd->method, [...$req->cmd->callArgs, ...$extraArgs, $req]);
    }

}
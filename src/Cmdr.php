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

    function add(string $command, callable $method, array $preArgs = [], array $postArgs = [], string $syntax = '') {
        if (str_contains($command, '#')) {
            throw new \Exception('Command name cannot contain #');
        }
        if(isset($this->cmds[$command])) {
            throw new \Exception('Command already exists');
        }
        $this->cmds[$command] = new Cmd($command, $method, $preArgs, $postArgs, $syntax);
    }

    function get(string $command, string $text) : Request|false {
	    if(!isset($this->cmds[$command])) {
	        return false;
        }
	    $cmd = $this->cmds[$command];
	    $args = $cmd->cmdArgs->parse($text);
	    return new Request($args, $cmd);
    }

    function loadMethodsByAttributes(object $obj) {
	    //TODO
    }

    function loadFuncsByAttributes() {
	    $funcs = get_defined_functions(true)["user"];
	    foreach ($funcs as $f) {
	        $rf = new \ReflectionFunction($f);
	        //all commands should at least have Cmd attribute
	        $cmdAttr = $rf->getAttributes(attributes\Cmd::class);
	        if(count($cmdAttr) == 0)
	            continue;
	        $cmdAttr = $cmdAttr[0]->newInstance();
            $syntaxAttr = $rf->getAttributes(attributes\Syntax::class);
            $syntax = '';
            $callWrapper = null;
            $callWrapperPre = [];
            $callWrapperPost = [];
            if(isset($syntaxAttr[0])) {
                $sa = $syntaxAttr[0]->newInstance();
                $syntax = $sa->syntax;
            }
            $callWrapAttr = $rf->getAttributes(attributes\CallWrap::class);
            if(isset($callWrapAttr[0])) {
                $cw = $callWrapAttr[0]->newInstance();
                $callWrapper = $cw->caller;
                $callWrapperPre = $cw->preArgs;
                $callWrapperPost = $cw->postArgs;
            }
            foreach ($cmdAttr->args as $command) {
                if($callWrapper != null)
                    $this->cmds[$command] = new Cmd($command, $callWrapper, [...$callWrapperPre, $f], $callWrapperPost, $syntax);
                else
                    $this->cmds[$command] = new Cmd($command, $f, $callWrapperPre, $callWrapperPost, $syntax);
            }
        }
    }

    function call(string $command, string $text, ...$extraArgs) {
	    $req = $this->get($command, $text);
	    if(!$req)
	        return false;
	    return call_user_func_array($req->cmd->method, [...$req->cmd->preArgs, ...$req->cmd->postArgs, ...$extraArgs, $req]);
    }

}
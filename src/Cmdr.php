<?php
namespace knivey\cmdr;

use Ayesh\CaseInsensitiveArray\Strict as CIArray;
use knivey\cmdr\exceptions\BadCmdName;
use knivey\cmdr\exceptions\CmdAlreadyExists;
use knivey\cmdr\exceptions\CmdNotFound;
use knivey\cmdr\exceptions\OptAlreadyDefined;
use knivey\cmdr\exceptions\SyntaxException;

/**
 * Main class to use Cmdr
 */
class Cmdr
{
    /**
     * Holds public commands (inside irc channel)
     * @var CIArray<string, Cmd>
     */
    public CIArray $cmds;
    /**
     * Holds private commands (private irc message)
     * @var CIArray<string, Cmd>
     */
    public CIArray $privCmds;

	public function __construct()
    {
        $this->cmds = new CIArray();
        $this->privCmds = new CIArray();
    }

    /**
     * Global middlewares, run in registration order before per-command ones.
     * Middleware signature: fn(Request $req, callable $next, mixed ...$args): mixed
     * @var array<int, callable>
     */
    public array $globalMiddleware = [];
    /**
     * Per-command middlewares by command name.
     * @var array<string, array<int, callable>>
     */
    public array $cmdMiddleware = [];
    /**
     * Named middleware aliases resolvable from attributes.
     * @var array<string, callable>
     */
    public array $middlewareAliases = [];

    /**
     * Register a middleware. Without $command it runs globally (registration
     * order); with $command it runs only for that command (both public and
     * private instances of the name).
     */
    function addMiddleware(callable $middleware, ?string $command = null): void
    {
        if ($command === null) {
            $this->globalMiddleware[] = $middleware;
        } else {
            $this->cmdMiddleware[strtolower($command)][] = $middleware;
        }
    }

    /**
     * Register a named middleware that attributes can reference by name.
     */
    function aliasMiddleware(string $name, callable $middleware): void
    {
        $this->middlewareAliases[$name] = $middleware;
    }

    /**
     * Manually add a command, typically you would use attributes and loading instead of this.
     * @throws BadCmdName
     * @throws CmdAlreadyExists
     * @throws SyntaxException
     */
    function add(string $command, callable $method, array $preArgs = [], array $postArgs = [], string $syntax = '',
                 array $opts = [], bool $priv = false, string $desc = "No description",
                 array $attrMiddleware = []): void {
        if (str_contains($command, '#')) {
            throw new BadCmdName('Command name cannot contain #');
        }
        if (str_contains($command, ' ')) {
            throw new BadCmdName('Command name cannot contain space');
        }
        if ($method instanceof \Closure) {
            $attrMiddleware = [...$attrMiddleware, ...$this->collectAttrMiddleware(new \ReflectionFunction($method))];
        }
        if(!$priv) {
            if (isset($this->cmds[$command])) {
                throw new CmdAlreadyExists("Command $command already exists");
            }
            $this->cmds[$command] = new Cmd($command, $method, $preArgs, $postArgs, $syntax, $opts, $desc, $attrMiddleware);
        } else {
            if (isset($this->privCmds[$command])) {
                throw new CmdAlreadyExists("Command already exists");
            }
            $this->privCmds[$command] = new Cmd($command, $method, $preArgs, $postArgs, $syntax, $opts, $desc, $attrMiddleware);
        }
    }

    /**
     * Gets a Request object for a command of false if not found
     * @param string $command
     * @param string $text argument string passed to command
     * @param bool $priv Is the command a private command?
     * @return Request|false
     */
    function get(string $command, string $text, bool $priv = false) : Request|false {
	    if(!$priv) {
            if (!isset($this->cmds[$command])) {
                return false;
            }
            $cmd = $this->cmds[$command];
        } else {
            if (!isset($this->privCmds[$command])) {
                return false;
            }
            $cmd = $this->privCmds[$command];
        }
	    $args = $cmd->cmdArgs->parse($text);
	    return new Request($args, $cmd);
    }

    /**
     * Loads all commands from an object by looking for attributes on public methods
     * @throws OptAlreadyDefined|SyntaxException
     */
    function loadMethods(object $obj): void {
	    $objRef = new \ReflectionObject($obj);
	    foreach($objRef->getMethods(\ReflectionMethod::IS_PUBLIC) as $rf) {
            $this->attrAddCmd($rf, [$obj, $rf->name]);
        }
    }

    /**
     * @throws OptAlreadyDefined
     * @throws SyntaxException
     */
    protected function attrAddCmd($rf, $f): void
    {
        $cmdAttr = $rf->getAttributes(attributes\Cmd::class);
        $privCmdAttr = $rf->getAttributes(attributes\PrivCmd::class);
        $pub = false;
        $priv = false;
        if (count($cmdAttr) > 0)
            $pub = true;
        if (count($privCmdAttr) > 0)
            $priv = true;
        if (!$pub && !$priv) {
            return;
        }
        if($pub)
            $cmdAttr = $cmdAttr[0]->newInstance();
        if($priv)
            $privCmdAttr = $privCmdAttr[0]->newInstance();
        $syntaxAttr = $rf->getAttributes(attributes\Syntax::class);
        $syntax = '';
        $callWrapperPre = [];
        $callWrapperPost = [];
        if (isset($syntaxAttr[0])) {
            $sa = $syntaxAttr[0]->newInstance();
            $syntax = $sa->syntax;
        }

        $callWrapAttr = $rf->getAttributes(attributes\CallWrap::class);
        if ($cw = ($callWrapAttr[0]??null)?->newInstance()) {
            $callWrapperPre = [...$cw->preArgs, $f];
            $f = $cw->caller;
            $callWrapperPost = $cw->postArgs;
        }

        $optionsAttr = $rf->getAttributes(attributes\Options::class);
        $opts = [];
        if(isset($optionsAttr[0])) {
            $options = $optionsAttr[0]->newInstance()->options;
            foreach ($options as $opt) {
                if(isset($opts[$opt]))
                    throw new OptAlreadyDefined($opt);
                $opts[$opt] = new Option($opt, "No description");
            }
        }

        $optionAttr = $rf->getAttributes(attributes\Option::class);
        foreach ($optionAttr as $attr) {
            $opt = $attr->newInstance();
            foreach ($opt->options as $v) {
                if(isset($opts[$v]))
                    throw new OptAlreadyDefined($v);
                $opts[$v] = new Option($v, $opt->desc);
            }
        }

        $attrMiddleware = $this->collectAttrMiddleware($rf);

        $desc = "No description";
        $descAttr = $rf->getAttributes(attributes\Desc::class);
        if(isset($descAttr[0]))
            $desc = $descAttr[0]->newInstance()->desc;

        if($pub) {
            foreach ($cmdAttr->args as $command) {
                $this->cmds[$command] = new Cmd($command, $f, $callWrapperPre, $callWrapperPost, $syntax, $opts, $desc, $attrMiddleware);
            }
        }
        if($priv) {
            foreach ($privCmdAttr->args as $command) {
                $this->privCmds[$command] = new Cmd($command, $f, $callWrapperPre, $callWrapperPost, $syntax, $opts, $desc, $attrMiddleware);
            }
        }
    }

    /**
     * Collects middleware declarations from attributes on a function or
     * method. #[CmdMiddleware] instances are collected first, then any other
     * attribute implementing MiddlewareAttribute (excluding CmdMiddleware
     * itself to avoid double-collection).
     * @param \ReflectionFunctionAbstract $rf
     * @return array<int, array{name: string, args: array}>
     */
    protected function collectAttrMiddleware(\ReflectionFunctionAbstract $rf): array
    {
        $attrMiddleware = [];
        foreach ($rf->getAttributes(attributes\CmdMiddleware::class) as $attr) {
            $m = $attr->newInstance();
            $attrMiddleware[] = ['name' => $m->name(), 'args' => $m->args()];
        }
        foreach ($rf->getAttributes() as $attr) {
            if (!is_subclass_of($attr->getName(), MiddlewareAttribute::class)) {
                continue;
            }
            if ($attr->getName() === attributes\CmdMiddleware::class) {
                continue; // already collected above
            }
            $m = $attr->newInstance();
            $attrMiddleware[] = ['name' => $m->name(), 'args' => $m->args()];
        }
        return $attrMiddleware;
    }

    /**
     * Resolves an attribute middleware declaration to its registered
     * callable. Aliases are resolved at call time so commands may be loaded
     * before their middleware aliases are registered.
     * @param array{name: string, args: array} $mw
     */
    protected function resolveMiddleware(array $mw): callable
    {
        if (!isset($this->middlewareAliases[$mw['name']])) {
            throw new exceptions\MiddlewareNotFound("middleware '{$mw['name']}' is not registered");
        }
        return $this->middlewareAliases[$mw['name']];
    }

    /**
     * Loads commands by looking for attributes on all currently defined functions, namespaced function load correctly as well
     * @throws \ReflectionException
     * @throws OptAlreadyDefined
     * @throws SyntaxException
     */
    function loadFuncs(): void {
	    $funcs = get_defined_functions(true)["user"];
	    foreach ($funcs as $f) {
	        $rf = new \ReflectionFunction($f);
	        $this->attrAddCmd($rf, $f);
        }
    }

    /**
     * Checks if a public command exists
     * @param string $command
     * @return bool
     */
    function cmdExists(string $command): bool {
        return isset($this->cmds[$command]);
    }

    /**
     * Checks if a private command exists
     * @param string $command
     * @return bool
     */
    function cmdExistsPriv(string $command): bool {
        return isset($this->privCmds[$command]);
    }

    /**
     * Runs the middleware chain around the command invocation. $entries is a
     * list of [callable $middleware, array $args] pairs in run order.
     * @param Request $req
     * @param array<int, mixed> $extraArgs
     * @param array<int, array{0: callable, 1: array}> $entries
     */
    protected function runPipeline(Request $req, array $extraArgs, array $entries): mixed
    {
        $invoke = fn(Request $r): mixed => $r->cmd->call(...[...$r->cmd->preArgs, ...$r->cmd->postArgs, ...$r->extraArgs, $r->args]);
        $req->extraArgs = $extraArgs;
        foreach (array_reverse($entries) as [$middleware, $args]) {
            $next = $invoke;
            $invoke = fn(Request $r): mixed => $middleware($r, $next, ...$args);
        }
        return $invoke($req);
    }

    /**
     * Calls the function/method for a public command
     * @param string $command Command name
     * @param string $text Text passed to the command
     * @param mixed $extraArgs,... Any additional arguments to pass
     * @return mixed Returns whatever the function/method did
     * @throws CmdNotFound
     */
    function call(string $command, string $text, ...$extraArgs) {
	    $req = $this->get($command, $text);
	    if(!$req)
	        throw new CmdNotFound($command);
        $entries = [];
        foreach ($this->globalMiddleware as $mw) { $entries[] = [$mw, []]; }
        foreach ($this->cmdMiddleware[strtolower($command)] ?? [] as $mw) { $entries[] = [$mw, []]; }
        foreach ($req->cmd->attrMiddleware ?? [] as $mw) { $entries[] = [$this->resolveMiddleware($mw), $mw['args']]; }
        if ($entries === []) {
            return $req->cmd->call(...[...$req->cmd->preArgs, ...$req->cmd->postArgs, ...$extraArgs, $req->args]);
        }
        return $this->runPipeline($req, $extraArgs, $entries);
        //return call_user_func_array($req->cmd->method, [...$req->cmd->preArgs, ...$req->cmd->postArgs, ...$extraArgs, $req]);
    }

    /**
     * Calls the function/method for a private command
     * @param string $command Command name
     * @param string $text Text passed to the command
     * @param mixed $extraArgs,... Any additional arguments to pass
     * @return mixed Returns whatever the function/method did
     * @throws CmdNotFound
     */
    function callPriv(string $command, string $text, ...$extraArgs) {
        $req = $this->get($command, $text, priv: true);
        if(!$req)
            throw new CmdNotFound($command);
        $entries = [];
        foreach ($this->globalMiddleware as $mw) { $entries[] = [$mw, []]; }
        foreach ($this->cmdMiddleware[strtolower($command)] ?? [] as $mw) { $entries[] = [$mw, []]; }
        foreach ($req->cmd->attrMiddleware ?? [] as $mw) { $entries[] = [$this->resolveMiddleware($mw), $mw['args']]; }
        if ($entries === []) {
            return $req->cmd->call(...[...$req->cmd->preArgs, ...$req->cmd->postArgs, ...$extraArgs, $req->args]);
        }
        return $this->runPipeline($req, $extraArgs, $entries);
    }

}
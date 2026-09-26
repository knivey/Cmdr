<?php
namespace knivey\cmdr;

use Closure;
use knivey\cmdr\exceptions\SyntaxException;

/**
 * @template TReturn
 */
class Cmd
{
    readonly public Args $cmdArgs;
    /**
     * @var Closure(): TReturn
     */
    readonly public Closure $method;
    /**
     * Middleware declarations collected from attributes at load time.
     * Aliases are resolved lazily at call time via Cmdr::resolveMiddleware().
     * @var array<int, array{name: string, args: array}>
     */
    public array $attrMiddleware = [];

    /**
     * @param string $command
     * @param callable(): TReturn $method
     * @param array $preArgs
     * @param array $postArgs
     * @param string $syntax
     * @param Option[] $opts
     * @param string $desc
     * @param array<int, array{name: string, args: array}> $attrMiddleware
     * @throws SyntaxException
     */
    public function __construct(
        readonly public string $command,
        callable $method,
        public array $preArgs,
        public array $postArgs,
        public string $syntax,
        public array $opts,
        public string $desc = "No description",
        array $attrMiddleware = [],
    )
    {
        $this->method = $method(...);
        $this->cmdArgs = new Args($syntax, $opts);
        $this->attrMiddleware = $attrMiddleware;
    }

    /**
     * @param ...$args
     * @return TReturn
     */
    function call(...$args) {
        return call_user_func_array($this->method, $args);
    }

    function __toString(): string
    {
        $out = trim($this->command . " " . $this->cmdArgs->syntax) . "\n";
        foreach ($this->opts as $opt) {
            $out .= "    $opt->option $opt->desc\n";
        }
        $out .= $this->desc;
        return rtrim($out, "\n");
    }
}

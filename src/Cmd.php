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
     * @param string $command
     * @param callable(): TReturn $method
     * @param array $preArgs
     * @param array $postArgs
     * @param string $syntax
     * @param Option[] $opts
     * @param string $desc
     * @throws SyntaxException
     */
    public function __construct(
        readonly public string $command,
        callable $method,
        public array $preArgs,
        public array $postArgs,
        public string $syntax,
        public array $opts,
        public string $desc = "No description"
    )
    {
        $this->method = $method(...);
        $this->cmdArgs = new Args($syntax, $opts);
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

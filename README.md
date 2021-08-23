[![Continuous Integration](https://github.com/knivey/Cmdr/actions/workflows/ci.yml/badge.svg)](https://github.com/knivey/Cmdr/actions/workflows/ci.yml)
# Cmdr
`knivey/cmdr` is command router designed for use on chat bots.

### Requirements
* PHP >= 8.0
### Features
* Arguments parsed/validated and easily accessed according to a syntax defined for command
* Uses function attributes to register functions for commands
* Can setup a wrapper function with `CallWrap` attribute (helpful for using something like `\Amp\asyncCall`)

## Installation
```bash
composer require knivey/cmdr
```

## Documentation & Examples

Setup the command router object and find commands

```php
use knivey\cmdr;
$router = new cmdr\Cmdr();

//All command functions will have Request as the last argument
#[cmdr\attributes\Cmd("example", "altname")] //define as many cmds for this function as you want
#[cmdr\attributes\PrivCmd("example", "altname")] //private message command
#[cmdr\attributes\Syntax("<required> [optional]")]
#[cmdr\attributes\Options("--option", "--anotheroption")]
#[cmdr\attributes\Option("--optionb", "description of option")]
#[cmdr\attributes\Option("--optionc", "description of option")]
#[cmdr\attributes\Desc("description of cmd")]
function exampleFunc($additonal, $arguments, cmdr\Request $request) {
    echo $request->args["required"];
    if(isset($request->args["optional"]))
        echo $request->args["optional"];
    if($request->getOpt('--option'))
        echo "--option was used";
}

//Do this AFTER all functions you want to load are defined
$router->loadFuncs();

// This will return what the command function returns
// Exceptions can be throw if the args given don't pass the syntax
$router->call('example', 'arguments given to cmd', "additional", "arguments");
```
Note that command names cannot contain a # in them.

To load public methods from a class object use `loadMethods($obj)`
### Argument Syntax Rules
*  `<arg>` is a required arg
*  `<arg>...` required multiword arg, must be last in list
*  `[arg]` is an optional arg, all optionals must be at the end and no multiword arguments preceed them
*  `[arg]...` optional multiword arg, must be last in list
* The arg name must not contain `[]<>` or whitespace

### CallWrap

```php
use knivey\cmdr;

#[cmdr\attributes\Cmd("example")]
//The callable for the cmd function is given to wrapper
#[cmdr\attributes\CallWrap("\Amp\asyncCall")]
function example(cmdr\Request $request) {
}

#[cmdr\attributes\Cmd("example2")]
//You can give additional arguments to the wrapper
//pre is before the callable post is after
#[cmdr\attributes\CallWrap("someWrapperFunc", preArgs: [1,2,3], postArgs: ['stuff'])]
function example2(cmdr\Request $request) {
}

function someWrapperFunc($one, $two, $three, callable $func, $stuff, cmdr\Request $request) {
    return $func($request);
}
```
## TODO
 * validation in the argument dsl, have many built in, allow adding, validator per arg, validator can also act like a caster Ex bool will cast to bool php type
   * \<arg name:validations>
   * \<width:uint max=100 min=50> args=val must NOT have any spaces, arg without =val will just be true
   * \<operation:(add|del | list)> option list (trim()ed)
   * \<blah:bool> accepts on off etc casts into a bool
   * \<blah:custom arg=val> custom validator, and caster if defined, arguments must match function argument names

validation can have a custom validator defined by using attributes
also have a cast function that attaches to that validator
custom validators should try to keep all their args optional
first arg will always be val
filters may use the same arg names as their validator but not required to
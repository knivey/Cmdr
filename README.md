[![Continuous Integration](https://github.com/knivey/Cmdr/actions/workflows/ci.yml/badge.svg)](https://github.com/knivey/Cmdr/actions/workflows/ci.yml)
# Cmdr
`knivey/cmdr` is command router designed for use on chatbots.

### Requirements
* PHP >= 8.2
### Features
* Arguments parsed/validated and easily accessed according to a syntax defined for command
* Uses function attributes to register functions for commands
* Can set up a wrapper function with `CallWrap` attribute (helpful for using something like `\Amp\asyncCall`)

## Installation
```bash
composer require knivey/cmdr
```

## Documentation & Examples

Set up the command router object and find commands

```php
use knivey\cmdr;
$router = new cmdr\Cmdr();

//All command functions will have Request as the last argument
//command names must not contain a # or space
#[cmdr\attributes\Cmd("example", "altname")] //define as many cmds for this function as you want
#[cmdr\attributes\PrivCmd("example", "altname")] //private message command
#[cmdr\attributes\Syntax("<required> [optional]")]
#[cmdr\attributes\Options("--option", "--anotheroption")] //Options will have No description
#[cmdr\attributes\Option("--optionb", "description of option")]
#[cmdr\attributes\Option("--optionc", "description of option")]
#[cmdr\attributes\Desc("description of cmd")]
function exampleFunc($additonal, $arguments, cmdr\Args $request) {
    echo $request->args["required"];
    if(isset($request->args["optional"]))
        echo $request->args["optional"];
    if($request->optEnabled('--option'))
        echo "--option was used";
}

#[cmdr\attributes\Cmd("example2")]
#[cmdr\attributes\Desc("Second example command")]
#[cmdr\attributes\Syntax("[args]...")] //the ... means it will eat the rest of the arguments "blah blah etc.."
#[cmdr\attributes\Option("--option", "enable some option")]
function exampleFunc2($additonal, $arguments, cmdr\Args $request) {
    // example2 blah blah --option=value
    // example2 blah --option=value blah (args will just have "blah blah")
    // if just --option then its val is true
    if($val = $request->getOpt('--option')) {
        echo "--option was set to $val";
    }
}

#[cmdr\attributes\PrivCmd("example2")]
#[cmdr\attributes\Desc("Command for private message example command")]
function exampleFunc2($additonal, $arguments, cmdr\Args $request) {
}

//Do this AFTER all functions you want to load are defined
$router->loadFuncs();
//If you have objects you use
$router->loadMethods($object);

// This will return what the command function returns
// Exceptions will be thrown if the args given don't pass the syntax
// Exception also thrown if the command doesn't exist
$router->call('example', 'arguments given to cmd', "additional", "arguments");

//simple example of a fictional IRC chatbot using triggers (!cmd)
function handleMsg(IRCuser $user, string $target, string $text) {
    global $router;
    if(isChan($target)) {
        $trigger = "!";
        if(substr($text, 0, 1) != $trigger)
            return;
        $text = substr($text, 1);
    }
    $text = explode(' ', $text);
    $cmd = array_shift($text);
    $text = implode(' ', $text);
    if(isChan($target)) {
        if($router->cmdExists($cmd)) {
            try {
                return $router->call($cmd, $text, $user);
            } catch (Exception $e) {
                //Exception may be bad arguments etc..., tell the user
                notice($nick, $e->getMessage());
            }
        }
    } else {
        if($router->cmdExistsPriv($cmd)) {
            try {
                return $router->callPriv($cmd, $text, $user);
            } catch (Exception $e) {
                notice($nick, $e->getMessage());
            }
        }
    }
}
```
**Note that command names cannot contain a # or a space in them.**

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

function someWrapperFunc($one, $two, $three, callable $func, $stuff, cmdr\Args $request) {
    return $func($request);
}
```
## Argument validators (WIP)
You can specify some simple validations in the argument DSL.

Validators can also filter/normalize inputs, for example bool can take "yes" and set that as true.

### Syntax
```
<arg_name: validation_type validator_arg=value validator_arg2=value>
```
* Arguments can only have one validation applied to them.
* The Validate class provides some default validators and custom validators can be registsered.
* Validator args must not have spaces, `<arg: int min = 0>` would be an error.
### Built in validators
#### uint, int, number
These all do what you would expect, check for types of numeric values.

They take two optional arguments `min` and `max`. 

They will cast to the appropriate PHP type, for int or uint that is `int` numeric could be `int` or `float`.


Example:
```
<id: uint>
```
```
<width: uint max=120 min=20>
```

#### bool
Can take "yes", "on", "true", "no", "off", "false" and returns a PHP `bool`

#### options/choices
This one lets you limit the argument to a few choices. To use an optional you only need to put options in parentheses with a | to separate choices.

Example:
```
<subcmd: (add|del|list)>
<subcmd: (add | del | list)> functions the same as above
```


### Custom validators and filters
You can set your own custom validators:
```php
Validate::setValidator("even", function(string $val) {
    if (!Validate::int($val))
        return false;
    return $val%2==0;
});
```

```
<arg: even>
```

You can set filters, these are what normalize values for you.
```php
Validate::setValidator("upper", fn ($val) => true);
Validate::setFilter("upper", function($val, $moo=false) {
    if($moo) return strtoupper($val);
    return $val;
});
```
```
<arg: upper moo>
<arg: upper>
<arg: upper bar> //invalid, validator arguments must match function argument names
```


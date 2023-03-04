<?php
namespace knivey\cmdr\exceptions;

/**
 * thrown if trying to redefine a command that was already loaded
 */
class CmdAlreadyExists extends \Exception {}
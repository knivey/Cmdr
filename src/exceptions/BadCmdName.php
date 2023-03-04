<?php
namespace knivey\cmdr\exceptions;

/**
 * Throw if trying to define a command with invalid naming (spaces and #)
 */
class BadCmdName extends \Exception {}
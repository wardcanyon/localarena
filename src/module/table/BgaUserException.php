<?php

// `BgaUserException` should be used for situations where nothing
// technical has gone wrong, but e.g. the user has done something
// invalid.
class BgaUserException extends Exception
{
  function __construct($message = null, $code = 0, Exception $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }
}

<?php

// `BgaVisibleSystemException` should be used when something internal
// has gone wrong.  In the rare cases where the message might contain
// sensitive information, use `BgaSystemException` instead.
class BgaVisibleSystemException extends Exception
{
  function __construct($message = null, $code = 0, Exception $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }
}

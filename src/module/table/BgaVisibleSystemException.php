<?php

class BgaVisibleSystemException extends Exception
{
    function __construct($message = null, $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

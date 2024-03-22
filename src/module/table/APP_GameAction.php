<?php 


define("AT_posint",1);
define("AT_alphanum",2);

/**
 * Class APP_GameAction
 * @property array viewArgs
 * @property Table game
 * @property string view
 */
class APP_GameAction
{

    /**
     * @param string $arg
     * @return bool
     */
    function isArg($arg)
    {
        return isset($this->params[$arg]);
    }

    /**
     * @param string $message
     */
    function trace($message)
    {

    }

    /**
     * @param string $arg
     * @param string $type
     * @param bool $required
     * @return mixed
     */
    function getArg($arg, $type, $required=false)
    {
        if(isset($this->params[$arg]))
        {
            if($type == AT_posint)
            {
                return intval($this->params[$arg]);
            }
            else
            {
                return $this->params[$arg];
            }
        }
        else
        {
            if($required)
            {
                throw feException("Parameter ".$arg." not found");
            }                
        }        
        return '';
    }
    
    
    /**
     *
     */
    function setAjaxMode()
    {

    }

    /**
     *
     */
    function ajaxResponse()
    {

    }

}
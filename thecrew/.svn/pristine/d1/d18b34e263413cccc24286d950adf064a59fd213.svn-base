<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * thecrew implementation : © Nicolas Gocel <nicolas.gocel@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 *
 * thecrew.action.php
 *
 * thecrew main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/thecrew/thecrew/myAction.html", ...)
 *
 */


class action_thecrew extends APP_GameAction
{
    // Constructor: please do not modify
    public function __default()
    {
        if( self::isArg( 'notifwindow') )
        {
            $this->view = "common_notifwindow";
            $this->viewArgs['table'] = self::getArg( "table", AT_posint, true );
        }
        else
        {
            $this->view = "thecrew_thecrew";
            self::trace( "Complete reinitialization of board game" );
        }
    }
    
    // TODO: defines your action entry points there
    
 
    
    public function actChooseTask()
    {
        self::setAjaxMode();
        
        $taskId = self::getArg( "taskId", AT_posint, true );
        $this->game->actChooseTask( $taskId);
        
        self::ajaxResponse( );
    }
    
    public function actPickCrew()
    {
        self::setAjaxMode();
        
        $crewId = self::getArg( "crewId", AT_posint, true );
        $this->game->actPickCrew( $crewId);
        
        self::ajaxResponse( );
    }
    
    public function actPlayCard()
    {
        self::setAjaxMode();
        
        $cardId = self::getArg( "cardId", AT_posint, true );
        $this->game->actPlayCard( $cardId);
        
        self::ajaxResponse( );
    }
    
    public function actFinishComm()
    {
        self::setAjaxMode();
        
        $place = self::getArg( "place", AT_alphanum, true );
        $this->game->actFinishComm( $place);
        
        self::ajaxResponse( );
    }
    
    public function actStartComm()
    {
        self::setAjaxMode();
        $this->game->actStartComm();
        self::ajaxResponse( );
    }
    
    public function actCancel()
    {
        self::setAjaxMode();
        $this->game->actCancel();
        self::ajaxResponse( );
    }
    public function actDistress()
    {
        self::setAjaxMode();
        $this->game->actDistress();
        self::ajaxResponse( );
    }
    
    public function actButton()
    {
        self::setAjaxMode();
        
        $choice = self::getArg( "choice", AT_alphanum, true );
        $this->game->actButton($choice);
        
        self::ajaxResponse( );
    }
    
    public function actMultiSelect()
    {
        self::setAjaxMode();
        
        $id1 = self::getArg( "id1", AT_alphanum, true );
        $id2 = self::getArg( "id2", AT_alphanum, true );
        $this->game->actMultiSelect($id1 ,$id2);
        
        self::ajaxResponse( );
    }
    
}



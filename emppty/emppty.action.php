<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Chakra implementation : © Nicolas Gocel <nicolas.gocel@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 * 
 * chakra.action.php
 *
 * Chakra main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/chakra/chakra/myAction.html", ...)
 *
 */
  
  
  class action_emppty extends APP_GameAction
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
            $this->view = "chakra_chakra";
            self::trace( "Complete reinitialization of board game" );
      }
  	} 
  	
  	
    public function actTake()
    {
        self::setAjaxMode();     

        $energyIds = self::getArg( "energyIds", AT_alphanum, true );
        $row = self::getArg( "row", AT_posint, true );
        
        $this->game->actTake( array_filter(explode(" ", $energyIds)), $row);

        self::ajaxResponse( );
    }
    
    public function actChannel()
    {
        self::setAjaxMode();
        
        $id = self::getArg( "id", AT_posint, true );
        $this->game->actChannel( $id);
        
        self::ajaxResponse( );
    }
    
    public function actMove()
    {
        self::setAjaxMode();
        
        $energyId = self::getArg( "energyId", AT_posint, true );
        $row = self::getArg( "row", AT_posint );
        $this->game->actMove( $energyId, $row);
        
        self::ajaxResponse( );
    }
    
    public function actColor()
    {
        self::setAjaxMode();
        
        $color = self::getArg( "color", AT_alphanum, true );
        $this->game->actColor( $color);
        
        self::ajaxResponse( );
    }    
    
    public function actCancel()
    {
        self::setAjaxMode();
        
        $this->game->actCancel();
        
        self::ajaxResponse( );
    }
    

  }
  


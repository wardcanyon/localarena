<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * thecrew implementation : © Nicolas Gocel <nicolas.gocel@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * thecrew.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 **/

class THCCheck5 extends THCCheck
{
    function __construct($cards )
    {
        parent::__construct($cards);
    }
    
    function specialCheck()
    {
        $specialOne =  $this->thecrew->getGameStateValue( 'special_id');
        $won = thecrew::getUniqueValueFromDB( "SELECT player_trick_number FROM player where player_id = ".$specialOne)>0;
                    
         if($won)
         {
             $this->missionFailed();
             
        }
        else if($this->isLastTrick())
        {            
            $this->missionSuccess();
        }
        else
        {
            //otherwise we continue
            $this->thecrew->setGameStateValue( 'mission_finished', 0 );
        }
            
        return true;        
    }
}
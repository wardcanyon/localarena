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

class THCCheck46 extends THCCheck
{
    function __construct($cards )
    {
        parent::__construct($cards);
    }
    
    function specialCheck()
    {       
        
        $target = $this->thecrew->getGameStateValue( 'challenge') == 2?8:9;
        $specialId =  $this->thecrew->getGameStateValue( 'special_id');
        $pinkGood =  thecrew::getUniqueValueFromDB("SELECT count(*) FROM card where card_type='3' and card_location like 'trick%' and card_location_arg=".$specialId);
        $pinkBad =  thecrew::getUniqueValueFromDB("SELECT count(*) FROM card where card_type='3' and card_location like 'trick%' and card_location_arg<>".$specialId);
        
        if($pinkGood == $target)
         {
             $this->missionSuccess();             
        }
        else if($this->isLastTrick() || $pinkBad>0)
        {            
            $this->missionFailed();
        }
        else
        {
            //otherwise we continue
            $this->thecrew->setGameStateValue( 'mission_finished', 0 );
        }
            
        return true;        
    }
}
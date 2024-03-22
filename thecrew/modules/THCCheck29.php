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

class THCCheck29 extends THCCheck
{
    function __construct($cards )
    {
        parent::__construct($cards);
    }
    
    function specialCheck()
    {
        
        $min = thecrew::getUniqueValueFromDB("SELECT min(player_trick_number) FROM player");
        $max = thecrew::getUniqueValueFromDB("SELECT max(player_trick_number) FROM player");          
        
        if($min + 2 < $max)
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
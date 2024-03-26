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

class THCCheck44 extends THCCheck
{
    function __construct($cards )
    {
        parent::__construct($cards);
    }
    
    function specialCheck()
    {        
        $trickCOunt =  $this->thecrew->getGameStateValue( 'trick_count');
        $totalTrump = 0;
        $fail = false;
        $start = $this->thecrew->getGameStateValue( 'challenge') == 2?2:1;
        
        for($i=1;$i<=$trickCOunt;$i++)
        {
            $max = thecrew::getUniqueValueFromDB("SELECT COALESCE(MAX(card_type_arg),0) FROM card where card_type='5' and card_location = 'trick".$i."'");
            
            if($max != 0)
            {
                if($max == $start)
                {
                    $start = $max+1;
                }
                else
                {
                    $fail = true;                    
                }
            }
        }        
        
        if(!$fail && $start == 5)
         {
             $this->missionSuccess();             
        }
        else if($fail || $this->isLastTrick())
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
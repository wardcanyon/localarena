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

class THCCheck13 extends THCCheck
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
        
        for($i=1;$i<=$trickCOunt;$i++)
        {
            $nbTrump = count($this->thecrew->cards->getCardsOfTypeInLocation(5,null,'trick'.$i));
            $totalTrump += $nbTrump;
            if($nbTrump>1)
            {
                $fail = true;
            }
        }        
                    
        $total = $this->thecrew->getGameStateValue( 'challenge') == 2?3:4;
        
        if(!$fail && $totalTrump == $total)
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
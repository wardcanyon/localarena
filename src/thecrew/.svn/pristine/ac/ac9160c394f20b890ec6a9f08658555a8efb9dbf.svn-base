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

class THCCheck50 extends THCCheck
{
    function __construct($cards )
    {
        parent::__construct($cards);
    }
    
    function specialCheck()
    {               
        $trickCOunt =  $this->thecrew->getGameStateValue( 'trick_count');
        $specialId =  $this->thecrew->getGameStateValue( 'special_id');
        $specialId2 =  $this->thecrew->getGameStateValue( 'special_id2');
        $nbCards = $this->thecrew->getGameStateValue( 'challenge') == 2?30:40;
        $nbPlayers = thecrew::getUniqueValueFromDB("SELECT count(*) from player");
        $nbTricks = intdiv($nbCards, $nbPlayers);
        
        $fail = false;
        for($i=1;$i<=$trickCOunt && $i<=4 && !$fail;$i++)
        {
            $winnerId = thecrew::getUniqueValueFromDB("SELECT card_location_arg FROM card where card_location = 'trick".$i."' limit 1");// NOI18N
            if($winnerId != $specialId)
            {
                $fail = true;
            }
        }
        
        for($i=5;$i<=$trickCOunt && $i<=$nbTricks-1 && !$fail;$i++)
        {
            $winnerId = thecrew::getUniqueValueFromDB("SELECT card_location_arg FROM card where card_location = 'trick".$i."' limit 1");// NOI18N
            if($winnerId == $specialId || $winnerId == $specialId2)
            {
                $fail = true;
            }
        }
        
        
        for($i=$nbTricks-1;$i<=$trickCOunt && $i<=$nbTricks && !$fail;$i++)
        {
            $winnerId = thecrew::getUniqueValueFromDB("SELECT card_location_arg FROM card where card_location = 'trick".$i."' limit 1");// NOI18N
            if($winnerId != $specialId2)
            {
                $fail = true;
            }
        }
        
        
        if(!$fail && $this->isLastTrick())
         {
             $this->missionSuccess();             
        }
        else if($fail)
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
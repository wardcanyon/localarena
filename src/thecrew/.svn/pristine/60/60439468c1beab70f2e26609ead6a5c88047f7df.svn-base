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

class THCCheck48 extends THCCheck
{
    function __construct($cards )
    {
        parent::__construct($cards);
    }
    
    function specialCheck()
    {       
        
        $sql = "SELECT task_id, player_id, status FROM task where token='o'";
        $task = $this->thecrew->getObjectFromDB( $sql );  
        
        $taskId = $this->thecrew->getGameStateValue( 'challenge') == 2?8:9;
        $specialId =  $this->thecrew->getGameStateValue( 'special_id');
        $pinkGood =  thecrew::getUniqueValueFromDB("SELECT count(*) FROM card where card_type='3' and card_location like 'trick%' and card_location_arg=".$specialId);
        $pinkBad =  thecrew::getUniqueValueFromDB("SELECT count(*) FROM card where card_type='3' and card_location like 'trick%' and card_location_arg<>".$specialId);
        
        if($this->isLastTrick() && thecrew::getUniqueValueFromDB("select count(*) from task where status <> 'ok'") == 0)// NOI18N
         {
             $this->missionSuccess();             
        }
        else if($task['status'] == 'ok' || thecrew::getUniqueValueFromDB("select count(*) from task where status = 'nok'") > 0)// NOI18N
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
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

class THCCheck
{    
    
    function __construct($thecrew )
    {
        $this->thecrew = $thecrew;
        $this->mission_finished = 0; //-1 : lost, 0: in progress, 1 : win
    }
    
    function check()
    {
        $trick_number = $this->thecrew->getGameStateValue( 'trick_count');
        $trick_winner = $this->thecrew->getGameStateValue( 'last_winner');
        
        
        $sql = "SELECT task_id, card_type, card_type_arg, token, player_id, status FROM task where status = 'tbd'";
        $tasks = $this->thecrew->getCollectionFromDb( $sql );
        
        $cards = $this->thecrew->cards->getCardsInLocation('trick'.$trick_number);
        
        //update task individually
        foreach($tasks as $task_id => $task) {
            foreach($cards as $card_id => $card)
            {
                if($task['card_type'] == $card['type'] && $task['card_type_arg'] == $card['type_arg'])
                {
                    $sql = "SELECT player_id, player_name FROM player where player_id = ".$task['player_id'];
                    $taskPlayer = $this->thecrew->getObjectFromDB( $sql );  
                    
                    $text = '';
                    if($task['player_id'] == $trick_winner)
                    {
                        $tasks[$task_id]['status'] = 'ok';
                        $text = clienttranslate('${player_name} fulfilled task ${value_symbol}${color_symbol}');
                    }
                    else
                    {
                        $tasks[$task_id]['status'] = 'nok';
                        $text = clienttranslate('${player_name} failed task ${value_symbol}${color_symbol}');
                    }
                    
                    $sql = "update task set trick = ".$trick_number.", status = '".$tasks[$task_id]['status']."' where task_id=".$task_id;
                    $this->thecrew->DbQuery( $sql );
                    
                    $this->thecrew->notifyAllPlayers('taskUpdate', $text, array(
                        'task' => $tasks[$task_id],
                        'player_id' => $taskPlayer['player_id'],
                        'player_name' => $taskPlayer['player_name'],
                        'value' => $task['card_type_arg'],
                        'value_symbol' => $task['card_type_arg'], // The substitution will be done in JS format_string_recursive function
                        'color' => $task['card_type'],
                        'color_symbol' => $task['card_type'] // The substitution will be done in JS format_string_recursive function
                    ));
                }
            }
        }
        
        //Update task according to token 1
        $sql = "SELECT task_id, trick, card_type, card_type_arg, token, player_id, status FROM task where token = '1' and status ='tbd'";
        $task1 =  thecrew::getObjectFromDB( $sql );
        if($task1 != null && thecrew::getUniqueValueFromDB("select count(*) from task where status = 'ok'") > 0)// NOI18N
        {
            $tasksMissed = array();
            $tasksMissed[$task1['task_id']] = $task1;
            $this->markMissed($tasksMissed);
        }
        
        //Update task according to token 2
        $sql = "SELECT task_id, trick, card_type, card_type_arg, token, player_id, status FROM task where token = '2' and status ='tbd'";
        $task1 =  thecrew::getObjectFromDB( $sql );
        if($task1 != null && thecrew::getUniqueValueFromDB("select count(*) from task where status = 'ok' and token <> '1'") > 0)// NOI18N
        {
            $tasksMissed = array();
            $tasksMissed[$task1['task_id']] = $task1;
            $this->markMissed($tasksMissed);
        }
        
        //Update task according to token 3
        $sql = "SELECT task_id, trick, card_type, card_type_arg, token, player_id, status FROM task where token = '3' and status ='tbd'";
        $task1 =  thecrew::getObjectFromDB( $sql );
        if($task1 != null && thecrew::getUniqueValueFromDB("select count(*) from task where status = 'ok' and token <> '1' and token <> '2' ") > 0)// NOI18N
        {
            $tasksMissed = array();
            $tasksMissed[$task1['task_id']] = $task1;
            $this->markMissed($tasksMissed);
        }
        
        //Update task according to token 4
        $sql = "SELECT task_id, trick, card_type, card_type_arg, token, player_id, status FROM task where token = '4' and status ='tbd'";
        $task1 =  thecrew::getObjectFromDB( $sql );
        if($task1 != null && thecrew::getUniqueValueFromDB("select count(*) from task where status = 'ok' and token <> '1' and token <> '2' and token <> '3'") > 0)// NOI18N
        {
            $tasksMissed = array();
            $tasksMissed[$task1['task_id']] = $task1;
            $this->markMissed($tasksMissed);
        }
        
        //Update task according to token 5
        $sql = "SELECT task_id, trick, card_type, card_type_arg, token, player_id, status FROM task where token = '5' and status ='tbd'";
        $task1 =  thecrew::getObjectFromDB( $sql );
        if($task1 != null && thecrew::getUniqueValueFromDB("select count(*) from task where status = 'ok' and token <> '1' and token <> '2' and token <> '3' and token <> '4'") > 0)// NOI18N
        {
            $tasksMissed = array();
            $tasksMissed[$task1['task_id']] = $task1;
            $this->markMissed($tasksMissed);
        }
        
        //Update task according to token > >> >>> >>>>
        $sql = "SELECT task_id, card_type, card_type_arg, token, player_id, status FROM task where token LIKE 'i%' and status = 'ok' order by token";
        $tasks = $this->thecrew->getCollectionFromDb( $sql ); 
        
        foreach($tasks as $task_id => $task) {
            $sql = "SELECT task_id, card_type, card_type_arg, token, player_id, status FROM task where token LIKE 'i%' and token < '".$task['token']."' and status = 'tbd'";
            $tasksMissed = $this->thecrew->getCollectionFromDb( $sql );  
                        
            $this->markMissed($tasksMissed);
        }
        
        //Update task according to token Omega
        $sql = "SELECT task_id, trick, card_type, card_type_arg, token, player_id, status FROM task where token = 'o' and status ='ok'";
        $task1 =  thecrew::getObjectFromDB( $sql );
        if($task1 != null)
        {
            $sql = "SELECT task_id, card_type, card_type_arg, token, player_id, status FROM task where status = 'tbd'";
            $tasksMissed = $this->thecrew->getCollectionFromDb( $sql );
            $this->markMissed($tasksMissed);
        }
        
        //update game
        if($this->specialCheck())
        {
            //nothing to do
        }
        else if(thecrew::getUniqueValueFromDB("select count(*) from task")>0 // NOI18N
            && thecrew::getUniqueValueFromDB("select count(*) from task where status <> 'ok'") == 0)// NOI18N
        {
            $this->missionSuccess();
        }
        else if(thecrew::getUniqueValueFromDB("select count(*) from task where status = 'nok'") > 0// NOI18N
            || $this->isLastTrick())
        {            
            $this->missionFailed();
        }
        else
        {
            //otherwise we continue
            $this->thecrew->setGameStateValue( 'mission_finished', 0 );
        }
    }
    
    function isLastTrick()
    {
        return $this->thecrew->cards->countCardInLocation('hand') <= 1;
    }
    
    function missionSuccess()
    {
        $this->thecrew->notifyAllPlayers('note', clienttranslate('Mission ${nb} completed'), array(
            'nb' =>  thecrew::getUniqueValueFromDB( "SELECT max(mission) FROM logbook")
        ));
        
        //all tasks are done
        $this->thecrew->setGameStateValue( 'mission_finished', 1 ); 
    }
    
    function missionFailed()
    {
        $this->thecrew->notifyAllPlayers('note', clienttranslate('Mission ${nb} failed'), array(
            'nb' => thecrew::getUniqueValueFromDB( "SELECT max(mission) FROM logbook")
        ));
        
        //1 task has failed or no more cards
        $this->thecrew->setGameStateValue( 'mission_finished', -1 );
    }
    
    function specialCheck()
    {
        return false;
    }
    
    function markMissed($tasksMissed)
    {
        foreach($tasksMissed as $taskmissed_id => $taskmissed) {
            $sql = "SELECT player_id, player_name FROM player where player_id = ".$taskmissed['player_id'];
            $taskPlayer = $this->thecrew->getObjectFromDB( $sql );
            
            $tasksMissed[$taskmissed_id]['status'] = 'nok';
            
            $sql = "update task set status = 'nok' where task_id = ".$taskmissed_id;
            $this->thecrew->DbQuery( $sql );
            
            $this->thecrew->notifyAllPlayers('taskUpdate', clienttranslate('${player_name} failed task ${value_symbol}${color_symbol}'), array(
                'task' => $tasksMissed[$taskmissed_id],
                'player_id' => $taskPlayer['player_id'],
                'player_name' => $taskPlayer['player_name'],
                'value' => $taskmissed['card_type_arg'],
                'value_symbol' => $taskmissed['card_type_arg'], // The substitution will be done in JS format_string_recursive function
                'color' => $taskmissed['card_type'],
                'color_symbol' => $taskmissed['card_type'] // The substitution will be done in JS format_string_recursive function
            ));
        }   
    }
    
    
}
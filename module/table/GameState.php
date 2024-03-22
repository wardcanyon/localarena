<?php 

/**
 * Class GameState
 */
class GameState {

    function __construct($game, $machinestates)
    {
        $this->game = $game;
        $this->machinestates = $machinestates;
    }
    
    /**
     * Not documented
     * @param $str
     */
    public function updateMultiactiveOrNextState($str)
    {
    }

    /**
     * You can call this method to make any player active.
     * Note: you CANT use this method in a "activeplayer" or "multipleactiveplayer" state. You must use a "game" type game state for this.
     *
     * @param $player_id
     */
    public function changeActivePlayer($player_id)
    {
        $this->game->setGameStateValue('activePlayerId', $player_id);
    }

    /**
     * With this method you can retrieve the list of the active player at any time.
     * During a "game" type gamestate, it will return a void array.
     * During a "activeplayer" type gamestate, it will return an array with one value (the active player id).
     * during a "multipleactiveplayer" type gamestate, it will return an array of the active players id.
     * Note: you should only use this method is the latter case.
     */
    public function getActivePlayerList()
    {
        $actives = array();
        $state = $this->state();
        switch($state['type'])
        {
            case "game":
            case "manager":
                //nothing to do
                break;
                
            case "activeplayer":
                $actives[] = $this->game->getActivePlayerId();
                break;
                
            case "multipleactiveplayer":
                $actives = $this->game->getObjectListFromDB("select player_is_multiactive from players", true);
                break;
        }
        
        return $actives;
    }

    /**
     * With this method, all playing players are made active.
     * Usually, you use this method at the beginning (ex: "st" action method) of a multiplayer game state when all players have to do some action.
     */
    public function setAllPlayersMultiactive()
    {
        $this->game->DbQuery("update players set player_is_multiactive = 1");
    }

    /**
     * Make a specific list of players active during a multiactive gamestate.
     * Bare in mind it doesn't deactivate other previously active players.
     * "players" is the array of player id that should be made active.
     * In case "players" is empty, the method trigger the "next_state" transition to go to the next game state.
     *
     * @param $players
     * @param $next_state
     */
    public function setPlayersMultiactive($players, $next_state)
    {
        if(count($players) == 0)
        {
            $this->nextState($next_state);
        }
        else
        {            
            $ids = implode( $players, ',' );
            $this->game->DbQuery("update players set player_is_multiactive = 0");
            $this->game->DbQuery("update players set player_is_multiactive = 1 where player_id in (".$ids.")");  
        }
    }

    /**
     * During a multiactive game state, make the specified player inactive.
     * Usually, you call this method during a multiactive game state after a player did his action.
     * If this player was the last active player, the method trigger the "next_state" transition to go to the next game state.
     *
     * @param $player_id
     * @param $next_state
     */
    public function setPlayerNonMultiactive($player_id, $next_state)
    {
        
        $this->game->DbQuery("update players set player_is_multiactive = 0 where player_id = ".$player_id); 
        if($this->game->getUniqueValueFromDB("select count(*) from players where player_is_multiactive = 1") == 0)
        {
            $this->nextState($next_state);
        }
    }

    /**
     * (rarely used)
     * This works exactly like "checkAction", except that it do NOT check if current player is active.
     * This is used specifically in certain game states when you want to authorize some additional actions for players that are not active at the moment.
     * Example: in Libertalia game, you want to authorize players to change their mind about card played. They are of course not active at the time they change their mind, so you cannot use "checkAction" and use "checkPossibleAction" instead.
     *
     * @param $action
     */
    public function checkPossibleAction($action, $bThrowException = TRUE)
    {
        $state = $this->state();
        if(!isset($state['possibleactions'][$action]))
        {
            if($bThrowException)
            {
                throw new feException('Impossible action "'.$action.'" at this state "'.$state['name'].'"');
            }
            else
            {
                return false;
            }
        }
        return true;
    }

    /**
     * Change current state to a new state. Important: parameter $transition is the name of the transition, and NOT the name of the target game state, see Your game state machine: states.inc.php for more information about states.
     *
     * @param $transition
     */
    public function nextState($transition)
    {
        $state = $this->state();
        if(!isset($state['transitions'][$transition]))
        {
            throw new feException('Impossible transition "'.$transition.'" at this state "'.$state['name'].'"');
        }
        $newStateId = $state['transitions'][$transition];
        $this->game->setGameStateValue('currentState', $newStateId);
        $this->game->enterState();
    }

    /**
     *Get an associative array of current game state attributes, see Your game state machine: states.inc.php for state attributes.
     * $state=$this->gamestate->state(); if( $state['name'] == 'myGameState' ) {...}
     * @return array
     */
    public function state()
    {
        $state_id = $this->game->getGameStateValue('currentState');
        return $this->machinestates[$state_id];
    }
    
}
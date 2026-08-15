<?php

/**
 * Class GameState
 */
class GameState
{
  // XXX: Eliminating dynamic-property deprecation notices; type
  // and document or remove.
  public $machinestates;
  public $game;

  function __construct($game, $machinestates)
  {
    $this->game = $game;
    $this->machinestates = $machinestates;
  }

  private function log($msg)
  {
    $this->game->log($msg);
  }

  /**
   * Not documented
   * @param $str
   */
  public function updateMultiactiveOrNextState($str)
  {
    throw new \feException('updateMultiactiveOrNextState(): not implemented');
  }

  /**
   * You can call this method to make any player active.
   * Note: you CANT use this method in a "activeplayer" or "multipleactiveplayer" state. You must use a "game" type game state for this.
   *
   * @param $player_id
   */
  public function changeActivePlayer(int $player_id): void
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
    $actives = [];
    $state = $this->state();
    switch ($state['type']) {
      case 'game':
      case 'manager':
        //nothing to do
        break;

      case 'activeplayer':
        $actives[] = $this->game->getActivePlayerId();
        break;

      case 'multipleactiveplayer':
        $actives = $this->game->getObjectListFromDB(
          'SELECT `player_id` FROM `player` WHERE `player_is_multiactive` = 1',
          true
        );
        break;
    }

    $this->log('getActivePlayerList(): ' . implode(', ', $actives));

    return $actives;
  }

  /**
   * With this method, all playing players are made active.
   * Usually, you use this method at the beginning (ex: "st" action method) of a multiplayer game state when all players have to do some action.
   */
  public function setAllPlayersMultiactive()
  {
    $this->game->DbQuery('UPDATE `player` SET `player_is_multiactive` = 1');

    $this->game->notify_gameStateMultipleActiveUpdate();
  }

  /**
   * Activate each of the players in $players.
   *
   * $bExclusive says what happens to players who are already
   * multiactive but are NOT in $players:
   *
   * - false (the default): they stay active, i.e. $players is ADDED to
   *   the set of active players;
   * - true: they are deactivated, so that the players multiactive at
   *   the end are exactly those in $players.
   *
   * Whether the $next_state transition is taken depends on the
   * ARGUMENT, not on the resulting flags: with a non-empty $players,
   * $next_state is ignored entirely (whatever is passed for it); with
   * an empty $players, the transition is taken.  Note that this means
   * an empty $players and `!$bExclusive` transitions onwards even
   * though the players who were already active still carry the flag.
   *
   * Returns true iff the state transition was taken.
   *
   * @param $players
   * @param $next_state
   * @param $bExclusive
   */
  public function setPlayersMultiactive($players, $next_state, bool $bExclusive = false): bool
  {
    if ($bExclusive) {
      // Deactivate all players (those in $players are reactivated
      // below), so that $players ends up being exactly the multiactive
      // set.
      $this->game->DbQuery('UPDATE `player` SET `player_is_multiactive` = 0');
    }

    if (count($players) > 0) {
      $ids = implode(',', $players);
      $this->game->DbQuery('UPDATE `player` SET `player_is_multiactive` = 1 WHERE `player_id` IN (' . $ids . ')');
    }

    // TODO: Check behavior against BGA.  Here, we send an update
    // notif even if $players is empty.
    $this->game->notify_gameStateMultipleActiveUpdate();

    if (count($players) == 0) {
      $this->nextState($next_state);
      return true;
    }

    return false;
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
    $this->log('setPlayerNonMultiactive(): ' . $player_id);

    // XXX: Should we throw an error if the player is not
    // currently multiactive?
    $this->game->DbQuery('UPDATE `player` SET `player_is_multiactive` = 0 WHERE `player_id` = ' . $player_id);
    if ($this->game->getUniqueValueFromDB('SELECT COUNT(*) FROM `player` WHERE `player_is_multiactive` = 1') == 0) {
      $this->log('setPlayerNonMultiactive(): no more multiactive players; transitioning to next state');
      $this->nextState($next_state);
    } else {
      // TODO: Check behavior of BGA for compatibility:
      // - Do we get an update when the last player becomes
      //   non-multiactive, before the "gameStateChange" notif?
      // - If multiple players are made non-multiactive in one
      //   turn, do we get multiple messages?
      $this->game->notify_gameStateMultipleActiveUpdate();
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
  public function checkPossibleAction($action, $bThrowException = true)
  {
    $state = $this->state();
    if (!isset($state['possibleactions'][$action])) {
      if ($bThrowException) {
        throw new feException('Impossible action "' . $action . '" at this state "' . $state['name'] . '"');
      } else {
        return false;
      }
    }
    return true;
  }

  /**
   * Change current state to a new state.  Important: the $stateNum
   * parameter is the KEY of the state (and NOT the name of a
   * transition, cf. `nextState()`); see Your game state machine:
   * states.inc.php for more information about states.
   *
   * Unlike `nextState()`, this does NOT consult the current state's
   * transitions: any state in the machine can be jumped to from
   * anywhere, whether or not an edge joins them.  That is the point of
   * the method -- but it is also why it should not be used in normal
   * cases.  Specific advanced cases include jumping to a specific state
   * from "do_anytime" actions, jumping to a dispatcher state, and
   * jumping to a recovery state from a zombie-player function.
   *
   * If $bWithActions is false, the target state is entered but its
   * "action" (st*) method is NOT run, so a "game"-type state jumped
   * into this way does not immediately cascade onwards; the machine
   * simply comes to rest there.  (The state-change notification is sent
   * either way, so clients always learn about the new state.)
   *
   * Like `nextState()`, this advances only the in-memory "live" state;
   * the persisted current-state global is flushed at the request
   * boundary.  See `nextState()` and `Table::flushCurrentStateGlobal()`.
   *
   * N.B.: BGA's documentation calls the first parameter $stateNum, but
   * its signature names it $nextState (see `_ide_helper.php`); we match
   * the signature, so that a game passing it by name still works.
   *
   * @param $nextState
   * @param $bWithActions
   */
  public function jumpToState(int $nextState, bool $bWithActions = true): void
  {
    if (!isset($this->machinestates[$nextState])) {
      throw new feException(
        'Cannot jump to state ' . $nextState . ': there is no such state in the game state machine.'
      );
    }

    $state = $this->state();
    $this->game->setLiveStateId($nextState);

    $this->log(
      'From state "' .
        $state['name'] .
        '", jumping to state "' .
        $this->machinestates[$nextState]['name'] .
        '"' .
        ($bWithActions ? '' : ' (without actions)') .
        '.'
    );

    $this->game->enterState($bWithActions);
  }

  /**
   * Change current state to a new state. Important: parameter $transition is the name of the transition, and NOT the name of the target game state, see Your game state machine: states.inc.php for more information about states.
   *
   * @param $transition
   */
  public function nextState($transition)
  {
    $state = $this->state();
    if (!isset($state['transitions'][$transition])) {
      throw new feException(
        'The transition "' . $transition . '" is not valid in the current state ("' . $state['name'] . '").'
      );
    }
    $newStateId = $state['transitions'][$transition];

    // Advance only the in-memory "live" state.  We deliberately do NOT
    // write the persisted current-state global here: real BGA leaves
    // that global pinned at the request's entry state for the whole
    // synchronous cascade and only advances it at the request
    // boundary.  LocalArena replicates that -- `Table::saveState()`
    // flushes the live state into the global once the action parks.
    // See `Table::$liveStateId_` and `Table::flushCurrentStateGlobal()`.
    $this->game->setLiveStateId($newStateId);

    $this->log(
      'From state "' .
        $state['name'] .
        '", taking transition "' .
        $transition .
        '" to state "' .
        $this->machinestates[$newStateId]['name'] .
        '".'
    );

    $this->game->enterState();
  }

  /**
   * Get an associative array of current game state attributes, see Your game state machine: states.inc.php for state attributes.
   * $state=$this->gamestate->state(); if( $state['name'] == 'myGameState' ) {...}
   *
   * This reflects the *live* in-memory state machine: it is updated
   * immediately by `nextState()`, so during an in-request cascade
   * through several "game"-type states it always names the state
   * currently being processed.  This is one of the two ways game code
   * can ask "what state am I in?"; the other is reading the
   * current-state global via `getGameStateValue()`, which -- matching
   * real BGA -- stays pinned at the request's entry state until the
   * request boundary.  See `Table::getCurrentStateId()` and
   * `Table::$liveStateId_`.
   *
   * @return array
   */
  public function state()
  {
      $state_id = $this->state_id();
    return $this->machinestates[$state_id];
  }

    /**
     * The id of the *live* state (see `state()`).  Updated immediately
     * by every `nextState()` transition.  NOT the same as
     * `getGameStateValue('currentState')` during a cascade, which lags.
     *
     * @return int
     */
    public function state_id()
    {
        return $this->game->getLiveStateId();
    }
}

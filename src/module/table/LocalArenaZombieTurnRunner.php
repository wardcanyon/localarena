<?php

/**
 * The control flow of "play for whoever the table is stuck waiting on".
 *
 * BGA marks a player who quits a table -- or is expelled from it for
 * running out of time -- a "zombie", and from then on plays for them:
 * whenever the game comes to rest on a zombie, the framework calls the
 * game's own `zombieTurn($state, $player_id)`.  This class is the part
 * of that with no database in it, so that it can be exercised by the
 * unit lane: deciding how many turns to run, in what order, and when to
 * stop.  `Table` supplies the three things it needs to know (see the
 * constructor) and owns everything about actual players and states.
 *
 * Two properties are what make this worth stating separately from the
 * plumbing:
 *
 * - Nothing read on one pass is trusted on the next.  A game's
 *   `zombieTurn()` transitions, so by the time it returns the machine
 *   is generally somewhere else, with a different set of players
 *   waiting -- possibly several states further on, possibly with
 *   another zombie now waiting.  So this asks "who is waiting?" afresh
 *   every time round, and plays exactly one turn per answer.
 *
 * - It is re-entrant.  Running a turn transitions, and a transition
 *   checks for zombies, so a run nests inside a run.  That is normal:
 *   it is how a zombie's turn leading into a state where another zombie
 *   waits gets both of them played for, synchronously, inside the
 *   original caller's `nextState()`.
 *
 * Which leaves the failure mode this has to defend against: a
 * `zombieTurn()` that does not make progress.  A game whose zombie
 * handling returns to the state it was called from with the same
 * player still waiting recurses; one that simply does nothing spins.
 * Either would run until the request died -- in a test suite, a hang
 * with nothing to look at.  Both are capped, and exceeding a cap
 * throws, naming the state, so the game bug reads as a test failure.
 */
class LocalArenaZombieTurnRunner
{
  // Both bounds are far above anything legitimate.  Nesting only grows
  // when one zombie's turn leads directly into a state where another
  // zombie is waiting, and one pass runs at most one turn per player
  // waiting in the state it is working on.
  const DEFAULT_MAX_DEPTH = 32;
  const DEFAULT_MAX_TURNS_PER_PASS = 32;

  /**
   * Returns the ids of the zombies the game is waiting on RIGHT NOW,
   * in the order they should be played for.  Consulted afresh on every
   * pass.
   *
   * @var callable(): array<int, int>
   */
  private $activeZombiePlayerIds_;

  /**
   * Plays one turn for the given player: on BGA, a call to the game's
   * `zombieTurn()`.  Expected to transition.
   *
   * @var callable(int): void
   */
  private $runTurn_;

  /**
   * The name of the state the machine is in right now; for error
   * messages only.
   *
   * @var callable(): string
   */
  private $stateName_;

  private int $max_depth_;
  private int $max_turns_per_pass_;

  // How many `run()` calls are on the stack.
  private int $depth_ = 0;

  public function __construct(
    callable $activeZombiePlayerIds,
    callable $runTurn,
    callable $stateName,
    int $max_depth = self::DEFAULT_MAX_DEPTH,
    int $max_turns_per_pass = self::DEFAULT_MAX_TURNS_PER_PASS
  ) {
    $this->activeZombiePlayerIds_ = $activeZombiePlayerIds;
    $this->runTurn_ = $runTurn;
    $this->stateName_ = $stateName;
    $this->max_depth_ = $max_depth;
    $this->max_turns_per_pass_ = $max_turns_per_pass;
  }

  /**
   * Plays out every zombie the game is currently waiting on, and every
   * zombie the resulting transitions run into, before returning.
   *
   * $context describes what triggered this pass (a state entry, the
   * stuck sweep); it appears in the runaway-turn errors, which are the
   * only thing it is used for.
   */
  public function run(string $context): void
  {
    if ($this->depth_ >= $this->max_depth_) {
      throw new feException(
        'Runaway zombie turns: ' .
          $this->max_depth_ .
          ' zombie turns are nested inside one another (most recently for ' .
          $context .
          ', in state "' .
          ($this->stateName_)() .
          '").  A zombieTurn() is transitioning into a state where a zombie is waiting again, without ever ' .
          'making progress.'
      );
    }

    $this->depth_++;
    try {
      for ($turns = 0; ; $turns++) {
        // Asked afresh every time round: the previous turn
        // transitioned out from under us.
        $player_ids = ($this->activeZombiePlayerIds_)();
        if (count($player_ids) === 0) {
          return;
        }

        if ($turns >= $this->max_turns_per_pass_) {
          throw new feException(
            'Runaway zombie turns: ' .
              $this->max_turns_per_pass_ .
              ' zombie turns have been run in a row while handling ' .
              $context .
              ', and player ' .
              $player_ids[0] .
              ' is STILL waiting in state "' .
              ($this->stateName_)() .
              '".  A zombieTurn() is leaving its player active, or returning to a state where they are.'
          );
        }

        // One turn at a time, re-checking in between: in a
        // "multipleactiveplayer" state each zombie's turn can change
        // who else is still waiting (and the last one to be
        // deactivated takes the state's transition).
        ($this->runTurn_)($player_ids[0]);
      }
    } finally {
      $this->depth_--;
    }
  }
}

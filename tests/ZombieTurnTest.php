<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/../module/test/IntegrationTestCase.php';

// We extend the bundled "localarenanoop" harness game, so load its
// class.  When a test supplies a `table_class`, TableManager::getTable()
// instantiates that class directly and does NOT require the game's
// .game.php file, so we must require it ourselves before subclassing.
require_once LOCALARENA_GAME_PATH . 'localarenanoop/localarenanoop.game.php';

/**
 * Tests for zombie ("abandoned") players.
 *
 * A BGA player who quits a table -- or is expelled from it for running
 * out of time -- is marked a zombie, and from then on the table stops
 * waiting on them: whenever the game comes to rest on a zombie, the
 * framework plays for them by calling the game's own
 * `zombieTurn($state, $player_id)`.
 *
 * Every published game has to implement that method, and in practice
 * it is among the least-exercised code any of them contains: nothing
 * an ordinary test does ever reaches it, so its defects are found in
 * production or not at all.  Making it reachable is the point of all
 * of this -- `IntegrationTestCase::setPlayerZombie()` is the switch,
 * and the tests below pin down what the framework does once it is
 * thrown.
 *
 * The property that most of these tests are really about is SYNCHRONY.
 * Real BGA runs the zombie's turn inside the transition that made them
 * active, so a single `nextState()` in game code can contain the
 * zombie's answer, whatever resumes on the strength of it, and the
 * rest of that player's turn -- all underneath a state action that has
 * not returned yet.  Bugs in this area are bugs in exactly that
 * re-entrancy, so an implementation that deferred zombie turns to the
 * next request would reproduce none of them.
 */
class ZombieTurnTest extends IntegrationTestCase
{
    const LOCALARENA_GAME_NAME = 'localarenanoop';

    // State ids used by the machine installed below.
    const ST_TURN = 2;      // activeplayer; the ordinary "waiting on someone" state
    const ST_HANDOFF = 3;   // game; passes the turn to the next player, then back to ST_TURN
    const ST_MULTI = 4;     // multipleactiveplayer; everyone acts at once
    const ST_PARK = 5;      // game; the machine comes to rest here, waiting on nobody
    const ST_LOOP = 6;      // activeplayer; its zombieTurn re-enters it (broken on purpose)
    const ST_STUCK = 7;     // activeplayer; its zombieTurn does nothing (broken on purpose)

    protected function defaultTableParams(): \LocalArena\TableParams
    {
        $params = parent::defaultTableParams();
        // Reuse all of localarenanoop's files (states/gameinfos/dbmodel/
        // action class etc.), but instantiate our zombie-aware subclass
        // instead of the plain game class.
        $params->table_class = ZombieTurnTestGame::class;
        return $params;
    }

    private function game(): ZombieTurnTestGame
    {
        return $this->table();
    }

    // The player ids, in seating order.
    private function playerId(int $index): int
    {
        return intval($this->playerByIndex($index)->id());
    }

    // Makes the given player the active player, without going through
    // a state transition -- so, deliberately, without the framework
    // getting a chance to notice that they may be a zombie.  This is
    // the situation `checkStuckedZombiePlayers()` exists for.
    private function givenActive(int $index): void
    {
        $this->game()->gamestate->changeActivePlayer($this->playerId($index));
    }

    // The multiactive players, as ints and in a stable order.
    private function multiactiveIds(): array
    {
        $ids = array_map('intval', $this->game()->gamestate->getActivePlayerList());
        sort($ids);
        return $ids;
    }

    // The zombie turns run so far, as "<state name>/<player id>"
    // strings in the order they were run.
    private function zombieTurns(): array
    {
        return array_map(
            fn($turn) => $turn['state'] . '/' . $turn['player_id'],
            $this->game()->zombie_turns
        );
    }

    // ==================== Storage ====================

    /**
     * `loadPlayersBasicInfos()` reports the flag, which is how a game
     * asks whether a player has abandoned the table: the conventional
     * `isPlayerZombie()` helper from BGA's game template reads it out
     * of that array, and must work here unmodified.
     */
    public function testLoadPlayersBasicInfosReportsTheZombieFlag(): void
    {
        $game = $this->game();

        // Nobody is a zombie by default, which is what keeps every
        // other test suite unaffected by this feature.
        foreach ($game->loadPlayersBasicInfos() as $player_id => $row) {
            $this->assertArrayHasKey('player_zombie', $row);
            $this->assertEquals(0, intval($row['player_zombie']), 'Players should not start out zombies.');
            $this->assertFalse($game->isPlayerZombie(intval($player_id)));
        }

        $this->setPlayerZombie($this->playerId(1));

        $players = $game->loadPlayersBasicInfos();
        $this->assertEquals(0, intval($players[$this->playerId(0)]['player_zombie']));
        $this->assertEquals(1, intval($players[$this->playerId(1)]['player_zombie']));
        $this->assertFalse($game->isPlayerZombie($this->playerId(0)));
        $this->assertTrue($game->isPlayerZombie($this->playerId(1)));
    }

    /**
     * The flag is reported to clients too, in the per-player data that
     * the game interface renders from.
     */
    public function testGamedatasReportTheZombieFlag(): void
    {
        $this->setPlayerZombie($this->playerId(1));

        $players = $this->gamedatas()['players'];
        $this->assertEquals(0, $players[$this->playerId(0)]['zombie']);
        $this->assertEquals(1, $players[$this->playerId(1)]['zombie']);
    }

    /**
     * `setPlayerZombie()` is a switch, not a one-way door: a test can
     * turn the flag back off (as BGA does when a player returns to the
     * table).
     */
    public function testZombieFlagCanBeCleared(): void
    {
        $player_id = $this->playerId(1);

        $this->setPlayerZombie($player_id);
        $this->assertTrue($this->isPlayerZombie($player_id));

        $this->setPlayerZombie($player_id, /*zombie=*/ false);
        $this->assertFalse($this->isPlayerZombie($player_id));
    }

    // ==================== Invocation on state entry ====================

    /**
     * The basic contract: entering a state that comes to rest on a
     * zombie runs that player's turn, and runs it before the entry
     * returns.
     */
    public function testRunsTheZombieTurnOnEnteringAStateTheZombieIsActiveIn(): void
    {
        $game = $this->game();

        $this->setPlayerZombie($this->playerId(1));
        $this->givenActive(1);

        // Re-entering the state the machine is already in is the
        // smallest possible "the game came to rest on this player".
        $game->gamestate->jumpToState(self::ST_TURN);

        $this->assertEquals(['stTurn/' . $this->playerId(1)], $this->zombieTurns());
        // The zombie's turn (see `ZombieTurnTestGame::zombieTurn()`)
        // moved the machine on, so the table is no longer stuck.
        $this->assertGameState(self::ST_PARK);
    }

    /**
     * The contrapositive, and the reason existing suites are
     * unaffected: with nobody zombified, `zombieTurn()` is never
     * called, however many states the machine passes through.
     */
    public function testDoesNotRunAZombieTurnForOrdinaryPlayers(): void
    {
        $game = $this->game();

        $this->givenActive(0);
        $game->gamestate->jumpToState(self::ST_TURN);
        $this->playerByIndex(0)->act('actTestTransition', ['transition' => 'tHandoff']);

        $this->assertEquals([], $this->zombieTurns());
        $this->assertGameState(self::ST_TURN);
    }

    /**
     * A zombie is only played for when the game is actually WAITING on
     * them.  In a "game"-type state it is working rather than waiting,
     * so nobody has a turn to take -- even the player the active-player
     * global happens to name.
     */
    public function testDoesNotRunAZombieTurnInAGameTypeState(): void
    {
        $game = $this->game();

        $this->setPlayerZombie($this->playerId(0));
        $this->givenActive(0);

        $game->gamestate->jumpToState(self::ST_PARK);

        $this->assertEquals([], $this->zombieTurns());
        $this->assertGameState(self::ST_PARK);
    }

    /**
     * Nor is an eliminated player played for: the table is not waiting
     * on them either way.
     */
    public function testDoesNotRunAZombieTurnForAnEliminatedPlayer(): void
    {
        $game = $this->game();

        $this->setPlayerZombie($this->playerId(1));
        $game->DbQuery('UPDATE `player` SET `player_eliminated` = 1 WHERE `player_id` = ' . $this->playerId(1));
        $this->givenActive(1);

        $game->gamestate->jumpToState(self::ST_TURN);

        $this->assertEquals([], $this->zombieTurns());
        $this->assertGameState(self::ST_TURN, 'An eliminated player is not waited on, so nothing should have moved.');
    }

    /**
     * A zombie's turn is an ordinary turn as far as the machine is
     * concerned: it can pass play to the next player, and the game
     * carries on from there.
     */
    public function testTheZombieTurnCanPassPlayToTheNextPlayer(): void
    {
        $game = $this->game();
        $game->zombie_turn_transition = 'tHandoff';

        $this->setPlayerZombie($this->playerId(1));
        $this->givenActive(1);

        $game->gamestate->jumpToState(self::ST_TURN);

        $this->assertEquals(['stTurn/' . $this->playerId(1)], $this->zombieTurns());
        $this->assertGameState(self::ST_TURN);
        $this->assertEquals(
            $this->playerId(0),
            intval($game->getActivePlayerId()),
            'The zombie passed, so the other player should now be active.'
        );
    }

    // ==================== Synchrony (the part that matters) ====================

    /**
     * The heart of it.  `stHandoff()` makes a player active and calls
     * `nextState()`; by the time that call RETURNS -- still inside
     * `stHandoff()`, still inside the player's request -- the zombie it
     * handed the turn to has already played, and the machine has moved
     * on past the state `stHandoff()` sent it to.
     *
     * A deferred implementation (queue the zombie turn, run it at the
     * start of the next request) would satisfy every other test in this
     * file and fail this one -- and would reproduce none of the
     * production bugs that live in this re-entrancy.
     */
    public function testRunsTheZombieTurnInsideTheCallersNextState(): void
    {
        $game = $this->game();

        $this->setPlayerZombie($this->playerId(1));
        $this->givenActive(0);

        $this->playerByIndex(0)->act('actTestTransition', ['transition' => 'tHandoff']);

        $this->assertEquals(['stTurn/' . $this->playerId(1)], $this->zombieTurns());

        // Both recorded by `stHandoff()` immediately after its
        // `nextState('tTurn')` returned.
        $this->assertEquals(
            1,
            $game->zombie_turns_after_handoff,
            'The zombie turn must have run before nextState() returned to its caller, not after the request.'
        );
        $this->assertEquals(
            self::ST_PARK,
            $game->state_id_after_handoff,
            'nextState() must return with the machine wherever the zombie turn left it.'
        );

        $this->assertGameState(self::ST_PARK);
    }

    /**
     * The corollary: whoever runs the zombie turns cannot trust
     * anything it read beforehand, because `zombieTurn()` transitions.
     * Here one zombie's turn carries the machine into a DIFFERENT state
     * -- a multiactive one, with a second zombie waiting in it -- and
     * that one is played for as well, all within the one call.
     */
    public function testFollowsTheMachineWhenTheZombieTurnChangesTheStateUnderneathIt(): void
    {
        $game = $this->game();
        $game->zombie_turn_transition = 'tMulti';

        $this->setPlayerZombie($this->playerId(0));
        $this->setPlayerZombie($this->playerId(1));
        $this->givenActive(0);

        $game->gamestate->jumpToState(self::ST_TURN);

        $this->assertEquals(
            [
                'stTurn/' . $this->playerId(0),
                'stMulti/' . $this->playerId(0),
                'stMulti/' . $this->playerId(1),
            ],
            $this->zombieTurns()
        );
        $this->assertGameState(self::ST_PARK);
    }

    // ==================== Multiactive states ====================

    /**
     * In a "multipleactiveplayer" state the game waits on several
     * players at once, so `zombieTurn()` is called once per active
     * zombie.
     */
    public function testRunsOneZombieTurnPerActiveZombieInAMultiactiveState(): void
    {
        $game = $this->game();

        $this->setPlayerZombie($this->playerId(0));
        $this->setPlayerZombie($this->playerId(1));

        $game->gamestate->jumpToState(self::ST_MULTI);

        $this->assertEquals(
            [
                'stMulti/' . $this->playerId(0),
                'stMulti/' . $this->playerId(1),
            ],
            $this->zombieTurns()
        );

        // Each turn deactivated its own player; deactivating the last
        // one took the state's transition.
        $this->assertEquals([], $this->multiactiveIds());
        $this->assertGameState(self::ST_PARK);
    }

    /**
     * Only the zombies are played for.  A multiactive state with one
     * zombie and one live player is left waiting on the live player --
     * which is the whole point of the feature: the table keeps running
     * for the people still at it.
     */
    public function testLeavesLiveMultiactivePlayersAlone(): void
    {
        $game = $this->game();

        $this->setPlayerZombie($this->playerId(1));

        $game->gamestate->jumpToState(self::ST_MULTI);

        $this->assertEquals(['stMulti/' . $this->playerId(1)], $this->zombieTurns());
        $this->assertEquals([$this->playerId(0)], $this->multiactiveIds());
        $this->assertGameState(self::ST_MULTI);
    }

    // ==================== The stuck sweep ====================

    /**
     * The gap that `checkStuckedZombiePlayers()` fills: a player
     * zombified while the game is ALREADY waiting on them was never
     * seen by a state entry, because no state entry happens between the
     * two.  Nothing runs on its own here...
     */
    public function testAPlayerZombifiedWhileActiveIsNotPlayedForImmediately(): void
    {
        $this->givenActive(0);
        $this->setPlayerZombie($this->playerId(0));

        $this->assertEquals([], $this->zombieTurns());
        $this->assertGameState(self::ST_TURN);
    }

    /**
     * ...and the sweep is what unsticks the table.
     */
    public function testTheSweepPlaysForAPlayerZombifiedWhileActive(): void
    {
        $game = $this->game();

        $this->givenActive(0);
        $this->setPlayerZombie($this->playerId(0));

        $game->checkStuckedZombiePlayers();

        $this->assertEquals(['stTurn/' . $this->playerId(0)], $this->zombieTurns());
        $this->assertGameState(self::ST_PARK);
    }

    /**
     * The sweep runs at the top of every request, so in practice a
     * stuck table unsticks itself on the next thing anybody does --
     * and it does so BEFORE that request's own action, as on BGA.
     */
    public function testTheSweepRunsAtTheStartOfTheNextRequest(): void
    {
        $game = $this->game();
        $game->zombie_turn_transition = 'tHandoff';

        $this->givenActive(0);
        $this->setPlayerZombie($this->playerId(0));

        // The other player acts; their request is what carries the
        // sweep.  The zombie's turn passes play to them, and only then
        // does their own transition run.
        $this->playerByIndex(1)->act('actTestTransition', ['transition' => 'tPark']);

        $this->assertEquals(['stTurn/' . $this->playerId(0)], $this->zombieTurns());
        $this->assertEquals(
            ['zombie:stTurn', 'st:stHandoff', 'st:stPark'],
            $game->trace,
            'The sweep must run before the request\'s own action.'
        );
        $this->assertGameState(self::ST_PARK);
    }

    /**
     * With nobody stuck the sweep is a no-op, which is what makes it
     * safe to run on every single request.
     */
    public function testTheSweepDoesNothingWhenNobodyIsStuck(): void
    {
        $game = $this->game();

        $this->givenActive(0);

        $game->checkStuckedZombiePlayers();

        $this->assertEquals([], $this->zombieTurns());
        $this->assertGameState(self::ST_TURN);
    }

    // ==================== Runaway guards ====================

    /**
     * A `zombieTurn()` that transitions back into the state it was
     * called from, with the same zombie still waiting, would recurse
     * until the request died -- which in a test suite means a hang with
     * nothing to look at.  The depth cap turns it into a failure that
     * names the state.
     */
    public function testFailsLoudlyWhenTheZombieTurnReEntersItsOwnState(): void
    {
        $game = $this->game();

        $this->setPlayerZombie($this->playerId(0));
        $this->givenActive(0);

        try {
            $game->gamestate->jumpToState(self::ST_LOOP);
            $this->fail('Expected a zombieTurn() that re-enters its own state to fail loudly.');
        } catch (\feException $exc) {
            $this->assertStringContainsString('Runaway zombie turns', $exc->getMessage());
            $this->assertStringContainsString('stLoop', $exc->getMessage());
        }

        $this->assertCount(
            \Table::LOCALARENA_MAX_ZOMBIE_DEPTH,
            $game->zombie_turns,
            'The cap should stop the recursion, not merely notice it afterwards.'
        );
    }

    /**
     * The other shape of the same bug: a `zombieTurn()` that returns
     * without making any progress at all, leaving its player active in
     * the state it was called for.  That spins in one pass rather than
     * recursing, so it needs its own bound.
     */
    public function testFailsLoudlyWhenTheZombieTurnLeavesItsPlayerActive(): void
    {
        $game = $this->game();

        $this->setPlayerZombie($this->playerId(0));
        $this->givenActive(0);

        try {
            $game->gamestate->jumpToState(self::ST_STUCK);
            $this->fail('Expected a zombieTurn() that makes no progress to fail loudly.');
        } catch (\feException $exc) {
            $this->assertStringContainsString('Runaway zombie turns', $exc->getMessage());
            $this->assertStringContainsString('stStuck', $exc->getMessage());
            $this->assertStringContainsString(strval($this->playerId(0)), $exc->getMessage());
        }

        $this->assertCount(\Table::LOCALARENA_MAX_ZOMBIE_TURNS_PER_PASS, $game->zombie_turns);
    }
}

/**
 * A localarenanoop subclass that installs a state machine with the
 * shapes zombie handling has to cope with -- an ordinary turn state, a
 * multiactive state, a state to come to rest in, and two states whose
 * zombie handling is broken on purpose -- and that records what the
 * framework asks it to do.  Used only by ZombieTurnTest.
 */
class ZombieTurnTestGame extends \localarenanoop
{
    /**
     * One entry per `zombieTurn()` call, in order.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $zombie_turns = [];

    /**
     * Every state action and zombie turn, in order, as short labels;
     * for tests that care about what ran before what.
     *
     * @var array<int, string>
     */
    public array $trace = [];

    /**
     * The transition a zombie's turn takes out of `stTurn`.  Tests set
     * this to choose what "the zombie played" leads to.
     */
    public string $zombie_turn_transition = 'tPark';

    /**
     * Recorded by `stHandoff()` immediately after its `nextState()`
     * returns: the live state id, and how many zombie turns had run by
     * then.  Both are how the tests see that zombie turns happen
     * INSIDE the caller's transition rather than after the request.
     */
    public ?int $state_id_after_handoff = null;
    public ?int $zombie_turns_after_handoff = null;

    public function __construct()
    {
        parent::__construct();

        // Replace the trivial noop machine with one built for zombies.
        $this->gamestate = new \GameState($this, self::zombieMachineStates());
    }

    private static function zombieMachineStates(): array
    {
        return [
            // Initial state; base Table::stGameSetup() runs here and
            // transitions to the turn state.
            1 => [
                'name' => 'gameSetup',
                'description' => '',
                'type' => 'manager',
                'action' => 'stGameSetup',
                'transitions' => ['' => ZombieTurnTest::ST_TURN],
            ],

            ZombieTurnTest::ST_TURN => [
                'name' => 'stTurn',
                'description' => '',
                'type' => 'activeplayer',
                'possibleactions' => ['actTestTransition'],
                'transitions' => [
                    'tHandoff' => ZombieTurnTest::ST_HANDOFF,
                    'tMulti' => ZombieTurnTest::ST_MULTI,
                    'tPark' => ZombieTurnTest::ST_PARK,
                ],
            ],

            // Hands the turn to the next player and returns to the turn
            // state -- the ordinary shape of "somebody's turn ends".
            ZombieTurnTest::ST_HANDOFF => [
                'name' => 'stHandoff',
                'description' => '',
                'type' => 'game',
                'action' => 'stHandoff',
                'transitions' => ['tTurn' => ZombieTurnTest::ST_TURN],
            ],

            ZombieTurnTest::ST_MULTI => [
                'name' => 'stMulti',
                'description' => '',
                'type' => 'multipleactiveplayer',
                'action' => 'stMulti',
                'possibleactions' => ['actTestTransition'],
                'transitions' => ['tPark' => ZombieTurnTest::ST_PARK],
            ],

            // Waits on nobody, and goes nowhere: where the machine comes
            // to rest once a zombie's turn is over.
            ZombieTurnTest::ST_PARK => [
                'name' => 'stPark',
                'description' => '',
                'type' => 'game',
                'action' => 'stPark',
                'transitions' => [],
            ],

            // Broken on purpose: its zombie handling transitions back
            // into it, with the same player still waiting.
            ZombieTurnTest::ST_LOOP => [
                'name' => 'stLoop',
                'description' => '',
                'type' => 'activeplayer',
                'possibleactions' => [],
                'transitions' => ['tLoop' => ZombieTurnTest::ST_LOOP],
            ],

            // Broken on purpose: its zombie handling does nothing at
            // all, so the player it was called for is still waiting
            // when it returns.
            ZombieTurnTest::ST_STUCK => [
                'name' => 'stStuck',
                'description' => '',
                'type' => 'activeplayer',
                'possibleactions' => [],
                'transitions' => ['tPark' => ZombieTurnTest::ST_PARK],
            ],
        ];
    }

    public function stHandoff(): void
    {
        $this->trace[] = 'st:stHandoff';

        $this->activeNextPlayer();
        $this->gamestate->nextState('tTurn');

        // Deliberately recorded AFTER the transition: on BGA, any
        // zombie turn the transition set off has already run by the
        // time control gets back here.
        $this->state_id_after_handoff = $this->getCurrentStateId();
        $this->zombie_turns_after_handoff = count($this->zombie_turns);
    }

    public function stMulti(): void
    {
        $this->trace[] = 'st:stMulti';

        $this->gamestate->setAllPlayersMultiactive();
    }

    public function stPark(): void
    {
        $this->trace[] = 'st:stPark';
    }

    /**
     * The conventional helper BGA's game template gives every game.  It
     * is reproduced verbatim here because making it work unmodified is
     * a requirement of the framework support these tests cover.
     */
    public function isPlayerZombie(int $player_id): bool
    {
        $players = $this->loadPlayersBasicInfos();
        return array_key_exists($player_id, $players) && $players[$player_id]['player_zombie'] == 1;
    }

    public function zombieTurn($state, $active_player)
    {
        $this->zombie_turns[] = ['state' => $state['name'], 'player_id' => intval($active_player)];
        $this->trace[] = 'zombie:' . $state['name'];

        switch ($state['name']) {
            case 'stTurn':
                $this->gamestate->nextState($this->zombie_turn_transition);
                return;

            case 'stMulti':
                $this->gamestate->setPlayerNonMultiactive($active_player, 'tPark');
                return;

            case 'stLoop':
                // Broken on purpose: straight back into this state,
                // with this player still the one being waited on.
                $this->gamestate->nextState('tLoop');
                return;

            case 'stStuck':
                // Broken on purpose: no progress whatsoever.
                return;
        }

        throw new \feException('Zombie mode not supported at this game state: ' . $state['name']);
    }
}

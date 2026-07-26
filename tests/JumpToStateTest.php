<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/../module/test/IntegrationTestCase.php';

// We extend the bundled "localarenanoop" harness game, so load its
// class.  When a test supplies a `table_class`, TableManager::getTable()
// instantiates that class directly and does NOT require the game's
// .game.php file, so we must require it ourselves before subclassing.
require_once LOCALARENA_GAME_PATH . 'localarenanoop/localarenanoop.game.php';

/**
 * Tests for `$this->gamestate->jumpToState($stateNum)`.
 *
 * Unlike `nextState()`, which names a TRANSITION out of the current
 * state, `jumpToState()` names the TARGET STATE by its key, and does
 * not require an edge to join the two: it can move the machine to any
 * state from any state.  It is the tool for BGA's advanced cases --
 * "do_anytime" actions, dispatcher states, and recovering from a
 * zombie-player function.
 *
 * The optional second parameter, `$bWithActions`, controls whether the
 * target state's "action" (st*) method runs on arrival; with it false,
 * a "game"-type state can be jumped into without immediately cascading
 * onwards.
 */
class JumpToStateTest extends IntegrationTestCase
{
    const LOCALARENA_GAME_NAME = 'localarenanoop';

    // State ids used by the machine installed below.
    const ST_INPUT = 2;          // entry / input state (activeplayer)
    const ST_DISPATCH = 10;      // game-type; jumps onwards itself
    const ST_TARGET_INPUT = 11;  // activeplayer; NO transition leads here
    const ST_INERT = 12;         // game-type; its action jumps to ST_OTHER_INPUT
    const ST_OTHER_INPUT = 13;   // activeplayer
    const ST_MULTI = 14;         // multipleactiveplayer
    const ST_ARGS = 15;          // game-type, with both an action and args

    protected function defaultTableParams(): \LocalArena\TableParams
    {
        $params = parent::defaultTableParams();
        // Reuse all of localarenanoop's files (states/gameinfos/dbmodel/
        // action class etc.), but instantiate our jump-aware subclass
        // instead of the plain game class.
        $params->table_class = JumpToStateTestGame::class;
        return $params;
    }

    private function game(): JumpToStateTestGame
    {
        return $this->table();
    }

    /**
     * The defining property: a jump does not need a transition.  The
     * target state here is not named by any transition of the entry
     * state (indeed, of any state), so `nextState()` cannot reach it --
     * but `jumpToState()` can.
     */
    public function testJumpsToAStateNoTransitionLeadsTo(): void
    {
        $game = $this->game();

        $this->assertGameState(self::ST_INPUT);
        $this->assertArrayNotHasKey(
            self::ST_TARGET_INPUT,
            array_flip($game->gamestate->state()['transitions']),
            'The premise of this test is that no transition of the entry state leads to the jump target.'
        );

        $game->gamestate->jumpToState(self::ST_TARGET_INPUT);

        $this->assertGameState(self::ST_TARGET_INPUT);
        $this->assertEquals('stTargetInput', $game->gamestate->state()['name']);
    }

    /**
     * A jump behaves like `nextState()` as far as the current-state
     * global is concerned: only the live, in-memory state moves; the
     * persisted global stays pinned until the request boundary.
     */
    public function testCurrentStateGlobalLagsUntilTheRequestBoundary(): void
    {
        $game = $this->game();

        $game->gamestate->jumpToState(self::ST_TARGET_INPUT);

        $this->assertEquals(self::ST_TARGET_INPUT, $game->getCurrentStateId());
        $this->assertEquals(
            self::ST_INPUT,
            intval($game->getGameStateValue('currentState')),
            'jumpToState() must not advance the current-state global before the request boundary.'
        );

        // Flushing (what saveState() does at the request boundary)
        // brings the global into agreement with the live state.
        $game->flushCurrentStateGlobal();
        $this->assertEquals(self::ST_TARGET_INPUT, intval($game->getGameStateValue('currentState')));
    }

    /**
     * By default the target state is entered "with actions": its st*
     * method runs, so jumping into a "game"-type state cascades onwards
     * from there just as arriving by transition would.
     */
    public function testRunsTheTargetStateActionByDefault(): void
    {
        $game = $this->game();

        // Jumping within a real request, so that the request-boundary
        // semantics of the current-state global are exercised too.
        $this->playerByIndex(0)->act('actTestJumpToState', [
            'state_id' => self::ST_DISPATCH,
            'with_actions' => true,
        ]);

        // stDispatch ran, and itself jumped onwards to the state where
        // the machine came to rest.
        $this->assertEquals([self::ST_DISPATCH], $game->entered);
        $this->assertGameState(self::ST_TARGET_INPUT);
        $this->assertEquals(
            self::ST_TARGET_INPUT,
            intval($game->getGameStateValue('currentState')),
            'After the request, the current-state global should equal the parked state.'
        );

        // While the dispatcher's action ran, the live state was already
        // ST_DISPATCH but the global still named the state the request
        // entered in.
        $this->assertEquals(
            [['live' => self::ST_DISPATCH, 'global' => self::ST_INPUT]],
            $game->recorded,
            'The live state must be current inside the jumped-to action, while the global lags.'
        );
    }

    /**
     * With `$bWithActions` false, the machine enters the target state
     * but does NOT run its action method, so a "game"-type state jumped
     * into this way does not cascade onwards -- the machine rests
     * there.
     */
    public function testDoesNotRunTheTargetStateActionWhenAskedNotTo(): void
    {
        $game = $this->game();

        $this->playerByIndex(0)->act('actTestJumpToState', [
            'state_id' => self::ST_INERT,
            'with_actions' => false,
        ]);

        $this->assertEquals([], $game->entered, 'The target state\'s action method must not have run.');
        $this->assertGameState(self::ST_INERT, 'The machine should have come to rest in the jumped-to state.');
        $this->assertEquals(self::ST_INERT, intval($game->getGameStateValue('currentState')));
    }

    /**
     * The same jump *with* actions runs the state's action method, and
     * so continues on to wherever that method leads: the contrast that
     * makes the previous test meaningful.
     */
    public function testRunsTheTargetStateActionWhenAskedTo(): void
    {
        $game = $this->game();

        $this->playerByIndex(0)->act('actTestJumpToState', [
            'state_id' => self::ST_INERT,
            'with_actions' => true,
        ]);

        $this->assertEquals([self::ST_INERT], $game->entered);
        $this->assertGameState(self::ST_OTHER_INPUT);
    }

    /**
     * Clients are told about the new state either way: the state-change
     * notification is not one of the "actions" that `$bWithActions`
     * suppresses.
     */
    public function testAnnouncesTheNewStateEvenWithoutActions(): void
    {
        $game = $this->game();

        $game->gamestate->jumpToState(self::ST_INERT, /*bWithActions=*/ false);

        $this->assertEquals('stInert', $this->state()->name());
        $this->assertEquals('game', $this->state()->type());

        // The jump queued a "gameStateChange" notification naming the
        // state we jumped into.
        $notif = $this->lastNotif();
        $this->assertEquals('gameStateChange', $notif['notification_type']);
        $this->assertEquals(self::ST_INERT, $notif['args']['id']);
        $this->assertEquals('stInert', $notif['args']['name']);
    }

    // Returns the most recently queued notification (notifications are
    // written to the game log and sent once the request's transaction
    // commits; see `Table::notifyAllPlayers()`).
    private function lastNotif(): array
    {
        return json_decode(
            $this->table()->getUniqueValueFromDB(
                'SELECT `gamelog_notification` FROM `gamelog` ORDER BY `gamelog_id` DESC LIMIT 1'
            ),
            /*associative=*/ true
        );
    }

    /**
     * `$bWithActions` suppresses the target state's ACTION method only:
     * its "args" method still runs, so the arrival notification carries
     * the state's args as usual.  (Were it otherwise, clients would be
     * told about a state they cannot render.)
     */
    public function testRendersTheTargetStatesArgsEvenWithoutActions(): void
    {
        $game = $this->game();

        $game->gamestate->jumpToState(self::ST_ARGS, /*bWithActions=*/ false);

        $this->assertEquals([], $game->entered, 'The target state\'s action method must not have run.');

        // `getStateForNotif()` (and hence the arrival notification)
        // calls the args method.
        $notif = $this->lastNotif();
        $this->assertEquals('gameStateChange', $notif['notification_type']);
        $this->assertEquals(self::ST_ARGS, $notif['args']['id']);
        $this->assertEquals(['jumped' => true], $notif['args']['args']);
        $this->assertEquals(['jumped' => true], $this->state()->args());
    }

    /**
     * A jump changes the state and nothing else: whoever was the active
     * player still is.  (Making someone active is the game's job, via
     * `changeActivePlayer()`/`activeNextPlayer()`, exactly as when
     * arriving by transition.)
     */
    public function testDoesNotChangeTheActivePlayer(): void
    {
        $game = $this->game();

        $player_id = intval($this->playerByIndex(1)->id());
        $game->gamestate->changeActivePlayer($player_id);

        $game->gamestate->jumpToState(self::ST_TARGET_INPUT);

        $this->assertGameState(self::ST_TARGET_INPUT);
        $this->assertEquals($player_id, intval($game->getActivePlayerId()));
    }

    /**
     * Likewise for multiactive flags: jumping out of a multiactive
     * state does not clear them, matching what `nextState()` does (and
     * so leaving `setPlayersMultiactive()` / `setPlayerNonMultiactive()`
     * as the way players stop being active).
     */
    public function testDoesNotClearMultiactiveFlags(): void
    {
        $game = $this->game();

        $player_count = count($this->players());
        $active_player_id = intval($this->playerByIndex(0)->id());
        $game->gamestate->changeActivePlayer($active_player_id);

        $game->gamestate->jumpToState(self::ST_MULTI, /*bWithActions=*/ false);
        $game->gamestate->setAllPlayersMultiactive();
        $this->assertCount($player_count, $game->gamestate->getActivePlayerList());

        $game->gamestate->jumpToState(self::ST_TARGET_INPUT);

        // The flags themselves survive the jump...
        $this->assertEquals(
            $player_count,
            intval($game->getUniqueValueFromDB('SELECT COUNT(*) FROM `player` WHERE `player_is_multiactive` = 1')),
            'A jump should not clear multiactive flags.'
        );
        // ...but they stop being what "who is active?" means: in an
        // "activeplayer" state that question is answered by the active
        // player, whatever the flags still say.
        $this->assertEquals(
            [$active_player_id],
            array_map('intval', $game->gamestate->getActivePlayerList())
        );
    }

    /**
     * End-to-end: a state jumped to in one request is the state the
     * NEXT request starts in.  (This is what the request-boundary flush
     * of the current-state global buys; without it the jump would be
     * forgotten as soon as the action returned.)
     */
    public function testJumpedToStateIsWhereTheNextRequestStarts(): void
    {
        $game = $this->game();

        // Request 1: jump, without running actions, into a state the
        // machine would otherwise never reach, and park there.
        $this->playerByIndex(0)->act('actTestJumpToState', [
            'state_id' => self::ST_TARGET_INPUT,
            'with_actions' => false,
        ]);
        $this->assertGameState(self::ST_TARGET_INPUT);

        // Request 2: jump into the dispatcher, which records the
        // current-state global as it stood when this request began.
        $this->playerByIndex(0)->act('actTestJumpToState', [
            'state_id' => self::ST_DISPATCH,
            'with_actions' => true,
        ]);

        $this->assertEquals(
            [['live' => self::ST_DISPATCH, 'global' => self::ST_TARGET_INPUT]],
            $game->recorded,
            'The second request should have started in the state the first request jumped to.'
        );
    }

    /**
     * Jumping into a multiactive state works like arriving there by
     * transition (the arrival is announced as a multiactive state); the
     * jump itself does not make anyone active.
     */
    public function testJumpsIntoAMultiactiveState(): void
    {
        $game = $this->game();

        $game->gamestate->jumpToState(self::ST_MULTI, /*bWithActions=*/ false);

        $this->assertGameState(self::ST_MULTI);
        $this->assertEquals('multipleactiveplayer', $this->state()->type());
        $this->assertEquals([], $game->gamestate->getActivePlayerList());
    }

    /**
     * A jump to a state that is not in the machine is a programming
     * error, and leaves the machine where it was.
     */
    public function testThrowsWhenTheTargetStateDoesNotExist(): void
    {
        $game = $this->game();

        try {
            $game->gamestate->jumpToState(4242);
            $this->fail('Expected jumping to a nonexistent state to throw.');
        } catch (\feException $exc) {
            $this->assertStringContainsString('4242', $exc->getMessage());
        }

        $this->assertGameState(self::ST_INPUT, 'A failed jump must not move the machine.');
        $this->assertEquals([], $game->entered);
    }

    /**
     * Re-entering the state the machine is already in is a legitimate
     * (if unusual) jump: the state is entered afresh, action and all.
     */
    public function testCanJumpToTheCurrentState(): void
    {
        $game = $this->game();

        $game->gamestate->jumpToState(self::ST_INERT, /*bWithActions=*/ false);
        $this->assertGameState(self::ST_INERT);
        $this->assertEquals([], $game->entered);

        $game->gamestate->jumpToState(self::ST_INERT, /*bWithActions=*/ true);
        $this->assertEquals([self::ST_INERT], $game->entered, 'Re-entering the current state should run its action.');
        $this->assertGameState(self::ST_OTHER_INPUT);
    }
}

/**
 * A localarenanoop subclass whose only job is to install a state
 * machine with states that no transition leads to (so only a jump can
 * reach them), and to record which of those states' action methods run.
 * Used only by JumpToStateTest.
 */
class JumpToStateTestGame extends \localarenanoop
{
    /**
     * The ids of the states whose action (st*) methods have run, in
     * order.
     *
     * @var array<int, int>
     */
    public array $entered = [];

    /**
     * One entry per action method run, recording the live state id and
     * the (lagging) current-state global as seen from inside it.
     *
     * @var array<int, array<string, int>>
     */
    public array $recorded = [];

    public function __construct()
    {
        parent::__construct();

        // Replace the trivial noop machine with one built for jumping.
        $this->gamestate = new \GameState($this, self::jumpMachineStates());
    }

    private static function jumpMachineStates(): array
    {
        return [
            // Initial state; base Table::stGameSetup() runs here and
            // transitions to the input state.
            1 => [
                'name' => 'gameSetup',
                'description' => '',
                'type' => 'manager',
                'action' => 'stGameSetup',
                'transitions' => ['' => JumpToStateTest::ST_INPUT],
            ],

            // Entry / input state.  Note that its only transition leads
            // to ST_OTHER_INPUT: every other state below is reachable
            // only by a jump.
            JumpToStateTest::ST_INPUT => [
                'name' => 'stInput',
                'description' => '',
                'type' => 'activeplayer',
                'possibleactions' => ['actTestJumpToState'],
                'transitions' => ['step' => JumpToStateTest::ST_OTHER_INPUT],
            ],

            // A dispatcher-style state: it decides where to go and
            // jumps there itself.
            JumpToStateTest::ST_DISPATCH => [
                'name' => 'stDispatch',
                'description' => '',
                'type' => 'game',
                'action' => 'stDispatch',
                'transitions' => [],
            ],

            // Nothing transitions here; only a jump can reach it.
            JumpToStateTest::ST_TARGET_INPUT => [
                'name' => 'stTargetInput',
                'description' => '',
                'type' => 'activeplayer',
                'possibleactions' => [],
                'transitions' => [],
            ],

            // A "game"-type state that cascades onwards when (and only
            // when) its action method is allowed to run.
            JumpToStateTest::ST_INERT => [
                'name' => 'stInert',
                'description' => '',
                'type' => 'game',
                'action' => 'stInert',
                'transitions' => ['next' => JumpToStateTest::ST_OTHER_INPUT],
            ],

            JumpToStateTest::ST_OTHER_INPUT => [
                'name' => 'stOtherInput',
                'description' => '',
                'type' => 'activeplayer',
                'possibleactions' => [],
                'transitions' => [],
            ],

            JumpToStateTest::ST_MULTI => [
                'name' => 'stMulti',
                'description' => '',
                'type' => 'multipleactiveplayer',
                'possibleactions' => [],
                'transitions' => [],
            ],

            // Has both an action and an args method, so that a jump
            // "without actions" can be seen to skip the former while
            // still running the latter.
            JumpToStateTest::ST_ARGS => [
                'name' => 'stArgs',
                'description' => '',
                'type' => 'game',
                'action' => 'stArgs',
                'args' => 'argArgs',
                'transitions' => ['next' => JumpToStateTest::ST_OTHER_INPUT],
            ],
        ];
    }

    public function stDispatch(): void
    {
        $this->record(JumpToStateTest::ST_DISPATCH);

        // A dispatcher jumps rather than transitions: ST_TARGET_INPUT
        // is not among this state's transitions (it has none).
        $this->gamestate->jumpToState(JumpToStateTest::ST_TARGET_INPUT);
    }

    public function stInert(): void
    {
        $this->record(JumpToStateTest::ST_INERT);

        $this->gamestate->nextState('next');
    }

    public function stArgs(): void
    {
        $this->record(JumpToStateTest::ST_ARGS);

        $this->gamestate->nextState('next');
    }

    public function argArgs(): array
    {
        return ['jumped' => true];
    }

    private function record(int $state_id): void
    {
        $this->entered[] = $state_id;
        $this->recorded[] = [
            'live' => $this->getCurrentStateId(),
            'global' => intval($this->getGameStateValue('currentState')),
        ];
    }
}

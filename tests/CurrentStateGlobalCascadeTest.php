<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/../module/test/IntegrationTestCase.php';

// We extend the bundled "localarenanoop" harness game, so load its
// class.  When a test supplies a `table_class`, TableManager::getTable()
// instantiates that class directly and does NOT require the game's
// .game.php file, so we must require it ourselves before subclassing.
require_once LOCALARENA_GAME_PATH . 'localarenanoop/localarenanoop.game.php';

/**
 * Regression test for the LocalArena <-> real-BGA divergence in the
 * framework's reserved "current state" global during an in-request
 * state cascade.
 *
 * There are two ways game code can ask "what state am I in?":
 *
 *   1. The live, in-memory state machine -- `gamestate->state()` /
 *      `gamestate->state_id()`, and the framework helper
 *      `getCurrentStateId()`.  These advance *immediately* on every
 *      `nextState()` transition.
 *
 *   2. The persisted current-state global -- `getGameStateValue()` for
 *      the reserved current-state global (global #1 / label
 *      'currentState', or any game label aliased to it, e.g. BGA's
 *      `BGA_GAMESTATE_CURRENT_STATE`).
 *
 * On real BGA these DIVERGE while a single request synchronously
 * cascades through several "game"-type states: (1) is live, but (2)
 * stays pinned at the state the request *entered* in until the request
 * boundary.  Historically LocalArena kept the two in lockstep, masking
 * a class of production bugs.  This test pins the corrected behavior.
 */
class CurrentStateGlobalCascadeTest extends IntegrationTestCase
{
    const LOCALARENA_GAME_NAME = 'localarenanoop';

    // State ids used by the cascade machine installed below.
    const ST_INPUT = 2;        // entry / input state (activeplayer)
    const ST_CASCADE_A = 10;   // game-type
    const ST_CASCADE_B = 11;   // game-type
    const ST_CASCADE_C = 12;   // game-type
    const ST_SECOND_INPUT = 13; // where the cascade parks (activeplayer)

    protected function defaultTableParams(): \LocalArena\TableParams
    {
        $params = parent::defaultTableParams();
        // Reuse all of localarenanoop's files (states/gameinfos/dbmodel/
        // action class etc.), but instantiate our cascade-aware
        // subclass instead of the plain game class.
        $params->table_class = CascadeStateTestGame::class;
        return $params;
    }

    private function game(): CascadeStateTestGame
    {
        return $this->table();
    }

    /**
     * The core LocalArena-level assertion: during a multi-game-state
     * cascade triggered by one action, the current-state global stays
     * equal to the entry state while the live state id advances.
     */
    public function testCurrentStateGlobalLagsLiveStateDuringCascade(): void
    {
        $game = $this->game();

        // After setup the game has parked at the input state, and the
        // live state and the persisted global agree (no cascade in
        // flight).
        $this->assertEquals(self::ST_INPUT, $game->getCurrentStateId());
        $this->assertEquals(self::ST_INPUT, intval($game->getGameStateValue('currentState')));

        // One player action drives the whole cascade:
        //   ST_INPUT -> ST_CASCADE_A -> ST_CASCADE_B -> ST_CASCADE_C
        //            -> ST_SECOND_INPUT (parks).
        // The action carries no active-player dependency, so any
        // participant may submit it.
        $this->playerByIndex(0)->act('actTestTransition', ['transition' => 'go']);

        // Each game-type state recorded both readings as it was entered.
        $recorded = $game->recorded;
        $this->assertCount(3, $recorded, 'Expected one record per game-type state in the cascade.');

        $expectedLive = [self::ST_CASCADE_A, self::ST_CASCADE_B, self::ST_CASCADE_C];
        foreach ($recorded as $i => $step) {
            // (1) The live, in-memory state advances on each nextState().
            $this->assertEquals(
                $expectedLive[$i],
                $step['live_state_id'],
                "getCurrentStateId() should be live at cascade step {$i} ({$step['name']})."
            );
            $this->assertEquals(
                $expectedLive[$i],
                $step['live_state_id_via_gamestate'],
                "gamestate->state_id() should be live at cascade step {$i} ({$step['name']})."
            );
            $this->assertEquals(
                $step['name'],
                $step['live_state_name'],
                "gamestate->state()['name'] should be live at cascade step {$i}."
            );

            // (2) The current-state global LAGS: pinned at the entry
            // state for the entire cascade -- this is the real-BGA
            // staleness LocalArena must replicate.
            $this->assertEquals(
                self::ST_INPUT,
                $step['current_state_global'],
                "getGameStateValue('currentState') should stay pinned at the entry state during the cascade " .
                    "(step {$i}, {$step['name']})."
            );
            $this->assertEquals(
                self::ST_INPUT,
                $step['current_state_global_via_alias'],
                "A game label aliased to the current-state global should lag identically (step {$i})."
            );

            // The whole point of the divergence: the two disagree
            // mid-cascade.
            $this->assertNotEquals(
                $step['current_state_global'],
                $step['live_state_id'],
                "The live state and the current-state global must diverge mid-cascade (step {$i})."
            );
        }

        // At the request boundary the global catches up to the parked
        // state, and the two are back in agreement.
        $this->assertEquals(self::ST_SECOND_INPUT, $game->getCurrentStateId());
        $this->assertEquals(
            self::ST_SECOND_INPUT,
            intval($game->getGameStateValue('currentState')),
            'After the request, the current-state global should equal the parked state.'
        );
    }

    /**
     * Companion check on the "live" side in isolation: outside of any
     * cascade, both ways of reading the current state agree, and
     * `nextState()` immediately updates the live readings.
     */
    public function testLiveStateUpdatesImmediatelyOnNextState(): void
    {
        $game = $this->game();

        // Parked at the input state; everything agrees.
        $this->assertEquals(self::ST_INPUT, $game->gamestate->state_id());
        $this->assertEquals(self::ST_INPUT, $game->getCurrentStateId());
        $this->assertEquals(self::ST_INPUT, intval($game->getGameStateValue('currentState')));

        // Drive a single transition directly into another input state
        // (which has no auto-advancing action, so the machine stops
        // there).  The live readings move at once...
        $game->gamestate->nextState('step');
        $this->assertEquals(self::ST_SECOND_INPUT, $game->gamestate->state_id());
        $this->assertEquals(self::ST_SECOND_INPUT, $game->getCurrentStateId());

        // ...but the persisted global has NOT been flushed yet, so it
        // still reports the pre-transition state.
        $this->assertEquals(
            self::ST_INPUT,
            intval($game->getGameStateValue('currentState')),
            'The current-state global must not advance until the request boundary flush.'
        );

        // Flushing (what saveState() does at the request boundary)
        // brings the global into agreement with the live state.
        $game->flushCurrentStateGlobal();
        $this->assertEquals(self::ST_SECOND_INPUT, intval($game->getGameStateValue('currentState')));
    }
}

/**
 * A localarenanoop subclass whose only job is to install a state
 * machine that cascades through several "game"-type states, and to
 * record -- as each of those states is entered -- both ways of reading
 * the current state.  Used only by CurrentStateGlobalCascadeTest.
 */
class CascadeStateTestGame extends \localarenanoop
{
    /**
     * One entry per game-type state entered during a cascade, each with
     * the live state readings and the (lagging) current-state global.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $recorded = [];

    public function __construct()
    {
        parent::__construct();

        // Alias a game-defined label onto the reserved current-state
        // global (global #1), mirroring how a real game might map
        // `'bgaCurrentState' => BGA_GAMESTATE_CURRENT_STATE`.  Reads via
        // this label must lag identically to reads via 'currentState'.
        $this->initGameStateLabels(['myCurrentStateLabel' => 1]);

        // Replace the trivial noop machine with one that cascades.
        $this->gamestate = new \GameState($this, self::cascadeMachineStates());
    }

    private static function cascadeMachineStates(): array
    {
        return [
            // Initial state; base Table::stGameSetup() runs here and
            // transitions to the input state.
            1 => [
                'name' => 'gameSetup',
                'description' => '',
                'type' => 'manager',
                'action' => 'stGameSetup',
                'transitions' => ['' => CurrentStateGlobalCascadeTest::ST_INPUT],
            ],

            // Entry / input state: the request enters here, and the
            // current-state global stays pinned at this id for the whole
            // cascade triggered from it.
            CurrentStateGlobalCascadeTest::ST_INPUT => [
                'name' => 'stInput',
                'description' => '',
                'type' => 'activeplayer',
                'possibleactions' => ['actTestTransition'],
                'transitions' => [
                    // Kicks off the multi-state cascade.
                    'go' => CurrentStateGlobalCascadeTest::ST_CASCADE_A,
                    // Single hop straight to another input (activeplayer)
                    // state -- no auto-advancing "game" state in between,
                    // so the machine stops there.  Used to observe one
                    // immediate transition in isolation.
                    'step' => CurrentStateGlobalCascadeTest::ST_SECOND_INPUT,
                ],
            ],

            CurrentStateGlobalCascadeTest::ST_CASCADE_A => [
                'name' => 'stCascadeA',
                'description' => '',
                'type' => 'game',
                'action' => 'stCascadeA',
                'transitions' => ['next' => CurrentStateGlobalCascadeTest::ST_CASCADE_B],
            ],
            CurrentStateGlobalCascadeTest::ST_CASCADE_B => [
                'name' => 'stCascadeB',
                'description' => '',
                'type' => 'game',
                'action' => 'stCascadeB',
                'transitions' => ['next' => CurrentStateGlobalCascadeTest::ST_CASCADE_C],
            ],
            CurrentStateGlobalCascadeTest::ST_CASCADE_C => [
                'name' => 'stCascadeC',
                'description' => '',
                'type' => 'game',
                'action' => 'stCascadeC',
                'transitions' => ['next' => CurrentStateGlobalCascadeTest::ST_SECOND_INPUT],
            ],

            // Where the cascade parks (awaiting player input again).
            CurrentStateGlobalCascadeTest::ST_SECOND_INPUT => [
                'name' => 'stSecondInput',
                'description' => '',
                'type' => 'activeplayer',
                'possibleactions' => [],
                'transitions' => [],
            ],
        ];
    }

    public function stCascadeA(): void
    {
        $this->recordCascadeStep('stCascadeA');
    }

    public function stCascadeB(): void
    {
        $this->recordCascadeStep('stCascadeB');
    }

    public function stCascadeC(): void
    {
        $this->recordCascadeStep('stCascadeC');
    }

    // Record both ways of reading the current state as this game-type
    // state is entered, then continue the cascade.
    private function recordCascadeStep(string $name): void
    {
        $this->recorded[] = [
            'name' => $name,
            // (1) live, in-memory readings
            'live_state_id' => $this->getCurrentStateId(),
            'live_state_id_via_gamestate' => $this->gamestate->state_id(),
            'live_state_name' => $this->gamestate->state()['name'],
            // (2) persisted current-state global (should lag)
            'current_state_global' => intval($this->getGameStateValue('currentState')),
            'current_state_global_via_alias' => intval($this->getGameStateValue('myCurrentStateLabel')),
        ];

        $this->gamestate->nextState('next');
    }
}

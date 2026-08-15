<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/../module/test/IntegrationTestCase.php';

// We extend the bundled "localarenanoop" harness game, so load its
// class.  When a test supplies a `table_class`, TableManager::getTable()
// instantiates that class directly and does NOT require the game's
// .game.php file, so we must require it ourselves before subclassing.
require_once LOCALARENA_GAME_PATH . 'localarenanoop/localarenanoop.game.php';

/**
 * Tests for `$this->gamestate->setPlayersMultiactive($players,
 * $next_state, $bExclusive)`.
 *
 * The two things this method decides are independent of each other, and
 * both are easy to get backwards:
 *
 * - WHO ends up active.  $players is always activated; $bExclusive says
 *   what happens to everyone else.  False (the default) leaves players
 *   who were already multiactive active -- $players is added to them --
 *   while true deactivates them, so the multiactive set ends up being
 *   exactly $players.
 *
 * - WHETHER the machine moves on.  That is driven by the ARGUMENT, not
 *   by the flags the call leaves behind: a non-empty $players ignores
 *   $next_state entirely, and an empty one takes the transition.
 */
class SetPlayersMultiactiveTest extends IntegrationTestCase
{
    const LOCALARENA_GAME_NAME = 'localarenanoop';

    const ST_MULTI = 20;  // multipleactiveplayer; where these tests run
    const ST_DONE = 21;   // where "tDone" leads

    protected function defaultTableParams(): \LocalArena\TableParams
    {
        $params = parent::defaultTableParams();
        // N.B.: The harness seats LOCALARENA_PLAYER_COUNT players
        // whatever `TableParams::$playerCount` says, so these tests are
        // written for the two players it gives us: in each of them one
        // player stands for "the players named in the call" and the
        // other for "everyone else".
        $params->table_class = SetPlayersMultiactiveTestGame::class;
        return $params;
    }

    private function game(): SetPlayersMultiactiveTestGame
    {
        return $this->table();
    }

    // The player ids, in seating order.
    private function playerId(int $index): int
    {
        return intval($this->playerByIndex($index)->id());
    }

    // The multiactive players, as ints and in a stable order, so that
    // they can be compared against an expected set.
    private function activeIds(): array
    {
        $ids = array_map('intval', $this->game()->gamestate->getActivePlayerList());
        sort($ids);
        return $ids;
    }

    // Makes exactly $player_ids multiactive, without going through the
    // method under test.
    private function givenMultiactive(array $player_ids): void
    {
        $game = $this->game();
        $game->DbQuery('UPDATE `player` SET `player_is_multiactive` = 0');
        if (count($player_ids) > 0) {
            $game->DbQuery(
                'UPDATE `player` SET `player_is_multiactive` = 1 WHERE `player_id` IN (' .
                    implode(',', $player_ids) .
                    ')'
            );
        }
    }

    /**
     * The default (and `$bExclusive` false) is ADDITIVE: players who
     * were already multiactive stay that way.
     */
    public function testNonExclusiveAddsToTheAlreadyActivePlayers(): void
    {
        $this->givenMultiactive([$this->playerId(0)]);

        $this->game()->gamestate->setPlayersMultiactive(
            [$this->playerId(1)],
            'tDone',
            /*bExclusive=*/ false
        );

        $expected = [$this->playerId(0), $this->playerId(1)];
        sort($expected);
        $this->assertEquals(
            $expected,
            $this->activeIds(),
            'A non-exclusive call must not deactivate players who were already active.'
        );
        $this->assertGameState(self::ST_MULTI);
    }

    /**
     * Omitting $bExclusive is the same as passing false -- so the
     * default is the additive case, NOT the exclusive one.
     */
    public function testDefaultsToNonExclusive(): void
    {
        $this->givenMultiactive([$this->playerId(0)]);

        $this->game()->gamestate->setPlayersMultiactive([$this->playerId(1)], 'tDone');

        $expected = [$this->playerId(0), $this->playerId(1)];
        sort($expected);
        $this->assertEquals($expected, $this->activeIds());
    }

    /**
     * With $bExclusive true, the players multiactive afterwards are
     * exactly those named: everyone else is deactivated.
     */
    public function testExclusiveDeactivatesTheOtherPlayers(): void
    {
        $this->givenMultiactive([$this->playerId(0), $this->playerId(1)]);

        $this->game()->gamestate->setPlayersMultiactive(
            [$this->playerId(1)],
            'tDone',
            /*bExclusive=*/ true
        );

        $this->assertEquals(
            [$this->playerId(1)],
            $this->activeIds(),
            'An exclusive call must leave exactly the named players active.'
        );
        $this->assertGameState(self::ST_MULTI);
    }

    /**
     * "Exactly $players" cuts both ways: an exclusive call activates a
     * named player who was not active before, in the same breath as it
     * deactivates an unnamed player who was.
     */
    public function testExclusiveActivatesTheNamedPlayerAndDeactivatesTheRest(): void
    {
        $this->givenMultiactive([$this->playerId(0)]);

        $this->game()->gamestate->setPlayersMultiactive(
            [$this->playerId(1)],
            'tDone',
            /*bExclusive=*/ true
        );

        $this->assertEquals([$this->playerId(1)], $this->activeIds());
    }

    /**
     * With a non-empty $players the transition is NOT taken, however
     * many players are active before or after -- $next_state is ignored
     * entirely, so it need not even name a valid transition.
     */
    public function testDoesNotTransitionWhenPlayersIsNotEmpty(): void
    {
        $this->givenMultiactive([]);

        $transitioned = $this->game()->gamestate->setPlayersMultiactive(
            [$this->playerId(1)],
            'tThisTransitionDoesNotExist',
            /*bExclusive=*/ true
        );

        $this->assertFalse($transitioned);
        $this->assertGameState(
            self::ST_MULTI,
            'A call naming players must stay put, whatever was passed for $next_state.'
        );
    }

    /**
     * An empty $players takes the transition.  Being exclusive, it also
     * leaves nobody active -- which is the usual way a state is left
     * when it turns out there is nothing for anyone to do.
     */
    public function testEmptyPlayersExclusiveDeactivatesEveryoneAndTransitions(): void
    {
        $this->givenMultiactive([$this->playerId(0), $this->playerId(1)]);

        $transitioned = $this->game()->gamestate->setPlayersMultiactive(
            [],
            'tDone',
            /*bExclusive=*/ true
        );

        $this->assertTrue($transitioned);
        $this->assertGameState(self::ST_DONE);
        $this->assertEquals(
            0,
            intval(
                $this->game()->getUniqueValueFromDB(
                    'SELECT COUNT(*) FROM `player` WHERE `player_is_multiactive` = 1'
                )
            ),
            'An exclusive call with no players must leave nobody multiactive.'
        );
    }

    /**
     * An empty $players takes the transition even when the call is
     * non-exclusive and so leaves players carrying the multiactive
     * flag: it is the empty argument that moves the machine, not the
     * absence of active players.
     */
    public function testEmptyPlayersNonExclusiveTransitionsAnyway(): void
    {
        $this->givenMultiactive([$this->playerId(0)]);

        $transitioned = $this->game()->gamestate->setPlayersMultiactive(
            [],
            'tDone',
            /*bExclusive=*/ false
        );

        $this->assertTrue($transitioned);
        $this->assertGameState(self::ST_DONE);
        $this->assertEquals(
            1,
            intval(
                $this->game()->getUniqueValueFromDB(
                    'SELECT COUNT(*) FROM `player` WHERE `player_is_multiactive` = 1'
                )
            ),
            'A non-exclusive call must not clear the flags of players who were already active.'
        );
    }
}

/**
 * A localarenanoop subclass whose only job is to install a state
 * machine that starts in a multiactive state, so that
 * `setPlayersMultiactive()` can be called there and the state it
 * transitions to can be observed.  Used only by
 * SetPlayersMultiactiveTest.
 */
class SetPlayersMultiactiveTestGame extends \localarenanoop
{
    public function __construct()
    {
        parent::__construct();

        $this->gamestate = new \GameState($this, self::multiactiveMachineStates());
    }

    private static function multiactiveMachineStates(): array
    {
        return [
            // Initial state; base Table::stGameSetup() runs here and
            // transitions straight into the multiactive state.
            1 => [
                'name' => 'gameSetup',
                'description' => '',
                'type' => 'manager',
                'action' => 'stGameSetup',
                'transitions' => ['' => SetPlayersMultiactiveTest::ST_MULTI],
            ],

            SetPlayersMultiactiveTest::ST_MULTI => [
                'name' => 'stMulti',
                'description' => '',
                'type' => 'multipleactiveplayer',
                'possibleactions' => [],
                'transitions' => ['tDone' => SetPlayersMultiactiveTest::ST_DONE],
            ],

            SetPlayersMultiactiveTest::ST_DONE => [
                'name' => 'stDone',
                'description' => '',
                'type' => 'activeplayer',
                'possibleactions' => [],
                'transitions' => [],
            ],
        ];
    }
}

<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/../module/test/IntegrationTestCase.php';

// We extend the bundled "localarenanoop" harness game, so load its
// class.  When a test supplies a `table_class`, TableManager::getTable()
// instantiates that class directly and does NOT require the game's
// .game.php file, so we must require it ourselves before subclassing.
require_once LOCALARENA_GAME_PATH . 'localarenanoop/localarenanoop.game.php';

/**
 * Tests for `$this->checkAction($actionName, $bThrowException)` -- the
 * gate every game action handler is supposed to call first.
 *
 * It must verify BOTH of these, and refuse when either fails:
 *
 * - The current player is entitled to act right now:
 *     - "activeplayer" state          -> they are THE active player;
 *     - "multipleactiveplayer" state  -> they are IN the multiactive set;
 *     - "game"/"manager" state        -> nobody may act.
 *   A player acting out of turn is a user error ("It is not your turn",
 *   BgaUserException), not a system error.
 *
 * - The action is listed in the current state's `possibleactions`.
 *
 * Regression history: checkAction() used to be a silent no-op that
 * accepted every action from every player in every state, because two
 * bugs cancelled each other.  Its condition was inverted (it would have
 * rejected exactly the LEGITIMATE case), but the inversion was masked
 * because `GameState::checkPossibleAction()` looked the action name up
 * as a KEY of `possibleactions` -- which is a plain value-list -- and
 * so never recognized any action as possible.  Happy-path suites
 * passed for the wrong reason, and nothing asserted a rejection until
 * a cross-seat prompt test in a real game caught a non-active player's
 * action being accepted.  These tests pin down both halves.
 */
class CheckActionTest extends IntegrationTestCase
{
    const LOCALARENA_GAME_NAME = 'localarenanoop';

    // State ids used by the machine installed below.
    const ST_ACTIVE = 30;  // activeplayer; entry state
    const ST_MULTI = 31;   // multipleactiveplayer
    const ST_GAME = 32;    // game-type; nobody may act here

    protected function defaultTableParams(): \LocalArena\TableParams
    {
        $params = parent::defaultTableParams();
        // Reuse all of localarenanoop's files (gameinfos/dbmodel/action
        // class etc.), but instantiate our subclass, whose machine has
        // one state of each type and a known `possibleactions` list.
        $params->table_class = CheckActionTestGame::class;
        return $params;
    }

    private function game(): CheckActionTestGame
    {
        return $this->table();
    }

    // The player ids, in seating order.
    private function playerId(int $index): int
    {
        return intval($this->playerByIndex($index)->id());
    }

    // Makes exactly $player_ids multiactive, without going through
    // gamestate methods.
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

    // Makes the request-level "current player" (the player the framework
    // believes submitted the request) be $player_id, the way a real
    // request would (cf. `Table::doAction()`).
    private function asCurrentPlayer(int $player_id): void
    {
        $this->game()->currentPlayer = $player_id;
    }

    // ==================== "activeplayer" states ====================

    public function testTheActivePlayerMayAct(): void
    {
        $this->assertGameState(self::ST_ACTIVE);
        $this->game()->changeActivePlayer($this->playerId(0));
        $this->asCurrentPlayer($this->playerId(0));

        $this->assertTrue($this->game()->checkAction('actLegit'));
    }

    public function testAnotherPlayerIsRefusedInAnActiveplayerState(): void
    {
        $this->assertGameState(self::ST_ACTIVE);
        $this->game()->changeActivePlayer($this->playerId(0));
        $this->asCurrentPlayer($this->playerId(1));

        $this->expectException(\BgaUserException::class);
        $this->game()->checkAction('actLegit');
    }

    public function testRefusalReturnsFalseInsteadOfThrowingWhenAsked(): void
    {
        $this->game()->changeActivePlayer($this->playerId(0));
        $this->asCurrentPlayer($this->playerId(1));

        $this->assertFalse(
            $this->game()->checkAction('actLegit', /*bThrowException=*/ false),
            'With $bThrowException false a refusal must be reported as a return value, not an exception.'
        );
    }

    // ==================== "multipleactiveplayer" states ====================

    public function testAMultiactivePlayerMayAct(): void
    {
        $this->game()->gamestate->jumpToState(self::ST_MULTI);
        $this->givenMultiactive([$this->playerId(0), $this->playerId(1)]);
        $this->asCurrentPlayer($this->playerId(1));

        $this->assertTrue($this->game()->checkAction('actLegit'));
    }

    /**
     * The regression that exposed all of this: a player OUTSIDE the
     * multiactive set submits an action to a multipleactiveplayer
     * state, and must be refused.  The multiactive set is the whole of
     * what can be enforced -- the action carries no seat, so who sent
     * it is the only identity the server has.
     */
    public function testANonMultiactivePlayerIsRefused(): void
    {
        $this->game()->gamestate->jumpToState(self::ST_MULTI);
        $this->givenMultiactive([$this->playerId(1)]);

        // Premise, as in the game suite that caught this: the set holds
        // exactly the other player.
        $this->assertEquals(
            [$this->playerId(1)],
            array_map('intval', $this->game()->gamestate->getActivePlayerList())
        );

        $this->asCurrentPlayer($this->playerId(0));
        $this->expectException(\BgaUserException::class);
        $this->game()->checkAction('actLegit');
    }

    // ==================== "game"-type states ====================

    public function testNobodyMayActInAGameTypeState(): void
    {
        // Even the player named by the active-player global is refused:
        // in a game-type state there is no such thing as an entitled
        // player.
        $this->game()->changeActivePlayer($this->playerId(0));
        $this->game()->gamestate->jumpToState(self::ST_GAME, /*bWithActions=*/ false);
        $this->asCurrentPlayer($this->playerId(0));

        $this->expectException(\BgaUserException::class);
        $this->game()->checkAction('actLegit');
    }

    // ==================== `possibleactions` membership ====================

    public function testAnUnlistedActionIsRefusedEvenForTheActivePlayer(): void
    {
        $this->game()->changeActivePlayer($this->playerId(0));
        $this->asCurrentPlayer($this->playerId(0));

        $this->assertFalse(
            $this->game()->checkAction('actUnlisted', /*bThrowException=*/ false)
        );
        $this->expectException(\feException::class);
        $this->game()->checkAction('actUnlisted');
    }

    /**
     * Regression: `possibleactions` is a plain LIST of action names
     * (numeric keys), so `checkPossibleAction()` must match by value.
     * It used to test `isset($possibleactions[$action])` -- a lookup by
     * key -- and therefore never recognized any action as possible.
     */
    public function testCheckPossibleActionMatchesTheActionListByValue(): void
    {
        $gamestate = $this->game()->gamestate;

        $this->assertTrue($gamestate->checkPossibleAction('actLegit', /*bThrowException=*/ false));
        $this->assertFalse($gamestate->checkPossibleAction('actUnlisted', /*bThrowException=*/ false));

        // And in throwing mode, a listed action must NOT throw.
        $this->assertTrue($gamestate->checkPossibleAction('actLegit'));
    }

    // ==================== End to end, through a real request ====================

    /**
     * The same refusal, driven the way a real client drives it: through
     * `doAction()`, which sets the current player from the request.
     * The rejection must also leave the game untouched -- the request's
     * transaction rolls back.
     */
    public function testARequestFromASeatOutsideTheMultiactiveSetIsRejected(): void
    {
        $this->game()->gamestate->jumpToState(self::ST_MULTI);
        $this->game()->flushCurrentStateGlobal();
        $this->givenMultiactive([$this->playerId(1)]);

        try {
            $this->playerByIndex(0)->act('actTestCheckAction', ['action_name' => 'actLegit']);
            $this->fail('Expected the out-of-turn request to be rejected.');
        } catch (\BgaUserException $exc) {
            // Expected.
        }

        $this->assertGameState(self::ST_MULTI);
        $this->assertEquals(
            [$this->playerId(1)],
            array_map('intval', $this->game()->gamestate->getActivePlayerList()),
            'A rejected request must not change who is active.'
        );
    }

    public function testARequestFromASeatInsideTheMultiactiveSetIsAccepted(): void
    {
        $this->game()->gamestate->jumpToState(self::ST_MULTI);
        $this->game()->flushCurrentStateGlobal();
        $this->givenMultiactive([$this->playerId(0), $this->playerId(1)]);

        $this->playerByIndex(1)->act('actTestCheckAction', ['action_name' => 'actLegit']);

        $this->assertGameState(self::ST_MULTI);
    }
}

/**
 * A localarenanoop subclass whose only job is to install a state
 * machine with one state of each type and a known `possibleactions`
 * list, so that `checkAction()` can be exercised in all of them.  Used
 * only by CheckActionTest.
 */
class CheckActionTestGame extends \localarenanoop
{
    public function __construct()
    {
        parent::__construct();

        $this->gamestate = new \GameState($this, self::checkActionMachineStates());
    }

    private static function checkActionMachineStates(): array
    {
        return [
            // Initial state; base Table::stGameSetup() runs here and
            // transitions straight into the activeplayer state.
            1 => [
                'name' => 'gameSetup',
                'description' => '',
                'type' => 'manager',
                'action' => 'stGameSetup',
                'transitions' => ['' => CheckActionTest::ST_ACTIVE],
            ],

            CheckActionTest::ST_ACTIVE => [
                'name' => 'stActive',
                'description' => '',
                'type' => 'activeplayer',
                'possibleactions' => ['actLegit', 'actOther'],
                'transitions' => [],
            ],

            CheckActionTest::ST_MULTI => [
                'name' => 'stMulti',
                'description' => '',
                'type' => 'multipleactiveplayer',
                'possibleactions' => ['actLegit'],
                'transitions' => [],
            ],

            CheckActionTest::ST_GAME => [
                'name' => 'stGame',
                'description' => '',
                'type' => 'game',
                'possibleactions' => ['actLegit'],
                'transitions' => [],
            ],
        ];
    }
}

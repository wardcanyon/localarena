<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/../module/test/IntegrationTestCase.php';

// We extend the bundled "localarenanoop" harness game, so load its
// class.  When a test supplies a `table_class`, TableManager::getTable()
// instantiates that class directly and does NOT require the game's
// .game.php file, so we must require it ourselves before subclassing.
require_once LOCALARENA_GAME_PATH . 'localarenanoop/localarenanoop.game.php';

/**
 * Tests for BGA's limit on the size of the notifications one request
 * may generate (see `module/table/LocalArenaNotifBudget.php`).
 *
 * Notifications are not sent as game code produces them: they are
 * bundled, and the bundle goes out when the request finishes -- so it
 * covers the action AND every state transition that followed it.  If
 * that bundle comes to more than 128 KiB, BGA fails the request with
 *
 *   generated notifications are larger than 128k (140501)
 *
 * and the move is lost.  What makes the limit worth modelling is that
 * no single notify call need look suspicious: a cascade of "game"-type
 * states that each send a few kilobytes can overrun it between them,
 * which is exactly the shape of failure these tests pin down.
 *
 * Most of the tests here run under a deliberately small limit (set
 * through `TableParams`) so that they need not generate 128 KiB of
 * padding; `testEnforcesBgasOwnLimitEndToEnd` and
 * `testUsesBgasWordingAtBgasLimit` cover the real thing.
 */
class NotifSizeLimitTest extends IntegrationTestCase
{
    const LOCALARENA_GAME_NAME = 'localarenanoop';

    // State ids used by the cascade machine installed below.
    const ST_INPUT = 2;      // entry / input state (activeplayer)
    const ST_CHATTY_A = 10;  // game-type; sends a notif, then moves on
    const ST_CHATTY_B = 11;  // game-type; likewise
    const ST_CHATTY_C = 12;  // game-type; likewise
    const ST_PARKED = 13;    // activeplayer; where the cascade parks

    // The limit these tests run under.  Small enough that a test can
    // exceed it with a few kilobytes of padding, and a whole number of
    // kibibytes so that the failure message reads the way BGA's does.
    const TEST_LIMIT_BYTES = 16 * 1024;

    // How much padding each state of the cascade sends.  Individually
    // this is far below the limit; three of them together are over it.
    const CASCADE_NOTIF_BYTES = 6 * 1024;

    protected function defaultTableParams(): \LocalArena\TableParams
    {
        $params = parent::defaultTableParams();
        // Reuse all of localarenanoop's files (states/gameinfos/dbmodel/
        // action class etc.), but instantiate our talkative subclass
        // instead of the plain game class.
        $params->table_class = ChattyStateTestGame::class;
        $params->notif_size_limit = self::TEST_LIMIT_BYTES;
        return $params;
    }

    private function game(): ChattyStateTestGame
    {
        return $this->table();
    }

    private function budget(): \LocalArenaNotifBudget
    {
        return $this->table()->localarenaNotifBudget();
    }

    private function gamelogCount(): int
    {
        return intval($this->table()->getUniqueValueFromDB('SELECT COUNT(*) FROM `gamelog`'));
    }

    private function moveId(): int
    {
        return intval($this->table()->getGameStateValue('moveId'));
    }

    //////////////////////////////////////////////////////////////////
    // The books LocalArena keeps.

    /**
     * The default limit is BGA's, and `TableParams` can override it.
     */
    public function testTheLimitDefaultsToBgasAndCanBeSetByTableParams(): void
    {
        $this->assertSame(131072, \LocalArenaNotifBudget::BGA_LIMIT_BYTES, "BGA's limit is 128 KiB.");
        $this->assertSame(
            \LocalArenaNotifBudget::BGA_LIMIT_BYTES,
            (new \LocalArenaNotifBudget())->limit(),
            'A table should be held to BGA\'s limit unless it is asked for something else.'
        );

        // This test case's table asked for something else.
        $this->assertSame(self::TEST_LIMIT_BYTES, $this->budget()->limit());
        $this->assertTrue($this->budget()->enforced());
    }

    /**
     * Every notification a request generates is counted, and is
     * attributed to its notification type.
     */
    public function testCountsTheNotificationsARequestGenerates(): void
    {
        $this->playerByIndex(0)->act('actTestNotifs', ['count' => 3, 'bytes' => 100]);

        $budget = $this->budget();
        $this->assertSame(3, $budget->count());
        $this->assertGreaterThan(3 * 100, $budget->total());
        $this->assertFalse($budget->exceeded());

        $breakdown = $budget->breakdown();
        $this->assertSame(['testPadding'], array_keys($breakdown));
        $this->assertSame(3, $breakdown['testPadding']['count']);
        $this->assertSame($budget->total(), $breakdown['testPadding']['bytes']);
    }

    /**
     * The bundle is per request: each new request starts from zero, so
     * a game that sends a lot over many moves is in no danger.  (It is
     * one action's worth of notifications that is capped, not a
     * table's.)
     */
    public function testTheBudgetStartsOverForEachRequest(): void
    {
        $this->playerByIndex(0)->act('actTestNotifs', ['count' => 4, 'bytes' => 1000]);
        $first_total = $this->budget()->total();
        $this->assertGreaterThan(4 * 1000, $first_total);

        $this->playerByIndex(0)->act('actTestNotifs', ['count' => 1, 'bytes' => 10]);

        $this->assertSame(1, $this->budget()->count());
        $this->assertLessThan($first_total, $this->budget()->total());
    }

    /**
     * The framework's own notifications -- the state-change notifs
     * `enterState()` sends -- are part of the bundle too, as they are
     * on BGA.  A game does not get the whole allowance to itself.
     */
    public function testFrameworkStateChangeNotifsCountTowardTheBundle(): void
    {
        $this->playerByIndex(0)->act('actTestTransition', ['transition' => 'step']);

        $this->assertGameState(self::ST_PARKED);
        $this->assertArrayHasKey('gameStateChange', $this->budget()->breakdown());
        $this->assertGreaterThan(0, $this->budget()->total());
    }

    /**
     * A private notification costs the same as one to everybody: what
     * is counted is the notification the game GENERATED, not the number
     * of players it will be delivered to.
     */
    public function testPrivateNotifsAreCountedOnce(): void
    {
        $budget = $this->budget();
        $budget->reset();

        $this->table()->notifyPlayer($this->playerByIndex(0)->id(), 'privateUpdate', '', [
            'padding' => str_repeat('x', 500),
        ]);

        $this->assertSame(1, $budget->count());
        $this->assertGreaterThan(500, $budget->total());
        $this->assertLessThan(2 * 500, $budget->total());
        $this->assertSame(['privateUpdate'], array_keys($budget->breakdown()));
    }

    /**
     * Notifications generated outside of a request -- during table
     * creation, or by a test poking at the game directly -- are not
     * part of any bundle, and must not be charged to the next request.
     */
    public function testNotifsGeneratedOutsideARequestDoNotCountAgainstTheNextOne(): void
    {
        // Enough to blow the limit several times over, were it charged
        // to a request.
        $this->table()->notifyAllPlayers('outOfBand', '', [
            'padding' => str_repeat('x', 4 * self::TEST_LIMIT_BYTES),
        ]);
        $this->assertGreaterThan(self::TEST_LIMIT_BYTES, $this->budget()->total());

        // The next request neither fails nor inherits any of it.
        $this->playerByIndex(0)->act('actTestNotifs', ['count' => 1, 'bytes' => 10]);

        $this->assertSame(1, $this->budget()->count());
        $this->assertSame(['testPadding'], array_keys($this->budget()->breakdown()));
    }

    //////////////////////////////////////////////////////////////////
    // Enforcement.

    /**
     * A request that stays within the limit is unremarkable: it
     * commits, and its notifications reach the gamelog.
     */
    public function testARequestWithinTheLimitSucceeds(): void
    {
        $before = $this->gamelogCount();

        $this->playerByIndex(0)->act('actTestNotifs', ['count' => 1, 'bytes' => 8000]);

        $this->assertFalse($this->budget()->exceeded());
        $this->assertLessThan(self::TEST_LIMIT_BYTES, $this->budget()->total());
        $this->assertSame($before + 1, $this->gamelogCount());
    }

    /**
     * A request that generates more than the limit fails, with BGA's
     * message.
     */
    public function testARequestOverTheLimitFails(): void
    {
        try {
            $this->playerByIndex(0)->act('actTestNotifs', ['count' => 4, 'bytes' => 6000]);
            $this->fail('Expected an over-budget request to fail.');
        } catch (\LocalArenaNotifSizeLimitException $exc) {
            $this->assertGreaterThan(self::TEST_LIMIT_BYTES, $exc->totalBytes());
            $this->assertSame(self::TEST_LIMIT_BYTES, $exc->limitBytes());
            $this->assertSame(
                'generated notifications are larger than 16k (' . $exc->totalBytes() . ')',
                $exc->getMessage(),
                "The failure should read the way BGA's does."
            );

            // Better than BGA manages: the failure says what the
            // bundle was made of.
            $this->assertSame(4, $exc->breakdown()['testPadding']['count']);
        }
    }

    /**
     * BGA reports this as an unexpected (system) error rather than as
     * anything the player did wrong, so game code that catches the
     * framework's system-exception type catches this too.
     */
    public function testTheFailureIsASystemException(): void
    {
        $this->expectException(\BgaVisibleSystemException::class);
        $this->playerByIndex(0)->act('actTestNotifs', ['count' => 4, 'bytes' => 6000]);
    }

    /**
     * The move is lost: an over-budget request leaves nothing behind --
     * no notifications in the gamelog, no move, and the state machine
     * back where the request found it.
     */
    public function testAnOverBudgetRequestIsRolledBackEntirely(): void
    {
        $gamelog_before = $this->gamelogCount();
        $move_before = $this->moveId();

        try {
            $this->playerByIndex(0)->act('actTestTransition', ['transition' => 'go']);
            $this->fail('Expected the chatty cascade to exceed the limit.');
        } catch (\LocalArenaNotifSizeLimitException $exc) {
            // Expected; see testTheLimitCoversTheWholeCascade.
        }

        $this->assertSame($gamelog_before, $this->gamelogCount(), 'A failed request must send no notifications.');
        $this->assertSame($move_before, $this->moveId(), 'A failed request must not consume a move.');
        $this->assertGameState(self::ST_INPUT, 'A failed request must leave the state machine where it was.');
        $this->assertSame(
            self::ST_INPUT,
            intval($this->table()->getGameStateValue('currentState')),
            'The current-state global must be back where the failed request found it.'
        );

        // And the table is still usable afterwards: the next request
        // works, from that same state.
        $this->playerByIndex(0)->act('actTestTransition', ['transition' => 'step']);
        $this->assertGameState(self::ST_PARKED);
    }

    /**
     * The heart of it: the limit covers the whole cascade an action
     * sets off, not each notification on its own.  Every state here
     * sends a notification that is comfortably within the limit; the
     * request fails because there are three of them (plus the
     * framework's own state-change notifs).
     */
    public function testTheLimitCoversTheWholeCascade(): void
    {
        try {
            $this->playerByIndex(0)->act('actTestTransition', ['transition' => 'go']);
            $this->fail('Expected the chatty cascade to exceed the limit.');
        } catch (\LocalArenaNotifSizeLimitException $exc) {
            $breakdown = $exc->breakdown();

            // All three states ran -- the cascade is not cut short
            // mid-flight; it is the bundle it produced that is refused.
            $this->assertSame(3, $breakdown['cascadePadding']['count']);
            $this->assertSame(
                ['stChattyA', 'stChattyB', 'stChattyC'],
                $this->game()->entered
            );

            // No single one of those notifications was anywhere near
            // the limit...
            $this->assertLessThan(
                self::TEST_LIMIT_BYTES,
                intdiv($breakdown['cascadePadding']['bytes'], 3),
                'The premise of this test is that no individual notification is over the limit.'
            );
            // ...but together with the state-change notifs the
            // transitions themselves generated, they are.
            $this->assertArrayHasKey('gameStateChange', $breakdown);
            $this->assertGreaterThan(self::TEST_LIMIT_BYTES, $exc->totalBytes());
        }
    }

    /**
     * End-to-end at BGA's own limit, rather than the small one the rest
     * of these tests use: 128 KiB of notifications in one request is
     * fine, and appreciably more than that is not.
     */
    public function testEnforcesBgasOwnLimitEndToEnd(): void
    {
        $this->table()->localarenaSetNotifSizeLimit(\LocalArenaNotifBudget::BGA_LIMIT_BYTES);

        // ~100 KiB in one request: large, but allowed.
        $this->playerByIndex(0)->act('actTestNotifs', ['count' => 100, 'bytes' => 1024]);
        $this->assertGreaterThan(100 * 1024, $this->budget()->total());
        $this->assertFalse($this->budget()->exceeded());

        // ~140 KiB is not -- and reads exactly like the real thing.
        try {
            $this->playerByIndex(0)->act('actTestNotifs', ['count' => 140, 'bytes' => 1024]);
            $this->fail('Expected 140 KiB of notifications to exceed BGA\'s limit.');
        } catch (\LocalArenaNotifSizeLimitException $exc) {
            $this->assertStringStartsWith('generated notifications are larger than 128k (', $exc->getMessage());
        }
    }

    /**
     * The exact wording BGA uses, pinned against a real report from the
     * site.  Games (and their tests) may well match on this string.
     */
    public function testUsesBgasWordingAtBgasLimit(): void
    {
        $budget = new \LocalArenaNotifBudget();
        $budget->record('bigUpdate', 140501);

        try {
            $budget->requireWithinLimit();
            $this->fail('Expected 140501 bytes to exceed the 128 KiB limit.');
        } catch (\LocalArenaNotifSizeLimitException $exc) {
            $this->assertSame('generated notifications are larger than 128k (140501)', $exc->getMessage());
        }
    }

    /**
     * The check can be turned off, for a test that deliberately
     * generates enormous notifications (or a game not ready to face the
     * limit yet).
     */
    public function testTheLimitCanBeDisabled(): void
    {
        $this->table()->localarenaSetNotifSizeLimit(\LocalArenaNotifBudget::NO_LIMIT);
        $this->assertFalse($this->budget()->enforced());

        $gamelog_before = $this->gamelogCount();
        $this->playerByIndex(0)->act('actTestNotifs', ['count' => 4, 'bytes' => 6000]);

        $this->assertGreaterThan(self::TEST_LIMIT_BYTES, $this->budget()->total());
        $this->assertFalse($this->budget()->exceeded());
        $this->assertSame($gamelog_before + 4, $this->gamelogCount());
    }

    /**
     * A limit is a size, so it cannot be negative; `NO_LIMIT` (zero) is
     * how you ask for no limit.
     */
    public function testRejectsANegativeLimit(): void
    {
        $this->expectException(\feException::class);
        $this->table()->localarenaSetNotifSizeLimit(-1);
    }

    /**
     * The breakdown LocalArena logs when a request goes over budget
     * (BGA reports only a total).
     */
    public function testDescribesWhatTheBundleWasMadeOf(): void
    {
        $budget = new \LocalArenaNotifBudget(self::TEST_LIMIT_BYTES);
        $budget->record('small', 10);
        $budget->record('big', 5000);
        $budget->record('big', 5000);

        $description = $budget->describe();
        $this->assertStringContainsString('10010 bytes in 3 notification(s)', $description);
        $this->assertStringContainsString('limit is 16384 bytes', $description);
        // Biggest contributor first, so the expensive notif type is
        // the first thing you see.
        $this->assertStringContainsString("\n  big: 10000 bytes in 2 notification(s)\n  small: 10 bytes", $description);
    }
}

/**
 * A localarenanoop subclass whose only job is to install a state
 * machine whose "game"-type states each send a sizable notification on
 * the way past, so that one action can set off a cascade that
 * overruns the per-request notification budget.  Used only by
 * NotifSizeLimitTest.
 */
class ChattyStateTestGame extends \localarenanoop
{
    /**
     * The names of the cascade states whose action (st*) methods have
     * run, in order.
     *
     * @var array<int, string>
     */
    public array $entered = [];

    public function __construct()
    {
        parent::__construct();

        // Replace the trivial noop machine with one that cascades
        // through several talkative states.
        $this->gamestate = new \GameState($this, self::chattyMachineStates());
    }

    private static function chattyMachineStates(): array
    {
        return [
            // Initial state; base Table::stGameSetup() runs here and
            // transitions to the input state.
            1 => [
                'name' => 'gameSetup',
                'description' => '',
                'type' => 'manager',
                'action' => 'stGameSetup',
                'transitions' => ['' => NotifSizeLimitTest::ST_INPUT],
            ],

            // Entry / input state: requests start here.
            NotifSizeLimitTest::ST_INPUT => [
                'name' => 'stInput',
                'description' => '',
                'type' => 'activeplayer',
                'possibleactions' => ['actTestTransition', 'actTestNotifs'],
                'transitions' => [
                    // Sets off the talkative cascade.
                    'go' => NotifSizeLimitTest::ST_CHATTY_A,
                    // A single quiet hop to another input state, for
                    // tests that want a transition without the noise.
                    'step' => NotifSizeLimitTest::ST_PARKED,
                ],
            ],

            NotifSizeLimitTest::ST_CHATTY_A => [
                'name' => 'stChattyA',
                'description' => '',
                'type' => 'game',
                'action' => 'stChattyA',
                'transitions' => ['next' => NotifSizeLimitTest::ST_CHATTY_B],
            ],
            NotifSizeLimitTest::ST_CHATTY_B => [
                'name' => 'stChattyB',
                'description' => '',
                'type' => 'game',
                'action' => 'stChattyB',
                'transitions' => ['next' => NotifSizeLimitTest::ST_CHATTY_C],
            ],
            NotifSizeLimitTest::ST_CHATTY_C => [
                'name' => 'stChattyC',
                'description' => '',
                'type' => 'game',
                'action' => 'stChattyC',
                'transitions' => ['next' => NotifSizeLimitTest::ST_PARKED],
            ],

            // Where the cascade parks (awaiting player input again).
            NotifSizeLimitTest::ST_PARKED => [
                'name' => 'stParked',
                'description' => '',
                'type' => 'activeplayer',
                'possibleactions' => ['actTestNotifs'],
                'transitions' => [],
            ],
        ];
    }

    public function stChattyA(): void
    {
        $this->sendCascadeNotif('stChattyA');
    }

    public function stChattyB(): void
    {
        $this->sendCascadeNotif('stChattyB');
    }

    public function stChattyC(): void
    {
        $this->sendCascadeNotif('stChattyC');
    }

    // Send a notification that is well within the limit on its own,
    // then continue the cascade.  A game doing this in three states in
    // a row looks entirely reasonable, one state at a time.
    private function sendCascadeNotif(string $name): void
    {
        $this->entered[] = $name;
        $this->notifyAllPlayers('cascadePadding', '', [
            'state' => $name,
            'padding' => str_repeat('x', NotifSizeLimitTest::CASCADE_NOTIF_BYTES),
        ]);
        $this->gamestate->nextState('next');
    }
}

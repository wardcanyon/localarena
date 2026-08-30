<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/../module/test/IntegrationTestCase.php';

/**
 * Tests for the notification path: `notifyAllPlayers()`,
 * `notifyPlayer()`, the `gamelog` entries they produce, and the
 * post-commit delivery in `Table::sendCommittedNotifs()`.
 *
 * Notifications are how a game tells its clients what happened, so
 * this is the framework surface a game leans on most after the state
 * machine.  Two properties matter and are easy to get wrong:
 *
 * - Nothing is sent inline.  Both functions only append to the
 *   `gamelog`; delivery happens after the request's transaction
 *   commits.  An action that throws must therefore announce nothing at
 *   all, however far it got.
 *
 * - Who receives what is decided per player, at delivery time.  A
 *   `notifyPlayer()` entry reaches only its addressee, and private
 *   data inside an entry is rendered separately for each recipient.
 */
class NotificationTest extends IntegrationTestCase
{
    const LOCALARENA_GAME_NAME = 'localarenanoop';

    // Announces $notifs (see `actTestNotify()`) as player 0, and
    // returns the notifications that the action produced.
    private function announce(array $notifs, array $extra_args = []): array
    {
        $marker = $this->notifMarker();
        $this->playerByIndex(0)->act('actTestNotify', array_merge(['notifs' => $notifs], $extra_args));
        return $this->notifsSince($marker);
    }

    //////////////////////////////////////////////////////////////////
    // What lands in the gamelog.

    public function testNotifyAllPlayersRecordsAPublicEntry(): void
    {
        $notifs = $this->announce([['type' => 'testEvent', 'log' => 'something happened', 'args' => ['n' => 7]]]);

        $this->assertCount(1, $notifs);
        $this->assertSame('testEvent', $notifs[0]->type());
        $this->assertSame('something happened', $notifs[0]->log());
        $this->assertSame(['n' => 7], $notifs[0]->args());

        // A null recipient is BGA's "main channel": every player at the
        // table receives it.
        $this->assertNull($notifs[0]->recipient());
        $this->assertFalse($notifs[0]->isPrivate());
    }

    public function testNotifyPlayerRecordsAnEntryAddressedToThatPlayer(): void
    {
        $player1 = $this->playerByIndex(1);

        $notifs = $this->announce([['type' => 'testSecret', 'player' => $player1->id(), 'args' => ['n' => 1]]]);

        $this->assertCount(1, $notifs);
        $this->assertTrue($notifs[0]->isPrivate());
        $this->assertSame($player1->id(), $notifs[0]->recipient());
    }

    /**
     * Notifications come back in the order they were announced, which
     * is the order clients will replay them in.
     */
    public function testPreservesTheOrderOfAnnouncement(): void
    {
        $notifs = $this->announce([
            ['type' => 'first'],
            ['type' => 'second'],
            ['type' => 'third'],
        ]);

        $this->assertNotifTypes(['first', 'second', 'third'], $notifs);
    }

    /**
     * Every notification announced during one action carries that
     * action's move id, and a later action's carry a higher one.  The
     * client uses this to group a turn's notifications together.
     */
    public function testNotificationsCarryTheMoveIdOfTheirAction(): void
    {
        $first = $this->announce([['type' => 'a'], ['type' => 'b']]);
        $second = $this->announce([['type' => 'c']]);

        $this->assertSame(
            $first[0]->moveId(),
            $first[1]->moveId(),
            'Notifications announced by the same action share a move id.'
        );
        $this->assertGreaterThan(
            $first[0]->moveId(),
            $second[0]->moveId(),
            'A later action must have a later move id.'
        );
    }

    /**
     * The entry records which player's request produced it, separately
     * from who it is addressed to.
     */
    public function testRecordsTheRequestingPlayer(): void
    {
        $notifs = $this->announce([['type' => 'testEvent']]);
        $this->assertSame($this->playerByIndex(0)->id(), $notifs[0]->currentPlayer());
    }

    //////////////////////////////////////////////////////////////////
    // Transactionality.

    /**
     * The defining property of the deferred design: notifications are
     * written inside the request's transaction, so an action that
     * fails announces nothing -- not even the notifications it had
     * already emitted before it failed.
     *
     * If they were sent inline, clients would act on a turn that never
     * happened.
     */
    public function testAFailedActionAnnouncesNothing(): void
    {
        $marker = $this->notifMarker();

        try {
            $this->playerByIndex(0)->act('actTestNotify', [
                'notifs' => [['type' => 'announcedBeforeFailing']],
                'fail' => true,
            ]);
            $this->fail('The action was expected to throw.');
        } catch (\BgaUserException $e) {
            // Expected.
        }

        $this->assertSame(
            [],
            $this->notifsSince($marker),
            'An action that threw must leave no notifications behind; its transaction was rolled back.'
        );
    }

    //////////////////////////////////////////////////////////////////
    // Delivery.

    /**
     * A public notification reaches every player; a private one
     * reaches only its addressee.
     */
    public function testDeliversPublicNotificationsToEveryoneAndPrivateOnesToOnePlayer(): void
    {
        $player0 = $this->playerByIndex(0);
        $player1 = $this->playerByIndex(1);

        $server = $this->recordNotifDelivery();
        $server->clear();

        $this->announce([
            ['type' => 'publicEvent'],
            ['type' => 'privateEvent', 'player' => $player0->id()],
        ]);

        $this->assertSame(['publicEvent', 'privateEvent'], $server->deliveredTypes($player0->id()));
        $this->assertSame(
            ['publicEvent'],
            $server->deliveredTypes($player1->id()),
            'A notifyPlayer() notification must not reach any other player.'
        );
    }

    /**
     * Private data inside a notification is rendered per recipient:
     * each player's copy carries their own `_private` payload and not
     * anybody else's.
     *
     * N.B.: the rendering only reaches into `args.args` -- the shape
     * that state-change notifications have (see
     * `Table::renderPrivateDataInGamelogEntry()`, which rewrites
     * `$notif['args']['args']`), so that is the shape used here.
     */
    public function testRendersPrivateDataSeparatelyForEachRecipient(): void
    {
        $player0 = $this->playerByIndex(0);
        $player1 = $this->playerByIndex(1);

        $server = $this->recordNotifDelivery();
        $server->clear();

        $this->announce([
            [
                'type' => 'withPrivateData',
                'args' => [
                    'args' => [
                        'shared' => 'visible to all',
                        '_private' => [
                            $player0->id() => 'for player 0 only',
                            $player1->id() => 'for player 1 only',
                        ],
                    ],
                ],
            ],
        ]);

        $delivered0 = $server->delivered($player0->id())[0]['notif']['args']['args'];
        $delivered1 = $server->delivered($player1->id())[0]['notif']['args']['args'];

        $this->assertSame('for player 0 only', $delivered0['_private']);
        $this->assertSame('for player 1 only', $delivered1['_private']);

        // The non-private part is unchanged for everyone.
        $this->assertSame('visible to all', $delivered0['shared']);
        $this->assertSame('visible to all', $delivered1['shared']);
    }

    /**
     * A player with no entry in `_private` has the key removed
     * entirely, rather than receiving an empty one -- and, more
     * importantly, rather than receiving somebody else's.
     */
    public function testDropsPrivateDataEntirelyForPlayersWithNone(): void
    {
        $player0 = $this->playerByIndex(0);
        $player1 = $this->playerByIndex(1);

        $server = $this->recordNotifDelivery();
        $server->clear();

        $this->announce([
            [
                'type' => 'withPrivateData',
                'args' => ['args' => ['_private' => [$player0->id() => 'for player 0 only']]],
            ],
        ]);

        $delivered1 = $server->delivered($player1->id())[0]['notif']['args']['args'];
        $this->assertArrayNotHasKey('_private', $delivered1);
    }

    /**
     * Delivery happens after the commit, so a failed action delivers
     * nothing either -- the gamelog it would have been read from was
     * rolled back.
     */
    public function testAFailedActionDeliversNothing(): void
    {
        $server = $this->recordNotifDelivery();
        $server->clear();

        try {
            $this->playerByIndex(0)->act('actTestNotify', [
                'notifs' => [['type' => 'announcedBeforeFailing']],
                'fail' => true,
            ]);
            $this->fail('The action was expected to throw.');
        } catch (\BgaUserException $e) {
            // Expected.
        }

        $this->assertSame([], $server->delivered());
    }

    //////////////////////////////////////////////////////////////////
    // Reading the log back.

    /**
     * `getLogsForClient()` is what a reconnecting client replays.  It
     * must show that player the public entries and their own private
     * ones, and nobody else's.
     */
    public function testGetLogsForClientHidesOtherPlayersPrivateEntries(): void
    {
        $player0 = $this->playerByIndex(0);
        $player1 = $this->playerByIndex(1);

        $this->announce([
            ['type' => 'publicEvent'],
            ['type' => 'forPlayer0', 'player' => $player0->id()],
            ['type' => 'forPlayer1', 'player' => $player1->id()],
        ]);

        $this->assertEqualsCanonicalizing(
            ['publicEvent', 'forPlayer0'],
            $this->logTypesFor($player0->id(), ['publicEvent', 'forPlayer0', 'forPlayer1']),
            'Player 0 should see the public entry and their own, but not player 1\'s.'
        );
    }

    // Returns the types among $of_interest that appear in
    // `getLogsForClient()` when read as $player_id.
    private function logTypesFor(string $player_id, array $of_interest): array
    {
        $table = $this->table();

        $previous_current_player = $table->currentPlayer;
        $table->currentPlayer = intval($player_id);
        try {
            $logs = $table->getLogsForClient();
        } finally {
            $table->currentPlayer = $previous_current_player;
        }

        $types = [];
        foreach ($logs as $log) {
            $notif = json_decode($log['gamelog_notification'], /*associative=*/ true);
            $type = $notif['notification_type'] ?? '';
            if (in_array($type, $of_interest, /*strict=*/ true)) {
                $types[] = $type;
            }
        }
        return $types;
    }
}

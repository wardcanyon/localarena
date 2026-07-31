<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/../module/test/IntegrationTestCase.php';

use Bga\GameFramework\NotificationMessage;
use Bga\GameFramework\Components\Counters\CounterVisibility;
use Bga\GameFramework\Components\Counters\OutOfRangeCounterException;
use Bga\GameFramework\Components\Counters\UnknownPlayerException;

/**
 * Tests for the counter APIs: `$this->counterFactory`, the
 * `PlayerCounter` and `TableCounter` components it creates, the two
 * counters every game has by default (`$this->playerScore` and
 * `$this->playerScoreAux`), and the counter helpers on the
 * integration-test fixtures.
 *
 * The "localarenanoop" game creates a player counter named "credits"
 * and a table counter named "round", takes them through the whole
 * documented lifecycle (`initDb()` in `setupNewGame()`,
 * `fillResult()` in `getAllDatas()`), and starts "round" at 1.  Tests
 * that need a differently-configured counter create one themselves.
 */
class CountersTest extends IntegrationTestCase
{
    const LOCALARENA_GAME_NAME = 'localarenanoop';

    private function playerId(int $index): int
    {
        return intval($this->playerByIndex($index)->id());
    }

    private function playerIds(): array
    {
        return array_map(fn($player) => intval($player->id()), $this->players());
    }

    // Returns the most recent notification of the given type.
    private function lastNotif(string $type): array
    {
        $notifs = $this->notifs($type);
        $this->assertNotEmpty($notifs, 'Expected at least one "' . $type . '" notification.');
        return $notifs[count($notifs) - 1];
    }

    //////////////////////////////////////////////////////////////////
    // TableCounter

    public function testTableCounterInitDbGetSetInc(): void
    {
        $counter = $this->table()->roundCounter;

        // The game's setupNewGame() started this counter at 1.
        $this->assertSame(1, $counter->get());

        $this->assertSame(4, $counter->set(4));
        $this->assertSame(4, $counter->get());

        $this->assertSame(6, $counter->inc(2));
        $this->assertSame(3, $counter->inc(-3));
        $this->assertSame(3, $counter->get());
    }

    public function testTableCounterDefaultsToZero(): void
    {
        $counter = $this->table()->counterFactory->createTableCounter('tokens');
        $counter->initDb();
        $this->assertSame(0, $counter->get());
    }

    public function testTableCounterInitDbLeavesAnExistingValueAlone(): void
    {
        // Re-running initDb() (as an upgradeTableDb() that adds a
        // counter to an already-published game would) must not reset
        // the tables that already have a value.
        $this->table()->roundCounter->set(7);
        $this->table()->roundCounter->initDb(1);
        $this->assertSame(7, $this->table()->roundCounter->get());
    }

    public function testTableCounterRangeIsEnforced(): void
    {
        $counter = $this->table()->counterFactory->createTableCounter('bounded', 0, 3);
        $counter->initDb(1);

        $this->assertSame(0, $counter->getMin());
        $this->assertSame(3, $counter->getMax());

        $this->assertSame(3, $counter->set(3));
        $this->expectException(OutOfRangeCounterException::class);
        $counter->inc(1);
    }

    public function testTableCounterRejectsAValueBelowTheDefaultMinimum(): void
    {
        // Counters have a minimum of 0 unless told otherwise.
        $this->assertSame(0, $this->table()->roundCounter->getMin());
        $this->expectException(OutOfRangeCounterException::class);
        $this->table()->roundCounter->set(-1);
    }

    public function testTableCounterMayBeUnbounded(): void
    {
        $counter = $this->table()->counterFactory->createTableCounter('unbounded', null, null);
        $counter->initDb();

        $this->assertNull($counter->getMin());
        $this->assertNull($counter->getMax());
        $this->assertSame(-5, $counter->inc(-5));
    }

    public function testTableCounterReadBeforeInitDbIsAnError(): void
    {
        $counter = $this->table()->counterFactory->createTableCounter('uninitialized');
        $this->expectException(\BgaVisibleSystemException::class);
        $counter->get();
    }

    public function testTableCounterFillResult(): void
    {
        $result = [];
        $this->table()->roundCounter->fillResult($result);
        $this->assertSame(['round' => 1], $result);

        $result = [];
        $this->table()->roundCounter->fillResult($result, 'currentRound');
        $this->assertSame(['currentRound' => 1], $result);
    }

    public function testTableCounterNotif(): void
    {
        $this->table()->roundCounter->set(2);
        $this->table()->roundCounter->inc(
            3,
            new NotificationMessage(clienttranslate('Round ${value} of ${totalRounds}'), ['totalRounds' => 8])
        );

        $notif = $this->lastNotif('setTableCounter');
        $this->assertSame('Round ${value} of ${totalRounds}', $notif['log']);
        $this->assertNull($notif['recipient'], 'A table counter notification goes to every player.');
        $this->assertSame(
            [
                'totalRounds' => 8,
                'name' => 'round',
                'value' => 5,
                'oldValue' => 2,
                'inc' => 3,
                'absInc' => 3,
            ],
            $notif['args']
        );
    }

    //////////////////////////////////////////////////////////////////
    // PlayerCounter

    public function testPlayerCounterInitDbGetSetInc(): void
    {
        $counter = $this->table()->playerCredits;
        $player0 = $this->playerId(0);
        $player1 = $this->playerId(1);

        // The game's setupNewGame() gave every player the default
        // starting value.
        $this->assertSame(0, $counter->get($player0));
        $this->assertSame(0, $counter->get($player1));

        $this->assertSame(5, $counter->set($player0, 5));
        $this->assertSame(7, $counter->inc($player0, 2));
        $this->assertSame(7, $counter->get($player0));

        // One player's value is not another's.
        $this->assertSame(0, $counter->get($player1));
    }

    public function testPlayerCounterInitDbWithAnInitialValue(): void
    {
        $counter = $this->table()->counterFactory->createPlayerCounter('energy');
        $counter->initDb($this->playerIds(), 3);
        $this->assertSame([$this->playerId(0) => 3, $this->playerId(1) => 3], $this->playerCounterValues('energy'));
    }

    public function testPlayerCounterGetAllAndSetAll(): void
    {
        $counter = $this->table()->playerCredits;
        $counter->set($this->playerId(0), 4);
        $counter->set($this->playerId(1), 9);

        $this->assertSame([$this->playerId(0) => 4, $this->playerId(1) => 9], $counter->getAll());

        $this->assertSame(2, $counter->setAll(2));
        $this->assertSame([$this->playerId(0) => 2, $this->playerId(1) => 2], $counter->getAll());
    }

    public function testPlayerCounterRangeIsEnforced(): void
    {
        $counter = $this->table()->counterFactory->createPlayerCounter('bounded', 0, 3);
        $counter->initDb($this->playerIds(), 2);

        $this->expectException(OutOfRangeCounterException::class);
        $counter->inc($this->playerId(0), 2);
    }

    public function testPlayerCounterSetAllRangeIsEnforced(): void
    {
        $counter = $this->table()->counterFactory->createPlayerCounter('bounded', 0, 3);
        $counter->initDb($this->playerIds());

        $this->expectException(OutOfRangeCounterException::class);
        $counter->setAll(4);
    }

    public function testPlayerCounterRejectsAPlayerItHasNoValueFor(): void
    {
        $counter = $this->table()->counterFactory->createPlayerCounter('partial', 0, null, CounterVisibility::VISIBLE, /*useNo=*/ false, /*strict=*/ false);
        // Only the first player has this counter.
        $counter->initDb([$this->playerId(0)]);

        $this->assertSame(0, $counter->get($this->playerId(0)));
        $this->expectException(UnknownPlayerException::class);
        $counter->get($this->playerId(1));
    }

    public function testStrictPlayerCounterRejectsAPlayerWhoIsNotAtTheTable(): void
    {
        // Counters validate their input by default, so a key that is
        // not a player at the table is rejected even before we look
        // for a stored value.
        $this->assertTrue($this->table()->playerCredits->getStrict());
        $this->expectException(UnknownPlayerException::class);
        $this->table()->playerCredits->get(0);
    }

    public function testNonStrictPlayerCounterAcceptsAKeyThatIsNotAPlayer(): void
    {
        // The documented use for `strict: false`: a counter that also
        // keeps a value for something that is not a player at the
        // table, such as an automaton.
        $counter = $this->table()->counterFactory->createPlayerCounter(
            'automa',
            0,
            null,
            CounterVisibility::VISIBLE,
            /*useNo=*/ false,
            /*strict=*/ false
        );
        $counter->initDb(array_merge($this->playerIds(), [0]));

        $this->assertSame(0, $counter->get(0));
        $this->assertSame(3, $counter->inc(0, 3));

        // The counter's value for the automaton is not attributed to
        // any player, so it does not reach `$result['players']`.
        $result = ['players' => [$this->playerId(0) => [], $this->playerId(1) => []]];
        $counter->fillResult($result);
        $this->assertSame(
            [$this->playerId(0) => ['automa' => 0], $this->playerId(1) => ['automa' => 0]],
            $result['players']
        );
    }

    public function testStrictnessCanBeChangedAfterCreation(): void
    {
        $counter = $this->table()->playerCredits;
        $counter->setStrict(false);
        $this->assertFalse($counter->getStrict());

        // Still unknown -- just for a different reason: there is no
        // stored value for a player the counter was not initialized
        // with.
        $this->expectException(UnknownPlayerException::class);
        $counter->get(0);
    }

    public function testPlayerCounterKeyedByPlayerNo(): void
    {
        $counter = $this->table()->counterFactory->createPlayerCounter(
            'byNo',
            0,
            null,
            CounterVisibility::VISIBLE,
            /*useNo=*/ true
        );
        $counter->initDb([1, 2]);

        $this->assertTrue($counter->getUseNo());
        $this->assertSame(4, $counter->set(1, 4));
        $this->assertSame([1 => 4, 2 => 0], $counter->getAll());

        // The test fixtures address the counter the way it is keyed.
        $this->assertSame(4, $this->playerByIndex(0)->counterValue('byNo'));

        // ... but fillResult() still keys players by player id, since
        // that is how `$result['players']` is keyed.
        $result = ['players' => [$this->playerId(0) => [], $this->playerId(1) => []]];
        $counter->fillResult($result);
        $this->assertSame(
            [$this->playerId(0) => ['byNo' => 4], $this->playerId(1) => ['byNo' => 0]],
            $result['players']
        );
    }

    public function testPlayerCounterFillResult(): void
    {
        $this->table()->playerCredits->set($this->playerId(0), 6);

        $result = ['players' => [$this->playerId(0) => ['id' => $this->playerId(0)], $this->playerId(1) => []]];
        $this->table()->playerCredits->fillResult($result);
        $this->assertSame(6, $result['players'][$this->playerId(0)]['credits']);
        $this->assertSame(0, $result['players'][$this->playerId(1)]['credits']);

        // A field name overrides the counter's own name.
        $result = ['players' => [$this->playerId(0) => []]];
        $this->table()->playerCredits->fillResult($result, 'money');
        $this->assertSame(['money' => 6], $result['players'][$this->playerId(0)]);
    }

    public function testPlayerCounterFillResultNeedsPlayers(): void
    {
        $result = [];
        $this->expectException(\BgaVisibleSystemException::class);
        $this->table()->playerCredits->fillResult($result);
    }

    //////////////////////////////////////////////////////////////////
    // Visibility

    public function testCounterVisibilityPredicates(): void
    {
        $counter = $this->table()->playerCredits;
        $this->assertTrue($counter->isVisible());
        $this->assertFalse($counter->isSelf());
        $this->assertFalse($counter->isHidden());

        $counter->setVisibility(CounterVisibility::SELF);
        $this->assertFalse($counter->isVisible());
        $this->assertTrue($counter->isSelf());
        $this->assertFalse($counter->isHidden());

        $counter->setVisibility(CounterVisibility::HIDDEN);
        $this->assertFalse($counter->isVisible());
        $this->assertFalse($counter->isSelf());
        $this->assertTrue($counter->isHidden());
    }

    public function testSelfCounterFillResultShowsOnlyTheViewersOwnValue(): void
    {
        $counter = $this->table()->playerCredits;
        $counter->setVisibility(CounterVisibility::SELF);
        $counter->set($this->playerId(0), 6);
        $counter->set($this->playerId(1), 9);

        $result = ['players' => [$this->playerId(0) => [], $this->playerId(1) => []]];
        $counter->fillResult($result, /*fieldName=*/ null, /*currentPlayerId=*/ $this->playerId(1));
        $this->assertSame(
            [$this->playerId(0) => ['credits' => null], $this->playerId(1) => ['credits' => 9]],
            $result['players']
        );
    }

    public function testHiddenCounterFillResultShowsNothing(): void
    {
        $counter = $this->table()->playerCredits;
        $counter->setVisibility(CounterVisibility::HIDDEN);
        $counter->set($this->playerId(0), 6);

        $result = ['players' => [$this->playerId(0) => [], $this->playerId(1) => []]];
        $counter->fillResult($result, /*fieldName=*/ null, /*currentPlayerId=*/ $this->playerId(0));
        $this->assertSame(
            [$this->playerId(0) => ['credits' => null], $this->playerId(1) => ['credits' => null]],
            $result['players']
        );
    }

    public function testSelfCounterNotifiesOnlyTheOwningPlayer(): void
    {
        $counter = $this->table()->playerCredits;
        $counter->setVisibility(CounterVisibility::SELF);
        $counter->inc($this->playerId(1), 2);

        $notif = $this->lastNotif('setPlayerCounter');
        $this->assertSame($this->playerId(1), $notif['recipient']);
    }

    public function testHiddenCounterSendsNoNotif(): void
    {
        $counter = $this->table()->playerCredits;
        $counter->setVisibility(CounterVisibility::HIDDEN);
        $counter->inc($this->playerId(0), 2);
        $counter->setAll(1);

        $this->assertSame([], $this->notifs('setPlayerCounter'));
        $this->assertSame([], $this->notifs('setPlayerCounterAll'));

        // The value was still stored; it is just not announced.
        $this->assertSame(1, $counter->get($this->playerId(0)));
    }

    //////////////////////////////////////////////////////////////////
    // Notifications

    public function testPlayerCounterNotifArgs(): void
    {
        $this->table()->playerCredits->set($this->playerId(0), 5);
        $this->table()->playerCredits->inc(
            $this->playerId(0),
            -2,
            new NotificationMessage(clienttranslate('${player_name} loses ${absInc} credits playing ${card_name}'), [
                'card_name' => 'Hunter',
            ])
        );

        $notif = $this->lastNotif('setPlayerCounter');
        $this->assertNull($notif['recipient'], 'A public counter notification goes to every player.');
        $this->assertSame('${player_name} loses ${absInc} credits playing ${card_name}', $notif['log']);
        $this->assertSame(
            [
                'card_name' => 'Hunter',
                'name' => 'credits',
                'value' => 3,
                'oldValue' => 5,
                'inc' => -2,
                'absInc' => 2,
                'playerId' => $this->playerId(0),
                'player_name' => $this->playerByIndex(0)->name(),
            ],
            $notif['args']
        );
    }

    public function testCounterNotifDefaultsToNoLogMessage(): void
    {
        $this->table()->playerCredits->inc($this->playerId(0), 1);

        $notif = $this->lastNotif('setPlayerCounter');
        $this->assertSame('', $notif['log'], 'By default a counter update is announced but not logged.');
        $this->assertSame(1, $notif['args']['value']);
    }

    public function testANullMessageSendsNoNotifAtAll(): void
    {
        $this->table()->playerCredits->inc($this->playerId(0), 1, null);
        $this->table()->roundCounter->inc(1, null);
        $this->table()->playerCredits->setAll(3, null);

        $this->assertSame([], $this->notifs('setPlayerCounter'));
        $this->assertSame([], $this->notifs('setTableCounter'));
        $this->assertSame([], $this->notifs('setPlayerCounterAll'));

        // The values were still updated.
        $this->assertSame(3, $this->table()->playerCredits->get($this->playerId(0)));
        $this->assertSame(2, $this->table()->roundCounter->get());
    }

    public function testAnIncrementOfZeroSendsNoNotif(): void
    {
        $this->table()->playerCredits->set($this->playerId(0), 4);
        $this->assertSame(4, $this->table()->playerCredits->inc($this->playerId(0), 0));
        $this->assertSame(1, $this->table()->roundCounter->inc(0));

        // Only the set() above was announced.
        $this->assertCount(1, $this->notifs('setPlayerCounter'));
        $this->assertSame([], $this->notifs('setTableCounter'));
    }

    public function testSetAllNotif(): void
    {
        $this->table()->playerCredits->setAll(4, new NotificationMessage(clienttranslate('Everyone gets 4 credits')));

        $notif = $this->lastNotif('setPlayerCounterAll');
        $this->assertNull($notif['recipient']);
        $this->assertSame('Everyone gets 4 credits', $notif['log']);
        $this->assertSame(['name' => 'credits', 'value' => 4], $notif['args']);
    }

    //////////////////////////////////////////////////////////////////
    // The default score counters

    public function testPlayerScoreWritesThePlayerTable(): void
    {
        $score = $this->table()->playerScore;

        // No initDb() call is needed: every player at the table has a
        // score from the moment they are seated.
        $this->assertSame(0, $score->get($this->playerId(0)));

        $this->assertSame(4, $score->set($this->playerId(0), 4));
        $this->assertSame(
            4,
            intval(
                $this->table()->getUniqueValueFromDB(
                    'SELECT `player_score` FROM `player` WHERE `player_id` = ' . $this->playerId(0)
                )
            )
        );

        $this->assertSame(1, $score->inc($this->playerId(0), -3));
        $this->assertSame([$this->playerId(0) => 1, $this->playerId(1) => 0], $score->getAll());
    }

    public function testPlayerScoreAuxWritesItsOwnColumn(): void
    {
        $this->table()->playerScoreAux->set($this->playerId(0), 7);

        $this->assertSame(7, $this->table()->playerScoreAux->get($this->playerId(0)));
        $this->assertSame(
            7,
            intval(
                $this->table()->getUniqueValueFromDB(
                    'SELECT `player_score_aux` FROM `player` WHERE `player_id` = ' . $this->playerId(0)
                )
            )
        );

        // The two counters are independent.
        $this->assertSame(0, $this->table()->playerScore->get($this->playerId(0)));
    }

    public function testPlayerScoreSetAll(): void
    {
        $this->assertSame(2, $this->table()->playerScore->setAll(2));
        $this->assertSame([$this->playerId(0) => 2, $this->playerId(1) => 2], $this->table()->playerScore->getAll());
    }

    public function testPlayerScoreIsUnboundedAndNotStrict(): void
    {
        $score = $this->table()->playerScore;
        $this->assertNull($score->getMin());
        $this->assertNull($score->getMax());
        $this->assertFalse($score->getStrict());

        // In particular, a score may go negative.
        $this->assertSame(-3, $score->inc($this->playerId(0), -3));
    }

    public function testPlayerScoreNotif(): void
    {
        $this->table()->playerScore->inc(
            $this->playerId(0),
            2,
            new NotificationMessage(clienttranslate('${player_name} scores ${inc} points'))
        );

        $notif = $this->lastNotif('setPlayerCounter');
        $this->assertSame('playerScore', $notif['args']['name']);
        $this->assertSame(2, $notif['args']['value']);
        $this->assertSame($this->playerId(0), $notif['args']['playerId']);
    }

    public function testPlayerScoreIsPublishedAsTheScoreField(): void
    {
        // The front-end score counter reads "score", which is how
        // games are expected to publish `player_score` from
        // `getAllDatas()`.
        $this->table()->playerScore->set($this->playerId(0), 11);

        $result = ['players' => [$this->playerId(0) => [], $this->playerId(1) => []]];
        $this->table()->playerScore->fillResult($result, 'score');
        $this->assertSame(
            [$this->playerId(0) => ['score' => 11], $this->playerId(1) => ['score' => 0]],
            $result['players']
        );
    }

    public function testCounterValuesReachTheClientThroughGetAllDatas(): void
    {
        // The game's getAllDatas() calls fillResult() on both of its
        // counters, so their values are in the gamedatas the client
        // is served.
        $this->table()->roundCounter->set(3);
        $this->table()->playerCredits->set($this->playerId(0), 6);

        $alldatas = $this->gamedatas()['alldatas'];
        $this->assertSame(3, $alldatas['round']);
        $this->assertSame(6, $alldatas['players'][$this->playerId(0)]['credits']);

        // ... alongside the framework's own information about the
        // player.
        $this->assertSame($this->playerByIndex(0)->name(), $alldatas['players'][$this->playerId(0)]['name']);
    }

    //////////////////////////////////////////////////////////////////
    // The factory

    public function testCountersAreLookedUpByName(): void
    {
        $this->assertSame($this->table()->playerCredits, $this->counter('credits'));
        $this->assertSame($this->table()->roundCounter, $this->counter('round'));
        $this->assertSame($this->table()->playerScore, $this->counter('playerScore'));
        $this->assertSame($this->table()->playerScoreAux, $this->counter('playerScoreAux'));
    }

    public function testCounterNamesMustBeUnique(): void
    {
        $this->expectException(\BgaVisibleSystemException::class);
        $this->table()->counterFactory->createTableCounter('credits');
    }

    public function testUnknownCounterNameIsAnError(): void
    {
        $this->expectException(\BgaVisibleSystemException::class);
        $this->counter('nosuchcounter');
    }

    //////////////////////////////////////////////////////////////////
    // The test fixtures

    public function testCounterAssertionHelpers(): void
    {
        $this->table()->roundCounter->set(3);
        $this->table()->playerCredits->set($this->playerId(0), 5);
        $this->table()->playerCredits->set($this->playerId(1), 8);

        $this->assertTableCounter(3, 'round');
        $this->assertPlayerCounter(5, 'credits', $this->playerByIndex(0));
        $this->assertPlayerCounter(8, 'credits', $this->playerByIndex(1));

        // Order-insensitive, and covers every player.
        $this->assertPlayerCounters([$this->playerId(1) => 8, $this->playerId(0) => 5], 'credits');
    }
}

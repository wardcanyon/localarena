<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/../module/test/IntegrationTestCase.php';

/**
 * Tests for the game-statistics APIs: the modern object-based APIs
 * (`$table->tableStats` / `$table->playerStats`), the legacy
 * functions (`initStat()`, `incStat()`, `setStat()`, `getStat()`),
 * and the stats helpers on the integration-test fixtures.
 *
 * The "localarenanoop" game defines the stats used here in its
 * stats.jsonc; note that its table stat "tableTestStat" and player
 * stat "playerTestStat" deliberately share numeric ID 10, so these
 * tests also cover the fact that table and player stats must be
 * disambiguated by `stats_player_id`.
 */
class StatsTest extends IntegrationTestCase
{
    const LOCALARENA_GAME_NAME = 'localarenanoop';

    //////////////////////////////////////////////////////////////////
    // The modern object-based APIs.

    public function testTableStatsInitSetIncGet(): void
    {
        $stats = $this->table()->tableStats;

        $stats->init('tableTestStat', 3);
        $this->assertSame(3, $stats->get('tableTestStat'));

        $stats->inc('tableTestStat', 2);
        $this->assertSame(5, $stats->get('tableTestStat'));

        // The delta defaults to 1.
        $stats->inc('tableTestStat');
        $this->assertSame(6, $stats->get('tableTestStat'));

        $stats->inc('tableTestStat', -2);
        $this->assertSame(4, $stats->get('tableTestStat'));

        $stats->set('tableTestStat', 42);
        $this->assertSame(42, $stats->get('tableTestStat'));
    }

    public function testTableStatsInitAcceptsArrayOfNames(): void
    {
        $this->table()->tableStats->init(['tableTestStat', 'tableFloatStat'], 0);
        $this->assertSame(0, $this->table()->tableStats->get('tableTestStat'));
        $this->assertSame(0.0, $this->table()->tableStats->get('tableFloatStat'));
    }

    public function testTableStatsGetReturnsDeclaredFloatType(): void
    {
        $this->table()->tableStats->init('tableFloatStat', 0.5);
        $this->table()->tableStats->inc('tableFloatStat', 0.25);
        $this->assertSame(0.75, $this->table()->tableStats->get('tableFloatStat'));
    }

    public function testPlayerStatsInitSetIncGet(): void
    {
        $stats = $this->table()->playerStats;
        $player0_id = intval($this->playerByIndex(0)->id());
        $player1_id = intval($this->playerByIndex(1)->id());

        // init() initializes the stat for every player.
        $stats->init('playerTestStat', 7);
        $this->assertSame(7, $stats->get('playerTestStat', $player0_id));
        $this->assertSame(7, $stats->get('playerTestStat', $player1_id));

        $stats->inc('playerTestStat', 2, $player0_id);
        $stats->set('playerTestStat', 11, $player1_id);
        $this->assertSame(9, $stats->get('playerTestStat', $player0_id));
        $this->assertSame(11, $stats->get('playerTestStat', $player1_id));
    }

    public function testPlayerStatsBoolType(): void
    {
        $stats = $this->table()->playerStats;
        $player0_id = intval($this->playerByIndex(0)->id());

        $stats->init('playerBoolStat', false);
        $this->assertSame(false, $stats->get('playerBoolStat', $player0_id));

        $stats->set('playerBoolStat', true, $player0_id);
        $this->assertSame(true, $stats->get('playerBoolStat', $player0_id));
    }

    public function testPlayerStatsUpdateTableStat(): void
    {
        // "turnsNumber" exists as both a player stat and a table stat;
        // with $updateTableStat, the player-stats APIs keep the table
        // stat in sync.
        $stats = $this->table()->playerStats;
        $player0_id = intval($this->playerByIndex(0)->id());
        $player1_id = intval($this->playerByIndex(1)->id());

        $stats->init('turnsNumber', 0, /*updateTableStat=*/ true);
        $this->assertSame(0, $this->table()->tableStats->get('turnsNumber'));

        $stats->inc('turnsNumber', 1, $player0_id, /*updateTableStat=*/ true);
        $stats->inc('turnsNumber', 1, $player1_id, /*updateTableStat=*/ true);
        $stats->inc('turnsNumber', 1, $player0_id, /*updateTableStat=*/ true);

        $this->assertSame(2, $stats->get('turnsNumber', $player0_id));
        $this->assertSame(1, $stats->get('turnsNumber', $player1_id));
        $this->assertSame(3, $this->table()->tableStats->get('turnsNumber'));
    }

    public function testTableStatsUnknownNameThrows(): void
    {
        $this->expectException(\feException::class);
        $this->table()->tableStats->get('thisStatDoesNotExist');
    }

    //////////////////////////////////////////////////////////////////
    // The legacy function-based APIs.

    public function testTableStatInitIncSetGet(): void
    {
        $this->table()->initStat('table', 'tableTestStat', 3);
        $this->assertEquals(3, $this->table()->getStat('tableTestStat'));

        $this->table()->incStat(2, 'tableTestStat');
        $this->assertEquals(5, $this->table()->getStat('tableTestStat'));

        $this->table()->incStat(-1, 'tableTestStat');
        $this->assertEquals(4, $this->table()->getStat('tableTestStat'));

        $this->table()->setStat(42, 'tableTestStat');
        $this->assertEquals(42, $this->table()->getStat('tableTestStat'));
    }

    public function testFloatTableStat(): void
    {
        $this->table()->initStat('table', 'tableFloatStat', 0.5);
        $this->table()->incStat(0.25, 'tableFloatStat');
        $this->assertEqualsWithDelta(0.75, floatval($this->table()->getStat('tableFloatStat')), 0.0001);
    }

    public function testPlayerStatInitializedForAllPlayersByDefault(): void
    {
        $this->table()->initStat('player', 'playerTestStat', 7);
        foreach ($this->players() as $player) {
            $this->assertEquals(7, $this->table()->getStat('playerTestStat', $player->id()));
        }
    }

    public function testPlayerStatInitializedForSinglePlayer(): void
    {
        $player0 = $this->playerByIndex(0);
        $player1 = $this->playerByIndex(1);

        $this->table()->initStat('player', 'playerTestStat', 4, $player0->id());

        $this->assertEquals(4, $this->table()->getStat('playerTestStat', $player0->id()));

        // No stat row should have been created for the other player.
        $row_count = $this->table()->getUniqueValueFromDB(
            'SELECT COUNT(*) FROM stats WHERE stats_player_id = ' . $player1->id()
        );
        $this->assertEquals(0, $row_count);
    }

    public function testPlayerStatsAreIndependentPerPlayer(): void
    {
        $player0 = $this->playerByIndex(0);
        $player1 = $this->playerByIndex(1);

        $this->table()->initStat('player', 'playerTestStat', 0);
        $this->table()->incStat(2, 'playerTestStat', $player0->id());
        $this->table()->setStat(9, 'playerTestStat', $player1->id());

        $this->assertEquals(2, $this->table()->getStat('playerTestStat', $player0->id()));
        $this->assertEquals(9, $this->table()->getStat('playerTestStat', $player1->id()));
    }

    public function testTableAndPlayerStatsWithSameIdDoNotCollide(): void
    {
        // "tableTestStat" and "playerTestStat" share numeric ID 10.
        $this->table()->initStat('table', 'tableTestStat', 100);
        $this->table()->initStat('player', 'playerTestStat', 1);

        $this->table()->incStat(5, 'tableTestStat');
        $this->assertEquals(105, $this->table()->getStat('tableTestStat'));
        foreach ($this->players() as $player) {
            $this->assertEquals(1, $this->table()->getStat('playerTestStat', $player->id()));
        }

        $player0 = $this->playerByIndex(0);
        $this->table()->setStat(2, 'playerTestStat', $player0->id());
        $this->assertEquals(105, $this->table()->getStat('tableTestStat'));
        $this->assertEquals(2, $this->table()->getStat('playerTestStat', $player0->id()));
    }

    public function testBoolPlayerStat(): void
    {
        $player0 = $this->playerByIndex(0);

        $this->table()->initStat('player', 'playerBoolStat', false);
        $this->assertEquals(0, $this->table()->getStat('playerBoolStat', $player0->id()));

        $this->table()->setStat(true, 'playerBoolStat', $player0->id());
        $this->assertEquals(1, $this->table()->getStat('playerBoolStat', $player0->id()));
    }

    public function testGetStatOnUnknownNameThrows(): void
    {
        $this->expectException(\feException::class);
        $this->table()->getStat('thisStatDoesNotExist');
    }

    public function testGetStatOnPlayerStatWithoutPlayerIdThrows(): void
    {
        // With a null $player_id, the stat name is resolved against
        // the "table" section, where no such stat exists.
        $this->table()->initStat('player', 'playerTestStat', 0);

        $this->expectException(\feException::class);
        $this->table()->getStat('playerTestStat');
    }

    public function testInitStatRejectsInvalidSection(): void
    {
        $this->expectException(\feException::class);
        $this->table()->initStat('banana', 'tableTestStat', 0);
    }

    public function testInitStatRejectsPlayerIdForTableStat(): void
    {
        $player0 = $this->playerByIndex(0);

        $this->expectException(\feException::class);
        $this->table()->initStat('table', 'tableTestStat', 0, $player0->id());
    }

    public function testInitStatRejectsNonNumericValue(): void
    {
        $this->expectException(\feException::class);
        $this->table()->initStat('table', 'tableTestStat', 'bogus');
    }

    //////////////////////////////////////////////////////////////////
    // The stats helpers on the integration-test fixtures.

    public function testFixtureStatHelpers(): void
    {
        $player0 = $this->playerByIndex(0);
        $player1 = $this->playerByIndex(1);

        $this->table()->tableStats->init('tableTestStat', 17);
        $this->table()->playerStats->init('playerTestStat', 3);
        $this->table()->playerStats->inc('playerTestStat', 1, intval($player1->id()));

        $this->assertSame(17, $this->tableStat('tableTestStat')->get());
        $this->assertTableStat(17, 'tableTestStat');

        $this->assertSame(3, $player0->stat('playerTestStat')->get());
        $this->assertSame(4, $player1->stat('playerTestStat')->get());
        $this->assertPlayerStat(3, 'playerTestStat', $player0);
        $this->assertPlayerStat(4, 'playerTestStat', $this->playerById($player1->id()));

        // A `StatPeer` reads the live value; it does not snapshot it.
        $stat = $player0->stat('playerTestStat');
        $this->table()->playerStats->inc('playerTestStat', 5, intval($player0->id()));
        $this->assertSame(8, $stat->get());

        // ...and can write through set().
        $stat->set(20);
        $this->assertSame(20, $stat->get());
        $this->assertPlayerStat(20, 'playerTestStat', $player0);
        $this->tableStat('tableTestStat')->set(21);
        $this->assertTableStat(21, 'tableTestStat');
    }

    public function testPlayerStatValues(): void
    {
        $player0 = $this->playerByIndex(0);
        $player1 = $this->playerByIndex(1);

        $this->table()->playerStats->init('playerTestStat', 3);
        $this->table()->playerStats->inc('playerTestStat', 1, intval($player1->id()));

        $this->assertSame(
            [
                intval($player0->id()) => 3,
                intval($player1->id()) => 4,
            ],
            $this->playerStatValues('playerTestStat')
        );

        // `assertPlayerStats()` compares the whole dict at once; the
        // expected array may be in any order.
        $this->assertPlayerStats(
            [
                intval($player1->id()) => 4,
                intval($player0->id()) => 3,
            ],
            'playerTestStat'
        );
    }

    public function testAssertPlayerStatsRejectsIncompleteExpectations(): void
    {
        $player0 = $this->playerByIndex(0);

        $this->table()->playerStats->init('playerTestStat', 3);

        // An expected array that omits a player must fail: the helper
        // asserts the complete per-player picture.
        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->assertPlayerStats([intval($player0->id()) => 3], 'playerTestStat');
    }

}

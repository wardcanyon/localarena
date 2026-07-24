<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/../module/test/IntegrationTestCase.php';

// We extend the bundled "localarenanoop" harness game, so load its
// class.  When a test supplies a `table_class`, TableManager::getTable()
// instantiates that class directly and does NOT require the game's
// .game.php file, so we must require it ourselves before subclassing.
require_once LOCALARENA_GAME_PATH . 'localarenanoop/localarenanoop.game.php';

/**
 * Tests for the legacy-games ("campaign") data APIs: the modern
 * object-based API (`$table->bga->legacy`), the deprecated
 * function-based aliases (`storeLegacyData()` et al.), and the
 * legacy-data helpers on the integration-test fixtures.
 *
 * Legacy data persists across tables, so the central scenario -- and
 * the reason the fixture helpers exist -- is seeding data BEFORE the
 * test's table is created, then observing the game read it during
 * `setupNewGame()`.  `LegacyReadingTestGame` below records what the
 * legacy API returned at each point during setup.
 *
 * Note that nothing here clears anything: every test case gets its own
 * legacy scope (see IntegrationTestCase::legacyScope()), so tests are
 * isolated from each other -- and from previous runs -- by
 * construction.
 */
class LegacyTest extends IntegrationTestCase
{
    const LOCALARENA_GAME_NAME = 'localarenanoop';

    protected function defaultTableParams(): \LocalArena\TableParams
    {
        $params = parent::defaultTableParams();
        // Reuse all of localarenanoop's files, but instantiate our
        // setup-observing subclass instead of the plain game class.
        $params->table_class = LegacyReadingTestGame::class;
        // For testUndoRestoreDoesNotRollBackLegacyData.  (Free for
        // every other test: the noop game never takes a savepoint.)
        $params->enable_undo_savepoints = true;
        return $params;
    }

    private function game(): LegacyReadingTestGame
    {
        return $this->table();
    }

    //////////////////////////////////////////////////////////////////
    // Reading legacy data during game setup.

    public function testSeededDataIsReadableDuringGameSetup(): void
    {
        // No table exists yet; seed data for the players that
        // stGameSetup() will seat, as if a previous table of this game
        // had stored it.
        $this->seedLegacyData(self::presetPlayerId(0), 'campaign', ['unlocked' => ['red']]);
        $this->seedLegacyData(self::presetPlayerId(1), 'campaign', ['unlocked' => []]);
        $this->seedLegacyTeamData(['chapter' => 3]);

        // The first table() call creates the table; setupNewGame()
        // records what the legacy API returns.
        $game = $this->game();

        $this->assertSame(
            [
                self::presetPlayerId(0) => ['unlocked' => ['red']],
                self::presetPlayerId(1) => ['unlocked' => []],
            ],
            $game->setup_player_reads
        );

        // getTeam() works during setup both before the game inserts
        // its players (from the seating list) and after (from the
        // player table).
        $this->assertSame(['chapter' => 3], $game->setup_team_read_before_players);
        $this->assertSame(['chapter' => 3], $game->setup_team_read_after_players);
    }

    public function testUnseededDataReadsDefaultsDuringGameSetup(): void
    {
        $game = $this->game();

        $this->assertSame(
            [
                self::presetPlayerId(0) => 'no-campaign',
                self::presetPlayerId(1) => 'no-campaign',
            ],
            $game->setup_player_reads
        );
        $this->assertSame('no-team-data', $game->setup_team_read_before_players);
        $this->assertSame('no-team-data', $game->setup_team_read_after_players);
    }

    public function testStoringLegacyDataDuringGameSetupThrows(): void
    {
        // setupNewGame() attempts a legacy->set() and records the
        // resulting exception; storing legacy data during setup must
        // fail, as it does on BGA.
        $game = $this->game();

        $this->assertInstanceOf(\BgaVisibleSystemException::class, $game->setup_set_exception);
        $this->assertInstanceOf(\BgaVisibleSystemException::class, $game->setup_set_team_exception);

        // ...and the failed writes must not have stored anything.
        $this->assertNull($this->legacyValue('setupsmuggle', self::presetPlayerId(0)));
    }

    //////////////////////////////////////////////////////////////////
    // The modern object-based API, after setup.

    public function testSetGetDeleteRoundTrip(): void
    {
        $legacy = $this->table()->bga->legacy;
        $player0_id = intval($this->playerByIndex(0)->id());

        $this->assertNull($legacy->get('campaign', $player0_id));
        $this->assertSame('fallback', $legacy->get('campaign', $player0_id, 'fallback'));

        $value = ['heroes' => ['aldric', 'mira'], 'gold' => 12];
        $legacy->set('campaign', $player0_id, $value);
        $this->assertSame($value, $legacy->get('campaign', $player0_id));

        // Overwriting replaces the value.
        $legacy->set('campaign', $player0_id, ['gold' => 99]);
        $this->assertSame(['gold' => 99], $legacy->get('campaign', $player0_id));

        $legacy->delete('campaign', $player0_id);
        $this->assertNull($legacy->get('campaign', $player0_id));
    }

    public function testDataIsPerPlayer(): void
    {
        $legacy = $this->table()->bga->legacy;
        $player0_id = intval($this->playerByIndex(0)->id());
        $player1_id = intval($this->playerByIndex(1)->id());

        $legacy->set('campaign', $player0_id, 'zero');
        $legacy->set('campaign', $player1_id, 'one');

        $this->assertSame('zero', $legacy->get('campaign', $player0_id));
        $this->assertSame('one', $legacy->get('campaign', $player1_id));

        $legacy->delete('campaign', $player0_id);
        $this->assertNull($legacy->get('campaign', $player0_id));
        $this->assertSame('one', $legacy->get('campaign', $player1_id));
    }

    public function testPlayerZeroStoresGameGlobalData(): void
    {
        // Player ID 0 is unused by real players; BGA documents it as a
        // slot for data global to the game (e.g. a solo leaderboard).
        $legacy = $this->table()->bga->legacy;

        $legacy->set('leaderboard', 0, [['name' => 'localdev0', 'score' => 31]]);
        $this->assertSame([['name' => 'localdev0', 'score' => 31]], $legacy->get('leaderboard', 0));

        // It does not collide with any real player's data.
        $this->assertNull($legacy->get('leaderboard', intval($this->playerByIndex(0)->id())));
    }

    public function testGetWithPatternReturnsAllMatchingKeys(): void
    {
        $legacy = $this->table()->bga->legacy;
        $player0_id = intval($this->playerByIndex(0)->id());

        $legacy->set('chapter1', $player0_id, ['done' => true]);
        $legacy->set('chapter2', $player0_id, ['done' => false]);
        $legacy->set('gold', $player0_id, 5);

        $this->assertSame(
            [
                'chapter1' => ['done' => true],
                'chapter2' => ['done' => false],
            ],
            $legacy->get('chapter%', $player0_id)
        );

        // A pattern that matches nothing returns an empty array.
        $this->assertSame([], $legacy->get('quest%', $player0_id));
    }

    public function testTeamDataRoundTrip(): void
    {
        $legacy = $this->table()->bga->legacy;

        $this->assertSame('none', $legacy->getTeam('none'));

        $legacy->setTeam(['progress' => 7]);
        $this->assertSame(['progress' => 7], $legacy->getTeam());

        // Team data is keyed by the exact set of players; a DIFFERENT
        // team's data is invisible at this table.
        $this->seedLegacyTeamData(['progress' => 99], [self::presetPlayerId(0), 987654]);
        $this->assertSame(['progress' => 7], $legacy->getTeam());

        $legacy->deleteTeam();
        $this->assertNull($legacy->getTeam());

        // Deleting this table's team data does not touch the other
        // team's.
        $this->assertSame(['progress' => 99], $this->legacyTeamValue([self::presetPlayerId(0), 987654]));
    }

    //////////////////////////////////////////////////////////////////
    // Validation and limits.

    public function testKeysWithUnderscoresAreRejected(): void
    {
        $this->expectException(\BgaVisibleSystemException::class);
        $this->table()->bga->legacy->set('my_key', intval($this->playerByIndex(0)->id()), 1);
    }

    public function testGetRejectsInvalidKeyCharacters(): void
    {
        $this->expectException(\BgaVisibleSystemException::class);
        $this->table()->bga->legacy->get("bad'key", intval($this->playerByIndex(0)->id()));
    }

    public function testNonPositiveTtlIsRejected(): void
    {
        $this->expectException(\BgaVisibleSystemException::class);
        $this->table()->bga->legacy->set('campaign', intval($this->playerByIndex(0)->id()), 1, /*ttl=*/ 0);
    }

    public function testExpiredDataIsNotReturned(): void
    {
        // Seed data whose TTL has already elapsed.
        $this->seedLegacyData(self::presetPlayerId(0), 'campaign', ['stale' => true], /*ttl=*/ -1);

        $legacy = $this->table()->bga->legacy;
        $this->assertSame('gone', $legacy->get('campaign', self::presetPlayerId(0), 'gone'));

        // ...and the slot is re-usable.
        $legacy->set('campaign', self::presetPlayerId(0), ['fresh' => true]);
        $this->assertSame(['fresh' => true], $legacy->get('campaign', self::presetPlayerId(0)));
    }

    public function testSizeLimitIsEnforcedPerPlayer(): void
    {
        $legacy = $this->table()->bga->legacy;
        $player0_id = intval($this->playerByIndex(0)->id());
        $player1_id = intval($this->playerByIndex(1)->id());

        // A single 40k value fits...
        $legacy->set('blobA', $player0_id, str_repeat('a', 40000));

        // ...and the limit applies to the player's TOTAL, so a second
        // 40k value for the same player fails with the documented code
        // on a catchable \feException...
        try {
            $legacy->set('blobB', $player0_id, str_repeat('b', 40000));
            $this->fail('Expected a size-limit feException.');
        } catch (\feException $e) {
            $this->assertSame(FEX_legacy_size_exceeded, $e->getCode());
        }

        // ...leaving the existing data intact, and other players'
        // budgets unaffected.
        $this->assertSame(str_repeat('a', 40000), $legacy->get('blobA', $player0_id));
        $this->assertNull($legacy->get('blobB', $player0_id));
        $legacy->set('blobB', $player1_id, str_repeat('b', 40000));

        // Freeing space (the documented recovery) makes the write
        // succeed.
        $legacy->delete('blobA', $player0_id);
        $legacy->set('blobB', $player0_id, str_repeat('b', 40000));
        $this->assertSame(str_repeat('b', 40000), $legacy->get('blobB', $player0_id));
    }

    //////////////////////////////////////////////////////////////////
    // The deprecated function-based aliases.

    public function testDeprecatedPlayerDataAliases(): void
    {
        $table = $this->table();
        $player0_id = intval($this->playerByIndex(0)->id());

        $this->assertSame([], $table->retrieveLegacyData($player0_id, 'campaign'));

        $table->storeLegacyData($player0_id, 'campaign', ['gold' => 3]);

        // Unlike the modern get(), the deprecated alias returns rows
        // whose values are still JSON-encoded.
        $rows = $table->retrieveLegacyData($player0_id, 'campaign');
        $this->assertCount(1, $rows);
        $this->assertSame('campaign', $rows[0]['key']);
        $this->assertSame(['gold' => 3], json_decode($rows[0]['value'], true));

        // '%' patterns match multiple keys.
        $table->storeLegacyData($player0_id, 'campaignExtra', [1]);
        $this->assertCount(2, $table->retrieveLegacyData($player0_id, 'campaign%'));

        $table->removeLegacyData($player0_id, 'campaign');
        $this->assertCount(1, $table->retrieveLegacyData($player0_id, 'campaign%'));
    }

    public function testDeprecatedTeamDataAliases(): void
    {
        $table = $this->table();

        $this->assertSame([], $table->retrieveLegacyTeamData());

        $table->storeLegacyTeamData(['progress' => 2]);
        $rows = $table->retrieveLegacyTeamData();
        $this->assertCount(1, $rows);
        $this->assertSame(['progress' => 2], json_decode($rows[0]['value'], true));

        $table->removeLegacyTeamData();
        $this->assertSame([], $table->retrieveLegacyTeamData());
    }

    //////////////////////////////////////////////////////////////////
    // Persistence across tables -- the point of the whole API.

    // Builds the params for an additional table beyond the test's
    // implicit first one.  Legacy data is shared exactly by the tables
    // that share a legacy scope, so tables whose legacy data should
    // flow from one to the next must all be given this test's scope.
    private function followOnTableParams(string $legacy_scope): \LocalArena\TableParams
    {
        $params = new \LocalArena\TableParams();
        $params->game = self::LOCALARENA_GAME_NAME;
        $params->playerCount = 2;
        $params->table_class = LegacyReadingTestGame::class;
        $params->legacy_scope = $legacy_scope;
        return $params;
    }

    public function testLegacyDataPersistsFromOneTableToTheNext(): void
    {
        // Table 1: store per-player and team data (as a real game
        // would at game end).
        $legacy = $this->table()->bga->legacy;
        $legacy->set('campaign', self::presetPlayerId(0), ['unlocked' => ['blue']]);
        $legacy->setTeam(['chapter' => 5]);

        // Tables share one process-wide database connection; close
        // table 1's so that table 2 gets a connection to its own
        // database.
        $this->table()->closeDbConnection();

        // Table 2: a brand-new table with the same players, in the
        // same legacy scope.  Its setup must see what table 1 stored.
        $table_manager = new \TableManager();
        $table2 = $table_manager->createTable($this->followOnTableParams($this->legacyScope()));

        $this->assertSame(
            [
                self::presetPlayerId(0) => ['unlocked' => ['blue']],
                self::presetPlayerId(1) => 'no-campaign',
            ],
            $table2->setup_player_reads
        );
        $this->assertSame(['chapter' => 5], $table2->setup_team_read_before_players);
        $this->assertSame(['chapter' => 5], $table2->setup_team_read_after_players);

        // And table 2 can keep using the API after its setup.
        $this->assertSame(['chapter' => 5], $table2->bga->legacy->getTeam());
    }

    public function testTablesInDifferentLegacyScopesAreIsolated(): void
    {
        // Table 1 (this test's scope) stores data...
        $legacy = $this->table()->bga->legacy;
        $legacy->set('campaign', self::presetPlayerId(0), ['unlocked' => ['blue']]);
        $legacy->setTeam(['chapter' => 5]);

        $this->table()->closeDbConnection();

        // ...but a table in a DIFFERENT scope -- which is what any
        // other test case's table is -- sees none of it, even though
        // the game and the players are identical.
        $table_manager = new \TableManager();
        $other = $table_manager->createTable(
            $this->followOnTableParams('test/' . \LocalArenaLegacyStore::allocateScopeId())
        );

        $this->assertSame(
            [
                self::presetPlayerId(0) => 'no-campaign',
                self::presetPlayerId(1) => 'no-campaign',
            ],
            $other->setup_player_reads
        );
        $this->assertSame('no-team-data', $other->setup_team_read_after_players);

        // ...and its writes are equally invisible to this test's
        // scope.
        $other->bga->legacy->set('campaign', self::presetPlayerId(1), ['unlocked' => ['green']]);
        $this->assertSame(['unlocked' => ['blue']], $this->legacyValue('campaign', self::presetPlayerId(0)));
        $this->assertNull($this->legacyValue('campaign', self::presetPlayerId(1)));
    }

    //////////////////////////////////////////////////////////////////
    // Interaction with undo.

    public function testUndoRestoreDoesNotRollBackLegacyData(): void
    {
        // Legacy data lives outside the table database, precisely so
        // that undo -- which snapshots and restores the table database
        // -- cannot roll it back.
        $legacy = $this->table()->bga->legacy;
        $player0 = $this->playerByIndex(0);

        $this->table()->undoSavepoint();

        // After the savepoint: change both table state and legacy
        // state.
        $this->table()->DbQuery('UPDATE player SET player_score = 77 WHERE player_id = ' . $player0->id());
        $legacy->set('campaign', intval($player0->id()), ['gold' => 9]);

        $this->table()->undoRestorePoint();

        // The table state rolled back...
        $score = (int) $this->table()->getUniqueValueFromDB(
            'SELECT player_score FROM player WHERE player_id = ' . $player0->id()
        );
        $this->assertSame(0, $score, 'undoRestorePoint() should roll back table state.');

        // ...but the legacy data did not.
        $this->assertSame(['gold' => 9], $legacy->get('campaign', intval($player0->id())));
    }
}

/**
 * A localarenanoop subclass whose only job is to exercise the
 * legacy-games API during `setupNewGame()` -- the point in a table's
 * life where campaign games read what previous tables stored -- and
 * record the results for the test to assert on.  Used only by
 * LegacyTest.
 */
class LegacyReadingTestGame extends \localarenanoop
{
    /** @var array<int, mixed> Player id => legacy->get('campaign', ...) during setup. */
    public $setup_player_reads = null;

    /** getTeam() read BEFORE this game inserted its players (exercises the seating-list fallback). */
    public $setup_team_read_before_players = null;

    /** getTeam() read AFTER the players were inserted (exercises the player-table path). */
    public $setup_team_read_after_players = null;

    /** The exceptions thrown by attempting to STORE legacy data during setup (which BGA forbids). */
    public $setup_set_exception = null;
    public $setup_set_team_exception = null;

    protected function setupNewGame($players, $options = [])
    {
        // Legacy reads are expected to work from the very top of
        // setup, before anything (players included) is in the table
        // database.
        $this->setup_player_reads = [];
        foreach (array_keys($players) as $player_id) {
            $this->setup_player_reads[intval($player_id)] = $this->bga->legacy->get(
                'campaign',
                intval($player_id),
                'no-campaign'
            );
        }
        $this->setup_team_read_before_players = $this->bga->legacy->getTeam('no-team-data');

        // Storing during setup must throw; record the exceptions so
        // the test can assert on them.
        try {
            $this->bga->legacy->set('setupsmuggle', intval(array_key_first($players)), ['x' => 1]);
        } catch (\BgaVisibleSystemException $e) {
            $this->setup_set_exception = $e;
        }
        try {
            $this->bga->legacy->setTeam(['x' => 1]);
        } catch (\BgaVisibleSystemException $e) {
            $this->setup_set_team_exception = $e;
        }

        parent::setupNewGame($players, $options);

        $this->setup_team_read_after_players = $this->bga->legacy->getTeam('no-team-data');
    }
}

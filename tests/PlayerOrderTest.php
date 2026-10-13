<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/../module/test/IntegrationTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for seating and turn order: `getNextPlayerTable()`,
 * `getPrevPlayerTable()`, `getPlayerAfter()`, `getPlayerBefore()`,
 * `activeNextPlayer()`, and `getPlayerRelativePositions()`.
 *
 * These had no tests, and until now could not really have had useful
 * ones: `stGameSetup()` seated `LOCALARENA_PLAYER_COUNT` players, a
 * `const` baked into the testenv image at 2, so every table in the
 * suite was the same size.  Turn order around a two-player table is
 * nearly degenerate -- "next" and "previous" are the same player --
 * so it cannot distinguish a correct implementation from several
 * wrong ones.
 *
 * `TableParams::$playerCount` is now honored, so each test here says
 * how many players it wants and the interesting cases are reachable.
 */
class PlayerOrderTest extends IntegrationTestCase
{
    const LOCALARENA_GAME_NAME = 'localarenanoop';

    // How many players this test's table seats.  Because the table is
    // created lazily -- on the first call that needs it -- a test can
    // set this first and have it apply, which is what `seat()` does.
    private int $player_count_ = 2;

    protected function defaultTableParams(): \LocalArena\TableParams
    {
        $params = parent::defaultTableParams();
        $params->playerCount = $this->player_count_;
        return $params;
    }

    // Seats $count players and returns their IDs in seating order.
    // Must be called before anything else touches the table.
    private function seat(int $count): array
    {
        $this->player_count_ = $count;
        return array_map(fn($p) => $p->id(), $this->players());
    }

    public static function tableSizeProvider(): array
    {
        return [
            '2 players' => [2],
            '3 players' => [3],
            '4 players' => [4],
        ];
    }

    //////////////////////////////////////////////////////////////////
    // Seating.

    /**
     * The premise of everything below: a table seats the number of
     * players it was created with.
     */
    #[DataProvider('tableSizeProvider')]
    public function testSeatsTheRequestedNumberOfPlayers(int $count): void
    {
        $this->assertCount($count, $this->seat($count));
    }

    /**
     * Seats are numbered from 1, in ascending player-ID order, with no
     * gaps -- which is what makes `player_no` usable as a ring index.
     */
    #[DataProvider('tableSizeProvider')]
    public function testAssignsConsecutiveSeatNumbersInPlayerIdOrder(int $count): void
    {
        $this->seat($count);

        $seats = [];
        foreach ($this->players() as $player) {
            $seats[] = $player->no();
        }
        sort($seats);

        $this->assertSame(range(1, $count), $seats);
    }

    //////////////////////////////////////////////////////////////////
    // The turn-order rings.

    #[DataProvider('tableSizeProvider')]
    public function testNextPlayerTableCyclesThroughTheSeatingOrder(int $count): void
    {
        $ids = $this->seat($count);
        $next = $this->table()->getNextPlayerTable();

        foreach ($ids as $i => $id) {
            $this->assertSame(
                intval($ids[($i + 1) % $count]),
                $next[$id],
                'The player after seat ' . ($i + 1) . ' should be the next one around the table.'
            );
        }
    }

    #[DataProvider('tableSizeProvider')]
    public function testPrevPlayerTableIsTheInverseOfTheNextTable(int $count): void
    {
        $ids = $this->seat($count);
        $prev = $this->table()->getPrevPlayerTable();

        foreach ($ids as $i => $id) {
            $this->assertSame(
                intval($ids[($i - 1 + $count) % $count]),
                $prev[$id],
                'The player before seat ' . ($i + 1) . ' should be the previous one around the table.'
            );
        }
    }

    /**
     * `getPlayerAfter()` and `getPlayerBefore()` are the single-player
     * views of those two tables, and must agree with them.
     */
    #[DataProvider('tableSizeProvider')]
    public function testGetPlayerAfterAndBeforeAgreeWithTheTables(int $count): void
    {
        $ids = $this->seat($count);
        $table = $this->table();

        foreach ($ids as $i => $id) {
            $this->assertSame(intval($ids[($i + 1) % $count]), $table->getPlayerAfter($id));
            $this->assertSame(intval($ids[($i - 1 + $count) % $count]), $table->getPlayerBefore($id));
        }
    }

    /**
     * With three or more players, "after" and "before" are genuinely
     * different directions.  At two players they coincide, which is
     * why a two-player-only suite could not tell the tables apart.
     */
    public function testAfterAndBeforeDifferOnceTheTableIsLargerThanTwo(): void
    {
        $ids = $this->seat(3);
        $table = $this->table();

        $this->assertNotSame($table->getPlayerAfter($ids[0]), $table->getPlayerBefore($ids[0]));
        $this->assertSame(intval($ids[1]), $table->getPlayerAfter($ids[0]));
        $this->assertSame(intval($ids[2]), $table->getPlayerBefore($ids[0]));
    }

    //////////////////////////////////////////////////////////////////
    // Advancing the active player.

    /**
     * Repeatedly advancing visits every player exactly once and
     * arrives back where it started.
     */
    #[DataProvider('tableSizeProvider')]
    public function testActiveNextPlayerGoesAllTheWayAroundTheTable(int $count): void
    {
        $ids = $this->seat($count);
        $table = $this->table();

        $table->changeActivePlayer(intval($ids[0]));
        $this->assertSame(intval($ids[0]), intval($table->getActivePlayerId()));

        $visited = [];
        for ($i = 0; $i < $count; $i++) {
            $visited[] = intval($table->activeNextPlayer());
        }

        $expected = array_map('intval', array_merge(array_slice($ids, 1), [$ids[0]]));
        $this->assertSame($expected, $visited);

        // Having gone all the way around, we are back at the start.
        $this->assertSame(intval($ids[0]), intval($table->getActivePlayerId()));
    }

    /**
     * `activeNextPlayer()` both returns the new active player and
     * makes them active; a game that trusts either alone should get
     * the same answer.
     */
    public function testActiveNextPlayerReturnsThePlayerItActivates(): void
    {
        $ids = $this->seat(3);
        $table = $this->table();

        $table->changeActivePlayer(intval($ids[0]));
        $returned = $table->activeNextPlayer();

        $this->assertSame(intval($returned), intval($table->getActivePlayerId()));
    }

    //////////////////////////////////////////////////////////////////
    // Relative positions.

    /**
     * `getPlayerRelativePositions()` is what puts the viewer at the
     * bottom of their own screen: the current player is position 0,
     * and everyone else follows in turn order.
     */
    #[DataProvider('tableSizeProvider')]
    public function testRelativePositionsPutTheCurrentPlayerFirst(int $count): void
    {
        $ids = $this->seat($count);
        $table = $this->table();

        // Look at the table through each player's eyes in turn.
        foreach ($ids as $viewer_index => $viewer_id) {
            $previous_current_player = $table->currentPlayer;
            $table->currentPlayer = intval($viewer_id);
            try {
                $positions = $table->getPlayerRelativePositions();
            } finally {
                $table->currentPlayer = $previous_current_player;
            }

            $this->assertSame(
                0,
                $positions[$viewer_id],
                'The current player should always be at relative position 0.'
            );

            // Everyone else is at their distance around the ring.
            foreach ($ids as $i => $id) {
                $expected_position = ($i - $viewer_index + $count) % $count;
                $this->assertSame(
                    $expected_position,
                    $positions[$id],
                    'Player at seat ' . ($i + 1) . ' seen from seat ' . ($viewer_index + 1) . '.'
                );
            }
        }
    }

    //////////////////////////////////////////////////////////////////
    // Per-player accessors.

    #[DataProvider('tableSizeProvider')]
    public function testPlayerAccessorsReportEachPlayersOwnValues(int $count): void
    {
        $ids = $this->seat($count);
        $table = $this->table();

        foreach ($ids as $i => $id) {
            $this->assertSame(LOCALARENA_PLAYER_NAME_STEM . $i, $table->getPlayerNameById(intval($id)));
            $this->assertSame($i + 1, $table->getPlayerNoById(intval($id)));
        }
    }

    /**
     * Player colors are six-character hex strings, so
     * `getPlayerColorById()` has to return a string.  It was declared
     * `: int`, which made it either fatal or silently wrong for every
     * color in the game: 'ff0000' raised a TypeError, and '008000'
     * came back as the integer 8000.
     */
    #[DataProvider('tableSizeProvider')]
    public function testGetPlayerColorByIdReturnsTheColorItself(int $count): void
    {
        $ids = $this->seat($count);
        $table = $this->table();

        $colors = [];
        foreach ($ids as $id) {
            $color = $table->getPlayerColorById(intval($id));
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{6}$/',
                $color,
                'A player color should be a six-digit hex string.'
            );
            $colors[] = $color;
        }

        $this->assertSame($colors, array_unique($colors), 'Every player should have a distinct color.');
    }
}

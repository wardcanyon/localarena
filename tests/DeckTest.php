<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/../module/test/IntegrationTestCase.php';

/**
 * Tests for the `deck` component (`module/table/deck.php`), BGA's
 * standard helper for managing a pile of cards.
 *
 * It had no tests at all, despite being the component games lean on
 * most after the state machine itself: 300-odd lines of hand-built SQL
 * covering creation, movement, drawing, counting, and shuffling.
 *
 * The deck is used here the way a game uses it -- through
 * `Table::getNew()`, against a real `card` table.  Rather than adding
 * a whole harness game just to carry a schema, the table is created
 * with `TableParams::$schema_changes`, which exists for this and (as
 * far as the tree goes) had no users until now.
 */
class DeckTest extends IntegrationTestCase
{
    const LOCALARENA_GAME_NAME = 'localarenanoop';

    // The canonical BGA card table, as bundled games declare it.
    const CARD_SCHEMA = <<<'SQL'
        CREATE TABLE IF NOT EXISTS `card` (
          `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `card_type` varchar(16) NOT NULL,
          `card_type_arg` int(11) NOT NULL,
          `card_location` varchar(16) NOT NULL,
          `card_location_arg` int(11) NOT NULL,
          PRIMARY KEY (`card_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
        SQL;

    private $deck_ = null;

    protected function defaultTableParams(): \LocalArena\TableParams
    {
        $params = parent::defaultTableParams();
        $params->schema_changes = self::CARD_SCHEMA;
        return $params;
    }

    private function deck()
    {
        if ($this->deck_ === null) {
            $this->deck_ = $this->table()->getNew('module.common.deck');
            $this->deck_->init('card');
        }
        return $this->deck_;
    }

    // Creates $count cards of one type in $location, numbered from 1.
    private function createCards(int $count, string $location = 'deck', string $type = 'basic'): void
    {
        $this->deck()->createCards([['type' => $type, 'type_arg' => 0, 'nbr' => $count]], $location);
    }

    // The location_args of the cards in $location, ascending.
    private function locationArgs(string $location): array
    {
        $args = [];
        foreach ($this->deck()->getCardsInLocation($location) as $card) {
            $args[] = intval($card['location_arg']);
        }
        sort($args);
        return $args;
    }

    // The ids of the cards in $location, ordered by location_arg.
    private function idsInLocation(string $location): array
    {
        $cards = $this->deck()->getCardsInLocation($location, null, 'card_location_arg');
        return array_map(fn($card) => intval($card['id']), array_values($cards));
    }

    //////////////////////////////////////////////////////////////////
    // Creating cards.

    public function testCreateCardsNumbersThemFromOne(): void
    {
        $this->createCards(5);

        $this->assertSame(5, intval($this->deck()->countCardInLocation('deck')));
        $this->assertSame([1, 2, 3, 4, 5], $this->locationArgs('deck'));
    }

    /**
     * With an explicit location_arg, every card gets that one instead
     * of being numbered -- which is how a hand is dealt (the arg is
     * the owning player).
     */
    public function testCreateCardsHonorsAnExplicitLocationArg(): void
    {
        $this->deck()->createCards([['type' => 'basic', 'type_arg' => 0, 'nbr' => 3]], 'hand', 42);

        $this->assertSame([42, 42, 42], $this->locationArgs('hand'));
    }

    public function testCreateCardsRecordsTypeAndTypeArg(): void
    {
        $this->deck()->createCards([['type' => 'spade', 'type_arg' => 7, 'nbr' => 1]], 'deck');

        $card = $this->deck()->getCardOnTop('deck');
        $this->assertSame('spade', $card['type']);
        $this->assertSame(7, intval($card['type_arg']));
        $this->assertSame('deck', $card['location']);
    }

    public function testGetCardReturnsOneCardById(): void
    {
        $this->createCards(3);
        $id = $this->idsInLocation('deck')[1];

        $card = $this->deck()->getCard($id);
        $this->assertSame($id, intval($card['id']));
        $this->assertSame('deck', $card['location']);
    }

    //////////////////////////////////////////////////////////////////
    // Moving cards.

    public function testMoveCard(): void
    {
        $this->createCards(3);
        $id = $this->idsInLocation('deck')[0];

        $this->deck()->moveCard($id, 'discard', 9);

        $card = $this->deck()->getCard($id);
        $this->assertSame('discard', $card['location']);
        $this->assertSame(9, intval($card['location_arg']));
        $this->assertSame(2, intval($this->deck()->countCardInLocation('deck')));
    }

    public function testMoveCardsMovesAWholeSet(): void
    {
        $this->createCards(4);
        $ids = array_slice($this->idsInLocation('deck'), 0, 2);

        $this->deck()->moveCards($ids, 'hand', 3);

        $this->assertSame(2, intval($this->deck()->countCardInLocation('hand')));
        $this->assertSame(2, intval($this->deck()->countCardInLocation('deck')));
    }

    /**
     * Moving an empty set is a no-op rather than an error -- worth
     * pinning down, since the implementation builds an `IN (...)`
     * clause that would be malformed if it ran.
     */
    public function testMoveCardsWithNoCardsDoesNothing(): void
    {
        $this->createCards(2);
        $this->deck()->moveCards([], 'hand', 1);

        $this->assertSame(2, intval($this->deck()->countCardInLocation('deck')));
        $this->assertSame(0, intval($this->deck()->countCardInLocation('hand')));
    }

    public function testMoveAllCardsInLocation(): void
    {
        $this->createCards(3);

        $this->deck()->moveAllCardsInLocation('deck', 'discard');

        $this->assertSame(0, intval($this->deck()->countCardInLocation('deck')));
        $this->assertSame(3, intval($this->deck()->countCardInLocation('discard')));
        // They all land on the default location_arg.
        $this->assertSame([0, 0, 0], $this->locationArgs('discard'));
    }

    public function testMoveAllCardsInLocationCanFilterByLocationArg(): void
    {
        $this->deck()->createCards([['type' => 'basic', 'type_arg' => 0, 'nbr' => 2]], 'hand', 1);
        $this->deck()->createCards([['type' => 'basic', 'type_arg' => 0, 'nbr' => 3]], 'hand', 2);

        $this->deck()->moveAllCardsInLocation('hand', 'discard', /*from_location_arg=*/ 1);

        $this->assertSame(2, intval($this->deck()->countCardInLocation('discard')));
        $this->assertSame(3, intval($this->deck()->countCardInLocation('hand')));
    }

    /**
     * The "keep order" variant leaves location_arg alone, so a pile's
     * ordering survives the move.
     */
    public function testMoveAllCardsInLocationKeepOrderPreservesPositions(): void
    {
        $this->createCards(3);
        $before = $this->idsInLocation('deck');

        $this->deck()->moveAllCardsInLocationKeepOrder('deck', 'discard');

        $this->assertSame([1, 2, 3], $this->locationArgs('discard'));
        $this->assertSame($before, $this->idsInLocation('discard'));
    }

    //////////////////////////////////////////////////////////////////
    // Drawing.

    /**
     * "Top" is the highest location_arg -- the last card created.
     */
    public function testGetCardOnTopIsTheHighestPosition(): void
    {
        $this->createCards(3);
        $ids = $this->idsInLocation('deck');

        $this->assertSame($ids[2], intval($this->deck()->getCardOnTop('deck')['id']));
    }

    public function testGetCardsOnTopReturnsThemTopFirst(): void
    {
        $this->createCards(5);
        $ids = $this->idsInLocation('deck');

        $top = array_map('intval', $this->deck()->getCardsOnTop(2, 'deck'));
        $this->assertSame([$ids[4], $ids[3]], $top);
    }

    public function testPickCardMovesTheTopCardToAPlayersHand(): void
    {
        $this->createCards(3);
        $expected = intval($this->deck()->getCardOnTop('deck')['id']);
        $player_id = intval($this->playerByIndex(0)->id());

        $card = $this->deck()->pickCard('deck', $player_id);

        $this->assertSame($expected, intval($card['id']));
        $this->assertSame('hand', $card['location']);
        $this->assertSame($player_id, intval($card['location_arg']));
        $this->assertSame(2, intval($this->deck()->countCardInLocation('deck')));
    }

    public function testPickCardsDrawsSeveral(): void
    {
        $this->createCards(5);
        $player_id = intval($this->playerByIndex(0)->id());

        $cards = $this->deck()->pickCards(3, 'deck', $player_id);

        $this->assertCount(3, $cards);
        $this->assertSame(2, intval($this->deck()->countCardInLocation('deck')));
        $this->assertSame(3, intval($this->deck()->countCardInLocation('hand')));
    }

    public function testPickCardForLocationDrawsToAnArbitraryLocation(): void
    {
        $this->createCards(3);

        $card = $this->deck()->pickCardForLocation('deck', 'table', 4);

        $this->assertSame('table', $card['location']);
        $this->assertSame(4, intval($card['location_arg']));
        $this->assertSame(1, intval($this->deck()->countCardInLocation('table')));
    }

    /**
     * Drawing from an empty location yields null rather than failing,
     * which is what lets a game test "is the deck exhausted?".
     */
    public function testPickCardForLocationReturnsNullWhenTheSourceIsEmpty(): void
    {
        $this->createCards(1);
        $this->deck()->pickCardForLocation('deck', 'table');

        $this->assertNull($this->deck()->pickCardForLocation('deck', 'table'));
    }

    public function testPickCardsForLocationDrawsSeveral(): void
    {
        $this->createCards(5);

        $cards = $this->deck()->pickCardsForLocation(2, 'deck', 'table', 1);

        $this->assertCount(2, $cards);
        $this->assertSame(3, intval($this->deck()->countCardInLocation('deck')));
        $this->assertSame([1, 1], $this->locationArgs('table'));
    }

    public function testGetExtremePositionFindsBothEnds(): void
    {
        $this->createCards(4);
        $ids = $this->idsInLocation('deck');

        $this->assertSame($ids[3], intval($this->deck()->getExtremePosition(true, 'deck')['id']));
        $this->assertSame($ids[0], intval($this->deck()->getExtremePosition(false, 'deck')['id']));
    }

    //////////////////////////////////////////////////////////////////
    // Inserting at a position.

    /**
     * Inserting into an occupied position makes room: the card already
     * there, and everything above it, shifts up by one.
     *
     * This did not work.  The guard compared `DbQuery(...)` -- a
     * mysqli_result -- against 0, so it was never true; and its sense
     * was inverted besides, asking to shift when the slot was empty.
     * An inserted card therefore landed on top of whatever already
     * held that position.
     */
    public function testInsertCardShiftsTheCardsItDisplaces(): void
    {
        $this->createCards(3);
        $ids = $this->idsInLocation('deck');

        // A fourth card, currently elsewhere, inserted at position 2.
        $this->deck()->createCards([['type' => 'basic', 'type_arg' => 0, 'nbr' => 1]], 'limbo');
        $inserted = $this->idsInLocation('limbo')[0];

        $this->deck()->insertCard($inserted, 'deck', 2);

        // Nothing was overwritten: four cards, at four distinct
        // positions, with the newcomer at 2.
        $this->assertSame(4, intval($this->deck()->countCardInLocation('deck')));
        $this->assertSame([1, 2, 3, 4], $this->locationArgs('deck'));
        $this->assertSame([$ids[0], $inserted, $ids[1], $ids[2]], $this->idsInLocation('deck'));
    }

    /**
     * Inserting into a free position needs no shifting, and must not
     * disturb the cards around it.
     */
    public function testInsertCardIntoAFreePositionLeavesOthersAlone(): void
    {
        $this->createCards(2);
        $ids = $this->idsInLocation('deck');

        $this->deck()->createCards([['type' => 'basic', 'type_arg' => 0, 'nbr' => 1]], 'limbo');
        $inserted = $this->idsInLocation('limbo')[0];

        $this->deck()->insertCard($inserted, 'deck', 7);

        $this->assertSame([1, 2, 7], $this->locationArgs('deck'));
        $this->assertSame([$ids[0], $ids[1], $inserted], $this->idsInLocation('deck'));
    }

    public function testInsertCardOnExtremePositionPutsACardOnTopOrAtTheBottom(): void
    {
        $this->createCards(3);

        $this->deck()->createCards([['type' => 'basic', 'type_arg' => 0, 'nbr' => 2]], 'limbo');
        $limbo = $this->idsInLocation('limbo');

        $this->deck()->insertCardOnExtremePosition($limbo[0], 'deck', /*bOnTop=*/ true);
        $this->assertSame($limbo[0], intval($this->deck()->getCardOnTop('deck')['id']));

        $this->deck()->insertCardOnExtremePosition($limbo[1], 'deck', /*bOnTop=*/ false);
        $this->assertSame($limbo[1], intval($this->deck()->getExtremePosition(false, 'deck')['id']));
    }

    public function testPlayCardPutsACardOnTopOfTheDiscard(): void
    {
        $this->createCards(3);
        $id = $this->idsInLocation('deck')[0];

        $this->deck()->playCard($id);

        $this->assertSame('discard', $this->deck()->getCard($id)['location']);
        $this->assertSame($id, intval($this->deck()->getCardOnTop('discard')['id']));
    }

    //////////////////////////////////////////////////////////////////
    // Querying.

    public function testGetCardsOfTypeFiltersByTypeAndTypeArg(): void
    {
        $this->deck()->createCards([['type' => 'spade', 'type_arg' => 1, 'nbr' => 2]], 'deck');
        $this->deck()->createCards([['type' => 'spade', 'type_arg' => 2, 'nbr' => 1]], 'deck');
        $this->deck()->createCards([['type' => 'heart', 'type_arg' => 1, 'nbr' => 3]], 'deck');

        $this->assertCount(3, $this->deck()->getCardsOfType('spade'));
        $this->assertCount(2, $this->deck()->getCardsOfType('spade', 1));
        $this->assertCount(3, $this->deck()->getCardsOfType('heart'));
    }

    public function testGetCardsOfTypeInLocationAlsoFiltersByLocation(): void
    {
        $this->deck()->createCards([['type' => 'spade', 'type_arg' => 1, 'nbr' => 2]], 'deck');
        $this->deck()->createCards([['type' => 'spade', 'type_arg' => 1, 'nbr' => 1]], 'discard');

        $this->assertCount(2, $this->deck()->getCardsOfTypeInLocation('spade', 1, 'deck'));
        $this->assertCount(1, $this->deck()->getCardsOfTypeInLocation('spade', null, 'discard'));
    }

    public function testGetPlayerHandReturnsOnlyThatPlayersCards(): void
    {
        $player0 = intval($this->playerByIndex(0)->id());
        $player1 = intval($this->playerByIndex(1)->id());

        $this->deck()->createCards([['type' => 'basic', 'type_arg' => 0, 'nbr' => 2]], 'hand', $player0);
        $this->deck()->createCards([['type' => 'basic', 'type_arg' => 0, 'nbr' => 3]], 'hand', $player1);

        $this->assertCount(2, $this->deck()->getPlayerHand($player0));
        $this->assertCount(3, $this->deck()->getPlayerHand($player1));
    }

    public function testCountCardInLocationCanFilterByLocationArg(): void
    {
        $this->deck()->createCards([['type' => 'basic', 'type_arg' => 0, 'nbr' => 2]], 'hand', 1);
        $this->deck()->createCards([['type' => 'basic', 'type_arg' => 0, 'nbr' => 3]], 'hand', 2);

        $this->assertSame(5, intval($this->deck()->countCardInLocation('hand')));
        $this->assertSame(2, intval($this->deck()->countCardInLocation('hand', 1)));
    }

    public function testCountCardsInLocationsCountsEveryPile(): void
    {
        $this->createCards(3, 'deck');
        $this->createCards(2, 'discard');

        $this->assertEquals(['deck' => 3, 'discard' => 2], $this->deck()->countCardsInLocations());
    }

    /**
     * Counts one pile broken down by location_arg -- for a hand, that
     * is "how many cards does each player hold?".
     *
     * The location was interpolated unquoted, so this raised an SQL
     * error for any string location, which in practice is all of them.
     */
    public function testCountCardsByLocationArgsBreaksAPileDownByArg(): void
    {
        $this->deck()->createCards([['type' => 'basic', 'type_arg' => 0, 'nbr' => 2]], 'hand', 1);
        $this->deck()->createCards([['type' => 'basic', 'type_arg' => 0, 'nbr' => 3]], 'hand', 2);

        $this->assertEquals([1 => 2, 2 => 3], $this->deck()->countCardsByLocationArgs('hand'));
    }

    //////////////////////////////////////////////////////////////////
    // Shuffling.

    /**
     * Shuffling renumbers a pile: the same cards, still there, holding
     * positions 1..n in some order.
     */
    public function testShuffleRenumbersThePileWithoutLosingCards(): void
    {
        $this->createCards(10);
        $before = $this->idsInLocation('deck');

        $this->deck()->shuffle('deck');

        $this->assertSame(range(1, 10), $this->locationArgs('deck'));

        $after = $this->idsInLocation('deck');
        sort($before);
        sort($after);
        $this->assertSame($before, $after, 'Shuffling must not add, drop, or duplicate cards.');
    }

    //////////////////////////////////////////////////////////////////
    // Automatic reshuffling.

    /**
     * With autoreshuffle on, drawing from an exhausted deck folds the
     * discard back in and draws from that instead -- so the draw
     * succeeds where it would otherwise have returned null.
     */
    public function testAutoReshuffleRefillsAnExhaustedDeck(): void
    {
        $deck = $this->deck();
        $deck->autoreshuffle = true;

        $this->createCards(1, 'deck');
        $this->createCards(3, 'discard');

        // Empty the deck.
        $this->assertNotNull($deck->pickCardForLocation('deck', 'table'));
        $this->assertSame(0, intval($deck->countCardInLocation('deck')));

        // The next draw reshuffles the discard into the deck first.
        $this->assertNotNull($deck->pickCardForLocation('deck', 'table'));

        $this->assertSame(0, intval($deck->countCardInLocation('discard')));
        $this->assertSame(2, intval($deck->countCardInLocation('deck')));
        $this->assertSame(2, intval($deck->countCardInLocation('table')));
    }

    /**
     * The reshuffle trigger lets a game announce the event (BGA games
     * typically notify the table that the deck was rebuilt).
     */
    public function testAutoReshuffleCallsTheTriggerIfOneIsSet(): void
    {
        $deck = $this->deck();
        $deck->autoreshuffle = true;

        $observer = new ReshuffleObserver();
        $deck->autoreshuffle_trigger = ['obj' => $observer, 'method' => 'onReshuffle'];

        $this->createCards(1, 'deck');
        $this->createCards(2, 'discard');

        $deck->pickCardForLocation('deck', 'table');
        $this->assertSame(0, $observer->calls, 'No reshuffle is needed while the deck still has cards.');

        $deck->pickCardForLocation('deck', 'table');
        $this->assertSame(1, $observer->calls);
    }

    /**
     * Without autoreshuffle, an exhausted deck stays exhausted and the
     * discard is left alone.
     */
    public function testDoesNotReshuffleUnlessAsked(): void
    {
        $deck = $this->deck();

        $this->createCards(1, 'deck');
        $this->createCards(3, 'discard');

        $deck->pickCardForLocation('deck', 'table');
        $this->assertNull($deck->pickCardForLocation('deck', 'table'));
        $this->assertSame(3, intval($deck->countCardInLocation('discard')));
    }
}

// Records that the deck's autoreshuffle trigger fired.
class ReshuffleObserver
{
    public int $calls = 0;

    public function onReshuffle(): void
    {
        $this->calls++;
    }
}

<?php

namespace BurgleBrosTwo\Managers;

// // XXX: Is this useful?
// class Card {

// }

class CardManager extends \APP_DbObject
{
    // N.B.: For `card_order`, lower numbers are "first" (closer to
    // the top of a deck).

    // See `card` table schema for details.
    protected $card_location;
    protected $card_location_index;

    function __construct($card_location, $card_location_index = null)
    {
        parent::__construct();

        $this->card_location = $card_location;
        $this->card_location_index = $card_location_index;
    }

    // --- API ---

    function cardLocation()
    {
        return $this->card_location;
    }
    function cardLocationIndex()
    {
        return $this->card_location_index;
    }

    // Instantiates new cards per $card_specs, assigning them new card
    // IDs in random order.  The new cards are placed on bottom of
    // $card_sublocation with shuffled $card_order.
    //
    // TODO: If it's not too hard, only shuffle the new cards; but we
    // don't really need that functionality, so it is probably okay to
    // shuffle the whole thing.
    //
    // TODO: What should $card_specs be?  Right now, it's an array;
    // each individual card spec is an associative array with one key
    // ("card_type").
    function createCards($card_specs, $card_sublocation = "DECK")
    {
        // XXX: this isn't going to work when `card_location_index` is NULL yet
        //
        // XXX: Also, we need to `$this->shuffle()` the new cards
        // (since all of them are created with a card_order of -1).
        // We should move that into this function!

        $values = [];
        foreach ($card_specs as $card_spec) {
            // XXX: update some of these values
            $values[] =
                '("patrol", "' .
                $card_spec["card_type"] .
                '", "PATROL", "DECK", ' .
                $this->card_location_index . ", -1" .
                ")";
        }

        shuffle($values);
        $sql =
            "INSERT INTO card (`card_type_group`, `card_type`, `card_location`, `card_sublocation`, `card_location_index`, `card_order`) VALUES " .
            implode(",", $values);
        self::DbQuery($sql);
    }

    // Takes cards from all $card_sublocations and moves them to
    // $destination_sublocation.
    function moveAll(
        $card_sublocations = ["DECK"],
        $destination_sublocation = "DECK"
    ) {
        throw new \feException("moveAll() not implemented");
    }

    // // XXX: Instead, create a CardManager with the intended
    // // location & location_index and use `placeOn{Top,Bottom}()`
    //
    // function move($card_id, $destination_location, $destination_sublocation, $destination_location_index = null) {
    //     $update_subexprs = [
    //         'card_location = "'.$destination_location.'"',
    //         'card_sublocation = "'.$destination_sublocation.'"',
    //     ];
    //     if (!is_null($destination_location_index)) {
    //         $update_subexprs[] = 'card_location_index = '.$destination_location_index;
    //     }
    //     self::DbQuery('UPDATE `card` SET ' . implode(',', $update_subexprs) . ' WHERE `id` = ' . $card_id);
    // }

    // Randomly assign a new `card_order` value to each card in
    // $card_sublocation.
    function shuffle($card_sublocation = "DECK")
    {
        self::trace("CardManager: shuffle()");
        $cards = $this->getAll([$card_sublocation]);
        shuffle($cards);

        $i = 0; // XXX: Should be able to replace this with `foreach()` syntax.
        foreach ($cards as $card) {
            self::trace(
                "CardManager: shuffle(): setting card_order=" .
                    $i .
                    " for id=" .
                    $card["id"]
            );
            self::DbQuery(
                "UPDATE `card` SET card_order=" .
                    $i .
                    " WHERE `id` = " .
                    $card["id"]
            );
            ++$i;
        }
    }

    function getAll($card_sublocations = ["DECK"])
    {
        return self::getCollectionFromDb(
            "SELECT * FROM `card` WHERE " .
                $this->buildWhereClause($card_sublocations)
        );
    }

    function get($cardId)
    {
        $this->assertValidCardId($cardId);

        self::trace("CardManager::get(cardId={$cardId})");
        // XXX: this should probably return an error if the card is not within the scope of this CardManager
        $card = self::getObjectFromDB(
            "SELECT * FROM `card` WHERE `id` = " . $cardId
        );
        if (is_null($card["id"])) {
            throw new \feException(
                "CardManager::get(cardId={$cardId}) -- card ID is null; $$card=" .
                    print_r($card, true)
            );
        }
        return $card;
    }

    // Returns the top `Card` in the indicated $card_sublocation, or
    // `null` iff it is empty.
    function peekTop($card_sublocation = "DECK")
    {
        $sql =
            "SELECT * FROM `card` WHERE " .
            $this->buildWhereClause([$card_sublocation]) .
            " ORDER BY card_order ASC LIMIT 1";
        // self::trace("readMinCardOrder: {$sql}");
        return $this->getObjectFromDB($sql);
    }

    // Returns the top `Card` in the indicated $card_sublocation, and
    // moves it to $destination_sublocation, where it is placed on top.
    //
    // If the deck is empty, if $auto_reshuffle is true and there are
    // cards in $destination_sublocation, `moveAll()` them back to
    // $card_sublocation and then `shuffle()` them and try again.  If
    // $auto_reshuffle is false, or if there are no cards in either
    // sublocation, returns `null`.
    function drawAndDiscard(
        $card_sublocation = "DECK",
        $destination_sublocation = "DISCARD",
        $auto_reshuffle = false
    ) {
        // XXX: $auto_reshuffle is ignored

        $card = $this->peekTop($card_sublocation);
        $this->placeOnTop($card, $destination_sublocation);
        // XXX: should $card reflect the before position or the after position?
        return $card;
    }

    // Like `drawAndDiscard()`, but repeats until $predicate returns
    // true for a card.  Cards that do not match are placed in
    // $destination_sublocation.
    function drawAndDiscardUntil(
        $predicate,
        $card_sublocation = "DECK",
        $destination_sublocation = "DISCARD",
        $auto_reshuffle = false
    ) {
        throw new \feException("not implemented");
    }

    // Like `drawAndDiscard()`, but repeats until $predicate returns
    // true for a card.  Cards that do not match remain in
    // $card_sublocation.
    //
    // If no card in $card_sublocation matches, returns null.
    //
    // Assumes $auto_reshuffle=false, mostly for implementation
    // convenience.  Could be extended to support that.
    function drawAndDiscardFirstMatching(
        $predicate,
        $card_sublocation = "DECK",
        $destination_sublocation = "DISCARD"
    ) {
        $cards = $this->getAll([$card_sublocation]);

        foreach ($cards as $card) {
            if ($predicate($card)) {
                $this->placeOnTop($card, $destination_sublocation);
                return $card;
            }
        }

        return null;
    }

    function assertValidCard($card)
    {
        $this->assertValidCardId($card["id"]);
    }

    function assertValidCardId($cardId)
    {
        // // XXX: This is a string that looks like e.g. "5".
        // if (!is_int($card['id']) || $card['id'] <= 0) {
        //     throw new \feException("CardManager::placeOnTop(): invalid cardId: {$card['id']} which is a " . get_debug_type($card['id']));
        // }
    }

    function placeOnTop($card, $card_sublocation)
    {
        $this->assertValidCard($card);
        $this->shiftCardOrder($card_sublocation, 1);
        $this->updateCard($card, $card_sublocation, /*card_order=*/ 0);
    }

    function placeOnBottom($card, $card_sublocation)
    {
        $this->assertValidCard($card);
        $this->updateCard(
            $card,
            $card_sublocation,
            /*card_order=*/ $this->readMaxCardOrder($card_sublocation) + 1
        );
    }

    // --- Internal helpers ---

    function updateCard($card, $card_sublocation, $card_order)
    {
        $this->assertValidCard($card);
        $update_subexprs = [
            'card_location = "' . $this->card_location . '"',
            'card_sublocation = "' . $card_sublocation . '"',
            "card_order = " . $card_order,
        ];
        if (!is_null($this->card_location_index)) {
            $update_subexprs[] =
                "card_location_index = " . $this->card_location_index;
        }
        self::DbQuery(
            "UPDATE `card` SET " .
                implode(",", $update_subexprs) .
                " WHERE `id` = " .
                $card["id"]
        );
    }

    // Modifies all `card_order`s in $card_sublocation by $n.
    protected function shiftCardOrder($card_sublocation, $n)
    {
        self::DbQuery(
            "UPDATE `card` SET card_order=(card_order+" .
                $n .
                ") WHERE " .
                $this->buildWhereClause([$card_sublocation])
        );
    }

    // Returns the number of cards in $card_sublocation.
    protected function cardCount($card_sublocation)
    {
        return self::getUniqueValueFromDB(
            "SELECT COUNT(*) FROM `card` WHERE " .
                $this->buildWhereClause([$card_sublocation])
        );
    }

    protected function readMaxCardOrder($card_sublocation)
    {
        return self::getUniqueValueFromDB(
            "SELECT card_order FROM `card` WHERE " .
                $this->buildWhereClause([$card_sublocation]) .
                " ORDER BY card_order DESC LIMIT 1"
        );
    }

    protected function readMinCardOrder($card_sublocation)
    {
        $sql =
            "SELECT card_order FROM `card` WHERE " .
            $this->buildWhereClause([$card_sublocation]) .
            " ORDER BY card_order ASC LIMIT 1";
        self::trace("readMinCardOrder: {$sql}");
        return self::getUniqueValueFromDB($sql);
    }

    protected function buildWhereClause($card_sublocations)
    {
        $clause = 'card_location = "' . $this->card_location . '"';

        if (!is_null($this->card_location_index)) {
            $clause .=
                " AND card_location_index = " . $this->card_location_index;
        }

        $sublocation_values = [];
        foreach ($card_sublocations as $card_sublocation) {
            $sublocation_values[] = '"' . $card_sublocation . '"';
        }
        if (count($sublocation_values) > 0) {
            $clause .=
                " AND card_sublocation IN (" .
                implode(",", $sublocation_values) .
                ")";
        }

        return $clause;
    }
}

<?php

namespace BurgleBrosTwo\States;

trait CharacterSelection
{
    // N.B.: This function is called directly after setup.  If BGA is
    // refusing to start a table, it may be because there is a problem
    // here.
    function stCharacterSelection()
    {
        $players_not_passed = array_keys(
            self::getCollectionFromDB(
                "SELECT player_id FROM player WHERE player_selection_passed IS FALSE"
            )
        );
        $this->gamestate->setPlayersMultiactive(
            $players_not_passed,
            "tRoundDone",
            /*bExclusive=*/ true
        );
    }

    function argCharacterSelection()
    {
        $bros_cards = self::getCardsByLocation("BROS", "DECK");

        // N.B.: The thing with `array_values()` here is because
        // `getCollectionFromDB()` returns an associative array whose
        // keys are the first column specified in the SELECT
        // statement.  If we don't specify one, that key is apparently
        // null.
        $character_count = 0;
        $current_player_character_count = 0;
        $selected_characters = self::getCollectionFromDB(
            "SELECT * FROM character_player WHERE TRUE"
        );
        foreach (array_values($selected_characters) as $character) {
            $character_count++;
            if ($character["player_id"] == self::getCurrentPlayerId()) {
                $current_player_character_count++;
            }
            $bros_cards = array_filter($bros_cards, function ($card) use (
                $character
            ) {
                return $card["card_type"] != $character["bro"];
            });
        }

        return [
            "cards" => self::renderCardsForClient($bros_cards),
            "characterCount" => $character_count,
            "currentPlayerCharacterCount" => $current_player_character_count,
        ];
    }

    function onActPlayCard_stCharacterSelection($cardId)
    {
        // Look up the card that the player chose, double-check that
        // it's a "bros" card, and resolve it to its cardType (the
        // bro's name).
        $card = self::getCard($cardId);
        if ($card == null || $card["card_type_group"] != "bros") {
            self::trace("cardId=$cardId card=" . print_r($card));
            throw new feException("Card ID does not correspond to character.");
        }
        self::trace(
            "Player sent character selection: cardType=" . $card["card_type"]
        );
        $selected_bro = $card["card_type"];

        $cardType = self::getCardTypeData($card);

        // TODO: Check that this player is allowed to select another
        //   character right now, given game settings and state.
        //
        // (Check that the player has not passed, and if they have any
        // characters, check that multi-handed play is enabled.)

        // TODO: Check that the choice is valid, given game settings.

        // TODO: Check that this character has not already been
        // selected.

        // Create a character entry and assign a `characterIndex`.
        self::DbQuery(
            "INSERT INTO entity (entity_type, pos_x, pos_y, pos_z, state) VALUES " .
                '("CHARACTER_PLAYER", NULL, NULL, NULL, "VISIBLE")'
        );

        // N.B.: `turn_order` is NOT NULL, but we don't assign
        // final turn order values until after all characters have
        // been selected (in the final game setup phase), so we
        // give everyone a value of 0 here.
        self::DbQuery(
            "INSERT INTO `character_player` " .
                "(entity_id, state, turn_order, bro, player_id, heat, statuses) VALUES " .
                "(" .
                self::DbGetLastId() .
                ', "NOT_ENTERED", 0, "' .
                $selected_bro .
                '", "' .
                self::getCurrentPlayerId() .
                '", 0, "[]")'
        );
        $pc_id = self::DbGetLastId();
        self::trace(
            "Bro " .
                $selected_bro .
                " has been assigned characterIndex=" .
                $pc_id
        );

        // Depending on game options, give the player their starting cards.
        //
        // XXX: Skip this if the CTJ variant is enabled.
        $gear = new \BurgleBrosTwo\Managers\CardManager("GEAR");
        $character_cards = new \BurgleBrosTwo\Managers\CardManager(
            "CHARACTER",
            $pc_id
        );
        foreach ($gear->getAll(["DECK"]) as $gear_card) {
            $gear_card_bro = CARD_DATA["gear"][$gear_card["card_type"]]["bro"];
            if ($gear_card_bro == $selected_bro) {
                $character_cards->placeOnTop($gear_card, "HAND");
            }
        }

        // Send all clients a message confirming the character
        // selection.
        self::notifyAllPlayers(
            "characterSelected",
            clienttranslate('${playerName} chooses ${characterName}.'),
            [
                "playerId" => self::getCurrentPlayerId(),
                "playerName" => self::getCurrentPlayerName(),
                "characterType" => $selected_bro,
                "characterIndex" => $pc_id,
                "characterName" => $cardType["title"],
                "cardId" => $card["id"],

                // XXX: This is the new, standard message representing a
                // character.  We can probably eliminate some of the
                // duplicative information above.
                "character" => self::renderPlayerCharacterForClient(
                    self::rawGetPlayerCharacter($pc_id)
                ),
            ]
        );

        // TODO: Now, what should we do next?
        //
        // - The players take turns selecting a character or passing.
        //   Once a player has passed they don't get offered
        //   characters in later rounds.
        //
        // - Once four characters have been selected or all players
        //   have passed, we move on.
        //
        // - Players can't pass on the first selection round; each
        //   needs to select at least one character.
        //
        // - If multi-handed play is disabled, we move on after the
        //   first round as though everyone had passed in the second
        //   round.

        /*
          $this->gameState->nextState('nextPlayer');

         */

        $this->gamestate->setPlayerNonMultiactive(
            self::getCurrentPlayerId(),
            "tRoundDone"
        );
    }

    function onActPass_stCharacterSelection()
    {
        // Players can't pass on the first selection round; each needs
        // to select at least one character.
        if (
            count(
                self::getCollectionFromDB(
                    "SELECT * FROM character_player WHERE player_id = " .
                        self::getCurrentPlayerId()
                )
            ) < 1
        ) {
            throw new BgaUserException(
                self::_("Cannot pass before selecting at least one character.")
            );
        }

        self::DbQuery(
            "UPDATE player SET player_selection_passed = TRUE WHERE player_id = " .
                self::getCurrentPlayerId()
        );

        $this->gamestate->setPlayerNonMultiactive(
            self::getCurrentPlayerId(),
            "tRoundDone"
        );
    }
}

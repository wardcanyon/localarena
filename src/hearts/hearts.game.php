<?php
/**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * Hearts implementation fixes: © ufm <tel2tale@gmail.com>
  * 
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  * 
  * hearts.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */

/**
 * Note: this code is modified to add suggestions from BGA players and popular variants.
 * Please visit here to read the basic code used in the BGA Studio tutorial: https://github.com/elaskavaia/
 */

require_once(APP_GAMEMODULE_PATH.'module/table/table.game.php');

class Hearts extends Table {
    function __construct() {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        self::initGameStateLabels([
            "dealer" => 10,
            "trick_color" => 11,
            "skip_end" => 20,
            "game_length" => 100,
            "rule_set" => 101,
            "jack_of_diamonds" => 102,
            "point_limit_variant" => 103,
            "face_value_scoring" => 104,
            "spades_scoring" => 105,
            "no_starter_card" => 106,
            "moon_variant" => 107,
            "pass_cycle" => 108,
            "track_information" => 109,
            "no_trick_skip" => 110,
        ]);
        $this->cards = self::getNew("module.common.deck");
        $this->cards->init("card");
    }

    protected function getGameName() {
        return "hearts";
    }

    /*
        setupNewGame:

        This method is called 1 time when a new game is launched.
        In this method, you must set up the game according to game rules, in order
        the game is ready to be played.
    */

    protected function setupNewGame ($players, $options = []) {
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/yellow
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialized it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = [];
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes($player['player_name'])."','".addslashes($player['player_avatar'])."')";
        }
        $sql .= implode(',', $values);
        self::DbQuery($sql);
        self::reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        self::reloadPlayersBasicInfos();

        /************ Start the game initialization *****/

        // Rule set initialization
        $rule_set = self::getGameStateValue("rule_set");
        switch ($rule_set) {
            case 0:
            case 1:
            case 3:
                self::setGameStateInitialValue("jack_of_diamonds", $rule_set == 1 ? 1 : 0);
                self::setGameStateInitialValue("point_limit_variant", 0);
                self::setGameStateInitialValue("face_value_scoring", $rule_set == 3 ? 1 : 0);
                self::setGameStateInitialValue("spades_scoring", 0);
                self::setGameStateInitialValue("no_starter_card", 0);
                self::setGameStateInitialValue("moon_variant", 0);
                self::setGameStateInitialValue("pass_cycle", 0);
                break;
            case 2:
                self::setGameStateInitialValue("jack_of_diamonds", 0);
                self::setGameStateInitialValue("point_limit_variant", 2);
                self::setGameStateInitialValue("face_value_scoring", 0);
                self::setGameStateInitialValue("spades_scoring", 1);
                self::setGameStateInitialValue("no_starter_card", 1);
                self::setGameStateInitialValue("moon_variant", 2);
                self::setGameStateInitialValue("pass_cycle", 2);
                break;
        }

        // Initial points setup
        switch (self::getGameStateValue('game_length')) {
            default:
                $start_points = 50;
                break;
            case 1:
                $start_points = 75;
                break;
            case 2:
                $start_points = 100;
                break;
        }
        if (self::getGameStateValue('face_value_scoring')) $start_points *= 5; // Spot Hearts multiplies initial points by 5
        $this->DbQuery("UPDATE player SET player_score = $start_points");

        // Init global values with their initial values
        // Set current trick color to zero (= no trick color)
        self::setGameStateInitialValue('trick_color', 0);
        if (self::getGameStateValue('no_starter_card')) self::setGameStateInitialValue("dealer", self::getPlayerBefore(self::getPlayerBefore($this->activeNextPlayer())));

        // Init game statistics
        // (note: statistics are defined in your stats.inc.php file)
        self::initStat("table", "handNbr", 0);
        self::initStat("player", "getQueenOfSpades", 0);
        self::initStat("player", "getHeart", 0);
        self::initStat("player", "getAllPointCards", 0);
        self::initStat("player", "getNoPoints", 0);
        if (self::getGameStateValue('jack_of_diamonds')) self::initStat("player", "getJackOfDiamonds", 0);
        if (self::getGameStateValue('spades_scoring')) {
            self::initStat("player", "getKingOfSpades", 0);
            self::initStat("player", "getAceOfSpades", 0);
        }

        // Remove excess cards according to the player count
        $remove_code = [];
        switch (count($players)) {
            case 3:
                // Remove 2 of Diamonds
                $remove_code = [402];
                break;
            case 5:
                // Remove 2 of Diamonds and 2 of Clubs
                $remove_code = [402, 302];
                break;
            case 6:
            case 8:
                // Remove 2 of Diamonds, 2 of Clubs, 2 of Spades and 3 of Clubs
                $remove_code = [402, 302, 102, 303];
                break;
            case 7:
                // Remove 2 of Diamonds, 2 of Clubs and 2 of Spades
                $remove_code = [402, 302, 102];
                break;
        }

        // Create cards
        $cards = [];
        foreach ($this->colors as $color_id => $color) // spade, heart, diamond, club
            for ($value = 2; $value <= 14; $value++) // 2, 3, 4, ... K, A
                if (!in_array($color_id * 100 + $value, $remove_code)) // Cards to be excluded
                    $cards[] = ['type' => $color_id, 'type_arg' => $value, 'nbr' => 1];

        $this->cards->createCards($cards, 'deck');

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all information about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refresh the game page (F5)
    */

    protected function getAllDatas() {
        $result = [];
        $current_player_id = self::getCurrentPlayerId(); // !! We must only return informations visible by this player !!

        // Get information about players
        // Note: you can retrieve some extra field you add for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player";
        $result['players'] = self::getCollectionFromDb($sql);
  
        // Cards in player hand      
        $result['hand'] = $this->cards->getPlayerHand($current_player_id);
  
        // Cards played on the table
        $result['cardsontable'] = $this->cards->getCardsInLocation('cardsontable');

        // Variant settings required in js
        $result['point_limit_variant'] = self::getGameStateValue('point_limit_variant');
        $result['face_value_scoring'] = self::getGameStateValue('face_value_scoring');
        $result['spades_scoring'] = self::getGameStateValue('spades_scoring');
        $result['jack_of_diamonds'] = self::getGameStateValue('jack_of_diamonds');
        $result['dealer'] = self::getGameStateValue('dealer');
        $result['no_starter_card'] = self::getGameStateValue('no_starter_card');
        $result['track_information'] = self::getGameStateValue('track_information');

        // Record trackable information if the option is enabled
        foreach ($result['players'] as $player_id => $players)
            if ($result['track_information']) {
                $cardswon = $this->cards->getCardsInLocation('cardswon', $player_id);
                $score = 0;
                foreach ($cardswon as $card) $score += $this->calculateCardPoints($card, $result['face_value_scoring'], $result['spades_scoring'], $result['jack_of_diamonds']);
                $result['players'][$player_id]['hand_score'] = $score;
            } else $result['players'][$player_id]['hand_score'] = null;

        // Audio list
        $result['audio_list'] = $this->audio_list;
        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer between 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with "updateGameProgression" property (see states.inc.php)
    */

    function getGameProgression() {
        // Game progression: get player minimum score
        switch (self::getGameStateValue('game_length')) {
            default:
                $start_points = 50;
                break;
            case 1:
                $start_points = 75;
                break;
            case 2:
                $start_points = 100;
                break;
        }
        return floor(max(0, min(100, ($start_points - self::getUniqueValueFromDb("SELECT MIN(player_score) FROM player")) / $start_points * 100))); // Note: 0 => 100
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

    /*
        At this place, you can put any utility methods useful for your game logic
    */

    // Read or modify player scores
    function dbGetScore ($player_id) {return $this->getUniqueValueFromDB("SELECT player_score FROM player WHERE player_id = '$player_id'");}
    function dbSetScore ($player_id, $count) {$this->DbQuery("UPDATE player SET player_score = '$count' WHERE player_id = '$player_id'");}
    function dbIncScore ($player_id, $inc) {
        $count = $this->dbGetScore($player_id);
        if ($inc != 0) {
            $count += $inc;
            $this->dbSetScore($player_id, $count);
        }
        return $count;
    }

    function getPassType(): int {
        // Get the pass direction from the round number and player count
        $round_number = self::getStat("handNbr");
        $player_count = self::getUniqueValueFromDB("SELECT count(*) FROM player");
        switch (self::getGameStateValue('pass_cycle')) {
            default:
                // Rotate the pass direction
                return $round_number % $player_count;
            case 1:
                // Same as the default, but remove 'no pass' rounds
                $pass_type = $round_number % ($player_count - 1);
                return $pass_type ?: ($player_count - 1);
            case 2:
                // Always pass right
                return 2;
        }
    }

    function getPlayerToGiveCards ($player_id, $to_give) {
        // To which player should I give (or who gave me) these cards?
        // Setting to_give to true gets the player to give cards, setting to false gets the card giver
        switch ($this->getPassType()) {
            case 1: // pass left
                return $to_give ? self::getPlayerAfter($player_id) : self::getPlayerBefore($player_id);
            case 2: // pass right
                return $to_give ? self::getPlayerBefore($player_id) : self::getPlayerAfter($player_id);
            case 3: // 4 players - pass front, more players - pass left 2
                return $to_give ? self::getPlayerAfter(self::getPlayerAfter($player_id)) : self::getPlayerBefore(self::getPlayerBefore($player_id));
            case 4: // pass right 2
                return $to_give ? self::getPlayerBefore(self::getPlayerBefore($player_id)) : self::getPlayerAfter(self::getPlayerAfter($player_id));
            case 5: // 6 players - pass front, more players - pass left 3
                return $to_give ? self::getPlayerAfter(self::getPlayerAfter(self::getPlayerAfter($player_id))) : self::getPlayerBefore(self::getPlayerBefore(self::getPlayerBefore($player_id)));
            case 6: // pass right 3
                return $to_give ? self::getPlayerBefore(self::getPlayerBefore(self::getPlayerBefore($player_id))) : self::getPlayerAfter(self::getPlayerAfter(self::getPlayerAfter($player_id)));
            case 7: // 8 players - pass front, more players - pass left 4
                return $to_give ? self::getPlayerAfter(self::getPlayerAfter(self::getPlayerAfter(self::getPlayerAfter($player_id)))) : self::getPlayerBefore(self::getPlayerBefore(self::getPlayerBefore(self::getPlayerBefore($player_id))));
            default:
                return null;
        }
    }

    function getPassDirectionName(): string {
        // Return the name of pass direction
        switch ($this->getPassType()) {
            case 1: // pass left
                return clienttranslate("the left player");
            case 2: // pass right
                return clienttranslate("the right player");
            case 3: // 4 players - pass front, more players - pass left 2
                $player_count = self::getUniqueValueFromDB("SELECT count(*) FROM player");
                return $player_count == 4 ? clienttranslate("the front player") : clienttranslate("the second left player");
            case 4: // pass right 2
                return clienttranslate("the second right player");
            case 5: // 6 players - pass front, more players - pass left 3
                $player_count = self::getUniqueValueFromDB("SELECT count(*) FROM player");
                return $player_count == 6 ? clienttranslate("the front player") : clienttranslate("the third left player");
            case 6: // pass right 3
                return clienttranslate("the third right player");
            case 7: // 8 players - pass front, more players - pass left 4
                $player_count = self::getUniqueValueFromDB("SELECT count(*) FROM player");
                return $player_count == 8 ? clienttranslate("the front player") : 'dummy';
            default:
                return '';
        }
    }

    function brokenHeart(): bool {
        // Check Heart in the played card piles
        return (bool)self::getUniqueValueFromDB("SELECT count(*) FROM card WHERE card_location = 'cardswon' AND card_type = 2");
    }

    function tableHeart(): bool {
        // Check Heart in the current trick
        return (bool)self::getUniqueValueFromDB("SELECT count(*) FROM card WHERE card_location = 'cardsontable' AND card_type = 2");
    }

    function calculateCardPoints ($card, $face_value_scoring, $spades_scoring, $jack_of_diamonds) {
        // Calculate card points
        // Face value scoring: Spot Hearts, Spades scoring: Black Maria
        $score = 0;
        switch ($card['type']) {
            case 1: // Spades
                switch ($card['type_arg']) {
                    case 12: // Queen
                        $score = $face_value_scoring ? -25 : -13;
                        break;
                    case 13: // King (Black Maria variant)
                        $score = $spades_scoring ? ($face_value_scoring ? -20 : -10) : 0;
                        break;
                    case 14: // Ace (Black Maria variant)
                        $score = $spades_scoring ? ($face_value_scoring ? -15 : -7) : 0;
                        break;
                }
                break;
            case 2: // Heart
                $score = $face_value_scoring ? -$card['type_arg'] : -1;
                break;
            case 4: // Jack of Diamonds variant
                if ($card['type_arg'] == 11 && $jack_of_diamonds) $score = $face_value_scoring ? 20 : 10;
                break;
        }
        return $score;
    }

    function checkPlayableCards ($player_id): array {
        // Get all data needed to check playable cards at the moment
        $currentTrickColor = self::getGameStateValue('trick_color');
        $broken_heart = $this->brokenHeart();
        $played_cards_count = $this->cards->countCardInLocation('cardswon');
        $table_cards_count = $this->cards->countCardInLocation('cardsontable');
        $hand = $this->cards->getPlayerHand($player_id);
        $player_count = self::getUniqueValueFromDB("SELECT count(*) FROM player");
        $playable_card_ids = [];
        $all_ids = self::getObjectListFromDB("SELECT card_id FROM card WHERE card_location = 'hand' AND card_location_arg = $player_id", true);
        $point_limit_variant = self::getGameStateValue('point_limit_variant');
        $no_starter_card = self::getGameStateValue('no_starter_card');

        if ($this->cards->getCardsInLocation('cardsontable', $player_id)) return []; // Already played a card

        $non_point_ids = [];
        foreach ($hand as $card) if ($card['type'] != 2 && !($card['type'] == 1 && $card['type_arg'] == 12)) $non_point_ids[] = $card['id'];

        // Check whether the first card of the hand has been played or not
        if (!($played_cards_count + $table_cards_count) && !$no_starter_card) {
            // No cards have been played yet, find and return the starter card only
            switch ($player_count) {
                default:
                    // 2 of Clubs must play first
                    $starter = 2;
                    break;
                case 5:
                case 7:
                    // 3 of Clubs must play first
                    $starter = 3;
                    break;
                case 6:
                case 8:
                    // 4 of Clubs must play first
                    $starter = 4;
                    break;
            }
            foreach ($hand as $card) if ($card['type'] == 3 && $card['type_arg'] == $starter) return [$card['id']];
            return [];
        } else if (!$currentTrickColor) { // First card of the trick
            if ($broken_heart || $point_limit_variant == 2) return $all_ids; // Broken Heart or no limitation, can play any card
            else {
                // Exclude Heart as Heart hasn't been broken yet
                foreach ($hand as $card) if ($card['type'] != 2) $playable_card_ids[] = $card['id'];
                if (!$playable_card_ids) return $all_ids; // All Heart cards!
                else return $playable_card_ids;
            }
        } else {
            // Must follow the lead suit if possible
            $same_suit = false;
            foreach ($hand as $card)
                if ($card['type'] == $currentTrickColor) {
                    $same_suit = true;
                    break;
                }
            if ($same_suit) return self::getObjectListFromDB("SELECT card_id FROM card WHERE card_type = $currentTrickColor AND card_location = 'hand' AND card_location_arg = $player_id", true); // Has at least 1 card of the same suit
            else if ($played_cards_count || !$non_point_ids || $point_limit_variant == 2) return $all_ids; // If not, may play any card...
            else return $point_limit_variant == 1 ? $all_ids : $non_point_ids; // except the first trick which limits points card play
        }
    }

    // Sort cards by suit then number
    function sortCards ($a, $b): int {
        return $a['type'] * 100 + $a['type_arg'] <=> $b['type'] * 100 + $b['type_arg'];
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 

    /*
        Each time a player is doing some game action, one of this method below is called.
        (note: each method below correspond to an input method in hearts.action.php)
    */

    // Play a card from player hand
    function playCard ($card_id) {
        self::checkAction("playCard");
        $player_id = self::getActivePlayerId();
        
        // Check whether the selected card can be played or not
        $playable_cards = $this->checkPlayableCards($player_id);
        if (!in_array($card_id, $playable_cards)) throw new BgaVisibleSystemException(self::_("You cannot play this card now"));

        // Checks are done! now we can play our card
        $currentCard = $this->cards->getCard($card_id);
        // Heartbreak check for its sound effect before moving the card :) - Heart only as the Queen of Spades has its own sound
        $heartbreak = !($this->brokenHeart() || $this->tableHeart()) && $currentCard['type'] == 2;
        $this->cards->moveCard($card_id, 'cardsontable', $player_id);

        // Set the trick color if it hasn't been set yet
        if (!self::getGameStateValue('trick_color')) self::setGameStateValue('trick_color', $currentCard['type']);

        // And notify
        self::notifyAllPlayers('playCard', clienttranslate('${player_name} plays ${color_displayed}${value_displayed}'), [
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'color_displayed' => $this->colors[$currentCard['type']]['name'],
            'value_displayed' => $this->values_label[$currentCard['type_arg']],
            'card' => $currentCard,
            'heartbreak' => $heartbreak,
        ]);

        // Give extra time and end the turn
        self::giveExtraTime($player_id);
        $this->gamestate->nextState('playCard');
    }

    // Give some cards (before the hands begin)
    function giveCards ($card_ids) {
        self::checkAction("giveCards");

        // !! Here we have to get CURRENT player (= player who send the request) and not
        //    active player, cause we are in a multiple active player state and the "active player"
        //    correspond to nothing.
        $card_ids = array_unique($card_ids); // Remove duplicate values
        if (count($card_ids) != 3) throw new BgaVisibleSystemException(self::_("You must give exactly 3 cards"));
        $player_id = self::getCurrentPlayerId();
        $player_to_give_cards = $this->getPlayerToGiveCards($player_id, true);
        if (!$player_to_give_cards) throw new BgaVisibleSystemException(self::_("Error while determining who to give the cards"));

        // Check if these cards are in player hands and record card names
        $cards = $this->cards->getCards($card_ids);
        $card_list = [];
        usort($cards, [$this, "sortCards"]);
        if (count($cards) != 3) throw new BgaVisibleSystemException(self::_("Some of these cards don't exist"));
        foreach ($cards as $card) {
            if ($card['location'] != 'hand' || $card['location_arg'] != $player_id)
                throw new BgaVisibleSystemException(self::_("Some of these cards are not in your hand"));
            $card_list[] = $this->colors[$card['type']]['name'].$this->values_label[$card['type_arg']];
        }

        // Alright, these cards can be given to this player
        // (note: we place the cards in some temporary location in order he can't see them before the hand starts)
        $this->cards->moveCards($card_ids, "temporary", $player_to_give_cards);

        // Notify the player so we can make these cards disappear
        self::notifyPlayer($player_id, "giveCards", clienttranslate('You passed ${card_list} to ${player_name}'), [
            'player_name' => self::getPlayerNameById($player_to_give_cards),
            'cards' => $card_ids,
            'card_list' => implode(', ', $card_list),
        ]);

        // Make this player inactive now
        // (and tell the machine state to use transtion "giveCards" if all players are now inactive
        $this->gamestate->setPlayerNonMultiactive($player_id, '');
    }
    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defines as "game state arguments" (see "args" property in states.inc.php).
        These methods are returning some additional information that are specific to the current
        game state.
    */


    function argPlayerTurn() {
        // Send playable card ids of the active player privately
        return ['_private' => ['active' => ['playableCards' => $this->checkPlayableCards(self::getActivePlayerId())]]];
    }

    function argGiveCards() {
        // Send the translatable name of pass direction
        return [
            "i18n" => ['direction'],
            "direction" => $this->getPassDirectionName(),
        ];
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defines as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stNewHand() {
        // Change round statistics
        self::incStat(1, "handNbr");
        self::setGameStateValue('trick_color', 0);
        self::setGameStateValue('skip_end', 0);

        // In Black Maria variant, the starting player is the next player of the dealer
        $no_starter_card = self::getGameStateValue('no_starter_card');
        if ($no_starter_card) self::setGameStateInitialValue("dealer", self::getPlayerAfter(self::getGameStateValue('dealer')));

        // Take back all cards (from any location => null) to deck
        $this->cards->moveAllCardsInLocation(null, "deck");
        $this->cards->shuffle('deck');

        // Count the number of cards to deal
        $player_list = self::getObjectListFromDB("SELECT player_id id FROM player", true);
        $deal_amount = floor($this->cards->countCardInLocation('deck') / count($player_list));

        // Deal cards to each player
        // Create deck, shuffle it and give initial cards
        foreach ($player_list as $player_id) {
            $cards = $this->cards->pickCards($deal_amount, 'deck', $player_id);

            // Notify player about his cards
            self::notifyPlayer($player_id, 'newHand', '', ['cards' => $cards]);
        }

        // Notification text
        $pass_type = $this->getPassType();
        $notif_text = $pass_type ? clienttranslate('A new hand is dealt. All players must pass 3 cards to ${pass_direction}') : clienttranslate('A new hand is dealt without card pass');

        // Notify the pass direction
        self::notifyAllPlayers("newRound", $notif_text, [
            'i18n' => ['pass_direction'],
            'pass_direction' => $pass_type ? $this->getPassDirectionName() : '',
            'new_dealer' => $no_starter_card ? self::getGameStateValue('dealer') : null,
        ]);

        // If we are in hand type "0" = "keep cards", skip card pass step
        if (!$pass_type) $this->gamestate->nextState("skipPass");
        else $this->gamestate->nextState("giveCards");
    }

    function stTakeCards() {
        // Take cards given by the other player
        $player_list = self::getObjectListFromDB("SELECT player_id id FROM player", true);

        foreach ($player_list as $player_id) {
            // Check the card pass direction and track the card giver
            $card_giver = $this->getPlayerToGiveCards($player_id, false);
            if (!$card_giver) throw new BgaVisibleSystemException(self::_("Error while determining who to give the cards"));

            // Check cards in the "temporary" location which reserves cards to be passed
            $cards = $this->cards->getCardsInLocation("temporary", $player_id);
            if (!$cards) {
                // The other player didn't pass any cards, probably a zombie player
                // Randomly select 3 cards in hand and pass them
                $card_ids = self::getObjectListFromDB("SELECT card_id FROM card WHERE card_location = 'hand' AND card_location_arg = $card_giver", true);
                shuffle($card_ids);
                $selected_card_ids = array_slice($card_ids, 0, 3);
                $this->cards->moveCards($selected_card_ids, "temporary", $player_id);
            }
        }

        foreach ($player_list as $player_id) {
            // Check the card pass direction and track the card giver
            $card_giver = $this->getPlayerToGiveCards($player_id, false);

            // Each player takes cards in the "temporary" location and place it in his hand
            $cards = $this->cards->getCardsInLocation("temporary", $player_id);
            $this->cards->moveAllCardsInLocation("temporary", "hand", $player_id, $player_id);

            // Create received card list
            $card_list = [];
            usort($cards, [$this, "sortCards"]);
            foreach ($cards as $card) $card_list[] = $this->colors[$card['type']]['name'].$this->values_label[$card['type_arg']];

            self::notifyPlayer($player_id, "takeCards", clienttranslate('You received ${card_list} from ${player_name}'), [
                'player_name' => self::getPlayerNameById($card_giver),
                'cards' => $cards,
                'card_list' => implode(', ', $card_list),
            ]);

            // Give extra time to each player
            self::giveExtraTime($player_id);
        }

        $this->gamestate->nextState("");  // For now
    }

    function stNewTrick() {
        // New trick: active player is the last trick winner or the player who owns the starter card
        $played_cards_count = $this->cards->countCardInLocation('cardswon');
        $table_cards_count = $this->cards->countCardInLocation('cardsontable');
        $player_count = self::getUniqueValueFromDB("SELECT count(*) FROM player");
        $transition = 'playerTurn'; // Next turn will begin except under a specific condition
        if (!($played_cards_count + $table_cards_count)) {
            // No cards have been played yet
            if (self::getGameStateValue('no_starter_card')) {
                // Pass the turn to the next player of the dealer
                $this->gamestate->changeActivePlayer(self::getPlayerAfter(self::getGameStateValue('dealer')));
            } else {
                // Find the starter card
                switch ($player_count) {
                    default:
                        // 2 of Clubs must play first
                        $starter = 2;
                        break;
                    case 5:
                    case 7:
                        // 3 of Clubs must play first
                        $starter = 3;
                        break;
                    case 6:
                    case 8:
                        // 4 of Clubs must play first
                        $starter = 4;
                        break;
                }
                $start_player = self::getUniqueValueFromDb("SELECT card_location_arg FROM card WHERE card_location = 'hand' AND card_type = 3 AND card_type_arg = $starter");
                if ($start_player !== null) {
                    // Play the starter automatically
                    $card_data = $this->cards->getCardsOfTypeInLocation(3, $starter, 'hand', $start_player);
                    $card = array_shift($card_data);
                    self::setGameStateValue('trick_color', 3);
                    $this->cards->moveCard($card['id'], 'cardsontable', $start_player);
                    self::notifyAllPlayers('playCard', clienttranslate('${player_name} plays ${color_displayed}${value_displayed}'), [
                        'player_id' => $start_player,
                        'player_name' => self::getPlayerNameById($start_player),
                        'color_displayed' => $this->colors[$card['type']]['name'],
                        'value_displayed' => $this->values_label[$card['type_arg']],
                        'card' => $card,
                        'heartbreak' => false,
                    ]);
    
                    // Pass the turn to the next player
                    $this->gamestate->changeActivePlayer(self::getPlayerAfter($start_player));
                } else throw new BgaVisibleSystemException(self::_("Cannot find the starting card"));
            }
        } else {
            $trick_leader = self::getActivePlayerId();
            $hand_count = $this->cards->countCardInLocation('hand', $trick_leader);
            if ($hand_count > 2 && !self::getGameStateValue('no_trick_skip')) {
                // changed '$hand_count > 1' into > 2 as some players did not like late phase skipping
                // Check automatic hand end (TRAM) is possible
                // Sort all cards in hand first
                $all_hand_cards = $this->cards->getCardsInLocation('hand');
                $sorted_cards = [];
                $player_list = self::getObjectListFromDB("SELECT player_id id FROM player", true);
                foreach ($player_list as $player_id)
                    for ($i = 1; $i <= 4; $i++)
                        $sorted_cards[$player_id][$i] = [];
                foreach ($all_hand_cards as $card) {
                    $sorted_cards[$card['location_arg']][$card['type']][] = $card['type_arg'];
                    sort($sorted_cards[$card['location_arg']][$card['type']]);
                }

                // Check cards by suit
                $tram_possible = true;
                for ($color = 1; $color <= 4; $color++)
                    if (count($sorted_cards[$trick_leader][$color])) { // Skip suits not in the trick leader's hand
                        foreach ($player_list as $player_id)
                            if ($player_id != $trick_leader && count($sorted_cards[$player_id][$color])) {
                                // Another player must have the same suit card to have a chance to beat the lead card
                                // If the leader's lowest card cannot beat another player's highest card, block automatic TRAM
                                if ($sorted_cards[$trick_leader][$color][0] <= $sorted_cards[$player_id][$color][count($sorted_cards[$player_id][$color]) - 1]) {
                                    $tram_possible = false;
                                    break 2;
                                }
                            }
                    }

                if ($tram_possible) {
                    // The lead player will win all remaining tricks regardless of choice!
                    // Sort and record all cards left in hand
                    $x = 1;
                    $cards_left = [];
                    $cards_left_args = [];
                    foreach ($player_list as $player_id) {
                        $cards_left_list = [];
                        $y = 1;
                        $hand = $this->cards->getCardsInLocation('hand', $player_id);
                        usort($hand, [$this, "sortCards"]);
                        foreach ($hand as $card) {
                            $cards_left_list[] = '${card_'.$x.'_'.$y.'_type}${card_'.$x.'_'.$y.'_value}';
                            $cards_left_args['card_'.$x.'_'.$y.'_type'] = $this->colors[$card['type']]['name'];
                            $cards_left_args['card_'.$x.'_'.$y.'_value'] = $this->values_label[$card['type_arg']];
                            $y++;
                        }
                        $x++;
                        $cards_left[$x] = '${player_name'.$x.'} - '.implode(', ', $cards_left_list);
                        $cards_left_args['player_name'.$x] = self::getPlayerNameById($player_id);
                    }
                    $cards_left_final = implode('<br>', $cards_left);

                    // Move all cards to the trick leader and end the hand
                    $winning_hand = $this->cards->getCardsInLocation('hand', $trick_leader);
                    $remaining_cards = $this->cards->getCardsInLocation('hand');
                    $this->cards->moveAllCardsInLocation('hand', 'cardswon', null, $trick_leader);
                    self::setGameStateValue('skip_end', 1);
                    $transition = 'endHand';

                    // Notify
                    usort($winning_hand, [$this, "sortCards"]);
                    self::notifyAllPlayers('earlyEnd', clienttranslate('${player_name} captures all remaining cards and ends this hand<br><br>Cards left:<br>${cards_left}'), [
                        'player_id' => $trick_leader,
                        'player_name' => self::getPlayerNameById($trick_leader),
                        'cards_left' => ['log' => $cards_left_final, 'args' => $cards_left_args],
                        'winning_hand' => $winning_hand,
                        'remaining_cards' => $remaining_cards,
                    ]);
                }
            }
            self::setGameStateValue('trick_color', 0); // Reset trick color to 0 (= no color)
        }
        $this->gamestate->nextState($transition);
    }

    function stNextPlayer() {
        // Active next player OR end the trick and go to the next trick OR end the hand
        $player_count = self::getUniqueValueFromDB("SELECT count(*) FROM player");
        if ($this->cards->countCardInLocation('cardsontable') == $player_count) {
            // This is the end of the trick
            // Who wins ?
            $cards_on_table = $this->cards->getCardsInLocation('cardsontable');
            $best_value = 0;
            $best_value_player_id = null;
            $currentTrickColor = self::getGameStateValue('trick_color');

            foreach ($cards_on_table as $card) {
                if ($card['type'] == $currentTrickColor)   // Note: type = card color
                    if ($best_value_player_id === null) {
                        $best_value_player_id = $card['location_arg'];  // Note: location_arg = player who played this card on table
                        $best_value = $card['type_arg'];        // Note: type_arg = value of the card
                    } else if ($card['type_arg'] > $best_value) {
                        $best_value_player_id = $card['location_arg'];  // Note: location_arg = player who played this card on table
                        $best_value = $card['type_arg'];        // Note: type_arg = value of the card
                    }
            }
            
            if ($best_value_player_id === null) throw new BgaVisibleSystemException(self::_("Error, nobody wins the trick"));

            // Move all cards to "cardswon" of the given player
            $this->cards->moveAllCardsInLocation('cardsontable', 'cardswon', null, $best_value_player_id);

            // Notify
            // Note: we use 2 notifications here in order we can pause the display during the first notification
            // before we move all cards to the winner (during the second)
            self::notifyAllPlayers('simplePause', '', ['time' => 750]);
            self::notifyAllPlayers('giveAllCardsToPlayer', clienttranslate('${player_name} captures the trick'), [
                'player_name' => self::getPlayerNameById($best_value_player_id),
                'player_id' => $best_value_player_id,
                'cards' => $cards_on_table,
            ]);

            // Activate this player => he's the one who starts the next trick
            $this->gamestate->changeActivePlayer($best_value_player_id);

            if (!$this->cards->countCardInLocation('hand')) $this->gamestate->nextState("endHand"); // End of the hand
            else {
                $hand_count = $this->cards->countCardInLocation('hand', $best_value_player_id);

                // Skip remaining tricks if all point cards are out, considering point card variants
                $spades_scoring = self::getGameStateValue('spades_scoring');
                $remaining_heart = (bool)self::getUniqueValueFromDB("SELECT count(*) FROM card WHERE card_location = 'hand' AND card_type = 2"); // Heart
                $remaining_queen = (bool)self::getUniqueValueFromDB("SELECT count(*) FROM card WHERE card_location = 'hand' AND card_type = 1 AND card_type_arg = 12"); // Queen of Spades
                $remaining_king = $spades_scoring && self::getUniqueValueFromDB("SELECT count(*) FROM card WHERE card_location = 'hand' AND card_type = 1 AND card_type_arg = 13"); // King of Spades (Black Maria variant)
                $remaining_ace = $spades_scoring && self::getUniqueValueFromDB("SELECT count(*) FROM card WHERE card_location = 'hand' AND card_type = 1 AND card_type_arg = 14"); // Ace of Spades (Black Maria variant)
                $remaining_jack = self::getGameStateValue('jack_of_diamonds') && self::getUniqueValueFromDB("SELECT count(*) FROM card WHERE card_location = 'hand' AND card_type = 4 AND card_type_arg = 11"); // Jack of Diamonds variant
                if (!$remaining_heart && !$remaining_queen && !$remaining_king && !$remaining_ace && !$remaining_jack && $hand_count > 1 && !self::getGameStateValue('no_trick_skip')) {
                    // All point cards have been played
                    // Sort and record all cards left in hand
                    $x = 1;
                    $cards_left = [];
                    $cards_left_args = [];
                    $player_list = self::getObjectListFromDB("SELECT player_id id FROM player", true);
                    foreach ($player_list as $player_id) {
                        $cards_left_list = [];
                        $y = 1;
                        $hand = $this->cards->getCardsInLocation('hand', $player_id);
                        usort($hand, [$this, "sortCards"]);
                        foreach ($hand as $card) {
                            $cards_left_list[] = '${card_'.$x.'_'.$y.'_type}${card_'.$x.'_'.$y.'_value}';
                            $cards_left_args['card_'.$x.'_'.$y.'_type'] = $this->colors[$card['type']]['name'];
                            $cards_left_args['card_'.$x.'_'.$y.'_value'] = $this->values_label[$card['type_arg']];
                            $y++;
                        }
                        $x++;
                        $cards_left[$x] = '${player_name'.$x.'} - '.implode(', ', $cards_left_list);
                        $cards_left_args['player_name'.$x] = self::getPlayerNameById($player_id);
                    }
                    $cards_left_final = implode('<br>', $cards_left);

                    // Notify
                    self::notifyAllPlayers('earlyEnd', clienttranslate('Ending the hand early as all scoring cards are out<br><br>Cards left:<br>${cards_left}'), [
                        'player_id' => null,
                        'cards_left' => ['log' => $cards_left_final, 'args' => $cards_left_args],
                        'winning_hand' => [],
                        'remaining_cards' => $this->cards->getCardsInLocation('hand'),
                    ]);
                    self::setGameStateValue('skip_end', 1);
                    $this->gamestate->nextState("endHand"); // Early end of the hand
                } else {
                    if ($hand_count == 1) {
                        // Only a single card in hand, play the last card automatically and pass the turn
                        $hand = $this->cards->getPlayerHand($best_value_player_id);
                        $card = array_shift($hand);
                        self::setGameStateValue('trick_color', $card['type']);
                        $this->cards->moveCard($card['id'], 'cardsontable', $best_value_player_id);
                        self::notifyAllPlayers('playCard', clienttranslate('${player_name} plays ${color_displayed}${value_displayed}'), [
                            'player_id' => $best_value_player_id,
                            'player_name' => self::getPlayerNameById($best_value_player_id),
                            'color_displayed' => $this->colors[$card['type']]['name'],
                            'value_displayed' => $this->values_label[$card['type_arg']],
                            'card' => $card,
                            'heartbreak' => false,
                        ]);
                        $this->gamestate->nextState('skip');
                    } else $this->gamestate->nextState("nextTrick"); // End of the trick
                }
            }
        } else {
            // Standard case (not the end of the trick)
            $player_id = self::activeNextPlayer();
            $hand_count = $this->cards->countCardInLocation('hand', $player_id);
            if ($hand_count == 1) {
                // Only a single card in hand, play the last card automatically and pass the turn
                $hand = $this->cards->getPlayerHand($player_id);
                $card = array_shift($hand);
                if (!self::getGameStateValue('trick_color')) self::setGameStateValue('trick_color', $card['type']);
                $this->cards->moveCard($card['id'], 'cardsontable', $player_id);
                // Add some delay before last card play
                self::notifyAllPlayers('simplePause', '', ['time' => 250]);
                self::notifyAllPlayers('playCard', clienttranslate('${player_name} plays ${color_displayed}${value_displayed}'), [
                    'player_id' => $player_id,
                    'player_name' => self::getPlayerNameById($player_id),
                    'color_displayed' => $this->colors[$card['type']]['name'],
                    'value_displayed' => $this->values_label[$card['type_arg']],
                    'card' => $card,
                    'heartbreak' => false,
                ]);
                $this->gamestate->nextState('skip');
            } else $this->gamestate->nextState('nextPlayer'); // Activate the next player
        }
    }

    function stEndHand() {
        // Count and score points, then end the game or go to the next hand.
        $player_list = self::getObjectListFromDB("SELECT player_id id FROM player", true);

        // Get relevant variant status
        $face_value_scoring = self::getGameStateValue('face_value_scoring');
        $spades_scoring = self::getGameStateValue('spades_scoring');
        $jack_of_diamonds = self::getGameStateValue('jack_of_diamonds');
        $moon_variant = self::getGameStateValue('moon_variant');

        // Gets all Heart cards + Queen of Spades + other penalty cards in Black Maria variant
        $player_penalty = [];
        $player_heart_penalty = [];
        $player_points = [];
        $player_total_points = [];
        foreach ($player_list as $player_id) {
            $player_penalty[$player_id] = 0;
            $player_heart_penalty[$player_id] = 0;
            $player_points[$player_id] = 0;
        }
        
        $cards = $this->cards->getCardsInLocation("cardswon");
        $jack_player = null;
        $queen_player = null;
        $king_player = null;
        $ace_player = null;
        $jack_points = 0;
        foreach ($cards as $card) { // Calculate card points and increase stats
            $player_id = $card['location_arg'];
            $card_score = $this->calculateCardPoints($card, $face_value_scoring, $spades_scoring, $jack_of_diamonds);
            $player_penalty[$player_id] += $card_score;
            switch ($card['type']) {
                case 1: // Spades
                    switch ($card['type_arg']) {
                        case 12: // Queen
                            $queen_player = $player_id;
                            self::incStat(1, "getQueenOfSpades", $player_id);
                            break;
                        case 13: // King (Black Maria variant)
                            if ($spades_scoring) {
                                $king_player = $player_id;
                                self::incStat(1, "getKingOfSpades", $player_id);
                            }
                            break;
                        case 14: // Ace (Black Maria variant)
                            if ($spades_scoring) {
                                $ace_player = $player_id;
                                self::incStat(1, "getAceOfSpades", $player_id);
                            }
                            break;
                    }
                    break;
                case 2: // Heart
                    $player_heart_penalty[$player_id] -= $card_score;
                    self::incStat(1, "getHeart", $player_id);
                    break;
                case 4: // Jack of Diamonds variant
                    if ($card['type_arg'] == 11 && $jack_of_diamonds) {
                        $jack_player = $player_id;
                        $jack_points = $this->calculateCardPoints($card, $face_value_scoring, $spades_scoring, $jack_of_diamonds);
                        $player_penalty[$player_id] -= $jack_points; // Prevent double scoring
                        self::incStat(1, "getJackOfDiamonds", $player_id);
                    }
                    break;
            }
        }

        // If someone gets all penalty cards => shooting the moon penalty or bonus
        $nonzero_penalty_list = [];
        foreach ($player_list as $player_id)
            if ($player_penalty[$player_id]) $nonzero_penalty_list[] = $player_id; // Took at least 1 penalty card

        if (count($nonzero_penalty_list) == 1 && $moon_variant != 2) {
            // A single player captured all penalty cards during this hand
            $shooter = array_shift($nonzero_penalty_list);
            self::incStat(1, "getAllPointCards", $shooter);
            self::incStat(1, "getNoPoints", $shooter);
            // Shot the moon, calculate moon score dynamically
            $shoot_score = $player_penalty[$shooter];
            foreach ($player_list as $player_id) {
                if ($player_id == $shooter) $player_points[$player_id] = $moon_variant ? -$shoot_score : 0;
                else $player_points[$player_id] = $moon_variant ? 0 : $shoot_score;
            }
            self::notifyAllPlayers("noSound", clienttranslate('${player_name} captured all penalty cards!'), ['player_name' => self::getPlayerNameById($shooter)]);
        } else {
            // Normal scoring
            foreach ($player_list as $player_id) {
                if ($player_penalty[$player_id]) {
                    // Captured at least 1 penalty card
                    $player_points[$player_id] = $player_penalty[$player_id];
                    self::notifyAllPlayers("noSound", $player_penalty[$player_id] == -1 ? clienttranslate('${player_name} loses 1 point') : clienttranslate('${player_name} loses ${points} points'), [
                        'player_name' => self::getPlayerNameById($player_id),
                        'points' => -$player_penalty[$player_id],
                    ]);
                } else self::incStat(1, "getNoPoints", $player_id); // Captured no penalty points
            }
        }

        if ($jack_player) {
            // Jack of Diamonds bonus
            $player_points[$jack_player] += $jack_points;
            self::notifyAllPlayers("noSound", clienttranslate('${player_name} captured ${jack_of_diamonds} and gains ${nb} bonus points'), [
                'player_name' => self::getPlayerNameById($jack_player),
                'jack_of_diamonds' => $this->colors[4]['name'].'J',
                'nb' => $jack_points,
            ]);
        }

        // Update the scores
        foreach ($player_points as $player_id => $points) $player_total_points[$player_id] = $this->dbIncScore($player_id, $points);
        $newScores = self::getCollectionFromDb("SELECT player_id, player_score FROM player", true);
        self::notifyAllPlayers("newScores", '', ['newScores' => $newScores]);
        
        //////////// Display table window with results /////////////////

        // Table labels
        $nameRow = [''];
        $heartRow = [['str' => $this->colors[2]['name'], 'args' => []]];
        $queenRow = [['str' => $this->colors[1]['name'].'Q', 'args' => []]];
        $kingRow = [['str' => $this->colors[1]['name'].'K', 'args' => []]];
        $aceRow = [['str' => $this->colors[1]['name'].'A', 'args' => []]];
        $jackRow = [['str' => $this->colors[4]['name'].'J', 'args' => []]];
        $scoreRow = [['str' => clienttranslate('Hand score'), 'args' => []]];
        $totalRow = [['str' => clienttranslate('Total score'), 'args' => []]];

        foreach ($player_list as $player_id) {
            // Header line
            $nameRow[] = [
                'str' => '${player_name}',
                'args' => ['player_name' => self::getPlayerNameById($player_id)],
                'type' => 'header',
            ];

            // Captured Heart
            $heartRow[] = $player_heart_penalty[$player_id] ?: '';

            // Captured Spades
            $queenRow[] = $queen_player == $player_id ? '✓' : '';
            if ($king_player) $kingRow[] = $king_player == $player_id ? '✓' : '';
            if ($ace_player) $aceRow[] = $ace_player == $player_id ? '✓' : '';

            // Captured the Jack of Diamonds
            if ($jack_player) $jackRow[] = $jack_player == $player_id ? '✓' : '';

            // Hand score and total score
            $scoreRow[] = $player_points[$player_id];
            $totalRow[] = $player_total_points[$player_id];
        }

        $table = [$nameRow, $heartRow, $queenRow];
        if ($king_player) $table[] = $kingRow;
        if ($ace_player) $table[] = $aceRow;
        if ($jack_player) $table[] = $jackRow;
        $table[] = $scoreRow;
        $table[] = $totalRow;

        $this->notifyAllPlayers("tableWindow", '', [
            "id" => 'finalScoring',
            "title" => self::getGameStateValue('skip_end') ? clienttranslate("Hand result (remaining tricks are skipped)") : clienttranslate("Hand result"),
            "table" => $table,
            "closing" => clienttranslate("Close"),
        ]);


        ///// Test if this is the end of the game
        if (self::getUniqueValueFromDb("SELECT MIN(player_score) FROM player") <= 0)
            $this->gamestate->nextState("endGame"); // Someone is dropped to 0 or below, trigger the end of the game !
        else $this->gamestate->nextState("nextHand"); // Otherwise, start a new hand
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player that quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player will end
        (ex: pass).
    */

    function zombieTurn ($state, $active_player) {
        $statename = $state['name'];

        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                case "playerTurn":
                    // Select a random playable card and play it
                    $playable_cards = $this->checkPlayableCards($active_player);
                    shuffle($playable_cards);
                    $card_id = array_shift($playable_cards);
                    $this->playCard($card_id);
                    break;
            }
            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive($active_player, '');
            return;
        }

        throw new feException("Zombie mode not supported at this game state: ".$statename);
    }
}
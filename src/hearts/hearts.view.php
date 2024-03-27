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
 * hearts.view.php
 *
 * This is your "view" file.
 *
 * The method "build_page" below is called each time the game interface is displayed to a player, ie:
 * _ when the game starts
 * _ when a player refreshes the game page (F5)
 *
 * "build_page" method allows you to dynamically modify the HTML generated for the game interface. In
 * particular, you can set here the values of variables elements defined in emptygame_emptygame.tpl (elements
 * like {MY_VARIABLE_ELEMENT}), and insert HTML block elements (also defined in your HTML template file)
 *
 * Note: if the HTML of your game interface is always the same, you don't have to place anything here.
 *
 */

/**
 * Note: this code is modified to add suggestions from BGA players and popular variants.
 * Please visit here to read the basic code used in the BGA Studio tutorial: https://github.com/elaskavaia/
 */

require_once(APP_BASE_PATH."view/common/game.view.php");

class view_hearts_hearts extends game_view {
    function getGameName() {
        return "hearts";
    }

    function build_page ($viewArgs) {
        // Get players & players number
        global $g_user;
        $game_name = self::getGameName();
        $template = $game_name . "_" . $game_name;
        $players = $this->game->loadPlayersBasicInfos();
        $players_nbr = count($players);

        /*********** Place your code below:  ************/

        $this->tpl['MY_HAND'] = self::_("My hand");
        $this->tpl['SCORE_LABEL'] = self::_("Score:");
        $this->tpl['SCORE_CHART'] = self::_("Points");

        // Card points chart
        $jack_of_diamonds = $this->game->getGameStateValue("jack_of_diamonds");
        $face_value_scoring = $this->game->getGameStateValue("face_value_scoring");
        $spades_scoring = $this->game->getGameStateValue("spades_scoring");
        $this->tpl['JACK_DISPLAY'] = $jack_of_diamonds ? '' : 'none';
        $this->tpl['SPADES_DISPLAY'] = $spades_scoring ? '' : 'none';
        $this->tpl['HEART_VALUE'] = $face_value_scoring ? self::_("-Rank") : '-1';
        $this->tpl['JACK_VALUE'] = $face_value_scoring ? '20' : '10';
        $this->tpl['ACE_VALUE'] = $face_value_scoring ? '-15' : '-7';
        $this->tpl['KING_VALUE'] = $face_value_scoring ? '-20' : '-10';
        $this->tpl['QUEEN_VALUE'] = $face_value_scoring ? '-25' : '-13';
        $this->tpl['NO_SCORE_CHART'] = $jack_of_diamonds || $face_value_scoring || $spades_scoring ? '' : 'no_score_chart';
        $this->tpl['HIDE_SCORE_CHART'] = $jack_of_diamonds || $face_value_scoring || $spades_scoring ? '' : 'none';

        // Removed cards
        if ($players_nbr != 4) {
            $this->tpl['REMOVED_LABEL'] = self::_("Removed: ");
            switch ($players_nbr) {
                case 3:
                    $this->tpl['REMOVED_CARDS'] = self::raw('<span style="color:red">♦</span>2');
                    break;
                case 5:
                    $this->tpl['REMOVED_CARDS'] = self::raw('♣2, <span style="color:red">♦</span>2');
                    break;
                case 6:
                case 8:
                    $this->tpl['REMOVED_CARDS'] = self::raw('♠2, ♣2, ♣3, <span style="color:red">♦</span>2');
                    break;
                case 7:
                    $this->tpl['REMOVED_CARDS'] = self::raw('♠2, ♣2, <span style="color:red">♦</span>2');
                    break;
                default:
                    $this->tpl['REMOVED_CARDS'] = "dummy";
                    break;
            }
        } else {
            $this->tpl['REMOVED_LABEL'] = "";
            $this->tpl['REMOVED_CARDS'] = "";
        }

        // Display other variants
        $variant = false;

        $point_limit_variant = $this->game->getGameStateValue("point_limit_variant");
        if ($point_limit_variant) {
            $this->tpl['POINT_LIMIT'] = $point_limit_variant == 1 ? self::_("Points in the first trick") : self::_("No penalty card play limit");
            $variant = true;
        } else $this->tpl['POINT_LIMIT'] = "";

        $no_starter_card = $this->game->getGameStateValue("no_starter_card");
        if ($no_starter_card) {
            $this->tpl['GAP_1'] = $variant ? ", " : "";
            $this->tpl['NO_STARTER'] = self::_("No starter card");
            $variant = true;
        } else {
            $this->tpl['GAP_1'] = "";
            $this->tpl['NO_STARTER'] = "";
        }

        $moon_variant = $this->game->getGameStateValue("moon_variant");
        if ($moon_variant) {
            $this->tpl['GAP_2'] = $variant ? ", " : "";
            $this->tpl['MOON'] = $moon_variant == 1 ? self::_("Positive moon scoring") : self::_("No shooting the moon");
            $variant = true;
        } else {
            $this->tpl['GAP_2'] = "";
            $this->tpl['MOON'] = "";
        }

        $pass_cycle = $this->game->getGameStateValue("pass_cycle");
        if ($pass_cycle) {
            $this->tpl['GAP_3'] = $variant ? ", " : "";
            $this->tpl['PASS_CYCLE'] = $pass_cycle == 1 ? self::_("Remove no pass hands") : self::_("Always pass right");
            $variant = true;
        } else {
            $this->tpl['GAP_3'] = "";
            $this->tpl['PASS_CYCLE'] = "";
        }

        // Add line break or hide variant table if needed
        $this->tpl['LINE_BREAK'] = ($variant && $this->tpl['REMOVED_CARDS']) ? self::raw("<br>") : "";
        $this->tpl['NO_VARIANT'] = ($variant || $this->tpl['REMOVED_CARDS']) ? "" : "none";

        // Arrange players so that I am on south
        switch ($players_nbr) {
            case 3:
                $directions = ['S', 'NW', 'NE'];
                break;
            case 4:
                $directions = ['S', 'W', 'N', 'E'];
                break;
            case 5:
                $directions = ['S', 'SW', 'NW', 'NE', 'SE'];
                break;
            case 6:
                $directions = ['S', 'SW', 'NW', 'N', 'NE', 'SE'];
                break;
            case 7:
                $directions = ['S', 'SW', 'W_X', 'NW', 'NE', 'E_X', 'SE'];
                break;
            case 8:
                $directions = ['S', 'SW', 'W_X', 'NW', 'N', 'NE', 'E_X', 'SE'];
                break;
        }

        // Extend game table in 7-8 player games
        $this->tpl['EXTENDED'] = $players_nbr > 6 ? 'extended' : '';

        $this->page->begin_block($template, "player");
        if ($this->game->isSpectator()) {
            // Spectator mode: take any player for south
            foreach ($players as $player_id => $info) {
                $dir = array_shift($directions);
                $this->page->insert_block("player", [
                    "PLAYER_ID" => $player_id,
                    "PLAYER_NAME" => $info['player_name'],
                    "PLAYER_COLOR" => $info['player_color'],
                    "DIR" => $dir,
                ]);
            }
        } else {
            // Normal mode: current player is on south
            $player_id = $g_user->get_id();
            for ($i = 1; $i <= $players_nbr; $i++) {
                $dir = array_shift($directions);
                $this->page->insert_block("player", [
                    "PLAYER_ID" => $player_id,
                    "PLAYER_NAME" => $players[$player_id]['player_name'],
                    "PLAYER_COLOR" => $players[$player_id]['player_color'],
                    "DIR" => $dir,
                ]);
                $player_id = $this->game->getPlayerAfter($player_id);
            }
        }

        // Load audio files
        $this->page->begin_block($template, "audio_list");
        $audio_list = $this->game->audio_list;
        foreach ($audio_list as $audio) {
            $this->page->insert_block("audio_list", [
                "GAME_NAME" => $game_name,
                "AUDIO" => $audio,
            ]);
        }

        /*********** Do not change anything below this line  ************/
    }
}

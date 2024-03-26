<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Reversi implementation : © Gregory Isabelli <gisabelli@boardgamearena.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * burglebrostwo.view.php
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

require_once APP_BASE_PATH . "view/common/game.view.php";

class view_burglebrostwo_burglebrostwo extends game_view
{
    function getGameName()
    {
        return "burglebrostwo";
    }
    function build_page($viewArgs)
    {
        // Get players & players number
        $players = $this->game->loadPlayersBasicInfos();
        // $players = $this->game->loadPlayersInfos();
        $players_nbr = count($players);
        $template = self::getGameName() . "_" . self::getGameName();
        $max_floor = 2; // $this->game->getFloorCount();

        $this->page->begin_block($template, "tiles");
        for ($floor = 1; $floor <= $max_floor; $floor++) {
            $this->page->insert_block("tiles", ["FLOOR" => $floor]);
        }
        $this->page->begin_block($template, "patrol");
        for ($floor = 1; $floor <= $max_floor; $floor++) {
            $this->page->insert_block("patrol", ["FLOOR" => $floor]);
        }
        $this->page->begin_block($template, "floor_preview");
        for ($floor = 1; $floor <= $max_floor; $floor++) {
            $this->page->insert_block("floor_preview", ["FLOOR" => $floor]);
        }

        global $g_user;
        $current_player_id = $g_user->get_id();

        $this->page->begin_block($template, "player_hand");
        $index = 2;
        foreach ($players as $player_id => $player) {
            if ($player_id != $current_player_id) {
                $this->page->insert_block("player_hand", [
                    "PLAYER_NAME" => $player["player_name"],
                    "PLAYER_COLOR" => $player["player_color"],
                    "PLAYER_ID" => $player_id,
                    "PLAYER_INDEX" => $index++,
                ]);
            }
        }

        $this->tpl["MY_HAND"] = self::_("Character choices");
    }
}

<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * EmptyGame implementation : © Nicolas Gocel <nicolas.gocel@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * emptygame.view.php
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

class view_thecrew_thecrew extends game_view
{
    function getGameName()
    {
        return "thecrew";
    }
    function build_page($viewArgs)
    {
        // Get players & players number
        $players = $this->game->loadPlayersBasicInfos();
        $players_nbr = count($players);

        /*********** Place your code below:  ************/

        // Get players & players number
        $players_nbr_class =
            $players_nbr == 3
                ? "three_players"
                : ($players_nbr == 4
                    ? "four_players"
                    : "five_players");

        // Arrange players so that I am on south
        $player_positions = $this->game->getPlayerRelativePositions();

        $this->page->begin_block("thecrew_thecrew", "player");
        foreach ($player_positions as $player_id => $dir) {
            $this->page->insert_block("player", [
                "PLAYER_ID" => $player_id,
                "PLAYER_NAME" => $players[$player_id]["player_name"],
                "PLAYER_COLOR" => $players[$player_id]["player_color"],
                "DIR" => $dir,
            ]);
        }

        $this->tpl["MISSION"] = self::_("Mission");
        $this->tpl["TRY"] = self::_("Mission attempts : ");
        $this->tpl["TOTALTRY"] = self::_("Total attempts : ");
        $this->tpl["TASKS"] = self::_("Available tasks");
        $this->tpl["NBR"] = $players_nbr_class;
        $this->tpl["CONTINUE"] = self::_("Do you want to continue?");
        $this->tpl["YES"] = self::_("Yes");
        $this->tpl["NO"] = self::_("No");

        /*********** Do not change anything below this line  ************/
    }
}

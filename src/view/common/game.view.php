<?php

require_once APP_GAMEMODULE_PATH . "view/common/util.php";
require_once APP_GAMEMODULE_PATH . "view/common/template.php";
require_once APP_GAMEMODULE_PATH . "module/tablemanager/tablemanager.php";

class game_view
{
    public $game;
    public $template;
    public $tpl;
    public $page;

    function __construct($game)
    {
        $this->game = $game;

        $this->template = new Template("default");
        $this->template->set_filenames([
            "game" =>
                APP_BASE_PATH .
                $this->getGameName() .
                "/" .
                $this->getGameName() .
                "_" .
                $this->getGameName() .
                ".tpl",
            "global" => APP_BASE_PATH . "view/common/global.tpl",
        ]);
        $this->tpl = [];
        $this->page = $this;

        $this->tpl["CURRENT_PLAYER"] = $this->game->getCurrentPlayerId();
        $this->tpl["LOGS"] = self::_("What happened?");
        $this->tpl["SURE"] = self::_("Are you sure?");
        $this->tpl["NO"] = self::_("No");
        $this->tpl["CONFIRM"] = self::_("Yes. I Confirm");
        $this->tpl["SAVE"] = self::_("Save current game state");
        $this->tpl["LOAD"] = self::_("Load previously saved game state");
    }

    function begin_block($template_name, $block_name)
    {
    }

    function insert_block($block, $arrval)
    {
        $this->template->assign_block_vars($block, $arrval);
    }

    function _($key)
    {
        return $this->game->_($key);
    }

    function getGameName()
    {
        return $this->game->getGameName();
    }

    function getFullDatasAsJson()
    {
        return json_encode($this->game->getFullDatas());
    }

    function display()
    {
        $this->build_page(null);
        $this->template->assign_vars($this->tpl);

        $players = $this->game->loadPlayersBasicInfos();
        $player_positions = $this->game->getPlayerRelativePositions();

        asort($player_positions);
        foreach ($player_positions as $player_id => $dir) {
            $player = $players[$player_id];
            $this->insert_block("bg_player", [
                "PLAYER_ID" => $player["player_id"],
                "PLAYER_NAME" => $player["player_name"],
                "PLAYER_COLOR" => $player["player_color"],
            ]);
        }

        $this->template->assign_var_from_handle("GAME_PLAY_AREA", "game");
        $this->template->pparse("global");
    }

    function build_page($viewArgs)
    {
    }

    // Encodes the raw HTML in $s so that when it is assigned to a
    // template variable and then rendered, the result is exactly $s.
    public function raw($s) {
        // XXX: This doesn't seem like it should be correct, but it
        // appears to work (?).
        return $s;
    }
}

<?php

require_once APP_GAMEMODULE_PATH . "module/table/table.game.php";

class localarenanoop extends Table
{
    function __construct()
    {
        parent::__construct();
        self::initGameStateLabels([
        ]);
    }

    protected function getGameName()
    {
        // Used for translations and stuff. Please do not modify.
        return "localarenanoop";
    }

    /*
     setupNewGame:

     This method is called only once, when a new game is launched.
     In this method, you must setup the game according to the game rules, so that
     the game is ready to be played.
     */
    protected function setupNewGame($players, $options = [])
    {
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos["player_colors"];

        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql =
            "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = [];
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $values[] =
                "('" .
                $player_id .
                "','$color','" .
                $player["player_canal"] .
                "','" .
                addslashes($player["player_name"]) .
                "','" .
                addslashes($player["player_avatar"]) .
                "')";
        }
        $sql .= implode(",", $values);
        self::DbQuery($sql);
        self::reattributeColorsBasedOnPreferences(
            $players,
            $gameinfos["player_colors"]
        );
        self::reloadPlayersBasicInfos();
    }

    /*
     getAllDatas:

     Gather all informations about current game situation (visible by the current player).

     The method is called each time the game interface is displayed to a player, ie:
     _ when the game starts
     _ when a player refreshes the game page (F5)
     */
    protected function getAllDatas()
    {
        return [];
    }

    /*
     getGameProgression:

     Compute and return the current game progression.
     The number returned must be an integer beween 0 (=the game just started) and
     100 (= the game is finished or almost finished).

     This method is called each time we are in a game state with the "updateGameProgression" property set to true
     (see states.inc.php)
     */
    function getGameProgression()
    {
        return 42;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Zombie
    ////////////

    /*
     zombieTurn:

     This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     You can do whatever you want in order to make sure the turn of this player ends appropriately
     (ex: pass).

     Important: your zombie code will be called when the player leaves the game. This action is triggered
     from the main site and propagated to the gameserver from a server, not from a browser.
     As a consequence, there is no current player associated to this action. In your zombieTurn function,
     you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message.
     */

    function zombieTurn($state, $active_player)
    {
        $statename = $state["name"];

        if ($state["type"] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState("zombiePass");
                    break;
            }

            return;
        }

        if ($state["type"] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive($active_player, "");

            return;
        }

        throw new feException(
            "Zombie mode not supported at this game state: " . $statename
        );
    }

    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// DB upgrade
    //////////

    /*
     upgradeTableDb:

     You don't have to care about this until your game has been published on BGA.
     Once your game is on BGA, this method is called everytime the system detects a game running with your old
     Database scheme.
     In this case, if you change your Database scheme, you just have to apply the needed changes in order to
     update the game database and allow the game to continue to run with your new version.

     */

    function upgradeTableDb($from_version)
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345

        // Example:
        //        if( $from_version <= 1404301345 )
        //        {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
        //            self::applyDbUpgradeToAllDB( $sql );
        //        }
        //        if( $from_version <= 1405061421 )
        //        {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
        //            self::applyDbUpgradeToAllDB( $sql );
        //        }
        //        // Please add your future database scheme changes here
        //
        //
    }
}

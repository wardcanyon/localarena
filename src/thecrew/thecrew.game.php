<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * thecrew implementation : © Nicolas Gocel <nicolas.gocel@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * thecrew.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */

require_once APP_GAMEMODULE_PATH . "module/table/table.game.php";

include "modules/THCCheck.php";
include "modules/THCCheck5.php";
include "modules/THCCheck9.php";
include "modules/THCCheck13.php";
include "modules/THCCheck16.php";
include "modules/THCCheck17.php";
include "modules/THCCheck26.php";
include "modules/THCCheck29.php";
include "modules/THCCheck33.php";
include "modules/THCCheck34.php";
include "modules/THCCheck41.php";
include "modules/THCCheck44.php";
include "modules/THCCheck46.php";
include "modules/THCCheck48.php";
include "modules/THCCheck50.php";

define("COMM", 6);

class thecrew extends Table
{
    function __construct()
    {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();

        self::initGameStateLabels([
            "trick_count" => 10,
            "commander_id" => 11,
            "trick_color" => 12,
            "distress_turn" => 13,
            "last_winner" => 16,
            "comm_id" => 17,
            "mission_finished" => 18,
            "special_id" => 19,
            "check_count" => 20,
            "special_id2" => 21,
            "end_game" => 22,

            "mission_start" => 100,
            "challenge" => 101,
        ]);

        $this->cards = self::getNew("module.common.deck");
        $this->cards->init("card");
    }

    protected function getGameName()
    {
        // Used for translations and stuff. Please do not modify.
        return "thecrew";
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

        /************ Start the game initialization *****/

        // Init global values with their initial values
        self::setGameStateInitialValue("trick_count", 0);
        self::setGameStateInitialValue("commander_id", 0);
        self::setGameStateInitialValue("last_winner", 0);
        self::setGameStateInitialValue("trick_color", 0);
        self::setGameStateInitialValue("mission_finished", 0);
        self::setGameStateInitialValue("distress_turn", 0);
        self::setGameStateInitialValue("special_id", 0);
        self::setGameStateInitialValue("special_id2", 0);
        self::setGameStateInitialValue("check_count", 0);
        self::setGameStateInitialValue("end_game", 0);

        self::setGameStateInitialValue("mission_start", 3);
        self::setGameStateInitialValue("challenge", 2);

        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
        //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)

        // Create cards
        $cards = [];
        foreach ($this->colors as $color_id => $color) {
            if ($color_id < 6) {
                if (
                    $color_id == 2 &&
                    self::getGameStateValue("challenge") == 2
                ) {
                    continue;
                }

                for ($value = 1; $value <= ($color_id == 5 ? 4 : 9); $value++) {
                    if (
                        $color_id == 5 &&
                        $value == 1 &&
                        self::getGameStateValue("challenge") == 2
                    ) {
                        continue;
                    }

                    $cards[] = [
                        "type" => $color_id,
                        "type_arg" => $value,
                        "nbr" => 1,
                    ];
                }
            }
        }

        $cards[] = ["type" => COMM, "type_arg" => 0, "nbr" => count($players)];
        $this->cards->createCards($cards, "deck");
        $this->cards->shuffle("deck");

        $mission_start = $this->getGameStateValue("mission_start");
        if ($mission_start == 999) {
            $this->load();
        } else {
            //initiate logbook
            $sql =
                "INSERT INTO logbook (mission) VALUES (" . $mission_start . ")";
            self::DbQuery($sql);
        }

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        /************ End of the game initialization *****/
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
        $result = [];
        $current_player_id = self::getCurrentPlayerId(); // !! We must only return informations visible by this player !!

        $relative = $this->getPlayerRelativePositions();

        $result["trick_count"] = self::getGameStateValue("trick_count");
        $result["mission"] = self::getUniqueValueFromDB(
            "SELECT max(mission) FROM logbook"
        );
        $result["mission_attempts"] = self::getUniqueValueFromDB(
            "SELECT attempt FROM logbook where mission=" . $result["mission"]
        ); // NOI18N;
        $result["distress"] = self::getUniqueValueFromDB(
            "SELECT distress FROM logbook where mission=" . $result["mission"]
        ); // NOI18N;
        $result["total_attempts"] = self::getUniqueValueFromDB(
            "SELECT sum(attempt) FROM logbook"
        );
        $result["commander_id"] = self::getGameStateValue("commander_id");
        $result["special_id"] = self::getGameStateValue("special_id");
        $result["special2_id"] = self::getGameStateValue("special_id2");
        $result["colors"] = $this->colors;

        // Cards in player hand
        $result["hand"] = $this->cards->getCardsInLocation(
            "hand",
            $current_player_id
        );

        $sql =
            "SELECT player_id id, player_trick_number, comm_token , player_score score FROM player ";
        $result["players"] = self::getCollectionFromDb($sql);

        foreach ($result["players"] as $player_id => $player) {
            $result["players"][$player_id]["relative"] = $relative[$player_id];
            $result["players"][$player_id][
                "cards_number"
            ] = $this->cards->countCardInLocation("hand", $player_id);
            $cards = $this->cards->getCardsInLocation(
                "cardsontable",
                $player_id
            );
            if (count($cards) > 0) {
                $result["players"][$player_id]["cardontable"] = array_shift(
                    $cards
                );
            }
            $cards = $this->cards->getCardsInLocation("comm", $player_id);
            if (count($cards) > 0) {
                $result["players"][$player_id]["comm"] = array_shift($cards);
            }
        }

        $sql =
            "SELECT task_id, card_type, card_type_arg, token, player_id, status FROM task where player_id IS NOT NULL";
        $result["tasks"] = self::getCollectionFromDb($sql);
        return $result;
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
        // TODO: compute and return the game progression

        $trickCOunt = $this->getGameStateValue("trick_count");
        $nbCards = $this->getGameStateValue("challenge") == 2 ? 30 : 40;
        $nbPlayers = thecrew::getUniqueValueFromDB(
            "SELECT count(*) from player"
        );
        $nbTricks = intdiv($nbCards, $nbPlayers);

        $prog = 0;
        if ($nbTricks > 0) {
            $prog = ($trickCOunt * 100) / $nbTricks;
        }
        return $prog;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////

    function getCollectionFromDB(
        $sql,
        $bSingleValue = false,
        $low_priority_select = false
    ) {
        return parent::getCollectionFromDb(
            $sql,
            $bSingleValue,
            $low_priority_select
        );
    }

    function getObjectFromDB($sql, $low_priority_select = false)
    {
        return parent::getObjectFromDB($sql, $low_priority_select);
    }

    // XXX: Needs to be static.
    function getUniqueValueFromDB($sql, $low_priority_select = false)
    {
        return parent::getUniqueValueFromDB($sql, $low_priority_select);
    }

    // XXX: Needs to be static.
    function DbQuery($sql, $specific_db = null, $bMulti = false)
    {
        return parent::DbQuery($sql, $specific_db, $bMulti);
    }

    function getLog()
    {
        $sql = "SELECT mission, attempt, success, distress FROM log";
        $log = self::getCollectionFromDb($sql);
        return $log;
    }

    function getMission()
    {
        $missionnb = self::getUniqueValueFromDB(
            "SELECT max(mission) FROM logbook"
        );
        return $this->missions[$missionnb];
    }

    function getPlayerName($player_id)
    {
        $players = self::loadPlayersBasicInfos();
        return $players[$player_id]["player_name"];
    }

    function getPossibleStatus($card)
    {
        $ret = [];
        $player_id = $card["location_arg"];
        $same = $this->cards->getCardsOfTypeInLocation(
            $card["type"],
            null,
            "hand",
            $player_id
        );
        $min = $card["type_arg"];
        $max = $card["type_arg"];
        $nb = 0;

        foreach ($same as $cardt) {
            if ($card["id"] != $cardt["id"]) {
                $nb++;
            }
            $val = $cardt["type_arg"];
            $min = min($min, $val);
            $max = max($max, $val);
        }

        if ($card["type_arg"] == $min) {
            $ret[] = "bottom";
        }
        if ($card["type_arg"] == $max) {
            $ret[] = "top";
        }
        if ($nb == 0) {
            $ret[] = "middle";
        }

        return $ret;
    }

    function getCommunicationCard($player_id)
    {
        $ret = null;
        $cards = $this->cards->getCardsInLocation("comm", $player_id);
        if (count($cards) > 0) {
            $ret = array_shift($cards);
        }
        return $ret;
    }

    function getReminderCard($player_id)
    {
        $ret = null;
        $cards = $this->cards->getCardsOfTypeInLocation(
            6,
            0,
            "hand",
            $player_id
        );
        if (count($cards) > 0) {
            $ret = array_shift($cards);
        }
        return $ret;
    }

    function getPlayerRelativePositions()
    {
        $result = [];

        $players = self::loadPlayersBasicInfos();
        $nextPlayer = self::createNextPlayerTable(array_keys($players));

        $current_player = self::getCurrentPlayerId();

        if (!isset($nextPlayer[$current_player])) {
            // Spectator mode: take any player for south
            $player_id = $nextPlayer[0];
        } else {
            // Normal mode: current player is on south
            $player_id = $current_player;
        }

        $result[$player_id] = 0;

        for ($i = 1; $i < count($players); $i++) {
            $player_id = $nextPlayer[$player_id];
            $result[$player_id] = $i;
        }
        return $result;
    }

    function listCardsForNotification($cards)
    {
        $values_by_color = [];
        foreach ($cards as $card) {
            $color = $card["type"];
            $value = $card["type_arg"];
            if (array_key_exists($color, $values_by_color)) {
                $values_by_color[$color][] = $value;
            } else {
                $values_by_color[$color] = [$value];
            }
        }

        $colors_log_as_array = [];
        $colors_args = [];
        ksort($values_by_color);
        foreach ($values_by_color as $color => $values) {
            sort($values);
            $color_key = "color_" . $color;
            $colors_log_as_array[] = '${' . $color_key . "}";

            $values_log_as_array = [];
            $values_args = [];
            $i = 1;
            foreach ($values as $value) {
                $value_key = "card_" . $color . "_" . $value;
                $values_log_as_array[] = '${' . $value_key . "}";
                $values_args[$value_key] = [
                    "log" =>
                        $i == count($values)
                            ? '${value_symbol} ${color_symbol}'
                            : '${value_symbol}',
                    "args" => [
                        "value_symbol" => $value,
                        "color_symbol" => $color,
                    ],
                ];
                $i++;
            }
            $values_log = join(" ", $values_log_as_array);
            $colors_args[$color_key] = [
                "log" => $values_log,
                "args" => $values_args,
            ];
        }
        $colors_log = join("&nbsp;<br />", $colors_log_as_array); // NOI18N

        return ["log" => $colors_log, "args" => $colors_args];
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    ////////////

    function actButton($button)
    {
        self::checkAction("actButton");
        switch ($this->gamestate->state()["name"]) {
            case "endMission":
                if ($button == "yes") {
                    self::notifyAllPlayers(
                        "note",
                        clienttranslate('${player_name} wants to continue'),
                        [
                            "player_name" => self::getPlayerName(
                                self::getCurrentPlayerId()
                            ),
                        ]
                    );

                    $this->gamestate->setPlayerNonMultiactive(
                        $this->getCurrentPlayerId(),
                        "next"
                    );
                } else {
                    self::setGameStateValue("end_game", 1);
                    self::notifyAllPlayers(
                        "note",
                        clienttranslate('${player_name} wants to stop'),
                        [
                            "player_name" => self::getPlayerName(
                                self::getCurrentPlayerId()
                            ),
                        ]
                    );

                    $this->gamestate->nextState("next");
                }
                break;

            case "distressSetup":
                $left = $button == "left";
                $text = clienttranslate(
                    "Cards will be passed to the <b>left</b>"
                );
                if (!$left) {
                    $text = clienttranslate(
                        "Cards will be passed to the <b>right</b>"
                    );
                }

                self::notifyAllPlayers("note", $text, [
                    "player_name" => self::getPlayerName(
                        self::getCurrentPlayerId()
                    ),
                ]);

                self::setGameStateValue("distress_turn", $left ? 1 : 0);

                $this->gamestate->setAllPlayersMultiactive();
                $this->gamestate->nextState("next");
                break;

            case "question":
                $index = intval($button);
                $mission = $this->getMission();
                $replies = $mission["replies"];
                $reply = explode("/", $replies)[$index];

                self::notifyAllPlayers("speak", '${player_name} : ' . $reply, [
                    // NOI18N
                    "player_id" => self::getCurrentPlayerId(),
                    "player_name" => self::getPlayerName(
                        self::getCurrentPlayerId()
                    ),
                    "content" => $reply,
                ]);
                $this->gamestate->nextState("next");
                break;
        }
    }

    function actMultiSelect($id1, $id2)
    {
        self::checkAction("actMultiSelect");

        $mission = $this->getMission();

        switch ($mission["id"]) {
            case 23:
            case 40:
                $idl1 = str_replace("marker_", "", $id1);
                $idl2 = str_replace("marker_", "", $id2);
                $t1 = self::getUniqueValueFromDB(
                    "SELECT task_id FROM task where token = '" . $idl1 . "'"
                );
                $t2 = self::getUniqueValueFromDB(
                    "SELECT task_id FROM task where token = '" . $idl2 . "'"
                );

                $sql =
                    "update task set token = '" .
                    $idl2 .
                    "' where task_id=" .
                    $t1;
                self::DbQuery($sql);
                $sql =
                    "update task set token = '" .
                    $idl1 .
                    "' where task_id=" .
                    $t2;
                self::DbQuery($sql);

                $sql =
                    "SELECT task_id, card_type, card_type_arg, token, player_id, status FROM task where task_id=" .
                    $t1;
                $task1 = self::getObjectFromDb($sql);

                $sql =
                    "SELECT task_id, card_type, card_type_arg, token, player_id, status FROM task where task_id=" .
                    $t2;
                $task2 = self::getObjectFromDb($sql);

                self::notifyAllPlayers("move", "", [
                    "player_id" => self::getCurrentPlayerId(),
                    "player_name" => self::getPlayerName(
                        self::getCurrentPlayerId()
                    ),
                    "task" => $task2,
                    "item_id" => $id1,
                    "location_id" => "task_" . $t2,
                ]);

                self::notifyAllPlayers("move", "", [
                    "player_id" => self::getCurrentPlayerId(),
                    "player_name" => self::getPlayerName(
                        self::getCurrentPlayerId()
                    ),
                    "task" => $task1,
                    "item_id" => $id2,
                    "location_id" => "task_" . $t1,
                ]);

                $this->gamestate->nextState("task");
                break;
        }
    }

    function actCancel()
    {
        self::checkAction("actCancel");

        self::notifyAllPlayers(
            "note",
            clienttranslate('${player_name} cancels communication'),
            [
                "player_name" => self::getPlayerName(
                    self::getCurrentPlayerId()
                ),
            ]
        );

        $this->gamestate->nextState("cancel");
    }

    function actDistress()
    {
        // self::checkAction("actDistress");
        if (
            $this->gamestate->state()["name"] == "playerTurn" &&
            $this->cards->countCardInLocation("cardsontable") == 0
        ) {
            $mission = self::getUniqueValueFromDB(
                "SELECT max(mission) FROM logbook"
            );

            $sql =
                "update logbook set distress = 1, attempt = attempt+1 where mission=" .
                $mission;
            self::DbQuery($sql);

            self::notifyAllPlayers(
                "distress",
                clienttranslate('${player_name} launches a distress signal'),
                [
                    "player_name" => self::getPlayerName(
                        self::getCurrentPlayerId()
                    ),
                ]
            );

            $this->gamestate->nextState("distress");
        }
    }

    function actStartComm()
    {
        if (
            $this->gamestate->state()["name"] == "playerTurn" &&
            $this->cards->countCardInLocation("cardsontable") == 0
        ) {
            //   self::checkAction("actStartComm");
            self::notifyAllPlayers(
                "note",
                clienttranslate('${player_name} starts communication'),
                [
                    "player_name" => self::getPlayerName(
                        self::getCurrentPlayerId()
                    ),
                ]
            );
            self::setGameStateValue("comm_id", self::getCurrentPlayerId());
            $this->gamestate->nextState("startComm");
        }
    }

    function actFinishComm($place)
    {
        self::checkAction("actFinishComm");

        $player_id = self::getActivePlayerId();
        $sql =
            "SELECT player_id, player_name FROM player where player_id = " .
            $player_id;
        $activePlayer = self::getObjectFromDB($sql);

        $sql =
            "update player set comm_token = '" .
            $place .
            "' where player_id=" .
            $player_id;
        self::DbQuery($sql);

        $card = $this->getCommunicationCard($player_id);

        $text = "";

        switch ($place) {
            case "top":
                $text = clienttranslate("its highest card of this color");
                break;
            case "middle":
                $text = clienttranslate("its only card of this color");
                break;
            case "bottom":
                $text = clienttranslate("its lowest card of this color");
                break;
        }

        self::notifyAllPlayers(
            "endComm",
            clienttranslate(
                '${player_name} tells ${value_symbol}${color_symbol} is ${comm_place}'
            ),
            [
                "player_name" => self::getPlayerName(self::getActivePlayerId()),
                "comm_place" => $text,
                "comm_status" => $place,
                "card_id" => $card["id"],
                "card" => $card,
                "player_id" => self::getActivePlayerId(),
                "value" => $card["type_arg"],
                "value_symbol" => $card["type_arg"], // The substitution will be done in JS format_string_recursive function
                "color" => $card["type"],
                "color_symbol" => $card["type"], // The substitution will be done in JS format_string_recursive function
            ]
        );

        $this->gamestate->nextState("next");
    }

    function actPickCrew($crew_id)
    {
        self::checkAction("actPickCrew");

        $player_id = self::getActivePlayerId();
        $sql =
            "SELECT player_id, player_name FROM player where player_id = " .
            $player_id;
        $activePlayer = self::getObjectFromDB($sql);

        $mission = $this->getMission();
        $distribution = array_key_exists("distribution", $mission);
        $down = array_key_exists("down", $mission);

        if (!$distribution && !$down) {
            if (self::getGameStateValue("special_id") == 0) {
                self::setGameStateValue("special_id", $crew_id);
                self::notifyAllPlayers(
                    "special",
                    clienttranslate('${player_name} chooses ${special_name}'),
                    [
                        "player_id" => $crew_id,
                        "player_name" => $activePlayer["player_name"],
                        "special_name" => $this->getPlayerName($crew_id),
                    ]
                );
            } else {
                self::setGameStateValue("special_id2", $crew_id);
                self::notifyAllPlayers(
                    "special",
                    clienttranslate(
                        '${player_name} chooses ${special_name} as second special crew'
                    ),
                    [
                        "player_id" => $crew_id,
                        "player_name" => $activePlayer["player_name"],
                        "special_name" => $this->getPlayerName($crew_id),
                        "special2" => true,
                    ]
                );
            }
        }

        if ($mission["id"] == 50) {
            if (self::getGameStateValue("special_id2") == 0) {
                $this->gamestate->nextState("pickCrew");
                return;
            }
        } elseif ($mission["id"] == 11) {
            $sql =
                "update player set comm_token = 'used' where player_id=" .
                $crew_id;
            self::DbQuery($sql);

            $card = $this->getCommunicationCard($crew_id);

            self::notifyAllPlayers("endComm", "", [
                "player_id" => $crew_id,
                "comm_status" => "used",
                "card_id" => $card["id"],
            ]);
        } elseif ($down) {
            $sql = "update task set player_id =" . $crew_id;
            self::DbQuery($sql);

            $sql =
                "SELECT task_id, card_type, card_type_arg, token, player_id, status FROM task where player_id IS NOT NULL";
            $tasks = self::getCollectionFromDb($sql);

            foreach ($tasks as $task_id => $task) {
                self::notifyAllPlayers(
                    "takeTask",
                    clienttranslate(
                        '${player_name} takes task ${value_symbol}${color_symbol}'
                    ),
                    [
                        "task" => $task,
                        "player_id" => $crew_id,
                        "player_name" => $this->getPlayerName($crew_id),
                        "value" => $task["card_type_arg"],
                        "value_symbol" => $task["card_type_arg"], // The substitution will be done in JS format_string_recursive function
                        "color" => $task["card_type"],
                        "color_symbol" => $task["card_type"], // The substitution will be done in JS format_string_recursive function
                    ]
                );
            }
            $mission["tasks"] = 0;
        } elseif ($distribution) {
            $sql =
                "SELECT task_id, card_type, card_type_arg, token, player_id, status FROM task where player_id IS NULL";
            $task = self::getObjectFromDb($sql);

            $sql =
                "update task set player_id=" .
                $crew_id .
                " where task_id = " .
                $task["task_id"];
            self::DbQuery($sql);

            $sql =
                "SELECT task_id, card_type, card_type_arg, token, player_id, status FROM task where task_id = " .
                $task["task_id"];
            $task = self::getObjectFromDB($sql);
            self::setGameStateValue("special_id", 0);

            self::notifyAllPlayers(
                "takeTask",
                clienttranslate(
                    '${player_name} takes task ${value_symbol}${color_symbol}'
                ),
                [
                    "task" => $task,
                    "player_id" => $crew_id,
                    "player_name" => $this->getPlayerName($crew_id),
                    "value" => $task["card_type_arg"],
                    "value_symbol" => $task["card_type_arg"], // The substitution will be done in JS format_string_recursive function
                    "color" => $task["card_type"],
                    "color_symbol" => $task["card_type"], // The substitution will be done in JS format_string_recursive function
                ]
            );

            $nbTask = self::getUniqueValueFromDB("SELECT count(*) FROM task");
            if ($nbTask < $mission["tasks"]) {
                $this->addOneTask();
                $this->gamestate->nextState("next");
            } else {
                $this->gamestate->nextState("trick");
            }
            return;
        }

        if ($mission["tasks"] > 0) {
            $this->gamestate->nextState("task");
        } else {
            $this->gamestate->nextState("trick");
        }
    }

    // Play a card from player hand
    function actPlayCard($card_id)
    {
        self::checkAction("actPlayCard");

        $card = $this->cards->getCard($card_id);
        $card_id = $card["id"];
        $player_id = $card["location_arg"];
        $reminder_card = null;

        switch ($this->gamestate->state()["name"]) {
            case "playerTurn":
                $current_trick_color = self::getGameStateValue("trick_color");

                if ($card["type"] == COMM) {
                    //reminder card played
                    $reminder_card = $card;
                    $card = $this->getCommunicationCard($player_id);
                    $this->cards->moveCard($card_id, "comm", $player_id);
                    $card_id = $card["id"];

                    $sql =
                        "update player set comm_token = 'used' where player_id=" .
                        $player_id;
                    self::DbQuery($sql);
                } elseif ($card["location"] == "comm") {
                    //comm card played
                    $reminder_card = $this->getReminderCard($player_id);
                    $this->cards->moveCard(
                        $reminder_card["id"],
                        "comm",
                        $player_id
                    );

                    $sql =
                        "update player set comm_token = 'used' where player_id=" .
                        $player_id;
                    self::DbQuery($sql);
                }

                $this->cards->moveCard($card_id, "cardsontable", $player_id);

                // Set the trick color if it hasn't been set yet
                if ($current_trick_color == 0) {
                    self::setGameStateValue("trick_color", $card["type"]);
                }
                $card = $this->cards->getCard($card_id);

                // And notify
                self::notifyAllPlayers(
                    "playCard",
                    clienttranslate(
                        '${player_name} plays ${value_symbol}${color_symbol}'
                    ),
                    [
                        "card_id" => $card_id,
                        "card" => $card,
                        "player_id" => $player_id,
                        "player_name" => self::getActivePlayerName(),
                        "value" => $card["type_arg"],
                        "value_symbol" => $card["type_arg"], // The substitution will be done in JS format_string_recursive function
                        "color" => $card["type"],
                        "color_symbol" => $card["type"], // The substitution will be done in JS format_string_recursive function
                    ]
                );

                if ($reminder_card != null) {
                    // And notify
                    self::notifyAllPlayers("resetComm", "", [
                        "card" => $reminder_card,
                        "player_id" => $player_id,
                        "reminder_id" => $reminder_card["id"],
                    ]);
                }
                break;

            case "comm":
                //reminder card
                $reminder = $this->getCommunicationCard($player_id);

                $this->cards->moveCard($card_id, "comm", $player_id);
                $this->cards->moveCard($reminder["id"], "hand", $player_id);

                // And notify
                self::notifyAllPlayers("commCard", "", [
                    "card_id" => $card_id,
                    "card" => $card,
                    "player_id" => $player_id,
                    "reminder_id" => $reminder["id"],
                ]);

                $mission = $this->getMission();

                if (array_key_exists("deadzone", $mission)) {
                    //dead zone
                    $sql =
                        "update player set comm_token = 'used' where player_id=" .
                        $player_id;
                    self::DbQuery($sql);

                    self::notifyAllPlayers(
                        "endComm",
                        clienttranslate(
                            '${player_name} tells ${value_symbol}${color_symbol} is ${comm_place}'
                        ),
                        [
                            "player_name" => self::getPlayerName(
                                self::getActivePlayerId()
                            ),
                            "comm_place" => "...",
                            "comm_status" => "used",
                            "card_id" => $card["id"],
                            "card" => $card,
                            "player_id" => self::getActivePlayerId(),
                            "value" => $card["type_arg"],
                            "value_symbol" => $card["type_arg"], // The substitution will be done in JS format_string_recursive function
                            "color" => $card["type"],
                            "color_symbol" => $card["type"], // The substitution will be done in JS format_string_recursive function
                        ]
                    );

                    $this->gamestate->nextState("after");
                    return;
                } else {
                    $sql =
                        "update player set comm_token = 'hidden' where player_id=" .
                        $player_id;
                    self::DbQuery($sql);
                }

                break;

            case "distress":
                $sql =
                    "update player set card_id = " .
                    $card_id .
                    " where player_id=" .
                    $player_id;
                self::DbQuery($sql);

                $this->gamestate->setPlayerNonMultiactive(
                    $this->getCurrentPlayerId(),
                    "next"
                );
                return;

                break;
        }

        // Next player
        $this->gamestate->nextState("next");
    }

    function actChooseTask($task_id)
    {
        self::checkAction("actChooseTask");

        $player_id = self::getActivePlayerId();
        $sql =
            "SELECT player_id, player_name FROM player where player_id = " .
            $player_id;
        $activePlayer = self::getObjectFromDB($sql);

        $sql =
            "SELECT task_id, player_id FROM task where task_id = " . $task_id;
        $task = self::getObjectFromDB($sql);

        if ($task["player_id"] != null) {
            throw new feException("Task already picked");
        } else {
            $sql =
                "update task set player_id=" .
                $player_id .
                " where task_id = " .
                $task_id;
            self::DbQuery($sql);

            $sql =
                "SELECT task_id, card_type, card_type_arg, token, player_id, status FROM task where task_id = " .
                $task_id;
            $task = self::getObjectFromDB($sql);

            self::notifyAllPlayers(
                "takeTask",
                clienttranslate(
                    '${player_name} takes task ${value_symbol}${color_symbol}'
                ),
                [
                    "task" => $task,
                    "player_id" => $player_id,
                    "player_name" => $activePlayer["player_name"],
                    "value" => $task["card_type_arg"],
                    "value_symbol" => $task["card_type_arg"], // The substitution will be done in JS format_string_recursive function
                    "color" => $task["card_type"],
                    "color_symbol" => $task["card_type"], // The substitution will be done in JS format_string_recursive function
                ]
            );
        }

        $this->gamestate->nextState("next");
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state arguments
    ////////////

    function argQuestion()
    {
        $result = [];
        $mission = $this->getMission();
        $result["commander"] = $this->getPlayerName(
            self::getGameStateValue("commander_id")
        );
        $result["question"] = $mission["question"];
        $result["replies"] = $mission["replies"];
        $sql =
            "SELECT task_id, card_type, card_type_arg, token, player_id, status FROM task where player_id IS NULL";
        $result["tasks"] = self::getCollectionFromDb($sql);

        $mission = $this->getMission();
        $down = array_key_exists("down", $mission);
        if ($down) {
            foreach ($result["tasks"] as $task_id => $task) {
                $result["tasks"][$task_id]["card_type"] = 7;
                $result["tasks"][$task_id]["card_type_arg"] = 0;
            }
        }

        return $result;
    }

    function argPickCrew()
    {
        $result = [];
        $sql =
            "SELECT task_id, card_type, card_type_arg, token, player_id, status FROM task where player_id IS NULL";
        $result["tasks"] = self::getCollectionFromDb($sql);
        $mission = $this->getMission();
        $down = array_key_exists("down", $mission);
        if ($down) {
            foreach ($result["tasks"] as $task_id => $task) {
                $result["tasks"][$task_id]["card_type"] = 7;
                $result["tasks"][$task_id]["card_type_arg"] = 0;
            }
        }

        $result["possible"] = [];
        $sql = "SELECT player_id id, comm_token FROM player ";
        $result["players"] = self::getCollectionFromDb($sql);
        foreach ($result["players"] as $player_id => $player) {
            if (
                self::getGameStateValue("commander_id") != $player_id ||
                $mission["id"] == 50
            ) {
                $result["possible"][$player_id] = $player_id;
            }
        }
        return $result;
    }

    function argEndMission()
    {
        $result = [];
        $result["end"] = self::getGameStateValue("mission_finished");
        $result["number"] = self::getUniqueValueFromDB(
            "SELECT max(mission) FROM logbook"
        );
        return $result;
    }

    function argDistress()
    {
        $result = [];

        $sql = "SELECT card_id id FROM card where card_type <5";
        $result["cards"] = self::getCollectionFromDb($sql);

        return $result;
    }

    function argMultiSelect()
    {
        $result = [];
        $sql =
            "SELECT task_id, card_type, card_type_arg, token, player_id, status FROM task where player_id IS NULL";
        $result["tasks"] = self::getCollectionFromDb($sql);
        $result["ids"] = [];

        for ($i = 1; $i <= 5; $i++) {
            $result["ids"]["marker_" . $i] = [];
            for ($j = 1; $j <= 5; $j++) {
                if ($i != $j) {
                    $result["ids"]["marker_" . $i]["marker_" . $j] =
                        "marker_" . $j;
                }
            }
        }

        return $result;
    }

    function argComm()
    {
        $result = [];
        $player_id = self::getActivePlayerId();

        $hand = $this->cards->getCardsInLocation("hand", $player_id);
        foreach ($hand as $card_id => $card) {
            if ($card["type"] < 5) {
                if (count($this->getPossibleStatus($card)) > 0) {
                    $result[$card_id] = $card_id;
                }
            }
        }

        return $result;
    }

    function argCommToken()
    {
        $result = [];
        $player_id = self::getActivePlayerId();
        $card = $this->getCommunicationCard($player_id);
        $ret["card"] = $card;
        $ret["possible"] = $this->getPossibleStatus($card);
        return $ret;
    }

    function argPlayerTurn()
    {
        $result = [];
        $result["cards"] = [];
        $player_id = self::getActivePlayerId();

        $comCard = $this->getCommunicationCard($player_id);
        $reminderCard = $this->getReminderCard($player_id);
        $current_trick_color = self::getGameStateValue("trick_color");
        $hand = [];
        if ($current_trick_color != 0) {
            $hand = $this->cards->getCardsOfTypeInLocation(
                $current_trick_color,
                null,
                "hand",
                $player_id
            );
            if ($comCard["type"] == $current_trick_color) {
                $hand[$comCard["id"]] = $comCard;
                $hand[$reminderCard["id"]] = $reminderCard;
            }
        }
        if (count($hand) == 0) {
            $hand = $this->cards->getCardsInLocation("hand", $player_id);
            if ($comCard["type"] != COMM) {
                $hand[$comCard["id"]] = $comCard;
            }
        }

        foreach ($hand as $card_id => $card) {
            $result["cards"][] = $card_id;
        }

        $mission = $this->getMission();
        $disruption =
            array_key_exists("disruption", $mission) &&
            $mission["disruption"] > self::getGameStateValue("trick_count");

        $sql = "SELECT player_id id, comm_token FROM player ";
        $result["players"] = self::getCollectionFromDb($sql);
        $noComm = true;
        foreach ($result["players"] as $player_id => $player) {
            $cards = $this->cards->getCardsInLocation("comm", $player_id);
            $notUsed = count($cards) == 1 && array_shift($cards)["type"] == 6;

            if (!$notUsed) {
                $noComm = false;
            }

            $result["players"][$player_id]["canCommunicate"] =
                !$disruption &&
                $current_trick_color == 0 &&
                $player["comm_token"] != "used" &&
                $notUsed;
        }

        $mission = self::getUniqueValueFromDB(
            "SELECT max(mission) FROM logbook"
        );
        $alreadyDistress = self::getUniqueValueFromDB(
            "SELECT distress FROM logbook where mission=" . $mission
        ); // NOI18N;
        $cardPlayed = $this->cards->countCardInLocation("cardsontable");
        $result["canDistress"] =
            $alreadyDistress == 0 &&
            $cardPlayed == 0 &&
            $noComm &&
            self::getGameStateValue("trick_count") == 1;

        return $result;
    }

    function argPickTask()
    {
        $result = [];
        $sql =
            "SELECT task_id, card_type, card_type_arg, token, player_id, status FROM task where player_id IS NULL";
        $result["tasks"] = self::getCollectionFromDb($sql);
        return $result;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////

    function stPreparation()
    {
        self::setGameStateValue("trick_count", 0);
        self::setGameStateValue("check_count", 0);
        $players = self::loadPlayersBasicInfos();
        $missionnb = self::getUniqueValueFromDB(
            "SELECT max(mission) FROM logbook"
        );

        // Take back all cards (from any location => null) to deck and shuffle
        $this->cards->moveAllCardsInLocation(null, "deck");
        $this->cards->shuffle("deck");

        //Deal communication cards
        $coms = $this->cards->getCardsOfType(COMM);
        foreach ($coms as $card_id => $card) {
            $player_id = array_shift($players)["player_id"];
            $this->cards->moveCard($card_id, "comm", $player_id);
        }

        $sql =
            "update player set card_id = NULL, comm_token = 'middle', player_trick_number = 0";
        self::DbQuery($sql);

        // Deal cards to each players (and signal the UI to clean-up)
        self::notifyAllPlayers(
            "note",
            clienttranslate('Start new mission ${mission}'),
            [
                "mission" => $missionnb,
            ]
        );

        // Create deck, shuffle it and give initial cards
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $nbCards = intdiv(
                self::getGameStateValue("challenge") == 2 ? 30 : 40,
                count($players)
            );

            if (
                count($players) == 3 &&
                self::getGameStateValue("challenge") == 1 &&
                $player["player_no"] == 1
            ) {
                $nbCards++;
            }
            $hand = $this->cards->pickCards($nbCards, "deck", $player_id);
            $players[$player_id]["nbCards"] = $nbCards;
            $players[$player_id]["comCard"] = $this->getCommunicationCard(
                $player_id
            );

            // Notify player about his cards
            self::notifyPlayer(
                $player_id,
                "newHand",
                clienttranslate('-- Your cards are:&nbsp;<br />${cards}'),
                [
                    "cards" => self::listCardsForNotification($hand),
                    "hand" => $hand,
                ]
            );
        }

        self::notifyAllPlayers("cleanUp", "", [
            "mission" => $missionnb,
            "mission_attempts" => self::getUniqueValueFromDB(
                "SELECT attempt FROM logbook where mission=" . $missionnb
            ), // NOI18N;
            "total_attempts" => self::getUniqueValueFromDB(
                "SELECT sum(attempt) FROM logbook"
            ),
            "distress" => self::getUniqueValueFromDB(
                "SELECT distress FROM logbook where mission=" . $missionnb
            ),
            "players" => $players,
        ]);

        //Locate commander
        $commanderId = self::getUniqueValueFromDB(
            "SELECT card_location_arg FROM card where card_type = 5 and card_type_arg = 4"
        ); // NOI18N

        self::setGameStateValue("commander_id", $commanderId);
        self::setGameStateValue("special_id", 0);
        // Notify player about commander
        self::notifyAllPlayers(
            "commander",
            clienttranslate('${player_name} is your new commander'),
            [
                "player_name" => $players[$commanderId]["player_name"],
                "player_id" => $commanderId,
            ]
        );

        //Draw tasks if necessary
        $sql = "delete from task where 1";
        self::DbQuery($sql);

        $picked = [];
        $mission = $this->missions[$missionnb];
        $tokens = explode(",", $mission["tiles"]);
        $distribution = array_key_exists("distribution", $mission);

        for (
            $i = 1;
            $i <= $mission["tasks"] && !($i == 2 && $distribution);
            $i++
        ) {
            $this->addOneTask();
        }

        $this->gamestate->changeActivePlayer(
            self::getGameStateValue("commander_id")
        );
        self::setGameStateValue(
            "last_winner",
            self::getGameStateValue("commander_id")
        );
        if (array_key_exists("question", $mission)) {
            self::notifyAllPlayers(
                "note",
                clienttranslate('Commander ${player_name} asks : ${question}'),
                [
                    "player_name" => $players[$commanderId]["player_name"],
                    "question" => $mission["question"],
                ]
            );
            $this->activeNextPlayer();
            $this->gamestate->nextState("question");
        } elseif ($mission["id"] == 11) {
            $this->gamestate->nextState("pickCrew");
        } elseif ($mission["id"] == 23 || $mission["id"] == 40) {
            $this->gamestate->nextState("multiSelect");
        } elseif ($mission["id"] == 46) {
            $card_id = self::getUniqueValueFromDB(
                "SELECT card_id FROM card where card_type = '3' and card_type_arg = 9"
            );
            $card = $this->cards->getCard($card_id);

            // And notify
            self::notifyAllPlayers(
                "note",
                clienttranslate(
                    '${player_name} has ${value_symbol}${color_symbol}'
                ),
                [
                    "card_id" => $card_id,
                    "card" => $card,
                    "player_id" => $card["location_arg"],
                    "player_name" => self::getPlayerName($card["location_arg"]),
                    "value" => $card["type_arg"],
                    "value_symbol" => $card["type_arg"], // The substitution will be done in JS format_string_recursive function
                    "color" => $card["type"],
                    "color_symbol" => $card["type"], // The substitution will be done in JS format_string_recursive function
                ]
            );

            $crew_id = $this->getPlayerAfter($card["location_arg"]);

            self::setGameStateValue("special_id", $crew_id);

            self::notifyAllPlayers(
                "special",
                clienttranslate('${player_name} must win all pink cards'),
                [
                    "player_id" => $crew_id,
                    "player_name" => $this->getPlayerName($crew_id),
                ]
            );
            $this->gamestate->nextState("trick");
        } elseif ($mission["tasks"] > 0) {
            $this->gamestate->nextState("task");
        } else {
            $this->gamestate->nextState("trick");
        }
    }

    function addOneTask()
    {
        $nbTask = self::getUniqueValueFromDB("SELECT count(*) FROM task") + 1;
        $mission = $this->getMission();
        $tokens = explode(",", $mission["tiles"]);
        $token = "";
        if (count($tokens) >= $nbTask) {
            $token = $tokens[$nbTask - 1];
        }

        do {
            $color = bga_rand(1, 4);
            if (self::getGameStateValue("challenge") == 2) {
                $color = bga_rand(1, 3);
                if ($color == 2) {
                    $color = 4;
                }
            }
            $value = bga_rand(1, 9);
        } while (
            self::getUniqueValueFromDB(
                "SELECT count(*) FROM task where card_type = '" .
                    $color .
                    "' and card_type_arg=" .
                    $value
            ) > 0
        ); // NOI18N

        $sql =
            "INSERT INTO task (task_id, card_type, card_type_arg, token) VALUES (" .
            $nbTask .
            ", '" .
            $color .
            "', " .
            $value .
            ", '" .
            $token .
            "' )";
        self::DbQuery($sql);
    }

    function stNextQuestion()
    {
        $this->activeNextPlayer();
        if (
            $this->getActivePlayerId() ==
            self::getGameStateValue("commander_id")
        ) {
            $this->gamestate->nextState("pick");
        } else {
            $this->gamestate->nextState("next");
        }
    }

    function stNewTrick()
    {
        // Reset trick color to 0 (= no color)
        self::setGameStateValue("trick_color", 0);

        self::setGameStateValue(
            "trick_count",
            self::getGameStateValue("trick_count") + 1
        );

        $mission = self::getUniqueValueFromDB(
            "SELECT max(mission) FROM logbook"
        );
        $distress = self::getUniqueValueFromDB(
            "SELECT distress FROM logbook where mission=" . $mission
        ); // NOI18N;

        if (self::getGameStateValue("trick_count") == 1 && $distress) {
            $this->gamestate->nextState("distress");
        } else {
            $this->gamestate->nextState("next");
        }
    }

    function stcheckPickTask()
    {
        if (
            self::getUniqueValueFromDB(
                "SELECT count(*) FROM task where player_id IS NULL"
            ) == 0
        ) {
            // NOI18N
            $this->gamestate->changeActivePlayer(
                self::getGameStateValue("commander_id")
            );
            $this->gamestate->nextState("turn");
        } else {
            $this->activeNextPlayer();
            $this->gamestate->nextState("task");
        }
    }

    function stBeforeComm()
    {
        $this->gamestate->changeActivePlayer(
            self::getGameStateValue("comm_id")
        );
        $this->gamestate->nextState("next");
    }

    function stAfterComm()
    {
        $this->gamestate->changeActivePlayer(
            self::getGameStateValue("last_winner")
        );
        $this->gamestate->nextState("next");
    }

    function swapOneCard()
    {
        $sql = "SELECT player_id id, player_no, card_id FROM player ";
        $result["players"] = self::getCollectionFromDb($sql);

        foreach ($result["players"] as $player_id => $player) {
            $cards = $this->cards->getCardsInLocation("hand", $player_id);
            $index = bga_rand(0, count($cards) - 1);
            $card = array_values($cards)[$index];

            while ($card["type"] == COMM) {
                $index = bga_rand(0, count($cards) - 1);
                $card = array_values($cards)[$index];
            }

            $result["players"][$player_id]["card"] = $card;
        }

        foreach ($result["players"] as $player_id => $player) {
            $rel = $this->getPlayerRelativePositions($player_id);
            $next = $this->getPlayerAfter($player_id);

            $cardGiven = $result["players"][$player_id]["card"];
            $cardReceive =
                $result["players"][$this->getPlayerBefore($player_id)]["card"];

            $card_id = $player["card_id"];
            $this->cards->moveCard($cardGiven["id"], "hand", $next);

            self::notifyPlayer(
                $player_id,
                "give",
                clienttranslate('You lost ${value_symbol}${color_symbol}'),
                [
                    "card_id" => $cardGiven["id"],
                    "card" => $cardGiven,
                    "value" => $cardGiven["type_arg"],
                    "value_symbol" => $cardGiven["type_arg"], // The substitution will be done in JS format_string_recursive function
                    "color" => $cardGiven["type"],
                    "color_symbol" => $cardGiven["type"], // The substitution will be done in JS format_string_recursive function
                ]
            );

            self::notifyPlayer(
                $next,
                "receive",
                clienttranslate('You picked ${value_symbol}${color_symbol}'),
                [
                    "card_id" => $cardGiven["id"],
                    "card" => $cardGiven,
                    "value" => $cardGiven["type_arg"],
                    "value_symbol" => $cardGiven["type_arg"], // The substitution will be done in JS format_string_recursive function
                    "color" => $cardGiven["type"],
                    "color_symbol" => $cardGiven["type"], // The substitution will be done in JS format_string_recursive function
                ]
            );
        }
    }

    function stDistressExchange()
    {
        $sql = "SELECT player_id id, player_no, card_id FROM player ";
        $result["players"] = self::getCollectionFromDb($sql);

        foreach ($result["players"] as $player_id => $player) {
            $rel = $this->getPlayerRelativePositions($player_id);
            $next = $this->getPlayerAfter($player_id);
            if (self::getGameStateValue("distress_turn") == 0) {
                $next = $this->getPlayerBefore($player_id);
            }

            $card_id = $player["card_id"];
            $this->cards->moveCard($card_id, "hand", $next);

            self::notifyPlayer($player_id, "give", "", [
                "card_id" => $card_id,
            ]);

            $card = $this->cards->getCard($card_id);
            self::notifyPlayer(
                $next,
                "receive",
                clienttranslate('You receive ${value_symbol}${color_symbol}'),
                [
                    "card_id" => $card["id"],
                    "card" => $card,
                    "value" => $card["type_arg"],
                    "value_symbol" => $card["type_arg"], // The substitution will be done in JS format_string_recursive function
                    "color" => $card["type"],
                    "color_symbol" => $card["type"], // The substitution will be done in JS format_string_recursive function
                ]
            );
        }

        $this->gamestate->changeActivePlayer(
            self::getGameStateValue("commander_id")
        );
        $this->gamestate->nextState("next");
    }

    function stChangeMission()
    {
        $mission = self::getUniqueValueFromDB(
            "SELECT max(mission) FROM logbook"
        );
        if (self::getGameStateValue("mission_finished") > 0) {
            $sql =
                "INSERT INTO logbook (mission) VALUES (" . ($mission + 1) . ")";
            self::DbQuery($sql);

            if ($mission == 50) {
                $this->gamestate->nextState("save");
                return;
            }
        } else {
            $sql =
                "update logbook set attempt = attempt + 1 where mission = " .
                $mission;
            self::DbQuery($sql);
        }

        self::setGameStateValue("mission_finished", 0);
        if (self::getGameStateValue("end_game") == 1) {
            $this->gamestate->nextState("end");
        } else {
            $this->gamestate->nextState("next");
        }
    }

    function stNextPlayer()
    {
        $players_number = self::getPlayersNumber();
        if (
            $this->cards->countCardInLocation("cardsontable") == $players_number
        ) {
            // This is the end of the trick

            $last_trick = $this->cards->countCardInLocation("hand") == 0;

            $cards_on_table = $this->cards->getCardsInLocation("cardsontable");
            $best_value = 0;
            $best_value_player_id = null;
            $winningColor = self::getGameStateValue("trick_color"); // The color needed to win the trick color unless a trump (5) was played

            // Who wins ?
            foreach ($cards_on_table as $card) {
                // Note: type = card color
                // Note: type_arg = value of the card
                // Note: location_arg = player who played this card on table
                if ($card["type"] == 5 && $winningColor != 5) {
                    // A trump has been played: this is the first one
                    $winningColor = 5; // Now trumps are needed to win the trick
                    $best_value_player_id = $card["location_arg"];
                    $best_value = $card["type_arg"];
                } elseif ($card["type"] == $winningColor) {
                    // This card is the right color to win the trick
                    if ($card["type_arg"] > $best_value) {
                        $best_value_player_id = $card["location_arg"];
                        $best_value = $card["type_arg"];
                    }
                }
            }

            // Transfer all remaining cards to the winner of the trick
            self::DbQuery(
                sprintf(
                    "UPDATE player SET player_trick_number = player_trick_number+1 WHERE player_id='%s'",
                    $best_value_player_id
                )
            );
            $this->cards->moveAllCardsInLocation(
                "cardsontable",
                "trick" . self::getGameStateValue("trick_count"),
                null,
                $best_value_player_id
            );

            // Notify
            // Note: we use 2 notifications here in order we can pause the display during the first notification
            //  before we move all cards to the winner (during the second)
            self::notifyAllPlayers(
                "trickWin",
                clienttranslate(
                    '${player_name} wins the trick:&nbsp;<br />${cards}'
                ),
                [
                    "player_id" => $best_value_player_id,
                    "player_name" => self::getPlayerName($best_value_player_id),
                    "cards" => self::listCardsForNotification($cards_on_table),
                ]
            );

            self::notifyAllPlayers("giveAllCardsToPlayer", "", [
                "player_id" => $best_value_player_id,
                "cards" => $cards_on_table,
            ]);

            self::setGameStateValue("last_winner", $best_value_player_id);

            $className = "THCCheck";
            $mission = self::getUniqueValueFromDB(
                "SELECT max(mission) FROM logbook"
            );
            if (in_array($mission, $this->specificCheck)) {
                $className = $className . $mission;
            }
            $check = new $className($this);
            $check->check();

            if (self::getGameStateValue("mission_finished") != 0) {
                $this->gamestate->setAllPlayersMultiactive();
                $this->gamestate->nextState("endMission");
            } else {
                if (
                    $this->getMission()["id"] == 12 &&
                    self::getGameStateValue("trick_count") == 1
                ) {
                    $this->swapOneCard();
                }
                $this->gamestate->changeActivePlayer($best_value_player_id);
                $this->gamestate->nextState("nextTrick");
            }
        } else {
            // Standard case (not the end of the trick)
            // => just active the next player

            $player_id = self::activeNextPlayer();
            self::giveExtraTime($player_id);

            $this->gamestate->nextState("nextPlayer");
        }
    }

    function load()
    {
        if ($this->getGameStateValue("mission_start") == 999) {
            $json = $this->retrieveLegacyTeamData();
            if (is_string($json)) {
                $json = substr($json, 1, strlen($json) - 2);
                $logs = json_decode($json, true);
                foreach ($logs as $log_id => $log) {
                    $sql =
                        "INSERT INTO logbook (mission, attempt, success, distress) VALUES (" .
                        $log["mission"] .
                        ", " .
                        $log["attempt"] .
                        ", " .
                        $log["success"] .
                        ", " .
                        $log["distress"] .
                        ")";
                    self::DbQuery($sql);
                }
            } else {
                //initiate logbook
                $sql = "INSERT INTO logbook (mission) VALUES (1)";
                self::DbQuery($sql);
            }
        }
    }

    function stSave()
    {
        if ($this->getGameStateValue("mission_start") == 999) {
            $sql = "SELECT mission, attempt, success, distress FROM logbook ";
            $logs = self::getCollectionFromDb($sql);
            $json = json_encode($logs);
            $this->storeLegacyTeamData($json);
        }
        $this->gamestate->nextState("next");
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
                    self::setGameStateValue("mission_finished", -1);
                    self::setGameStateValue("end_game", 1);
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

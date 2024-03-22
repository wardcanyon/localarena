<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Chakra implementation : © Nicolas Gocel <nicolas.gocel@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * chakra.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */


require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );


class emppty extends Table
{
    function __construct( )
    {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        self::initGameStateLabels( array(
            "channel" => 10,
            "step" => 11,
            "choice" => 12,
            "alreadyMoved" => 13,
            "blackEnergyId" => 14,
            "finished" => 15,
        ) );
    }

    protected function getGameName( )
    {
        // Used for translations and stuff. Please do not modify.
        return "emppty";
    }

    /*
     setupNewGame:

     This method is called only once, when a new game is launched.
     In this method, you must setup the game according to the game rules, so that
     the game is ready to be played.
     */
    protected function setupNewGame( $players, $options = array() )
    {
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach( $players as $player_id => $player )
        {
            $color = array_shift( $default_colors );
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";
        }
        $sql .= implode(',', $values);
        self::DbQuery( $sql );
        self::reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        self::reloadPlayersBasicInfos();

        /************ Start the game initialization *****/
        $plenitudes = [1,1,2,2,3,3,4,4];
        shuffle($plenitudes);

        $sql = "INSERT INTO plenitude (color, value) VALUES ";
        $values = array();
        for($i=1;$i<=7;$i++)
        {
            $color = $this->colors[$i];
            $value = array_shift( $plenitudes);
            $values[] = "('".$color."', ".$value.")";
        }
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );


        $sql = "INSERT INTO inspiration (player_id, id, location, location_arg) VALUES ";
        $values = array();
        foreach( $players as $player_id => $player )
        {
            for($i=1;$i<=5;$i++)
            {
                $values[] = "(".$player_id.", ".$i.", 'board', ".$i.")";
            }
        }
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );

        $sql = "INSERT INTO energy (color, location) VALUES ";
        $values = array();
        for($i=1;$i<=8;$i++)
        {
            $color = $this->colors[$i];
            for($j=0;$j<3*count($players);$j++)
            {
                //DEBUG
                //  $color = 'green';
                $values[] = "('".$color."', 'bag')";
            }
        }
        shuffle($values);
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );

        $this->refillMaya(true);

        foreach( $players as $player_id => $player )
        {
            $color = $this->colors[bga_rand(1, 7)];
            $this->revealPlenitude($player_id, $color);
        }

        // Init global values with their initial values
        self::setGameStateInitialValue( 'channel', 0 );
        self::setGameStateInitialValue( 'step', 0 );
        self::setGameStateInitialValue( 'choice', 0 );
        self::setGameStateInitialValue( 'alreadyMoved', 0 );
        self::setGameStateInitialValue( 'blackEnergyId', 0 );
        self::setGameStateInitialValue( 'finished', 0 );

        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        self::initStat( 'player', 'turns_number', 0 );
        self::initStat( 'player', 'chakra_harmonized', 0 );
        self::initStat( 'player', 'chakra_aligned', 0 );
        self::initStat( 'player', 'chakra_points', 0 );
        self::initStat( 'player', 'black_points', 0 );
        self::initStat( 'player', 'harmo_points', 0 );
        self::initStat( 'player', 'meditation', 0 );

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        /************ End of the game initialization *****/
    }

    function refillMaya($ignoreNotify = false)
    {
        for($row = 1; $row <= 3;$row++)
        {
            for($col= 1; $col <= 3;$col++)
            {
                if($this->getEnergy('maya', $row, $col) == null)
                {
                    $energy = self::getObjectFromDB( "SELECT id, color, location, row, col FROM energy where location ='bag' limit 1");
                    if($energy != null)
                    {
                        self::DbQuery( "update energy set location = 'maya', row=".$row.", col=".$col." where id = ".$energy['id']);

                        if(!$ignoreNotify)
                        {
                            $this->notifyEnergy($energy['id']);
                        }
                    }
                }
            }
        }
    }

    function revealPlenitude($player_id, $color)
    {
        $value = self::getUniqueValueFromDB( "SELECT value FROM plenitude where color='".$color."'");
        self::DbQuery( "update player set ".$color." = ".$value." where player_id = ".$player_id);
    }

    function getEnergy($location, $row, $col)
    {
        return self::getObjectFromDB( "SELECT id, color, location, row, col FROM energy where location ='maya' and row =".$row." and col=".$col); // NOI18N
    }
    function getEnergyById($id)
    {
        return self::getObjectFromDB( "SELECT id, color, location, row, col FROM energy where id=".$id);
    }
    function getEnergies($location, $row = null, $col = null)
    {
        $sql = "SELECT id, color, location, row, col FROM energy where location ='".$location."'";
        if($row != null)
        {
            $sql = $sql." and row =".$row;
        }
        if($col != null)
        {
            $sql = $sql." and col =".$col;
        }
        return self::getObjectListFromDB( $sql);
    }

    function getEnergiesTable()
    {
        $current_player = self::getActivePlayerId();
        $energies = $this->getEnergies($current_player);

        $table = array();
        for($i=1;$i<=9;$i++)
        {
            $table[$i] = array();
        }
        foreach($energies as $id => $energy)
        {
            $table[$energy['row']][$energy['id']] = $energy;
        }
        return $table;
    }

    function isHarmonized($player_id, $row)
    {
        if($row <= 1 || $row >= 9) return false;
        $color = $this->colors[$row-1];
        return self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location='".$player_id."' and row = ".$row." and color = '".$color."'") == 3; // NOI18N
    }

    function isHarmonizedTable($table, $row)
    {
        if($row <= 1 || $row >= 9) return false;
        if(count($table[$row]) != 3) return false;
        $color = $this->colors[$row-1];

        foreach($table[$row] as $id => $energy)
        {
            if($energy['color'] != $color)
            {
                return false;
            }
        }
        return true;
    }

    function getPlayerRelativePositions()
    {
        $result = array();

        $players = self::loadPlayersBasicInfos();
        $nextPlayer = self::createNextPlayerTable(array_keys($players));

        $current_player = self::getCurrentPlayerId();

        if(!isset($nextPlayer[$current_player])) {
            // Spectator mode: take any player for south
            $player_id = $nextPlayer[0];
        }
        else {
            // Normal mode: current player is on south
            $player_id = $current_player;
        }
        $result[$player_id] = 0;

        for($i=1; $i<count($players); $i++) {
            $player_id = $nextPlayer[$player_id];
            $result[$player_id] = $i;
        }
        return $result;
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
        $result = array();


        $current_player_id = self::getCurrentPlayerId();    // !! We must only return informations visible by this player !!

        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score, player_no position, if(purple>0,1,0) purple, if(darkblue>0,1,0) darkblue, if(blue>0,1,0) blue, if(green>0,1,0) green, if(yellow>0,1,0) yellow, if(orange>0,1,0) orange, if(red>0,1,0) red FROM player ";
        $result['players'] = self::getCollectionFromDb( $sql );

        foreach($result['players'] as $player_id => $player) {

            $sql = "SELECT player_id, id, location, location_arg FROM inspiration where player_id=".$player_id;
            $result['players'][$player_id]['inspirations'] = self::getObjectListFromDB( $sql );
        }

        $sql = "SELECT purple, darkblue, blue, green, yellow, orange, red FROM player where player_id=".self::getCurrentPlayerId();
        $result['plenitudes'] = self::getObjectFromDb( $sql );

        $sql = "SELECT id, color, location, row, col FROM energy where location <> 'bag'";
        $result['energies'] = self::getObjectListFromDB( $sql );



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
        $max = 0;
        $sql = "SELECT player_id id, player_color FROM player ";
        $players = self::getCollectionFromDb( $sql );
        foreach( $players as $player_id => $player )
        {
            $nb = 0;
            for($row=2;$row<=8;$row++)
            {
                $nb += $this->isHarmonized($player_id, $row)?1:0;
            }
            $max = max($max, $nb);
        }

        return $max * 20;
    }


    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////

    /*
     In this space, you can put any utility methods useful for your game logic
     */



    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    ////////////

    function actColor($color)
    {
        self::checkAction( 'actColor' );
        $player_id = self::getActivePlayerId();
        $sql = "SELECT player_id id, player_name, player_color FROM player where player_id=".$player_id;
        $player = self::getObjectFromDb( $sql );

        if($this->gamestate->state()['name'] == "take")
        {

            //move back inspirations
            $maxBoard = self::getUniqueValueFromDB( "SELECT max(location_arg) FROM inspiration where player_id =".$player_id." and location = 'board'");// NOI18N

            var_dump( $maxBoard);

            $inspirations = self::getObjectListFromDB( "SELECT id FROM inspiration where player_id =".$player_id." and location = 'channel'");// NOI18N

            foreach($inspirations as $c => $insp)
            {
                $maxBoard++;
                self::DbQuery( "update inspiration set location_arg=".$maxBoard.", location='board' where id = ".$insp['id']." and player_id =".$player_id);
                $this->notifyInspiration($insp['id']);
            }

            $value = self::getUniqueValueFromDB( "SELECT value FROM plenitude where color='".$color."'");
            self::DbQuery( "update player set ".$color." = ".$value." where player_id = ".$player_id);

            $this->incStat(1, 'meditation', self::getActivePlayerId());

            self::notifyAllPlayers( "newmeditation", '', array(
                'player_id' => $player_id,
                'color' =>  $color,
                'value' => 0
            ) );

            self::notifyPlayer( $player_id, "newmeditation", '', array(
                'player_id' => $player_id,
                'color' =>  $color,
                'value' => $value
            ));

            self::notifyAllPlayers( "notee", clienttranslate('${player_name} meditates on ${meditation}'), array(
                'player_name' => $player['player_name'],
                'meditation' => $color
            ) );
        }
        else
        {
            //remove black energy
            $energyId = self::getUniqueValueFromDB( "SELECT id FROM energy where location='".$player_id."' and row = 9 order by col desc limit 1");
            self::DbQuery( "delete from energy where id = ".$energyId);

            self::notifyAllPlayers( "destruct", '', array(
                'id' =>  'energy_'.$energyId
            ) );


            $row = 1;
            $emptyCol = array();
            for($i=1;$i<=3;$i++)
            {
                if(self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location='".$player_id."' and row = ".$row." and col=".$i)==0)// NOI18N
                {
                    $emptyCol[] = $i;
                }
            }

            $energyId = self::getUniqueValueFromDB( "SELECT id FROM energy where location='bag' and color='".$color."' limit 1");
            if($energyId == null || $energyId == '')
            {
                throw new feException( "Not a valid move");
            }

            if($color != 'black' && self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location =".$player_id." and color='".$color."'" )>=3)// NOI18N
            {
                throw new feException( "Not a valid move");
            }

            self::DbQuery( "update energy set location = '".$player_id."', row= ".$row.", col= ".array_shift($emptyCol)." where id = ".$energyId);

            $sql = "SELECT id, color, location, row, col FROM energy where id=".$energyId;
            $energy = self::getObjectFromDB( $sql );
            self::notifyAllPlayers( "newenergy", '', array(
                'energy' =>  $energy
            ) );

            self::notifyAllPlayers( "notee", clienttranslate('${player_name} chooses a new energy ${colorcanal}'), array(
                'player_name' => $player['player_name'],
                'colorcanal' => $color
            ) );

        }

        $this->gamestate->nextState( 'finish' );
    }

    function actMove( $energyId, $row)
    {
        self::checkAction( 'actMove' );

        $player_id = self::getActivePlayerId();
        $sql = "SELECT player_id id, player_name, player_color, player_score FROM player where player_id=".$player_id;
        $player = self::getObjectFromDb( $sql );
        $args = $this->argChannel();
        $possibles = $args['possibles'];

        if(array_key_exists($energyId, $possibles) && in_array($row, $possibles[$energyId]))
        {
            $step = self::getGameStateValue( 'step');
            $channel = self::getGameStateValue( 'channel');

            if($channel == 8)
            {
                self::DbQuery( "delete from energy where id = ".$energyId);
                self::setGameStateValue( 'blackEnergyId', $energyId);

                self::notifyAllPlayers( "destruct", '', array(
                    'id' =>  'energy_'.$energyId
                ) );

                $this->gamestate->nextState( 'pickColor' );
            }
            else
            {

                $emptyCol = array();
                $max = 3;
                if($row == 9)
                {
                    $max = 8;
                }
                for($i=1;$i<=$max;$i++)
                {
                    if(self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location='".$player_id."' and row = ".$row." and col=".$i)==0)// NOI18N
                    {
                        $emptyCol[] = $i;
                    }
                }
                self::DbQuery( "update energy set location = '".$player_id."', row=".$row.", col=".array_shift($emptyCol)." where id = ".$energyId);
                $this->notifyEnergy($energyId);


                if($row>=2 && $row <=8 && self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location = '".$player_id."' and row=".$row." and color='".$this->colors[$row-1]."'")==3)
                {
                    $maxBoard = self::getUniqueValueFromDB( "SELECT max(location_arg) FROM inspiration where player_id =".$player_id." and location = 'board'");// NOI18N
                    if($maxBoard == null)
                    {
                        $maxBoard = 0;
                    }
                    $inspirationId = self::getUniqueValueFromDB( "SELECT id FROM inspiration where player_id =".$player_id." and location = 'chakra' and location_arg = ".($row-1));// NOI18N
                    if($inspirationId != null && $inspirationId != '' && $inspirationId != 'NULL')
                    {
                        self::DbQuery( "update inspiration set location_arg=".($maxBoard+1).", location='board' where id = ".$inspirationId." and player_id =".$player_id);
                        $this->notifyInspiration($inspirationId);
                    }

                    self::DbQuery( "update player set player_score = player_score + 1 where player_id =".$player_id);


                    self::notifyAllPlayers( "harmonize", clienttranslate('${player_name} harmonizes its ${color} Chakra'), array(
                        'player_name' => $player['player_name'],
                        'color' => $this->colorstr[$this->colors[$row-1]],
                        'player_id' => $player_id,
                        'score' => $player['player_score']+1
                    ) );
                }

                if(count($this->channels[$channel][0])>$step+1)
                {
                    self::setGameStateValue( 'alreadyMoved', self::getGameStateValue( 'alreadyMoved') * 100 + $energyId);
                    self::setGameStateValue( 'step', $step+1);
                    self::setGameStateValue( 'choice', array_search($row, $possibles[$energyId]));
                    $this->gamestate->nextState( 'channel' );
                }
                else
                {
                    $this->gamestate->nextState( 'next' );
                }
            }
        }
        else
        {
            throw new feException( "Not a valid move");
        }
    }

    function actCancel()
    {
        self::checkAction( 'actCancel' );
        $player_id = self::getActivePlayerId();
        $sql = "SELECT player_id id, player_name, player_color FROM player where player_id=".$player_id;
        $player = self::getObjectFromDb( $sql );

        if(self::getGameStateValue( 'step') == 0)
        {
            $channel_id = self::getGameStateValue( 'channel');

            self::notifyAllPlayers( "notee", clienttranslate('${player_name} cancels'), array(
                'player_name' => $player['player_name']
            ) );

            $inspirationId = self::getUniqueValueFromDB( "SELECT id FROM inspiration where player_id =".$player_id." and location = 'channel' and location_arg = ".$channel_id);// NOI18N

            $maxBoard = self::getUniqueValueFromDB( "SELECT max(location_arg) FROM inspiration where player_id =".$player_id." and location = 'board'");// NOI18N
            if($maxBoard == null)
            {
                $maxBoard = 0;
            }
            if($inspirationId != null && $inspirationId != '' && $inspirationId != 'NULL')
            {
                self::DbQuery( "update inspiration set location_arg=".($maxBoard+1).", location='board' where id = ".$inspirationId." and player_id =".$player_id);
                $this->notifyInspiration($inspirationId);
            }

            $this->gamestate->nextState( 'take' );
        }
        else
        {
            throw new feException( "Not a valid move");
        }
    }

    function actChannel( $id )
    {
        self::checkAction( 'actChannel' );
        $player_id = self::getActivePlayerId();
        $sql = "SELECT player_id id, player_name, player_color FROM player where player_id=".$player_id;
        $player = self::getObjectFromDb( $sql );
        $args = $this->argTake();

        if($args['channel'][$id]==0)
        {
            throw new feException( "Not a valid move");
        }

        self::notifyAllPlayers( "notee", clienttranslate('${player_name} channels energy'), array(
            'player_name' => $player['player_name']
        ) );

        $inspirationId = self::getUniqueValueFromDB( "SELECT id FROM inspiration where player_id =".$player_id." and location = 'board' order by location_arg desc limit 1");// NOI18N
        self::DbQuery( "update inspiration set location_arg=".$id.", location='channel' where id = ".$inspirationId." and player_id =".$player_id);
        $this->notifyInspiration($inspirationId);

        self::setGameStateValue( 'channel', $id );
        self::setGameStateValue( 'step', 0 );
        self::setGameStateValue( 'choice', 0 );
        self::setGameStateValue( 'alreadyMoved', 0 );

        if($id == 8)
        {
            $this->gamestate->nextState( 'pickColor' );
        }
        else
        {
            $this->gamestate->nextState( 'channel' );
        }

    }

    function actTake( $energyIds, $row )
    {
        self::checkAction( 'actTake' );

        $player_id = self::getActivePlayerId();
        $sql = "SELECT player_id id, player_name, player_color, player_score FROM player where player_id=".$player_id;
        $player = self::getObjectFromDb( $sql );

        $energies = array();
        foreach($energyIds as $c => $id)
        {
            $energies[] = $this->getEnergyById($id);
        }

        $emptyCol = array();
        for($i=1;$i<=3;$i++)
        {
            if(self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location='".$player_id."' and row = ".$row." and col=".$i)==0)// NOI18N
            {
                $emptyCol[] = $i;
            }
        }

        //Check if move is correct
        if(count($energies)==0 || count($energies)>count($emptyCol))
        {
            throw new feException( "Not a valid number of energies");
        }
        else
        {
            $column = $energies[0]['col'];
            $colors = array();
            foreach($energies as $id => $energy)
            {
                if(in_array($energy['color'], $colors))
                {
                    throw new feException( "two matching colors is forbidden");
                }
                else
                {
                    $colors[] = $energy['color'];
                    if($energy['col'] != $column)
                    {
                        throw new feException( "all energy must come from same maya flow");
                    }
                }
            }


            if(self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location ='maya' and color='black' and col=".$column)>0 && !in_array('black', $colors))
            {
                throw new feException( "You must take at least one black");
            }

            if(self::getUniqueValueFromDB( "SELECT count(*) FROM inspiration where player_id =".$player_id." and location = 'chakra' and location_arg=".($row-1))>0)// NOI18N
            {
                throw new feException( "Blocked by inspiration");
            }

            if($row>1 && self::getUniqueValueFromDB( "SELECT count(*) FROM inspiration where player_id =".$player_id." and location = 'board'")==0)// NOI18N
            {
                throw new feException( "No more inspiration");
            }

            foreach($energies as $id => $energy)
            {
                if($energy['color'] != 'black' && self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location ='".$player_id."' and color = '".$energy['color']."'")>=3)// NOI18N
                {
                    throw new feException( "too many energy of this color");
                }
            }

            $colors = "";

            //Move energies
            foreach($energies as $id => $energy)
            {
                self::DbQuery( "update energy set location = '".$player_id."', row=".$row.", col=".array_shift($emptyCol)." where id = ".$energy['id']);
                $this->notifyEnergy($energy['id']);
                $this->refillMaya();
                $colors = $colors." ".$energy['color'];
            }

            self::notifyAllPlayers( "notee", clienttranslate('${player_name} takes ${energies}'), array(
                'player_name' => $player['player_name'],
                'energies' => $colors
            ) );


            if($row>1)
            {
                $inspirationId = self::getUniqueValueFromDB( "SELECT id FROM inspiration where player_id =".$player_id." and location = 'board' order by location_arg desc limit 1");// NOI18N
                self::DbQuery( "update inspiration set location_arg=".($row-1).", location='chakra' where id = ".$inspirationId." and player_id =".$player_id);
                $this->notifyInspiration($inspirationId);
            }


            if($row>=2 && $row <=8 && self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location = '".$player_id."' and row=".$row." and color='".$this->colors[$row-1]."'")==3)
            {
                $maxBoard = self::getUniqueValueFromDB( "SELECT max(location_arg) FROM inspiration where player_id =".$player_id." and location = 'board'");// NOI18N
                if($maxBoard == null)
                {
                    $maxBoard = 0;
                }

                $inspirationId = self::getUniqueValueFromDB( "SELECT id FROM inspiration where player_id =".$player_id." and location = 'chakra' and location_arg = ".($row-1));// NOI18N
                if($inspirationId != null && $inspirationId != '' && $inspirationId != 'NULL')
                {
                    self::DbQuery( "update inspiration set location_arg=".($maxBoard+1).", location='board' where id = ".$inspirationId." and player_id =".$player_id);
                    $this->notifyInspiration($inspirationId);
                }

                self::DbQuery( "update player set player_score = player_score + 1 where player_id =".$player_id);


                self::notifyAllPlayers( "harmonize", clienttranslate('${player_name} harmonizes its ${color} Chakra'), array(
                    'player_name' => $player['player_name'],
                    'color' => $this->colorstr[$this->colors[$row-1]],
                    'player_id' => $player_id,
                    'score' => $player['player_score']+1
                ) );
            }

            $this->gamestate->nextState( 'next' );

        }

    }

    function notifyEnergy($energyId)
    {
        $sql = "SELECT id, color, location, row, col FROM energy where id=".$energyId;
        $energy = self::getObjectFromDB( $sql );

        self::notifyAllPlayers( "energy", '', array(
            'energy' =>  $energy
        ) );
    }

    function notifyInspiration($inspirationId)
    {
        $player_id = self::getActivePlayerId();
        $sql = "SELECT id, player_id, location, location_arg FROM inspiration where id=".$inspirationId." and player_id=".$player_id;
        $inspiration = self::getObjectFromDB( $sql );

        self::notifyAllPlayers( "inspiration", '', array(
            'inspiration' =>  $inspiration
        ) );
    }


    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state arguments
    ////////////

    /*
     Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
     These methods function is to return some additional information that is specific to the current
     game state.
     */

    function argTake()
    {
        $available = array();
        $player_id = self::getActivePlayerId();
        $return = array();
        $frees = array();
        $left = self::getUniqueValueFromDB( "SELECT count(*) FROM inspiration where player_id=".$player_id." and location='board'");// NOI18N
        $frees[1] = 3 - self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location='".$player_id."' and row = 1");// NOI18N
        for($row = 2;$row<=8;$row++)
        {
            if($left == 0 || self::getUniqueValueFromDB( "SELECT count(*) FROM inspiration where player_id=".$player_id." and location='chakra' and location_arg = ".($row-1))>0)// NOI18N
            {
                $frees[$row] = 0;
            }
            else
            {
                $frees[$row] = 3 - self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location='".$player_id."' and row = ".$row);// NOI18N
            }
        }
        $return['frees'] = $frees;



        $return['prevent'] = array();
        foreach($this->colors as $cid => $color)
        {
            if($color != 'black')
            {
                if(self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location =".$player_id." and color='".$color."'" )>=3)// NOI18N
                {
                    $return['prevent'][$color] = $color;
                }
            }
        }

        $return['channel'] = array();

        for($i=1;$i<=8;$i++)
        {
            $return['channel'][$i] = 0;

            if($left > 0 && self::getUniqueValueFromDB( "SELECT count(*) FROM inspiration where player_id ='".$player_id."' and location = 'channel' and location_arg=".$i)==0 && $this->isChannelPossible($i))// NOI18N
            {
                $return['channel'][$i] = 1;
            }
        }

        $return['inspirationsLeft'] = $left;
        $return['inspirationsNotBlocked'] = 5 - self::getUniqueValueFromDB( "SELECT count(*) FROM inspiration where player_id=".$player_id." and location='chakra'");// NOI18N
        return $return;
    }

    function isChannelPossible($channel)
    {
        $player_id = self::getActivePlayerId();
        $possibleMoves = $this->channels[$channel];

        if($channel == 8)
        {
            if(self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location='".$player_id."' and row = 9 and color = 'black'")>0// NOI18N
                && self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location='".$player_id."' and row = 1")<3)
            {
                $colors = self::getCollectionFromDB( "SELECT distinct(color) FROM energy where location = 'bag'");// NOI18N
                foreach($colors as $color => $colorval)
                {
                    if($color == 'black' || self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location =".$player_id." and color='".$color."'" )<3)// NOI18N
                    {
                        return true;
                    }
                }
            }
            return false;

        }

        //DEBUG
        // if($channel == 2)

        foreach($possibleMoves as $moveIndex => $moves)
        {
            $table = $this->getEnergiesTable();
            $alreadyMoved = array();

            $energies = $this->getEnergies($player_id);

            $prePossibles = array();
            $prePossibles[0] = $this->tableCopy($table);

            for($step=0;$step<count($moves);$step++)
            {
                $diff = $moves[$step];

                $nextPossibles = array();

                foreach($prePossibles as $alreadyMoved => $tablepre)
                {
                    foreach($energies as $id => $energy)
                    {
                        $nextState = $this->getNextTableState($tablepre, $channel, $alreadyMoved, $energy, $diff);
                        if($nextState != null)
                        {
                            $am = $alreadyMoved * 100 + $energy['id'];
                            $nextPossibles[$am] = $nextState;
                        }
                    }
                }

                $prePossibles = $nextPossibles;
            }

            if(count($nextPossibles)>0)
            {
                return true;
            }
        }
        return false;
    }

    function isNextStepPossible($channel, $stepCurrent, $choice, $alreadyMoved, $table)
    {
        $player_id = self::getActivePlayerId();
        $moves = $this->channels[$channel][$choice];

        if(count($moves) <= $stepCurrent)
        {
            return true;
        }

        $energies = $this->getEnergies($player_id);

        $prePossibles = array();
        $prePossibles[$alreadyMoved] = $this->tableCopy($table);

        for($step=$stepCurrent;$step<count($moves);$step++)
        {
            $diff = $moves[$step];

            $nextPossibles = array();

            foreach($prePossibles as $alreadyMoved => $tablepre)
            {
                foreach($energies as $id => $energy)
                {
                    $nextState = $this->getNextTableState($tablepre, $channel, $alreadyMoved, $energy, $diff);
                    if($nextState != null)
                    {
                        $am = $alreadyMoved * 100 + $energy['id'];
                        $nextPossibles[$am] = $nextState;
                    }
                }
            }

            $prePossibles = $nextPossibles;
        }

        if(count($nextPossibles)>0)
        {
            return true;
        }

        return false;
    }

    function tableCopy($table)
    {
        $clone = array();

        for($i=1;$i<=9;$i++)
        {
            $clone[$i] = array();
            foreach($table[$i] as $ind => $val)
            {
                $clone[$i][$ind] = $val;
            }
        }
        return $table;
    }

    function getNextTableState($table, $channel, $am, $energy, $diff)
    {
        $player_id = self::getActivePlayerId();
        $copy = null;

        $alreadyMoved = array();
        while($am>0)
        {
            $alreadyMoved[] = $am%100;
            $am = floor($am/100);
        }

        $harmonized = array();
        for($i=2;$i<=8;$i++)
        {
            $harmonized[$i] = $this->isHarmonizedTable($table, $i);
        }
        $harmonized[1] = false;
        $harmonized[9] = false;

        if(!$harmonized[$energy['row']] && !in_array($energy['id'],$alreadyMoved) && $energy['row'] != 9)
        {
            $possible = true;
            $harmo = 0;
            $rowTested = 0;

            //check if move is possible
            for($d=1;$d<=abs($diff) && $possible;$d++)
            {
                $rowTested = $energy['row'] + ($d+$harmo) * ($diff>0?1:-1);

                if($rowTested>=10 || $rowTested<=0)
                {
                    $possible = false;
                }
                else if($harmonized[$rowTested])
                {
                    $harmo++;
                    $d--;
                }
                else
                {
                    if($rowTested > 1 &&  $rowTested <= 8)
                    {
                        if(count($table[$rowTested])<3)
                        {
                            continue;
                        }
                    }
                    if( $energy['color'] == 'black' && $rowTested == 9)
                    {
                        continue;
                    }
                    $possible = false;
                }
            }

            if($possible)
            {
                $copy = $this->tableCopy($table);
                unset($copy[$energy['row']][$energy['id']]);

                $energy['row'] = $rowTested;
                $copy[$rowTested][$energy['id']] = $energy;
            }

        }

        return $copy;


    }


    function argPickColor()
    {
        $ret = array();
        $ret['colors'] = array();
        $ret['step'] = 0;
        $player_id = self::getActivePlayerId();

        $colors = self::getCollectionFromDB( "SELECT distinct(color) FROM energy where location = 'bag'");// NOI18N
        foreach($colors as $color => $colorval)
        {
            if($color == 'black' || self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location =".$player_id." and color='".$color."'" )<3)// NOI18N
            {
                $ret['colors'][$color] = $color;
            }
        }
        return $ret;
    }

    function argChannel()
    {
        $player_id = self::getActivePlayerId();
        $channel = self::getGameStateValue( 'channel');
        $step = self::getGameStateValue( 'step');
        $am = self::getGameStateValue( 'alreadyMoved');
        $alreadyMoved = array();
        while($am>0)
        {
            $alreadyMoved[] = $am%100;
            $am = floor($am/100);
        }

        $ret = array();
        $ret['channel'] = $channel;
        $ret['step'] = $step;
        $ret['inspiration'] = self::getUniqueValueFromDB( "SELECT id FROM inspiration where player_id ='".$player_id."' and location = 'channel' and location_arg=".$channel);// NOI18N

        $ret['possibles'] = array();

        $table = $this->getEnergiesTable();

        if($channel == 8)
        {
            foreach($table[9] as $id => $energy)
            {
                $ret['possibles'][$energy['id']] = array();
                $ret['possibles'][$energy['id']][] = 9;
            }
        }
        else
        {
            $harmonized = array();
            for($i=2;$i<=8;$i++)
            {
                $harmonized[$i] = $this->isHarmonized($player_id, $i);
            }
            $harmonized[1] = false;
            $harmonized[9] = false;

            $energies = $this->getEnergies($player_id);
            foreach($energies as $id => $energy)
            {
                $ret['possibles'][$energy['id']] = array();

                if(!$harmonized[$energy['row']] && !in_array($energy['id'],$alreadyMoved) && $energy['row']!=9)
                {
                    $min = 0;
                    $max = count($this->channels[$channel]);

                    if($step>0)
                    {
                        $min = self::getGameStateValue( 'choice');
                        $max = $min+1;
                    }

                    for($p=$min;$p<$max;$p++)
                    {
                        $diff = $this->channels[$channel][$p][$step];
                        $possible = true;
                        $harmo = 0;
                        $rowTested = 0;

                        //check if move is possible
                        for($d=1;$d<=abs($diff) && $possible;$d++)
                        {
                            $rowTested = $energy['row'] + ($d+$harmo) * ($diff>0?1:-1);

                            if($rowTested >= 10  || $rowTested<=0)
                            {
                                $possible = false;
                            }
                            else if($harmonized[$rowTested])
                            {
                                $harmo++;
                                $d--;
                            }
                            else
                            {
                                if($rowTested > 1 && ( $rowTested <= 8))
                                {
                                    if(count($table[$rowTested])<3)
                                    {
                                        continue;
                                    }
                                }
                                if($energy['color'] == 'black' && $rowTested == 9)
                                {
                                    continue;
                                }
                                $possible = false;
                            }
                        }

                        if($possible)
                        {
                            $newRow = $energy['row'] + (abs($diff)+$harmo) * ($diff>0?1:-1);

                            //Test if it does not block further step
                            $stepCurrent = $step+1;
                            $choice = $p;
                            $alreadyMovedPlus = $am*100+$energy['id'];

                            $copy = $this->tableCopy($table);
                            unset($copy[$energy['row']][$energy['id']]);
                            $copy[$rowTested][$energy['id']] = $energy;

                            if($this->isNextStepPossible($channel, $stepCurrent, $choice, $alreadyMovedPlus, $copy))
                            {
                                $ret['possibles'][$energy['id']][$p] = $newRow;
                            }
                        }
                    }
                }

                if(count($ret['possibles'][$energy['id']]) == 0)
                {
                    unset( $ret['possibles'][$energy['id']]);
                }
            }
        }

        return $ret;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////


    function stCheckFinish()
    {
        $this->incStat(1, 'turns_number', self::getActivePlayerId());

        $finished = self::getGameStateValue( 'finished');

        if(!$finished)
        {
            $sql = "SELECT player_id id, player_name, player_color FROM player ";
            $players = self::getCollectionFromDb( $sql );
            foreach( $players as $player_id => $player )
            {
                $nb = 0;
                for($row=2;$row<=8;$row++)
                {
                    $nb += $this->isHarmonized($player_id, $row)?1:0;
                }
                //DEBUG
                if($nb>= 5)
                {
                    $finished = true;
                    self::setGameStateValue( 'finished', 1 );
                    self::notifyAllPlayers( "notee", clienttranslate('${player_name} has harmonized five Chakras. This game will end at the end of this round.'), array(
                        'player_name' => $players[$player_id]['player_name']
                    ) );
                }
            }
        }


        $this->activeNextPlayer();
        $this->giveExtraTime(self::getActivePlayerId());

        if($finished && self::getUniqueValueFromDB(  "SELECT player_no FROM player where player_id=".self::getActivePlayerId()) == 1)
        {
            foreach($this->colors as $cid => $color)
            {
                if($color != 'black')
                {
                    $value = self::getUniqueValueFromDB( "SELECT value FROM plenitude where color='".$color."'");
                    self::DbQuery( "update player set ".$color." = ".$value);
                }
            }

            $sql = "SELECT purple, darkblue, blue, green, yellow, orange, red FROM player where player_no = 1";
            $plenitudes = self::getObjectFromDb( $sql );

            self::notifyAllPlayers( "reveal", '', array(
                'plenitudes' => $plenitudes
            ) );

            //End of the game
            $sql = "SELECT player_id id, player_name, player_color, player_score FROM player ";
            $players = self::getCollectionFromDb( $sql );
            $harmo_max = 1;
            $player_harmo_max = -1;
            foreach( $players as $player_id => $player )
            {
                $nb = 0;
                $harmo = 0;
                $harmostill = true;
                $players[$player_id]['player_score'] = 0;
                $players[$player_id]['harmotot'] = 0;
                for($row=8;$row>=2;$row--)
                {
                    if($this->isHarmonized($player_id, $row))
                    {
                        $color = $this->colors[$row-1];
                        $players[$player_id]['player_score'] += self::getUniqueValueFromDB( "SELECT value FROM plenitude where color='".$color."'");
                        $players[$player_id]['harmotot'] ++;
                        if($harmostill)
                        {
                            $harmo++;
                        }
                    }
                    else
                    {
                        $harmostill = false;
                    }
                }
                $players[$player_id]['harmo'] = $harmo;
                if($harmo>$harmo_max)
                {
                    $harmo_max = $harmo;
                }
            }

            foreach( $players as $player_id => $player )
            {
                $this->setStat($players[$player_id]['player_score'], 'chakra_points', $player_id);
                $nbBlack = self::getUniqueValueFromDB( "SELECT count(*) FROM energy where location='".$player_id."' and row = 9 and color = 'black'");// NOI18N
                $players[$player_id]['player_score'] += $nbBlack;
                $this->setStat($nbBlack, 'black_points', $player_id);
                $this->setStat(0, 'harmo_points', $player_id);

                if($players[$player_id]['harmo'] == $harmo_max)
                {
                    $players[$player_id]['player_score'] += 2;
                    self::notifyAllPlayers( "notee", clienttranslate('${player_name} wins 2 points with harmonization bonus'), array(
                        'player_name' => $players[$player_id]['player_name']
                    ) );
                    $this->setStat(2, 'harmo_points', $player_id);
                }
                $sql = "UPDATE player set player_score=". $players[$player_id]['player_score']." where player_id=".$player_id;
                self::DbQuery( $sql );

                $this->setStat($players[$player_id]['harmotot'], 'chakra_harmonized', $player_id);
                $this->setStat($players[$player_id]['harmo'], 'chakra_aligned', $player_id);
            }

            $this->gamestate->nextState( 'finish' );
        }
        else
        {
            $this->gamestate->nextState( 'take' );
        }
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

    function zombieTurn( $state, $active_player )
    {
        $statename = $state['name'];

        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState( "zombiePass" );
                    break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive( $active_player, '' );

            return;
        }

        throw new feException( "Zombie mode not supported at this game state: ".$statename );
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

    function upgradeTableDb( $from_version )
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

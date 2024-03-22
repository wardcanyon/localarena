<?php 

require_once( APP_GAMEMODULE_PATH.'view/common/util.php' );
require_once( APP_GAMEMODULE_PATH.'view/common/template.php' );

class game_view {
    
    function __construct( )
    {
        $classname = ucfirst($this->getGameName());
        include($this->getGameName().'/'.$this->getGameName().'.game.php');
        
        $this->game = new $classname();
        $this->game->initTable();
        $this->template = new Template('default');
        $this->template->set_filenames(array(
            'game' =>  APP_BASE_PATH.$this->getGameName().'/'.$this->getGameName().'_'.$this->getGameName().'.tpl',
            'global' => APP_BASE_PATH.'view/common/global.tpl',
        ));
        $this->tpl = array();
        $this->page = $this;
        
        $this->tpl['CURRENT_PLAYER'] =  $this->game->getCurrentPlayerId();
        $this->tpl['LOGS'] =  self::_('What happened?');
        $this->tpl['SURE'] =  self::_('Are you sure?');
        $this->tpl['NO'] =  self::_('No');
        $this->tpl['CONFIRM'] =  self::_('Yes. I Confirm');
        $this->tpl['SAVE'] =  self::_('Save current game state');
        $this->tpl['LOAD'] =  self::_('Load previously saved game state');
    }
    
    
    function begin_block($block, $arrval)
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
    
    function getGameName() {
        return "noname";
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
            $this->insert_block('bg_player', array(
                'PLAYER_ID' => $player['player_id'],
                'PLAYER_NAME' => $player['player_name'],
                'PLAYER_COLOR' => $player['player_color'],
            ));   
        }
        
        $this->template -> assign_var_from_handle("GAME_PLAY_AREA","game");
        $this->template->pparse('global');
    }
    
    function build_page( $viewArgs )
    {	
        
    }
}

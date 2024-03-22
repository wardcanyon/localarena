    <div id="bg_game_overall">
		<div id="bg_game_central">
			<div id="greeting" class="alert alert-primary text-center" role="alert">
  			  <i id="bg_game_thinking_top" class="fas fa-cog fa-spin"></i>	
              <div class="d-inline" id="pagemaintitletext"></div>
              <div class="d-inline" id="bg_game_main_buttons"></div>
            </div>
            <div id="game_play_area">
            	{GAME_PLAY_AREA}
            </div>
            <div id="bg_game_bottom">
	            <ul class="nav nav-tabs" id="myTab" role="tablist">
				  <li class="nav-item">
				    <a class="nav-link active" id="debug-tab" data-toggle="tab" href="#debug" role="tab" aria-controls="debug" aria-selected="true">Debug</a>
				  </li>
				  <li class="nav-item">
				    <a class="nav-link" id="rules-tab" data-toggle="tab" href="#rules" role="tab" aria-controls="rules" aria-selected="false">Rules</a>
				  </li>
				  <li class="nav-item">
				    <a class="nav-link" id="options-tab" data-toggle="tab" href="#options" role="tab" aria-controls="options" aria-selected="false">Options</a>
				  </li>
				</ul>
				<div class="tab-content" id="myTabContent">
				  <div class="tab-pane fade show active text-center" id="debug" role="tabpanel" aria-labelledby="debug-tab">
				  	<span>
				        <button type="button" class="btn btn-secondary socketButton" id="bg_game_debugsave"> {SAVE}</button>
				        <a href="?loadDatabase=1&testplayer={CURRENT_PLAYER}">
				        	<button type="button" class="btn btn-primary"> {LOAD}</button>
				        </a>
		            </span>
				  </div>
				  <div class="tab-pane fade" id="rules" role="tabpanel" aria-labelledby="rules-tab">Rules</div>
				  <div class="tab-pane fade" id="options" role="tabpanel" aria-labelledby="options-tab">Options</div>
				</div>
            </div>
        </div> 
        <div id="bg_game_sidebar">
        	<div id="bg_game_players">
	        	<!-- BEGIN bg_player -->
	            <div id="bg_player_{PLAYER_ID}" class="bg_game_player d-flex flex-column">
  						<div class="d-flex justify-content-between" style="height: 32px;">
	  						<div class="">
	  							  <img src="avatar.png" alt="Avatar" class="avatar position-absolute"/>	
	  							  <i id="bg_game_thinking_{PLAYER_ID}" class="fas fa-cog fa-spin bg_game_thinking position-absolute"></i>				
	  						</div>
	  						<div class=" font-weight-bold" style="color: #{PLAYER_COLOR}">{PLAYER_NAME}</div>
	  						<div class=" id="player_status_{PLAYER_ID}">
	  						<span class="bg_game_score">
		  						<span id="bg_game_score_{PLAYER_ID}">0</span>
		  						<i class="fas fa-star" style="color:orange"></i>
							</span>
	  						 <a target="_blank" href="?testplayer={PLAYER_ID}" class="bg_game_debug_user">
	  							<i class="fas fa-user-secret"></i>
							</a>
	  						</div>
	            		</div>
  						<div class="d-flex" id="player_board_{PLAYER_ID}">
  						</div>
	            </div>
	            <!-- END bg_player -->
            </div>
            <div class="log_title"> {LOGS}</div>
            <div id="bg_game_logs">
            </div>
		</div>
	</div>
 <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle"> {SURE}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="bg_game_confirm_text">
        ...
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal"> {NO}</button>
        <button id="bg_game_modal_confirm_button" type="button" class="btn btn-primary" data-dismiss="modal"> {CONFIRM}</button>
      </div>
    </div>
  </div>
</div>
	
<script type="text/javascript">
var jstpl_bg_game_message = '<div class="bg_game_message" id="bg_game_message_${id}" >${content}</div>';
var jstpl_bg_game_button = '<button id="${id}" type="button" class="btn btn-outline-${color} ${blink} btn-sm ml-1">${label}</button>';
</script>  
{OVERALL_GAME_HEADER}


<div id="up">
    <div id="table">
        <div id="playertables" class=" {NBR}">
		    <div id="playertable_central" class="centraltable whiteblock hidden">
		    	<div id="playertablecard_0" class="playertablecard">
	        	</div>
	        	<div id="playertablecard_1" class="playertablecard">
	        	</div>
	        	<div id="playertablecard_2" class="playertablecard">
	        	</div>
	        	<div id="playertablecard_3" class="playertablecard">
	        	</div>
		    </div>
		        
		    <div id="tasks" class="tasks whiteblock hidden">
		    <div class="playertablename">
                    {TASKS}	                
            </div>
                <div id="tasklists" class = "playertablecards">
		    	
                </div>
		    </div> 
		    
		    <div id="endPanel" class="tasks whiteblock hidden">
		    <div class="playertablename" id="endResult">
            </div>
            <div class="playertablename">
                 	 {CONTINUE}               
            </div>
                <div class = "playertablecards">
                	<div class="check_ok" id="continue_ok"></div>
		    		<a id="yes_button" class="finalbutton bgabutton bgabutton_blue" href="#" target=_"blank"> {YES}</a><a id="no_button" class="finalbutton bgabutton bgabutton_gray" href="#"> {NO}</a>
                </div>
		    </div> 
		    
            <!-- BEGIN player -->
            <div id="playertable_{PLAYER_ID}" class="playertable whiteblock playertable_{DIR}">	        	
                <div class="playertablename" style="color:#{PLAYER_COLOR}">
                    {PLAYER_NAME}	                
                </div>
                <div id = "tasks_{PLAYER_ID}" class = "playertablecards">
                	<div id="comcard_{PLAYER_ID}" class="card_com" >
                		<div class="radio middle" id="radio_{PLAYER_ID}"></div>
                	</div>
                	<div id="commander_icon_spot_{PLAYER_ID}" class="commander appears"></div>
                	<div id="special_icon_spot_{PLAYER_ID}" class="special appears"></div>
                	<div id="special2_icon_spot_{PLAYER_ID}" class="special2 appears"></div>
                </div>
            </div>
            <!-- END player -->
        </div>
    </div>

    <div id="left" class="whiteblock">
        <div id='turn_count_wrap'><span> {MISSION}</span> <span id='mission_counter'></span></span></div>
        <div id='mission_description'></div>
     	<div id='try_wrap'><span> {TRY}</span> <span id='try_counter'></span></span></div>
     	<div id='total_try_wrap'><span> {TOTALTRY}</span> <span id='total_try_counter'></span></span></div>
        <div id="distress" class=""></div>
    </div>
</div>

<div id="myhand_wrap" class="whiteblock">
    <div id="myhand">
    </div>
</div>

<script type="text/javascript">
    // Javascript HTML templates
    var jstpl_task = '<div class="taskontable col${card_type} val${card_type_arg}" id="task_${task_id}">\
		                		<div class="task_marker task${token}" id="marker_${task_id}"></div>\
		                		<div class="check_ok ${status}" id="status_${task_id}"></div>\
		                	</div>';
	var jstpl_cardontable = '<div class="cardontable col${type} val${type_arg}" id="card_${id}"></div>';	
	var jstpl_player = '<div class="panel_container" id="panel_container_${player_id}"><div class="tricks" id="tricks_${player_id}"><div class="icon trick"></div><span class="trick_number"><span class="times">&times;</span> <span id="trick_counter_${player_id}"></span></span></div><div class="cardsinhands" id="cardsinhand_${player_id}"><div class="icon cardsinhand"></div><span class="trick_number"><span class="times">&times;</span> <span id="cardsinhands_counter_${player_id}"></span></span></div><div class="commander_in_panel" id="commander_in_panel_${player_id}"></div></div>';
var jstpl_tooltip_common = '<div class="tooltip-container">\
		<span class="tooltip-title">${title}</span>\
		<hr/>\
		<span class="tooltip-message">${description}</span>\
	</div>';
var jstpl_temp_comm = '<div class="radio selectable radio_temp ${status}" id="radio_${player_id}"></div>';
</script>

{OVERALL_GAME_FOOTER}

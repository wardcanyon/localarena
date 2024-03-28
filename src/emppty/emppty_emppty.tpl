{OVERALL_GAME_HEADER}

<div id="table">
    <div class="leftside">
    	<div class="maya" id="maya">
	    	<div class="plenitude" id="plenitude_purple"></div>
	    	<div class="plenitude" id="plenitude_darkblue"></div>
	    	<div class="plenitude" id="plenitude_blue"></div>
	    	<div class="plenitude" id="plenitude_green"></div>
	    	<div class="plenitude" id="plenitude_yellow"></div>
	    	<div class="plenitude" id="plenitude_orange"></div>
	    	<div class="plenitude" id="plenitude_red"></div>   
	            <div class="placeholder energyph row1 col1" id="ph_maya_1_1"></div>
	            <div class="placeholder energyph row1 col2" id="ph_maya_1_2"></div>
	            <div class="placeholder energyph row1 col3" id="ph_maya_1_3"></div> 	
	            <div class="placeholder energyph row2 col1" id="ph_maya_2_1"></div>
	            <div class="placeholder energyph row2 col2" id="ph_maya_2_2"></div>
	            <div class="placeholder energyph row2 col3" id="ph_maya_2_3"></div> 	
	            <div class="placeholder energyph row3 col1" id="ph_maya_3_1"></div>
	            <div class="placeholder energyph row3 col2" id="ph_maya_3_2"></div>
	            <div class="placeholder energyph row3 col3" id="ph_maya_3_3"></div> 	
    	</div>
	    <div class="meditations">
	    	<div class="imeditatemedit" id="imeditatemedit"></div>
	    	<div class="meditation purple" id="meditation_purple"></div>
	    	<div class="meditation darkblue" id="meditation_darkblue"></div>
	    	<div class="meditation blue" id="meditation_blue"></div>
	    	<div class="meditation green" id="meditation_green"></div>
	    	<div class="meditation yellow" id="meditation_yellow"></div>
	    	<div class="meditation orange" id="meditation_orange"></div>
	    	<div class="meditation red" id="meditation_red"></div>
	    </div>
	    <div class="colors">
	    	<div class="color purple" id="color_purple"></div>
	    	<div class="color darkblue" id="color_darkblue"></div>
	    	<div class="color blue" id="color_blue"></div>
	    	<div class="color green" id="color_green"></div>
	    	<div class="color yellow" id="color_yellow"></div>
	    	<div class="color orange" id="color_orange"></div>
	    	<div class="color red" id="color_red"></div>
	    </div>
    </div>
        <!-- BEGIN player -->
        <div id="playertable_{PLAYER_ID}" class="playertable">
            <div class="board board_back_{DIR}">
	            <div class="playertablename" style="color:#{PLAYER_COLOR}">
	                {PLAYER_NAME}
	            </div>
	            <div class="firstPlayer{PLAYER_NO}" id="firstPlayer{PLAYER_NO}"></div>
	            <div class="inspiration" id="inspiration_{PLAYER_ID}_1"></div>
	            <div class="inspiration" id="inspiration_{PLAYER_ID}_2"></div>
	            <div class="inspiration" id="inspiration_{PLAYER_ID}_3"></div>
	            <div class="inspiration" id="inspiration_{PLAYER_ID}_4"></div>
	            <div class="inspiration" id="inspiration_{PLAYER_ID}_5"></div>
	            <div class="placeholder chakra_1" id="ph_{PLAYER_ID}_chakra_1"></div>
	            <div class="placeholder chakra_2" id="ph_{PLAYER_ID}_chakra_2"></div>
	            <div class="placeholder chakra_3" id="ph_{PLAYER_ID}_chakra_3"></div>
	            <div class="placeholder chakra_4" id="ph_{PLAYER_ID}_chakra_4"></div>
	            <div class="placeholder chakra_5" id="ph_{PLAYER_ID}_chakra_5"></div>
	            <div class="placeholder chakra_6" id="ph_{PLAYER_ID}_chakra_6"></div>
	            <div class="placeholder chakra_7" id="ph_{PLAYER_ID}_chakra_7"></div>
	            <div class="placeholder board_1" id="ph_{PLAYER_ID}_board_1"></div>
	            <div class="placeholder board_2" id="ph_{PLAYER_ID}_board_2"></div>
	            <div class="placeholder board_3" id="ph_{PLAYER_ID}_board_3"></div>
	            <div class="placeholder board_4" id="ph_{PLAYER_ID}_board_4"></div>
	            <div class="placeholder board_5" id="ph_{PLAYER_ID}_board_5"></div>
	            <div class="placeholder channel channel_1" id="ph_{PLAYER_ID}_channel_1"></div>
	            <div class="placeholder channel channel_2" id="ph_{PLAYER_ID}_channel_2"></div>
	            <div class="placeholder channel channel_3" id="ph_{PLAYER_ID}_channel_3"></div>
	            <div class="placeholder channel channel_4" id="ph_{PLAYER_ID}_channel_4"></div>
	            <div class="placeholder channel channel_5" id="ph_{PLAYER_ID}_channel_5"></div>
	            <div class="placeholder channel channel_6" id="ph_{PLAYER_ID}_channel_6"></div>
	            <div class="placeholder channel channel_7" id="ph_{PLAYER_ID}_channel_7"></div>
	            <div class="placeholder channel channel_8" id="ph_{PLAYER_ID}_channel_8"></div>
	            <div class="placeholder energyph row1 col1" id="ph_{PLAYER_ID}_1_1"></div>
	            <div class="placeholder energyph row1 col2" id="ph_{PLAYER_ID}_1_2"></div>
	            <div class="placeholder energyph row1 col3" id="ph_{PLAYER_ID}_1_3"></div>
	            <div class="placeholder energyph row2 col1" id="ph_{PLAYER_ID}_2_1"></div>
	            <div class="placeholder energyph row2 col2" id="ph_{PLAYER_ID}_2_2"></div>
	            <div class="placeholder energyph row2 col3" id="ph_{PLAYER_ID}_2_3"></div>
	            <div class="placeholder energyph row3 col1" id="ph_{PLAYER_ID}_3_1"></div>
	            <div class="placeholder energyph row3 col2" id="ph_{PLAYER_ID}_3_2"></div>
	            <div class="placeholder energyph row3 col3" id="ph_{PLAYER_ID}_3_3"></div>
	            <div class="placeholder energyph row4 col1" id="ph_{PLAYER_ID}_4_1"></div>
	            <div class="placeholder energyph row4 col2" id="ph_{PLAYER_ID}_4_2"></div>
	            <div class="placeholder energyph row4 col3" id="ph_{PLAYER_ID}_4_3"></div>
	            <div class="placeholder energyph row5 col1" id="ph_{PLAYER_ID}_5_1"></div>
	            <div class="placeholder energyph row5 col2" id="ph_{PLAYER_ID}_5_2"></div>
	            <div class="placeholder energyph row5 col3" id="ph_{PLAYER_ID}_5_3"></div>
	            <div class="placeholder energyph row6 col1" id="ph_{PLAYER_ID}_6_1"></div>
	            <div class="placeholder energyph row6 col2" id="ph_{PLAYER_ID}_6_2"></div>
	            <div class="placeholder energyph row6 col3" id="ph_{PLAYER_ID}_6_3"></div>
	            <div class="placeholder energyph row7 col1" id="ph_{PLAYER_ID}_7_1"></div>
	            <div class="placeholder energyph row7 col2" id="ph_{PLAYER_ID}_7_2"></div>
	            <div class="placeholder energyph row7 col3" id="ph_{PLAYER_ID}_7_3"></div>
	            <div class="placeholder energyph row8 col1" id="ph_{PLAYER_ID}_8_1"></div>
	            <div class="placeholder energyph row8 col2" id="ph_{PLAYER_ID}_8_2"></div>
	            <div class="placeholder energyph row8 col3" id="ph_{PLAYER_ID}_8_3"></div>
	            <div class="placeholder energyph row9 col1" id="ph_{PLAYER_ID}_9_1"></div>
	            <div class="placeholder energyph row9 col2" id="ph_{PLAYER_ID}_9_2"></div>
	            <div class="placeholder energyph row9 col3" id="ph_{PLAYER_ID}_9_3"></div>
	            <div class="placeholder energyph row9 col4" id="ph_{PLAYER_ID}_9_4"></div>
	            <div class="placeholder energyph row9 col5" id="ph_{PLAYER_ID}_9_5"></div>
	            <div class="placeholder energyph row9 col6" id="ph_{PLAYER_ID}_9_6"></div>
	            <div class="placeholder energyph row9 col7" id="ph_{PLAYER_ID}_9_7"></div>
	            <div class="placeholder energyph row9 col8" id="ph_{PLAYER_ID}_9_8"></div>	  
		    	<div class="meditation purple hidden" id="meditation_{PLAYER_ID}_purple"></div>
		    	<div class="meditation darkblue hidden" id="meditation_{PLAYER_ID}_darkblue"></div>
		    	<div class="meditation blue hidden" id="meditation_{PLAYER_ID}_blue"></div>
		    	<div class="meditation green hidden" id="meditation_{PLAYER_ID}_green"></div>
		    	<div class="meditation yellow hidden" id="meditation_{PLAYER_ID}_yellow"></div>
		    	<div class="meditation orange hidden" id="meditation_{PLAYER_ID}_orange"></div>
		    	<div class="meditation red hidden" id="meditation_{PLAYER_ID}_red"></div>
            </div>
        </div>
        <!-- END player -->
    </div>


<script type="text/javascript">
var jstpl_energy = '<div class="energy ${color}" id="energy_${id}"></div>';
var jstpl_tooltip_common = '<div class="tooltip-container">\
		<span class="tooltip-title">${title}</span>\
		<hr/>\
		<span class="tooltip-message">${description}</span>\
	</div>';
var jstpl_firstplayer = '<div class="firstPlayerPanel" id="firstPlayer1b"></div>';
</script>  

{OVERALL_GAME_FOOTER}

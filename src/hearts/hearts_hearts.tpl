{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- Hearts implementation fixes: © ufm <tel2tale@gmail.com>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

    hearts_hearts.tpl
    
    This is the HTML template of your game.
    
    Everything you are writing in this file will be displayed in the HTML page of your game user interface,
    in the "main game zone" of the screen.
    
    You can use in this template:
    _ variables, with the format {MY_VARIABLE_ELEMENT}.
    _ HTML block, with the BEGIN/END format
    
    See your "view" PHP file to check how to set variables and control blocks
-->

<!--
-- Note: this code is modified to add suggestions from BGA players and popular variants.
-- Please visit here to read the basic code used in the BGA Studio tutorial: https://github.com/elaskavaia/
-->
<div id="variant_wrap" class="whiteblock" style="text-align:center; display:{NO_VARIANT};">
    <b>{REMOVED_LABEL}{REMOVED_CARDS}{LINE_BREAK}{POINT_LIMIT}{GAP_1}{NO_STARTER}{GAP_2}{MOON}{GAP_3}{PASS_CYCLE}</b>
</div>

<div id="game_board_wrap">
    <div id="game_board" class="{EXTENDED} {NO_SCORE_CHART}">
        <!-- BEGIN player -->
        <div class="playertable whiteblock playertable_{DIR}">
            <div class="playertablename" style="color:#{PLAYER_COLOR}"><span class="dealer_token" id="dealer_token_p{PLAYER_ID}">🃏 </span>{PLAYER_NAME}</div>
            <div class="playertablecard" id="playertablecard_{PLAYER_ID}"></div>
            <div class="playertablename" id="hand_score_wrap_{PLAYER_ID}">{SCORE_LABEL} <span id="hand_score_{PLAYER_ID}"></span></div>
        </div>
        <!-- END player -->
    </div>
    <div id="score_chart" style="display:{HIDE_SCORE_CHART};">
        <table style="margin: 10px auto;">
            <tbody>
                <tr class="table_color"><td class="table_cell" colspan="2" style="border-radius: 5px 5px 0 0;"><b>{SCORE_CHART}</b></td></tr>
                <tr class="table_color"><td class="table_cell"><span style="color:red">♥</span></td><td class="table_cell">{HEART_VALUE}</td></tr>
                <tr class="table_color" style="display:{JACK_DISPLAY}"><td class="table_cell"><span style="color:red">♦</span>J</td><td class="table_cell"><b>{JACK_VALUE}</b></td></tr>
                <tr class="table_color" style="display:{SPADES_DISPLAY}"><td class="table_cell"><span style="color:black">♠</span>A</td><td class="table_cell">{ACE_VALUE}</td></tr>
                <tr class="table_color" style="display:{SPADES_DISPLAY}"><td class="table_cell"><span style="color:black">♠</span>K</td><td class="table_cell">{KING_VALUE}</td></tr>
                <tr class="table_color"><td class="table_cell" style="border-bottom-left-radius: 5px;"><span style="color:black">♠</span>Q</td><td class="table_cell" style="border-bottom-right-radius: 5px;">{QUEEN_VALUE}</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div id="myhand_wrap" class="whiteblock">
    <b>{MY_HAND}</b>
    <div id="myhand"></div>
</div>

<!-- BEGIN audio_list -->
<audio id="audiosrc_{GAME_NAME}_{AUDIO}" src="{GAMETHEMEURL}img/{GAME_NAME}_{AUDIO}.mp3" preload="none" autobuffer></audio>
<audio id="audiosrc_o_{GAME_NAME}_{AUDIO}" src="{GAMETHEMEURL}img/{GAME_NAME}_{AUDIO}.ogg" preload="none" autobuffer></audio>
<!-- END audio_list -->

<script type="text/javascript">
// Javascript HTML templates
var jstpl_card = '<div class="card cardontable card_${card_style}" id="cardontable_${player_id}" style="background-position:-${x}00% -${y}00%"></div>';
</script>  

{OVERALL_GAME_FOOTER}
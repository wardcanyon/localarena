/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Chakra implementation : © Nicolas Gocel <nicolas.gocel@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * chakra.js
 *
 * Chakra user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter"
],
function (dojo, declare) {
    return declare("emppty", ebg.core.gamegui, {
        constructor: function(){
              
            // Here, you can init the global variables of your user interface
            // Example:
            // this.myGlobalValue = 0;
            
            this.colors = {
                    "purple" : _("purple"),
                    "darkblue" : _("dark blue"),
                    "blue" : _("blue"),
                    "green" : _("green"),
                    "yellow" : _("yellow"),
                    "orange" : _('orange'),
                    "red" : _('red'),
                    "black" : _('black'),
                }
            this.stateName = null;
            this.frees = {};
            this.possibles = {};
            this.selectedEnergyId = null;
            this.inspirationsNotBlocked = 5;
            
            this.translatableTexts = {
                    "cancel": _('Cancel'),
                    "tooltip_energy_title": _('Energy'),
                    "tooltip_energy_description": _('Place 3 energy with matching color on corresponding Maya to harmonize it.'),
                    "tooltip_inspiration_title": _('Inspiration token'),
                    "tooltip_inspiration_description": _('Inspiration is used to place energy on a Chakra or channel energy.'),
                    "tooltip_meditation_title": _('Meditation token'),
                    "tooltip_meditation_description": _('While meditating, select a meditation token to reveal the corresponding Plenitude token.'),
                    "tooltip_plenitude_title": _('Plenitude token'),
                    "tooltip_plenitude_description": _('At the end of the game, you score the number of points written on the plenitude token corresponding to the Chakra color you harmonized.'),
                    "tooltip_firstplayer_title": _('First player token'),
                    "tooltip_firstplayer_description": _('The end of the game is triggered when a player has at least five harmonized Chakras at the end of their turn. The current round is finished, allowing all players the same number of turns.'),
                    "tooltip_meditate_title": _('Meditate'),
                    "tooltip_meditate_description": _('Click on the meditation token with the desired color to meditate.'),
                    "tooltip_channel_title": _('Channel energy'),
                    "tooltip_channel1_description": _('Move one energy down by three Chakras.'),
                    "tooltip_channel2_description": _('Move three energy down by one Chakra.'),
                    "tooltip_channel3_description": _('Move one energy down by two Chakras, and another energy by one Chakra.'),
                    "tooltip_channel4_description": _('Move one energy up by two Chakras.'),
                    "tooltip_channel5_description": _('Move two energy up by one Chakra.'),
                    "tooltip_channel6_description": _('In the desired order, move one energy down and move another energy up by one Chakra.'),
                    "tooltip_channel7_description": _('Move one energy up OR down by one Chakra.'),
                    "tooltip_channel8_description": _('Discard one alleviated energy, and then choose one energy from the Universe bag. You must place it in an available Bhagya Bubble.'),
                    "confirmation_inspirations":_("Are you sure you want to placed your last inspiration token? There is a high risk of you being stuck until the end of the game."),
                    "confirmation_meditate":_("You have already meditate on this Chakra. Do you want to continue?"),
               }
        },
        
        /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */
        
        setup: function( gamedatas )
        {
            
            // Setting up player boards
            for( var player_id in gamedatas.players )
            {
                var player = gamedatas.players[player_id];
                         
                for(var id in player.inspirations)
                {
                    var inspiration = player.inspirations[id];
                    this.moveInspiration(inspiration);
                }
                for(var color in gamedatas.plenitudes)
                {
	                if(player[color] > 0)
	                {
	                	dojo.removeClass("meditation_"+player_id+"_"+color, "hidden");
	                }
                }
                if(player.position == 1)
                {
                	dojo.place( this.format_block('jstpl_firstplayer', player), $('player_board_' + player_id) );
                	 this.addTooltipHtmlToClass( 'firstPlayerPanel', this.format_block('jstpl_tooltip_common', {title: this.translatableTexts.tooltip_firstplayer_title, description: this.translatableTexts.tooltip_firstplayer_description }));
                     
                }
            }
            for(var color in gamedatas.plenitudes)
            {
            	var plenitude = gamedatas.plenitudes[color];
        		dojo.addClass("plenitude_"+color, 'val'+gamedatas.plenitudes[color] );
            }
            
            for(var en_id in gamedatas.energies)
            {
            	var energy = gamedatas.energies[en_id];
            	this.moveEnergy(energy);
            }
            dojo.query('.energyph').connect('onclick', this, 'onEnergyBoardPlaceHolder'); 
            dojo.query('.placeholder.channel').connect('onclick', this, 'onChannel'); 
            dojo.query('.color').connect('onclick', this, 'onColor'); 
            dojo.query('.meditation').connect('onclick', this, 'onColor'); 
            
             this.addTooltipHtmlToClass( 'inspiration', this.format_block('jstpl_tooltip_common', {title: this.translatableTexts.tooltip_inspiration_title, description: this.translatableTexts.tooltip_inspiration_description }));
            this.addTooltipHtmlToClass( 'meditation', this.format_block('jstpl_tooltip_common', {title: this.translatableTexts.tooltip_meditation_title, description: this.translatableTexts.tooltip_meditation_description }));
            this.addTooltipHtmlToClass( 'plenitude', this.format_block('jstpl_tooltip_common', {title: this.translatableTexts.tooltip_plenitude_title, description: this.translatableTexts.tooltip_plenitude_description }));
            this.addTooltipHtmlToClass( 'firstPlayer1', this.format_block('jstpl_tooltip_common', {title: this.translatableTexts.tooltip_firstplayer_title, description: this.translatableTexts.tooltip_firstplayer_description }));
            this.addTooltipHtmlToClass( 'imeditatemedit', this.format_block('jstpl_tooltip_common', {title: this.translatableTexts.tooltip_meditate_title, description: this.translatableTexts.tooltip_meditate_description }));
            
            for(var i=1;i<=8;i++)
            	{
            this.addTooltipHtmlToClass( 'channel_'+i, this.format_block('jstpl_tooltip_common', {title: this.translatableTexts.tooltip_channel_title, description: this.translatableTexts['tooltip_channel'+i+'_description'] }));
            	}
            
            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();
        },
       
        moveEnergy: function(energy)
        {
        	var id = "energy_"+energy.id;
        	var phid = "ph_"+energy.location+"_"+energy.row+"_"+energy.col;
        	
        	if(dojo.byId(id) == null)
        	{
        	   	dojo.place(this.format_block('jstpl_energy', energy), $('maya'));  
        	}
        	
        	this.attachToNewParent(id, phid);
        	this.slideToObjectPos( id, phid,0,0 ).play();   	
        	this.addTooltipHtml( id, this.format_block('jstpl_tooltip_common', {title: this.translatableTexts.tooltip_energy_title+" : "+this.colors[energy.color], description: this.translatableTexts.tooltip_energy_description }));
            dojo.query('#'+id).connect('onclick', this, 'onEnergyMaya'); 
        },
        
        moveInspiration: function(inspiration)
        {
        	var id = "inspiration_"+inspiration.player_id+"_"+inspiration.id;
        	var phid = "ph_"+inspiration.player_id+"_"+inspiration.location+"_"+inspiration.location_arg;
    		this.slideToObject( id, phid ).play();
        },

        ///////////////////////////////////////////////////
        //// Game & client states
        
        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState: function( stateName, args )
        {
        	this.stateName = stateName;
            switch( stateName )
            {            
	            case 'take':
	            	 $('pagemaintitletext').innerHTML = $('pagemaintitletext').innerHTML.replace("{receive}", '<span class="logIcon blue" ></span> &nbsp;' );
	                    $('pagemaintitletext').innerHTML = $('pagemaintitletext').innerHTML.replace("{channel}", '<span class="logIcon ichannel"></span> &nbsp;' );
	                    $('pagemaintitletext').innerHTML = $('pagemaintitletext').innerHTML.replace("{meditate}", '<span class="logIcon imeditate"></span> &nbsp;' );
	                	
	                if( this.isCurrentPlayerActive() )
	                { 

	                    this.inspirationsNotBlocked = args.args.inspirationsNotBlocked;
		            	this.frees = args.args.frees;
	            		dojo.query(".leftside .meditation").addClass("selectable");
		            	if(args.args.inspirationsLeft>0 || args.args.frees[1]>0)
		            	{
		            		dojo.query("#maya .energy").addClass("selectable");
		            		for(var color in args.args.prevent)
		            		{
			            		dojo.query("#maya .energy."+color).removeClass("selectable");		            			
		            		}
		            		
		            		for(var i=1;i<=8;i++)
		            		{
		            			if(args.args.channel[i] == 1)
		            			{
		            				dojo.query("#ph_"+this.player_id+"_channel_"+i).addClass("selectable"); 	
		            			}
		            		}
	            		}
	                }
	            	break;	            
	            
	            case 'channel':
	            	var id = "#inspiration_"+this.getActivePlayerId()+"_"+args.args.inspiration;
	            	this.possibles = args.args.possibles;
	            	
	                this.selectedEnergyId = null;
	            	dojo.query(id).addClass("currentInspiration");
	            	if( this.isCurrentPlayerActive() )
	                { 
		            	for(var id in this.possibles)
		                {
		            		dojo.addClass("energy_"+id, 'selectable' );
		                }
	                }
	            	
	                break;
	                
	            case "pickColor":	            	
            		if( this.isCurrentPlayerActive() )
	                { 
            			dojo.query(".color").addClass('hidden' );
            			for(var color in args.args.colors)
                        {

                			dojo.query(".color."+color).removeClass('hidden' );
                			dojo.query(".color."+color).addClass('selectable' );
                        }
            			
            			dojo.query(".colors").addClass('appears' );
	                }
	            	break;
	           
            }
            
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function( stateName )
        {
            
            dojo.query(".selectable").removeClass("selectable"); 
            dojo.query(".selected").removeClass("selected"); 
            dojo.query(".currentInspiration").removeClass("currentInspiration"); 
    		dojo.query(".appears").removeClass('appears' );              
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons: function( stateName, args )
        {
                      
            if( this.isCurrentPlayerActive() )
            {            
                switch( stateName )
                {
	            case 'channel':
	            case 'pickColor':
		            	if(args.step == 0)
		            		{
		                  	 this.addActionButton('cancel_button', this.translatableTexts.cancel , 'onCancel');
		            		}
		            	break;
                }
            }
        },        

        ///////////////////////////////////////////////////
        //// Utility methods
        
        /*
        
            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.
        
        */


        ///////////////////////////////////////////////////
        //// Player's action
        
        onCancel:function(event)
        {
        	if( this.checkAction( "actCancel" ) ) {
                this.ajaxcall('/chakra/chakra/actCancel.html', {
                    lock:true
                },this, function( result ) {}, function( is_error ) { } );
            }
        },
        
        onColor:function(event)
        {
            dojo.stopEvent( event );  
            if(this.isCurrentPlayerActive())
            {
            	if(event.currentTarget.classList.contains('selectable')  && this.checkAction( "actColor" ) ) { 
            		
            		var color = event.currentTarget.id.split('_')[1];            		
            		if(this.stateName == "take" && !dojo.hasClass("meditation_"+this.player_id+"_"+color,"hidden"))
            		{
            			this.confirmationDialog( this.translatableTexts.confirmation_meditate, dojo.hitch( this, function() {

            				dojo.query(".selectable").removeClass("selectable"); 
                    		this.ajaxcall('/chakra/chakra/actColor.html', {
        	 	                   lock:true,
        	 	                  color:color
        	 	                },this, function( result ) {
        	 	                }, function( is_error ) { } );
                        } ) ); 
                        return;
            		}
            		else
            		{

                        dojo.query(".selectable").removeClass("selectable"); 
                		this.ajaxcall('/chakra/chakra/actColor.html', {
    	 	                   lock:true,
    	 	                  color:color
    	 	                },this, function( result ) {
    	 	                }, function( is_error ) { } );
            		}
            		
            		
            	}
            }
        },
        
        onChannel:function(event)
        {
            dojo.stopEvent( event );  
            if(this.isCurrentPlayerActive())
            {
            	if(event.currentTarget.classList.contains('selectable')  && this.checkAction( "actChannel" ) ) { 
            		
            		var id = event.currentTarget.id.split('_')[3];            		

                    dojo.query(".selectable").removeClass("selectable"); 
                    dojo.query(".selected").removeClass("selected"); 
            		
            		this.ajaxcall('/chakra/chakra/actChannel.html', {
	 	                   lock:true,
	 	                  id:id
	 	                },this, function( result ) {
	 	                }, function( is_error ) { } );
            	}
            }
        },
        
        onEnergyBoardPlaceHolder:function(event)
        {
            dojo.stopEvent( event );  
            if(this.isCurrentPlayerActive() )
            {
            	if(event.currentTarget.classList.contains('selectable')) { 
            		var ids = "";
            		dojo.query("#maya .selected").forEach(function(selectTag){
            			ids+= selectTag.id.replace("energy_","") +" ";
                    });
            		var row = event.currentTarget.id.split('_')[2];            		

            		
                    if( this.stateName == "take" && this.checkAction( "actTake" ) )
            		{
                    
                    if(this.inspirationsNotBlocked > 1 || row== 1)
                    {

                        dojo.query(".selectable").removeClass("selectable"); 
                        dojo.query(".selected").removeClass("selected"); 
                		this.ajaxcall('/chakra/chakra/actTake.html', {
    	 	                   lock:true,
    	 	                  energyIds:ids,
    	 	                  row:row
    	 	                },this, function( result ) {
    	 	                }, function( is_error ) { } );
                    }
                    else
                    	{
                    	 this.confirmationDialog( this.translatableTexts.confirmation_inspirations, dojo.hitch( this, function() {

                             dojo.query(".selectable").removeClass("selectable"); 
                             dojo.query(".selected").removeClass("selected"); 
                     		this.ajaxcall('/chakra/chakra/actTake.html', {
         	 	                   lock:true,
         	 	                  energyIds:ids,
         	 	                  row:row
         	 	                },this, function( result ) {
         	 	                }, function( is_error ) { } );
                         } ) ); 
                         return;
                    	}
            		}
                    else
                    {     

                        dojo.query(".selectable").removeClass("selectable"); 
                        dojo.query(".selected").removeClass("selected"); 
                		this.ajaxcall('/chakra/chakra/actMove.html', {
    	 	                   lock:true,
    	 	                  energyId:this.selectedEnergyId,
    	 	                  row:row
    	 	                },this, function( result ) {
    	 	                }, function( is_error ) { } );
                    	
                    }
            	}
            }
        },
        
        
        onEnergyMaya:function(event)
        {
            dojo.stopEvent( event );  
            if(this.isCurrentPlayerActive())
            {
            	if(event.currentTarget.classList.contains('selectable')) { 
            		
            		if( this.stateName == "take" && this.checkAction( "actTake" ) )
            		{
	            		event.currentTarget.classList.toggle('selected');
	            		var parentId = event.currentTarget.parentNode.id;
	            		var row = parentId.split('_')[2];
	            		var col = parentId.split('_')[3];
	            		
	            		if(event.currentTarget.classList.contains('selected'))
	            		{
	            			if(col != 1) dojo.query("#maya .col1 .energy").removeClass("selected"); 
	            			if(col != 2) dojo.query("#maya .col2 .energy").removeClass("selected"); 
	            			if(col != 3) dojo.query("#maya .col3 .energy").removeClass("selected"); 
	            			            			
	            			var id = event.currentTarget.id;
	            			var color = event.currentTarget.classList[1];
	            			dojo.query("#maya .energy."+color).removeClass("selected");
	            			dojo.query("#"+id).addClass("selected");
	            			
	            			if(dojo.query("#maya .col"+col+" .energy.black").length>0 && dojo.query("#maya .col"+col+" .energy.black.selected").length<1)
	            			{
	            				dojo.query("#maya .col"+col+" .energy.black")[0].classList.add("selected");
	            			}
	            		}
	            		else if(event.currentTarget.classList.contains('black'))
	            		{
	            			if(col == 1) dojo.query("#maya .col1 .energy").removeClass("selected"); 
	            			if(col == 2) dojo.query("#maya .col2 .energy").removeClass("selected"); 
	            			if(col == 3) dojo.query("#maya .col3 .energy").removeClass("selected"); 
	            		}            		
	            	
		            	var nb = dojo.query("#maya .energy.selected").length;
		            	for(var row = 1;row<=9;row++)
		            	{
		            		if(this.frees[row]>=nb && nb>0)
		            		{
		            			dojo.query("#playertable_"+this.player_id+" .row"+row+".energyph:empty").addClass("selectable"); 
		            		}
		            		else
		            		{
		            			dojo.query("#playertable_"+this.player_id+" .row"+row+".energyph").removeClass("selectable"); 
		            		}
		            	}
	            	}
            		else
            		{
            			this.onEnergyChannel(event);
            		}
            	
            	}
            }
        },
        
        onEnergyChannel:function(event)
        {
        	if( this.stateName == "channel" && this.checkAction( "actMove" ) )
    		{
        		
        		this.selectedEnergyId  = event.currentTarget.id.split('_')[1];
        		if(this.possibles[this.selectedEnergyId].length == 1)
        		{
        			this.ajaxcall('/chakra/chakra/actMove.html', {
	 	                   lock:true,
	 	                   energyId:this.selectedEnergyId,
	 	                   row:this.possibles[this.selectedEnergyId][0]
	 	                },this, function( result ) {
	 	                }, function( is_error ) { } );
        		}
        		else
        		{
                    dojo.query(".selected").removeClass("selected"); 
            		event.currentTarget.classList.add('selected');
            		
            		for(var r in this.possibles[this.selectedEnergyId])
	                {
            			var row = this.possibles[this.selectedEnergyId][r];
            			dojo.query("#playertable_"+this.player_id+" .row"+row+".energyph:empty").addClass("selectable"); 
	            	}
            		
        		}
    		}
        },
        

        
        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your chakra.game.php file.
        
        */
        setupNotifications: function()
        {
            dojo.subscribe( 'energy', this, "notif_energy" );
            dojo.subscribe( 'inspiration', this, "notif_inspiration" );
            dojo.subscribe( 'destruct', this, "notif_destruct" );
            dojo.subscribe( 'newenergy', this, "notif_newenergy" );
            dojo.subscribe( 'newmeditation', this, "notif_newmeditation" );
            dojo.subscribe( 'reveal', this, "notif_reveal" );
            dojo.subscribe( 'harmonize', this, "notif_harmonize" );
        },  
        
        notif_harmonize: function( notif )
        {
        	this.scoreCtrl[ notif.args.player_id ].toValue( notif.args.score );
        },

        notif_newenergy: function( notif )
        {
        	var energy = notif.args.energy;
        	var id = "energy_"+energy.id;
        	var phid = "ph_"+energy.location+"_"+energy.row+"_"+energy.col;
        	
        	if(dojo.byId(id) == null)
        	{
        	   	dojo.place(this.format_block('jstpl_energy', energy), $('color_'+energy.color));  
        	}
            this.moveEnergy(notif.args.energy);
            
        }, 
        
        notif_newmeditation: function( notif )
        {
        	dojo.removeClass("meditation_"+notif.args.player_id+"_"+notif.args.color, "hidden");
        	dojo.addClass("meditation_"+notif.args.player_id+"_"+notif.args.color, "appears");
        	if(notif.args.value != 0)
        	{
            	var plenitudeId = "plenitude_"+notif.args.color;
                dojo.query("#"+plenitudeId).addClass("val"+notif.args.value); 
        		
        	}
        	 this.addTooltipHtmlToClass( 'meditation', this.format_block('jstpl_tooltip_common', {title: this.translatableTexts.tooltip_meditation_title, description: this.translatableTexts.tooltip_meditation_description }));
             
        }, 

        notif_reveal: function( notif )
        {
        	for(var color in notif.args.plenitudes)
            {
            	var plenitude = notif.args.plenitudes[color];
        		dojo.addClass("plenitude_"+color, 'val'+notif.args.plenitudes[color] );
            }
        }, 
        
        notif_energy: function( notif )
        {
            this.moveEnergy(notif.args.energy);
        }, 
        notif_inspiration: function( notif )
        {
            this.moveInspiration(notif.args.inspiration);
              
        }, 
        notif_destruct: function( notif )
        {
            this.fadeOutAndDestroy(notif.args.id);
        },    
        
        format_string_recursive : function(log, args) {
            try {
                if (log && args && !args.processed) {
                    args.processed = true;

                    var keys = ['energies','meditation','colorcanal'];                    
                    
                    for ( var i in keys) {
                        var key = keys[i];
                        if (typeof args[key] == 'string') {
                        	args[key] = this.getTokenDiv(key, args); 
                        }
                    }    
                }
            } catch (e) {
                console.error(log,args,"Exception thrown", e.stack);
            }
            return this.inherited(arguments);
        },
        
        getTokenDiv : function(key, args) {
        	var ret = "";
        	switch(key)
        	{
	        	case 'energies':
	        	var list = args[key].split(" ");
	        	for(var c_id in list)
	            {
	        		var cid = list[c_id];
	        		if(cid != '')
	        			{
	            	var card = this.colors[cid];
	            	ret += '<span class="logIcon '+cid+'" title="'+this.colors[cid]+'"></span>';
	        			}
	            }
	        	break;

	        	case 'meditation':
		            	ret += '<span class="logIcon meditation '+args[key]+'" title="'+this.colors[args[key]]+'"></span>';		        		
		        	break;
	        	case 'colorcanal':
	            	ret += '<span class="logIcon '+args[key]+'" title="'+this.colors[args[key]]+'"></span>';		        		
	        	break;
        	}
        	return ret;
       },
        
   });             
});

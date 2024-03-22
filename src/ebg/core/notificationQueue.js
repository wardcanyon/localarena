
define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui"
],
function (dojo, declare, game) {
    return declare("ebg.core.notificationQueue", null, {
        constructor: function(game){
        	this.processingNotifications = false;
        	this.notificationsBuffer = [];
        	this.notificationDelay = [];
        	this.game = game;
        	this.lastMoveId = -1;
        },
        
        addEvent: function(event)
        {
        	this.notificationsBuffer.push(event);
        	if(!this.game.replay)
        	{
        		this.processNotif();
        	}
        },
        
        processNotif: function()
        {
        	if(!this.processingNotifications && this.notificationsBuffer.length > 0)
        	{
        		this.processingNotifications = true;
        		var event = this.notificationsBuffer.shift();
        		var delayBefore = 0;
        		if(this.game.replay && this.lastMoveId != event.gamelog_move_id)
        		{
        			delayBefore = 1500;
        		}
        		this.lastMoveId = event.gamelog_move_id;
        		
        		setTimeout(dojo.hitch( this, function() {
	        		this.game.addLog(event);
	        		dojo.publish(event.notification_type, event);  	        		
	        		var delay = 0;
	        		if(event.notification_type in  this.notificationDelay)
	        		{
	        			delay = this.notificationDelay[event.notification_type];
	        		}
	    			setTimeout(dojo.hitch( this, function() { 
	                	this.processingNotifications = false;
	    				this.processNotif(); 
	    			}), delay);
    			
        		}), delayBefore);
    			
        	}
        	
        	if(this.notificationsBuffer.length == 0)
        	{
        		this.game.replay = false;
        	}
        	
        },
        
        setSynchronous: function(notif, delay)
        {
        	this.notificationDelay[notif] = delay;
        },
        
        
    });             
 });
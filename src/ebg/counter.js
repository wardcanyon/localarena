define([
    "dojo","dojo/_base/declare"
],
function (dojo, declare, game) {
    return declare("ebg.counter", null, {
        constructor: function(){
        	
        },
        
        create: function(containerId)
        {
        	this.containerId = containerId;
        	this.value = 0;
        },
        
        getValue: function()
        {
        	return this.value;
        },
        
        incValue: function(inc)
        {
        	this.setValue(this.value + parseInt(inc));
        },
        
        setValue: function(val)
        {
        	this.value = parseInt(val);
        	dojo.byId(this.containerId).innerHTML = this.value;
        },
        
    });             
});
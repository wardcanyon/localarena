var __decorate = (this && this.__decorate) || function (decorators, target, key, desc) {
    var c = arguments.length, r = c < 3 ? target : desc === null ? desc = Object.getOwnPropertyDescriptor(target, key) : desc, d;
    if (typeof Reflect === "object" && typeof Reflect.decorate === "function") r = Reflect.decorate(decorators, target, key, desc);
    else for (var i = decorators.length - 1; i >= 0; i--) if (d = decorators[i]) r = (c < 3 ? d(r) : c > 3 ? d(target, key, r) : d(target, key)) || r;
    return c > 3 && r && Object.defineProperty(target, key, r), r;
};
(function (factory) {
    if (typeof module === "object" && typeof module.exports === "object") {
        var v = factory(require, exports);
        if (v !== undefined) module.exports = v;
    }
    else if (typeof define === "function" && define.amd) {
        define(["require", "exports", "../declareDecorator", "ebg/core/notificationQueue", "dojo/dom-geometry", "dojo/dom-style", "dojo/fx", "dijit/Tooltip"], factory);
    }
})(function (require, exports) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.EbgCoreGamegui = void 0;
    var declareDecorator_1 = require("../declareDecorator");
    require("ebg/core/notificationQueue");
    var domGeom = require("dojo/dom-geometry");
    var style = require("dojo/dom-style");
    var fx = require("dojo/fx");
    var Tooltip = require("dijit/Tooltip");
    var EbgCoreGamegui = (function () {
        function EbgCoreGamegui() {
            this.lock = false;
            this.bgg_stateId = 0;
            this.replay = false;
            this.processingNotifications = false;
            this.notificationsBuffer = [];
            this.notifqueue = null;
            this.socket = null;
            this.bg_game_players = null;
            this.gamedatas = null;
            this.bgg_states = null;
            this.active_player_id = null;
            this.multiactive = [];
        }
        EbgCoreGamegui.prototype.setup = function (gamedatas) {
            console.log("setup parent");
            console.log('*** gamegui ctor');
            console.log(ebg);
            this.notifqueue = new ebg.core.notificationQueue(this);
        };
        EbgCoreGamegui.prototype.completesetup = function (gamename, gamedatas) {
            this.socket = new WebSocket("ws://localhost:3000/" + this.player_id);
            this.socket.onopen = function (e) { };
            var gui = this;
            this.socket.onmessage = function (event) {
                var event = JSON.parse(event.data);
                gui.notifqueue.addEvent(event);
            };
            this.socket.onclose = function (event) {
                alert("[close] Connection close");
            };
            this.socket.onerror = function (error) {
                alert("[error] ".concat(error.message));
            };
            console.log(gamedatas);
            this.bg_game_players = gamedatas.players;
            this.gamedatas = gamedatas.alldatas;
            this.active_player_id = gamedatas.active_player_id;
            this.bgg_states = gamedatas.states;
            this.setup(gamedatas.alldatas);
            this.bRealtime = true;
            dojo.subscribe("bg_onEnteringState", this, "notif_bg_onEnteringState");
            this.addTooltipToClass("bg_game_score", _("Current score"));
            this.addTooltipToClass("bg_game_debug_user", _("Open new window with this user"));
            this.addTooltipToClass("bg_game_thinking", _("Active player"));
            dojo.query(".socketButton").connect("onclick", this, "onSocketButton");
            this.notif_bg_onEnteringState({ args: gamedatas });
        };
        EbgCoreGamegui.prototype.onUpdateActionButtons = function (stateName, args) { };
        EbgCoreGamegui.prototype.onEnteringState = function (stateName, args) {
            console.log("onEnteringState parent");
        };
        EbgCoreGamegui.prototype.onLeavingState = function (stateName) { };
        EbgCoreGamegui.prototype.onSocketButton = function (event) {
            dojo.stopEvent(event);
            var id = event.currentTarget.id;
            var parameters = { lock: false };
            parameters["bgg_player_id"] = this.player_id;
            parameters["bgg_actionName"] = id;
            this.socket.send(JSON.stringify(parameters));
        };
        EbgCoreGamegui.prototype.notif_bg_onEnteringState = function (gamedatas) {
            gamedatas = gamedatas.args;
            this.bg_game_players = gamedatas.players;
            if (this.bgg_stateId > 0) {
                var state = this.bgg_states[this.bgg_stateId];
                this.onLeavingState(state["name"]);
            }
            dojo.empty("bg_game_main_buttons");
            console.log('*** notif_bg_onEnteringState() gamedatas=');
            console.log(gamedatas);
            if (this.active_player_id === undefined) {
                this.active_player_id = null;
            }
            else {
                this.active_player_id = parseInt(gamedatas.active_player_id);
            }
            if (this.multiactive === undefined) {
                this.multiactive = [];
            }
            else {
                this.multiactive = gamedatas.multiactive.map(function (x) { return parseInt(x); });
            }
            this.bgg_stateId = gamedatas.id;
            var state = this.bgg_states[this.bgg_stateId];
            state["id"] = this.bgg_stateId;
            state["args"] = gamedatas.args;
            state["active_player"] = gamedatas.active_player_id;
            state["multiactive"] = gamedatas.multiactive;
            dojo.query(".bg_game_thinking").addClass("bg_game_hidden");
            for (var player_id in gamedatas.players) {
                if (this.isPlayerActive(player_id)) {
                    dojo.removeClass("bg_game_thinking_" + player_id, "bg_game_hidden");
                }
            }
            if (this.isCurrentPlayerActive()) {
                dojo.removeClass("bg_game_thinking_top", "bg_game_hidden");
            }
            else {
                dojo.addClass("bg_game_thinking_top", "bg_game_hidden");
            }
            this.onUpdateActionButtons(state["name"], gamedatas.args);
            if (this.isCurrentPlayerActive()) {
                dojo.byId("pagemaintitletext").innerHTML = this.format_string_recursive(_(state["descriptionmyturn"]), gamedatas.args);
            }
            else {
                dojo.byId("pagemaintitletext").innerHTML = this.format_string_recursive(_(state["description"]), gamedatas.args);
            }
            this.onEnteringState(state["name"], state);
            this.lock = false;
        };
        EbgCoreGamegui.prototype.notif_bg_LeavingState = function (gamedatas) {
            this.bgg_stateId = gamedatas.id;
            var state = this.bgg_states[this.bgg_stateId];
            this.onLeavingState(state["name"]);
        };
        EbgCoreGamegui.prototype.confirmationDialog = function (label, func) {
            $("bg_game_confirm_text").innerHTML = label;
            jQuery("#bg_game_modal_confirm_button").off();
            jQuery("#bg_game_modal_confirm_button").on("click", func);
            jQuery("#exampleModalCenter").modal();
        };
        EbgCoreGamegui.prototype.format_block = function (templateId, data) {
            var template = window[templateId];
            if (typeof "template" === "string") {
                var result = template;
                for (var key in data) {
                    result = result.replaceAll("${" + key + "}", data[key]);
                }
                return result;
            }
            console.error('Invalid template ID: ' + templateId);
        };
        EbgCoreGamegui.prototype.logAll = function (logs) {
            for (var key in logs) {
                var log = logs[key];
                this.addLog(JSON.parse(log.gamelog_notification));
            }
        };
        EbgCoreGamegui.prototype.format_string_recursive = function (log, args) {
            if (log) {
                for (var key in args) {
                    if (args[key]["log"] === undefined) {
                        log = log.replace("${" + key + "}", args[key]);
                    }
                    else {
                        var chg = this.format_string_recursive(args[key]["log"], args[key]["args"]);
                        log = log.replace("${" + key + "}", chg);
                    }
                }
                log = this.replacePlayerName(log);
                log = log.replace("${you}", this.divYou());
                log = log.replace("${actplayer}", this.divActPlayer());
            }
            else {
                log = "";
            }
            return log;
        };
        EbgCoreGamegui.prototype.divYou = function () {
            var color = this.bg_game_players[this.player_id].player_color;
            var color_bg = "";
            if (this.gamedatas.players[this.player_id] &&
                this.bg_game_players[this.player_id].color_back) {
                color_bg =
                    "background-color:#" +
                        this.bg_game_players[this.player_id].color_back +
                        ";";
            }
            var you = '<span style="font-weight:bold;color:#' +
                color +
                ";" +
                color_bg +
                '">' +
                _("You") +
                "</span>";
            return you;
        };
        EbgCoreGamegui.prototype.replacePlayerName = function (log) {
            for (var key in this.bg_game_players) {
                var player = this.bg_game_players[key];
                var color = player.player_color;
                var name = player.player_name;
                var color_bg = "";
                if (player.color_back) {
                    color_bg = "background-color:#" + player.color_back + ";";
                }
                log = log.replace(name, '<span style="font-weight:bold;color:#' +
                    color +
                    ";" +
                    color_bg +
                    '">' +
                    name +
                    "</span>");
            }
            return log;
        };
        EbgCoreGamegui.prototype.divActPlayer = function () {
            var color = this.bg_game_players[this.player_id].player_color;
            var name = this.bg_game_players[this.player_id].player_name;
            var color_bg = "";
            if (this.gamedatas.players[this.player_id] &&
                this.bg_game_players[this.player_id].color_back) {
                color_bg =
                    "background-color:#" +
                        this.bg_game_players[this.player_id].color_back +
                        ";";
            }
            var you = '<span style="font-weight:bold;color:#' +
                color +
                ";" +
                color_bg +
                '">' +
                name +
                "</span>";
            return you;
        };
        EbgCoreGamegui.prototype.addLog = function (event) {
            if (event.notification_log != "") {
                var raw = event.notification_log;
                raw = this.format_string_recursive(raw, event.args);
                dojo.place(this.format_block("jstpl_bg_game_message", {
                    id: event.gamelog_id,
                    content: raw,
                }), "bg_game_logs", "first");
                dojo.query("#bg_game_message_" + event.gamelog_id).connect("onclick", this, dojo.partial(function (id) {
                    var action_id = id;
                    this.confirmationDialog(_("Do you want to replay the game from this point?"), function () {
                        var newurl = window.location.href.split("?")[0] +
                            "?replayFrom=" +
                            action_id;
                        var queryString = window.location.search;
                        var urlParams = new URLSearchParams(queryString);
                        if (urlParams.get("testplayer") != undefined) {
                            newurl += "&testplayer=" + urlParams.get("testplayer");
                        }
                        window.location.replace(newurl);
                    });
                }, event.gamelog_move_id));
                this.addTooltip("bg_game_message_" + event.gamelog_id, _("Click to replay the game from this point"));
            }
        };
        EbgCoreGamegui.prototype.newState = function (gamedatas) {
            this.bgg_stateId = gamedatas.id;
            var state = this.bgg_states[this.bgg_stateId];
            state["id"] = this.bgg_stateId;
            state["args"] = gamedatas.args;
            state["active_player"] = gamedatas.active_player_id;
            state["multiactive"] = gamedatas.multiactive;
            if (this.isCurrentPlayerActive()) {
                $("pagemaintitletext").innerHTML = _(state["descriptionmyturn"]);
            }
            else {
                $("pagemaintitletext").innerHTML = _(state["description"]);
            }
            this.onEnteringState(state["name"], state);
        };
        EbgCoreGamegui.prototype.slideToObject = function (mobile_obj, target_obj, duration, delay) {
            if (duration === void 0) { duration = 500; }
            if (delay === void 0) { delay = 0; }
            return this.slideToObjectPos(mobile_obj, target_obj, 0, 0, duration, delay);
        };
        EbgCoreGamegui.prototype.slideToObjectPos = function (mobile_obj, target_obj, target_x, target_y, duration, delay) {
            if (duration === void 0) { duration = 500; }
            if (delay === void 0) { delay = 0; }
            var mobile = dojo.byId(mobile_obj);
            var computedStyle = style.getComputedStyle(mobile);
            var start = domGeom.position(mobile_obj);
            var stop = domGeom.position(target_obj);
            var finalx = stop.x - start.x;
            var finaly = stop.y - start.y;
            var left = finalx + parseFloat(computedStyle.left.replace("px", "")) + target_x;
            var top = finaly + parseFloat(computedStyle.top.replace("px", "")) + target_y;
            return fx.slideTo({
                node: mobile_obj,
                duration: duration,
                delay: delay,
                left: left,
                top: top,
            });
        };
        EbgCoreGamegui.prototype.slideTemporaryObject = function (mobile_obj_html, mobile_obj_parent, from, to, duration, delay) {
            console.log("not implemented : slideTemporaryObject");
        };
        EbgCoreGamegui.prototype.slideToObjectAndDestroy = function (node, to, time, delay) {
            var start = domGeom.position(node);
            var stop = domGeom.position(to);
            var finalx = start.x - stop.x;
            var finaly = start.y - stop.y;
            var tnode = node;
            dojo
                .animateProperty({
                node: tnode,
                duration: time,
                delay: delay,
                properties: {
                    left: -finalx,
                    top: -finaly,
                },
                onEnd: dojo.partial(function (tnode) {
                    var animation = dojo.fadeOut({ duration: 250, node: tnode });
                    (animation.onEnd = dojo.partial(function (tnode) {
                        dojo.destroy(tnode);
                    }, tnode)),
                        animation.play();
                }, tnode),
            })
                .play();
        };
        EbgCoreGamegui.prototype.fadeOutAndDestroy = function (node, duration, delay) {
            var animation = dojo.fadeOut({
                duration: duration,
                delay: delay,
                node: node,
            });
            (animation.onEnd = dojo.partial(function (tnode) {
                dojo.destroy(tnode);
            }, node)),
                animation.play();
        };
        EbgCoreGamegui.prototype.placeOnObject = function (mobile_obj, target_obj) {
            return this.placeOnObjectPos(mobile_obj, target_obj, 0, 0);
        };
        EbgCoreGamegui.prototype.placeOnObjectPos = function (mobile_obj, target_obj, target_x, target_y) {
            var mobile = dojo.byId(mobile_obj);
            var computedStyle = style.getComputedStyle(mobile);
            var start = domGeom.position(mobile_obj);
            var stop = domGeom.position(target_obj);
            console.log(stop);
            var finalx = stop.x - start.x + stop.w / 2 - start.w / 2;
            var finaly = stop.y - start.y + stop.h / 2 - start.h / 2;
            var left = finalx + parseFloat(computedStyle.left.replace("px", "")) + target_x;
            var top = finaly + parseFloat(computedStyle.top.replace("px", "")) + target_y;
            dojo.style(mobile, {
                left: left + "px",
                top: top + "px",
            });
        };
        EbgCoreGamegui.prototype.attachToNewParent = function (mobile_obj, target_obj) {
            var mobile = dojo.byId(mobile_obj);
            var target = dojo.byId(target_obj);
            var start = domGeom.position(mobile);
            var stop = domGeom.position(target);
            var finalx = start.x - stop.x;
            var finaly = start.y - stop.y;
            dojo.place(mobile, target);
            dojo.style(mobile, {
                left: finalx + "px",
                top: finaly + "px",
            });
        };
        EbgCoreGamegui.prototype.addTooltipHtml = function (nodeId, html, delay) {
            if (delay === void 0) { delay = 400; }
            new Tooltip({
                connectId: [nodeId],
                label: html,
                showDelay: delay,
            });
        };
        EbgCoreGamegui.prototype.addTooltip = function (nodeId, helpString, actionString, delay) {
            if (delay === void 0) { delay = 400; }
            new Tooltip({
                connectId: [nodeId],
                label: helpString !== undefined
                    ? helpString
                    : "" + actionString !== undefined
                        ? actionString
                        : "",
                showDelay: delay,
            });
        };
        EbgCoreGamegui.prototype.addTooltipToClass = function (cssClass, helpString, actionString, delay) {
            if (delay === void 0) { delay = 400; }
            new Tooltip({
                connectId: dojo.query("." + cssClass),
                label: helpString !== undefined
                    ? helpString
                    : "" + actionString !== undefined
                        ? actionString
                        : "",
                showDelay: delay,
            });
        };
        EbgCoreGamegui.prototype.addTooltipHtmlToClass = function (cssClass, html, delay) {
            if (delay === void 0) { delay = 400; }
            new Tooltip({
                connectId: dojo.query("." + cssClass),
                label: html,
                showDelay: delay,
            });
        };
        EbgCoreGamegui.prototype.removeTooltip = function (nodeId) {
            console.log("not implemented : removeTooltip");
        };
        EbgCoreGamegui.prototype.checkAction = function (action_name, nomessage) {
            if (nomessage === void 0) { nomessage = false; }
            var ret = !this.lock &&
                this.bgg_states[this.bgg_stateId]["possibleactions"].includes(action_name);
            if (!ret && !nomessage) {
                alert("Impossible action at this state : " +
                    this.bgg_states[this.bgg_stateId]["name"]);
            }
            return ret;
        };
        EbgCoreGamegui.prototype.checkPossibleActions = function (action_name, nomessage) {
            if (nomessage === void 0) { nomessage = false; }
            var ret = !this.lock &&
                this.bgg_states[this.bgg_stateId]["possibleactions"].includes(action_name);
            if (!ret && !nomessage) {
                alert("Impossible action at this state : " +
                    this.bgg_states[this.bgg_stateId]["name"]);
            }
            return ret;
        };
        EbgCoreGamegui.prototype.isCurrentPlayerActive = function () {
            return this.isPlayerActive(this.player_id);
        };
        EbgCoreGamegui.prototype.isPlayerActive = function (player_id) {
            var active = this.active_player_id == player_id;
            return (this.active_player_id == player_id ||
                this.multiactive.includes(player_id));
        };
        EbgCoreGamegui.prototype.getActivePlayers = function () {
            return this.multiactive;
        };
        EbgCoreGamegui.prototype.getActivePlayerId = function () {
            return this.active_player_id;
        };
        EbgCoreGamegui.prototype.addActionButton = function (id, label, method, destination, blinking, color) {
            if (destination === void 0) { destination = null; }
            if (blinking === void 0) { blinking = false; }
            if (color === void 0) { color = "blue"; }
            var tcol = "primary";
            switch (color) {
                case "blue":
                    tcol = "primary";
                    break;
                case "red":
                    tcol = "alert";
                    break;
                case "gray":
                    tcol = "light";
                    break;
            }
            var tblink = "";
            if (blinking) {
                tblink = "bg_game_blink";
            }
            dojo.place(this.format_block("jstpl_bg_game_button", {
                id: id,
                color: tcol,
                label: label,
                blink: tblink,
            }), "bg_game_main_buttons");
            dojo.query("#" + id).connect("onclick", this, method);
        };
        EbgCoreGamegui.prototype.ajaxcall = function (url, parameters, obj_callback, callback, callback_error) {
            if (this.lock && parameters.lock) {
                alert("Action already in progress");
            }
            else {
                this.lock = parameters.lock;
                var actionName = url.match(/([a-zA-Z0-9_-]*?)\.html/)[1];
                parameters["bgg_player_id"] = this.player_id;
                parameters["bgg_actionName"] = actionName;
                this.socket.send(JSON.stringify(parameters));
            }
        };
        EbgCoreGamegui = __decorate([
            (0, declareDecorator_1.default)()
        ], EbgCoreGamegui);
        return EbgCoreGamegui;
    }());
    exports.EbgCoreGamegui = EbgCoreGamegui;
    ebg.core.gamegui = EbgCoreGamegui;
});

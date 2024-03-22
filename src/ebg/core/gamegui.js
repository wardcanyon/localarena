define([
  "dojo",
  "dojo/_base/declare",
  "dojo/dom-geometry",
  "dojo/fx",
  "dojo/dom-style",
  "dijit/Tooltip",
  "dojo/fx/easing",
  "ebg/core/notificationQueue",
], function (dojo, declare, domGeom, fx, style, Tooltip) {
  return declare("ebg.core.gamegui", null, {
    constructor: function () {
      this.lock = false;
      this.bgg_stateId = 0;
      this.replay = false;
      this.processingNotifications = false;
      this.notificationsBuffer = [];
      this.notifqueue = new ebg.core.notificationQueue();
      this.notifqueue.game = this;
    },

    setup: function (gamedatas) {
      console.log("setup parent");
    },

    onEnteringState: function (stateName, args) {
      console.log("onEnteringState parent");
    },

    completesetup: function (gamename, gamedatas) {
      this.socket = new WebSocket("ws://localhost:3000/" + this.player_id);

      this.socket.onopen = function (e) {};

      var gui = this;
      this.socket.onmessage = function (event) {
        var event = JSON.parse(event.data);
        gui.notifqueue.addEvent(event);
      };

      this.socket.onclose = function (event) {
        alert("[close] Connection close");
      };

      this.socket.onerror = function (error) {
        alert(`[error] ${error.message}`);
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
      this.addTooltipToClass(
        "bg_game_debug_user",
        _("Open new window with this user"),
      );
      this.addTooltipToClass("bg_game_thinking", _("Active player"));

      dojo.query(".socketButton").connect("onclick", this, "onSocketButton");

      this.notif_bg_onEnteringState({ args: gamedatas });
    },

    onUpdateActionButtons: function (stateName, args) {},
    onEnteringState: function (stateName, args) {},
    onLeavingState: function (stateName) {},

    onSocketButton(event) {
      dojo.stopEvent(event);
      var id = event.currentTarget.id;
      var parameters = { lock: false };
      parameters["bgg_player_id"] = this.player_id;
      parameters["bgg_actionName"] = id;
      this.socket.send(JSON.stringify(parameters));
    },

    notif_bg_onEnteringState: function (gamedatas) {
      gamedatas = gamedatas.args;
      this.bg_game_players = gamedatas.players;

      if (this.bgg_stateId > 0) {
        var state = this.bgg_states[this.bgg_stateId];
        this.onLeavingState(state["name"]);
      }
      dojo.empty("bg_game_main_buttons");
      this.active_player_id = gamedatas.active_player_id;
      this.multiactive = gamedatas.multiactive;
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
      } else {
        dojo.addClass("bg_game_thinking_top", "bg_game_hidden");
      }

      this.onUpdateActionButtons(state["name"], gamedatas.args);

      if (this.isCurrentPlayerActive()) {
        dojo.byId("pagemaintitletext").innerHTML = this.format_string_recursive(
          _(state["descriptionmyturn"]),
          gamedatas.args,
        );
      } else {
        dojo.byId("pagemaintitletext").innerHTML = this.format_string_recursive(
          _(state["description"]),
          gamedatas.args,
        );
      }

      this.onEnteringState(state["name"], state);
      this.lock = false;
    },

    notif_bg_LeavingState: function (gamedatas) {
      this.bgg_stateId = gamedatas.id;
      var state = this.bgg_states[this.bgg_stateId];
      this.onLeavingState(state["name"]);
    },

    confirmationDialog: function (label, func) {
      $("bg_game_confirm_text").innerHTML = label;
      jQuery("#bg_game_modal_confirm_button").off();
      jQuery("#bg_game_modal_confirm_button").on("click", func);
      jQuery("#exampleModalCenter").modal();
    },

    format_block: function (templateId, data) {
      var template = window[templateId];
      for (var key in data) {
        template = replaceAll(template, "${" + key + "}", data[key]);
      }
      return template;
    },

    /**
     * Messages
     */

    logAll: function (logs) {
      for (var key in logs) {
        var log = logs[key];
        this.addLog(JSON.parse(log.gamelog_notification));
      }
    },

    format_string_recursive: function (log, args) {
      if (log) {
        for (var key in args) {
          if (args[key]["log"] === undefined) {
            log = log.replace("${" + key + "}", args[key]);
          } else {
            var chg = this.format_string_recursive(
              args[key]["log"],
              args[key]["args"],
            );
            log = log.replace("${" + key + "}", chg);
          }
        }

        log = this.replacePlayerName(log);
        log = log.replace("${you}", this.divYou());
        log = log.replace("${actplayer}", this.divActPlayer());
      } else {
        log = "";
      }
      return log;
    },

    divYou: function () {
      var color = this.bg_game_players[this.player_id].player_color;
      var color_bg = "";
      if (
        this.gamedatas.players[this.player_id] &&
        this.bg_game_players[this.player_id].color_back
      ) {
        color_bg =
          "background-color:#" +
          this.bg_game_players[this.player_id].color_back +
          ";";
      }
      var you =
        '<span style="font-weight:bold;color:#' +
        color +
        ";" +
        color_bg +
        '">' +
        _("You") +
        "</span>";
      return you;
    },

    replacePlayerName: function (log) {
      for (var key in this.bg_game_players) {
        var player = this.bg_game_players[key];
        var color = player.player_color;
        var name = player.player_name;
        var color_bg = "";
        if (player.color_back) {
          color_bg = "background-color:#" + player.color_back + ";";
        }
        log = log.replace(
          name,
          '<span style="font-weight:bold;color:#' +
            color +
            ";" +
            color_bg +
            '">' +
            name +
            "</span>",
        );
      }

      return log;
    },
    divActPlayer: function () {
      var color = this.bg_game_players[this.active_player_id].player_color;
      var name = this.bg_game_players[this.active_player_id].player_name;
      var color_bg = "";
      if (
        this.gamedatas.players[this.player_id] &&
        this.bg_game_players[this.player_id].color_back
      ) {
        color_bg =
          "background-color:#" +
          this.bg_game_players[this.player_id].color_back +
          ";";
      }
      var you =
        '<span style="font-weight:bold;color:#' +
        color +
        ";" +
        color_bg +
        '">' +
        name +
        "</span>";
      return you;
    },

    addLog: function (event) {
      if (event.notification_log != "") {
        var raw = event.notification_log;
        raw = this.format_string_recursive(raw, event.args);
        dojo.place(
          this.format_block("jstpl_bg_game_message", {
            id: event.gamelog_id,
            content: raw,
          }),
          "bg_game_logs",
          "first",
        );

        dojo.query("#bg_game_message_" + event.gamelog_id).connect(
          "onclick",
          this,
          dojo.partial(function (id) {
            var action_id = id;
            this.confirmationDialog(
              _("Do you want to replay the game from this point?"),
              function () {
                var newurl =
                  window.location.href.split("?")[0] +
                  "?replayFrom=" +
                  action_id;
                const queryString = window.location.search;
                const urlParams = new URLSearchParams(queryString);
                if (urlParams.get("testplayer") != undefined) {
                  newurl += "&testplayer=" + urlParams.get("testplayer");
                }
                window.location.replace(newurl);
              },
            );
          }, event.gamelog_move_id),
        );
        this.addTooltip(
          "bg_game_message_" + event.gamelog_id,
          _("Click to replay the game from this point"),
        );
      }
    },

    /**
     * State
     */
    newState: function (gamedatas) {
      this.bgg_stateId = gamedatas.id;
      var state = this.bgg_states[this.bgg_stateId];
      state["id"] = this.bgg_stateId;
      state["args"] = gamedatas.args;
      state["active_player"] = gamedatas.active_player_id;
      state["multiactive"] = gamedatas.multiactive;

      if (this.isCurrentPlayerActive()) {
        $("pagemaintitletext").innerHTML = _(state["descriptionmyturn"]);
      } else {
        $("pagemaintitletext").innerHTML = _(state["description"]);
      }
      this.onEnteringState(state["name"], state);
    },

    isCurrentPlayerActive: function () {
      return true;
    },

    /**
     * Animations
     */

    slideToObject: function (
      mobile_obj,
      target_obj,
      duration = 500,
      delay = 0,
    ) {
      return this.slideToObjectPos(
        mobile_obj,
        target_obj,
        0,
        0,
        duration,
        delay,
      );
    },

    slideToObjectPos: function (
      mobile_obj,
      target_obj,
      target_x,
      target_y,
      duration = 500,
      delay = 0,
    ) {
      var mobile = dojo.byId(mobile_obj);
      var computedStyle = style.getComputedStyle(mobile);

      var start = domGeom.position(mobile_obj);
      var stop = domGeom.position(target_obj);
      var finalx = stop.x - start.x;
      var finaly = stop.y - start.y;

      var left =
        finalx + parseFloat(computedStyle.left.replace("px", "")) + target_x;
      var top =
        finaly + parseFloat(computedStyle.top.replace("px", "")) + target_y;

      return fx.slideTo({
        node: mobile_obj,
        duration: duration,
        delay: delay,
        left: left,
        top: top,
      });
    },

    slideTemporaryObject: function (
      mobile_obj_html,
      mobile_obj_parent,
      from,
      to,
      duration,
      delay,
    ) {
      console.log("not implemented : slideTemporaryObject");
    },

    slideToObjectAndDestroy: function (node, to, time, delay) {
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
    },

    fadeOutAndDestroy: function (node, duration, delay) {
      var animation = dojo.fadeOut({
        duration: duration,
        delay: delay,
        node: node,
      });
      (animation.onEnd = dojo.partial(function (tnode) {
        dojo.destroy(tnode);
      }, node)),
        animation.play();
    },

    /**
     * MOVING
     */

    placeOnObject: function (mobile_obj, target_obj) {
      return this.placeOnObjectPos(mobile_obj, target_obj, 0, 0);
    },

    placeOnObjectPos: function (mobile_obj, target_obj, target_x, target_y) {
      var mobile = dojo.byId(mobile_obj);
      var computedStyle = style.getComputedStyle(mobile);

      var start = domGeom.position(mobile_obj);
      var stop = domGeom.position(target_obj);
      console.log(stop);

      var finalx = stop.x - start.x + stop.w / 2 - start.w / 2;
      var finaly = stop.y - start.y + stop.h / 2 - start.h / 2;

      var left =
        finalx + parseFloat(computedStyle.left.replace("px", "")) + target_x;
      var top =
        finaly + parseFloat(computedStyle.top.replace("px", "")) + target_y;
      dojo.style(mobile, {
        left: left + "px",
        top: top + "px",
      });
    },

    attachToNewParent: function (mobile_obj, target_obj) {
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
    },

    /**
     * Tooltips
     */

    addTooltipHtml: function (nodeId, html, delay = 400) {
      new Tooltip({
        connectId: [nodeId],
        label: html,
        showDelay: delay,
      });
    },

    addTooltip: function (nodeId, helpString, actionString, delay = 400) {
      new Tooltip({
        connectId: [nodeId],
        label:
          helpString !== undefined
            ? helpString
            : "" + actionString !== undefined
              ? actionString
              : "",
        showDelay: delay,
      });
    },

    addTooltipToClass: function (
      cssClass,
      helpString,
      actionString,
      delay = 400,
    ) {
      new Tooltip({
        connectId: dojo.query("." + cssClass),
        label:
          helpString !== undefined
            ? helpString
            : "" + actionString !== undefined
              ? actionString
              : "",
        showDelay: delay,
      });
    },

    addTooltipHtmlToClass: function (cssClass, html, delay = 400) {
      new Tooltip({
        connectId: dojo.query("." + cssClass),
        label: html,
        showDelay: delay,
      });
    },

    removeTooltip(nodeId) {
      console.log("not implemented : removeTooltip");
    },

    /**
     * GameState
     */
    checkAction(action_name, nomessage = false) {
      var ret =
        !this.lock &&
        this.bgg_states[this.bgg_stateId]["possibleactions"].includes(
          action_name,
        );
      if (!ret && !nomessage) {
        alert(
          "Impossible action at this state : " +
            this.bgg_states[this.bgg_stateId]["name"],
        );
      }
      return ret;
    },

    checkPossibleActions(action_name, nomessage = false) {
      var ret =
        !this.lock &&
        this.bgg_states[this.bgg_stateId]["possibleactions"].includes(
          action_name,
        );
      if (!ret && !nomessage) {
        alert(
          "Impossible action at this state : " +
            this.bgg_states[this.bgg_stateId]["name"],
        );
      }
      return ret;
    },

    isCurrentPlayerActive() {
      return this.isPlayerActive(this.player_id);
    },

    isPlayerActive(player_id) {
      var active = this.active_player_id == player_id;
      return (
        this.active_player_id == player_id ||
        this.multiactive.includes(player_id)
      );
    },

    getActivePlayers() {
      return this.multiactive;
    },

    getActivePlayerId() {
      return this.active_player_id;
    },

    addActionButton(
      id,
      label,
      method,
      destination = null,
      blinking = false,
      color = "blue",
    ) {
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

      dojo.place(
        this.format_block("jstpl_bg_game_button", {
          id: id,
          color: tcol,
          label: label,
          blink: tblink,
        }),
        "bg_game_main_buttons",
      );
      dojo.query("#" + id).connect("onclick", this, method);
    },

    /**
     * Communication
     */
    ajaxcall(url, parameters, obj_callback, callback, callback_error) {
      if (this.lock && parameters.lock) {
        alert("Action already in progress");
      } else {
        this.lock = parameters.lock;
        var actionName = url.match(/([a-zA-Z0-9_-]*?)\.html/)[1];
        parameters["bgg_player_id"] = this.player_id;
        parameters["bgg_actionName"] = actionName;
        this.socket.send(JSON.stringify(parameters));
      }
    },
  });
});

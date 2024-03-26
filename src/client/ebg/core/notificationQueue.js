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
        define(["require", "exports", "../declareDecorator"], factory);
    }
})(function (require, exports) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.EbgCoreNotificationQueue = void 0;
    var declareDecorator_1 = require("../declareDecorator");
    var EbgCoreNotificationQueue = (function () {
        function EbgCoreNotificationQueue(game) {
            this.game = null;
            this.processingNotifications = false;
            this.notificationsBuffer = [];
            this.notificationDelay = [];
            this.lastMoveId = -1;
            this.game = game;
        }
        EbgCoreNotificationQueue.prototype.addEvent = function (event) {
            this.notificationsBuffer.push(event);
            if (!this.game.replay) {
                this.processNotif();
            }
        };
        EbgCoreNotificationQueue.prototype.processNotif = function () {
            if (!this.processingNotifications &&
                this.notificationsBuffer.length > 0) {
                this.processingNotifications = true;
                var event = this.notificationsBuffer.shift();
                var delayBefore = 0;
                if (this.game.replay && this.lastMoveId != event.gamelog_move_id) {
                    delayBefore = 1500;
                }
                this.lastMoveId = event.gamelog_move_id;
                setTimeout(dojo.hitch(this, function () {
                    this.game.addLog(event);
                    dojo.publish(event.notification_type, event);
                    var delay = 0;
                    if (event.notification_type in this.notificationDelay) {
                        delay = this.notificationDelay[event.notification_type];
                    }
                    setTimeout(dojo.hitch(this, function () {
                        this.processingNotifications = false;
                        this.processNotif();
                    }), delay);
                }), delayBefore);
            }
            if (this.notificationsBuffer.length == 0) {
                this.game.replay = false;
            }
        };
        EbgCoreNotificationQueue.prototype.setSynchronous = function (notif, delay) {
            this.notificationDelay[notif] = delay;
        };
        EbgCoreNotificationQueue = __decorate([
            (0, declareDecorator_1.default)()
        ], EbgCoreNotificationQueue);
        return EbgCoreNotificationQueue;
    }());
    exports.EbgCoreNotificationQueue = EbgCoreNotificationQueue;
    ebg.core.notificationQueue = EbgCoreNotificationQueue;
});

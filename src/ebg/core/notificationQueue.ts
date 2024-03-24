import declare from '../declareDecorator';

import { EbgCoreGamegui } from 'ebg/core/gamegui';

@declare()
export class EbgCoreNotificationQueue {
    game: EbgCoreGamegui = null;
    processingNotifications: boolean = false;
    notificationsBuffer = [];
    notificationDelay = [];
    lastMoveId: number = -1;

    constructor(game?: EbgCoreGamegui) {
      this.game = game;
    }

    addEvent(event) {
      this.notificationsBuffer.push(event);
      if (!this.game.replay) {
        this.processNotif();
      }
    }

    processNotif() {
      if (
        !this.processingNotifications &&
        this.notificationsBuffer.length > 0
      ) {
        this.processingNotifications = true;
        var event = this.notificationsBuffer.shift();
        var delayBefore = 0;
        if (this.game.replay && this.lastMoveId != event.gamelog_move_id) {
          delayBefore = 1500;
        }
        this.lastMoveId = event.gamelog_move_id;

        setTimeout(
          dojo.hitch(this, function () {
            this.game.addLog(event);
            dojo.publish(event.notification_type, event);
            var delay = 0;
            if (event.notification_type in this.notificationDelay) {
              delay = this.notificationDelay[event.notification_type];
            }
            setTimeout(
              dojo.hitch(this, function () {
                this.processingNotifications = false;
                this.processNotif();
              }),
              delay,
            );
          }),
          delayBefore,
        );
      }

      if (this.notificationsBuffer.length == 0) {
        this.game.replay = false;
      }
    }

    setSynchronous(notif, delay) {
      this.notificationDelay[notif] = delay;
    }
}

console.log('**notifqueue class:');
console.log(EbgCoreNotificationQueue);
console.log(EbgCoreNotificationQueue.constructor);

ebg.core.notificationQueue = EbgCoreNotificationQueue.constructor;

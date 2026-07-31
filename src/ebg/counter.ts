import declare from "./declareDecorator";

@declare()
export default class EbgCounter {
  el: HTMLElement = null;

  // The logical value of the counter.
  value: number = 0;

  // This differs from `value` only when the counter is being animated.
  displayedValue: number = 0;

  speed: number = 100;

  // The name of the server-side counter this counter follows, if it
  // was created with a `playerCounter` or `tableCounter` option; null
  // otherwise.  See `module/table/LocalArenaCounters.php`.
  counterName: string = null;

  // True if `counterName` names a PlayerCounter (rather than a
  // TableCounter).
  counterIsPlayerCounter: boolean = false;

  // For a player counter, the player whose value this counter shows:
  // a player id, or a player no if the server-side counter is keyed
  // that way (see `PlayerCounter::setUseNo()`).  Null means "any
  // player", which is only useful for a counter that a single player
  // owns.
  counterPlayerId: number = null;

  // Associates the counter with the given (already existing) DOM
  // element.
  //
  // With `options.playerCounter` or `options.tableCounter` -- the
  // name of a server-side counter -- the counter keeps itself up to
  // date from that counter's notifications, so a game never has to
  // update it by hand:
  //
  //     counter.create(`energy-player-counter-${player.id}`,
  //                    { value: player.energy, playerCounter: 'energy', playerId: player.id });
  //
  //     counter.create(`remaining-tokens-counter`,
  //                    { value: gamedatas.remainingTokens, tableCounter: 'remainingTokens' });
  //
  // `options.value` is the starting value; a null starting value (as
  // published for a counter whose value this player may not see)
  // displays "-" instead.
  create(containerId: string, options?: CounterCreateOptions): void {
    this.el = $(containerId);

    if (options === undefined || options === null) {
      return;
    }

    if (options.playerCounter !== undefined && options.tableCounter !== undefined) {
      console.error(
        "A counter may follow a player counter or a table counter, but not both: " + containerId,
      );
    }

    if (options.playerCounter !== undefined) {
      this.counterName = options.playerCounter;
      this.counterIsPlayerCounter = true;
      this.counterPlayerId =
        options.playerId === undefined || options.playerId === null
          ? null
          : parseInt("" + options.playerId);
      dojo.subscribe("setPlayerCounter", this, "onSetPlayerCounter");
      dojo.subscribe("setPlayerCounterAll", this, "onSetPlayerCounterAll");
    } else if (options.tableCounter !== undefined) {
      this.counterName = options.tableCounter;
      dojo.subscribe("setTableCounter", this, "onSetTableCounter");
    }

    if (options.value !== undefined) {
      if (options.value === null) {
        this.disable();
      } else {
        this.setValue(options.value);
      }
    }
  }

  getValue(): number {
    return this.value;
  }

  incValue(delta: number): void {
    this.toValue(this.value + delta);
  }

  setValue(value: number): void {
    this.value = value;
    this.el.innerHTML = "" + this.value;
  }

  // Like `setValue()`, but animates the counter.
  toValue(value: number): void {
    // XXX: For the moment, no animation is supported.
    this.setValue(value);
  }

  disable(): void {
    this.el.innerHTML = "-";
  }

  // ---- Following a server-side counter ----

  onSetPlayerCounter(notif): void {
    const args = notif.args;
    if (!this.followsPlayerCounter(args.name, args.playerId)) {
      return;
    }
    this.toValue(parseInt("" + args.value));
  }

  onSetPlayerCounterAll(notif): void {
    const args = notif.args;
    // Every player's value changed, so this one's did too.
    if (!this.followsPlayerCounter(args.name, null)) {
      return;
    }
    this.toValue(parseInt("" + args.value));
  }

  onSetTableCounter(notif): void {
    const args = notif.args;
    if (this.counterIsPlayerCounter || args.name !== this.counterName) {
      return;
    }
    this.toValue(parseInt("" + args.value));
  }

  // Whether an update of `name` for `playerId` (null: for every
  // player) is an update of what this counter displays.
  private followsPlayerCounter(name: string, playerId): boolean {
    if (!this.counterIsPlayerCounter || name !== this.counterName) {
      return false;
    }
    if (playerId === undefined || playerId === null || this.counterPlayerId === null) {
      return true;
    }
    return parseInt("" + playerId) === this.counterPlayerId;
  }
}

ebg.counter = EbgCounter;

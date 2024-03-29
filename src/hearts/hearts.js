/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Hearts implementation fixes: © ufm <tel2tale@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * hearts.js
 *
 * Hearts user interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */
////////////////////////////////////////////////////////////////////////////////

/*
    In this file, you are describing the logic of your user interface, in Javascript language.
*/

/**
 * Note: this code is modified to add suggestions from BGA players and popular variants.
 * Please visit here to read the basic code used in the BGA Studio tutorial: https://github.com/elaskavaia/
 */

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock"
],
function (dojo, declare) {
    return declare("bgagame.hearts", ebg.core.gamegui, {
        constructor: function(){
            console.log('hearts constructor');

            // Here, you can init the global variables of your user interface
            // Example:
            // this.myGlobalValue = 0;
            this.cardwidth = 72;
            this.cardheight = 96;
            this.score_counter = {};
        },

        /*
            setup:

            This method must set up the game user interface according to current game situation specified
            in parameter.

            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refresh the game page (F5)

            "gamedatas" argument contains all data retrieved by your "getAllDatas" PHP method.
        */

        setup: function (gamedatas) {
            // Create score counters
            for (let player_id in gamedatas.players) {
                const player = gamedatas.players[player_id];
                if (gamedatas.track_information > 0) {
                    this.score_counter[player_id] = new ebg.counter();
                    this.score_counter[player_id].create('hand_score_' + player_id);
                    this.score_counter[player_id].setValue(player.hand_score);
                } else dojo.destroy('hand_score_wrap_' + player_id);
            }

            // Dealer token in Black Maria variant
            if (gamedatas.no_starter_card > 0) document.getElementById('dealer_token_p' + gamedatas.dealer).classList.add('show_dealer');
            this.addTooltipToClass('dealer_token', _('This player is the dealer of this round.<br>The next player starts this round and will be the next dealer.'), '');

            // Hide hand zone from spectators
            if (this.isSpectator) document.getElementById("myhand_wrap").style.display = 'none';

            // Skip loading audio files when the option is disabled
            if (this.prefs[103].value != 1)
                gamedatas.audio_list.forEach(s => {
                    this.dontPreloadImage(this.game_name + '_' + s + '.mp3');
                    this.dontPreloadImage(this.game_name + '_' + s + '.ogg');
                });

            // Skip loading card images when the option is disabled
            for (let i = 1; i <= 4; i++) if (this.prefs[100].value != i) this.dontPreloadImage('cards_' + i + '.png');

            // Change card size according to the sprite type
            if (this.prefs[100].value > 1) {
                this.cardwidth = 84;
                this.cardheight = 117;
                document.querySelectorAll('.playertablecard').forEach(e => e.classList.add('larger_card'));
                document.querySelectorAll('.playertable').forEach(e => e.classList.add('larger_table'));
                document.getElementById("game_board").classList.add('larger_board');
            }

            // Player hand
            this.playerHand = new ebg.stock();
            this.playerHand.create(this, $('myhand'), this.cardwidth, this.cardheight);
            this.playerHand.extraClasses = 'stock_card_border card_' + this.prefs[100].value; // Add background-size modification to fix Safari bug
            this.playerHand.centerItems = true;
            this.playerHand.image_items_per_row = 13;
            this.playerHand.apparenceBorderWidth = '2px'; // Change border width when selected
            this.playerHand.setSelectionMode(1); // Select only a single card
            if (this.prefs[101].value != 1) {
                // Card overlap preference option
                this.playerHand.horizontal_overlap = 28;
                this.playerHand.item_margin = 0;
            }
            dojo.connect(this.playerHand, 'onChangeSelection', this, 'onHandCardSelect');

            // Create cards types:
            for (let color = 1; color <= 4; color++)
                for (let value = 2; value <= 14; value++) {
                    // Build card type id
                    const card_type_id = this.getCardUniqueId(color, value);
                    // Change card image style according to the preference option
                    this.playerHand.addItemType(card_type_id, card_type_id, g_gamethemeurl + 'img/cards_' + this.prefs[100].value + '.png', card_type_id);
                }

            // Cards in player's hand
            for (let i in gamedatas.hand) {
                const card = gamedatas.hand[i];
                const color = card.type;
                const value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }

            // Cards played on table
            for (let i in gamedatas.cardsontable) {
                const card = gamedatas.cardsontable[i];
                const color = card.type;
                const value = card.type_arg;
                const player_id = card.location_arg;
                dojo.place(this.format_block('jstpl_card', {
                    x: value - 2,
                    y: color - 1,
                    player_id: player_id,
                    card_style: this.prefs[100].value,
                }), 'playertablecard_' + player_id);
            }

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();
        },


        ///////////////////////////////////////////////////
        //// Game & client states

        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //

        onEnteringState: function (stateName, args) {
            console.log( 'Entering state: '+stateName );
            switch (stateName) {
                case 'playerTurn':
                    if (this.isCurrentPlayerActive()) {
                        // Check playable cards received from argPlayerTurn() in php
                        const playable_cards = args.args._private.playableCards;
                        const all_cards = this.playerHand.getAllItems();
                        for (let i in all_cards)
                            if (!playable_cards.includes(all_cards[i].id)) {
                                // Mark unplayable cards
                                if (this.prefs[104].value == 1) document.getElementById('myhand_item_' + all_cards[i].id).classList.add('unplayable');
                                else document.getElementById('myhand_item_' + all_cards[i].id).classList.add('unplayable', 'unplayable_transparent');
                            }
                        if (this.prefs[102].value == 1) {
                            // Play the preselected card when the confirmation option is off
                            const selected_cards = this.playerHand.getSelectedItems();
                            if (selected_cards.length === 1) {
                                const card_id = selected_cards[0].id;
                                if (playable_cards.includes(card_id)) this.onBtnPlayCard();
                                else this.playerHand.unselectAll();
                            }
                        }
                    }
                    break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function (stateName) {
            console.log( 'Leaving state: '+stateName );
            switch (stateName) {
                case 'playerTurn':
                    // Reset unplayable card marking
                    document.querySelectorAll('.unplayable').forEach(e => e.classList.remove('unplayable', 'unplayable_transparent'));
                    break;
                case 'giveCards':
                    this.playerHand.setSelectionMode(1); // Select only a single card
                    break;
            }
        },

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //
        onUpdateActionButtons: function (stateName, args) {
            console.log( 'onUpdateActionButtons: '+stateName );
            if (this.isCurrentPlayerActive()) {
                switch (stateName) {
                    case 'playerTurn':
                        if (this.prefs[102].value == 2) this.addActionButton('btnPlayCard', _('Play card'), 'onBtnPlayCard'); // Confirmation mode
                        break;
                    case 'giveCards':
                        this.playerHand.setSelectionMode(2); // Let players select multiple cards if active
                        this.addActionButton('giveCards_button', _('Give selected cards'), 'onGiveCards');
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

        // Color player names in the log
        coloredPlayerName: function (name) {
            const player = Object.values(this.gamedatas.players).find((player) => player.name == name);
            if (player == undefined) return '<!--PNS--><span class="playername">' + name + '</span><!--PNE-->';

            const color = player.color;
            const color_bg = player.color_back ? 'background-color:#' + this.gamedatas.players[this.player_id].color_back + ';' : '';
            return (
                '<!--PNS--><span class="playername" style="color:#' + color + ';' + color_bg + '">' + name + '</span><!--PNE-->'
            );
        },

        format_string_recursive: function (log, args) {
            try {
                if (log && args) {
                    let player_keys = Object.keys(args).filter((key) => key.substr(0, 11) == 'player_name');
                    player_keys.forEach((key) => {
                        args[key] = this.coloredPlayerName(args[key]);
                    });
                }
            } catch (e) {
                console.error(log, args, 'Exception thrown', e.stack);
            }

            return this.inherited(arguments);
        },

        // Get card unique identifier based on its color and value
        getCardUniqueId: function (color, value) {
            return (color - 1) * 13 + (value - 2);
        },

        // Calculate card points
        calculateCardPoints: function (color, value, face_value_scoring, spades_scoring, jack_of_diamonds) {
            // Face value scoring: Spot Hearts, Spades scoring: Black Maria
            let score = 0;
            switch (Number(color)) {
                case 1: // Spades
                    switch (Number(value)) {
                        case 12: // Queen
                            score = face_value_scoring == 1 ? -25 : -13;
                            break;
                        case 13: // King (Black Maria variant)
                            score = spades_scoring == 1 ? (face_value_scoring == 1 ? -20 : -10) : 0;
                            break;
                        case 14: // Ace (Black Maria variant)
                            score = spades_scoring == 1 ? (face_value_scoring == 1 ? -15 : -7) : 0;
                            break;
                    }
                    break;
                case 2: // Heart
                    score = face_value_scoring == 1 ? -value : -1;
                    break;
                case 4: // Jack of Diamonds variant
                    if (value == 11 && jack_of_diamonds == 1) score = face_value_scoring == 1 ? 20 : 10;
                    break;
            }
            return score;
        },

        ///////////////////////////////////////////////////
        //// Player's action

        /*

            Here, you are defining methods to handle player's action (ex: results of mouse click on
            game objects).

            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server

        */

        onHandCardSelect: function (control_name, item_id) {
            // Do not trigger any action when it's not the player's turn!
            if (!this.isCurrentPlayerActive()) return;

            // Check the number of cards
            const selected_cards = this.playerHand.getSelectedItems();
            if (selected_cards.length === 1) {
                const card_id = selected_cards[0].id;
                if (this.prefs[102].value == 1) { // No confirmation
                    if (this.gamedatas.gamestate.name === 'playerTurn') {
                        const action = "playCard";
                        if (!this.checkAction(action)) return;

                        // Check whether the card is playable or not
                        this.playerHand.unselectAll();
                        if (document.getElementById('myhand_item_' + card_id).classList.contains('unplayable')) return;

                        // Play the card
                        this.ajaxcall("/" + this.game_name + "/" +  this.game_name + "/" + action + ".html", {lock: true, card_id: card_id}, this, function (result) {}, function (is_error) {});
                    }
                } else if (document.getElementById('myhand_item_' + card_id).classList.contains('unplayable'))
                    this.playerHand.unselectAll(); // Unselect unplayable cards
            }
        },

        onBtnPlayCard: function () {
            const action = "playCard";
            if (!this.checkAction(action)) return;

            // Check the number of selected items
            const selected_cards = this.playerHand.getSelectedItems();
            if (selected_cards.length !== 1) {
                this.showMessage(_('Please select a card'), "error");
                return;
            }

            // Check the playability of the card
            const card_id = selected_cards[0].id;
            if (document.getElementById('myhand_item_' + card_id).classList.contains('unplayable')) {
                this.showMessage(_('You cannot play this card now'), "error");
                this.playerHand.unselectAll();
                return;
            }

            // Play the card
            this.playerHand.unselectAll();
            this.ajaxcall("/" + this.game_name + "/" +  this.game_name + "/" + action + ".html", {lock: true, card_id: card_id}, this, function (result) {}, function (is_error) {});
        },

        onGiveCards: function() {
            const action = "giveCards";
            if (!this.checkAction(action)) return;

            // Check the number of selected items
            const selected_cards = this.playerHand.getSelectedItems();
            if (selected_cards.length !== 3) {
                this.showMessage(_('You must select exactly 3 cards'), "error");
                return;
            }

            // Get card ids
            let card_ids = '';
            for (let i in selected_cards) card_ids += selected_cards[i].id + ';';

            // Give selected cards
            this.playerHand.unselectAll();
            this.ajaxcall("/" + this.game_name + "/" +  this.game_name + "/" + action + ".html", {lock: true, card_ids: card_ids}, this, function (result) {}, function (is_error) {});
        },

        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:

            In this method, you associate each of your game notifications with your local method to handle it.

            Note: game notification names correspond to your "notifyAllPlayers" and "notifyPlayer" calls in
                  your emptygame.game.php file.

        */

        setupNotifications: function() {
            console.log('notifications subscriptions setup');
            const notif_list = ['newRound', 'newHand', 'playCard', 'giveAllCardsToPlayer', 'newScores', 'giveCards', 'takeCards', 'earlyEnd', 'noSound'];
            notif_list.forEach(s => dojo.subscribe(s, this, 'notif_' + s));
            this.notifqueue.setSynchronous('newRound', 1000);
            this.notifqueue.setSynchronous('playCard', 100);
            this.notifqueue.setSynchronous('giveAllCardsToPlayer', 600);
            this.notifqueue.setSynchronous('giveCards', 500);
            this.notifqueue.setSynchronous('takeCards', 500);
            this.notifqueue.setSynchronous('earlyEnd');
        },

        // TODO: from this point and below, you can write your game notifications handling methods
        notif_newRound: function (notif) {
            if (this.gamedatas.no_starter_card > 0) {
                // Change the dealer in Black Maria variant
                document.querySelectorAll('.show_dealer').forEach(e => e.classList.remove('show_dealer'));
                document.getElementById('dealer_token_p' + notif.args.new_dealer).classList.add('show_dealer');
            }

            if (this.gamedatas.track_information > 0) // Reset captured card scores if the display option is on
                for (let player_id in this.gamedatas.players) this.score_counter[player_id].toValue(0);

            // Custom sound effect
            if (this.prefs[103].value == 1 && !g_archive_mode) {
                playSound(this.game_name + '_shuffle');
                this.disableNextMoveSound();
            }
        },

        notif_newHand: function (notif) {
            // Remove existing cards and add new cards
            this.playerHand.removeAll();
            for (let i in notif.args.cards) {
                const card = notif.args.cards[i];
                const color = card.type;
                const value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }

            // Remove notification sound
            if (this.prefs[103].value == 1 && !g_archive_mode) this.disableNextMoveSound();
        },

        notif_playCard: function (notif) {
            // Play a card on the table
            const player_id = notif.args.player_id;
            const color = notif.args.card.type;
            const value = notif.args.card.type_arg;
            const card_id = notif.args.card.id;

            // player_id => direction
            dojo.place(this.format_block('jstpl_card', {
                x: value - 2,
                y: color - 1,
                player_id: player_id,
                card_style: this.prefs[100].value,
            }), 'playertablecard_' + player_id);

            if (player_id != this.player_id) {
                // Some opponent played a card
                // Move card from player panel
                this.placeOnObject('cardontable_' + player_id, 'overall_player_board_' + player_id);
            } else {
                // You played a card. If it exists in your hand, move card from there and remove corresponding item
                if ($('myhand_item_' + card_id)) {
                    this.placeOnObject('cardontable_' + player_id, 'myhand_item_' + card_id);
                    this.playerHand.removeFromStockById(card_id);
                }
            }

            // In any case: move it to its final destination
            this.slideToObject('cardontable_' + player_id, 'playertablecard_' + player_id).play();

            // Custom sound effect - change the sound based on the card and variants
            if (this.prefs[103].value == 1 && !g_archive_mode) {
                let sound_name = (notif.args.heartbreak && this.gamedatas.point_limit_variant < 2) ? 'break' : 'play'; // First Heart played?
                if (notif.args.card.type == 1 && notif.args.card.type_arg == 12 && this.gamedatas.face_value_scoring == 0 && this.gamedatas.spades_scoring == 0) sound_name = 'queen'; // Queen of Spades?
                else if (notif.args.card.type == 4 && notif.args.card.type_arg == 11 && this.gamedatas.jack_of_diamonds > 0) sound_name = 'jack'; // Jack of Diamonds bonus?
                playSound(this.game_name + '_' + sound_name);
                this.disableNextMoveSound();
            }
        },

        notif_giveAllCardsToPlayer: function (notif) {
            // Move all cards on table to given table, then destroy them
            document.querySelectorAll('.cardontable').forEach(e => this.slideToObjectAndDestroy(e.id, 'playertablecard_' + notif.args.player_id));

            // Track information if the option is on
            if (this.gamedatas.track_information > 0) {
                let face_value_scoring = this.gamedatas.face_value_scoring;
                let spades_scoring = this.gamedatas.spades_scoring;
                let jack_of_diamonds = this.gamedatas.jack_of_diamonds;
                let score = 0;
                for (let i in notif.args.cards) {
                    const card = notif.args.cards[i];
                    const color = card.type;
                    const value = card.type_arg;
                    score += this.calculateCardPoints(color, value, face_value_scoring, spades_scoring, jack_of_diamonds);
                }
                this.score_counter[notif.args.player_id].incValue(score);
            }

            // Custom sound effect
            if (this.prefs[103].value == 1 && !g_archive_mode) {
                playSound(this.game_name + '_take');
                this.disableNextMoveSound();
            }
        },

        notif_newScores: function (notif) {
            // Update players' scores
            for (let player_id in notif.args.newScores) this.scoreCtrl[player_id].toValue(notif.args.newScores[player_id]);

            // Remove notification sound
            if (this.prefs[103].value == 1 && !g_archive_mode) this.disableNextMoveSound();
        },

        notif_giveCards: function (notif) {
            // Remove cards from the hand (they have been given)
            for (let i in notif.args.cards) {
                const card_id = notif.args.cards[i];
                this.playerHand.removeFromStockById(card_id);
            }

            // Custom sound effect
            if (this.prefs[103].value == 1 && !g_archive_mode) {
                playSound(this.game_name + '_give');
                this.disableNextMoveSound();
            }
        },

        notif_takeCards: function (notif) {
            // Cards taken from some opponent
            for (let i in notif.args.cards) {
                const card = notif.args.cards[i];
                const color = card.type;
                const value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }

            // Custom sound effect
            if (this.prefs[103].value == 1 && !g_archive_mode) {
                playSound(this.game_name + '_give');
                this.disableNextMoveSound();
            }
        },

        notif_earlyEnd: function (notif) {
            if (Object.keys(notif.args.winning_hand).length === 0) {
                // Create all remaining cards and move to the center - all point cards have been played
                this.notifqueue.setSynchronousDuration(1000);
                for (let i in notif.args.remaining_cards) {
                    const card = notif.args.remaining_cards[i];
                    const color = card.type;
                    const value = card.type_arg;
                    if (card.location_arg != this.player_id) {
                        dojo.place(this.format_block('jstpl_card', {
                            x: value - 2,
                            y: color - 1,
                            player_id: card.location_arg + '_' + card.id,
                            card_style: this.prefs[100].value,
                        }), 'playertablecard_' + card.location_arg);
                    }
                }
                if (!this.isSpectator) this.playerHand.removeAllTo('game_board');
                document.querySelectorAll('.cardontable').forEach(e => this.slideToObjectAndDestroy(e.id, 'game_board'));
            } else {
                // Create and then move cards of the winner - a player captures all remaining tricks
                this.notifqueue.setSynchronousDuration((Object.keys(notif.args.winning_hand).length + 1) * 750);
                let face_value_scoring = this.gamedatas.face_value_scoring;
                let spades_scoring = this.gamedatas.spades_scoring;
                let jack_of_diamonds = this.gamedatas.jack_of_diamonds;
                let score = 0;
                for (let i in notif.args.remaining_cards) {
                    const card = notif.args.remaining_cards[i];
                    const color = card.type;
                    const value = card.type_arg;
                    score += this.calculateCardPoints(color, value, face_value_scoring, spades_scoring, jack_of_diamonds);
                }
                for (let i in notif.args.winning_hand) {
                    const card = notif.args.winning_hand[i];
                    const color = card.type;
                    const value = card.type_arg;
                    setTimeout(() => {
                        if (notif.args.player_id == this.player_id) this.playerHand.removeFromStockById(card.id, 'playertablecard_' + notif.args.player_id);
                        else {
                            dojo.place(this.format_block('jstpl_card', {
                                x: value - 2,
                                y: color - 1,
                                player_id: card.id,
                                card_style: this.prefs[100].value,
                            }), 'playertablecard_' + notif.args.player_id);
                            this.placeOnObject('cardontable_' + card.id, 'overall_player_board_' + notif.args.player_id);
                            this.slideToObject('cardontable_' + card.id, 'playertablecard_' + notif.args.player_id, 500).play();
                            setTimeout(() => this.fadeOutAndDestroy('cardontable_' + card.id), 500);
                        }
                        if (this.prefs[102].value == 1 && !g_archive_mode) playSound(this.game_name + '_play');
                    }, i * 750);
                }

                // Track information if the option is on
                if (this.gamedatas.track_information > 0 && notif.args.player_id !== null) this.score_counter[notif.args.player_id].incValue(score);
            }

            // Custom sound effect
            if (this.prefs[103].value == 1 && !g_archive_mode) {
                playSound(this.game_name + '_take');
                this.disableNextMoveSound();
            }
        },

        notif_noSound: function (notif) {
            // We do nothing here (texts only)
            // Remove notification sound
            if (this.prefs[103].value == 1 && !g_archive_mode) this.disableNextMoveSound();
        },
   });
});

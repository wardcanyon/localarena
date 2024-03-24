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
        define(["require", "exports", "dojo/dom-geometry", "dojo/dom-style", "./declareDecorator"], factory);
    }
})(function (require, exports) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    var domGeom = require("dojo/dom-geometry");
    var style = require("dojo/dom-style");
    var declareDecorator_1 = require("./declareDecorator");
    var EbgStock = (function () {
        function EbgStock() {
            this.page = null;
            this.container_div = null;
            this.item_height = null;
            this.item_width = null;
            this.item_margin = 5;
            this.centerItems = false;
            this.onChangeSelection = null;
            this.onItemCreate = null;
            this.image_items_per_row = 1;
            this.itemTypes = [];
            this.items = [];
            this.toBeDelete = [];
            this.selectionMode = 0;
            this.selectionAppearance = "border";
            this.horizontalOverlap = 0;
            this.verticalOverlap = 0;
        }
        EbgStock.prototype.create = function (page, container_div, item_width, item_height) {
            this.page = page;
            this.container_div = container_div;
            this.item_width = item_width;
            this.item_height = item_height;
            dojo.byId(this.container_div).style =
                "position: relative; height: 324px;";
            dojo.connect(window, "onresize", this, "resetItemsPosition");
        };
        EbgStock.prototype.count = function () {
            return this.items.length;
        };
        EbgStock.prototype.addItemType = function (type, weight, image, image_position) {
            this.itemTypes[type] = {
                type: type,
                weight: weight,
                image: image,
                image_position: image_position,
            };
        };
        EbgStock.prototype.addToStock = function (type, from) {
            this.addToStockWithId(type, type, from);
        };
        EbgStock.prototype.addToStockWithId = function (type, id, from) {
            if (from === void 0) { from = null; }
            var item = this.itemTypes[type];
            var backx = -(item.image_position % this.image_items_per_row) * this.item_width;
            var backy = -Math.floor(item.image_position / this.image_items_per_row) *
                this.item_height;
            this.items.push({ id: id, type: type });
            var propComparator = function (types) { return function (a, b) {
                return types[a.type].weight - types[b.type].weight;
            }; };
            this.items.sort(propComparator(this.itemTypes));
            var index = 0;
            for (var idx in this.items) {
                if (this.items[idx].id == id) {
                    break;
                }
                index++;
            }
            var position = this.getPosition(index);
            console.log(position);
            var template = '<div id="' +
                this.container_div.id +
                "_item_" +
                id +
                '" class="stockitem" style="opacity:0; top: ' +
                position.top +
                "px; left: " +
                position.left +
                "px; width: " +
                this.item_width +
                "px; height: " +
                this.item_height +
                "px; background-image: url(" +
                item.image +
                "); background-position: " +
                backx +
                "px " +
                backy +
                'px; "></div>';
            dojo.place(template, this.container_div);
            if (this.selectionMode != 0) {
                $(this.container_div.id + "_item_" + id).classList.add("stockitem_selectable");
            }
            var item = dojo.query("#" + this.container_div.id + "_item_" + id);
            item.connect("onclick", this, "onClick");
            if (from != null) {
                var start = domGeom.position(this.container_div.id + "_item_" + id);
                var stop = domGeom.position(from);
                var finalx = start.x - stop.x;
                var finaly = start.y - stop.y;
                dojo.style(this.container_div.id + "_item_" + id, {
                    left: -finalx + "px",
                    top: -finaly + "px",
                    opacity: 1,
                });
            }
            else {
                dojo
                    .fadeIn({
                    duration: 1000,
                    node: this.container_div.id + "_item_" + id,
                })
                    .play();
            }
            this.resetItemsPosition();
            if (this.onItemCreate != null) {
                this.onItemCreate(dojo.query("#" + this.container_div.id + "_item_" + id), type, this.container_div.id + "_item_" + id);
            }
        };
        EbgStock.prototype.onClick = function (event) {
            dojo.stopEvent(event);
            if (event.currentTarget.classList.contains("stockitem_selectable")) {
                if (event.currentTarget.classList.contains("stockitem_selected")) {
                    event.currentTarget.classList.remove("stockitem_selected");
                }
                else {
                    if (this.selectionMode == 1) {
                        this.unselectAll();
                    }
                    event.currentTarget.classList.add("stockitem_selected");
                }
                if (this.onChangeSelection != null) {
                    var itemId = event.currentTarget.id.substr((this.container_div.id + '_item_').length);
                    this.onChangeSelection(this.container_div.id, itemId);
                }
            }
        };
        EbgStock.prototype.removeFromStock = function (type, to, noupdate) {
            if (to === void 0) { to = null; }
            if (noupdate === void 0) { noupdate = false; }
            this.removeFromStockById(type, to, noupdate);
        };
        EbgStock.prototype.removeFromStockById = function (id, to, noupdate) {
            if (to === void 0) { to = null; }
            if (noupdate === void 0) { noupdate = false; }
            this.items = this.items.filter(function (value, index, arr) {
                return value.id != id;
            });
            if (to != null) {
                var start = domGeom.position(this.container_div.id + "_item_" + id);
                var stop = domGeom.position(to);
                var finalx = start.x - stop.x;
                var finaly = start.y - stop.y;
                var tnode = this.container_div.id + "_item_" + id;
                dojo
                    .animateProperty({
                    node: tnode,
                    duration: 1000,
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
            }
            else {
                this.toBeDelete.push(id);
            }
            if (!noupdate) {
                this.updateDisplay();
            }
        };
        EbgStock.prototype.removeAll = function () {
            this.items.length = 0;
            dojo.empty(this.container_div.id);
        };
        EbgStock.prototype.removeAllTo = function (to) {
            for (var idx in this.items) {
                var id = this.items[idx].id;
                var start = domGeom.position(this.container_div.id + "_item_" + id);
                var stop = domGeom.position(to);
                var finalx = start.x - stop.x;
                var finaly = start.y - stop.y;
                var tnode = this.container_div.id + "_item_" + id;
                dojo
                    .animateProperty({
                    node: tnode,
                    duration: 1000,
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
            }
            this.items.length = 0;
        };
        EbgStock.prototype.getPresentTypeList = function () {
            var ret = {};
            for (var idx in this.items) {
                var type = this.items[idx].type;
                if (ret[type] !== undefined) {
                    ret[type]++;
                }
                else {
                    ret[type] = 1;
                }
            }
            return ret;
        };
        EbgStock.prototype.updateDisplay = function () {
            for (var idx in this.toBeDelete) {
                var id = this.toBeDelete[idx];
                dojo.destroy(this.container_div.id + "_item_" + id);
            }
            this.toBeDelete.length = 0;
            this.resetItemsPosition();
        };
        EbgStock.prototype.getPosition = function (index) {
            var computedStyle = style.getComputedStyle(this.container_div);
            var width = parseInt(computedStyle.width.replace("px", ""));
            var itemsInLine = Math.floor(width / (this.item_margin + this.item_width));
            var nbLines = Math.ceil(this.items.length / itemsInLine);
            dojo.style(this.container_div, {
                height: nbLines * (this.item_margin + this.item_height) + "px",
            });
            var line = Math.floor(index / itemsInLine);
            var left = (index % itemsInLine) *
                (this.item_margin + this.item_width - this.horizontalOverlap);
            var top = line * (this.item_margin + this.item_height - this.verticalOverlap);
            var nb_in_current_line = Math.min(itemsInLine, this.items.length - itemsInLine * line);
            if (this.centerItems) {
                left +=
                    (width -
                        nb_in_current_line *
                            (this.item_margin + this.item_width - this.horizontalOverlap)) /
                        2;
            }
            return { top: top, left: left };
        };
        EbgStock.prototype.resetItemsPosition = function () {
            var propComparator = function (types) { return function (a, b) {
                return types[a.type].weight - types[b.type].weight;
            }; };
            this.items.sort(propComparator(this.itemTypes));
            var computedStyle = style.getComputedStyle(this.container_div);
            var width = parseInt(computedStyle.width.replace("px", ""));
            var itemsInLine = Math.floor(width / (this.item_margin + this.item_width));
            var nbLines = Math.ceil(this.items.length / itemsInLine);
            dojo.style(this.container_div, {
                height: nbLines * (this.item_margin + this.item_height) + "px",
            });
            var i = 0;
            for (var idx in this.items) {
                var id = this.items[idx].id;
                var line = Math.floor(i / itemsInLine);
                var left = (i % itemsInLine) *
                    (this.item_margin + this.item_width - this.horizontalOverlap);
                var top = line * (this.item_margin + this.item_height - this.verticalOverlap);
                var nb_in_current_line = Math.min(itemsInLine, this.items.length - itemsInLine * line);
                if (this.centerItems) {
                    left +=
                        (width -
                            nb_in_current_line *
                                (this.item_margin + this.item_width - this.horizontalOverlap)) /
                            2;
                }
                dojo
                    .animateProperty({
                    node: this.container_div.id + "_item_" + id,
                    duration: 1000,
                    properties: {
                        left: left,
                        top: top,
                    },
                })
                    .play();
                i++;
            }
        };
        EbgStock.prototype.changeItemsWeight = function (newWeights) {
            for (var idx in newWeights) {
                var weight = newWeights[idx];
                this.itemTypes[idx].weight = weight;
            }
            this.resetItemsPosition();
        };
        EbgStock.prototype.setSelectionMode = function (mode) {
            this.selectionMode = mode;
            dojo.query(this.container_div.id + " .stockitem").forEach(function (node) {
                node.classList.remove("stockitem_selectable stockitem_selected");
            });
            if (this.selectionMode != 0) {
                dojo.query(this.container_div.id + " .stockitem").forEach(function (node) {
                    node.classList.add("stockitem_selectable");
                });
            }
        };
        EbgStock.prototype.setSelectionAppearance = function (type) {
            this.selectionAppearance = type;
        };
        EbgStock.prototype.isSelected = function (id) {
            return $(this.container_div.id + "_item_" + id).classList.contains("stockitem_selected");
        };
        EbgStock.prototype.selectItem = function (id) {
            if (this.selectionMode == 1) {
                this.unselectAll();
            }
            $(this.container_div.id + "_item_" + id).classList.add("stockitem_selected");
        };
        EbgStock.prototype.unselectItem = function (id) {
            $(this.container_div.id + "_item_" + id).classList.remove("stockitem_selected");
        };
        EbgStock.prototype.unselectAll = function () {
            dojo
                .query("#" + this.container_div.id + " .stockitem_selected")
                .forEach(function (node) {
                node.removeClass("stockitem_selected");
            });
        };
        EbgStock.prototype.getSelectedItems = function () {
            var ret = [];
            var items = this.items;
            dojo
                .query("#" + this.container_div.id + " .stockitem_selected")
                .forEach(function (node, index, arr) {
                var split = node.id.split("_");
                var id = split[split.length - 1];
                for (var idx in items) {
                    if (items[idx].id == id) {
                        ret.push({ type: items[idx].type, id: items[idx].id });
                        break;
                    }
                }
            });
            return ret;
        };
        EbgStock.prototype.getUnselectedItems = function () {
            var ret = [];
            var items = this.items;
            dojo
                .query("#" + this.container_div.id + " .stockitem:not(.stockitem_selected)")
                .forEach(function (node, index, arr) {
                var split = node.id.split("_");
                var id = split[split.length - 1];
                for (var idx in items) {
                    if (items[idx].id == id) {
                        ret.push({ type: items[idx].type, id: items[idx].id });
                        break;
                    }
                }
            });
            return ret;
        };
        EbgStock.prototype.getAllItems = function () {
            var ret = [];
            var items = this.items;
            dojo
                .query("#" + this.container_div.id + " .stockitem")
                .forEach(function (node, index, arr) {
                var split = node.id.split("_");
                var id = split[split.length - 1];
                for (var idx in items) {
                    if (items[idx].id == id) {
                        ret.push({ type: items[idx].type, id: items[idx].id });
                        break;
                    }
                }
            });
            return ret;
        };
        EbgStock.prototype.getItemDivId = function (id) {
            return this.container_div.id + "_item_" + id;
        };
        EbgStock.prototype.getItemById = function (id) {
            for (var idx in this.items) {
                if (this.items[idx].id == id) {
                    return this.items[idx];
                }
            }
            return null;
        };
        EbgStock.prototype.setOverlap = function (horizontal_percent, vertical_percent) {
            this.horizontalOverlap = (horizontal_percent * this.item_width) / 100;
            this.verticalOverlap = (vertical_percent * this.item_height) / 100;
            this.resetItemsPosition();
        };
        EbgStock = __decorate([
            (0, declareDecorator_1.default)()
        ], EbgStock);
        return EbgStock;
    }());
    exports.default = EbgStock;
    ebg.stock = EbgStock;
});

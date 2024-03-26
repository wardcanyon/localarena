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
        define(["require", "exports", "./declareDecorator"], factory);
    }
})(function (require, exports) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    var declareDecorator_1 = require("./declareDecorator");
    var EbgZone = (function () {
        function EbgZone() {
            this.page = null;
            this.container_div = null;
            this.item_height = null;
            this.item_width = null;
            this.instantaneous = false;
            this.items = [];
            this.control_name = null;
            this.item_margin = 5;
            this.autowidth = false;
            this.autoheight = true;
            this.item_pattern = 'grid';
        }
        EbgZone.prototype.create = function (page, container_div, item_width, item_height) {
            if (container_div === null) {
                console.error('Null container.');
            }
            this.page = page;
            this.container_div = container_div;
            this.item_width = item_width;
            this.item_height = item_height;
            this.control_name = container_div.id;
            if (dojo.style(this.container_div, 'position') != 'absolute') {
                dojo.style(this.container_div, 'position', 'relative');
            }
        };
        EbgZone.prototype.getItemNumber = function () {
            return this.items.length;
        };
        EbgZone.prototype.getAllItems = function () {
            var result = [];
            for (var idx in this.items) {
                result.push(this.items[idx].id);
            }
            return result;
        };
        EbgZone.prototype.setFluidWidth = function () {
            dojo.connect(window, 'onresize', this, 'updateDisplay');
        };
        EbgZone.prototype.setPattern = function (item_pattern) {
            switch (item_pattern) {
                case 'grid':
                case 'diagonal':
                    this.autoheight = true;
                    this.item_pattern = item_pattern;
                    break;
                case 'verticalfit':
                case 'horizontalfit':
                case 'ellipticalfit':
                    this.autoheight = false;
                    this.item_pattern = item_pattern;
                    break;
                case 'custom':
                    break;
                default:
                    console.error('Unknown pattern: ' + item_pattern);
            }
        };
        EbgZone.prototype.isInZone = function (objectId) {
            for (var idx in this.items) {
                if (this.items[idx].id == objectId) {
                    return true;
                }
            }
            return false;
        };
        EbgZone.prototype.placeInZone = function (objectId, weight) {
            if (weight == null) {
                weight = 0;
            }
            if (!this.isInZone(objectId)) {
                this.items.push({ id: objectId, weight: weight });
                this.page.attachToNewParent($(objectId), this.container_div);
                this.items.sort(function (a, b) {
                    if (a.weight > b.weight) {
                        return 1;
                    }
                    else if (a.weight < b.weight) {
                        return -1;
                    }
                    else {
                        return 0;
                    }
                });
                this.updateDisplay();
            }
        };
        EbgZone.prototype.removeFromZone = function (objectId, destroy, to) {
            var onEnd = function (objectId) {
                dojo.destroy(objectId);
            };
            for (var idx in this.items) {
                var item = this.items[idx];
                if (item.id == objectId) {
                    var anim = null;
                    if (to) {
                        var duration = 500;
                        if (this.instantaneous) {
                            duration = 1;
                        }
                        anim = this.page.slideToObject($(item.id), to, duration).play();
                        if (destroy) {
                            dojo.connect(anim, 'onEnd', objectId);
                        }
                        anim.play();
                    }
                    else if (destroy) {
                        var duration = 500;
                        if (this.page.instantaneousMode || this.instantaneous) {
                            duration = 1;
                        }
                        anim = dojo.fadeOut({
                            node: $(item.id),
                            duration: duration,
                            onEnd: onEnd,
                        });
                        anim.play();
                    }
                    this.items.splice(+idx, 1);
                    this.updateDisplay();
                    return;
                }
            }
        };
        EbgZone.prototype.removeAll = function () {
            var onEnd = function (el) {
                dojo.destroy(el);
            };
            for (var idx in this.items) {
                var item = this.items[idx];
                var anim = dojo.fadeOut({ node: $(item.id), onEnd: onEnd });
                anim.play();
            }
            this.items = [];
            this.updateDisplay();
        };
        EbgZone.prototype.updateDisplay = function () {
            var elPos = dojo.position(this.container_div);
            if (this.autowidth) {
                elPos = dojo.position($('page-content')).w;
            }
            var j = 0;
            var height = 0;
            var width = 0;
            for (var idx in this.items) {
                var item = this.items[idx].id;
                var itemEl = $(item);
                if (itemEl) {
                    var itemCoords = this.itemIdToCoords(j, elPos.w, elPos.h, this.items.length);
                    j++;
                    width = Math.max(width, itemCoords.x + itemCoords.w);
                    height = Math.max(height, itemCoords.y + itemCoords.h);
                    var duration = 1000;
                    if (this.page.instantaneousMode || this.instantaneous) {
                        duration = 2;
                    }
                    var anim = dojo.fx.slideTo({
                        node: itemEl,
                        top: itemCoords.y,
                        left: itemCoords.x,
                        duration: duration,
                        unit: 'px',
                    });
                    anim.play();
                }
            }
            if (this.autoheight) {
                dojo.style(this.container_div, 'height', height + 'px');
            }
            if (this.autowidth) {
                dojo.style(this.container_div, 'width', width + 'px');
            }
        };
        EbgZone.prototype.itemIdToCoords = function (i, controlWidth, controlHeight, itemCount) {
            switch (this.item_pattern) {
                case 'grid':
                    return this.itemIdToCoordsGrid(i, controlWidth);
                case 'diagonal':
                    return this.itemIdToCoordsDiagonal(i, controlWidth);
                case 'verticalfit':
                    return this.itemIdToCoordsVerticalFit(i, controlWidth, controlHeight, itemCount);
                case 'horizontalfit':
                    return this.itemIdToCoordsHorizontalFit(i, controlWidth, controlHeight, itemCount);
                case 'ellipticalfit':
                    return this.itemIdToCoordsEllipticalFit(i, controlWidth, controlHeight, itemCount);
            }
        };
        EbgZone.prototype.itemIdToCoordsGrid = function (i, controlWidth) {
            var itemsPerLine = Math.max(1, Math.floor(controlWidth / (this.item_width + this.item_margin)));
            var line = Math.floor(i / itemsPerLine);
            var c = {
                w: this.item_width,
                h: this.item_height,
                y: line * (this.item_height + this.item_margin),
                x: (i - line * itemsPerLine) * (this.item_width + this.item_margin),
            };
            return c;
        };
        EbgZone.prototype.itemIdToCoordsDiagonal = function (i, controlWidth) {
            var c = {
                w: this.item_width,
                h: this.item_height,
                y: i * this.item_margin,
                x: i * this.item_margin,
            };
            return c;
        };
        EbgZone.prototype.itemIdToCoordsVerticalFit = function (i, controlWidth, controlHeight, itemCount) {
            var heightNeeded = itemCount * this.item_height;
            if (heightNeeded <= controlHeight) {
                var spacing = this.item_height;
                var offset = (controlHeight - heightNeeded) / 2;
            }
            else {
                var spacing = (controlHeight - this.item_height) / (itemCount - 1);
                var offset = 0;
            }
            var c = {
                w: this.item_width,
                h: this.item_height,
                y: Math.round(i * spacing + offset),
                x: 0,
            };
            return c;
        };
        EbgZone.prototype.itemIdToCoordsHorizontalFit = function (i, controlWidth, controlHeight, itemCount) {
            var widthNeeded = itemCount * this.item_width;
            if (widthNeeded <= controlWidth) {
                var spacing = this.item_width;
                var offset = (controlWidth - widthNeeded) / 2;
            }
            else {
                var spacing = (controlWidth - this.item_width) / (itemCount - 1);
                var offset = 0;
            }
            var c = {
                w: this.item_width,
                h: this.item_height,
                x: Math.round(i * spacing + offset),
                y: 0,
            };
            return c;
        };
        EbgZone.prototype.itemIdToCoordsEllipticalFit = function (i, controlWidth, controlHeight, itemCount) {
            var centerX = controlWidth / 2;
            var centerY = controlHeight / 2;
            var j = itemCount - (i + 1);
            if (j <= 4) {
                var a = c.w;
                var b = (c.h * centerY) / centerX;
                var theta = Math.PI + j * ((2 * Math.PI) / 5);
            }
            else if (j > 4) {
                var a = 2 * c.w;
                var b = (2 * c.h * centerY) / centerX;
                var theta = Math.PI - Math.PI / 2 + (j - 4) * ((2 * Math.PI) / Math.max(10, itemCount - 5));
            }
            var c = {
                w: this.item_width,
                h: this.item_height,
                x: centerX + a * Math.cos(theta) - c.w / 2,
                y: centerY + b * Math.sin(theta) - c.h / 2,
            };
            return c;
        };
        EbgZone = __decorate([
            (0, declareDecorator_1.default)()
        ], EbgZone);
        return EbgZone;
    }());
    exports.default = EbgZone;
    ebg.zone = EbgZone;
});

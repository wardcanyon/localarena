import declare from './declareDecorator';

@declare()
export default class EbgZone {
    page = null;
    container_div = null;
    item_height: number = null;
    item_width: number = null;
    instantaneous: boolean = false;
    items: Array<any> = [];
    control_name = null;
    item_margin: number = 5;
    autowidth: boolean = false;
    autoheight: boolean = true;
    item_pattern: string = 'grid';

    create(page, container_div, item_width, item_height): void {
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
    }

    getItemNumber(): number {
        return this.items.length;
    }

    getAllItems(): string[] {
        var result: string[] = [];
        for (var idx in this.items) {
            result.push(this.items[idx].id);
        }
        return result;
    }

    setFluidWidth(): void {
        dojo.connect(window, 'onresize', this, 'updateDisplay');
    }

    setPattern(item_pattern: string): void {
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
    }

    isInZone(objectId: string): boolean {
        for (var idx in this.items) {
            if (this.items[idx].id == objectId) {
                return true;
            }
        }
        return false;
    }

    placeInZone(objectId: string, weight?: number): void {
        // XXX: "void 0"?
        if (weight == null) {
            weight = 0;
        }
        if (!this.isInZone(objectId)) {
            this.items.push({ id: objectId, weight: weight });
            this.page.attachToNewParent($(objectId), this.container_div);
            this.items.sort(function (a, b) {
                if (a.weight > b.weight) {
                    return 1;
                } else if (a.weight < b.weight) {
                    return -1;
                } else {
                    return 0;
                }
            });
            this.updateDisplay();
        }
    }

    removeFromZone(objectId: string, destroy: boolean, to?: string): void {
        var onEnd = function (objectId) {
            dojo.destroy(objectId);
        };
        for (var idx in this.items) {
            var item = this.items[idx];
            if (item.id == objectId) {
                var anim = null;
                if (to) {
                    var duration: number = 500;
                    if (this.instantaneous) {
                        duration = 1;
                    }
                    anim = this.page.slideToObject($(item.id), to, duration).play();
                    if (destroy) {
                        dojo.connect(anim, 'onEnd', objectId);
                    }
                    anim.play();
                } else if (destroy) {
                    var duration: number = 500;
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
                // XXX: string-int conversion issue
                this.items.splice(+idx, 1);
                this.updateDisplay();
                return;
            }
        }
    }

    removeAll(): void {
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
    }

    updateDisplay(): void {
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
    }

    itemIdToCoords(i: number, controlWidth: number, controlHeight: number, itemCount: number): Coords {
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
    }

    itemIdToCoordsGrid(i: number, controlWidth: number): Coords {
        var itemsPerLine = Math.max(
            1,
            Math.floor(controlWidth / (this.item_width + this.item_margin))
        );
        var line = Math.floor(i / itemsPerLine);

        let c: Coords = {
            w: this.item_width,
            h: this.item_height,
            y: line * (this.item_height + this.item_margin),
            x: (i - line * itemsPerLine) * (this.item_width + this.item_margin),
        };
        return c;
    }

    itemIdToCoordsDiagonal(i: number, controlWidth: number): Coords {
        var c: Coords = {
            w: this.item_width,
            h: this.item_height,
            y: i * this.item_margin,
            x: i * this.item_margin,
        };
        return c;
    }

    itemIdToCoordsVerticalFit(i: number, controlWidth: number, controlHeight: number, itemCount: number): Coords {
        var heightNeeded: number = itemCount * this.item_height;
        if (heightNeeded <= controlHeight) {
            // Plenty of space; items will be at ideal spacing.
            var spacing = this.item_height;
            var offset = (controlHeight - heightNeeded) / 2;
        } else {
            // Items need to be closer than they ideally would be.
            var spacing = (controlHeight - this.item_height) / (itemCount - 1);
            var offset = 0;
        }

        var c: Coords = {
            w: this.item_width,
            h: this.item_height,
            y: Math.round(i * spacing + offset),
            x: 0,
        };
        return c;
    }

    itemIdToCoordsHorizontalFit(i: number, controlWidth: number, controlHeight: number, itemCount: number): Coords {
        var widthNeeded = itemCount * this.item_width;
        if (widthNeeded <= controlWidth) {
            // Plenty of space; items will be at ideal spacing.
            var spacing = this.item_width;
            var offset = (controlWidth - widthNeeded) / 2;
        } else {
            // Items need to be closer than they ideally would be.
            var spacing = (controlWidth - this.item_width) / (itemCount - 1);
            var offset = 0;
        }

        var c: Coords = {
            w: this.item_width,
            h: this.item_height,
            x: Math.round(i * spacing + offset),
            y: 0,
        };
        return c;
    }

    itemIdToCoordsEllipticalFit(i: number, controlWidth: number, controlHeight: number, itemCount: number): Coords {
        var centerX = controlWidth / 2;
        var centerY = controlHeight / 2;

        var j = itemCount - (i + 1);
        if (j <= 4) {
            var a = c.w;
            var b = (c.h * centerY) / centerX;
            var theta = Math.PI + j * ((2 * Math.PI) / 5);
        } else if (j > 4) {
            var a = 2 * c.w;
            var b = (2 * c.h * centerY) / centerX;
            var theta = Math.PI - Math.PI / 2 + (j - 4) * ((2 * Math.PI) / Math.max(10, itemCount - 5));
        }

        var c: Coords = {
            w: this.item_width,
            h: this.item_height,
            x: centerX + a * Math.cos(theta) - c.w / 2,
            y: centerY + b * Math.sin(theta) - c.h / 2,
        };
        return c;
    }
}

// XXX: I'm sure that this is probably not the "right" way to do this,
// but it works!
ebg.zone = EbgZone;

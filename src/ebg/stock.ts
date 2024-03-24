// define([
//   "dojo",
//   "dojo/_base/declare",
//   "dojo/dom-geometry",
//   "dojo/fx",
//   "dojo/dom-style",
// ], function (dojo, declare, domGeom, fx, style) {
//   return declare("ebg.stock", null, {

import * as domGeom from 'dojo/dom-geometry';
import * as style from 'dojo/dom-style';
import declare from './declareDecorator';

@declare()
export default class EbgStock {
    page = null;
    container_div = null;
    item_height: number = null;
    item_width: number = null;
    item_margin = 5;
    centerItems = false;
    onChangeSelection = null;
    onItemCreate = null;
    image_items_per_row = 1;
    itemTypes = [];
    items = [];
    toBeDelete = [];
    selectionMode = 0;
    selectionAppearance = "border";
    horizontalOverlap = 0;
    verticalOverlap = 0;

    create(page, container_div, item_width, item_height) {
      this.page = page;
      this.container_div = container_div;
      this.item_width = item_width;
      this.item_height = item_height;
      dojo.byId(this.container_div).style =
        "position: relative; height: 324px;";
      dojo.connect(window, "onresize", this, "resetItemsPosition");
    }

    count() {
      return this.items.length;
    }

    addItemType(type, weight, image, image_position) {
      this.itemTypes[type] = {
        type: type,
        weight: weight,
        image: image,
        image_position: image_position,
      };
    }

    addToStock(type, from) {
      this.addToStockWithId(type, type, from);
    }

    addToStockWithId(type, id, from = null) {
      var item = this.itemTypes[type];
      var backx =
        -(item.image_position % this.image_items_per_row) * this.item_width;
      var backy =
        -Math.floor(item.image_position / this.image_items_per_row) *
        this.item_height;

      this.items.push({ id: id, type: type });
      const propComparator = (types) => (a, b) =>
        types[a.type].weight - types[b.type].weight;
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

      var template =
        '<div id="' +
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
        dojo
          .query("#" + this.container_div.id + "_item_" + id)
          .addClass("stockitem_selectable");
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
      } else {
        dojo
          .fadeIn({
            duration: 1000,
            node: this.container_div.id + "_item_" + id,
          })
          .play();
      }

      this.resetItemsPosition();

      if (this.onItemCreate != null) {
        this.onItemCreate(
          dojo.query("#" + this.container_div.id + "_item_" + id),
          type,
          this.container_div.id + "_item_" + id,
        );
      }
    }

    onClick(event) {
        dojo.stopEvent(event);
        if (event.currentTarget.classList.contains("stockitem_selectable")) {
            if (event.currentTarget.classList.contains("stockitem_selected")) {
                event.currentTarget.classList.remove("stockitem_selected");
            } else {
                if (this.selectionMode == 1) {
                    this.unselectAll();
                }
                event.currentTarget.classList.add("stockitem_selected");
            }
            if (this.onChangeSelection != null) {
                var itemId = event.currentTarget.id.substr((this.container_div.id+'_item_').length);
                this.onChangeSelection(this.container_div.id, itemId);
            }
        }
    }

    removeFromStock(type, to = null, noupdate = false) {
      this.removeFromStockById(type, to, noupdate);
    }

    removeFromStockById(id, to = null, noupdate = false) {
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
      } else {
        this.toBeDelete.push(id);
      }
      if (!noupdate) {
        this.updateDisplay();
      }
    }

    removeAll() {
      this.items.length = 0;
      dojo.empty(this.container_div.id);
    }

    removeAllTo(to) {
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
    }

    getPresentTypeList() {
      var ret = {};
      for (var idx in this.items) {
        var type = this.items[idx].type;
        if (ret[type] !== undefined) {
          ret[type]++;
        } else {
          ret[type] = 1;
        }
      }
      return ret;
    }

    updateDisplay() {
      for (var idx in this.toBeDelete) {
        var id = this.toBeDelete[idx];
        dojo.destroy(this.container_div.id + "_item_" + id);
      }
      this.toBeDelete.length = 0;
      this.resetItemsPosition();
    }

    getPosition(index) {
      var computedStyle = style.getComputedStyle(this.container_div);
      var width = parseInt(computedStyle.width.replace("px", ""));
      var itemsInLine = Math.floor(
        width / (this.item_margin + this.item_width),
      );
      var nbLines = Math.ceil(this.items.length / itemsInLine);
      dojo.style(this.container_div, {
        height: nbLines * (this.item_margin + this.item_height) + "px",
      });

      var line = Math.floor(index / itemsInLine);
      var left =
        (index % itemsInLine) *
        (this.item_margin + this.item_width - this.horizontalOverlap);
      var top =
        line * (this.item_margin + this.item_height - this.verticalOverlap);
      var nb_in_current_line = Math.min(
        itemsInLine,
        this.items.length - itemsInLine * line,
      );

      if (this.centerItems) {
        left +=
          (width -
            nb_in_current_line *
              (this.item_margin + this.item_width - this.horizontalOverlap)) /
          2;
      }

      return { top: top, left: left };
    }

    resetItemsPosition() {
      const propComparator = (types) => (a, b) =>
        types[a.type].weight - types[b.type].weight;
      this.items.sort(propComparator(this.itemTypes));

      var computedStyle = style.getComputedStyle(this.container_div);
      var width = parseInt(computedStyle.width.replace("px", ""));
      var itemsInLine = Math.floor(
        width / (this.item_margin + this.item_width),
      );
      var nbLines = Math.ceil(this.items.length / itemsInLine);
      dojo.style(this.container_div, {
        height: nbLines * (this.item_margin + this.item_height) + "px",
      });

      var i = 0;
      for (var idx in this.items) {
        var id = this.items[idx].id;
        var line = Math.floor(i / itemsInLine);
        var left =
          (i % itemsInLine) *
          (this.item_margin + this.item_width - this.horizontalOverlap);
        var top =
          line * (this.item_margin + this.item_height - this.verticalOverlap);
        var nb_in_current_line = Math.min(
          itemsInLine,
          this.items.length - itemsInLine * line,
        );

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
    }

    changeItemsWeight(newWeights) {
      for (var idx in newWeights) {
        var weight = newWeights[idx];
        this.itemTypes[idx].weight = weight;
      }
      this.resetItemsPosition();
    }

    setSelectionMode(mode) {
      this.selectionMode = mode;
      dojo
        .query("#" + this.container_div.id + " .stockitem")
        .removeClass("stockitem_selectable stockitem_selected");

      if (this.selectionMode != 0) {
        dojo
          .query("#" + this.container_div.id + " .stockitem")
          .addClass("stockitem_selectable");
      }
    }

    setSelectionAppearance(type) {
      this.selectionAppearance = type;
    }

    isSelected(id) {
        return $(this.container_div.id + "_item_" + id).classList.contains("stockitem_selected");
    }

    selectItem(id) {
      if (this.selectionMode == 1) {
        this.unselectAll();
      }
        $(this.container_div.id + "_item_" + id).classList.add("stockitem_selected");
    }

    unselectItem(id) {
        $(this.container_div.id + "_item_" + id).classList.remove("stockitem_selected");
    }

    unselectAll() {
        dojo
            .query("#" + this.container_div.id + " .stockitem_selected")
            .forEach(function(node) {
                node.removeClass("stockitem_selected");
            });
    }

    getSelectedItems() {
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
    }

    getUnselectedItems() {
      var ret = [];
      var items = this.items;
      dojo
        .query(
          "#" + this.container_div.id + " .stockitem:not(.stockitem_selected)",
        )
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
    }

    getAllItems() {
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
    }

    getItemDivId(id) {
      return this.container_div.id + "_item_" + id;
    }

    getItemById(id) {
      for (var idx in this.items) {
        if (this.items[idx].id == id) {
          return this.items[idx];
        }
      }
      return null;
    }

    setOverlap(horizontal_percent, vertical_percent) {
      this.horizontalOverlap = (horizontal_percent * this.item_width) / 100;
      this.verticalOverlap = (vertical_percent * this.item_height) / 100;
      this.resetItemsPosition();
    }
}

// XXX: I'm sure that this is probably not the "right" way to do this,
// but it works!
ebg.stock = EbgStock;

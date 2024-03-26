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
    var EbgCounter = (function () {
        function EbgCounter() {
            this.containerId = null;
            this.value = 0;
        }
        EbgCounter.prototype.create = function (containerId) {
            this.containerId = containerId;
            this.value = 0;
        };
        EbgCounter.prototype.getValue = function () {
            return this.value;
        };
        EbgCounter.prototype.incValue = function (inc) {
            this.setValue(this.value + parseInt(inc));
        };
        EbgCounter.prototype.setValue = function (val) {
            this.value = parseInt(val);
            dojo.byId(this.containerId).innerHTML = this.value;
        };
        EbgCounter = __decorate([
            (0, declareDecorator_1.default)()
        ], EbgCounter);
        return EbgCounter;
    }());
    ebg.counter = EbgCounter;
});

import declare from "./declareDecorator";

@declare()
export default class EbgCounter {
    el: HTMLElement = null;

    // The logical value of the counter.
    value: number = 0;

    // This differs from `value` only when the counter is being animated.
    displayedValue: number = 0;

    speed: number = 100;

    create(containerId: string): void {
        this.el = $(containerId);
    }

    getValue(): number {
        return this.value;
    }

    incValue(delta: number): void {
        this.toValue(this.value + delta);
    }

    setValue(value: number): void {
        this.value = value;
        this.el.innerHTML = '' + this.value;
    }

    // Like `setValue()`, but animates the counter.
    toValue(value: number): void {
        // XXX: For the moment, no animation is supported.
        this.setValue(value);
    }

    disable(): void {
        this.el.innerHTML = '-';
    }
}

ebg.counter = EbgCounter;

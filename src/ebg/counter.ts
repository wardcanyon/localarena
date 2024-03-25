import declare from './declareDecorator';

@declare()
class EbgCounter {
    containerId: string = null;
    value: number = 0;

    create(containerId: string): void {
        this.containerId = containerId;
        this.value = 0;
    }

    getValue() {
        return this.value;
    }

    incValue(inc) {
        this.setValue(this.value + parseInt(inc));
    }

    setValue(val) {
        this.value = parseInt(val);
        dojo.byId(this.containerId).innerHTML = this.value;
    }
}

ebg.counter = EbgCounter;

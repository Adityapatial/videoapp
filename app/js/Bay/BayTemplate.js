const SUFFIX = ' .template';

BayTemplate = {

    struct: {
        destination: '',
        element: ''
    },

    wipe: function (source) {
        // debugger
        var d = '';
        if (document.querySelector('[template-default="' + source + '"]') !== null) {
            d = document.querySelector('[template-default="' + source + '"]').innerHTML;
        }
        document.querySelector(source).innerHTML = d;
    },

    open: function (source, to) {
        //  debugger
        var struct = this.struct;

        struct.destination = document.querySelector(source);
        struct.element = document.querySelector('[template="' + source + '"]').cloneNode(true);

        return struct;
    },

    saveAsElement: function (struct) {
        struct.element.removeAttribute('template');
        return struct.destination.appendChild(struct.element);
    },

    saveAsTemplate: function (struct) {
        return struct.destination.appendChild(struct.element);
    }

};

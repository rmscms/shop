(function(window, $) {
    'use strict';

    if (typeof $ === 'undefined') {
        console.error('RMS Fancytree: jQuery is required.');
        return;
    }

    const DATA_KEY = 'rms.fancytree';

    const DEFAULTS = {
        checkbox: false,
        selectMode: 2,
        rtl: document.documentElement.getAttribute('dir') === 'rtl',
        extensions: [],
        source: []
    };

    class RmsFancytree {
        constructor(element, options) {
            this.$element = $(element);
            this.options = $.extend(true, {}, DEFAULTS, options);
            this.tree = null;

            this.init();
        }

        init() {
            if (!$.fn.fancytree) {
                console.error('RMS Fancytree: fancytree_all.min.js is missing.');
                return;
            }

            const dataOptions = this._parseDataOptions();
            const settings = $.extend(true, {}, this.options, dataOptions);

            this.destroy();

            this.$element.fancytree(settings);
            this.tree = $.ui.fancytree.getTree(this.$element);

            this.$element.trigger('rms.fancytree.initialized', [this.tree]);
        }

        reloadSource(source) {
            if (!this.tree) {
                return;
            }

            this.tree.reload(source);
        }

        getTree() {
            return this.tree;
        }

        destroy() {
            if (this.tree) {
                this.tree.destroy();
                this.tree = null;
            }
            this.$element.removeData(DATA_KEY);
        }

        _parseDataOptions() {
            const dataset = this.$element.data();
            const options = {};

            if (typeof dataset.checkbox !== 'undefined') {
                options.checkbox = dataset.checkbox === true || dataset.checkbox === 'true';
            }

            if (typeof dataset.selectMode !== 'undefined') {
                const parsed = parseInt(dataset.selectMode, 10);
                if (!Number.isNaN(parsed)) {
                    options.selectMode = parsed;
                }
            }

            if (typeof dataset.extensions === 'string' && dataset.extensions.trim() !== '') {
                options.extensions = dataset.extensions
                    .split(',')
                    .map(ext => ext.trim())
                    .filter(Boolean);
            }

            if (dataset.url) {
                options.source = { url: dataset.url };
            } else if (dataset.source) {
                try {
                    options.source = JSON.parse(dataset.source);
                } catch (error) {
                    console.warn('RMS Fancytree: failed to parse data-source JSON.', error);
                }
            }

            if (typeof dataset.lazyload === 'function') {
                options.lazyLoad = dataset.lazyload;
            }

            return options;
        }

        static initAll(context) {
            const $context = context ? $(context) : $(document);

            $context.find('[data-plugin="fancytree"]').each(function() {
                const $el = $(this);

                if ($el.data(DATA_KEY)) {
                    return;
                }

                const options = $el.data('options') || {};
                const instance = new RmsFancytree(this, options);
                $el.data(DATA_KEY, instance);
            });
        }
    }

    $.fn.rmsFancytree = function(options) {
        return this.each(function() {
            const $el = $(this);
            let instance = $el.data(DATA_KEY);

            if (!instance) {
                instance = new RmsFancytree(this, options);
                $el.data(DATA_KEY, instance);
            } else if (options) {
                instance.destroy();
                instance = new RmsFancytree(this, options);
                $el.data(DATA_KEY, instance);
            }
        });
    };

    document.addEventListener('DOMContentLoaded', function() {
        RmsFancytree.initAll();
    });

    window.RmsFancytree = RmsFancytree;
})(window, window.jQuery);


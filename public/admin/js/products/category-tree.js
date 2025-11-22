(function ($) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof $ === 'undefined' || !$.fn || !$.fn.rmsFancytree) {
            return;
        }

        var container = document.getElementById('product-category-tree');
        if (!container) {
            return;
        }

        var hiddenInput = document.getElementById('product-category-id');
        var clearBtn = document.getElementById('btn-clear-category');

        var runtimeConfig = (window.RMS && window.RMS.categories) ? window.RMS.categories : {};
        var dataset = container.dataset || {};

        var treeData = [];
        if (Array.isArray(runtimeConfig.tree)) {
            treeData = runtimeConfig.tree;
        } else if (dataset.tree) {
            try {
                treeData = JSON.parse(dataset.tree);
            } catch (e) {
                treeData = [];
            }
        }

        var treeEndpoint = runtimeConfig.treeEndpoint || dataset.endpoint || '';
        var defaultId = parseInt(runtimeConfig.defaultId ?? dataset.default ?? '0', 10) || 0;
        var selectedIdFromConfig = runtimeConfig.selectedId ?? dataset.selected;
        var selectedId = selectedIdFromConfig !== undefined && selectedIdFromConfig !== null && selectedIdFromConfig !== ''
            ? parseInt(selectedIdFromConfig, 10) || 0
            : defaultId;

        function setHiddenValue(value) {
            if (!hiddenInput) {
                return;
            }
            if (value === null || value === undefined || value === '') {
                hiddenInput.value = '';
            } else {
                hiddenInput.value = value;
            }
        }

        function initialiseTree(data) {
            $(container).rmsFancytree({
                source: data,
                selectMode: 1,
                clickFolderMode: 2,
                debugLevel: 0,
                activate: function (event, ctx) {
                    var node = ctx.node;
                    if (!node) {
                        setHiddenValue('');
                        return;
                    }
                    var id = node.data && node.data.id ? node.data.id : node.key;
                    setHiddenValue(id);
                }
            });
        }

        initialiseTree(treeData);

        var tree = $.ui.fancytree.getTree(container);

        function focusNode(key) {
            if (!tree || !key) {
                return;
            }
            var target = tree.getNodeByKey(String(key));
            if (target) {
                target.setActive(true);
                target.makeVisible();
            }
        }

        if (selectedId) {
            focusNode(selectedId);
            setHiddenValue(selectedId);
        } else if (defaultId) {
            focusNode(defaultId);
            setHiddenValue(defaultId);
        }

        if (treeEndpoint) {
            var url = new URL(treeEndpoint, window.location.origin);
            url.searchParams.set('include_inactive', '1');
            if (selectedId) {
                url.searchParams.set('selected', selectedId);
            }

            fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to fetch category tree');
                }
                return response.json();
            }).then(function (payload) {
                if (!payload || payload.success !== true || !Array.isArray(payload.data) || !tree) {
                    return;
                }
                tree.reload(payload.data).done(function () {
                    var key = hiddenInput && hiddenInput.value ? hiddenInput.value : (selectedId || defaultId);
                    if (key) {
                        focusNode(key);
                    } else {
                        tree.getActiveNode()?.setActive(false);
                    }
                });
            }).catch(function (error) {
                console.warn('Unable to refresh category tree:', error);
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (tree) {
                    var activeNode = tree.getActiveNode();
                    if (activeNode) {
                        activeNode.setActive(false);
                    }
                    tree.visit(function (node) {
                        node.setSelected(false);
                    });
                }
                setHiddenValue('');
            });
        }
    });
})(window.jQuery);


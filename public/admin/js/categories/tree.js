(function ($) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof $ === 'undefined' || !$.fn || !$.fn.rmsFancytree) {
            console.error('Fancytree: jQuery integration not found.');
            return;
        }

        var container = document.getElementById('shop-category-tree');
        if (!container) {
            return;
        }

        var config = (window.RMS && window.RMS.ADMIN_SHOP_CATEGORIES) ? window.RMS.ADMIN_SHOP_CATEGORIES : {};
        var routes = config.routes || {};

        var initialData = Array.isArray(config.treeData) ? config.treeData : [];
        if (initialData.length === 0) {
            try {
                var datasetTree = container.getAttribute('data-tree');
                if (datasetTree) {
                    initialData = JSON.parse(datasetTree);
                }
            } catch (e) {
                initialData = [];
            }
        }

        var endpoint = config.treeEndpoint || container.getAttribute('data-endpoint') || '';
        var defaultId = parseInt(config.defaultCategoryId ?? container.getAttribute('data-default-id') ?? '0', 10) || 0;
        var fallbackLabel = container.getAttribute('data-fallback-label') || (config.fallbackLabel || 'بدون دسته');

        var currentNode = null;
        var treeInstance = null;

        var titleEl = document.getElementById('category-info-title');
        var detailsEl = document.getElementById('category-details');
        var emptyHintEl = document.getElementById('category-empty-hint');
        var cardEl = document.getElementById('category-info-card');
        var btnCreateChild = document.getElementById('btn-create-child');
        var btnEdit = document.getElementById('btn-edit-category');

        var detailId = document.getElementById('category-detail-id');
        var detailName = document.getElementById('category-detail-name');
        var detailSlug = document.getElementById('category-detail-slug');
        var detailStatus = document.getElementById('category-detail-status');
        var detailSort = document.getElementById('category-detail-sort');

        function resetDetails() {
            currentNode = null;
            if (cardEl) {
                cardEl.setAttribute('data-empty-state', 'true');
            }
            if (titleEl) {
                titleEl.textContent = 'هیچ دسته‌ای انتخاب نشده است';
            }
            if (detailsEl) {
                detailsEl.hidden = true;
            }
            if (emptyHintEl) {
                emptyHintEl.hidden = false;
            }
            if (btnCreateChild) {
                btnCreateChild.disabled = true;
                btnCreateChild.setAttribute('aria-disabled', 'true');
            btnCreateChild.classList.add('disabled');
            btnCreateChild.href = routes.create || '#';
            }
            if (btnEdit) {
                btnEdit.hidden = true;
                btnEdit.removeAttribute('href');
            }
        }

        function applyDetails(node) {
            if (!node || !node.data) {
                resetDetails();
                return;
            }

            currentNode = node;

            if (cardEl) {
                cardEl.setAttribute('data-empty-state', 'false');
            }
            if (titleEl) {
                titleEl.textContent = node.title || 'دسته بدون نام';
            }
            if (detailsEl) {
                detailsEl.hidden = false;
            }
            if (emptyHintEl) {
                emptyHintEl.hidden = true;
            }

            if (detailId) {
                detailId.textContent = '#' + (node.data.id ?? node.key);
            }
            if (detailName) {
                detailName.textContent = node.title || fallbackLabel;
            }
            if (detailSlug) {
                detailSlug.textContent = node.data.slug || '-';
            }
            if (detailStatus) {
                detailStatus.textContent = node.data.active ? 'فعال' : 'غیرفعال';
                detailStatus.classList.toggle('text-success', !!node.data.active);
                detailStatus.classList.toggle('text-danger', !node.data.active);
            }
            if (detailSort) {
                detailSort.textContent = typeof node.data.sort !== 'undefined' ? node.data.sort : '-';
            }

            if (btnCreateChild) {
                btnCreateChild.disabled = false;
                btnCreateChild.removeAttribute('aria-disabled');
                btnCreateChild.classList.remove('disabled');
                if (routes.create) {
                    btnCreateChild.href = routes.create + '?parent_id=' + encodeURIComponent(node.data.id);
                }
            }

            if (btnEdit) {
                if (routes.edit) {
                    btnEdit.hidden = false;
                    btnEdit.href = routes.edit.replace('__ID__', node.data.id);
                } else {
                    btnEdit.hidden = true;
                }
            }
        }

        function initTree(data) {
            $(container).rmsFancytree({
                source: data,
                selectMode: 1,
                clickFolderMode: 2,
                debugLevel: 0,
                activate: function (event, ctx) {
                    applyDetails(ctx.node);
                },
                dblclick: function (event, ctx) {
                    if (!ctx.node || !routes.edit) {
                        return;
                    }
                    var url = routes.edit.replace('__ID__', ctx.node.data.id);
                    window.open(url, '_blank', 'noopener');
                }
            });

            treeInstance = $.ui.fancytree.getTree(container);
        }

        function selectInitialNode(nodeId) {
            if (!treeInstance || !nodeId) {
                return;
            }
            var node = treeInstance.getNodeByKey(String(nodeId));
            if (node) {
                node.setActive(true);
                node.makeVisible();
            } else {
                resetDetails();
            }
        }

        function reloadTree(selectedId) {
            if (!endpoint || !treeInstance) {
                selectInitialNode(selectedId);
                return;
            }

            var url = new URL(endpoint, window.location.origin);
            if (selectedId) {
                url.searchParams.set('selected', selectedId);
            }

            fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to load category tree');
                }
                return response.json();
            }).then(function (payload) {
                if (!payload || payload.success !== true || !Array.isArray(payload.data)) {
                    return;
                }
                treeInstance.reload(payload.data).done(function () {
                    selectInitialNode(selectedId);
                });
            }).catch(function (error) {
                console.warn('Shop category tree reload failed:', error);
            });
        }

        resetDetails();
        initTree(initialData);
        selectInitialNode(defaultId);
        reloadTree(defaultId);

        var expandBtn = document.getElementById('btn-expand-all');
        if (expandBtn) {
            expandBtn.addEventListener('click', function () {
                if (!treeInstance) {
                    return;
                }
                treeInstance.visit(function (node) {
                    node.setExpanded(true);
                });
            });
        }

        var collapseBtn = document.getElementById('btn-collapse-all');
        if (collapseBtn) {
            collapseBtn.addEventListener('click', function () {
                if (!treeInstance) {
                    return;
                }
                treeInstance.visit(function (node) {
                    node.setExpanded(false);
                });
            });
        }

        if (btnCreateChild) {
            btnCreateChild.addEventListener('click', function (event) {
                if (btnCreateChild.disabled || btnCreateChild.classList.contains('disabled')) {
                    event.preventDefault();
                    return;
                }
                if (!routes.create) {
                    return;
                }
                event.preventDefault();
                var targetUrl = routes.create;
                if (currentNode && currentNode.data && currentNode.data.id) {
                    targetUrl = routes.create + '?parent_id=' + encodeURIComponent(currentNode.data.id);
                }
                window.location.href = targetUrl;
            });
        }
    });
})(window.jQuery);


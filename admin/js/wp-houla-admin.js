/**
 * WP-Houla Admin JavaScript
 *
 * Handles:
 * - Tab navigation on the settings page
 * - Disconnect AJAX
 * - Batch sync AJAX
 * - Save settings AJAX
 * - Product metabox: sync, unsync, load stats
 * - Post metabox: generate/regenerate shortlink, copy, QR, load click stats
 * - Dashboard widget: overview stats + sparkline
 *
 * @since 1.0.0
 * @package Wp_Houla
 */

(function ($) {
    'use strict';

    // Bail early if our localized data is missing
    if (typeof wphoulaAdmin === 'undefined') {
        return;
    }

    var ajaxUrl = wphoulaAdmin.ajaxUrl;
    var nonce = wphoulaAdmin.nonce;
    var i18n = wphoulaAdmin.i18n;

    // =================================================================
    // Tab navigation
    // =================================================================

    $(document).on('click', '.wphoula-tabs .nav-tab', function (e) {
        e.preventDefault();
        var tab = $(this).data('tab');

        // Update active tab
        $('.wphoula-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Show corresponding content
        $('.wphoula-tab-content').hide();
        $('#tab-' + tab).show();
    });

    // =================================================================
    // Disconnect
    // =================================================================

    $(document).on('click', '#wphoula-disconnect', function () {
        if (!confirm(i18n.confirm)) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text(i18n.disconnecting);

        $.post(ajaxUrl, {
            action: 'wphoula_disconnect',
            nonce: nonce
        }, function (response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data || 'Error');
                $btn.prop('disabled', false).text(i18n.disconnect);
            }
        }).fail(function () {
            alert('Network error');
            $btn.prop('disabled', false).text(i18n.disconnect);
        });
    });

    // =================================================================
    // Save settings
    // =================================================================

    $(document).on('click', '#wphoula-save-settings, .wphoula-save-settings-btn', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);

        var postTypes = [];
        $('.wphoula-post-type:checked').each(function () {
            postTypes.push($(this).val());
        });

        var syncCategories = [];
        $('.wphoula-sync-category:checked').each(function () {
            syncCategories.push($(this).val());
        });

        // Category -> Collection mapping
        var catCollectionMap = {};
        $('.wphoula-collection-select').each(function () {
            var catId = $(this).data('cat-id');
            var collectionId = $(this).val();
            if (catId && collectionId) {
                catCollectionMap[catId] = collectionId;
            }
        });

        var data = {
            action: 'wphoula_save_settings',
            nonce: nonce,
            auto_sync: $('#wphoula-auto-sync').is(':checked') ? 1 : 0,
            sync_on_publish: $('#wphoula-sync-on-publish').is(':checked') ? 1 : 0,
            debug: $('#wphoula-debug').is(':checked') ? 1 : 0,
            api_url: ($('#wphoula-api-url').val() || '').trim(),
            price_adjustment_type: $('#wphoula-price-adj-type').val() || 'none',
            price_adjustment_value: $('#wphoula-price-adj-value').val() || 0,
            sync_tracking: $('#wphoula-sync-tracking').is(':checked') ? 1 : 0,
            'allowed_post_types[]': postTypes,
            'sync_categories[]': syncCategories,
        };

        // Add category_collection_map as nested keys
        for (var catId in catCollectionMap) {
            data['category_collection_map[' + catId + ']'] = catCollectionMap[catId];
        }

        // Add order status concordance map (wc_slug => houla_status)
        $('.wphoula-status-map').each(function () {
            var wcSlug = $(this).data('wc-status');
            var houlaStatus = $(this).val();
            if (wcSlug && houlaStatus) {
                data['order_status_map[' + wcSlug + ']'] = houlaStatus;
            }
        });

        // Add product identifier meta mapping (ean/isbn => meta_key)
        $('.wphoula-id-meta-select').each(function () {
            var idKey = $(this).data('id-key');
            var metaKey = $(this).val();
            data['identifier_meta_map[' + idKey + ']'] = metaKey || '';
        });

        $.post(ajaxUrl, data, function (response) {
            $btn.prop('disabled', false);
            if (response.success) {
                // Brief visual feedback
                $btn.text('OK!');
                setTimeout(function () {
                    $btn.text($btn.data('original') || (i18n.saveSettings || 'Save Settings'));
                }, 1500);
            }
        }).fail(function () {
            $btn.prop('disabled', false);
        });
    });

    // =================================================================
    // Reset sync data
    // =================================================================

    $(document).on('click', '#wphoula-reset-sync', function () {
        if (!confirm(i18n.confirmResetSync || 'This will clear all sync metadata. You will need to re-sync all products. Continue?')) {
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true).text(i18n.loading || 'Loading...');

        $.post(ajaxUrl, {
            action: 'wphoula_reset_sync',
            nonce: nonce
        }, function (resp) {
            if (resp.success) {
                alert(resp.data.message);
                location.reload();
            } else {
                alert(i18n.error || 'Error');
                $btn.prop('disabled', false).text(i18n.resetSync || 'Reset Sync Data');
            }
        }).fail(function () {
            alert(i18n.networkError || 'Network error');
            $btn.prop('disabled', false).text(i18n.resetSync || 'Reset Sync Data');
        });
    });

    // Batch sync (paginated with progress bar)
    // =================================================================

    $(document).on('click', '#wphoula-batch-sync', function () {
        if (shopActive === false) {
            alert(i18n.shopNotActive || 'Your Hou.la shop is not activated. Please connect Stripe on Hou.la first.');
            return;
        }
        var $btn = $(this);
        var $spinner = $('#wphoula-sync-status');
        var $progress = $('#wphoula-sync-progress');
        var $fill = $('#wphoula-progress-fill');
        var $text = $('#wphoula-progress-text');

        $btn.prop('disabled', true);
        $spinner.show().text(i18n.syncing || 'Counting products...');

        // Step 1: count products
        $.post(ajaxUrl, {
            action: 'wphoula_batch_sync_count',
            nonce: nonce
        }, function (countResp) {
            if (!countResp.success) {
                $btn.prop('disabled', false);
                $spinner.text(i18n.syncError || 'Error');
                return;
            }

            var total = countResp.data.total;
            if (total === 0) {
                $btn.prop('disabled', false);
                $spinner.text('0 products');
                setTimeout(function () { $spinner.hide(); }, 3000);
                return;
            }

            // Show progress bar
            $spinner.hide();
            $progress.show();
            $fill.css('width', '0%');
            $text.text('0 / ' + total);

            var page = 1;
            var totalSynced = 0;
            var totalErrors = 0;

            // Step 2: sync page by page
            function syncNextPage() {
                $.post(ajaxUrl, {
                    action: 'wphoula_batch_sync_page',
                    nonce: nonce,
                    page: page
                }, function (resp) {
                    if (!resp.success) {
                        $btn.prop('disabled', false);
                        $text.text(i18n.syncError || 'Error');
                        return;
                    }

                    var d = resp.data;
                    totalSynced += d.synced;
                    totalErrors += d.errors;

                    var pct = Math.min(100, Math.round((totalSynced + totalErrors) / total * 100));
                    $fill.css('width', pct + '%');
                    $text.text(totalSynced + ' / ' + total + (totalErrors > 0 ? ' (' + totalErrors + ' errors)' : ''));

                    if (d.has_more) {
                        page++;
                        syncNextPage();
                    } else {
                        // Done
                        $fill.css('width', '100%');
                        $text.text(totalSynced + ' synced' + (totalErrors > 0 ? ', ' + totalErrors + ' errors' : '') + ' ✓');
                        $btn.prop('disabled', false);
                        setTimeout(function () { $progress.fadeOut(); }, 5000);
                    }
                }).fail(function () {
                    $btn.prop('disabled', false);
                    $text.text(i18n.syncError || 'Error');
                });
            }

            syncNextPage();

        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.text(i18n.syncError || 'Error');
        });
    });

    // =================================================================
    // Product metabox: sync / unsync / stats
    // =================================================================

    function getMetaboxData() {
        var $box = $('.wphoula-metabox');
        return {
            productId: $box.data('product-id'),
            nonce: $box.data('nonce')
        };
    }

    // Sync now (from not-synced state)
    $(document).on('click', '#wphoula-sync-now, #wphoula-resync', function () {
        var $btn = $(this);
        var $spinner = $('#wphoula-spinner');
        var meta = getMetaboxData();

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.post(ajaxUrl, {
            action: 'wphoula_sync_product',
            nonce: meta.nonce,
            product_id: meta.productId
        }, function (response) {
            $spinner.removeClass('is-active');
            if (response.success) {
                location.reload();
            } else {
                $btn.prop('disabled', false);
                alert(response.data || i18n.syncError);
            }
        }).fail(function () {
            $spinner.removeClass('is-active');
            $btn.prop('disabled', false);
        });
    });

    // Unsync
    $(document).on('click', '#wphoula-unsync', function () {
        if (!confirm(i18n.confirm)) {
            return;
        }

        var meta = getMetaboxData();
        var $spinner = $('#wphoula-spinner');
        $spinner.addClass('is-active');

        $.post(ajaxUrl, {
            action: 'wphoula_unsync_product',
            nonce: meta.nonce,
            product_id: meta.productId
        }, function (response) {
            $spinner.removeClass('is-active');
            if (response.success) {
                location.reload();
            }
        }).fail(function () {
            $spinner.removeClass('is-active');
        });
    });

    // Load stats on page load (for synced products)
    $(document).ready(function () {
        var $stats = $('#wphoula-stats');
        if ($stats.length === 0) {
            return;
        }

        var meta = getMetaboxData();

        $.post(ajaxUrl, {
            action: 'wphoula_get_stats',
            nonce: meta.nonce,
            product_id: meta.productId
        }, function (response) {
            if (response.success && response.data) {
                var d = response.data;
                $('#wphoula-stat-views').text(d.views || 0);
                $('#wphoula-stat-clicks').text(d.clicks || 0);
                $('#wphoula-stat-sales').text(d.sales || 0);
                $('#wphoula-stat-revenue').text(
                    d.revenue ? d.currency + ' ' + parseFloat(d.revenue).toFixed(2) : '-'
                );
                $stats.show();
            }
        });
    });

    // =================================================================
    // Post metabox: shortlink + QR (all post types)
    // =================================================================

    function getPostMetaboxData() {
        var $box = $('.wphoula-post-metabox');
        return {
            postId: $box.data('post-id'),
            nonce: $box.data('nonce')
        };
    }

    // Copy shortlink to clipboard
    $(document).on('click', '#wphoula-copy-link', function () {
        var $input = $('#wphoula-shortlink-input');
        if ($input.length) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText($input.val());
            } else {
                $input[0].select();
                document.execCommand('copy');
            }
            var $btn = $(this);
            $btn.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
            setTimeout(function () {
                $btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard');
            }, 1500);
        }
    });

    // Generate shortlink
    $(document).on('click', '#wphoula-generate-link', function () {
        var $btn = $(this);
        var $spinner = $('#wphoula-post-spinner');
        var meta = getPostMetaboxData();

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.post(ajaxUrl, {
            action: 'wphoula_generate_shortlink',
            nonce: meta.nonce,
            post_id: meta.postId,
            force: 0
        }, function (response) {
            $spinner.removeClass('is-active');
            if (response.success) {
                location.reload();
            } else {
                $btn.prop('disabled', false);
                alert(response.data || i18n.syncError);
            }
        }).fail(function () {
            $spinner.removeClass('is-active');
            $btn.prop('disabled', false);
        });
    });

    // Regenerate shortlink
    $(document).on('click', '#wphoula-regenerate-link', function () {
        if (!confirm(i18n.confirm)) {
            return;
        }
        var $btn = $(this);
        var $spinner = $('#wphoula-post-spinner');
        var meta = getPostMetaboxData();

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.post(ajaxUrl, {
            action: 'wphoula_generate_shortlink',
            nonce: meta.nonce,
            post_id: meta.postId,
            force: 1
        }, function (response) {
            $spinner.removeClass('is-active');
            if (response.success) {
                location.reload();
            } else {
                $btn.prop('disabled', false);
            }
        }).fail(function () {
            $spinner.removeClass('is-active');
            $btn.prop('disabled', false);
        });
    });

    // Load post link stats on page load
    $(document).ready(function () {
        var $postStats = $('#wphoula-post-stats');
        if ($postStats.length === 0) {
            return;
        }

        var meta = getPostMetaboxData();

        $.post(ajaxUrl, {
            action: 'wphoula_get_link_stats',
            nonce: meta.nonce,
            post_id: meta.postId
        }, function (response) {
            if (response.success && response.data) {
                var d = response.data;
                $('#wphoula-post-stat-clicks').text(d.totalClicks || d.clicks || 0);
                $('#wphoula-post-stat-today').text(d.clicksToday || 0);
                $('#wphoula-post-stat-qr').text(d.qrScans || 0);
                $postStats.show();

                // Draw 7-day sparkline if data available
                if (d.byDay && d.byDay.length > 0) {
                    drawSparkline(d.byDay);
                }
            }
        });
    });

    // =================================================================
    // Sparkline chart (pure canvas, no dependencies)
    // =================================================================

    function drawSparkline(byDay) {
        var canvas = document.getElementById('wphoula-sparkline');
        if (!canvas || !canvas.getContext) return;

        var $wrapper = $('#wphoula-chart-wrapper');
        $wrapper.show();

        var ctx = canvas.getContext('2d');
        var w = canvas.width;
        var h = canvas.height;
        var padding = 4;

        // Extract click values, sorted by date
        byDay.sort(function (a, b) {
            return a.date < b.date ? -1 : a.date > b.date ? 1 : 0;
        });

        var values = byDay.map(function (d) { return d.clicks || 0; });
        var labels = byDay.map(function (d) {
            var parts = d.date.split('-');
            return parts[2] + '/' + parts[1];
        });
        var maxVal = Math.max.apply(null, values) || 1;
        var n = values.length;

        if (n < 2) return;

        var stepX = (w - padding * 2) / (n - 1);
        var scaleY = (h - padding * 2) / maxVal;

        // Background
        ctx.clearRect(0, 0, w, h);

        // Gradient fill under the line
        var gradient = ctx.createLinearGradient(0, 0, 0, h);
        gradient.addColorStop(0, 'rgba(102, 126, 234, 0.3)');
        gradient.addColorStop(1, 'rgba(102, 126, 234, 0.02)');

        ctx.beginPath();
        ctx.moveTo(padding, h - padding - values[0] * scaleY);
        for (var i = 1; i < n; i++) {
            ctx.lineTo(padding + i * stepX, h - padding - values[i] * scaleY);
        }
        ctx.lineTo(padding + (n - 1) * stepX, h - padding);
        ctx.lineTo(padding, h - padding);
        ctx.closePath();
        ctx.fillStyle = gradient;
        ctx.fill();

        // Line
        ctx.beginPath();
        ctx.moveTo(padding, h - padding - values[0] * scaleY);
        for (var j = 1; j < n; j++) {
            ctx.lineTo(padding + j * stepX, h - padding - values[j] * scaleY);
        }
        ctx.strokeStyle = '#667eea';
        ctx.lineWidth = 2;
        ctx.lineJoin = 'round';
        ctx.stroke();

        // Dots at each data point
        for (var k = 0; k < n; k++) {
            ctx.beginPath();
            ctx.arc(padding + k * stepX, h - padding - values[k] * scaleY, 2.5, 0, Math.PI * 2);
            ctx.fillStyle = '#667eea';
            ctx.fill();
        }

        // Day labels at bottom
        ctx.fillStyle = '#999';
        ctx.font = '9px -apple-system, BlinkMacSystemFont, sans-serif';
        ctx.textAlign = 'center';
        for (var m = 0; m < n; m++) {
            // Only show first, middle, and last label to avoid overlap
            if (m === 0 || m === n - 1 || m === Math.floor(n / 2)) {
                ctx.fillText(labels[m], padding + m * stepX, h - 1);
            }
        }
    }

    // =================================================================
    // Dashboard widget
    // =================================================================

    $(document).ready(function () {
        var $widget = $('.wphoula-dashboard-widget');
        if ($widget.length === 0) return;

        var widgetNonce = $widget.data('nonce');

        $.post(ajaxUrl, {
            action: 'wphoula_dashboard_stats',
            nonce: widgetNonce
        }, function (response) {
            $('#wphoula-dw-loading').hide();

            if (response.success && response.data) {
                var d = response.data;
                $('#wphoula-dw-total-links').text(d.totalLinks || 0);
                $('#wphoula-dw-total-clicks').text(d.totalClicks || 0);
                $('#wphoula-dw-clicks-today').text(d.clicksToday || 0);
                $('#wphoula-dw-content').show();

                // Render top links table
                if (d.topLinks && d.topLinks.length > 0) {
                    var $tbody = $('#wphoula-dw-table tbody');
                    $tbody.empty();
                    for (var i = 0; i < d.topLinks.length; i++) {
                        var link = d.topLinks[i];
                        var title = link.title || link.shortUrl;
                        if (title.length > 35) title = title.substring(0, 35) + '...';
                        $tbody.append(
                            '<tr>' +
                            '<td><a href="' + link.shortUrl + '" target="_blank" title="' + link.shortUrl + '">' + title + '</a></td>' +
                            '<td class="wphoula-stat-value">' + (link.clicks || 0) + '</td>' +
                            '</tr>'
                        );
                    }
                    $('#wphoula-dw-top').show();
                }
            } else {
                $('#wphoula-dw-loading').text(response.data || 'Error loading stats');
                $('#wphoula-dw-loading').show();
            }
        }).fail(function () {
            $('#wphoula-dw-loading').text('Network error');
        });
    });

    // =================================================================
    // Price adjustment UI
    // =================================================================

    $(document).on('change', '#wphoula-price-adj-type', function () {
        var type = $(this).val();
        var $row = $('#wphoula-price-adj-row');
        var $unit = $('#wphoula-price-adj-unit');

        if (type === 'none') {
            $row.hide();
        } else {
            $row.show();
            if (type === 'percent_up' || type === 'percent_down') {
                $unit.text('%');
            } else {
                // Currency symbol from WC (fallback EUR)
                $unit.text(wphoulaAdmin.currencySymbol || 'EUR');
            }
            updatePriceExample();
        }
    });

    $(document).on('input', '#wphoula-price-adj-value', function () {
        updatePriceExample();
    });

    function updatePriceExample() {
        var type = $('#wphoula-price-adj-type').val();
        var value = parseFloat($('#wphoula-price-adj-value').val()) || 0;
        var $example = $('#wphoula-price-example');
        var currency = wphoulaAdmin.currencySymbol || 'EUR';

        if (type === 'none' || value <= 0) {
            $example.text('');
            return;
        }

        var samplePrice = 100;
        var result;

        switch (type) {
            case 'percent_up':
                result = samplePrice * (1 + value / 100);
                $example.text('Example: ' + samplePrice + ' ' + currency + ' -> ' + result.toFixed(2) + ' ' + currency + ' (+' + value + '%)');
                break;
            case 'percent_down':
                result = samplePrice * (1 - value / 100);
                $example.text('Example: ' + samplePrice + ' ' + currency + ' -> ' + result.toFixed(2) + ' ' + currency + ' (-' + value + '%)');
                break;
            case 'fixed_up':
                result = samplePrice + value;
                $example.text('Example: ' + samplePrice + ' ' + currency + ' -> ' + result.toFixed(2) + ' ' + currency + ' (+' + value + ' ' + currency + ')');
                break;
            case 'fixed_down':
                result = Math.max(0, samplePrice - value);
                $example.text('Example: ' + samplePrice + ' ' + currency + ' -> ' + result.toFixed(2) + ' ' + currency + ' (-' + value + ' ' + currency + ')');
                break;
        }
    }

    // =================================================================
    // Sync tab initialisation: load collections + synced products
    // =================================================================

    var collectionsLoaded = false;
    var shopActive = null; // null = unknown, true = active, false = not active

    $(document).on('click', '.wphoula-tabs .nav-tab[data-tab="sync"]', function () {
        if (!collectionsLoaded) {
            collectionsLoaded = true;
            checkShopStatus();
            loadCollections();
            loadSyncedProducts();
            loadIdentifierMetaKeys();
        }
    });

    // If sync tab is already active on page load
    $(document).ready(function () {
        if ($('.wphoula-tabs .nav-tab[data-tab="sync"]').hasClass('nav-tab-active')) {
            collectionsLoaded = true;
            checkShopStatus();
            loadCollections();
            loadSyncedProducts();
            loadIdentifierMetaKeys();
        }

        // Trigger price example on load if value is set
        if ($('#wphoula-price-adj-type').val() !== 'none') {
            updatePriceExample();
        }
    });

    // -----------------------------------------------------------------
    // Check shop activation status
    // -----------------------------------------------------------------

    function checkShopStatus() {
        $.post(ajaxUrl, {
            action: 'wphoula_get_shop_status',
            nonce: nonce
        }, function (response) {
            if (response.success && response.data) {
                shopActive = !!response.data.shopActive;
                if (!shopActive) {
                    $('#wphoula-shop-status-banner').show();
                    // Disable sync buttons
                    $('#wphoula-batch-sync').prop('disabled', true).attr('title', i18n.shopNotActive || 'Shop not activated on Hou.la');
                    $('#wphoula-auto-map').prop('disabled', true).attr('title', i18n.shopNotActive || 'Shop not activated on Hou.la');
                } else {
                    $('#wphoula-shop-status-banner').hide();
                }
            }
        });
    }

    // -----------------------------------------------------------------
    // Load product identifier meta keys (EAN / ISBN)
    // -----------------------------------------------------------------

    function loadIdentifierMetaKeys() {
        $('.wphoula-id-meta-loading').show();

        $.post(ajaxUrl, {
            action: 'wphoula_get_product_meta_keys',
            nonce: nonce
        }, function (response) {
            $('.wphoula-id-meta-loading').hide();

            if (!response.success || !response.data) return;

            var data = response.data;

            $('.wphoula-id-meta-select').each(function () {
                var $select = $(this);
                var idKey = $select.data('id-key'); // 'ean' or 'isbn'
                var savedValue = $('#wphoula-id-meta-' + idKey + '-saved').val();

                // Keep the first default option
                $select.find('option:gt(0)').remove();

                // Add detected keys first (from installed plugins)
                if (data.detected && data.detected.length > 0) {
                    var $group = $('<optgroup></optgroup>').attr('label', i18n.detectedPlugins || 'Detected plugins');
                    for (var i = 0; i < data.detected.length; i++) {
                        var d = data.detected[i];
                        var $opt = $('<option></option>').val(d.key).text(d.label);
                        if (d.key === savedValue) $opt.prop('selected', true);
                        $group.append($opt);
                    }
                    $select.append($group);
                }

                // Add custom (non-underscore) meta keys
                if (data.custom && data.custom.length > 0) {
                    var $group2 = $('<optgroup></optgroup>').attr('label', i18n.customMetaKeys || 'Custom meta fields');
                    for (var j = 0; j < data.custom.length; j++) {
                        var c = data.custom[j];
                        var $opt2 = $('<option></option>').val(c.key).text(c.label);
                        if (c.key === savedValue) $opt2.prop('selected', true);
                        $group2.append($opt2);
                    }
                    $select.append($group2);
                }

                // Add all known plugin keys (even if not installed)
                if (data.known && data.known.length > 0) {
                    var $group3 = $('<optgroup></optgroup>').attr('label', i18n.knownPlugins || 'Known plugins (not detected)');
                    for (var k = 0; k < data.known.length; k++) {
                        var kn = data.known[k];
                        var $opt3 = $('<option></option>').val(kn.key).text(kn.label);
                        if (kn.key === savedValue) $opt3.prop('selected', true);
                        $group3.append($opt3);
                    }
                    $select.append($group3);
                }

                // If saved value not in any list, add it manually
                if (savedValue && $select.val() !== savedValue) {
                    var $manual = $('<option></option>').val(savedValue).text(savedValue).prop('selected', true);
                    $select.prepend($manual);
                }
            });
        }).fail(function () {
            $('.wphoula-id-meta-loading').hide();
        });
    }

    // -----------------------------------------------------------------
    // Load collections and populate dropdowns
    // -----------------------------------------------------------------

    function loadCollections() {
        $.post(ajaxUrl, {
            action: 'wphoula_get_collections',
            nonce: nonce
        }, function (response) {
            if (!response.success || !response.data) return;

            var collections = response.data;

            $('.wphoula-collection-select').each(function () {
                var $select = $(this);
                var $hidden = $select.closest('td').find('.wphoula-collection-value');
                var savedValue = $hidden.val();

                // Keep the first "- Not mapped -" option
                $select.find('option:gt(0)').remove();

                for (var i = 0; i < collections.length; i++) {
                    var c = collections[i];
                    var $opt = $('<option></option>')
                        .val(c.id)
                        .text(c.title);
                    if (c.id === savedValue) {
                        $opt.prop('selected', true);
                    }
                    $select.append($opt);
                }
            });
        });
    }

    // -----------------------------------------------------------------
    // Load synced products
    // -----------------------------------------------------------------

    function loadSyncedProducts() {
        var $loading = $('#wphoula-synced-products-loading');
        var $table = $('#wphoula-synced-products');
        var $empty = $('#wphoula-synced-products-empty');

        $loading.show();
        $table.hide();
        $empty.hide();

        $.post(ajaxUrl, {
            action: 'wphoula_get_synced_products',
            nonce: nonce
        }, function (response) {
            $loading.hide();

            if (!response.success || !response.data || response.data.length === 0) {
                $empty.show();
                return;
            }

            var products = response.data;
            var $tbody = $table.find('tbody');
            $tbody.empty();

            for (var i = 0; i < products.length; i++) {
                var p = products[i];
                var statusClass = 'wphoula-status-draft';
                var statusLabel = p.status || 'draft';
                if (p.status === 'active') {
                    statusClass = 'wphoula-status-active';
                    statusLabel = 'Active';
                } else if (p.status === 'draft') {
                    statusLabel = 'Draft';
                } else if (p.status === 'out_of_stock') {
                    statusLabel = 'Rupture';
                    statusClass = 'wphoula-status-oos';
                }
                var stockLabel = (p.stock !== undefined && p.stock !== null) ? p.stock : '-';
                var wcPrice = p.wc_price ? p.wc_price : '-';
                var houlaPrice = p.price ? p.price : '-';
                var synced = p.last_synced ? p.last_synced : '-';
                var name = p.name || p.title || 'Unknown';

                $tbody.append(
                    '<tr>' +
                    '<td>' + (i + 1) + '</td>' +
                    '<td>' + escapeHtml(name) + '</td>' +
                    '<td>' + escapeHtml(String(wcPrice)) + '</td>' +
                    '<td>' + escapeHtml(String(houlaPrice)) + '</td>' +
                    '<td><span class="' + statusClass + '">' + escapeHtml(statusLabel) + '</span></td>' +
                    '<td>' + escapeHtml(String(stockLabel)) + '</td>' +
                    '<td>' + escapeHtml(synced) + '</td>' +
                    '</tr>'
                );
            }

            $table.show();
        }).fail(function () {
            $loading.hide();
            $empty.show();
        });
    }

    // Utility: escape HTML
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // -----------------------------------------------------------------
    // Auto-map collections button
    // -----------------------------------------------------------------

    $(document).on('click', '#wphoula-auto-map', function () {
        var $btn = $(this);
        var $status = $('#wphoula-automap-status');

        $btn.prop('disabled', true);
        $status.show().text(i18n.creatingCollections || 'Creating collections...').css('color', '#666');

        $.post(ajaxUrl, {
            action: 'wphoula_auto_map_collections',
            nonce: nonce
        }, function (response) {
            $btn.prop('disabled', false);

            if (response.success && response.data) {
                var d = response.data;
                $status.text((d.created || 0) + (i18n.collectionsCreated || ' collections created, ') + (d.total || 0) + (i18n.collectionsMapped || ' mapped.')).css('color', '#46b450');

                // Update all dropdowns with the new mapping
                if (d.mapping) {
                    // Reload collections to show newly created ones
                    loadCollections();

                    // After slight delay (to let loadCollections finish), set dropdown values
                    setTimeout(function () {
                        for (var catId in d.mapping) {
                            var collectionId = d.mapping[catId];
                            var $select = $('.wphoula-collection-select[data-cat-id="' + catId + '"]');
                            if ($select.length) {
                                $select.val(collectionId);
                                $select.closest('td').find('.wphoula-collection-value').val(collectionId);
                            }
                        }
                    }, 1500);
                }

                setTimeout(function () { $status.fadeOut(); }, 5000);
            } else {
                $status.text(response.data || 'Error').css('color', '#dc3232');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $status.text('Network error').css('color', '#dc3232');
        });
    });

})(jQuery);

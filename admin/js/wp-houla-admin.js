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

    $(document).on('click', '#wphoula-save-settings', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);

        var postTypes = [];
        $('.wphoula-post-type:checked').each(function () {
            postTypes.push($(this).val());
        });

        $.post(ajaxUrl, {
            action: 'wphoula_save_settings',
            nonce: nonce,
            auto_sync: $('#wphoula-auto-sync').is(':checked') ? 1 : 0,
            sync_on_publish: $('#wphoula-sync-on-publish').is(':checked') ? 1 : 0,
            debug: $('#wphoula-debug').is(':checked') ? 1 : 0,
            'allowed_post_types[]': postTypes
        }, function (response) {
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
    // Batch sync
    // =================================================================

    $(document).on('click', '#wphoula-batch-sync', function () {
        var $btn = $(this);
        var $spinner = $('#wphoula-sync-status');

        $btn.prop('disabled', true);
        $spinner.show().text(i18n.syncing);

        $.post(ajaxUrl, {
            action: 'wphoula_batch_sync',
            nonce: nonce
        }, function (response) {
            $btn.prop('disabled', false);
            if (response.success) {
                var data = response.data;
                $spinner.text(
                    data.synced + ' synced, ' + data.errors + ' errors'
                );
                setTimeout(function () {
                    $spinner.hide();
                }, 5000);
            } else {
                $spinner.text(i18n.syncError);
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.text(i18n.syncError);
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

})(jQuery);

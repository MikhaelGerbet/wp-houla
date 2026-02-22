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

        $.post(ajaxUrl, {
            action: 'wphoula_save_settings',
            nonce: nonce,
            auto_sync: $('#wphoula-auto-sync').is(':checked') ? 1 : 0,
            sync_on_publish: $('#wphoula-sync-on-publish').is(':checked') ? 1 : 0,
            debug: $('#wphoula-debug').is(':checked') ? 1 : 0
        }, function (response) {
            $btn.prop('disabled', false);
            if (response.success) {
                // Brief visual feedback
                $btn.text('OK!');
                setTimeout(function () {
                    $btn.text($btn.data('original') || 'Save Settings');
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
            $input[0].select();
            document.execCommand('copy');
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
            }
        });
    });

})(jQuery);

/* global jQuery, LLMSCLICK */
(function ($) {
    'use strict';

    var $key;

    function post(action, data) {
        data = data || {};
        data.action = action;
        data.nonce = LLMSCLICK.nonce;
        return $.post(LLMSCLICK.ajax, data);
    }

    function status($el, msg, type) {
        $el.removeClass('ok err busy').addClass(type || '').text(msg || '');
    }

    function esc(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }

    // ── Verifica e salva la chiave ──────────────────────────────────
    function validateKey() {
        var $s = $('#llmsclick-key-status');
        status($s, LLMSCLICK.i18n.validating, 'busy');

        post('llmsclick_save_settings', {
            api_key: $key.val()
        }).done(function () {
            post('llmsclick_validate_key', { api_key: $key.val() })
                .done(function (r) {
                    if (r.success) {
                        status($s, r.data.message || 'OK', 'ok');
                    } else {
                        status($s, (r.data && r.data.message) || LLMSCLICK.i18n.error, 'err');
                    }
                })
                .fail(function () { status($s, LLMSCLICK.i18n.error, 'err'); });
        }).fail(function () { status($s, LLMSCLICK.i18n.error, 'err'); });
    }

    // ── Carica i fix dal server ─────────────────────────────────────
    function loadFixes() {
        var $s = $('#llmsclick-load-status');
        status($s, LLMSCLICK.i18n.loading, 'busy');
        $('#llmsclick-fixes').empty();
        $('#llmsclick-warnings').empty();
        $('#llmsclick-score').empty();

        // Salva prima target/lang correnti.
        post('llmsclick_save_settings', {
            api_key: $key.val()
        }).always(function () {
            post('llmsclick_fetch_fixes').done(function (r) {
                if (!r.success) {
                    var m = (r.data && r.data.message) || LLMSCLICK.i18n.error;
                    if (r.data && r.data.upgrade_url) {
                        m += ' ';
                    }
                    status($s, m, 'err');
                    if (r.data && r.data.upgrade_url) {
                        $('#llmsclick-warnings').html(
                            '<div class="notice notice-warning"><p>' + esc(m) +
                            ' <a href="' + esc(r.data.upgrade_url) + '" target="_blank" rel="noopener">' +
                            'Upgrade your plan →</a></p></div>'
                        );
                    }
                    return;
                }
                status($s, '', '');
                renderResult(r.data);
            }).fail(function () { status($s, LLMSCLICK.i18n.error, 'err'); });
        });
    }

    function renderWarnings(w) {
        if (!w) { return; }
        var html = '';
        if (w.seo_plugin) {
            html += '<div class="notice notice-info inline"><p><strong>' + esc(w.seo_plugin) +
                '</strong> detected. The fixes for title, meta description, Open Graph and schema are ' +
                'handled by your SEO plugin: we show them as suggestions but do not apply them ' +
                'to avoid duplicates.</p></div>';
        }
        if (w.physical_robots) {
            html += '<div class="notice notice-warning inline"><p>A physical <code>robots.txt</code> ' +
                'file exists in the site root: WordPress (and therefore this plugin) cannot modify it. ' +
                'Add the AI-bot directives manually, or remove the physical file.</p></div>';
        }
        $('#llmsclick-warnings').html(html);
    }

    function renderScore(score) {
        if (!score) { return; }
        $('#llmsclick-score').html(
            '<div class="llmsclick-score">Current score: <strong>' +
            esc(score.total) + '/' + esc(score.max) + '</strong> (' + esc(score.grade) + ')</div>'
        );
    }

    var BUCKET_LABELS = {
        head: 'Site head (JSON-LD, Open Graph, meta)',
        file: 'Files (robots.txt, llms.txt, sitemap)',
        body: 'Page content (FAQ, structure)',
        server: 'Server (HTTPS, 404)'
    };

    function renderResult(data) {
        renderWarnings(data.warnings);
        renderScore(data.score);

        var fixes = data.fixes || {};
        var $wrap = $('#llmsclick-fixes').empty();
        var order = ['head', 'file', 'body', 'server'];
        var any = false;

        order.forEach(function (bucket) {
            var list = fixes[bucket] || [];
            if (!list.length) { return; }
            any = true;
            var $sec = $('<div class="llmsclick-bucket">');
            $sec.append('<h3>' + esc(BUCKET_LABELS[bucket] || bucket) + '</h3>');
            list.forEach(function (fix) {
                $sec.append(renderFix(fix));
            });
            $wrap.append($sec);
        });

        if (!any) {
            $wrap.html('<p>' + esc('No fixes to apply: your site is already in good shape on these checks. 🎉') + '</p>');
        }
    }

    function renderFix(fix) {
        var $card = $('<div class="llmsclick-fix">').attr('data-check', fix.check_id);
        if (fix.enabled) { $card.addClass('is-enabled'); }

        var $head = $('<div class="llmsclick-fix-head">');
        $head.append('<span class="llmsclick-fix-title">' + esc(fix.title) + '</span>');
        if (fix.needs_review) {
            $head.append('<span class="llmsclick-badge review">' + esc(LLMSCLICK.i18n.review) + '</span>');
        }
        if (fix.kind === 'guide' || !fix.applicable) {
            $head.append('<span class="llmsclick-badge guide">Guide</span>');
        }
        $card.append($head);

        if (fix.location) {
            $card.append('<div class="llmsclick-fix-loc">' + esc(fix.location) + '</div>');
        }
        if (fix.instructions) {
            $card.append('<div class="llmsclick-fix-instr">' + esc(fix.instructions) + '</div>');
        }

        if (fix.code) {
            var $pre = $('<pre class="llmsclick-code">').text(fix.code);
            var $toggle = $('<a href="#" class="llmsclick-code-toggle">Show code</a>');
            var $box = $('<div class="llmsclick-code-box" hidden>').append($pre);
            $toggle.on('click', function (e) {
                e.preventDefault();
                $box.prop('hidden', !$box.prop('hidden'));
                $(this).text($box.prop('hidden') ? 'Show code' : 'Hide code');
            });
            $card.append($toggle).append($box);
        }

        // Pulsante apply/remove solo per i fix applicabili automaticamente.
        if (fix.applicable) {
            var $btn = $('<button type="button" class="button">');
            $btn.text(fix.enabled ? LLMSCLICK.i18n.remove : LLMSCLICK.i18n.apply);
            if (fix.enabled) { $btn.addClass('llmsclick-on'); }
            $btn.on('click', function () { toggleFix(fix.check_id, !$card.hasClass('is-enabled'), $card, $btn); });
            $card.append($('<div class="llmsclick-fix-actions">').append($btn));
        }

        return $card;
    }

    function toggleFix(check, enable, $card, $btn) {
        $btn.prop('disabled', true).addClass('busy');
        post('llmsclick_toggle_fix', { check: check, enable: enable ? '1' : '0' })
            .done(function (r) {
                if (r.success) {
                    $card.toggleClass('is-enabled', enable);
                    $btn.toggleClass('llmsclick-on', enable)
                        .text(enable ? LLMSCLICK.i18n.remove : LLMSCLICK.i18n.apply);
                } else {
                    window.alert((r.data && r.data.message) || LLMSCLICK.i18n.error);
                }
            })
            .fail(function () { window.alert(LLMSCLICK.i18n.error); })
            .always(function () { $btn.prop('disabled', false).removeClass('busy'); });
    }

    $(function () {
        $key = $('#llmsclick-key');
        $('#llmsclick-validate').on('click', validateKey);
        $('#llmsclick-load').on('click', loadFixes);
    });
})(jQuery);

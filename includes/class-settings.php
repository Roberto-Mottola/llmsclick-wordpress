<?php
/**
 * LlmsClick_Settings - pannello admin (Impostazioni -> llms.click) e handler AJAX.
 *
 * Sicurezza: ogni azione richiede current_user_can('manage_options') + nonce.
 * La API key e' salvata in wp_options (autoload off) e mai esposta nel front-end.
 */

if (!defined('ABSPATH')) { exit; }

class LlmsClick_Settings {

    const NONCE = 'llmsclick_admin';

    public function register(): void {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);

        add_action('wp_ajax_llmsclick_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_llmsclick_validate_key',  [$this, 'ajax_validate_key']);
        add_action('wp_ajax_llmsclick_fetch_fixes',   [$this, 'ajax_fetch_fixes']);
        add_action('wp_ajax_llmsclick_toggle_fix',     [$this, 'ajax_toggle_fix']);

        // Link "Impostazioni" nella riga del plugin.
        add_filter('plugin_action_links_' . plugin_basename(LLMSCLICK_FILE), [$this, 'action_links']);
    }

    public function menu(): void {
        add_options_page(
            'llms.click',
            'llms.click',
            'manage_options',
            'llmsclick',
            [$this, 'render_page']
        );
    }

    public function action_links($links) {
        $url = admin_url('options-general.php?page=llmsclick');
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'llmsclick') . '</a>');
        return $links;
    }

    public function assets($hook): void {
        if ($hook !== 'settings_page_llmsclick') { return; }
        wp_enqueue_style('llmsclick-admin', LLMSCLICK_URL . 'admin/admin.css', [], LLMSCLICK_VERSION);
        wp_enqueue_script('llmsclick-admin', LLMSCLICK_URL . 'admin/admin.js', ['jquery'], LLMSCLICK_VERSION, true);
        wp_localize_script('llmsclick-admin', 'LLMSCLICK', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE),
            'i18n'  => [
                'validating' => __('Verifying...', 'llmsclick'),
                'loading'    => __('Loading fixes from your site...', 'llmsclick'),
                'applied'    => __('Applied', 'llmsclick'),
                'apply'      => __('Apply', 'llmsclick'),
                'remove'     => __('Remove', 'llmsclick'),
                'error'      => __('Error', 'llmsclick'),
                'review'     => __('Review before publishing', 'llmsclick'),
            ],
        ]);
    }

    // ── Render ────────────────────────────────────────────────────────

    public function render_page(): void {
        if (!current_user_can('manage_options')) { return; }
        require LLMSCLICK_DIR . 'admin/settings-page.php';
    }

    // ── AJAX ────────────────────────────────────────────────────────────

    private function guard(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Not authorized.', 'llmsclick')], 403);
        }
        if (!check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid nonce. Please reload the page.', 'llmsclick')], 403);
        }
    }

    public function ajax_save_settings(): void {
        $this->guard();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in guard() via check_ajax_referer().
        $key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';

        if ($key !== '') { update_option(LLMSCLICK_OPT_KEY, $key, false); }

        wp_send_json_success(['message' => __('Settings saved.', 'llmsclick')]);
    }

    public function ajax_validate_key(): void {
        $this->guard();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in guard() via check_ajax_referer().
        $key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        if ($key === '') { $key = (string) get_option(LLMSCLICK_OPT_KEY, ''); }
        if ($key === '') {
            wp_send_json_error(['message' => __('Enter an API key.', 'llmsclick')]);
        }
        $api = new LlmsClick_Api($key);
        $res = $api->validate_key($this->target_url());
        if (!empty($res['ok'])) {
            wp_send_json_success($res);
        }
        wp_send_json_error($res);
    }

    public function ajax_fetch_fixes(): void {
        $this->guard();
        $api = new LlmsClick_Api();
        if (!$api->has_key()) {
            wp_send_json_error(['message' => __('Configure the API key first.', 'llmsclick')]);
        }
        $data = $api->fetch_fixes($this->target_url());
        if (is_wp_error($data)) {
            wp_send_json_error(['message' => $data->get_error_message()]);
        }
        if (!empty($data['locked'])) {
            wp_send_json_error([
                'message'     => __('Your plan does not include fixes. Silver or higher is required.', 'llmsclick'),
                'upgrade_url' => LLMSCLICK_API_BASE . '/pricing',
                'locked'      => true,
            ]);
        }

        // Arricchisce ogni fix con lo stato corrente (abilitato/applicabile).
        $enabled = LlmsClick_Applier::enabled_fixes();
        foreach (($data['fixes'] ?? []) as $bucket => &$list) {
            foreach ($list as &$fix) {
                $cid = $fix['check_id'] ?? '';
                $fix['enabled']    = array_key_exists($cid, $enabled);
                $fix['applicable'] = in_array($cid, LlmsClick_Applier::APPLICABLE, true);
            }
        }
        unset($list, $fix);

        // Diagnostica conflitti per il pannello.
        $data['warnings'] = [
            'seo_plugin'      => LlmsClick_Applier::active_seo_plugin(),
            'physical_robots' => LlmsClick_Applier::physical_robots_exists(),
        ];

        wp_send_json_success($data);
    }

    public function ajax_toggle_fix(): void {
        $this->guard();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in guard() via check_ajax_referer().
        $check  = isset($_POST['check']) ? sanitize_key(wp_unslash($_POST['check'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in guard() via check_ajax_referer().
        $enable = isset($_POST['enable']) && '1' === ( isset($_POST['enable']) ? sanitize_key(wp_unslash($_POST['enable'])) : '' );

        if ($check === '') {
            wp_send_json_error(['message' => __('Missing check.', 'llmsclick')]);
        }

        if ($enable) {
            // Recupera il payload fresco dall'API (mai fidarsi di dati dal browser).
            $api  = new LlmsClick_Api();
            $data = $api->fetch_fixes($this->target_url());
            if (is_wp_error($data)) {
                wp_send_json_error(['message' => $data->get_error_message()]);
            }
            $found = null;
            foreach (($data['fixes'] ?? []) as $list) {
                foreach ($list as $fix) {
                    if (($fix['check_id'] ?? '') === $check) { $found = $fix; break 2; }
                }
            }
            if (!$found) {
                wp_send_json_error(['message' => __('Fix not available for this site.', 'llmsclick')]);
            }
            if (!LlmsClick_Applier::enable($check, $found)) {
                wp_send_json_error(['message' => __('This fix cannot be applied automatically (it is a guide).', 'llmsclick')]);
            }
        } else {
            LlmsClick_Applier::disable($check);
        }

        LlmsClick_Applier::flush_caches();
        wp_send_json_success(['enabled' => $enable, 'check' => $check]);
    }

    /** Il sito da analizzare e' SEMPRE questo (il plugin gira sul dominio da ottimizzare). */
    private function target_url(): string {
        return home_url('/');
    }
}

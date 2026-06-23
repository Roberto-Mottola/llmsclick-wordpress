<?php
/**
 * LlmsClick_Applier - stato dei fix abilitati + output di contenuto (FAQ).
 *
 * I fix abilitati sono salvati in una sola option (LLMSCLICK_OPT_FIXES) come
 * mappa check_id => fix array. Abilitare = salvare; disabilitare = rimuovere.
 * Idempotente: riapplicare non duplica nulla (e' sempre la stessa chiave).
 */

if (!defined('ABSPATH')) { exit; }

class LlmsClick_Applier {

    /** Check che il plugin sa applicare automaticamente (gli altri sono guide). */
    const APPLICABLE = [
        // head
        'schema_present', 'schema_richness', 'open_graph', 'title', 'meta_description', 'freshness',
        // file
        'ai_bot_directives', 'sitemap', 'llms_txt',
        // body (via shortcode/blocco)
        'aeo_structure',
    ];

    public static function enabled_fixes(): array {
        $f = get_option(LLMSCLICK_OPT_FIXES, []);
        return is_array($f) ? $f : [];
    }

    public static function is_enabled(string $check): bool {
        return array_key_exists($check, self::enabled_fixes());
    }

    public static function get_fix(string $check): ?array {
        $f = self::enabled_fixes();
        return $f[$check] ?? null;
    }

    /** Fix abilitati di un certo bucket (head/file/body). */
    public static function by_bucket(string $bucket): array {
        $out = [];
        foreach (self::enabled_fixes() as $check => $fix) {
            if (($fix['bucket'] ?? '') === $bucket) {
                $out[$check] = $fix;
            }
        }
        return $out;
    }

    /**
     * Abilita un fix salvandone il payload. Solo i check applicabili.
     * Il payload arriva dall'API autenticata; qui lo si normalizza e basta.
     */
    public static function enable(string $check, array $fix): bool {
        if (!in_array($check, self::APPLICABLE, true)) {
            return false;
        }
        $fixes = self::enabled_fixes();
        $fixes[$check] = [
            'check_id'     => $check,
            'bucket'       => sanitize_key($fix['bucket'] ?? 'body'),
            'kind'         => sanitize_key($fix['kind'] ?? 'template'),
            'title'        => sanitize_text_field($fix['title'] ?? ''),
            'location'     => sanitize_text_field($fix['location'] ?? ''),
            // 'code' e' markup (JSON-LD / meta / FAQ): non si passa da sanitize_text_field,
            // verrebbe distrutto. Si conserva grezzo; l'output lo filtra per tipo (vedi Head/Files).
            'code'         => (string) ($fix['code'] ?? ''),
            'needs_review' => !empty($fix['needs_review']),
            'saved_at'     => time(),
        ];
        return update_option(LLMSCLICK_OPT_FIXES, $fixes, false);
    }

    public static function disable(string $check): bool {
        $fixes = self::enabled_fixes();
        if (!isset($fixes[$check])) { return true; }
        unset($fixes[$check]);
        return update_option(LLMSCLICK_OPT_FIXES, $fixes, false);
    }

    // ── Rilevamento conflitti con plugin SEO ──────────────────────────

    /** Plugin SEO noti attivi (gestiscono gia' title/meta/schema/OG). */
    public static function active_seo_plugin(): ?string {
        // I controlli defined() coprono il caso comune (plugin caricato).
        if (defined('WPSEO_VERSION'))      return 'Yoast SEO';
        if (defined('RANK_MATH_VERSION'))  return 'Rank Math';
        if (defined('SEOPRESS_VERSION'))   return 'SEOPress';
        if (defined('AIOSEO_VERSION'))     return 'All in One SEO';

        // Fallback via is_plugin_active (non sempre caricato fuori da wp-admin).
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (is_plugin_active('wordpress-seo/wp-seo.php'))                       return 'Yoast SEO';
        if (is_plugin_active('seo-by-rank-math/rank-math.php'))                 return 'Rank Math';
        if (is_plugin_active('wp-seopress/seopress.php'))                      return 'SEOPress';
        if (is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php'))    return 'All in One SEO';
        return null;
    }

    /** True se un robots.txt FISICO esiste nella root (il filtro WP verrebbe ignorato). */
    public static function physical_robots_exists(): bool {
        if (!function_exists('get_home_path')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $root = function_exists('get_home_path') ? get_home_path() : ABSPATH;
        return file_exists(rtrim($root, '/\\') . '/robots.txt');
    }

    /** Svuota le cache dei plugin di caching piu' diffusi dopo un apply. */
    public static function flush_caches(): void {
        if (function_exists('wp_cache_flush')) { wp_cache_flush(); }
        if (function_exists('rocket_clean_domain')) { rocket_clean_domain(); }           // WP Rocket
        if (class_exists('LiteSpeed\\Purge')) { do_action('litespeed_purge_all'); }       // LiteSpeed
        if (function_exists('w3tc_flush_all')) { w3tc_flush_all(); }                      // W3 Total Cache
        if (has_action('cache_enabler_clear_complete_cache')) { do_action('cache_enabler_clear_complete_cache'); }
    }

    // ── Output di contenuto: shortcode + blocco FAQ ───────────────────

    public static function register_content_outputs(): void {
        add_shortcode('llmsclick_faq', [__CLASS__, 'render_faq_shortcode']);

        // Blocco dinamico server-rendered (nessun build step: editor script inline).
        if (function_exists('register_block_type')) {
            register_block_type('llmsclick/faq', [
                'render_callback' => [__CLASS__, 'render_faq_block'],
                'api_version'     => 2,
            ]);
            add_action('enqueue_block_editor_assets', [__CLASS__, 'enqueue_block_editor']);
        }
    }

    public static function render_faq_shortcode($atts = []): string {
        return self::faq_html();
    }

    public static function render_faq_block($attrs = []): string {
        return self::faq_html();
    }

    /**
     * HTML del fix aeo_structure (paragrafo answer-first + FAQ + FAQPage JSON-LD).
     * Il contenuto e' generato dall'API sui dati reali; viene mostrato cosi' com'e'.
     */
    public static function faq_html(): string {
        $fix = self::get_fix('aeo_structure');
        if (!$fix || empty($fix['code'])) {
            if (current_user_can('manage_options')) {
                return '<!-- llms.click: nessun blocco FAQ configurato. Abilita il fix "answer-ready" nelle impostazioni del plugin. -->';
            }
            return '';
        }
        // Il code contiene HTML + uno <script type="application/ld+json"> sicuro.
        // Lo si stampa nel body (JSON-LD valido anche fuori dal head).
        $allowed = self::faq_allowed_html();
        $html = wp_kses($fix['code'], $allowed);

        // wp_kses rimuove <script>: re-iniettiamo solo il blocco ld+json validato.
        if (preg_match('#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#is', $fix['code'], $m)) {
            $json = trim($m[1]);
            if (json_decode($json) !== null) {
                $html .= "\n<script type=\"application/ld+json\">" . $json . "</script>";
            }
        }
        return $html;
    }

    /** Whitelist tag per il corpo FAQ. */
    private static function faq_allowed_html(): array {
        return [
            'section' => [], 'div' => [], 'p' => [],
            'h2' => [], 'h3' => [], 'h4' => [],
            'ul' => [], 'ol' => [], 'li' => [],
            'strong' => [], 'em' => [], 'br' => [],
            'a' => ['href' => [], 'rel' => [], 'title' => []],
        ];
    }

    public static function enqueue_block_editor(): void {
        $handle = 'llmsclick-block';
        wp_register_script($handle, false, ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'], LLMSCLICK_VERSION, true);
        $js = "( function( blocks, el, blockEditor ) {\n"
            . "    blocks.registerBlockType( 'llmsclick/faq', {\n"
            . "        title: 'llms.click FAQ',\n"
            . "        icon: 'editor-help',\n"
            . "        category: 'widgets',\n"
            . "        edit: function() {\n"
            . "            return el( 'div', { className: 'llmsclick-faq-placeholder',\n"
            . "                style: { padding: '12px', border: '1px dashed #888', borderRadius: '6px' } },\n"
            . "                'llms.click answer-ready FAQ block. The content is generated from your real site data and rendered on the front-end.' );\n"
            . "        },\n"
            . "        save: function() { return null; }\n"
            . "    } );\n"
            . "} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );";
        wp_add_inline_script($handle, $js);
        wp_enqueue_script($handle);
    }
}

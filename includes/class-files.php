<?php
/**
 * LlmsClick_Files - serve /llms.txt (rewrite rule) e inietta le direttive
 * AI-bot nel robots.txt (filtro nativo WP, append non-distruttivo).
 */

if (!defined('ABSPATH')) { exit; }

class LlmsClick_Files {

    const QV = 'llmsclick_llmstxt';

    public function register(): void {
        $this->add_rewrite_rules();
        add_filter('query_vars', [$this, 'query_vars']);
        // Priorita' 0: intercetta /llms.txt PRIMA di redirect_canonical (priorita' 10),
        // cosi' funziona anche con permalink "plain" e senza slash finale.
        add_action('template_redirect', [$this, 'maybe_serve_llmstxt'], 0);
        add_filter('robots_txt', [$this, 'filter_robots'], 20, 2);
    }

    /** True se la richiesta corrente e' per /llms.txt (con o senza slash). */
    private function is_llmstxt_request(): bool {
        if ((int) get_query_var(self::QV) === 1) { return true; }
        $path = strtolower(trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/'));
        return $path === 'llms.txt';
    }

    public function add_rewrite_rules(): void {
        add_rewrite_rule('^llms\.txt$', 'index.php?' . self::QV . '=1', 'top');
    }

    public function query_vars($vars) {
        $vars[] = self::QV;
        return $vars;
    }

    /**
     * Serve /llms.txt se il fix e' abilitato. Usa la rewrite rule (portabile,
     * non richiede permessi di scrittura sulla root).
     */
    public function maybe_serve_llmstxt(): void {
        if (!$this->is_llmstxt_request()) { return; }

        $fix = LlmsClick_Applier::get_fix('llms_txt');
        if (!$fix || empty($fix['code'])) {
            status_header(404);
            exit;
        }
        // Il contenuto e' testo semplice generato dall'API: nessun markup attivo.
        $body = wp_strip_all_tags((string) $fix['code']);
        status_header(200);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex'); // il file stesso non va indicizzato
        echo $body;
        exit;
    }

    /**
     * Append delle direttive AI-bot al robots.txt virtuale di WordPress.
     * NON sovrascrive: aggiunge in coda. Se esiste un robots.txt FISICO,
     * questo filtro non viene chiamato da WP (lo segnaliamo nel pannello).
     */
    public function filter_robots($output, $public) {
        if (!LlmsClick_Applier::is_enabled('ai_bot_directives')) {
            return $output;
        }
        $fix = LlmsClick_Applier::get_fix('ai_bot_directives');
        if (!$fix || empty($fix['code'])) {
            return $output;
        }
        $directives = wp_strip_all_tags((string) $fix['code']);
        return rtrim($output) . "\n\n# --- llms.click: direttive AI-bot ---\n" . $directives . "\n";
    }
}

<?php
/**
 * LlmsClick_Api - client HTTP verso llms.click.
 *
 * Server-to-server via wp_remote_get (no CORS). Autenticazione con header
 * X-API-Key. Nessuna logica premium: si limita a chiamare e restituire JSON.
 */

if (!defined('ABSPATH')) { exit; }

class LlmsClick_Api {

    private string $key;

    public function __construct(?string $key = null) {
        $this->key = $key !== null ? $key : (string) get_option(LLMSCLICK_OPT_KEY, '');
    }

    public function has_key(): bool {
        return $this->key !== '';
    }

    /**
     * Verifica la chiave con una chiamata leggera (single check su un URL minimo).
     * Ritorna ['ok'=>bool, 'plan'=>?string, 'locked'=>bool, 'message'=>string].
     */
    public function validate_key(string $target_url): array {
        $res = $this->get('/api/fix', [
            'url'   => $target_url,
            'check' => 'schema_present',
            'lang'  => $this->lang(),
        ]);

        if (is_wp_error($res)) {
            return ['ok' => false, 'message' => $res->get_error_message()];
        }
        $code = $res['code'];
        $body = $res['body'];

        if ($code === 401) {
            return ['ok' => false, 'message' => __('Chiave non valida o scaduta.', 'llmsclick')];
        }
        if (!empty($body['locked'])) {
            return [
                'ok'      => true,
                'locked'  => true,
                'plan'    => $body['plan'] ?? 'free',
                'message' => __('Chiave valida, ma il piano non include i fix. Serve Silver o superiore.', 'llmsclick'),
            ];
        }
        if ($code === 403 && !empty($body['domain_limit'])) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    /* translators: %d numero massimo domini */
                    __('Questa chiave ha raggiunto il numero massimo di domini consentiti dal piano (%d). Usa una chiave dedicata a questo sito o aggiorna il piano.', 'llmsclick'),
                    (int) ($body['limit'] ?? 1)
                ),
            ];
        }
        if ($code >= 200 && $code < 300 && !empty($body['fix'])) {
            return ['ok' => true, 'locked' => false, 'message' => __('Chiave valida e attiva.', 'llmsclick')];
        }
        return ['ok' => false, 'message' => $body['error'] ?? __('Risposta inattesa dal server.', 'llmsclick')];
    }

    /**
     * Recupera tutti i fix applicabili per l'URL (endpoint aggregato).
     * Ritorna l'array decodificato oppure WP_Error.
     */
    public function fetch_fixes(string $target_url) {
        $res = $this->get('/api/plugin-fixes', [
            'url'  => $target_url,
            'lang' => $this->lang(),
        ]);
        if (is_wp_error($res)) {
            return $res;
        }
        if ($res['code'] === 401) {
            return new WP_Error('llmsclick_auth', __('Chiave non valida o scaduta.', 'llmsclick'));
        }
        if ($res['code'] === 403 && !empty($res['body']['domain_limit'])) {
            return new WP_Error('llmsclick_domain', __('Dominio non autorizzato per questa chiave API.', 'llmsclick'));
        }
        if ($res['code'] === 429) {
            return new WP_Error('llmsclick_rate', __('Troppe richieste. Riprova tra qualche minuto.', 'llmsclick'));
        }
        if ($res['code'] < 200 || $res['code'] >= 300) {
            return new WP_Error('llmsclick_http', $res['body']['error'] ?? __('Errore dal server llms.click.', 'llmsclick'));
        }
        return $res['body'];
    }

    /** Lingua dei testi: derivata dal locale di WordPress, fallback inglese. */
    private function lang(): string {
        $loc  = function_exists('get_locale') ? get_locale() : 'en_US';
        $lang = strtolower(substr($loc, 0, 2));
        return in_array($lang, ['it','en','de','fr','es'], true) ? $lang : 'en';
    }

    /**
     * GET con header X-API-Key. Ritorna ['code'=>int, 'body'=>array] o WP_Error.
     */
    private function get(string $path, array $args) {
        $url = LLMSCLICK_API_BASE . $path . '?' . http_build_query($args);
        $response = wp_remote_get($url, [
            'timeout'     => 45, // i fix AI possono richiedere qualche secondo
            'redirection' => 2,
            'sslverify'   => true,
            'headers'     => [
                'X-API-Key' => $this->key,
                'Accept'    => 'application/json',
                'User-Agent' => 'llmsclick-wp/' . LLMSCLICK_VERSION,
            ],
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) { $body = []; }
        return ['code' => $code, 'body' => $body];
    }
}

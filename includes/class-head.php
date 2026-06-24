<?php
/**
 * LlmsClick_Head - iniezione nel <head> dei fix abilitati del bucket "head":
 * JSON-LD (Organization/WebSite/WebPage/FAQ), Open Graph, freshness.
 *
 * Title e meta description sono delicati: se e' attivo un plugin SEO non li
 * sovrascriviamo (lo segnaliamo nel pannello come suggerimento copiabile).
 */

if (!defined('ABSPATH')) { exit; }

class LlmsClick_Head {

    public function register(): void {
        add_action('wp_head', [$this, 'output'], 20);
    }

    public function output(): void {
        // Iniettiamo solo in single/front, non in feed o pagine di sistema.
        if (is_feed() || is_admin()) { return; }

        $seo_plugin = LlmsClick_Applier::active_seo_plugin();

        $blocks = [];
        $seen   = []; // dedup: schema_present e schema_richness producono lo stesso JSON-LD

        foreach (LlmsClick_Applier::by_bucket('head') as $check => $fix) {
            $code = trim((string) ($fix['code'] ?? ''));
            if ($code === '') { continue; }

            // Con un plugin SEO attivo, evitiamo doppioni di title/meta/OG/schema.
            if ($seo_plugin && in_array($check, ['title', 'meta_description', 'open_graph', 'schema_present', 'schema_richness'], true)) {
                continue;
            }

            // title: lo gestisce WP/SEO plugin; lo lasciamo come suggerimento nel pannello.
            if ($check === 'title') {
                continue;
            }

            $safe = $this->sanitize_head_block($check, $code);
            if ($safe === '') { continue; }

            // Dedup per riga: non emettere due volte lo stesso tag/blocco.
            foreach (preg_split('/\n(?=<)/', $safe) as $line) {
                $line = trim($line);
                if ($line === '') { continue; }
                $h = md5(preg_replace('/\s+/', '', $line));
                if (isset($seen[$h])) { continue; }
                $seen[$h] = true;
                $blocks[] = $line;
            }
        }

        if ($blocks) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each block is either a <meta> tag sanitized via wp_kses(), or a JSON-LD block re-encoded with JSON_HEX_TAG/JSON_HEX_AMP (see LlmsClick_Applier::jsonld_script) so "<", ">" and "&" are unicode-escaped and cannot break out of the <script> tag.
            echo "\n<!-- llms.click fixes -->\n" . implode("\n", $blocks) . "\n<!-- /llms.click fixes -->\n";
        }
    }

    /**
     * Lascia passare solo markup atteso per ciascun check.
     *  - schema/freshness: solo <script type="application/ld+json"> con JSON valido
     *  - open_graph/meta_description: solo <meta ...> (e <title> per og non ammesso)
     */
    private function sanitize_head_block(string $check, string $code): string {
        if (in_array($check, ['schema_present', 'schema_richness', 'freshness'], true)) {
            return $this->extract_jsonld($code);
        }
        if (in_array($check, ['open_graph', 'meta_description'], true)) {
            return $this->extract_meta_tags($code);
        }
        return '';
    }

    /** Extracts and re-serializes only the valid ld+json blocks (tag-safe). */
    private function extract_jsonld(string $code): string {
        if (!preg_match_all('#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#is', $code, $matches)) {
            return '';
        }
        $out = '';
        foreach ($matches[1] as $json) {
            // jsonld_script re-encodes with JSON_HEX_TAG so "</script>" cannot break out.
            $script = LlmsClick_Applier::jsonld_script(trim($json));
            if ($script !== '') { $out .= $script . "\n"; }
        }
        return trim($out);
    }

    /** Estrae solo i tag <meta ...> (Open Graph / description / twitter). */
    private function extract_meta_tags(string $code): string {
        if (!preg_match_all('#<meta\b[^>]*>#i', $code, $matches)) {
            return '';
        }
        $allowed = [
            'meta' => [
                'property' => [], 'name' => [], 'content' => [],
            ],
        ];
        $out = '';
        foreach ($matches[0] as $tag) {
            $clean = wp_kses($tag, $allowed);
            if ($clean !== '') { $out .= $clean . "\n"; }
        }
        return trim($out);
    }
}

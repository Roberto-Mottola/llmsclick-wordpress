<?php
/**
 * Plugin Name:       llms.click - AI Discoverability Fixes
 * Plugin URI:        https://llms.click
 * Description:        Applica con un click i fix di llms.click (JSON-LD, Open Graph, llms.txt, robots AI-bot, FAQ answer-ready) generati sui dati reali del tuo sito. Richiede un account llms.click (piano Silver o superiore) e una API key.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            llms.click
 * Author URI:        https://llms.click
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       llmsclick
 *
 * Principio anti-pirateria: questo plugin e' un thin client. Nessuna logica
 * premium gira qui. Tutto il valore (codice fix, JSON-LD, contenuti AI) e'
 * prodotto dal server llms.click e restituito SOLO a chi ha un abbonamento
 * attivo. Niente abbonamento -> l'API risponde "locked" -> niente da applicare.
 */

if (!defined('ABSPATH')) { exit; }

define('LLMSCLICK_VERSION', '1.0.0');
define('LLMSCLICK_API_BASE', 'https://llms.click');
define('LLMSCLICK_FILE', __FILE__);
define('LLMSCLICK_DIR', plugin_dir_path(__FILE__));
define('LLMSCLICK_URL', plugin_dir_url(__FILE__));

// Nomi delle option (per-sito; in multisite ogni blog ha le sue).
define('LLMSCLICK_OPT_KEY',    'llmsclick_api_key');
define('LLMSCLICK_OPT_FIXES',  'llmsclick_enabled_fixes'); // [check_id => fix array]
define('LLMSCLICK_OPT_LANG',   'llmsclick_lang');
define('LLMSCLICK_OPT_TARGET', 'llmsclick_target_url');

require_once LLMSCLICK_DIR . 'includes/class-api.php';
require_once LLMSCLICK_DIR . 'includes/class-applier.php';
require_once LLMSCLICK_DIR . 'includes/class-head.php';
require_once LLMSCLICK_DIR . 'includes/class-files.php';
require_once LLMSCLICK_DIR . 'includes/class-settings.php';

/**
 * Bootstrap dei componenti runtime (front-end + admin).
 */
function llmsclick_init() {
    // Iniezione head (JSON-LD / OG / meta / title) per i fix abilitati.
    (new LlmsClick_Head())->register();

    // File virtuali llms.txt + direttive robots.txt.
    (new LlmsClick_Files())->register();

    // Shortcode + blocco FAQ answer-ready.
    LlmsClick_Applier::register_content_outputs();

    // Pannello admin (solo in dashboard).
    if (is_admin()) {
        (new LlmsClick_Settings())->register();
    }
}
add_action('init', 'llmsclick_init');

/**
 * Attivazione: registra la rewrite rule per /llms.txt e fa flush una volta.
 */
function llmsclick_activate() {
    (new LlmsClick_Files())->add_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'llmsclick_activate');

/**
 * Disattivazione: pulisce le rewrite rules. Le option restano (le rimuove uninstall.php).
 */
function llmsclick_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'llmsclick_deactivate');

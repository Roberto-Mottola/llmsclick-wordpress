<?php
/**
 * Pannello impostazioni llms.click.
 * Variabili dal contesto: nessuna (legge le option direttamente).
 */
if (!defined('ABSPATH')) { exit; }

$api_key    = (string) get_option(LLMSCLICK_OPT_KEY, '');
$site_url   = home_url('/');
$site_host  = (string) parse_url($site_url, PHP_URL_HOST);
$key_masked = $api_key !== '' ? substr($api_key, 0, 16) . str_repeat('•', 12) : '';
$enabled    = LlmsClick_Applier::enabled_fixes();
?>
<div class="wrap llmsclick-wrap">
    <h1>
        <img src="<?php echo esc_url(LLMSCLICK_URL . 'admin/logo.svg'); ?>" alt="" width="28" height="28" style="vertical-align:middle" onerror="this.style.display='none'">
        llms.click <span class="llmsclick-ver">v<?php echo esc_html(LLMSCLICK_VERSION); ?></span>
    </h1>
    <p class="llmsclick-lead">
        <?php esc_html_e('Applica con un click i fix di discoverability AI generati sui dati reali del tuo sito. Serve un account llms.click con piano Silver o superiore.', 'llmsclick'); ?>
        <a href="<?php echo esc_url(LLMSCLICK_API_BASE . '/profile?tab=apikeys&domain=' . rawurlencode($site_host)); ?>" target="_blank" rel="noopener"><?php esc_html_e('Genera la tua API key →', 'llmsclick'); ?></a>
    </p>

    <h2 class="title"><?php esc_html_e('1. Connessione', 'llmsclick'); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="llmsclick-key"><?php esc_html_e('API key', 'llmsclick'); ?></label></th>
            <td>
                <input type="password" id="llmsclick-key" class="regular-text" autocomplete="off"
                       placeholder="<?php echo $api_key !== '' ? esc_attr($key_masked) : 'llms_ex_...'; ?>">
                <button type="button" class="button" id="llmsclick-validate"><?php esc_html_e('Verifica e salva', 'llmsclick'); ?></button>
                <span id="llmsclick-key-status" class="llmsclick-status"></span>
                <p class="description"><?php esc_html_e('La chiave e\' legata a questo dominio. Restera\' salvata in modo cifrato e mai visibile nel front-end.', 'llmsclick'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Dominio', 'llmsclick'); ?></th>
            <td>
                <code style="font-size:13px;padding:4px 8px;background:#f0f0f1;border-radius:4px"><?php echo esc_html($site_host); ?></code>
                <p class="description"><?php esc_html_e('I fix vengono generati e applicati per questo sito. La chiave deve essere autorizzata su questo dominio (limite domini secondo il piano).', 'llmsclick'); ?></p>
            </td>
        </tr>
    </table>

    <h2 class="title"><?php esc_html_e('2. Fix disponibili', 'llmsclick'); ?></h2>
    <p>
        <button type="button" class="button button-primary" id="llmsclick-load"><?php esc_html_e('Analizza e carica i fix', 'llmsclick'); ?></button>
        <span id="llmsclick-load-status" class="llmsclick-status"></span>
    </p>

    <div id="llmsclick-warnings"></div>
    <div id="llmsclick-score"></div>

    <?php if (!empty($enabled)): ?>
        <p class="description"><?php
            /* translators: %d numero fix attivi */
            printf(esc_html__('Hai %d fix attivi su questo sito.', 'llmsclick'), count($enabled));
        ?></p>
    <?php endif; ?>

    <div id="llmsclick-fixes" class="llmsclick-fixes" aria-live="polite"></div>

    <h2 class="title"><?php esc_html_e('Contenuto FAQ', 'llmsclick'); ?></h2>
    <p class="description">
        <?php esc_html_e('Per la struttura answer-ready (paragrafo + FAQ + JSON-LD), abilita il fix qui sopra e inserisci nel contenuto della pagina lo shortcode:', 'llmsclick'); ?>
        <code>[llmsclick_faq]</code>
        <?php esc_html_e('oppure il blocco "llms.click FAQ" nell\'editor.', 'llmsclick'); ?>
    </p>
</div>

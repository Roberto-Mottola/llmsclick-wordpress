<?php
/**
 * llms.click settings page.
 * Reads options directly; no context variables required.
 */
if (!defined('ABSPATH')) { exit; }

$llmsclick_api_key    = (string) get_option(LLMSCLICK_OPT_KEY, '');
$llmsclick_site_url   = home_url('/');
$llmsclick_site_host  = (string) wp_parse_url($llmsclick_site_url, PHP_URL_HOST);
$llmsclick_key_masked = $llmsclick_api_key !== '' ? substr($llmsclick_api_key, 0, 16) . str_repeat('•', 12) : '';
$llmsclick_enabled    = LlmsClick_Applier::enabled_fixes();
?>
<div class="wrap llmsclick-wrap">
    <h1>
        <img src="<?php echo esc_url(LLMSCLICK_URL . 'admin/logo.svg'); ?>" alt="" width="28" height="28" style="vertical-align:middle" onerror="this.style.display='none'">
        llms.click <span class="llmsclick-ver">v<?php echo esc_html(LLMSCLICK_VERSION); ?></span>
    </h1>
    <p class="llmsclick-lead">
        <?php esc_html_e('Apply AI-discoverability fixes generated on your real site content with one click. Requires an llms.click account on the Silver plan or higher.', 'llmsclick'); ?>
        <a href="<?php echo esc_url(LLMSCLICK_API_BASE . '/profile?tab=apikeys&domain=' . rawurlencode($llmsclick_site_host)); ?>" target="_blank" rel="noopener"><?php esc_html_e('Generate your API key →', 'llmsclick'); ?></a>
    </p>

    <h2 class="title"><?php esc_html_e('1. Connection', 'llmsclick'); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="llmsclick-key"><?php esc_html_e('API key', 'llmsclick'); ?></label></th>
            <td>
                <input type="password" id="llmsclick-key" class="regular-text" autocomplete="off"
                       placeholder="<?php echo $llmsclick_api_key !== '' ? esc_attr($llmsclick_key_masked) : 'llms_ex_...'; ?>">
                <button type="button" class="button" id="llmsclick-validate"><?php esc_html_e('Verify and save', 'llmsclick'); ?></button>
                <span id="llmsclick-key-status" class="llmsclick-status"></span>
                <p class="description"><?php esc_html_e('The key is tied to this domain. It is stored encrypted and never exposed in the front-end.', 'llmsclick'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Domain', 'llmsclick'); ?></th>
            <td>
                <code style="font-size:13px;padding:4px 8px;background:#f0f0f1;border-radius:4px"><?php echo esc_html($llmsclick_site_host); ?></code>
                <p class="description"><?php esc_html_e('Fixes are generated and applied for this site. The key must be authorized for this domain (the domain limit depends on your plan).', 'llmsclick'); ?></p>
            </td>
        </tr>
    </table>

    <h2 class="title"><?php esc_html_e('2. Available fixes', 'llmsclick'); ?></h2>
    <p>
        <button type="button" class="button button-primary" id="llmsclick-load"><?php esc_html_e('Analyze and load fixes', 'llmsclick'); ?></button>
        <span id="llmsclick-load-status" class="llmsclick-status"></span>
    </p>

    <div id="llmsclick-warnings"></div>
    <div id="llmsclick-score"></div>

    <?php if (!empty($llmsclick_enabled)): ?>
        <p class="description"><?php
            /* translators: %d: number of active fixes */
            printf(esc_html__('You have %d active fixes on this site.', 'llmsclick'), count($llmsclick_enabled));
        ?></p>
    <?php endif; ?>

    <div id="llmsclick-fixes" class="llmsclick-fixes" aria-live="polite"></div>

    <h2 class="title"><?php esc_html_e('FAQ content', 'llmsclick'); ?></h2>
    <p class="description">
        <?php esc_html_e('For the answer-ready structure (paragraph + FAQ + JSON-LD), enable the fix above and add this shortcode to your page content:', 'llmsclick'); ?>
        <code>[llmsclick_faq]</code>
        <?php esc_html_e('or the "llms.click FAQ" block in the editor.', 'llmsclick'); ?>
    </p>
</div>

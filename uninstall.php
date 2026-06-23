<?php
/**
 * Cleanup alla disinstallazione: rimuove tutte le option del plugin.
 * Multisite-safe: pulisce ogni blog.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

$llmsclick_options = [
    'llmsclick_api_key',
    'llmsclick_enabled_fixes',
    'llmsclick_lang',
    'llmsclick_target_url',
];

function llmsclick_cleanup_options($opts) {
    foreach ($opts as $opt) {
        delete_option($opt);
    }
}

if (is_multisite()) {
    $llmsclick_sites = get_sites(['fields' => 'ids']);
    foreach ($llmsclick_sites as $llmsclick_blog_id) {
        switch_to_blog($llmsclick_blog_id);
        llmsclick_cleanup_options($llmsclick_options);
        restore_current_blog();
    }
} else {
    llmsclick_cleanup_options($llmsclick_options);
}

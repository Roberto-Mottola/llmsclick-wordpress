<?php
/**
 * Cleanup alla disinstallazione: rimuove tutte le option del plugin.
 * Multisite-safe: pulisce ogni blog.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

$options = [
    'llmsclick_api_key',
    'llmsclick_enabled_fixes',
    'llmsclick_lang',
    'llmsclick_target_url',
];

function llmsclick_cleanup_options($options) {
    foreach ($options as $opt) {
        delete_option($opt);
    }
}

if (is_multisite()) {
    $sites = get_sites(['fields' => 'ids']);
    foreach ($sites as $blog_id) {
        switch_to_blog($blog_id);
        llmsclick_cleanup_options($options);
        restore_current_blog();
    }
} else {
    llmsclick_cleanup_options($options);
}

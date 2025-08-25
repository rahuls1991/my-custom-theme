<?php
/**
 * GitHub-powered theme updates (public repo; no plugin).
 * Replace values in CONFIG section.
 */

/** CONFIG **/
define('MYTHEME_SLUG', 'my-custom-theme');          // your theme folder name
define('MYTHEME_REPO', 'rahuls1991/my-custom-theme');  // GitHub "owner/repo"

/**
 * Inject update info into WP's theme updater.
 */
add_filter('pre_set_site_transient_update_themes', function ($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $api = 'https://api.github.com/repos/' . MYTHEME_REPO . '/releases/latest';

    $remote = wp_remote_get($api, [
        'headers' => [
            'Accept'      => 'application/vnd.github+json',
            'User-Agent'  => 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/'),
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($remote)) {
        return $transient;
    }

    $data = json_decode(wp_remote_retrieve_body($remote));

    if (!isset($data->tag_name)) {
        return $transient;
    }

    $latest   = ltrim($data->tag_name, 'v');                 // v1.2.3 -> 1.2.3
    $current  = wp_get_theme(MYTHEME_SLUG)->get('Version');

    if (version_compare($current, $latest, '<')) {
        // Prefer GitHub's auto ZIP (zipball_url)
        $package = $data->zipball_url;

        $transient->response[MYTHEME_SLUG] = [
            'theme'       => MYTHEME_SLUG,
            'new_version' => $latest,
            'url'         => $data->html_url ?? ('https://github.com/' . MYTHEME_REPO . '/releases'),
            'package'     => $package,
        ];
    }

    return $transient;
});

/**
 * Ensure the extracted ZIP folder is renamed to the actual theme slug.
 * (GitHub ZIPs extract as owner-repo-<hash>/ by default.)
 */
add_filter('upgrader_source_selection', function ($source, $remote_source, $upgrader, $hook_extra) {
    if (!isset($hook_extra['theme']) || $hook_extra['theme'] !== MYTHEME_SLUG) {
        return $source;
    }

    $desired = trailingslashit($remote_source) . MYTHEME_SLUG;

    // If it's already correct, nothing to do.
    if (basename($source) === MYTHEME_SLUG) {
        return $source;
    }

    // Try rename extracted folder to our theme slug
    if (@rename($source, $desired)) {
        return $desired;
    }

    return $source;
}, 10, 4);

/**
 * (Optional) Add a quick way to force a fresh check via URL:
 * /wp-admin/?force-mytheme-update-check=1
 */
add_action('admin_init', function () {
    if (current_user_can('update_themes') && isset($_GET['force-mytheme-update-check'])) {
        delete_site_transient('update_themes');
    }
});

/**
 * (Optional) Auto-install updates with no clicks.
 * Comment out if you want manual control.
 */
add_filter('auto_update_theme', function ($update, $item) {
    if (!empty($item->slug) && $item->slug === MYTHEME_SLUG) {
        return true;
    }
    return $update;
}, 10, 2);

<?php
/**
 * Remote plugin management for WINC Admin.
 *
 * Exposes two REST routes that WINC Admin calls, authenticated with this site's
 * API key (the same `winc_api_key` used for the other WINC Admin requests):
 *   GET  /wp-json/winc/v1/plugins          list plugins and available updates
 *   POST /wp-json/winc/v1/plugins/update   update one plugin ({ "file": "dir/plugin.php" })
 */

if (! defined('ABSPATH')) {
    exit;
}

function winc_agent_authorised(WP_REST_Request $request)
{
    $stored   = get_option('winc_api_key');
    $provided = (string) $request->get_header('X-API-Key');

    if (! $stored || ! hash_equals($stored, $provided)) {
        return new WP_Error('winc_unauthorised', 'Unauthorised', ['status' => 401]);
    }

    return true;
}

add_action('rest_api_init', function () {
    register_rest_route('winc/v1', '/plugins', [
        'methods'             => 'GET',
        'callback'            => 'winc_agent_list_plugins',
        'permission_callback' => 'winc_agent_authorised',
    ]);

    register_rest_route('winc/v1', '/plugins/update', [
        'methods'             => 'POST',
        'callback'            => 'winc_agent_update_plugin',
        'permission_callback' => 'winc_agent_authorised',
    ]);
});

function winc_agent_load_admin_includes(): void
{
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
}

function winc_agent_list_plugins()
{
    winc_agent_load_admin_includes();

    wp_update_plugins(); // refresh available-update data

    $updates = get_site_transient('update_plugins');
    $out     = [];

    foreach (get_plugins() as $file => $data) {
        $out[] = [
            'file'           => $file,
            'name'           => $data['Name'],
            'version'        => $data['Version'],
            'update_version' => isset($updates->response[$file]) ? $updates->response[$file]->new_version : null,
            'active'         => is_plugin_active($file),
        ];
    }

    // Never let a host or CDN cache this: stale update info makes WINC Admin report "up to date".
    $response = new WP_REST_Response(['plugins' => $out, 'site' => winc_agent_site_info()]);
    $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
    $response->header('Pragma', 'no-cache');

    return $response;
}

/**
 * WordPress core, PHP and active theme versions, plus any updates waiting.
 * Reported alongside the plugin list so WINC Admin can show site health.
 */
function winc_agent_site_info()
{
    require_once ABSPATH . 'wp-admin/includes/update.php';

    // Core: refresh and look for an available upgrade.
    if (function_exists('wp_version_check')) {
        wp_version_check();
    }
    $core        = get_site_transient('update_core');
    $core_update = null;
    if ($core && ! empty($core->updates) && is_array($core->updates)) {
        foreach ($core->updates as $candidate) {
            if (isset($candidate->response) && $candidate->response === 'upgrade' && ! empty($candidate->version)) {
                $core_update = $candidate->version;
                break;
            }
        }
    }

    // Active theme and its update, if any.
    if (function_exists('wp_update_themes')) {
        wp_update_themes();
    }
    $theme         = wp_get_theme();
    $theme_updates = get_site_transient('update_themes');
    $stylesheet    = $theme->get_stylesheet();
    $theme_update  = null;
    if ($theme_updates && isset($theme_updates->response[$stylesheet]['new_version'])) {
        $theme_update = $theme_updates->response[$stylesheet]['new_version'];
    }

    // PHP_VERSION can carry distro suffixes; keep just major.minor.patch.
    preg_match('/^\d+\.\d+\.\d+/', PHP_VERSION, $php);

    return [
        'wp_version'        => get_bloginfo('version'),
        'wp_update_version' => $core_update,
        'php_version'       => $php[0] ?? PHP_VERSION,
        'theme'             => [
            'name'           => $theme->get('Name'),
            'version'        => $theme->get('Version'),
            'update_version' => $theme_update,
        ],
    ];
}

/**
 * WordPress deactivates a plugin while it swaps in the new files, and the Plugins screen
 * switches it back on afterwards. We have to do that ourselves, whatever the outcome of
 * the update, or an active plugin is left switched off.
 *
 * @return array{was_active:bool,active:bool,warning:?string}
 */
function winc_agent_restore_active($file, $was_active, $network_wide)
{
    $now_active = is_plugin_active($file) || (is_multisite() && is_plugin_active_for_network($file));

    if (! $was_active || $now_active) {
        return ['was_active' => (bool) $was_active, 'active' => (bool) $now_active, 'warning' => null];
    }

    // Silent, like the admin screen: no activation hooks, it simply goes back to how it was.
    $result = activate_plugin($file, '', $network_wide, true);

    if (is_wp_error($result)) {
        return [
            'was_active' => true,
            'active'     => false,
            'warning'    => 'WordPress could not switch the plugin back on: ' . $result->get_error_message(),
        ];
    }

    return ['was_active' => true, 'active' => true, 'warning' => null];
}

function winc_agent_update_plugin(WP_REST_Request $request)
{
    winc_agent_load_admin_includes();

    $file    = (string) $request->get_param('file');
    $plugins = get_plugins();

    if (! isset($plugins[$file])) {
        return new WP_Error('winc_unknown_plugin', 'Unknown plugin', ['status' => 404]);
    }

    $name = $plugins[$file]['Name'];
    $old  = $plugins[$file]['Version'];

    // Remember whether it was on, so we can put it back.
    $network    = is_multisite() && is_plugin_active_for_network($file);
    $was_active = $network || is_plugin_active($file);

    if (! WP_Filesystem()) {
        return ['success' => false, 'name' => $name, 'old_version' => $old, 'was_active' => $was_active, 'active' => $was_active, 'message' => 'WordPress cannot write to the filesystem directly on this host.'];
    }

    wp_update_plugins();

    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    $result   = $upgrader->upgrade($file);

    // Whatever happened, make sure a plugin that was active still is.
    $restore = winc_agent_restore_active($file, $was_active, $network);

    if (is_wp_error($result)) {
        return array_merge(['success' => false, 'name' => $name, 'old_version' => $old, 'message' => $result->get_error_message()], $restore);
    }

    if ($result === false || $result === null) {
        $errors = $upgrader->skin->get_errors();
        $msg    = is_wp_error($errors) && $errors->has_errors() ? $errors->get_error_message() : 'No update available or the upgrade failed.';

        return array_merge(['success' => false, 'name' => $name, 'old_version' => $old, 'message' => $msg], $restore);
    }

    wp_clean_plugins_cache();
    $new = get_plugins()[$file]['Version'] ?? null;

    return array_merge([
        'success'     => $new !== $old,
        'name'        => $name,
        'old_version' => $old,
        'new_version' => $new,
        'message'     => $new !== $old ? 'Updated.' : 'Plugin version did not change.',
    ], $restore);
}

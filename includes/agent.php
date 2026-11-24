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

    return ['plugins' => $out];
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

    if (! WP_Filesystem()) {
        return ['success' => false, 'name' => $name, 'old_version' => $old, 'message' => 'WordPress cannot write to the filesystem directly on this host.'];
    }

    wp_update_plugins();

    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    $result   = $upgrader->upgrade($file);

    if (is_wp_error($result)) {
        return ['success' => false, 'name' => $name, 'old_version' => $old, 'message' => $result->get_error_message()];
    }

    if ($result === false || $result === null) {
        $errors = $upgrader->skin->get_errors();
        $msg    = is_wp_error($errors) && $errors->has_errors() ? $errors->get_error_message() : 'No update available or the upgrade failed.';
        return ['success' => false, 'name' => $name, 'old_version' => $old, 'message' => $msg];
    }

    wp_clean_plugins_cache();
    $new = get_plugins()[$file]['Version'] ?? null;

    return [
        'success'     => $new !== $old,
        'name'        => $name,
        'old_version' => $old,
        'new_version' => $new,
        'message'     => $new !== $old ? 'Updated.' : 'Plugin version did not change.',
    ];
}

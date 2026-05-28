<?php

/**
 * Plugin Name: WINC Admin Dashboard
 * Plugin URI:  https://wincstudio.co.uk
 * Description: WordPress plugin to sync with WINC Admin.
 * Version:     1.1.2
 * Author:      WINC Studio
 * Author URI:  https://wincstudio.co.uk
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (! defined('ABSPATH')) {
    exit;
}

// Auto-updater via GitHub.
require_once __DIR__ . '/vendor/autoload.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$winc_updater = PucFactory::buildUpdateChecker(
    'https://github.com/paddywinc/wincAdminWPPlugin',
    __FILE__,
    'winc-admin-dashboard'
);

$winc_updater->setBranch('main');

function winc_dashboard_widget()
{
    wp_add_dashboard_widget(
        'winc_dashboard_widget',
        'WINC Studio',
        'winc_dashboard_widget_content'
    );
}
add_action('wp_dashboard_setup', 'winc_dashboard_widget');

function winc_dashboard_widget_content()
{
    $current_url = home_url();

    $response = wp_remote_get('https://admin.wincstudio.co.uk/api/urls');

    if (is_wp_error($response)) {
        echo '<p>Unable to load your WINC plan details. Please try again later.</p>';
        return;
    }

    $data = wp_remote_retrieve_body($response);
    $urls = json_decode($data, true);

    if (! is_array($urls)) {
        echo '<p>Unable to load your WINC plan details. Please try again later.</p>';
        return;
    }

    $found = false;
    $level = null;

    foreach ($urls as $url_data) {
        if (isset($url_data['url']) && $url_data['url'] === $current_url) {
            $found = true;
            $level = $url_data['level'];
            break;
        }
    }

    if ($found) {
        switch ($level) {
            case 1:
                echo '<p><strong>WINC Maintain</strong></p>';
                echo '<p>Your site is covered by our Maintain plan — keeping everything up to date, secure and running smoothly.</p>';
                echo '<ul>';
                echo '<li>Regular updates &amp; security patches</li>';
                echo '<li>Uptime monitoring</li>';
                echo '<li>Monthly reporting</li>';
                echo '</ul>';
                echo '<hr style="margin: 16px 0; border: none; border-top: 1px solid #e0e0e0;">';
                echo '<p><strong>Upgrade to WINC Support</strong></p>';
                echo '<p>Get priority support and a dedicated point of contact — so when something needs fixing, it gets fixed fast.</p>';
                echo '<p><a href="https://wincstudio.co.uk/support" target="_blank">Find out more &rarr;</a></p>';
                break;
            case 2:
                echo '<p><strong>WINC Support</strong></p>';
                echo '<p>Everything in Maintain, plus priority support so any issues are resolved quickly by our team.</p>';
                echo '<ul>';
                echo '<li>Regular updates &amp; security patches</li>';
                echo '<li>Uptime monitoring</li>';
                echo '<li>Monthly reporting</li>';
                echo '<li>Priority support</li>';
                echo '<li>Dedicated point of contact</li>';
                echo '</ul>';
                echo '<hr style="margin: 16px 0; border: none; border-top: 1px solid #e0e0e0;">';
                echo '<p><strong>Upgrade to WINC Momentum</strong></p>';
                echo '<p>Ready to go further? Momentum gives you an embedded WINC team driving ongoing development, new features, content and strategy — so your site keeps moving forward.</p>';
                echo '<p><a href="https://wincstudio.co.uk/support" target="_blank">Find out more &rarr;</a></p>';
                break;
            case 3:
                echo '<p><strong>WINC Momentum</strong></p>';
                echo '<p>Everything in Support, plus an embedded WINC team driving ongoing development and growth.</p>';
                echo '<ul>';
                echo '<li>Regular updates &amp; security patches</li>';
                echo '<li>Uptime monitoring</li>';
                echo '<li>Monthly reporting</li>';
                echo '<li>Priority support</li>';
                echo '<li>Dedicated point of contact</li>';
                echo '<li>Ongoing development &amp; new features</li>';
                echo '<li>Content &amp; strategy</li>';
                echo '<li>Embedded team</li>';
                echo '</ul>';
                echo '<hr style="margin: 16px 0; border: none; border-top: 1px solid #e0e0e0;">';
                echo '<p>Questions about your plan? <a href="https://wincstudio.co.uk/support" target="_blank">Get in touch &rarr;</a></p>';
                break;
            default:
                echo '<p>Your site is on a WINC plan (Level ' . esc_html($level) . '). Get in touch if you have any questions.</p>';
                break;
        }
    } else { ?>
        <p><strong>You don't have a WINC care plan yet.</strong></p>
        <p>We offer three plans to keep your site secure, supported and growing:</p>
        <ul>
            <li><strong>Maintain</strong> — regular updates and security patches.</li>
            <li><strong>Support</strong> — everything in Maintain, plus priority support.</li>
            <li><strong>Momentum</strong> — an embedded team for ongoing development, new features, content and strategy.</li>
        </ul>
        <p><a href="https://wincstudio.co.uk/maintenance" target="_blank"><strong>Get in touch to find out more</strong></a></p>
<?php
    }
}

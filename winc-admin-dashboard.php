<?php

/**
 * Plugin Name: WINC Admin Dashboard
 * Plugin URI:  https://wincstudio.co.uk
 * Description: WordPress plugin to sync with WINC Admin.
 * Version:     1.2.0
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

// ─── Data helpers ────────────────────────────────────────────────────────────

function winc_get_plan_data()
{
    static $plan = null;

    if ($plan !== null) {
        return $plan;
    }

    $response = wp_remote_get('https://admin.wincstudio.co.uk/api/urls', ['timeout' => 5]);

    if (is_wp_error($response)) {
        $plan = false;
        return $plan;
    }

    $urls = json_decode(wp_remote_retrieve_body($response), true);

    if (! is_array($urls)) {
        $plan = false;
        return $plan;
    }

    $current_url = home_url();

    foreach ($urls as $url_data) {
        if (isset($url_data['url']) && $url_data['url'] === $current_url) {
            $plan = $url_data;
            return $plan;
        }
    }

    $plan = ['level' => 0];
    return $plan;
}

function winc_plan_label($level)
{
    switch ($level) {
        case 1:  return 'WINC Maintain';
        case 2:  return 'WINC Support';
        case 3:  return 'WINC Momentum';
        default: return null;
    }
}

function winc_plan_description($level)
{
    switch ($level) {
        case 1:  return 'Your site is covered by our Maintain plan — keeping everything up to date, secure and running smoothly.';
        case 2:  return 'Everything in Maintain, plus priority support so any issues are resolved quickly by our team.';
        case 3:  return 'Everything in Support, plus an embedded WINC team driving ongoing development and growth.';
        default: return null;
    }
}

function winc_plan_features($level)
{
    $base     = ['Regular updates & security patches', 'Uptime monitoring', 'Monthly reporting'];
    $support  = array_merge($base, ['Priority support', 'Dedicated point of contact']);
    $momentum = array_merge($support, ['Ongoing development & new features', 'Content & strategy', 'Embedded team']);

    switch ($level) {
        case 1:  return $base;
        case 2:  return $support;
        case 3:  return $momentum;
        default: return [];
    }
}

// ─── Styles ──────────────────────────────────────────────────────────────────

add_action('admin_head', function () {
    ?>
    <style>
        /* ── Hero banner ── */
        .winc-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 32px;
            background: #111;
            color: #d8d4d4;
            padding: 40px 48px;
            margin: 0 -20px 16px;
            box-sizing: border-box;
        }
        .winc-hero__wordmark {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: #00e7a2;
            margin: 0 0 10px;
        }
        .winc-hero__plan {
            font-size: 26px;
            font-weight: 700;
            color: #fff;
            margin: 0 0 10px;
            line-height: 1.2;
        }
        .winc-hero__desc {
            font-size: 14px;
            color: #d8d4d4;
            margin: 0;
            max-width: 500px;
            line-height: 1.65;
        }
        .winc-hero__right {
            flex-shrink: 0;
            text-align: right;
        }
        .winc-hero__cta {
            display: inline-block;
            background: #00e7a2;
            color: #111 !important;
            font-weight: 700;
            font-size: 13px;
            padding: 10px 22px;
            border-radius: 4px;
            text-decoration: none;
            white-space: nowrap;
        }
        .winc-hero__cta:hover { opacity: 0.85; }
        .winc-hero__link {
            display: block;
            margin-top: 10px;
            font-size: 13px;
            color: #d8d4d4 !important;
            text-decoration: none;
            opacity: 0.7;
        }
        .winc-hero__link:hover { opacity: 1; color: #fff !important; }

        /* ── Admin page ── */
        .winc-page { max-width: 820px; padding-top: 16px; }
        .winc-page-hero {
            background: #111;
            color: #d8d4d4;
            padding: 40px 48px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .winc-page-hero .winc-hero__wordmark { margin-bottom: 8px; }
        .winc-page-hero h1 {
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            margin: 0 0 10px;
        }
        .winc-page-hero p {
            font-size: 14px;
            color: #d8d4d4;
            margin: 0;
            line-height: 1.65;
        }
        .winc-card {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 6px;
            padding: 28px 32px;
            margin-bottom: 16px;
        }
        .winc-card h3 { margin-top: 0; font-size: 16px; }
        .winc-card ul { margin: 12px 0 0; padding-left: 20px; }
        .winc-card ul li { margin-bottom: 7px; font-size: 14px; color: #444; }
        .winc-card--muted { background: #f9f9f9; }
        .winc-card p { font-size: 14px; color: #555; margin: 0 0 16px; }
        .winc-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .winc-btn {
            display: inline-block;
            background: #00e7a2;
            color: #111 !important;
            font-weight: 700;
            font-size: 13px;
            padding: 10px 22px;
            border-radius: 4px;
            text-decoration: none;
        }
        .winc-btn:hover { opacity: 0.85; }
        .winc-btn-outline {
            display: inline-block;
            border: 2px solid #111;
            color: #111 !important;
            font-weight: 700;
            font-size: 13px;
            padding: 9px 20px;
            border-radius: 4px;
            text-decoration: none;
            background: transparent;
        }
        .winc-btn-outline:hover { background: #111; color: #fff !important; }
    </style>
    <?php
});

// ─── Hero banner (dashboard only) ────────────────────────────────────────────

add_action('admin_notices', function () {
    $screen = get_current_screen();

    if (! $screen || $screen->id !== 'dashboard') {
        return;
    }

    $plan  = winc_get_plan_data();
    $level = ($plan && isset($plan['level'])) ? $plan['level'] : 0;
    $label = winc_plan_label($level);
    $desc  = winc_plan_description($level);

    if ($label) {
        ?>
        <div class="winc-hero">
            <div class="winc-hero__left">
                <p class="winc-hero__wordmark">WINC Studio</p>
                <h2 class="winc-hero__plan"><?php echo esc_html($label); ?></h2>
                <p class="winc-hero__desc"><?php echo esc_html($desc); ?></p>
            </div>
            <div class="winc-hero__right">
                <a href="<?php echo esc_url(admin_url('admin.php?page=winc-admin')); ?>" class="winc-hero__cta">View your plan &rarr;</a>
                <a href="https://wincstudio.co.uk/support" target="_blank" rel="noopener" class="winc-hero__link">Get support</a>
            </div>
        </div>
        <?php
    } else {
        ?>
        <div class="winc-hero">
            <div class="winc-hero__left">
                <p class="winc-hero__wordmark">WINC Studio</p>
                <h2 class="winc-hero__plan">Welcome to your dashboard.</h2>
                <p class="winc-hero__desc">You don't have a WINC care plan yet. Get in touch to keep your site secure, supported and growing.</p>
            </div>
            <div class="winc-hero__right">
                <a href="https://wincstudio.co.uk/maintenance" target="_blank" rel="noopener" class="winc-hero__cta">Get a care plan &rarr;</a>
            </div>
        </div>
        <?php
    }
});

// ─── Dashboard widget ─────────────────────────────────────────────────────────

add_action('wp_dashboard_setup', function () {
    wp_add_dashboard_widget('winc_dashboard_widget', 'WINC Studio', 'winc_dashboard_widget_content');
});

function winc_dashboard_widget_content()
{
    $plan  = winc_get_plan_data();
    $level = ($plan && isset($plan['level'])) ? $plan['level'] : 0;
    $label = winc_plan_label($level);

    if ($plan === false) {
        echo '<p>Unable to load your WINC plan details. Please try again later.</p>';
        return;
    }

    if ($label) {
        $features = winc_plan_features($level);
        echo '<p style="margin-top:4px"><strong>' . esc_html($label) . '</strong></p>';
        echo '<ul style="padding-left:18px;margin:8px 0">';
        foreach ($features as $feature) {
            echo '<li style="margin-bottom:5px;font-size:13px">' . esc_html($feature) . '</li>';
        }
        echo '</ul>';

        $admin_url = esc_url(admin_url('admin.php?page=winc-admin'));

        if ($level < 3) {
            $next = winc_plan_label($level + 1);
            echo '<hr style="margin:12px 0;border:none;border-top:1px solid #eee">';
            echo '<p style="margin:0;font-size:13px"><a href="' . $admin_url . '">Learn about ' . esc_html($next) . ' &rarr;</a></p>';
        } else {
            echo '<hr style="margin:12px 0;border:none;border-top:1px solid #eee">';
            echo '<p style="margin:0;font-size:13px"><a href="' . $admin_url . '">View your plan &rarr;</a></p>';
        }
    } else {
        echo '<p style="margin-top:4px"><strong>No WINC care plan found.</strong></p>';
        echo '<p style="font-size:13px"><a href="https://wincstudio.co.uk/maintenance" target="_blank" rel="noopener">Find out about our care plans &rarr;</a></p>';
    }
}

// ─── Admin menu page ──────────────────────────────────────────────────────────

add_action('admin_menu', function () {
    $icon = 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">'
        . '<text x="2" y="15" font-family="sans-serif" font-size="13" font-weight="bold" fill="#a0a5aa">W</text>'
        . '</svg>'
    );

    add_menu_page(
        'WINC Studio',
        'WINC',
        'manage_options',
        'winc-admin',
        'winc_admin_page',
        $icon,
        3
    );
});

function winc_admin_page()
{
    $plan     = winc_get_plan_data();
    $level    = ($plan && isset($plan['level'])) ? $plan['level'] : 0;
    $label    = winc_plan_label($level);
    $desc     = winc_plan_description($level);
    $features = winc_plan_features($level);
    ?>
    <div class="wrap winc-page">

        <div class="winc-page-hero">
            <p class="winc-hero__wordmark">WINC Studio</p>
            <?php if ($label): ?>
                <h1><?php echo esc_html($label); ?></h1>
                <p><?php echo esc_html($desc); ?></p>
            <?php else: ?>
                <h1>Your WINC Plan</h1>
                <p>You don't currently have a WINC care plan. Get in touch to find out more.</p>
            <?php endif; ?>
        </div>

        <?php if ($label && ! empty($features)): ?>
        <div class="winc-card">
            <h3>What's included in your plan</h3>
            <ul>
                <?php foreach ($features as $feature): ?>
                    <li><?php echo esc_html($feature); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($label && $level < 3):
            $next_level    = $level + 1;
            $next_label    = winc_plan_label($next_level);
            $next_desc     = winc_plan_description($next_level);
            $next_features = winc_plan_features($next_level);
            $new_features  = array_slice($next_features, count($features));
        ?>
        <div class="winc-card winc-card--muted">
            <h3>Upgrade to <?php echo esc_html($next_label); ?></h3>
            <p><?php echo esc_html($next_desc); ?></p>
            <?php if (! empty($new_features)): ?>
                <ul>
                    <?php foreach ($new_features as $feature): ?>
                        <li><?php echo esc_html($feature); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="winc-actions" style="margin-top:16px">
                <a href="https://wincstudio.co.uk/support" target="_blank" rel="noopener" class="winc-btn">Find out more &rarr;</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="winc-card winc-card--muted">
            <h3>Need help?</h3>
            <p>Our team is here whenever you need us. Reach out via our support page or drop us an email.</p>
            <div class="winc-actions">
                <a href="https://wincstudio.co.uk/support" target="_blank" rel="noopener" class="winc-btn">Get support &rarr;</a>
                <a href="mailto:hello@wincstudio.co.uk" class="winc-btn-outline">Email us</a>
            </div>
        </div>

    </div>
    <?php
}

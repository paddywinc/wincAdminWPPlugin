<?php

/**
 * Plugin Name: WINC Admin Dashboard
 * Plugin URI:  https://wincstudio.co.uk
 * Description: WordPress plugin to sync with WINC Admin.
 * Version:     1.4.0
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


// ─── Data helpers ─────────────────────────────────────────────────────────────

function winc_get_plan_data()
{
    static $plan = null;

    if ($plan !== null) {
        return $plan;
    }

    $cached = get_transient('winc_plan_data');

    if ($cached !== false) {
        $plan = $cached;
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
            set_transient('winc_plan_data', $plan, 5 * MINUTE_IN_SECONDS);
            return $plan;
        }
    }

    $plan = ['level' => 0];
    return $plan;
}

function winc_plan_label($level)
{
    $labels = [
        1 => 'WINC Maintain',
        2 => 'WINC Support',
        3 => 'WINC Momentum',
    ];
    return $labels[$level] ?? null;
}

function winc_plan_description($level)
{
    $descriptions = [
        1 => 'Your site is covered by our Maintain plan — keeping everything up to date, secure and running smoothly.',
        2 => 'Everything in Maintain, plus priority support so any issues are resolved quickly by our team.',
        3 => 'Everything in Support, plus an embedded WINC team driving ongoing development and growth.',
    ];
    return $descriptions[$level] ?? null;
}

function winc_plan_features($level)
{
    $base     = ['Regular updates & security patches', 'Uptime monitoring', 'Monthly reporting'];
    $support  = array_merge($base, ['Priority support', 'Dedicated point of contact']);
    $momentum = array_merge($support, ['Ongoing development & new features', 'Content & strategy', 'Embedded team']);

    $features = [
        1 => $base,
        2 => $support,
        3 => $momentum,
    ];
    return $features[$level] ?? [];
}

function winc_plan_icons()
{
    return [
        1 => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>',
        2 => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>',
        3 => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>',
    ];
}


// ─── Uptime history helper ──────────────────────────────────────────────────────

function winc_get_uptime_history()
{
    $site_url  = home_url();
    $cache_key = 'winc_uptime_history_' . md5($site_url);
    $cached    = get_transient($cache_key);

    if ($cached !== false) {
        return $cached;
    }

    $api_url  = 'https://admin.wincstudio.co.uk/api/uptime/' . rawurlencode($site_url) . '?days=7';
    $response = wp_remote_get($api_url, ['timeout' => 5]);

    if (is_wp_error($response)) {
        return [];
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (! is_array($data)) {
        return [];
    }

    set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
    return $data;
}


// ─── Enqueue uPlot on WINC admin page ────────────────────────────────────────

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_winc-admin') {
        return;
    }
    $base    = plugins_url('dist/', __FILE__);
    $version = '1.3.0';
    wp_enqueue_style('winc-admin', $base . 'admin.css', [], $version);
    wp_enqueue_script('winc-admin', $base . 'admin.js', [], $version, true);
});


// ─── Styles ───────────────────────────────────────────────────────────────────

add_action('admin_head', function () {
?>
    <style>
        /* ─── Variables ── */
        :root {
            --winc-black: #111;
            --winc-white: #fff;
            --winc-green: #00e7a2;
            --winc-green-dark: #00b87a;
            --winc-green-light: #e6fdf6;
            --winc-muted: #d8d4d4;
            --winc-body: #444;
            --winc-subtle: #555;
            --winc-border: #e5e5e5;
            --winc-bg-muted: #f9f9f9;
            --winc-font-base: 18px;
            --winc-font-sm: 17px;
            --winc-font-lg: 18px;
            --winc-font-xl: 35px;
            --winc-font-hero: 48px;
            --winc-radius: 6px;
            --winc-gap: 16px;
        }

        /* ─── Shared ── */
        .winc-wordmark {
            font-size: var(--winc-font-base);
            color: var(--winc-muted);
            margin: 0 0 10px;
            line-height: 1.4;
        }

        .winc-btn,
        .winc-btn-outline {
            display: inline-block;
            font-size: var(--winc-font-base);
            font-weight: 400;
            padding: 13px 20px;
            border-radius: 4px;
            text-decoration: none;
            white-space: nowrap;
            transition: background-color 0.3s, color 0.3s;
            cursor: pointer;
        }

        .winc-btn {
            background: var(--winc-green);
            border: 1px solid var(--winc-green);
            color: var(--winc-black) !important;
        }

        .winc-btn:hover {
            background: transparent;
            color: var(--winc-green) !important;
        }

        .winc-btn-outline {
            background: transparent;
            border: 1px solid var(--winc-black);
            color: var(--winc-black) !important;
        }

        .winc-btn-outline:hover {
            background: var(--winc-black);
            color: var(--winc-white) !important;
        }

        .winc-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .winc-feature-list {
            list-style: none;
            padding: 0;
            margin: 12px 0 0;
        }

        .winc-feature-list li {
            font-size: var(--winc-font-base);
            color: var(--winc-body);
            margin-bottom: 2px;
            padding-left: 1.4em;
            position: relative;
            line-height: 1.5;
        }

        .winc-feature-list li::before {
            content: '\2192';
            position: absolute;
            left: 0;
            color: var(--winc-green);
        }

        /* ─── Hero banner (dashboard) ── */
        .winc-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 32px;
            background: var(--winc-black);
            color: var(--winc-muted);
            padding: 70px 30px;
            margin: 16px -22px 16px;
            box-sizing: border-box;
        }

        .winc-hero__plan {
            font-size: var(--winc-font-hero);
            font-weight: 400;
            color: var(--winc-white);
            margin: 0 0 10px;
            line-height: 1.2;
        }

        .winc-hero__desc {
            font-size: var(--winc-font-lg);
            color: var(--winc-muted);
            margin: 0;
            max-width: 500px;
            line-height: 1.5;
        }

        .winc-hero__right {
            flex-shrink: 0;
            text-align: right;
        }

        .winc-hero__link {
            display: block;
            margin-top: 10px;
            font-size: var(--winc-font-sm);
            color: var(--winc-muted) !important;
            text-decoration: none;
            opacity: 0.7;
        }

        .winc-hero__link:hover {
            opacity: 1;
            color: var(--winc-white) !important;
        }

        /* ─── Admin page ── */
        .winc-page {
            max-width: 100%;
            padding-top: var(--winc-gap);
        }

        .winc-page-hero {
            background: var(--winc-black);
            color: var(--winc-muted);
            padding: 60px 30px;
            border-radius: var(--winc-radius) var(--winc-radius) 0 0;
        }

        .winc-page-hero h1 {
            font-size: var(--winc-font-hero);
            font-weight: 400;
            color: var(--winc-white);
            margin: 0 0 20px;
        }

        .winc-page-hero p {
            font-size: var(--winc-font-lg);
            color: var(--winc-muted);
            margin: 0;
            line-height: 1.5;
        }

        .winc-page-hero .winc-feature-list li {
            color: var(--winc-muted);
        }

        .winc-hero-inner {
            display: flex;
            gap: 40px;
            align-items: flex-end;
        }

        .winc-hero-inner__main {
            flex: 1;
        }

        .winc-hero-inner__features {
            width: 35%;
            flex-shrink: 0;
        }

        .winc-hero-inner__features .winc-wordmark {
            margin-bottom: 14px;
        }

        /* ─── Cards ── */
        .winc-card {
            background: var(--winc-white);
            border: 1px solid var(--winc-border);
            border-radius: 0 0 var(--winc-radius) var(--winc-radius);
            padding: 30px;
            margin-bottom: var(--winc-gap);
        }

        .winc-card h3 {
            margin-top: 0;
            font-size: 22px;
            font-weight: 500;
        }

        .winc-card p {
            font-size: var(--winc-font-base);
            color: var(--winc-subtle);
            margin: 0 0 var(--winc-gap);
            line-height: 1.6;
        }

        .winc-card--muted {
            background: var(--winc-bg-muted);
        }

        /* ─── Plans grid (no-plan state) ── */
        .winc-plans-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            background: var(--winc-black);
            color: var(--winc-muted);
            padding: 0 30px 30px;
        }

        .winc-plan-card {
            background: var(--winc-white);
            border: 1px solid var(--winc-border);
            border-radius: var(--winc-radius);
            padding: 30px;
            display: flex;
            flex-direction: column;
            transition: transform 0.25s ease, opacity 0.25s ease;
        }

        .winc-plans-grid:has(.winc-plan-card:hover) .winc-plan-card:not(:hover) {
            opacity: 0.45;
        }

        .winc-plan-card:hover {
            transform: scale(1.005);
        }

        .winc-plan-card--featured {
            border-color: var(--winc-green);
            border-width: 2px;
        }

        .winc-plan-card__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--winc-green-light);
            border-radius: 8px;
            margin-bottom: 30px;
            color: var(--winc-green-dark);
        }

        .winc-plan-card h3 {
            font-size: var(--winc-font-xl);
            font-weight: 400;
            margin: 0 0 16px;
            color: var(--winc-black);
        }

        .winc-plan-card__desc {
            font-size: var(--winc-font-base);
            color: var(--winc-subtle);
            line-height: 1.6;
            margin: 0 0 20px;
        }

        .winc-plan-card .winc-feature-list {
            flex: 1;
            margin-bottom: 28px;
        }

        .winc-plan-card .winc-btn,
        .winc-plan-card .winc-btn-outline {
            text-align: center;
            display: block;
        }

        /* ─── Uptime card ── */
        .winc-uptime {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .winc-uptime__dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--winc-green);
            flex-shrink: 0;
        }

        .winc-uptime__label {
            font-size: var(--winc-font-base);
            color: var(--winc-body);
        }

        .winc-uptime__url {
            font-size: var(--winc-font-sm);
            color: #888;
            margin-top: 2px;
        }

        /* ─── Dashboard widget ── */
        .winc-widget-features {
            padding-left: 18px;
            margin: 8px 0;
        }

        .winc-widget-features li {
            margin-bottom: 5px;
            font-size: var(--winc-font-sm);
        }

        .winc-widget-divider {
            margin: 12px 0;
            border: none;
            border-top: 1px solid #eee;
        }

        .winc-widget-footer {
            margin: 0;
            font-size: var(--winc-font-sm);
        }

        /* ─── Responsive ── */
        @media (max-width: 1024px) {
            .winc-plans-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .winc-plans-grid .winc-plan-card:last-child {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 782px) {
            .winc-hero {
                flex-direction: column;
                align-items: flex-start;
                padding: 40px 20px;
                margin: 16px -10px 16px;
                gap: 20px;
            }

            .winc-hero__plan {
                font-size: 32px;
            }

            .winc-hero__right {
                text-align: left;
            }

            .winc-page-hero {
                padding: 32px 20px;
            }

            .winc-page-hero h1 {
                font-size: 32px;
            }

            .winc-hero-inner {
                flex-direction: column;
                gap: 24px;
            }

            .winc-hero-inner__features {
                width: 100%;
            }

            .winc-plans-grid {
                grid-template-columns: 1fr;
                padding: 0 20px 20px;
            }

            .winc-plans-grid .winc-plan-card:last-child {
                grid-column: auto;
            }

            .winc-card {
                padding: 20px;
            }
        }
    </style>
<?php
});


// ─── Move hero after Dashboard h1 ─────────────────────────────────────────────

add_action('admin_footer', function () {
    $screen = get_current_screen();
    if (! $screen || $screen->id !== 'dashboard') {
        return;
    }
?>
    <script>
        (function() {
            var hero = document.querySelector('.winc-hero');
            var h1 = document.querySelector('#wpbody-content .wrap h1');
            if (hero && h1) {
                h1.after(hero);
            }
        })();
    </script>
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

    if ($label) : ?>
        <div class="winc-hero">
            <div class="winc-hero__left">
                <p class="winc-wordmark">WINC Admin</p>
                <h2 class="winc-hero__plan"><?php echo esc_html($label); ?></h2>
                <p class="winc-hero__desc"><?php echo esc_html($desc); ?></p>
            </div>
            <div class="winc-hero__right">
                <a href="<?php echo esc_url(admin_url('admin.php?page=winc-admin')); ?>" class="winc-btn">View your plan &rarr;</a>
                <a href="https://wincstudio.co.uk/support" target="_blank" rel="noopener" class="winc-hero__link">Get support</a>
            </div>
        </div>
    <?php else : ?>
        <div class="winc-hero">
            <div class="winc-hero__left">
                <p class="winc-wordmark">WINC Admin</p>
                <h2 class="winc-hero__plan">Welcome to your dashboard.</h2>
                <p class="winc-hero__desc">You don't have a WINC care plan yet. Get in touch to keep your site secure, supported and growing.</p>
            </div>
            <div class="winc-hero__right">
                <a href="https://wincstudio.co.uk/maintenance" target="_blank" rel="noopener" class="winc-btn">Get a care plan &rarr;</a>
            </div>
        </div>
    <?php endif;
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

    if ($label) :
        $features   = winc_plan_features($level);
        $admin_url  = esc_url(admin_url('admin.php?page=winc-admin'));
        $next_label = winc_plan_label($level + 1);
    ?>
        <p><strong><?php echo esc_html($label); ?></strong></p>
        <ul class="winc-widget-features">
            <?php foreach ($features as $feature) : ?>
                <li><?php echo esc_html($feature); ?></li>
            <?php endforeach; ?>
        </ul>
        <hr class="winc-widget-divider">
        <p class="winc-widget-footer">
            <?php if ($level < 3) : ?>
                <a href="<?php echo $admin_url; ?>">Learn about <?php echo esc_html($next_label); ?> &rarr;</a>
            <?php else : ?>
                <a href="<?php echo $admin_url; ?>">View your plan &rarr;</a>
            <?php endif; ?>
        </p>
    <?php else : ?>
        <p><strong>No WINC care plan found.</strong></p>
        <p class="winc-widget-footer">
            <a href="https://wincstudio.co.uk/maintenance" target="_blank" rel="noopener">Find out about our care plans &rarr;</a>
        </p>
    <?php endif;
}


// ─── Admin menu page ──────────────────────────────────────────────────────────

add_action('admin_menu', function () {
    $icon = 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">'
            . '<text x="2" y="15" font-family="sans-serif" font-size="13" font-weight="bold" fill="#a00e7a2">W</text>'
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
    $icons    = winc_plan_icons();
    ?>
    <div class="wrap winc-page">

        <div class="winc-page-hero">
            <p class="winc-wordmark">WINC Admin</p>
            <?php if ($label) : ?>
                <div class="winc-hero-inner">
                    <div class="winc-hero-inner__main">
                        <h1><?php echo esc_html($label); ?></h1>
                        <p><?php echo esc_html($desc); ?></p>
                    </div>
                    <?php if (! empty($features)) : ?>
                        <div class="winc-hero-inner__features">
                            <p class="winc-wordmark">What's included</p>
                            <ul class="winc-feature-list">
                                <?php foreach ($features as $feature) : ?>
                                    <li><?php echo esc_html($feature); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <h1>Get a WINC care plan</h1>
                <p>Choose a plan to keep your site secure, supported and growing.</p>
            <?php endif; ?>
        </div>

        <?php if (! $label) : ?>
            <div class="winc-plans-grid">
                <?php
                $all_plans = [
                    1 => [
                        'label'    => 'Maintain',
                        'desc'     => 'Everything you need to keep your site healthy, secure and running smoothly.',
                        'featured' => false,
                    ],
                    2 => [
                        'label'    => 'Support',
                        'desc'     => 'Everything in Maintain, plus priority support when you need it.',
                        'featured' => true,
                    ],
                    3 => [
                        'label'    => 'Momentum',
                        'desc'     => 'Everything in Support, plus an embedded WINC team driving ongoing growth.',
                        'featured' => false,
                    ],
                ];

                foreach ($all_plans as $plan_level => $plan_data) :
                    $card_class    = 'winc-plan-card' . ($plan_data['featured'] ? ' winc-plan-card--featured' : '');
                    $plan_features = winc_plan_features($plan_level);
                    $btn_class     = $plan_data['featured'] ? 'winc-btn' : 'winc-btn-outline';
                ?>
                    <div class="<?php echo $card_class; ?>">
                        <span class="winc-plan-card__icon"><?php echo $icons[$plan_level]; ?></span>
                        <h3><?php echo esc_html($plan_data['label']); ?></h3>
                        <p class="winc-plan-card__desc"><?php echo esc_html($plan_data['desc']); ?></p>
                        <ul class="winc-feature-list winc-plan-card__features">
                            <?php foreach ($plan_features as $feature) : ?>
                                <li><?php echo esc_html($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="https://wincstudio.co.uk/maintenance" target="_blank" rel="noopener" class="<?php echo $btn_class; ?>">
                            Find out more &rarr;
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($label) :
            $history    = winc_get_uptime_history();
            $chart_data = [];
            foreach ($history as $point) {
                if (isset($point['checked_at'], $point['status'])) {
                    $chart_data[] = [
                        'ts'     => strtotime($point['checked_at']),
                        'ms'     => isset($point['response_time_ms']) ? (float) $point['response_time_ms'] : null,
                        'status' => $point['status'] === 'up' ? 1 : 0,
                    ];
                }
            }
            usort($chart_data, fn($a, $b) => $a['ts'] <=> $b['ts']);
            $chart_timestamps = array_column($chart_data, 'ts');
            $chart_ms         = array_column($chart_data, 'ms');
            $chart_status     = array_column($chart_data, 'status');
        ?>
            <div class="winc-card">
                <h3>Uptime monitoring</h3>
                <div class="winc-uptime">
                    <span class="winc-uptime__dot"></span>
                    <div>
                        <div class="winc-uptime__label">Your site is being monitored</div>
                        <div class="winc-uptime__url"><?php echo esc_html(home_url()); ?></div>
                    </div>
                </div>
                <?php if (! empty($chart_data)) : ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:24px">
                        <div>
                            <p style="font-size:13px;color:#888;margin:0 0 8px">Response time (ms)</p>
                            <div id="winc-chart-ms"></div>
                        </div>
                        <div>
                            <p style="font-size:13px;color:#888;margin:0 0 8px">Uptime</p>
                            <div id="winc-chart-uptime"></div>
                        </div>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var timestamps = <?php echo wp_json_encode($chart_timestamps); ?>;
                            var msValues = <?php echo wp_json_encode($chart_ms); ?>;
                            var upValues = <?php echo wp_json_encode($chart_status); ?>;

                            var sharedAxes = [{
                                    stroke: '#888',
                                    ticks: {
                                        stroke: 'transparent'
                                    },
                                    grid: {
                                        show: false
                                    },
                                    font: '11px -apple-system,BlinkMacSystemFont,sans-serif',
                                },
                                {
                                    stroke: '#888',
                                    ticks: {
                                        stroke: 'transparent'
                                    },
                                    grid: {
                                        stroke: '#f0f0f0',
                                        width: 1
                                    },
                                    font: '11px -apple-system,BlinkMacSystemFont,sans-serif',
                                },
                            ];

                            // Response time chart
                            var elMs = document.getElementById('winc-chart-ms');
                            new WincAdmin.uPlot({
                                width: elMs.offsetWidth || 340,
                                height: 140,
                                cursor: {
                                    show: false
                                },
                                legend: {
                                    show: false
                                },
                                scales: {
                                    x: {
                                        time: true
                                    }
                                },
                                axes: sharedAxes,
                                series: [{},
                                    {
                                        stroke: '#00e7a2',
                                        width: 2,
                                        fill: 'transparent'
                                    },
                                ],
                            }, [timestamps, msValues], elMs);

                            // Uptime chart
                            var elUp = document.getElementById('winc-chart-uptime');
                            new WincAdmin.uPlot({
                                width: elUp.offsetWidth || 340,
                                height: 140,
                                cursor: {
                                    show: false
                                },
                                legend: {
                                    show: false
                                },
                                scales: {
                                    x: {
                                        time: true
                                    },
                                    y: {
                                        range: [0, 1]
                                    }
                                },
                                axes: [
                                    sharedAxes[0],
                                    {
                                        stroke: '#888',
                                        ticks: {
                                            stroke: 'transparent'
                                        },
                                        grid: {
                                            stroke: '#f0f0f0',
                                            width: 1
                                        },
                                        font: '11px -apple-system,BlinkMacSystemFont,sans-serif',
                                        values: (u, vals) => vals.map(v => v === 1 ? 'Up' : v === 0 ? 'Down' : ''),
                                        splits: [0, 1],
                                    },
                                ],
                                series: [{},
                                    {
                                        stroke: '#00e7a2',
                                        width: 2,
                                        fill: (u, idx) => {
                                            var ctx = u.ctx;
                                            var g = ctx.createLinearGradient(0, u.bbox.top, 0, u.bbox.top + u.bbox.height);
                                            g.addColorStop(0, 'rgba(0,231,162,0.15)');
                                            g.addColorStop(1, 'rgba(0,231,162,0)');
                                            return g;
                                        },
                                        points: {
                                            show: false
                                        },
                                        spanGaps: false,
                                    },
                                ],
                            }, [timestamps, upValues], elUp);
                        });
                    </script>
                <?php else : ?>
                    <p style="margin-top:20px;color:#888;font-size:15px">No data yet.</p>
                <?php endif; ?>
                <div class="winc-actions">
                    <a href="https://admin.wincstudio.co.uk" target="_blank" rel="noopener" class="winc-btn-outline">View uptime reports &rarr;</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($label && $level < 3) :
            $next_level    = $level + 1;
            $next_label    = winc_plan_label($next_level);
            $next_desc     = winc_plan_description($next_level);
            $next_features = winc_plan_features($next_level);
            $new_features  = array_slice($next_features, count($features));
        ?>
            <div class="winc-card winc-card--muted">
                <h3>Upgrade to <?php echo esc_html($next_label); ?></h3>
                <p><?php echo esc_html($next_desc); ?></p>
                <?php if (! empty($new_features)) : ?>
                    <ul class="winc-feature-list">
                        <?php foreach ($new_features as $feature) : ?>
                            <li><?php echo esc_html($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="winc-actions">
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

<?php
/**
 * Plugin Name: MJ Autopilot SEO
 * Plugin URI: https://www.melaniejulien.com
 * Description: Extension IA & SEO avancée pour la génération de contenu, GEO renforcé, maillage et synchronisation Google Search Console.
 * Version: 9.2.0
 * Author: Mélanie Julien
 * License: GPL2
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) { exit; }

if (!defined('MJ_AUTOPILOT_SEO_VERSION')) { define('MJ_AUTOPILOT_SEO_VERSION', '9.2.0'); }
if (!defined('MJ_AUTOPILOT_SEO_PATH'))    { define('MJ_AUTOPILOT_SEO_PATH', plugin_dir_path(__FILE__)); }
if (!defined('MJ_AUTOPILOT_SEO_URL'))     { define('MJ_AUTOPILOT_SEO_URL', plugin_dir_url(__FILE__)); }
if (!defined('MJ_LOG_FILE'))              { define('MJ_LOG_FILE', WP_CONTENT_DIR . '/mj_autopilot_seo.log'); }

$mj_modules = [
    'includes/core.php',
    'includes/content-engine.php',
    'includes/linking.php',
    'includes/gsc.php',
    'includes/kpi-dashboard.php',
    'includes/library-crud.php',
    'includes/opportunities.php',
    'includes/calendar.php',
    'includes/seo-score.php',
    'includes/geo-report.php',
];

foreach ($mj_modules as $mj_module) {
    $file_path = MJ_AUTOPILOT_SEO_PATH . $mj_module;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            trigger_error('MJ Autopilot SEO : module manquant — ' . esc_html($mj_module), E_USER_WARNING);
        }
    }
}

register_activation_hook(__FILE__, 'mj_autopilot_seo_activate_safe');
register_deactivation_hook(__FILE__, 'mj_autopilot_seo_deactivate_safe');

if (!function_exists('mj_autopilot_seo_activate_safe')) {
    function mj_autopilot_seo_activate_safe() {
        if (function_exists('mj_core_activation')) {
            mj_core_activation();
        }
    }
}

if (!function_exists('mj_autopilot_seo_deactivate_safe')) {
    function mj_autopilot_seo_deactivate_safe() {
        if (function_exists('mj_core_deactivation')) {
            mj_core_deactivation();
        }
    }
}

if (!function_exists('mj_autopilot_force_admin_menu')) {
    function mj_autopilot_force_admin_menu() {
        if (!current_user_can('manage_options')) { return; }

        add_menu_page(
            'MJ Autopilot SEO',
            'MJ Autopilot',
            'manage_options',
            'mj-autopilot-seo',
            'mj_render_kpi_dashboard_page',
            'dashicons-chart-area',
            30
        );

        add_submenu_page('mj-autopilot-seo', 'Bibliothèque de Sujets', 'Bibliothèque',
            'manage_options', 'mj-autopilot-library', 'mj_render_library_crud_page');

        add_submenu_page('mj-autopilot-seo', 'Calendrier Éditorial', 'Calendrier',
            'manage_options', 'mj-autopilot-calendar', 'mj_render_calendar_page');

        add_submenu_page('mj-autopilot-seo', 'Opportunités GSC', 'Opportunités SEO',
            'manage_options', 'mj-autopilot-opportunities', 'mj_render_opportunities_page');

        add_submenu_page('mj-autopilot-seo', 'Rapport GEO', 'Rapport GEO',
            'manage_options', 'mj-autopilot-geo-report', 'mj_render_geo_report_page');

        add_submenu_page('mj-autopilot-seo', 'Configuration API', 'Configuration',
            'manage_options', 'mj-autopilot-settings', 'mj_render_gsc_settings_page');
    }
    add_action('admin_menu', 'mj_autopilot_force_admin_menu', 10);
}

if (!function_exists('mj_autopilot_enqueue_admin_assets_safe')) {
    function mj_autopilot_enqueue_admin_assets_safe($hook) {
        if (strpos($hook, 'mj-autopilot') === false) { return; }

        $css_path = MJ_AUTOPILOT_SEO_PATH . 'assets/admin.css';
        $js_path  = MJ_AUTOPILOT_SEO_PATH . 'assets/admin.js';

        if (file_exists($css_path)) {
            wp_enqueue_style('mj-autopilot-admin-css', MJ_AUTOPILOT_SEO_URL . 'assets/admin.css', [], MJ_AUTOPILOT_SEO_VERSION);
        }
        if (file_exists($js_path)) {
            wp_enqueue_script('mj-autopilot-admin-js', MJ_AUTOPILOT_SEO_URL . 'assets/admin.js', ['jquery'], MJ_AUTOPILOT_SEO_VERSION, true);
            wp_localize_script('mj-autopilot-admin-js', 'mjAutopilot', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('mj_ajax_nonce'),
            ]);
        }
    }
    add_action('admin_enqueue_scripts', 'mj_autopilot_enqueue_admin_assets_safe');
}

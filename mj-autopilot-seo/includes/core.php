<?php
/**
 * Module Core - Opérations de fond, Planifications Cron et Journalisation
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('mj_core_activation')) {
    function mj_core_activation() {
        if (!wp_next_scheduled('mj_autopilot_hourly_cron_hook')) {
            wp_schedule_event(time(), 'hourly', 'mj_autopilot_hourly_cron_hook');
        }
        if (function_exists('mj_gsc_create_table')) {
            mj_gsc_create_table();
        }
        mj_log('Plugin MJ Autopilot SEO initialisé avec succès.');
    }
}

if (!function_exists('mj_core_deactivation')) {
    function mj_core_deactivation() {
        wp_clear_scheduled_hook('mj_autopilot_hourly_cron_hook');
        mj_log('Plugin MJ Autopilot SEO désactivé proprement.');
    }
}

if (!function_exists('mj_log')) {
    function mj_log($message) {
        // CORRIGÉ : wp_date() respecte le timezone WordPress, date() utilisait le timezone serveur
        $timestamp   = wp_date('Y-m-d H:i:s');
        $clean_msg   = (is_array($message) || is_object($message)) ? print_r($message, true) : $message;
        $log_entry   = "[{$timestamp}] {$clean_msg}\n";
        $log_file    = defined('MJ_LOG_FILE') ? MJ_LOG_FILE : WP_CONTENT_DIR . '/mj_autopilot_seo.log';

        // Taille max du log : 5 Mo — rotation automatique pour éviter la saturation disque
        if (file_exists($log_file) && filesize($log_file) > 5 * 1024 * 1024) {
            rename($log_file, $log_file . '.old');
        }

        @error_log($log_entry, 3, $log_file);
    }
}

<?php
/**
 * Module GSC - API Google Search Console & Configuration
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('mj_gsc_create_table')) {
    function mj_gsc_create_table() {
        global $wpdb;
        $table_name      = $wpdb->prefix . 'mj_autopilot_gsc_indexation';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            url varchar(255) NOT NULL,
            status varchar(50) DEFAULT 'Non vérifié' NOT NULL,
            last_checked datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            clicks int(11) DEFAULT 0,
            impressions int(11) DEFAULT 0,
            position float DEFAULT 0,
            PRIMARY KEY  (id),
            KEY post_id (post_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}

if (!function_exists('mj_render_gsc_settings_page')) {
    function mj_render_gsc_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Vous n\'avez pas les droits nécessaires pour accéder à cette page.'));
        }

        if (isset($_POST['mj_save_settings'])) {
            check_admin_referer('mj_settings_secure_nonce', 'mj_settings_nonce_field');

            if (isset($_POST['mj_anthropic_key'])) {
                $key = sanitize_text_field($_POST['mj_anthropic_key']);
                // CORRIGÉ : on ne sauvegarde que si le champ contient une vraie clé (pas les bullets masqués)
                if (!empty($key) && strpos($key, '•') === false) {
                    update_option('mj_anthropic_api_key', $key);
                }
            }

            if (!empty($_POST['mj_gsc_client_secret'])) {
                $secret = sanitize_text_field($_POST['mj_gsc_client_secret']);
                // CORRIGÉ : on ne sauvegarde que si c'est une vraie valeur (pas les bullets masqués)
                if (strpos($secret, '•') === false) {
                    // NOTE SÉCURITÉ : base64 n'est pas du chiffrement.
                    // Recommandation : utiliser sodium_crypto_secretbox() si disponible (PHP 7.2+).
                    if (function_exists('sodium_crypto_secretbox') && defined('AUTH_KEY') && strlen(AUTH_KEY) >= 32) {
                        $nonce      = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                        $key_bytes  = substr(hash('sha256', AUTH_KEY, true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
                        $encrypted  = sodium_crypto_secretbox($secret, $nonce, $key_bytes);
                        update_option('mj_gsc_client_secret_encrypted', base64_encode($nonce . $encrypted));
                    } else {
                        // Fallback basique si sodium indisponible — avertissement en WP_DEBUG
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            trigger_error('MJ Autopilot SEO : sodium non disponible, secret GSC stocké en base64 simple.', E_USER_WARNING);
                        }
                        update_option('mj_gsc_client_secret_encrypted', base64_encode($secret . AUTH_KEY));
                    }
                }
            }

            echo '<div class="notice notice-success is-dismissible"><p>Configuration mise à jour.</p></div>';
        }

        // CORRIGÉ : on n'affiche JAMAIS la clé API en clair dans le DOM
        $has_anthropic_key = get_option('mj_anthropic_api_key', '') ? true : false;
        $has_secret        = get_option('mj_gsc_client_secret_encrypted', '') ? true : false;
        ?>
        <div class="wrap" style="max-width:900px; margin-top:20px;">
            <h1 style="font-weight:700; margin-bottom:20px;">Configuration Sécurisée des API</h1>
            <form method="post" action="" style="background:#fff; padding:30px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.05);">
                <?php wp_nonce_field('mj_settings_secure_nonce', 'mj_settings_nonce_field'); ?>

                <h3 style="margin-top:0;">Clé API Anthropic (Claude)</h3>
                <?php if ($has_anthropic_key) : ?>
                    <p style="color:#46b450; font-weight:600;">✔ Clé configurée. Saisir une nouvelle valeur pour la remplacer.</p>
                <?php endif; ?>
                <input type="password" name="mj_anthropic_key" value="" class="large-text"
                       placeholder="sk-ant-…" autocomplete="new-password"
                       style="padding:10px; border-radius:4px;">
                <p class="description" style="margin-bottom:20px;">Utilisée par le Content Engine pour générer le contenu sémantique.</p>

                <h3>Google Search Console - Client Secret</h3>
                <?php if ($has_secret) : ?>
                    <p style="color:#46b450; font-weight:600;">✔ Secret configuré. Saisir une nouvelle valeur pour le remplacer.</p>
                <?php endif; ?>
                <input type="password" name="mj_gsc_client_secret" value="" class="large-text"
                       placeholder="Nouveau secret GSC…" autocomplete="new-password"
                       style="padding:10px; border-radius:4px;">
                <p class="description" style="margin-bottom:20px;">Permet l'extraction automatisée des opportunités de mots-clés.</p>

                <p class="submit">
                    <input type="submit" name="mj_save_settings" class="button button-primary button-large"
                           value="Sauvegarder les configurations">
                </p>
            </form>
        </div>
        <?php
    }
}

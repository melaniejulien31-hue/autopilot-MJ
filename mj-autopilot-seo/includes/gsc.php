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

            // Clé Anthropic
            if (isset($_POST['mj_anthropic_key'])) {
                $key = sanitize_text_field($_POST['mj_anthropic_key']);
                if (!empty($key) && strpos($key, '•') === false) {
                    update_option('mj_anthropic_api_key', $key);
                }
            }

            // Clé Pexels (V2)
            if (isset($_POST['mj_pexels_key'])) {
                $pexels = sanitize_text_field($_POST['mj_pexels_key']);
                if (!empty($pexels) && strpos($pexels, '•') === false) {
                    update_option('mj_pexels_api_key', $pexels);
                }
            }

            // Logo (V2) — URL de la médiathèque WP
            if (isset($_POST['mj_logo_url'])) {
                update_option('mj_logo_url', esc_url_raw($_POST['mj_logo_url']));
            }

            // Tunnel par défaut (V2)
            if (isset($_POST['mj_default_tunnel'])) {
                $t = sanitize_text_field($_POST['mj_default_tunnel']);
                if (in_array($t, ['TOFU', 'MOFU', 'BOFU'], true)) {
                    update_option('mj_default_tunnel', $t);
                }
            }

            // Client Secret GSC
            if (!empty($_POST['mj_gsc_client_secret'])) {
                $secret = sanitize_text_field($_POST['mj_gsc_client_secret']);
                if (strpos($secret, '•') === false) {
                    if (function_exists('sodium_crypto_secretbox') && defined('AUTH_KEY') && strlen(AUTH_KEY) >= 32) {
                        $nonce     = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                        $key_bytes = substr(hash('sha256', AUTH_KEY, true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
                        $encrypted = sodium_crypto_secretbox($secret, $nonce, $key_bytes);
                        update_option('mj_gsc_client_secret_encrypted', base64_encode($nonce . $encrypted));
                    } else {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            trigger_error('MJ Autopilot SEO : sodium non disponible, secret GSC stocké en base64 simple.', E_USER_WARNING);
                        }
                        update_option('mj_gsc_client_secret_encrypted', base64_encode($secret . AUTH_KEY));
                    }
                }
            }

            echo '<div class="notice notice-success is-dismissible"><p>Configuration mise à jour.</p></div>';
        }

        $has_anthropic_key  = get_option('mj_anthropic_api_key', '') ? true : false;
        $has_pexels_key     = get_option('mj_pexels_api_key', '') ? true : false;
        $has_secret         = get_option('mj_gsc_client_secret_encrypted', '') ? true : false;
        $logo_url           = get_option('mj_logo_url', '');
        $default_tunnel     = get_option('mj_default_tunnel', 'TOFU');
        ?>
        <div class="wrap" style="max-width:900px; margin-top:20px;">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
            <h1 style="font-weight:700;margin:0;">Configuration</h1>
            <span style="font-size:11px;background:#2A1F14;color:#fff;padding:2px 8px;border-radius:4px;">V2</span>
          </div>

          <form method="post" action="" enctype="multipart/form-data"
                style="background:#fff; padding:30px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.05);">
            <?php wp_nonce_field('mj_settings_secure_nonce', 'mj_settings_nonce_field'); ?>

            <!-- ── Branding ── -->
            <h3 style="margin-top:0;border-bottom:1px solid #EDE5D8;padding-bottom:8px;">Branding</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
              <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Logo (URL médiathèque)</label>
                <?php if ($logo_url) : ?>
                  <div style="margin-bottom:8px;">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-height:50px;border-radius:4px;border:1px solid #EDE5D8;">
                  </div>
                <?php endif; ?>
                <input type="url" name="mj_logo_url" value="<?php echo esc_attr($logo_url); ?>"
                       class="large-text" placeholder="https://…/logo.png" style="padding:8px;border-radius:4px;">
                <p class="description">Collez l'URL d'une image de la médiathèque. Fallback : nom texte si vide.</p>
              </div>
              <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Tunnel par défaut à la génération</label>
                <select name="mj_default_tunnel" style="padding:8px;border-radius:4px;width:100%;">
                  <?php foreach (['TOFU', 'MOFU', 'BOFU'] as $t) : ?>
                    <option value="<?php echo $t; ?>" <?php selected($default_tunnel, $t); ?>><?php echo $t; ?></option>
                  <?php endforeach; ?>
                </select>
                <p class="description">Pré-sélectionné à la création d'un nouveau sujet.</p>
              </div>
            </div>

            <!-- ── API Anthropic ── -->
            <h3 style="border-bottom:1px solid #EDE5D8;padding-bottom:8px;">Clé API Anthropic (Claude)</h3>
            <?php if ($has_anthropic_key) : ?>
              <p style="color:#1D9E75;font-weight:600;font-size:12px;">✔ Clé configurée. Saisir une nouvelle valeur pour la remplacer.</p>
            <?php endif; ?>
            <input type="password" name="mj_anthropic_key" value="" class="large-text"
                   placeholder="sk-ant-…" autocomplete="new-password"
                   style="padding:10px;border-radius:4px;margin-bottom:20px;">

            <!-- ── API Pexels ── -->
            <h3 style="border-bottom:1px solid #EDE5D8;padding-bottom:8px;">
              Clé API Pexels
              <span style="font-size:10px;background:#D5EFEA;color:#0F6E56;padding:2px 7px;border-radius:20px;vertical-align:middle;margin-left:6px;">NOUVEAU</span>
            </h3>
            <p style="font-size:12px;color:#6B4F30;margin-bottom:8px;">
              Permet la sélection automatique d'une image illustrative à la génération, avec alt text généré depuis le mot-clé.
            </p>
            <?php if ($has_pexels_key) : ?>
              <p style="color:#1D9E75;font-weight:600;font-size:12px;">✔ Clé Pexels configurée.</p>
            <?php endif; ?>
            <input type="password" name="mj_pexels_key" value="" class="large-text"
                   placeholder="Clé API Pexels…" autocomplete="new-password"
                   style="padding:10px;border-radius:4px;margin-bottom:20px;">

            <!-- ── GSC Secret ── -->
            <h3 style="border-bottom:1px solid #EDE5D8;padding-bottom:8px;">Google Search Console — Client Secret</h3>
            <?php if ($has_secret) : ?>
              <p style="color:#1D9E75;font-weight:600;font-size:12px;">✔ Secret configuré.</p>
            <?php endif; ?>
            <input type="password" name="mj_gsc_client_secret" value="" class="large-text"
                   placeholder="Nouveau secret GSC…" autocomplete="new-password"
                   style="padding:10px;border-radius:4px;margin-bottom:24px;">

            <p class="submit">
              <input type="submit" name="mj_save_settings" class="button button-primary button-large"
                     value="Sauvegarder la configuration"
                     style="background:#B8935A;border-color:#A37F49;">
            </p>
          </form>
        </div>
        <?php
    }
}

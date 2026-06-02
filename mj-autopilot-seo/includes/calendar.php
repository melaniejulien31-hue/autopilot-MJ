<?php
/**
 * Module Calendrier Éditorial — planning auto 30 jours TOFU/MOFU/BOFU
 */

if (!defined('ABSPATH')) { exit; }

// ---------------------------------------------------------------------------
// Génération automatique du planning 30 jours
// ---------------------------------------------------------------------------
if (!function_exists('mj_calendar_generate_plan')) {
    /**
     * Distribue les sujets de la bibliothèque sur les 30 prochains jours.
     * Règles :
     *  - 1 article tous les 2 jours (15 slots sur 30 jours)
     *  - On alterne TOFU → MOFU → BOFU pour l'équilibre du tunnel
     *  - Les sujets déjà planifiés (scheduled_date) conservent leur date
     *  - Retourne un tableau [date_Y-m-d => [subject_data, ...]]
     */
    function mj_calendar_generate_plan($save = false) {
        $library = function_exists('mj_get_library_data') ? mj_get_library_data() : [];
        if (empty($library)) { return []; }

        $today    = strtotime('today');
        $plan     = []; // date => [slots]
        $used_ids = [];

        // 1. Placer d'abord les sujets qui ont déjà une scheduled_date
        foreach ($library as $id => $sub) {
            if (!empty($sub['scheduled_date'])) {
                $date = date('Y-m-d', strtotime($sub['scheduled_date']));
                $plan[$date][] = ['id' => $id, 'data' => $sub, 'auto' => false];
                $used_ids[] = $id;
            }
        }

        // 2. Sujets sans date, groupés par tunnel
        $remaining = [];
        foreach (['TOFU', 'MOFU', 'BOFU'] as $tunnel) {
            foreach ($library as $id => $sub) {
                if (!in_array($id, $used_ids) && $sub['tunnel'] === $tunnel) {
                    $remaining[] = ['id' => $id, 'data' => $sub];
                }
            }
        }

        // 3. Distribuer sur les 30 prochains jours (1 slot tous les 2 jours)
        $slot_dates = [];
        for ($d = 0; $d < 30; $d += 2) {
            $date = date('Y-m-d', strtotime("+{$d} days", $today));
            // Ne pas écraser un slot déjà occupé par une date planifiée
            if (!isset($plan[$date])) {
                $slot_dates[] = $date;
            }
        }

        foreach ($slot_dates as $i => $date) {
            if (!isset($remaining[$i])) { break; }
            $item = $remaining[$i];
            $plan[$date][] = ['id' => $item['id'], 'data' => $item['data'], 'auto' => true];

            // Sauvegarde optionnelle de la date dans la bibliothèque
            if ($save) {
                $library[$item['id']]['scheduled_date'] = $date . ' 09:00:00';
            }
        }

        if ($save) {
            mj_save_library_data($library);
        }

        ksort($plan);
        return $plan;
    }
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------
if (!function_exists('mj_render_calendar_page')) {
    function mj_render_calendar_page() {
        if (!current_user_can('manage_options')) { return; }

        // Action : valider le planning auto
        if (isset($_GET['action']) && $_GET['action'] === 'save_plan') {
            check_admin_referer('mj_save_plan_nonce');
            mj_calendar_generate_plan(true);
            echo '<div class="notice notice-success is-dismissible"><p><strong>Planning validé !</strong> Les dates ont été enregistrées dans la bibliothèque.</p></div>';
        }

        // Action : réinitialiser toutes les dates planifiées
        if (isset($_GET['action']) && $_GET['action'] === 'reset_plan') {
            check_admin_referer('mj_reset_plan_nonce');
            $library = mj_get_library_data();
            foreach ($library as &$sub) { $sub['scheduled_date'] = ''; }
            unset($sub);
            mj_save_library_data($library);
            echo '<div class="notice notice-success is-dismissible"><p>Planning réinitialisé.</p></div>';
        }

        $filter_tunnel = isset($_GET['tunnel']) ? sanitize_text_field($_GET['tunnel']) : '';
        $plan          = mj_calendar_generate_plan(false);
        $today         = date('Y-m-d');
        $end_date      = date('Y-m-d', strtotime('+30 days'));

        // Articles déjà publiés dans les 30 prochains jours
        $published = get_posts([
            'post_type'      => 'post',
            'post_status'    => ['publish', 'future'],
            'posts_per_page' => -1,
            'date_query'     => [['after' => 'yesterday', 'before' => '+31 days']],
            'meta_key'       => '_mj_tunnel',
        ]);
        foreach ($published as $post) {
            $date    = date('Y-m-d', strtotime($post->post_date));
            $tunnel  = get_post_meta($post->ID, '_mj_tunnel', true);
            $motcle  = get_post_meta($post->ID, '_mj_mots_cles', true);
            $score   = (int) get_post_meta($post->ID, '_mj_seo_score', true);
            $plan[$date][] = [
                'id'   => null,
                'auto' => false,
                'data' => [
                    'sujet'  => $post->post_title,
                    'motcle' => $motcle,
                    'tunnel' => $tunnel,
                    'status' => $post->post_status === 'publish' ? 'published' : 'auto-publish',
                    'geo_tag'=> get_post_meta($post->ID, '_mj_geo_tag', true),
                ],
                'published' => true,
                'url'        => get_edit_post_link($post->ID),
                'score'      => $score,
            ];
        }
        ksort($plan);

        // Stats du planning
        $total_slots    = 0;
        $auto_slots     = 0;
        $fixed_slots    = 0;
        $tunnel_counts  = ['TOFU' => 0, 'MOFU' => 0, 'BOFU' => 0];
        foreach ($plan as $date => $slots) {
            if ($date < $today || $date > $end_date) { continue; }
            foreach ($slots as $slot) {
                if (!empty($slot['published'])) { continue; }
                $total_slots++;
                if ($slot['auto'])  { $auto_slots++; }
                else                { $fixed_slots++; }
                $t = $slot['data']['tunnel'] ?? '';
                if (isset($tunnel_counts[$t])) { $tunnel_counts[$t]++; }
            }
        }

        $save_url  = wp_nonce_url(admin_url('admin.php?page=mj-autopilot-calendar&action=save_plan'),  'mj_save_plan_nonce');
        $reset_url = wp_nonce_url(admin_url('admin.php?page=mj-autopilot-calendar&action=reset_plan'), 'mj_reset_plan_nonce');
        ?>
        <div class="wrap" style="margin-top:20px; max-width:1080px;">

          <!-- En-tête -->
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
            <div style="display:flex;align-items:center;gap:10px;">
              <h1 style="font-weight:700;margin:0;">Calendrier Éditorial — 30 jours</h1>
              <span style="font-size:11px;background:#D5EFEA;color:#0F6E56;padding:2px 8px;border-radius:4px;">AUTO</span>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              <a href="<?php echo esc_url($save_url); ?>"
                 onclick="return confirm('Enregistrer ce planning dans la bibliothèque ?');"
                 class="button button-primary" style="background:#B8935A;border-color:#A37F49;">
                ✓ Valider le planning
              </a>
              <a href="<?php echo esc_url($reset_url); ?>"
                 onclick="return confirm('Effacer toutes les dates planifiées ?');"
                 class="button">↺ Réinitialiser</a>
            </div>
          </div>

          <!-- Légende planning -->
          <div style="background:#FFF8EE;border:0.5px solid #E0C890;border-radius:6px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:#7A4E20;">
            <strong>Planning automatique :</strong> les sujets de la bibliothèque sont distribués sur 30 jours, 1 article tous les 2 jours, en alternant TOFU → MOFU → BOFU.
            Cliquez <strong>"Valider le planning"</strong> pour enregistrer les dates. Vous pouvez ensuite affiner chaque date depuis la bibliothèque.
          </div>

          <!-- Stats -->
          <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:18px;">
            <?php
            $stats = [
                ['label' => 'Slots sur 30j',    'val' => $total_slots,            'color' => '#2A1F14'],
                ['label' => 'Auto-générés',      'val' => $auto_slots,             'color' => '#B8935A'],
                ['label' => 'Dates fixes',       'val' => $fixed_slots,            'color' => '#1565C0'],
                ['label' => 'TOFU / MOFU / BOFU','val' => implode(' / ', $tunnel_counts), 'color' => '#2A1F14'],
                ['label' => 'Fréquence',         'val' => '1 / 2 jours',           'color' => '#1D9E75'],
            ];
            foreach ($stats as $s) : ?>
            <div style="background:#fff;border:0.5px solid #E0D5C5;border-radius:8px;padding:10px 12px;">
              <div style="font-size:9px;color:#9A7850;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;"><?php echo esc_html($s['label']); ?></div>
              <div style="font-size:14px;font-weight:600;color:<?php echo $s['color']; ?>"><?php echo esc_html($s['val']); ?></div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Filtres tunnel -->
          <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
            <?php
            $tunnels = ['' => 'Tous', 'TOFU' => 'TOFU', 'MOFU' => 'MOFU', 'BOFU' => 'BOFU'];
            foreach ($tunnels as $val => $label) :
                $active = ($filter_tunnel === $val) ? 'background:#2A1F14;color:#fff;' : 'background:#fff;color:#7A4E20;';
                $url    = admin_url('admin.php?page=mj-autopilot-calendar' . ($val ? '&tunnel=' . $val : ''));
            ?>
            <a href="<?php echo esc_url($url); ?>"
               style="font-size:11px;padding:4px 14px;border-radius:20px;border:1px solid #C4A870;text-decoration:none;<?php echo $active; ?>">
              <?php echo esc_html($label); ?>
            </a>
            <?php endforeach; ?>
          </div>

          <!-- Légende couleurs -->
          <div style="display:flex;gap:16px;margin-bottom:12px;font-size:11px;color:#6B4F30;flex-wrap:wrap;">
            <span><span style="display:inline-block;width:10px;height:10px;background:#D5EFEA;border:1px solid #1D9E75;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>TOFU</span>
            <span><span style="display:inline-block;width:10px;height:10px;background:#F5EAD5;border:1px solid #B8935A;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>MOFU</span>
            <span><span style="display:inline-block;width:10px;height:10px;background:#FAE5DC;border:1px solid #D85A30;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>BOFU</span>
            <span><span style="display:inline-block;width:10px;height:10px;background:#EDE5D8;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>Auto-proposé</span>
            <span><span style="display:inline-block;width:10px;height:10px;background:#F0F7FF;border:1px solid #1565C0;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>Date fixée</span>
            <span><span style="display:inline-block;width:10px;height:10px;background:#E8F5E9;border:1px solid #2E7D32;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>Publié</span>
          </div>

          <!-- Timeline 30 jours -->
          <div style="background:#fff;border-radius:8px;border:0.5px solid #E0D5C5;overflow:hidden;">

            <!-- En-tête colonnes -->
            <div style="display:grid;grid-template-columns:100px 1fr 1fr 1fr 90px;background:#F0EAE0;border-bottom:0.5px solid #E0D5C5;padding:8px 12px;font-size:10px;font-weight:700;color:#6B4F30;text-transform:uppercase;letter-spacing:.06em;">
              <div>Date</div>
              <div>Sujet</div>
              <div>Mot-clé</div>
              <div>Tunnel / Statut</div>
              <div>Score SEO</div>
            </div>

            <?php
            $day_cursor = strtotime('today');
            $has_rows   = false;

            for ($d = 0; $d < 30; $d++) {
                $date      = date('Y-m-d', $day_cursor);
                $day_label = wp_date('D d/m', $day_cursor);
                $is_today  = ($date === $today);
                $slots     = $plan[$date] ?? [];

                // Filtrage tunnel
                if ($filter_tunnel) {
                    $slots = array_filter($slots, fn($s) => ($s['data']['tunnel'] ?? '') === $filter_tunnel);
                }

                if (!empty($slots)) {
                    $has_rows = true;
                    foreach ($slots as $slot) :
                        $sub        = $slot['data'];
                        $is_pub     = !empty($slot['published']);
                        $is_auto    = !empty($slot['auto']);
                        $tunnel     = $sub['tunnel'] ?? '';
                        $tc_bg      = $tunnel === 'TOFU' ? '#D5EFEA' : ($tunnel === 'BOFU' ? '#FAE5DC' : '#F5EAD5');
                        $tc_border  = $tunnel === 'TOFU' ? '#1D9E75' : ($tunnel === 'BOFU' ? '#D85A30' : '#B8935A');
                        $tc_class   = 'tag-' . strtolower($tunnel);
                        $row_bg     = $is_pub ? '#F0FAF6' : ($is_auto ? '#FFFDF7' : '#F0F7FF');
                        $score      = $slot['score'] ?? 0;
                        $score_col  = $score >= 80 ? '#1D9E75' : ($score >= 50 ? '#B8935A' : '#D85A30');
                        $edit_url   = $slot['url'] ?? ($slot['id'] ? admin_url('admin.php?page=mj-autopilot-library&action=edit&id=' . $slot['id']) : '');
                    ?>
                    <div style="display:grid;grid-template-columns:100px 1fr 1fr 1fr 90px;padding:9px 12px;border-bottom:0.5px solid #EDE5D8;background:<?php echo $row_bg; ?>;<?php echo $is_today ? 'border-left:3px solid #B8935A;' : ''; ?>">

                      <div style="font-size:11px;color:<?php echo $is_today ? '#B8935A' : '#6B4F30'; ?>;font-weight:<?php echo $is_today ? '700' : '400'; ?>;">
                        <?php echo esc_html($day_label); ?>
                        <?php if ($is_auto) echo '<br><span style="font-size:9px;color:#C4A870;">auto</span>'; ?>
                        <?php if (!$is_auto && !$is_pub) echo '<br><span style="font-size:9px;color:#1565C0;">fixé</span>'; ?>
                        <?php if ($is_pub) echo '<br><span style="font-size:9px;color:#2E7D32;">publié</span>'; ?>
                      </div>

                      <div style="font-size:11px;color:#2A1F14;padding-right:8px;">
                        <?php if ($edit_url) : ?>
                          <a href="<?php echo esc_url($edit_url); ?>" style="color:#4A3018;text-decoration:none;font-weight:500;">
                            <?php echo esc_html(wp_trim_words($sub['sujet'], 9, '…')); ?>
                          </a>
                        <?php else : ?>
                          <span style="font-weight:500;"><?php echo esc_html(wp_trim_words($sub['sujet'], 9, '…')); ?></span>
                        <?php endif; ?>
                      </div>

                      <div style="font-size:11px;color:#6B4F30;">
                        <code style="background:transparent;font-size:10px;"><?php echo esc_html($sub['motcle'] ?? '—'); ?></code>
                      </div>

                      <div style="font-size:11px;">
                        <span class="<?php echo esc_attr($tc_class); ?>"><?php echo esc_html($tunnel); ?></span>
                        &nbsp;
                        <?php if (function_exists('mj_pub_status_badge')) echo mj_pub_status_badge($sub['status'] ?? 'draft'); ?>
                        <?php if (!empty($sub['geo_tag'])) : ?>
                          <br><span style="font-size:9px;color:#9A7850;"><?php echo esc_html(ucfirst($sub['geo_tag'])); ?></span>
                        <?php endif; ?>
                      </div>

                      <div style="font-size:11px;text-align:center;">
                        <?php if ($score) : ?>
                          <strong style="color:<?php echo $score_col; ?>"><?php echo $score; ?></strong><span style="color:#9A7850;font-size:9px;">/100</span>
                        <?php else : ?>
                          <span style="color:#C4A870;">—</span>
                        <?php endif; ?>
                      </div>

                    </div>
                    <?php
                    endforeach;
                } elseif ($is_today) {
                    // Afficher quand même aujourd'hui même si vide
                    echo '<div style="padding:7px 12px;border-bottom:0.5px solid #EDE5D8;border-left:3px solid #B8935A;background:#FFF8EE;font-size:11px;color:#B8935A;font-weight:700;">' . esc_html($day_label) . ' — aujourd\'hui</div>';
                }

                $day_cursor = strtotime('+1 day', $day_cursor);
            }

            if (!$has_rows) : ?>
              <div style="padding:30px;text-align:center;color:#9A7850;font-size:12px;">
                Aucun sujet dans la bibliothèque. <a href="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-library')); ?>">Ajouter des sujets →</a>
              </div>
            <?php endif; ?>
          </div>

          <p style="font-size:11px;color:#9A7850;margin-top:12px;">
            <?php echo count($plan); ?> jours avec des slots sur les 30 prochains jours.
            Cliquez <strong>Valider le planning</strong> pour enregistrer les dates proposées dans la bibliothèque.
          </p>

        </div>
        <?php
    }
}

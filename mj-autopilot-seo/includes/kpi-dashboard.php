<?php
/**
 * Module KPI Dashboard
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('mj_render_kpi_dashboard_page')) {
    function mj_render_kpi_dashboard_page() {
        if (!current_user_can('manage_options')) { return; }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mj_autopilot_gsc_indexation';

        $total_articles = (int) wp_count_posts('post')->publish;
        $total_indexed  = 0;
        $total_pending  = 0;
        $total_clicks   = 0;
        $table_exists   = false;

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name) {
            $table_exists  = true;
            $total_indexed = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}` WHERE status = 'Indexé'");
            $total_pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}` WHERE status = 'En attente'");
            $total_clicks  = (int) $wpdb->get_var("SELECT COALESCE(SUM(clicks), 0) FROM `{$table_name}`");
        }

        $total_published  = $total_indexed + $total_pending ?: 1;
        $index_rate       = $total_published > 0 ? round($total_indexed / $total_published * 100) : 0;
        $index_bar_pct    = $total_published > 0 ? round($total_indexed / $total_published * 100) : 0;
        $pending_bar_pct  = $total_published > 0 ? round($total_pending / $total_published * 100) : 0;

        // Top articles
        $top_articles = [];
        if ($table_exists) {
            $top_articles = $wpdb->get_results(
                "SELECT t.post_id, t.clicks, p.post_title
                 FROM `{$table_name}` t
                 JOIN {$wpdb->posts} p ON p.ID = t.post_id
                 WHERE p.post_status = 'publish'
                 ORDER BY t.clicks DESC
                 LIMIT 4"
            );
        }

        // Positions moyennes par tunnel
        $tunnel_positions = [];
        if ($table_exists) {
            $raw = $wpdb->get_results(
                "SELECT pm.meta_value AS tunnel, AVG(t.position) AS avg_pos
                 FROM `{$table_name}` t
                 JOIN {$wpdb->postmeta} pm ON pm.post_id = t.post_id AND pm.meta_key = '_mj_tunnel'
                 GROUP BY pm.meta_value"
            );
            foreach ($raw as $row) {
                $tunnel_positions[$row->tunnel] = round((float)$row->avg_pos, 1);
            }
        }

        $pos_tofu  = $tunnel_positions['TOFU'] ?? '—';
        $pos_mofu  = $tunnel_positions['MOFU'] ?? '—';
        $pos_bofu  = $tunnel_positions['BOFU'] ?? '—';
        $bar_tofu  = is_numeric($pos_tofu) ? max(0, round((30 - $pos_tofu) / 30 * 100)) : 0;
        $bar_mofu  = is_numeric($pos_mofu) ? max(0, round((30 - $pos_mofu) / 30 * 100)) : 0;
        $bar_bofu  = is_numeric($pos_bofu) ? max(0, round((30 - $pos_bofu) / 30 * 100)) : 0;

        $last_sync = get_option('mj_gsc_last_sync', '—');

        // V2 — Score SEO moyen + stats GEO
        $mj_posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => '_mj_tunnel',
        ]);
        $seo_scores = array_filter(array_map(fn($p) => (int) get_post_meta($p->ID, '_mj_seo_score', true), $mj_posts));
        $avg_seo    = count($seo_scores) > 0 ? round(array_sum($seo_scores) / count($seo_scores)) : 0;
        $low_seo    = count(array_filter($seo_scores, fn($s) => $s < 50));

        $geo_tagged = array_filter($mj_posts, fn($p) => (bool) get_post_meta($p->ID, '_mj_geo_tag', true));
        $geo_pct    = count($mj_posts) > 0 ? round(count($geo_tagged) / count($mj_posts) * 100) : 0;

        // V2 — Logo
        $logo_url = get_option('mj_logo_url', '');
        ?>
        <style>
        #mj-dash *{box-sizing:border-box;margin:0;padding:0}
        #mj-dash{background:#F7F3EE;border-radius:12px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;border:0.5px solid #E0D5C5;max-width:980px;margin-top:16px}
        #mj-dash .mj-seo-alert{background:#FEF2F2;border-bottom:0.5px solid #FECACA;padding:9px 24px;font-size:12px;color:#991B1B;display:flex;align-items:center;gap:8px;}
        #mj-dash .mj-topbar{background:#fff;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;border-bottom:0.5px solid #E0D5C5}
        #mj-dash .mj-logo{display:flex;align-items:center;gap:10px}
        #mj-dash .mj-logo-dot{width:8px;height:8px;border-radius:50%;background:#B8935A;flex-shrink:0}
        #mj-dash .mj-logo-name{font-size:13px;font-weight:500;color:#2A1F14;letter-spacing:.06em}
        #mj-dash .mj-logo-sub{font-size:10px;color:#9A7850;letter-spacing:.08em;text-transform:uppercase}
        #mj-dash .mj-topbar-right{font-size:11px;color:#9A7850}
        #mj-dash .mj-layout{display:grid;grid-template-columns:176px 1fr}
        #mj-dash .mj-sidebar{background:#F0EAE0;padding:20px 0;border-right:0.5px solid #E0D5C5;min-height:560px;position:relative;overflow:hidden}
        #mj-dash .mj-nav-label{font-size:9px;font-weight:500;color:#B8A080;letter-spacing:.1em;text-transform:uppercase;padding:0 16px;margin-bottom:8px;margin-top:16px}
        #mj-dash .mj-nav-item{display:flex;align-items:center;gap:9px;padding:8px 16px;font-size:12px;color:#6B4F30;text-decoration:none}
        #mj-dash .mj-nav-item:hover{background:rgba(184,147,90,.1);color:#4A3018}
        #mj-dash .mj-nav-item.active{background:rgba(184,147,90,.18);color:#7A4E20;border-right:2px solid #B8935A}
        #mj-dash .mj-nav-item .dashicons{font-size:14px;width:14px;height:14px}
        #mj-dash .mj-nav-badge{font-size:9px;background:#D5EFEA;color:#0F6E56;padding:1px 5px;border-radius:10px;margin-left:auto;}
        #mj-dash .mj-content{padding:24px;background:#F7F3EE}
        #mj-dash .mj-page-title{font-size:18px;font-weight:500;color:#2A1F14}
        #mj-dash .mj-page-sub{font-size:12px;color:#9A7850;margin-top:3px}
        #mj-dash .mj-kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px}
        #mj-dash .mj-kpi{background:#fff;border:0.5px solid #E0D5C5;border-radius:8px;padding:12px 14px}
        #mj-dash .mj-kpi-label{font-size:10px;color:#9A7850;text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px}
        #mj-dash .mj-kpi-val{font-size:22px;font-weight:500;color:#2A1F14}
        #mj-dash .mj-kpi-delta{font-size:10px;margin-top:4px;color:#9A7850}
        #mj-dash .mj-row2{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px}
        #mj-dash .mj-card{background:#fff;border:0.5px solid #E0D5C5;border-radius:10px;padding:14px 16px}
        #mj-dash .mj-card-title{font-size:12px;font-weight:500;color:#4A3018;margin-bottom:12px;display:flex;align-items:center;gap:7px}
        #mj-dash .mj-card-title .dashicons{font-size:14px;width:14px;height:14px;color:#B8935A}
        #mj-dash .mj-tunnel-row{display:flex;align-items:center;gap:8px;margin-bottom:8px}
        #mj-dash .mj-tunnel-label{font-size:11px;color:#6B4F30;width:42px}
        #mj-dash .mj-bar-bg{flex:1;height:5px;background:#EDE5D8;border-radius:3px;overflow:hidden}
        #mj-dash .mj-bar{height:5px;border-radius:3px}
        #mj-dash .mj-tunnel-pos{font-size:11px;color:#2A1F14;min-width:28px;text-align:right}
        #mj-dash .mj-art-row{display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:0.5px solid #EDE5D8}
        #mj-dash .mj-art-num{font-size:10px;color:#C4A870;min-width:14px}
        #mj-dash .mj-art-title{font-size:11px;color:#4A3018;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        #mj-dash .mj-art-clics{font-size:11px;color:#2A1F14;font-weight:500;min-width:36px;text-align:right}
        #mj-dash .mj-index-row{display:flex;align-items:center;gap:8px;margin-bottom:10px}
        #mj-dash .mj-index-label{font-size:11px;color:#6B4F30;flex:1}
        #mj-dash .mj-index-count{font-size:13px;font-weight:500;color:#2A1F14;min-width:28px}
        #mj-dash .mj-prog-bg{height:6px;background:#EDE5D8;border-radius:3px;overflow:hidden;flex:2}
        #mj-dash .mj-prog-bar{height:6px;border-radius:3px;background:#B8935A}
        #mj-dash .tag-tofu{display:inline-block;font-size:9px;padding:2px 7px;border-radius:20px;font-weight:500;background:#D5EFEA;color:#0F6E56}
        #mj-dash .tag-mofu{display:inline-block;font-size:9px;padding:2px 7px;border-radius:20px;font-weight:500;background:#F5EAD5;color:#7A4E20}
        #mj-dash .tag-bofu{display:inline-block;font-size:9px;padding:2px 7px;border-radius:20px;font-weight:500;background:#FAE5DC;color:#993C1D}
        #mj-dash .mj-botton-bar{padding:12px 16px;border-top:0.5px solid #E0D5C5;background:#fff;display:flex;gap:8px;flex-wrap:wrap}
        #mj-dash .mj-btn{font-size:11px;padding:6px 14px;border-radius:4px;border:0.5px solid #C4A870;background:transparent;color:#7A4E20;cursor:pointer;text-decoration:none;display:inline-block}
        #mj-dash .mj-btn-primary{background:#B8935A;color:#fff;border-color:#B8935A}
        #mj-dash .mj-sidebar-illus{position:absolute;bottom:0;left:0;right:0;pointer-events:none}
        #mj-dash .mj-notice{margin:0 24px 16px;padding:10px 14px;background:#FFF8EE;border:0.5px solid #E0C890;border-radius:6px;font-size:12px;color:#7A4E20}
        </style>

        <div id="mj-dash">

          <?php if ($low_seo > 0) : ?>
          <div class="mj-seo-alert">
            <span style="font-size:14px;">⚠</span>
            <strong><?php echo intval($low_seo); ?> article(s)</strong> ont un score SEO inférieur à 50/100 — consultez la bibliothèque pour optimiser.
            <a href="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-library')); ?>" style="margin-left:8px;color:#991B1B;text-decoration:underline;">Voir →</a>
          </div>
          <?php endif; ?>

          <div class="mj-topbar">
            <div class="mj-logo">
              <?php if ($logo_url) : ?>
                <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-height:32px;border-radius:4px;">
              <?php else : ?>
                <div class="mj-logo-dot"></div>
              <?php endif; ?>
              <div>
                <div class="mj-logo-name">MJ Autopilot SEO</div>
                <div class="mj-logo-sub">v<?php echo esc_html(MJ_AUTOPILOT_SEO_VERSION); ?> — melaniejulien.com</div>
              </div>
            </div>
            <div class="mj-topbar-right">Dernière sync GSC : <?php echo esc_html($last_sync); ?></div>
          </div>

          <div class="mj-layout">
            <div class="mj-sidebar">
              <div class="mj-nav-label">Principal</div>
              <a href="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-seo')); ?>" class="mj-nav-item active">
                <span class="dashicons dashicons-chart-area"></span> Dashboard
              </a>
              <a href="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-library')); ?>" class="mj-nav-item">
                <span class="dashicons dashicons-book"></span> Bibliothèque
              </a>
              <a href="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-calendar')); ?>" class="mj-nav-item">
                <span class="dashicons dashicons-calendar-alt"></span> Calendrier
                <span class="mj-nav-badge">NEW</span>
              </a>

              <div class="mj-nav-label">Données</div>
              <a href="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-opportunities')); ?>" class="mj-nav-item">
                <span class="dashicons dashicons-search"></span> Search Console
              </a>
              <a href="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-opportunities')); ?>" class="mj-nav-item">
                <span class="dashicons dashicons-lightbulb"></span> Opportunités
              </a>
              <a href="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-geo-report')); ?>" class="mj-nav-item">
                <span class="dashicons dashicons-location"></span> Rapport GEO
                <span class="mj-nav-badge">NEW</span>
              </a>

              <div class="mj-nav-label">Système</div>
              <a href="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-settings')); ?>" class="mj-nav-item">
                <span class="dashicons dashicons-admin-settings"></span> Réglages
              </a>

              <div class="mj-sidebar-illus">
                <svg width="176" height="130" viewBox="0 0 176 130" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M20 130 Q30 100 50 95 Q60 92 65 80 Q70 68 80 65 Q95 60 100 45" stroke="#C4A870" stroke-width="1.2" fill="none"/>
                  <path d="M65 80 Q55 72 48 76 Q40 80 38 90" stroke="#C4A870" stroke-width="0.8" fill="none"/>
                  <path d="M80 65 Q85 55 78 48 Q72 42 68 50" stroke="#B89050" stroke-width="0.8" fill="none"/>
                  <path d="M100 45 Q110 35 105 25 Q100 16 92 22 Q86 27 90 35" stroke="#C4A870" stroke-width="0.8" fill="none"/>
                  <ellipse cx="52" cy="74" rx="9" ry="6" transform="rotate(-25 52 74)" fill="#D4B888" opacity=".55"/>
                  <ellipse cx="42" cy="86" rx="8" ry="5" transform="rotate(-40 42 86)" fill="#D4B888" opacity=".4"/>
                  <ellipse cx="83" cy="52" rx="10" ry="6" transform="rotate(-15 83 52)" fill="#C4A870" opacity=".5"/>
                  <ellipse cx="75" cy="44" rx="7" ry="5" transform="rotate(-35 75 44)" fill="#C4A870" opacity=".35"/>
                  <ellipse cx="104" cy="30" rx="11" ry="7" transform="rotate(-10 104 30)" fill="#D4B888" opacity=".5"/>
                  <ellipse cx="94" cy="26" rx="8" ry="5" transform="rotate(-30 94 26)" fill="#B89050" opacity=".4"/>
                  <path d="M130 130 Q145 105 155 90 Q162 78 158 65 Q154 52 162 42 Q168 34 165 22" stroke="#B89050" stroke-width="1.2" fill="none"/>
                  <ellipse cx="162" cy="50" rx="9" ry="6" transform="rotate(20 162 50)" fill="#C4A870" opacity=".45"/>
                  <ellipse cx="166" cy="28" rx="10" ry="6" transform="rotate(15 166 28)" fill="#D4B888" opacity=".4"/>
                </svg>
              </div>
            </div>

            <div class="mj-content">
              <?php if (!$table_exists) : ?>
                <div class="mj-notice">Table GSC non initialisée — désactivez puis réactivez le plugin.</div>
              <?php endif; ?>

              <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px">
                <div>
                  <div class="mj-page-title">Dashboard KPIs</div>
                  <div class="mj-page-sub">Vue globale — <?php echo esc_html(wp_date('d F Y')); ?></div>
                </div>
                <svg width="80" height="60" viewBox="0 0 80 60" fill="none" xmlns="http://www.w3.org/2000/svg" opacity=".7">
                  <path d="M40 58 Q42 45 50 38 Q58 31 55 20 Q52 10 44 14 Q38 17 40 26 Q42 35 35 38 Q28 41 26 52" stroke="#B8935A" stroke-width="1" fill="none"/>
                  <ellipse cx="32" cy="32" rx="8" ry="5" transform="rotate(-20 32 32)" fill="#C4A870" opacity=".5"/>
                  <ellipse cx="44" cy="14" rx="8" ry="5" transform="rotate(-10 44 14)" fill="#C4A870" opacity=".4"/>
                </svg>
              </div>

              <!-- KPIs row -->
              <div class="mj-kpi-grid">
                <div class="mj-kpi">
                  <div class="mj-kpi-label">Articles publiés</div>
                  <div class="mj-kpi-val"><?php echo esc_html($total_articles); ?></div>
                </div>
                <div class="mj-kpi">
                  <div class="mj-kpi-label">Taux d'indexation</div>
                  <div class="mj-kpi-val"><?php echo esc_html($index_rate); ?>%</div>
                  <div class="mj-kpi-delta"><?php echo esc_html($total_indexed); ?> indexés / <?php echo esc_html($total_pending); ?> en attente</div>
                </div>
                <div class="mj-kpi">
                  <div class="mj-kpi-label">Score SEO moyen</div>
                  <div class="mj-kpi-val" style="color:<?php echo $avg_seo >= 80 ? '#1D9E75' : ($avg_seo >= 50 ? '#B8935A' : '#D85A30'); ?>">
                    <?php echo $avg_seo ?: '—'; ?>
                    <?php if ($avg_seo) echo '/100'; ?>
                  </div>
                  <?php if ($low_seo > 0) : ?>
                    <div class="mj-kpi-delta" style="color:#D85A30;"><?php echo $low_seo; ?> article(s) &lt; 50</div>
                  <?php endif; ?>
                </div>
                <div class="mj-kpi">
                  <div class="mj-kpi-label">Couverture GEO</div>
                  <div class="mj-kpi-val"><?php echo $geo_pct; ?>%</div>
                  <div class="mj-kpi-delta"><?php echo count($geo_tagged); ?> / <?php echo count($mj_posts); ?> articles tagués</div>
                </div>
              </div>

              <!-- Row 2 : tunnels + top articles -->
              <div class="mj-row2">
                <div class="mj-card">
                  <div class="mj-card-title"><span class="dashicons dashicons-networking"></span> Positions par tunnel</div>
                  <div class="mj-tunnel-row">
                    <div class="mj-tunnel-label"><span class="tag-tofu">TOFU</span></div>
                    <div class="mj-bar-bg"><div class="mj-bar" style="width:<?php echo esc_attr($bar_tofu); ?>%;background:#1D9E75"></div></div>
                    <div class="mj-tunnel-pos"><?php echo esc_html($pos_tofu); ?></div>
                  </div>
                  <div class="mj-tunnel-row">
                    <div class="mj-tunnel-label"><span class="tag-mofu">MOFU</span></div>
                    <div class="mj-bar-bg"><div class="mj-bar" style="width:<?php echo esc_attr($bar_mofu); ?>%;background:#B8935A"></div></div>
                    <div class="mj-tunnel-pos"><?php echo esc_html($pos_mofu); ?></div>
                  </div>
                  <div class="mj-tunnel-row">
                    <div class="mj-tunnel-label"><span class="tag-bofu">BOFU</span></div>
                    <div class="mj-bar-bg"><div class="mj-bar" style="width:<?php echo esc_attr($bar_bofu); ?>%;background:#D85A30"></div></div>
                    <div class="mj-tunnel-pos"><?php echo esc_html($pos_bofu); ?></div>
                  </div>
                </div>

                <div class="mj-card">
                  <div class="mj-card-title"><span class="dashicons dashicons-star-filled"></span> Top articles (clics GSC)</div>
                  <?php if (!empty($top_articles)) : ?>
                    <?php foreach ($top_articles as $i => $art) : ?>
                      <div class="mj-art-row">
                        <div class="mj-art-num"><?php echo esc_html($i + 1); ?></div>
                        <div class="mj-art-title"><?php echo esc_html($art->post_title); ?></div>
                        <div class="mj-art-clics"><?php echo esc_html(number_format((int)$art->clicks, 0, ',', ' ')); ?></div>
                      </div>
                    <?php endforeach; ?>
                  <?php else : ?>
                    <p style="font-size:11px;color:#9A7850;padding:8px 0">Aucune donnée GSC disponible.</p>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Row 3 : indexation + actions -->
              <div class="mj-row2">
                <div class="mj-card">
                  <div class="mj-card-title"><span class="dashicons dashicons-yes-alt"></span> Indexation Google</div>
                  <div class="mj-index-row">
                    <div class="mj-index-label">Indexés</div>
                    <div class="mj-prog-bg"><div class="mj-prog-bar" style="width:<?php echo esc_attr($index_bar_pct); ?>%;background:#1D9E75"></div></div>
                    <div class="mj-index-count"><?php echo esc_html($total_indexed); ?></div>
                  </div>
                  <div class="mj-index-row">
                    <div class="mj-index-label">En attente</div>
                    <div class="mj-prog-bg"><div class="mj-prog-bar" style="width:<?php echo esc_attr($pending_bar_pct); ?>%"></div></div>
                    <div class="mj-index-count"><?php echo esc_html($total_pending); ?></div>
                  </div>
                </div>

                <div class="mj-card">
                  <div class="mj-card-title"><span class="dashicons dashicons-admin-links"></span> Actions rapides</div>
                  <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-library')); ?>" class="mj-btn mj-btn-primary">Bibliothèque éditoriale</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-calendar')); ?>" class="mj-btn">Calendrier TOFU/MOFU/BOFU</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-geo-report')); ?>" class="mj-btn">Rapport GEO hebdo</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-settings')); ?>" class="mj-btn">Configuration API</a>
                  </div>
                </div>
              </div>

              <div class="mj-botton-bar">
                <a href="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-settings')); ?>" class="mj-btn mj-btn-primary">Synchroniser GSC</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-library')); ?>" class="mj-btn">Bibliothèque éditoriale</a>
              </div>
            </div>
          </div>
        </div>
        <?php
    }
}

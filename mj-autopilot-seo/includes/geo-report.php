<?php
/**
 * Module Rapport GEO — récapitulatif hebdomadaire des articles optimisés GEO
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('mj_render_geo_report_page')) {
    function mj_render_geo_report_page() {
        if (!current_user_can('manage_options')) { return; }

        // Récupérer tous les articles générés par le plugin (qui ont _mj_tunnel)
        $all_posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => ['publish', 'pending', 'draft', 'future'],
            'posts_per_page' => -1,
            'meta_key'       => '_mj_tunnel',
        ]);

        $total         = count($all_posts);
        $by_tag        = ['local' => [], 'national' => [], 'international' => [], 'non-tagué' => []];
        $geo_total     = 0;
        $no_geo        = 0;
        $avg_score_geo = 0;
        $avg_score_all = 0;
        $scores        = [];

        // Signaux géographiques locaux — liste de mots courants indiquant une portée locale
        $local_signals = ['paris', 'lyon', 'bordeaux', 'marseille', 'toulouse', 'nantes', 'lille', 'strasbourg', 'france', 'français', 'française', 'région', 'ville', 'département'];

        foreach ($all_posts as $post) {
            $tag   = get_post_meta($post->ID, '_mj_geo_tag', true);
            $score = (int) get_post_meta($post->ID, '_mj_seo_score', true);
            $scores[] = $score;

            if ($tag && isset($by_tag[$tag])) {
                $by_tag[$tag][] = $post;
                $geo_total++;
            } else {
                $by_tag['non-tagué'][] = $post;
                $no_geo++;
            }

            // Vérification des signaux géographiques dans le contenu si tag = local
            if ($tag === 'local') {
                $plain    = strtolower(wp_strip_all_tags($post->post_content));
                $detected = [];
                foreach ($local_signals as $signal) {
                    if (str_contains($plain, $signal)) { $detected[] = $signal; }
                }
                // Stocker les signaux détectés dans le post pour affichage
                $post->_geo_signals = $detected;
            }
        }

        $avg_score_all = $total > 0 ? round(array_sum($scores) / $total) : 0;
        $geo_pct       = $total > 0 ? round($geo_total / $total * 100) : 0;

        // Rapport hebdomadaire : articles créés dans les 7 derniers jours
        $week_ago       = date('Y-m-d H:i:s', strtotime('-7 days'));
        $recent_posts   = array_filter($all_posts, fn($p) => $p->post_date >= $week_ago);
        $recent_geo     = array_filter($recent_posts, fn($p) => (bool) get_post_meta($p->ID, '_mj_geo_tag', true));
        ?>
        <div class="wrap" style="margin-top:20px; max-width:1100px;">

          <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
            <h1 style="font-weight:700;margin:0;">Rapport GEO</h1>
            <span style="font-size:11px;background:#D5EFEA;color:#0F6E56;padding:2px 8px;border-radius:4px;">NOUVEAU</span>
          </div>

          <!-- KPIs GEO -->
          <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
            <?php
            $kpis = [
                ['label' => 'Articles totaux',     'val' => $total,     'delta' => ''],
                ['label' => 'Articles avec tag GEO','val' => $geo_total, 'delta' => $geo_pct . '% du total'],
                ['label' => 'Sans tag GEO',         'val' => $no_geo,    'delta' => ($total - $geo_total) . ' à tagger'],
                ['label' => 'Score SEO moyen',      'val' => $avg_score_all . '/100', 'delta' => ''],
            ];
            foreach ($kpis as $kpi) : ?>
            <div style="background:#fff;border:0.5px solid #E0D5C5;border-radius:8px;padding:14px;">
              <div style="font-size:10px;color:#9A7850;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;"><?php echo esc_html($kpi['label']); ?></div>
              <div style="font-size:22px;font-weight:500;color:#2A1F14;"><?php echo esc_html($kpi['val']); ?></div>
              <?php if ($kpi['delta']) : ?>
                <div style="font-size:10px;color:#9A7850;margin-top:4px;"><?php echo esc_html($kpi['delta']); ?></div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Rapport hebdo -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">

            <div style="background:#fff;border:0.5px solid #E0D5C5;border-radius:8px;padding:16px;">
              <h3 style="font-size:13px;margin:0 0 12px;color:#2A1F14;">Rapport de la semaine (7 derniers jours)</h3>
              <div style="display:flex;gap:20px;">
                <div style="text-align:center;">
                  <div style="font-size:28px;font-weight:600;color:#1D9E75;"><?php echo count($recent_geo); ?></div>
                  <div style="font-size:11px;color:#9A7850;">Optimisés GEO</div>
                </div>
                <div style="text-align:center;">
                  <div style="font-size:28px;font-weight:600;color:#D85A30;"><?php echo count($recent_posts) - count($recent_geo); ?></div>
                  <div style="font-size:11px;color:#9A7850;">Non optimisés</div>
                </div>
                <div style="text-align:center;">
                  <div style="font-size:28px;font-weight:600;color:#2A1F14;"><?php echo count($recent_posts); ?></div>
                  <div style="font-size:11px;color:#9A7850;">Total créés</div>
                </div>
              </div>
              <?php if (count($recent_posts) > 0) :
                  $recent_geo_pct = round(count($recent_geo) / count($recent_posts) * 100); ?>
                <div style="margin-top:12px;">
                  <div style="font-size:11px;color:#6B4F30;margin-bottom:4px;">Taux d'optimisation GEO cette semaine</div>
                  <div style="height:6px;background:#EDE5D8;border-radius:3px;overflow:hidden;">
                    <div style="height:6px;width:<?php echo $recent_geo_pct; ?>%;background:#1D9E75;border-radius:3px;"></div>
                  </div>
                  <div style="font-size:11px;color:#2A1F14;margin-top:4px;font-weight:600;"><?php echo $recent_geo_pct; ?>%</div>
                </div>
              <?php endif; ?>
            </div>

            <!-- Répartition par tag -->
            <div style="background:#fff;border:0.5px solid #E0D5C5;border-radius:8px;padding:16px;">
              <h3 style="font-size:13px;margin:0 0 12px;color:#2A1F14;">Répartition par portée géographique</h3>
              <?php
              $tag_colors = [
                  'local'         => '#B8935A',
                  'national'      => '#1D9E75',
                  'international' => '#4A7FB8',
                  'non-tagué'     => '#D85A30',
              ];
              $tag_labels = [
                  'local'         => 'Local',
                  'national'      => 'National',
                  'international' => 'International',
                  'non-tagué'     => 'Non tagué',
              ];
              foreach ($by_tag as $tag => $posts_in_tag) :
                  $count = count($posts_in_tag);
                  $pct   = $total > 0 ? round($count / $total * 100) : 0;
                  $color = $tag_colors[$tag];
              ?>
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <div style="font-size:11px;color:#6B4F30;width:90px;"><?php echo esc_html($tag_labels[$tag]); ?></div>
                <div style="flex:1;height:5px;background:#EDE5D8;border-radius:3px;overflow:hidden;">
                  <div style="height:5px;width:<?php echo $pct; ?>%;background:<?php echo $color; ?>;border-radius:3px;"></div>
                </div>
                <div style="font-size:11px;color:#2A1F14;min-width:36px;text-align:right;"><?php echo $count; ?> (<?php echo $pct; ?>%)</div>
              </div>
              <?php endforeach; ?>
            </div>

          </div>

          <!-- Tableau détaillé par article -->
          <div style="background:#fff;border-radius:8px;border:0.5px solid #E0D5C5;overflow:hidden;">
            <div style="padding:14px 16px;border-bottom:0.5px solid #E0D5C5;display:flex;align-items:center;justify-content:space-between;">
              <h3 style="font-size:13px;margin:0;color:#2A1F14;">Articles générés — détail GEO</h3>
              <span style="font-size:11px;color:#9A7850;"><?php echo $total; ?> articles</span>
            </div>
            <?php if (empty($all_posts)) : ?>
              <p style="padding:20px;color:#9A7850;font-size:12px;">Aucun article généré pour l'instant.</p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped" style="border:none;">
              <thead>
                <tr>
                  <th style="padding:10px;font-weight:700;">Article</th>
                  <th style="width:80px;padding:10px;font-weight:700;">Tunnel</th>
                  <th style="width:100px;padding:10px;font-weight:700;">Tag GEO</th>
                  <th style="width:90px;padding:10px;font-weight:700;">Score SEO</th>
                  <th style="width:90px;padding:10px;font-weight:700;">Statut</th>
                  <th style="width:180px;padding:10px;font-weight:700;">Signaux locaux</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($all_posts as $post) :
                    $tag     = get_post_meta($post->ID, '_mj_geo_tag', true) ?: 'non-tagué';
                    $score   = (int) get_post_meta($post->ID, '_mj_seo_score', true);
                    $tunnel  = get_post_meta($post->ID, '_mj_tunnel', true);
                    $sc      = $score >= 80 ? '#1D9E75' : ($score >= 50 ? '#B8935A' : '#D85A30');
                    $tc      = $tag_colors[$tag] ?? '#9A7850';
                    $tc_cls  = $tunnel === 'TOFU' ? 'tag-tofu' : ($tunnel === 'BOFU' ? 'tag-bofu' : 'tag-mofu');

                    // Signaux locaux
                    $signals = [];
                    if ($tag === 'local') {
                        $plain = strtolower(wp_strip_all_tags($post->post_content));
                        foreach ($local_signals as $signal) {
                            if (str_contains($plain, $signal)) { $signals[] = $signal; }
                        }
                    }
                ?>
                <tr>
                  <td style="padding:10px;">
                    <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>" style="color:#4A3018;font-weight:600;">
                      <?php echo esc_html(wp_trim_words($post->post_title, 8, '…')); ?>
                    </a>
                    <br><span style="font-size:10px;color:#9A7850;"><?php echo esc_html(wp_date('d/m/Y', strtotime($post->post_date))); ?></span>
                  </td>
                  <td style="padding:10px;"><span class="<?php echo esc_attr($tc_cls); ?>"><?php echo esc_html($tunnel); ?></span></td>
                  <td style="padding:10px;">
                    <span style="font-size:10px;padding:2px 8px;border-radius:20px;border:1px solid <?php echo $tc; ?>;color:<?php echo $tc; ?>;">
                      <?php echo esc_html($tag_labels[$tag] ?? $tag); ?>
                    </span>
                  </td>
                  <td style="padding:10px;">
                    <strong style="color:<?php echo $sc; ?>"><?php echo $score; ?>/100</strong>
                  </td>
                  <td style="padding:10px;">
                    <span style="font-size:10px;color:#6B4F30;"><?php echo esc_html(ucfirst($post->post_status)); ?></span>
                  </td>
                  <td style="padding:10px;font-size:10px;color:#6B4F30;">
                    <?php if (!empty($signals)) : ?>
                      <?php echo esc_html(implode(', ', array_slice($signals, 0, 4))); ?>
                    <?php elseif ($tag === 'local') : ?>
                      <span style="color:#D85A30;">⚠ Aucun signal local détecté</span>
                    <?php else : ?>
                      <span style="color:#C4A870;">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>

        </div>
        <?php
    }
}

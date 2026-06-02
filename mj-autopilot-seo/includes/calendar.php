<?php
/**
 * Module Calendrier Éditorial — vue mensuelle TOFU/MOFU/BOFU
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('mj_render_calendar_page')) {
    function mj_render_calendar_page() {
        if (!current_user_can('manage_options')) { return; }

        // Navigation mois
        $year  = isset($_GET['cal_year'])  ? intval($_GET['cal_year'])  : (int) wp_date('Y');
        $month = isset($_GET['cal_month']) ? intval($_GET['cal_month']) : (int) wp_date('m');
        if ($month < 1)  { $month = 12; $year--; }
        if ($month > 12) { $month = 1;  $year++; }

        $filter_tunnel = isset($_GET['tunnel']) ? sanitize_text_field($_GET['tunnel']) : '';

        $prev_month = $month - 1 ?: 12;
        $prev_year  = $month - 1 ? $year : $year - 1;
        $next_month = $month % 12 + 1;
        $next_year  = $month === 12 ? $year + 1 : $year;

        $prev_url = admin_url('admin.php?page=mj-autopilot-calendar&cal_year=' . $prev_year . '&cal_month=' . $prev_month . ($filter_tunnel ? '&tunnel=' . $filter_tunnel : ''));
        $next_url = admin_url('admin.php?page=mj-autopilot-calendar&cal_year=' . $next_year . '&cal_month=' . $next_month . ($filter_tunnel ? '&tunnel=' . $filter_tunnel : ''));

        // Jours du mois
        $days_in_month = (int) wp_date('t', mktime(0, 0, 0, $month, 1, $year));
        $first_dow     = (int) wp_date('N', mktime(0, 0, 0, $month, 1, $year)); // 1=lun … 7=dim

        // Sujets de la bibliothèque avec scheduled_date dans ce mois
        $library = function_exists('mj_get_library_data') ? mj_get_library_data() : [];
        $slots   = []; // day => [items]

        foreach ($library as $id => $sub) {
            if ($filter_tunnel && $sub['tunnel'] !== $filter_tunnel) { continue; }
            if (!empty($sub['scheduled_date'])) {
                $ts = strtotime($sub['scheduled_date']);
                if ($ts && (int) wp_date('Y', $ts) === $year && (int) wp_date('n', $ts) === $month) {
                    $day = (int) wp_date('j', $ts);
                    $slots[$day][] = ['type' => 'planned', 'data' => $sub, 'id' => $id];
                }
            }
        }

        // Articles publiés dans ce mois
        $posts = get_posts([
            'post_type'   => 'post',
            'post_status' => 'publish',
            'year'        => $year,
            'monthnum'    => $month,
            'posts_per_page' => -1,
            'meta_query'  => $filter_tunnel ? [['key' => '_mj_tunnel', 'value' => $filter_tunnel]] : [],
        ]);

        foreach ($posts as $post) {
            $day = (int) get_the_date('j', $post);
            $slots[$day][] = [
                'type'   => 'published',
                'title'  => $post->post_title,
                'tunnel' => get_post_meta($post->ID, '_mj_tunnel', true),
                'motcle' => get_post_meta($post->ID, '_mj_mots_cles', true),
                'score'  => (int) get_post_meta($post->ID, '_mj_seo_score', true),
                'url'    => get_edit_post_link($post->ID),
            ];
        }

        $month_name = wp_date('F Y', mktime(0, 0, 0, $month, 1, $year));
        ?>
        <div class="wrap" style="margin-top:20px; max-width:1100px;">

          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <div style="display:flex;align-items:center;gap:10px;">
              <h1 style="font-weight:700;margin:0;">Calendrier Éditorial</h1>
              <span style="font-size:11px;background:#D5EFEA;color:#0F6E56;padding:2px 8px;border-radius:4px;">NOUVEAU</span>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
              <a href="<?php echo esc_url($prev_url); ?>" class="button">← <?php echo esc_html(wp_date('M', mktime(0,0,0,$prev_month,1,$prev_year))); ?></a>
              <strong style="font-size:14px;color:#2A1F14;"><?php echo esc_html(ucfirst($month_name)); ?></strong>
              <a href="<?php echo esc_url($next_url); ?>" class="button"><?php echo esc_html(wp_date('M', mktime(0,0,0,$next_month,1,$next_year))); ?> →</a>
            </div>
          </div>

          <!-- Filtres tunnel -->
          <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
            <?php
            $tunnels = ['' => 'Tous les tunnels', 'TOFU' => 'TOFU', 'MOFU' => 'MOFU', 'BOFU' => 'BOFU'];
            foreach ($tunnels as $val => $label) :
                $active = ($filter_tunnel === $val) ? 'background:#2A1F14;color:#fff;' : 'background:#fff;color:#7A4E20;';
                $url    = admin_url('admin.php?page=mj-autopilot-calendar&cal_year=' . $year . '&cal_month=' . $month . ($val ? '&tunnel=' . $val : ''));
            ?>
            <a href="<?php echo esc_url($url); ?>"
               style="font-size:11px;padding:4px 14px;border-radius:20px;border:1px solid #C4A870;text-decoration:none;<?php echo $active; ?>">
              <?php echo esc_html($label); ?>
            </a>
            <?php endforeach; ?>
          </div>

          <!-- Légende -->
          <div style="display:flex;gap:16px;margin-bottom:12px;font-size:11px;color:#6B4F30;">
            <span><span style="display:inline-block;width:10px;height:10px;background:#1D9E75;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>Publié</span>
            <span><span style="display:inline-block;width:10px;height:10px;background:#B8935A;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>Planifié</span>
          </div>

          <!-- Grille calendrier -->
          <div style="background:#fff;border-radius:8px;border:0.5px solid #E0D5C5;overflow:hidden;">

            <!-- Jours de la semaine -->
            <div style="display:grid;grid-template-columns:repeat(7,1fr);background:#F0EAE0;border-bottom:0.5px solid #E0D5C5;">
              <?php foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $d) : ?>
                <div style="padding:8px;text-align:center;font-size:11px;font-weight:600;color:#6B4F30;"><?php echo $d; ?></div>
              <?php endforeach; ?>
            </div>

            <!-- Cellules -->
            <div style="display:grid;grid-template-columns:repeat(7,1fr);">
              <?php
              // Cases vides avant le 1er du mois
              for ($i = 1; $i < $first_dow; $i++) {
                  echo '<div style="min-height:90px;border-right:0.5px solid #EDE5D8;border-bottom:0.5px solid #EDE5D8;background:#F7F3EE;"></div>';
              }

              $today = (int) wp_date('j');
              $is_current_month = ((int) wp_date('n') === $month && (int) wp_date('Y') === $year);

              for ($day = 1; $day <= $days_in_month; $day++) :
                  $is_today   = $is_current_month && $day === $today;
                  $day_slots  = $slots[$day] ?? [];
                  $col        = (($first_dow - 1 + $day - 1) % 7) + 1;
                  $border_r   = $col < 7 ? '0.5px solid #EDE5D8' : 'none';
              ?>
              <div style="min-height:90px;border-right:<?php echo $border_r; ?>;border-bottom:0.5px solid #EDE5D8;padding:6px;
                          background:<?php echo $is_today ? '#FFF8EE' : '#fff'; ?>;">
                <div style="font-size:11px;font-weight:<?php echo $is_today ? '700' : '400'; ?>;
                             color:<?php echo $is_today ? '#B8935A' : '#6B4F30'; ?>;margin-bottom:4px;">
                  <?php echo $day; ?>
                </div>
                <?php foreach ($day_slots as $slot) :
                    if ($slot['type'] === 'published') :
                        $tc = $slot['tunnel'] === 'TOFU' ? '#1D9E75' : ($slot['tunnel'] === 'BOFU' ? '#D85A30' : '#B8935A');
                ?>
                  <div style="background:#F0FAF6;border-left:3px solid <?php echo $tc; ?>;
                               padding:3px 6px;border-radius:0 4px 4px 0;margin-bottom:3px;font-size:10px;">
                    <?php if ($slot['url']) : ?>
                      <a href="<?php echo esc_url($slot['url']); ?>" style="color:#2A1F14;text-decoration:none;">
                        <?php echo esc_html(wp_trim_words($slot['title'], 6, '…')); ?>
                      </a>
                    <?php else : ?>
                      <?php echo esc_html(wp_trim_words($slot['title'], 6, '…')); ?>
                    <?php endif; ?>
                    <br>
                    <span style="color:#9A7850;"><?php echo esc_html($slot['motcle']); ?></span>
                    <?php if ($slot['score']) : ?>
                      <span style="float:right;font-weight:600;color:<?php echo $slot['score'] >= 80 ? '#1D9E75' : '#B8935A'; ?>;">
                        <?php echo $slot['score']; ?>
                      </span>
                    <?php endif; ?>
                  </div>
                <?php elseif ($slot['type'] === 'planned') :
                    $sub = $slot['data'];
                    $tc  = $sub['tunnel'] === 'TOFU' ? '#1D9E75' : ($sub['tunnel'] === 'BOFU' ? '#D85A30' : '#B8935A');
                    $edit_url = admin_url('admin.php?page=mj-autopilot-library&action=edit&id=' . $slot['id']);
                ?>
                  <div style="background:#FFF8EE;border-left:3px solid <?php echo $tc; ?>;
                               padding:3px 6px;border-radius:0 4px 4px 0;margin-bottom:3px;font-size:10px;">
                    <a href="<?php echo esc_url($edit_url); ?>" style="color:#7A4E20;text-decoration:none;">
                      <?php echo esc_html(wp_trim_words($sub['sujet'], 6, '…')); ?>
                    </a>
                    <br>
                    <span style="color:#9A7850;"><?php echo esc_html($sub['motcle']); ?></span>
                    &nbsp;<?php echo mj_pub_status_badge($sub['status']); ?>
                  </div>
                <?php endif; ?>
                <?php endforeach; ?>
              </div>
              <?php endfor; ?>

              <?php
              // Cases vides après le dernier jour
              $last_dow = (($first_dow - 1 + $days_in_month - 1) % 7) + 1;
              for ($i = $last_dow; $i < 7; $i++) {
                  echo '<div style="min-height:90px;border-bottom:0.5px solid #EDE5D8;background:#F7F3EE;"></div>';
              }
              ?>
            </div>
          </div>

          <p style="font-size:11px;color:#9A7850;margin-top:12px;">
            Les slots planifiés apparaissent selon la date configurée dans la bibliothèque. Les articles publiés sont affichés à leur date de publication.
          </p>

        </div>
        <?php
    }
}

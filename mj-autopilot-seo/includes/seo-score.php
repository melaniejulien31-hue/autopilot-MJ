<?php
/**
 * Module Score SEO — calcul live et checklist
 */

if (!defined('ABSPATH')) { exit; }

// ---------------------------------------------------------------------------
// Calcul du score SEO (0–100) pour un article publié
// ---------------------------------------------------------------------------
if (!function_exists('mj_calculate_seo_score')) {
    function mj_calculate_seo_score($post_id) {
        $post    = get_post($post_id);
        if (!$post) { return 0; }

        $score   = 0;
        $keyword = strtolower(get_post_meta($post_id, '_mj_mots_cles', true));
        $content = $post->post_content;
        $title   = strtolower($post->post_title);

        // 1. Titre contient le mot-clé (20 pts)
        if ($keyword && str_contains($title, $keyword)) { $score += 20; }

        // 2. Meta description renseignée (20 pts)
        if (get_post_meta($post_id, '_mj_meta_description', true)) { $score += 20; }

        // 3. Image à la une avec alt text (20 pts)
        $thumb_id = get_post_thumbnail_id($post_id);
        if ($thumb_id && get_post_meta($thumb_id, '_wp_attachment_image_alt', true)) { $score += 20; }

        // 4. Maillage interne : au moins 1 lien interne dans le contenu (20 pts)
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        if ($site_host && str_contains($content, $site_host)) { $score += 20; }

        // 5. Densité du mot-clé entre 0,5 % et 3 % (20 pts)
        if ($keyword) {
            $plain      = strtolower(wp_strip_all_tags($content));
            $word_count = max(1, str_word_count($plain));
            $kw_count   = substr_count($plain, $keyword);
            $density    = ($kw_count / $word_count) * 100;
            if ($density >= 0.5 && $density <= 3) { $score += 20; }
        }

        return $score;
    }
}

// ---------------------------------------------------------------------------
// AJAX — recalcul live depuis l'interface admin
// ---------------------------------------------------------------------------
if (!function_exists('mj_ajax_get_seo_score')) {
    function mj_ajax_get_seo_score() {
        check_ajax_referer('mj_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); }

        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) { wp_send_json_error('Invalid post ID'); }

        $score = mj_calculate_seo_score($post_id);
        update_post_meta($post_id, '_mj_seo_score', $score);

        $checklist = mj_seo_score_checklist($post_id);
        wp_send_json_success(['score' => $score, 'checklist' => $checklist]);
    }
    add_action('wp_ajax_mj_get_seo_score', 'mj_ajax_get_seo_score');
}

// ---------------------------------------------------------------------------
// Checklist détaillée pour l'affichage
// ---------------------------------------------------------------------------
if (!function_exists('mj_seo_score_checklist')) {
    function mj_seo_score_checklist($post_id) {
        $post    = get_post($post_id);
        if (!$post) { return []; }

        $keyword  = strtolower(get_post_meta($post_id, '_mj_mots_cles', true));
        $content  = $post->post_content;
        $title    = strtolower($post->post_title);
        $thumb_id = get_post_thumbnail_id($post_id);
        $host     = wp_parse_url(home_url(), PHP_URL_HOST);

        $has_kw_title  = $keyword && str_contains($title, $keyword);
        $has_meta      = (bool) get_post_meta($post_id, '_mj_meta_description', true);
        $has_alt       = $thumb_id && get_post_meta($thumb_id, '_wp_attachment_image_alt', true);
        $has_link      = $host && str_contains($content, $host);

        $density_ok = false;
        if ($keyword) {
            $plain      = strtolower(wp_strip_all_tags($content));
            $word_count = max(1, str_word_count($plain));
            $kw_count   = substr_count($plain, $keyword);
            $density    = ($kw_count / $word_count) * 100;
            $density_ok = ($density >= 0.5 && $density <= 3);
        }

        return [
            ['label' => 'Titre H1 contient le mot-clé',   'ok' => $has_kw_title],
            ['label' => 'Meta description renseignée',     'ok' => $has_meta],
            ['label' => 'Alt text sur l\'image à la une', 'ok' => (bool) $has_alt],
            ['label' => 'Maillage interne présent',        'ok' => $has_link],
            ['label' => 'Densité mot-clé (0,5–3 %)',       'ok' => $density_ok],
        ];
    }
}

// ---------------------------------------------------------------------------
// Widget score SEO affiché dans la bibliothèque pour les articles générés
// ---------------------------------------------------------------------------
if (!function_exists('mj_render_seo_score_widget')) {
    function mj_render_seo_score_widget($post_id) {
        $score     = (int) get_post_meta($post_id, '_mj_seo_score', true);
        $checklist = mj_seo_score_checklist($post_id);
        $color     = $score >= 80 ? '#1D9E75' : ($score >= 50 ? '#B8935A' : '#D85A30');
        $bar_pct   = $score;
        ?>
        <div class="mj-seo-score-widget" style="background:#fff;border:0.5px solid #E0D5C5;border-radius:8px;padding:14px 16px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <span style="font-size:12px;font-weight:600;color:#4A3018;">Score SEO</span>
            <span style="font-size:20px;font-weight:700;color:<?php echo $color; ?>"><?php echo $score; ?>/100</span>
          </div>
          <div style="height:6px;background:#EDE5D8;border-radius:3px;overflow:hidden;margin-bottom:12px;">
            <div style="height:6px;width:<?php echo $bar_pct; ?>%;background:<?php echo $color; ?>;border-radius:3px;transition:width .3s;"></div>
          </div>
          <ul style="margin:0;padding:0;list-style:none;">
            <?php foreach ($checklist as $item) : ?>
              <li style="font-size:11px;color:#6B4F30;padding:2px 0;display:flex;align-items:center;gap:6px;">
                <span style="color:<?php echo $item['ok'] ? '#1D9E75' : '#D85A30'; ?>;font-size:13px;">
                  <?php echo $item['ok'] ? '✓' : '✗'; ?>
                </span>
                <?php echo esc_html($item['label']); ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <button class="button button-small mj-refresh-seo-score" data-post-id="<?php echo intval($post_id); ?>"
                  style="margin-top:10px;font-size:11px;">↺ Recalculer</button>
        </div>
        <?php
    }
}

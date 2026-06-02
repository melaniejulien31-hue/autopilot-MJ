<?php
/**
 * Module Content Engine - Génération IA & Optimisations GEO
 */

if (!defined('ABSPATH')) { exit; }

if (!defined('MJ_CLUSTERS')) {
    define('MJ_CLUSTERS', [
        'regulation' => 'Régulation émotionnelle',
        'trauma'     => 'Trauma transgénérationnel',
        'burnout'    => 'Burnout parental',
        'corporel'   => 'Pratiques corporelles',
        'offres'     => 'Accompagnement (BOFU)',
    ]);
}

if (!function_exists('mj_geo_render_crawler_meta')) {
    function mj_geo_render_crawler_meta() {
        if (!is_single()) { return; }
        $post_id = get_the_ID();
        if (!$post_id) { return; }

        if (metadata_exists('post', $post_id, '_mj_tunnel')) {
            echo "\n<!-- Signals MJ Autopilot SEO v9 - IA & GEO Crawlers -->\n";
            echo '<meta name="ai-content-type" content="expert-article">' . "\n";
            echo '<link rel="author" href="https://www.melaniejulien.com/#melanie-julien">' . "\n";

            $geo_tag = get_post_meta($post_id, '_mj_geo_tag', true);
            if ($geo_tag) {
                echo '<meta name="geo-scope" content="' . esc_attr($geo_tag) . '">' . "\n";
            }
            $meta_desc = get_post_meta($post_id, '_mj_meta_description', true);
            if ($meta_desc) {
                echo '<meta name="description" content="' . esc_attr($meta_desc) . '">' . "\n";
            }
        }
    }
    add_action('wp_head', 'mj_geo_render_crawler_meta');
}

if (!function_exists('mj_geo_enrich_article_schema')) {
    function mj_geo_enrich_article_schema($data) {
        if (!is_array($data) || !is_single()) { return $data; }
        $post_id = get_the_ID();
        if (!$post_id) { return $data; }

        if (metadata_exists('post', $post_id, '_mj_tunnel')) {
            $data['speakable'] = [
                '@type'       => 'SpeakableSpecification',
                'cssSelector' => ['.mj-answer-box', '.mj-expert-quote'],
            ];
        }
        return $data;
    }
    add_filter('rank_math/json_ld', 'mj_geo_enrich_article_schema', 99, 1);
}

if (!function_exists('mj_content_engine_build_prompt')) {
    function mj_content_engine_build_prompt($subject) {
        $cluster_slug = isset($subject['cluster']) ? $subject['cluster'] : 'regulation';
        $cluster_name = MJ_CLUSTERS[$cluster_slug] ?? 'Régulation émotionnelle';
        $sujet        = isset($subject['sujet']) ? $subject['sujet'] : '';
        $motcle       = isset($subject['motcle']) ? $subject['motcle'] : '';
        $meta_hint    = !empty($subject['meta_desc']) ? "\nMeta description cible : {$subject['meta_desc']}" : '';
        $geo_hint     = !empty($subject['geo_tag']) && $subject['geo_tag'] === 'local'
            ? "\nOptimisation GEO locale : cite des lieux, villes ou références géographiques françaises précises."
            : '';

        return "Tu es un copywriter d'élite, expert en neurosciences appliquées et GEO pour melaniejulien.com.\n"
             . "Cluster thématique : [{$cluster_name}].\n"
             . "Sujet : {$sujet}\n"
             . "Mot-clé principal : {$motcle}"
             . $meta_hint
             . $geo_hint . "\n\n"
             . "Rédige un article complet en HTML (balises h2, h3, p, ul). "
             . "Inclus une section <div class=\"mj-answer-box\"> avec une réponse directe en début d'article pour le GEO. "
             . "Inclus une section <blockquote class=\"mj-expert-quote\"> avec une citation d'expert. "
             . "Longueur : 800 à 1200 mots. Ton : bienveillant, expert, sans jargon. "
             . "Retourne uniquement le HTML du contenu de l'article, sans balise <html> ni <body>.";
    }
}

// ---------------------------------------------------------------------------
// Pexels — récupération et import de l'image à la une
// ---------------------------------------------------------------------------
if (!function_exists('mj_pexels_fetch_and_set_thumbnail')) {
    function mj_pexels_fetch_and_set_thumbnail($post_id, $keyword) {
        $api_key = get_option('mj_pexels_api_key', '');
        if (empty($api_key)) { return false; }

        $response = wp_remote_get(
            add_query_arg(['query' => urlencode($keyword), 'per_page' => 1, 'orientation' => 'landscape'],
                'https://api.pexels.com/v1/search'),
            ['headers' => ['Authorization' => $api_key], 'timeout' => 15]
        );

        if (is_wp_error($response)) {
            mj_log('Pexels API error: ' . $response->get_error_message());
            return false;
        }

        $body  = json_decode(wp_remote_retrieve_body($response), true);
        $photo = $body['photos'][0] ?? null;
        if (!$photo) { return false; }

        $image_url = $photo['src']['large'] ?? $photo['src']['original'] ?? '';
        if (empty($image_url)) { return false; }

        // Télécharger et importer dans la médiathèque
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $tmp = download_url($image_url, 30);
        if (is_wp_error($tmp)) {
            mj_log('Pexels download error: ' . $tmp->get_error_message());
            return false;
        }

        $file_array = [
            'name'     => sanitize_file_name($keyword . '-pexels.jpg'),
            'tmp_name' => $tmp,
        ];

        $attach_id = media_handle_sideload($file_array, $post_id, $keyword);
        @unlink($tmp);

        if (is_wp_error($attach_id)) {
            mj_log('Pexels media sideload error: ' . $attach_id->get_error_message());
            return false;
        }

        // Alt text automatique = mot-clé
        update_post_meta($attach_id, '_wp_attachment_image_alt', sanitize_text_field($keyword));
        set_post_thumbnail($post_id, $attach_id);

        mj_log("Pexels : image définie (ID #{$attach_id}) pour l'article #{$post_id}");
        return $attach_id;
    }
}

// ---------------------------------------------------------------------------
// Génération et publication
// ---------------------------------------------------------------------------
if (!function_exists('mj_generate_and_publish')) {
    function mj_generate_and_publish($subject_id) {
        if (!current_user_can('manage_options')) { return false; }

        mj_log("MOTEUR IA : Lancement de la génération pour le sujet ID: " . intval($subject_id));

        if (!function_exists('mj_get_library_data')) { return false; }
        $library = mj_get_library_data();
        $subject = $library[$subject_id] ?? null;

        if (!$subject) {
            mj_log("MOTEUR IA ERREUR : Sujet #{$subject_id} introuvable.");
            return false;
        }

        $api_key = get_option('mj_anthropic_api_key', '');
        if (empty($api_key)) {
            mj_log("MOTEUR IA ERREUR : Clé API Anthropic non configurée.");
            return false;
        }

        $prompt   = mj_content_engine_build_prompt($subject);
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 90,
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'      => 'claude-opus-4-5',
                'max_tokens' => 2048,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            mj_log("MOTEUR IA ERREUR API : " . $response->get_error_message());
            return false;
        }

        $body         = wp_remote_retrieve_body($response);
        $decoded      = json_decode($body, true);
        $post_content = $decoded['content'][0]['text'] ?? '';

        if (empty($post_content)) {
            mj_log("MOTEUR IA ERREUR : Réponse API vide. Body: " . substr($body, 0, 300));
            return false;
        }

        if (function_exists('mj_linking_apply_internal_and_external')) {
            $post_content = mj_linking_apply_internal_and_external($post_content, $subject_id);
        }

        // --- Détermination du statut WP selon le statut de publication ---
        $pub_status   = $subject['status'] ?? 'manual-review';
        $wp_status    = 'pending';
        $post_date    = null;

        if ($pub_status === 'auto-publish') {
            $wp_status = 'publish';
        } elseif ($pub_status === 'draft') {
            $wp_status = 'draft';
        } elseif ($pub_status === 'scheduled' && !empty($subject['scheduled_date'])) {
            $wp_status = 'future';
            $post_date = gmdate('Y-m-d H:i:s', strtotime($subject['scheduled_date']));
        }

        // --- Slug personnalisé ---
        $post_slug = !empty($subject['slug']) ? sanitize_title($subject['slug']) : '';

        $post_args = [
            'post_title'   => wp_strip_all_tags($subject['sujet']),
            'post_content' => $post_content,
            'post_status'  => $wp_status,
            'post_type'    => 'post',
            'post_author'  => get_current_user_id() ?: 1,
        ];
        if ($post_slug)  { $post_args['post_name'] = $post_slug; }
        if ($post_date)  { $post_args['post_date'] = $post_date; $post_args['post_date_gmt'] = $post_date; }

        $post_id = wp_insert_post($post_args);

        if (!$post_id || is_wp_error($post_id)) {
            mj_log("MOTEUR IA ERREUR : wp_insert_post a échoué.");
            return false;
        }

        // --- Meta données ---
        update_post_meta($post_id, '_mj_tunnel',              sanitize_text_field($subject['tunnel']));
        update_post_meta($post_id, '_mj_cluster',             sanitize_text_field($subject['cluster']));
        update_post_meta($post_id, '_mj_mots_cles',           sanitize_text_field($subject['motcle']));
        update_post_meta($post_id, '_mj_pub_status',          $pub_status);
        update_post_meta($post_id, '_mj_geo_tag',             sanitize_text_field($subject['geo_tag'] ?? 'national'));
        update_post_meta($post_id, 'rank_math_focus_keyword', strtolower(sanitize_text_field($subject['motcle'])));

        // --- Meta description : utilise celle du sujet ou extrait du contenu ---
        $meta_desc = !empty($subject['meta_desc'])
            ? $subject['meta_desc']
            : wp_trim_words(wp_strip_all_tags($post_content), 25, '');
        $meta_desc = mb_substr($meta_desc, 0, 160);
        update_post_meta($post_id, '_mj_meta_description', sanitize_text_field($meta_desc));

        // --- Image Pexels avec alt text auto ---
        mj_pexels_fetch_and_set_thumbnail($post_id, $subject['motcle']);

        // --- Score SEO ---
        if (function_exists('mj_calculate_seo_score')) {
            $score = mj_calculate_seo_score($post_id);
            update_post_meta($post_id, '_mj_seo_score', $score);
        }

        mj_geo_generate_llms_txt();
        mj_log("MOTEUR IA SUCCÈS : Article créé sous l'ID #{$post_id} (statut: {$wp_status})");
        return $post_id;
    }
}

if (!function_exists('mj_geo_generate_llms_txt')) {
    function mj_geo_generate_llms_txt() {
        $file_path       = ABSPATH . 'llms.txt';
        $target_writable = file_exists($file_path) ? is_writable($file_path) : is_writable(ABSPATH);
        if (!$target_writable) {
            mj_log("Fichier llms.txt non accessible en écriture.");
            return;
        }

        $posts  = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => -1]);
        $output = "# Contexte LLMs / GEO - Cabinet Mélanie Julien\n\n";

        foreach (MJ_CLUSTERS as $slug => $label) {
            $output .= "## Cluster : {$label}\n\n";
            foreach ($posts as $post) {
                if (get_post_meta($post->ID, '_mj_cluster', true) === $slug) {
                    $output .= "- [{$post->post_title}](" . get_permalink($post->ID) . ")\n";
                }
            }
            $output .= "\n";
        }

        $file_handle = @fopen($file_path, 'w');
        if ($file_handle) {
            fwrite($file_handle, $output);
            fclose($file_handle);
        } else {
            mj_log("ERREUR : Impossible d'ouvrir llms.txt en écriture.");
        }
    }
}

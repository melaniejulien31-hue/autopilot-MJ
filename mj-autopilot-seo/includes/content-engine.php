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

        return "Tu es un copywriter d'élite, expert en neurosciences appliquées et GEO pour melaniejulien.com.\n"
             . "Cluster thématique : [{$cluster_name}].\n"
             . "Sujet : {$sujet}\n"
             . "Mot-clé principal : {$motcle}\n\n"
             . "Rédige un article complet en HTML (balises h2, h3, p, ul). "
             . "Inclus une section <div class=\"mj-answer-box\"> avec une réponse directe en début d'article pour le GEO. "
             . "Inclus une section <blockquote class=\"mj-expert-quote\"> avec une citation d'expert. "
             . "Longueur : 800 à 1200 mots. Ton : bienveillant, expert, sans jargon. "
             . "Retourne uniquement le HTML du contenu de l'article, sans balise <html> ni <body>.";
    }
}

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

        // Appel réel à l'API Anthropic
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
            mj_log("MOTEUR IA ERREUR : Réponse API vide ou malformée. Body: " . substr($body, 0, 300));
            return false;
        }

        // Application du maillage
        if (function_exists('mj_linking_apply_internal_and_external')) {
            $post_content = mj_linking_apply_internal_and_external($post_content, $subject_id);
        }

        $post_id = wp_insert_post([
            'post_title'   => wp_strip_all_tags($subject['sujet']),
            'post_content' => $post_content,
            'post_status'  => 'pending',
            'post_type'    => 'post',
            'post_author'  => get_current_user_id() ?: 1,
        ]);

        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, '_mj_tunnel',           sanitize_text_field($subject['tunnel']));
            update_post_meta($post_id, '_mj_cluster',          sanitize_text_field($subject['cluster']));
            update_post_meta($post_id, '_mj_mots_cles',        sanitize_text_field($subject['motcle']));
            update_post_meta($post_id, 'rank_math_focus_keyword', strtolower(sanitize_text_field($subject['motcle'])));

            mj_geo_generate_llms_txt();
            mj_log("MOTEUR IA SUCCÈS : Article créé sous l'ID #{$post_id}");
            return $post_id;
        }

        return false;
    }
}

if (!function_exists('mj_geo_generate_llms_txt')) {
    function mj_geo_generate_llms_txt() {
        $file_path = ABSPATH . 'llms.txt';

        // CORRIGÉ : logique is_writable corrigée — on vérifie le répertoire si le fichier n'existe pas encore
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

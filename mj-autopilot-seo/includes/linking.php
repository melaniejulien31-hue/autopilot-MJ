<?php
/**
 * Module 1 - Maillage Sémantique Avancé & Remplacement Unicode Safe
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('mj_linking_apply_internal_and_external')) {
    function mj_linking_apply_internal_and_external($content, $subject_id) {
        if (empty($content)) { return $content; }
        
        $content = mj_linking_inject_bofu_offers($content);
        $content = mj_linking_inject_internal_posts($content);
        $content = mj_linking_inject_external_sources($content, $subject_id);
        
        return $content;
    }
}

if (!function_exists('mj_linking_inject_bofu_offers')) {
    function mj_linking_inject_bofu_offers($content) {
        $offers = [
            'ata' => ['url' => 'https://www.melaniejulien.com/programme-ata/', 'keyword' => 'programme ata'],
            'seances' => ['url' => 'https://www.melaniejulien.com/seances/', 'keyword' => 'séances individuelles']
        ];

        foreach ($offers as $offer) {
            // Expression régulière Unicode préservant les limites de mots accentués en Français
            $regex = '/(?<!<[^>])(?<![\w\x7f-\xff])(' . preg_quote($offer['keyword'], '/') . ')(?![\w\x7f-\xff])(?![^<]*>)/iu';
            
            if (preg_match($regex, $content)) {
                $link = '<a href="' . esc_url($offer['url']) . '" title="' . esc_attr($offer['keyword']) . '">' . esc_html($offer['keyword']) . '</a>';
                // Remplacement strict d'une seule occurrence pour éviter la sur-optimisation
                $content = preg_replace($regex, $link, $content, 1);
                break;
            }
        }
        return $content;
    }
}

if (!function_exists('mj_linking_inject_internal_posts')) {
    function mj_linking_inject_internal_posts($content) {
        $query = new WP_Query([
            'post_type'      => 'post', 
            'post_status'    => 'publish', 
            'posts_per_page' => 5,
            'fields'         => 'ids' // Optimisation de la mémoire vive du serveur
        ]);
        
        if (!$query->have_posts()) { return $content; }
        
        $links_meta = "";
        foreach ($query->posts as $post_id) {
            $permalink = get_permalink($post_id);
            if ($permalink) {
                $links_meta .= "\n<!-- Lien d'autorité interne suggéré : " . esc_url($permalink) . " -->";
            }
        }
        wp_reset_postdata();
        return $content . $links_meta;
    }
}

if (!function_exists('mj_linking_inject_external_sources')) {
    function mj_linking_inject_external_sources($content, $subject_id) {
        if (!function_exists('mj_linking_get_subject_references')) { return $content; }
        
        $subject_refs = mj_linking_get_subject_references($subject_id);
        if (!empty($subject_refs)) {
            $sources_html = "\n\n<div class=\"mj-sources\" style=\"margin-top:20px; border-top:1px solid #ddd; padding-top:10px;\"><p><strong>Sources et études cliniques :</strong></p><ul>";
            foreach (array_slice($subject_refs, 0, 2) as $ref) {
                $host = parse_url($ref, PHP_URL_HOST) ?: 'source';
                $sources_html .= "<li><a href=\"" . esc_url($ref) . "\" target=\"_blank\" rel=\"noopener noreferrer\">" . esc_html($host) . "</a></li>";
            }
            $sources_html .= "</ul></div>";
            $content .= $sources_html;
        }
        return $content;
    }
}

if (!function_exists('mj_linking_get_subject_references')) {
    function mj_linking_get_subject_references($subject_id) {
        if (function_exists('mj_get_library_data')) {
            $library = mj_get_library_data();
            if (isset($library[$subject_id]['refs']) && is_array($library[$subject_id]['refs'])) {
                return $library[$subject_id]['refs'];
            }
        }
        return ['https://www.ncbi.nlm.nih.gov/pubmed/', 'https://www.inserm.fr/'];
    }
}
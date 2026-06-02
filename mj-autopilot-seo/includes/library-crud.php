<?php
/**
 * Module Library CRUD - Gestionnaire de la Bibliothèque de Contenus
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('mj_get_library_data')) {
    function mj_get_library_data() {
        return [
            1 => [
                'sujet'   => 'Comment calmer la culpabilité maternelle quand on crie sur ses enfants ?',
                'motcle'  => 'culpabilité maternelle',
                'tunnel'  => 'TOFU',
                'cluster' => 'regulation',
                'refs'    => ['https://www.ncbi.nlm.nih.gov/pubmed/', 'https://www.cairn.info/'],
            ],
            2 => [
                'sujet'   => 'Le rôle secret du système nerveux et du nerf vague dans l\'épuisement parental',
                'motcle'  => 'nerf vague épuisement',
                'tunnel'  => 'MOFU',
                'cluster' => 'corporel',
                'refs'    => ['https://www.inserm.fr/', 'https://www.ncbi.nlm.nih.gov/pubmed/'],
            ],
        ];
    }
}

if (!function_exists('mj_render_library_crud_page')) {
    function mj_render_library_crud_page() {
        if (!current_user_can('manage_options')) { return; }

        // Traitement d'une action de génération
        if (isset($_GET['action']) && $_GET['action'] === 'generate' && isset($_GET['id'])) {
            check_admin_referer('mj_generate_article_nonce', '_wpnonce');

            $id_to_gen = intval($_GET['id']);
            if (function_exists('mj_generate_and_publish')) {
                $created_id = mj_generate_and_publish($id_to_gen);
                if ($created_id) {
                    echo '<div class="notice notice-success is-dismissible"><p><strong>Succès :</strong> Article GEO créé en statut "En attente" (ID #' . intval($created_id) . ').</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p><strong>Erreur :</strong> La génération a échoué. Vérifiez que la clé API Anthropic est configurée et consultez le fichier de log.</p></div>';
                }
            }
        }

        $library = mj_get_library_data();
        ?>
        <div class="wrap" style="margin-top:20px;">
            <h1 style="font-weight:700; margin-bottom:20px;">Bibliothèque Éditoriale Optimisée GEO</h1>
            <div style="background:#fff; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,0.05); padding:10px;">
                <table class="wp-list-table widefat fixed striped" style="border:none;">
                    <thead>
                        <tr>
                            <th style="font-weight:700; width:60px; padding:15px 10px;">ID</th>
                            <th style="font-weight:700; padding:15px 10px;">Sujet Planifié</th>
                            <th style="font-weight:700; padding:15px 10px;">Mot-clé Principal</th>
                            <th style="font-weight:700; padding:15px 10px;">Cluster</th>
                            <th style="font-weight:700; width:160px; padding:15px 10px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($library as $id => $data) :
                            $generate_url = wp_nonce_url(
                                admin_url('admin.php?page=mj-autopilot-library&action=generate&id=' . $id),
                                'mj_generate_article_nonce'
                            );
                            $cluster_label = MJ_CLUSTERS[$data['cluster']] ?? $data['cluster'];
                        ?>
                            <tr>
                                <td style="padding:15px 10px;"><code>#<?php echo esc_html($id); ?></code></td>
                                <td style="padding:15px 10px;"><strong><?php echo esc_html($data['sujet']); ?></strong></td>
                                <td style="padding:15px 10px;"><code><?php echo esc_html($data['motcle']); ?></code></td>
                                <td style="padding:15px 10px;"><span style="background:#f0efe8; padding:3px 8px; border-radius:4px; font-size:12px;"><?php echo esc_html($cluster_label); ?></span></td>
                                <td style="padding:12px 10px;">
                                    <a href="<?php echo esc_url($generate_url); ?>"
                                       class="button button-primary"
                                       onclick="return confirm('Lancer la génération IA pour ce sujet ?');"
                                       style="border-radius:4px;">
                                       Rédiger via IA
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}

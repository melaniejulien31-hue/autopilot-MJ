<?php
/**
 * Module Library CRUD - Gestionnaire de la Bibliothèque de Contenus
 */

if (!defined('ABSPATH')) { exit; }

// ---------------------------------------------------------------------------
// Statuts de publication disponibles
// ---------------------------------------------------------------------------
if (!defined('MJ_PUB_STATUSES')) {
    define('MJ_PUB_STATUSES', [
        'draft'         => 'Brouillon',
        'manual-review' => 'Relecture',
        'scheduled'     => 'Planifié',
        'auto-publish'  => 'Auto-publier',
    ]);
}

// ---------------------------------------------------------------------------
// Lecture / écriture de la bibliothèque (options WP)
// ---------------------------------------------------------------------------
if (!function_exists('mj_get_library_data')) {
    function mj_get_library_data() {
        $data = get_option('mj_library_subjects', null);
        if ($data === null) {
            $defaults = [
                1 => [
                    'sujet'          => 'Comment calmer la culpabilité maternelle quand on crie sur ses enfants ?',
                    'motcle'         => 'culpabilité maternelle',
                    'tunnel'         => 'TOFU',
                    'cluster'        => 'regulation',
                    'refs'           => ['https://www.ncbi.nlm.nih.gov/pubmed/', 'https://www.cairn.info/'],
                    'status'         => 'manual-review',
                    'scheduled_date' => '',
                    'meta_desc'      => '',
                    'slug'           => '',
                    'geo_tag'        => 'national',
                ],
                2 => [
                    'sujet'          => 'Le rôle secret du système nerveux et du nerf vague dans l\'épuisement parental',
                    'motcle'         => 'nerf vague épuisement',
                    'tunnel'         => 'MOFU',
                    'cluster'        => 'corporel',
                    'refs'           => ['https://www.inserm.fr/', 'https://www.ncbi.nlm.nih.gov/pubmed/'],
                    'status'         => 'manual-review',
                    'scheduled_date' => '',
                    'meta_desc'      => '',
                    'slug'           => '',
                    'geo_tag'        => 'national',
                ],
            ];
            update_option('mj_library_subjects', $defaults);
            update_option('mj_library_next_id', 3);
            return $defaults;
        }
        return (array) $data;
    }
}

if (!function_exists('mj_save_library_data')) {
    function mj_save_library_data(array $data) {
        update_option('mj_library_subjects', $data);
    }
}

if (!function_exists('mj_library_next_id')) {
    function mj_library_next_id() {
        $id = (int) get_option('mj_library_next_id', 1);
        update_option('mj_library_next_id', $id + 1);
        return $id;
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
if (!function_exists('mj_pub_status_badge')) {
    function mj_pub_status_badge($status) {
        $map = [
            'draft'         => ['label' => 'Brouillon',   'class' => 'mj-badge-status mj-status-draft'],
            'manual-review' => ['label' => 'Relecture',   'class' => 'mj-badge-status mj-status-review'],
            'scheduled'     => ['label' => 'Planifié',    'class' => 'mj-badge-status mj-status-scheduled'],
            'auto-publish'  => ['label' => 'Auto-publier','class' => 'mj-badge-status mj-status-auto'],
        ];
        $info = $map[$status] ?? ['label' => esc_html($status), 'class' => 'mj-badge-status'];
        return '<span class="' . esc_attr($info['class']) . '">' . esc_html($info['label']) . '</span>';
    }
}

if (!function_exists('mj_seo_score_mini')) {
    function mj_seo_score_mini($subject) {
        $score = 0;
        if (!empty($subject['motcle']))      $score += 20;
        if (!empty($subject['meta_desc']))   $score += 20;
        if (!empty($subject['slug']))        $score += 20;
        if (!empty($subject['geo_tag']))     $score += 20;
        if (!empty($subject['refs']))        $score += 20;
        $color = $score >= 80 ? '#1D9E75' : ($score >= 50 ? '#B8935A' : '#D85A30');
        return '<span style="font-weight:600;color:' . $color . '">' . $score . '/100</span>';
    }
}

// ---------------------------------------------------------------------------
// Page principale
// ---------------------------------------------------------------------------
if (!function_exists('mj_render_library_crud_page')) {
    function mj_render_library_crud_page() {
        if (!current_user_can('manage_options')) { return; }

        // --- Génération ---
        if (isset($_GET['action']) && $_GET['action'] === 'generate' && isset($_GET['id'])) {
            check_admin_referer('mj_generate_article_nonce', '_wpnonce');
            $id_to_gen = intval($_GET['id']);
            if (function_exists('mj_generate_and_publish')) {
                $created_id = mj_generate_and_publish($id_to_gen);
                if ($created_id) {
                    echo '<div class="notice notice-success is-dismissible"><p><strong>Succès :</strong> Article créé (ID #' . intval($created_id) . ').&nbsp;<a href="' . esc_url(get_edit_post_link($created_id)) . '">Voir l\'article</a></p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p><strong>Erreur :</strong> La génération a échoué. Vérifiez la clé API Anthropic et le fichier de log.</p></div>';
                }
            }
        }

        // --- Suppression ---
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            check_admin_referer('mj_delete_subject_nonce', '_wpnonce');
            $library = mj_get_library_data();
            unset($library[intval($_GET['id'])]);
            mj_save_library_data($library);
            echo '<div class="notice notice-success is-dismissible"><p>Sujet supprimé.</p></div>';
        }

        // --- Ajout / modification ---
        if (isset($_POST['mj_save_subject'])) {
            check_admin_referer('mj_save_subject_nonce', 'mj_subject_nonce');
            $library = mj_get_library_data();
            $edit_id = isset($_POST['subject_id']) && $_POST['subject_id'] !== '' ? intval($_POST['subject_id']) : null;
            $id      = $edit_id ?? mj_library_next_id();

            $refs_raw = sanitize_textarea_field($_POST['refs'] ?? '');
            $refs     = array_filter(array_map('trim', explode("\n", $refs_raw)));

            $library[$id] = [
                'sujet'          => sanitize_text_field($_POST['sujet'] ?? ''),
                'motcle'         => sanitize_text_field($_POST['motcle'] ?? ''),
                'tunnel'         => sanitize_text_field($_POST['tunnel'] ?? 'TOFU'),
                'cluster'        => sanitize_text_field($_POST['cluster'] ?? 'regulation'),
                'refs'           => array_values($refs),
                'status'         => sanitize_text_field($_POST['pub_status'] ?? 'manual-review'),
                'scheduled_date' => sanitize_text_field($_POST['scheduled_date'] ?? ''),
                'meta_desc'      => sanitize_textarea_field($_POST['meta_desc'] ?? ''),
                'slug'           => sanitize_title($_POST['slug'] ?? ''),
                'geo_tag'        => sanitize_text_field($_POST['geo_tag'] ?? 'national'),
            ];
            mj_save_library_data($library);
            $verb = $edit_id ? 'modifié' : 'ajouté';
            echo '<div class="notice notice-success is-dismissible"><p>Sujet ' . $verb . ' avec succès.</p></div>';
        }

        $library  = mj_get_library_data();
        $edit_id  = isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit' ? intval($_GET['id']) : null;
        $edit_sub = $edit_id ? ($library[$edit_id] ?? null) : null;

        // --- Filtre tunnel ---
        $filter_tunnel = isset($_GET['tunnel']) ? sanitize_text_field($_GET['tunnel']) : '';
        $filtered = $filter_tunnel ? array_filter($library, fn($s) => $s['tunnel'] === $filter_tunnel) : $library;
        ?>
        <div class="wrap" style="margin-top:20px; max-width:1100px;">

          <!-- En-tête -->
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <h1 style="font-weight:700;margin:0;">Bibliothèque Éditoriale
              <span style="font-size:11px;background:#2A1F14;color:#fff;padding:2px 8px;border-radius:4px;vertical-align:middle;margin-left:8px;">V2</span>
            </h1>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'mj-autopilot-library', 'action' => 'add'], admin_url('admin.php'))); ?>"
               class="button button-primary" style="background:#B8935A;border-color:#A37F49;">+ Nouveau sujet</a>
          </div>

          <!-- Filtres tunnel -->
          <div style="display:flex;gap:8px;margin-bottom:14px;">
            <?php
            $tunnels = ['' => 'Tous', 'TOFU' => 'TOFU', 'MOFU' => 'MOFU', 'BOFU' => 'BOFU'];
            foreach ($tunnels as $val => $label) :
                $active = ($filter_tunnel === $val) ? 'background:#2A1F14;color:#fff;' : '';
                $url = admin_url('admin.php?page=mj-autopilot-library' . ($val ? '&tunnel=' . $val : ''));
            ?>
            <a href="<?php echo esc_url($url); ?>"
               style="font-size:11px;padding:4px 12px;border-radius:20px;border:1px solid #C4A870;text-decoration:none;color:#7A4E20;<?php echo $active; ?>">
                <?php echo esc_html($label); ?>
            </a>
            <?php endforeach; ?>
          </div>

          <!-- Tableau -->
          <div style="background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.05);padding:10px;">
            <table class="wp-list-table widefat fixed striped" style="border:none;">
              <thead>
                <tr>
                  <th style="width:50px;padding:12px 10px;font-weight:700;">ID</th>
                  <th style="padding:12px 10px;font-weight:700;">Sujet</th>
                  <th style="padding:12px 10px;font-weight:700;">Mot-clé</th>
                  <th style="width:70px;padding:12px 10px;font-weight:700;">Tunnel</th>
                  <th style="width:100px;padding:12px 10px;font-weight:700;">Statut</th>
                  <th style="width:80px;padding:12px 10px;font-weight:700;">Score SEO</th>
                  <th style="width:200px;padding:12px 10px;font-weight:700;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($filtered)) : ?>
                  <tr><td colspan="7" style="padding:20px;text-align:center;color:#9A7850;">Aucun sujet trouvé.</td></tr>
                <?php endif; ?>
                <?php foreach ($filtered as $id => $data) :
                    $gen_url = wp_nonce_url(
                        admin_url('admin.php?page=mj-autopilot-library&action=generate&id=' . $id),
                        'mj_generate_article_nonce'
                    );
                    $edit_url = admin_url('admin.php?page=mj-autopilot-library&action=edit&id=' . $id);
                    $del_url  = wp_nonce_url(
                        admin_url('admin.php?page=mj-autopilot-library&action=delete&id=' . $id),
                        'mj_delete_subject_nonce'
                    );
                    $cluster_label = defined('MJ_CLUSTERS') ? (MJ_CLUSTERS[$data['cluster']] ?? $data['cluster']) : $data['cluster'];
                    $tunnel_class  = 'tag-' . strtolower($data['tunnel']);
                ?>
                <tr>
                  <td style="padding:12px 10px;"><code>#<?php echo esc_html($id); ?></code></td>
                  <td style="padding:12px 10px;">
                    <strong><?php echo esc_html($data['sujet']); ?></strong>
                    <?php if (!empty($data['meta_desc'])) : ?>
                      <br><span style="font-size:11px;color:#9A7850;font-style:italic;"><?php echo esc_html(wp_trim_words($data['meta_desc'], 12)); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($data['slug'])) : ?>
                      <br><code style="font-size:10px;color:#B8935A;">/<?php echo esc_html($data['slug']); ?></code>
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px 10px;"><code><?php echo esc_html($data['motcle']); ?></code></td>
                  <td style="padding:12px 10px;"><span class="<?php echo esc_attr($tunnel_class); ?>"><?php echo esc_html($data['tunnel']); ?></span></td>
                  <td style="padding:12px 10px;"><?php echo mj_pub_status_badge($data['status']); ?></td>
                  <td style="padding:12px 10px;"><?php echo mj_seo_score_mini($data); ?></td>
                  <td style="padding:10px;">
                    <a href="<?php echo esc_url($gen_url); ?>" class="button button-primary button-small"
                       onclick="return confirm('Lancer la génération IA ?');"
                       style="background:#B8935A;border-color:#A37F49;font-size:11px;">▶ Générer</a>
                    <a href="<?php echo esc_url($edit_url); ?>" class="button button-small" style="font-size:11px;">✎ Éditer</a>
                    <a href="<?php echo esc_url($del_url); ?>" class="button button-small" style="font-size:11px;color:#D85A30;"
                       onclick="return confirm('Supprimer ce sujet ?');">✕</a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Formulaire ajout / édition -->
          <div style="background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.05);padding:24px;margin-top:20px;">
            <h3 style="margin-top:0;font-size:14px;color:#2A1F14;">
              <?php echo $edit_sub ? '✎ Modifier le sujet #' . intval($edit_id) : '+ Nouveau sujet'; ?>
            </h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-library')); ?>">
              <?php wp_nonce_field('mj_save_subject_nonce', 'mj_subject_nonce'); ?>
              <input type="hidden" name="subject_id" value="<?php echo $edit_sub ? intval($edit_id) : ''; ?>">

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

                <div>
                  <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Sujet *</label>
                  <input type="text" name="sujet" required value="<?php echo esc_attr($edit_sub['sujet'] ?? ''); ?>"
                         class="widefat" placeholder="Titre du sujet…">
                </div>

                <div>
                  <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Mot-clé principal *</label>
                  <input type="text" name="motcle" required value="<?php echo esc_attr($edit_sub['motcle'] ?? ''); ?>"
                         class="widefat" placeholder="mot-clé cible">
                </div>

                <div>
                  <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Slug (URL)</label>
                  <input type="text" name="slug" value="<?php echo esc_attr($edit_sub['slug'] ?? ''); ?>"
                         class="widefat" placeholder="mon-article-seo">
                  <p class="description" style="font-size:11px;">Laissez vide pour génération automatique.</p>
                </div>

                <div>
                  <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Meta description</label>
                  <textarea name="meta_desc" class="widefat" rows="2" maxlength="160"
                            placeholder="Description SEO (max 160 car.)…"><?php echo esc_textarea($edit_sub['meta_desc'] ?? ''); ?></textarea>
                </div>

                <div>
                  <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Étape de tunnel</label>
                  <select name="tunnel" class="widefat">
                    <?php foreach (['TOFU', 'MOFU', 'BOFU'] as $t) : ?>
                      <option value="<?php echo $t; ?>" <?php selected($edit_sub['tunnel'] ?? 'TOFU', $t); ?>><?php echo $t; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div>
                  <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Cluster thématique</label>
                  <select name="cluster" class="widefat">
                    <?php
                    $clusters = defined('MJ_CLUSTERS') ? MJ_CLUSTERS : [];
                    foreach ($clusters as $slug => $label) :
                    ?>
                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($edit_sub['cluster'] ?? 'regulation', $slug); ?>>
                      <?php echo esc_html($label); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div>
                  <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Statut de publication</label>
                  <select name="pub_status" class="widefat" id="mj-pub-status-select">
                    <?php foreach (MJ_PUB_STATUSES as $val => $label) : ?>
                      <option value="<?php echo esc_attr($val); ?>" <?php selected($edit_sub['status'] ?? 'manual-review', $val); ?>>
                        <?php echo esc_html($label); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div id="mj-scheduled-date-wrap" style="display:none;">
                  <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Date de publication planifiée</label>
                  <input type="datetime-local" name="scheduled_date" class="widefat"
                         value="<?php echo esc_attr($edit_sub['scheduled_date'] ?? ''); ?>">
                </div>

                <div>
                  <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Tag GEO</label>
                  <select name="geo_tag" class="widefat">
                    <?php foreach (['local' => 'Local', 'national' => 'National', 'international' => 'International'] as $val => $label) : ?>
                      <option value="<?php echo esc_attr($val); ?>" <?php selected($edit_sub['geo_tag'] ?? 'national', $val); ?>>
                        <?php echo esc_html($label); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div>
                  <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Sources (une URL par ligne)</label>
                  <textarea name="refs" class="widefat" rows="3"
                            placeholder="https://www.ncbi.nlm.nih.gov/pubmed/&#10;https://www.inserm.fr/"><?php
                    echo esc_textarea(implode("\n", $edit_sub['refs'] ?? []));
                  ?></textarea>
                </div>

              </div>

              <p style="margin-top:16px;">
                <input type="submit" name="mj_save_subject" class="button button-primary button-large"
                       value="<?php echo $edit_sub ? 'Enregistrer les modifications' : 'Ajouter le sujet'; ?>"
                       style="background:#B8935A;border-color:#A37F49;">
                <?php if ($edit_sub) : ?>
                  <a href="<?php echo esc_url(admin_url('admin.php?page=mj-autopilot-library')); ?>"
                     class="button button-large" style="margin-left:8px;">Annuler</a>
                <?php endif; ?>
              </p>
            </form>
          </div>

        </div>
        <?php
    }
}

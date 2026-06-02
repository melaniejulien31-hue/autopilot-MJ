<?php
/**
 * Module 5.2 - Analyseur de Mots-Clés et Quick Wins Google
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('mj_render_opportunities_page')) {
    function mj_render_opportunities_page() {
        if (!current_user_can('manage_options')) { return; }

        $fake_opportunities = [
            [
                'query'       => 'pourquoi je crie sur mon fils',
                'impressions' => 1420,
                'clicks'      => 42,
                'position'    => 8.4,
                'win_type'    => 'Optimiser Maillage Interne'
            ]
        ];
        ?>
        <div class="wrap" style="margin-top:20px;">
            <h1 style="font-weight:700; margin-bottom:20px;">Opportunités SEO Détectées (GSC Quick Wins)</h1>
            <div style="background:#fff; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,0.05); padding:10px;">
                <table class="wp-list-table widefat fixed striped" style="border:none;">
                    <thead>
                        <tr>
                            <th style="font-weight:700; padding:15px 10px;">Requête de Recherche</th>
                            <th style="font-weight:700; padding:15px 10px;">Impressions</th>
                            <th style="font-weight:700; padding:15px 10px;">Clics</th>
                            <th style="font-weight:700; padding:15px 10px;">Position Moyenne</th>
                            <th style="font-weight:700; padding:15px 10px;">Action Stratégique</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fake_opportunities as $opp) : ?>
                            <tr>
                                <td style="padding:15px 10px;"><strong>🔎 <?php echo esc_html($opp['query']); ?></strong></td>
                                <td style="padding:15px 10px;"><?php echo number_format($opp['impressions'], 0, ',', ' '); ?></td>
                                <td style="padding:15px 10px;"><?php echo intval($opp['clicks']); ?></td>
                                <td style="padding:15px 10px;"><span style="background:#f0a400; color:#fff; padding:3px 8px; border-radius:4px; font-weight:600; font-size:12px;"><?php echo number_format($opp['position'], 1); ?></span></td>
                                <td style="padding:15px 10px;"><span style="background:#46b450; color:#fff; padding:3px 8px; border-radius:4px; font-weight:600; font-size:12px;"><?php echo esc_html($opp['win_type']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
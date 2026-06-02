/* MJ Autopilot SEO — Admin JS v9.2.0 */
(function ($) {
    'use strict';

    // ── Affichage conditionnel du champ date planifiée ──
    function toggleScheduledDate() {
        var val = $('#mj-pub-status-select').val();
        if (val === 'scheduled') {
            $('#mj-scheduled-date-wrap').show();
        } else {
            $('#mj-scheduled-date-wrap').hide();
        }
    }

    $(document).ready(function () {
        // Init état champ date
        if ($('#mj-pub-status-select').length) {
            toggleScheduledDate();
            $('#mj-pub-status-select').on('change', toggleScheduledDate);
        }

        // ── Recalcul live du score SEO ──
        $(document).on('click', '.mj-refresh-seo-score', function (e) {
            e.preventDefault();
            var $btn    = $(this);
            var postId  = $btn.data('post-id');
            var $widget = $btn.closest('.mj-seo-score-widget');

            $btn.text('…').prop('disabled', true);

            $.post(mjAutopilot.ajaxUrl, {
                action:  'mj_get_seo_score',
                nonce:   mjAutopilot.nonce,
                post_id: postId
            }, function (res) {
                if (res.success) {
                    var score = res.data.score;
                    var color = score >= 80 ? '#1D9E75' : (score >= 50 ? '#B8935A' : '#D85A30');

                    $widget.find('.mj-score-value').text(score + '/100').css('color', color);
                    $widget.find('.mj-score-bar-inner').css('width', score + '%').css('background', color);

                    // Mettre à jour la checklist
                    var $list = $widget.find('ul');
                    $list.empty();
                    $.each(res.data.checklist, function (i, item) {
                        var icon  = item.ok ? '✓' : '✗';
                        var icolor = item.ok ? '#1D9E75' : '#D85A30';
                        $list.append(
                            '<li style="font-size:11px;color:#6B4F30;padding:2px 0;display:flex;align-items:center;gap:6px;">' +
                            '<span style="color:' + icolor + ';font-size:13px;">' + icon + '</span>' +
                            item.label + '</li>'
                        );
                    });
                }
                $btn.text('↺ Recalculer').prop('disabled', false);
            });
        });
    });

})(jQuery);

/* Restaurant Reservierung – Admin JS */
(function($) {
    // Anmerkungen als Tooltip beim Hover
    $(document).on('mouseenter', '.rr-note-icon', function() {
        var text = $(this).attr('title');
        if (!text) return;
        var tip = $('<div class="rr-tooltip">').text(text).css({
            position: 'absolute',
            background: '#1a1a2e',
            color: '#fff',
            padding: '6px 10px',
            borderRadius: '4px',
            fontSize: '12px',
            maxWidth: '240px',
            zIndex: 9999,
            lineHeight: 1.4
        });
        $(this).after(tip);
        tip.css({ top: $(this).position().top + 20, left: $(this).position().left });
    }).on('mouseleave', '.rr-note-icon', function() {
        $(this).siblings('.rr-tooltip').remove();
    });
})(jQuery);

// Von/Bis: Bis-Feld auto-befüllen
$(document).on('change', '#von', function() {
    var $bis = $('#bis');
    if (!$bis.val() || $bis.val() < $(this).val()) {
        $bis.val($(this).val());
    }
    $bis.attr('min', $(this).val());
});

/* Admin helpers for SATORI Audit */
(function ($) {
    $(document).ready(function () {
        $('.satori-audit-table').on('focus', '.satori-audit-blank input, .satori-audit-blank textarea', function () {
            var $row = $(this).closest('tr');
            if (!$row.data('expanded')) {
                var $clone = $row.clone();
                $clone.find('input, textarea').val('');
                $row.removeClass('satori-audit-blank').data('expanded', true);
                $row.after($clone);
            }
        });
    });
})(jQuery);

jQuery(function ($) {
    $(document).on('click', '.mld-locahl-banner .locahl-bento-banner__close', function () {
        if (typeof mldLocahlBanner === 'undefined') {
            return;
        }

        var $banner = $(this).closest('.locahl-promo-banner');

        $.post(mldLocahlBanner.ajax_url, {
            action: mldLocahlBanner.action,
            nonce: mldLocahlBanner.nonce,
        });

        $banner.fadeOut(200, function () {
            $(this).remove();
        });
    });
});

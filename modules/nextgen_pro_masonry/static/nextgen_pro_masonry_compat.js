(function($) {
    $(document).ready(function() {
        $('.ngg-pro-masonry-wrapper').each(function () {
            var $parent = $(this);
            var $self = $parent.find('.ngg-pro-masonry');
            var $spinner = $parent.find('.ngg-pro-masonry-spinner');

            $self.waitForImages(function() {
                $spinner.hide();
                $self.show().masonry({
                    itemSelector: '.ngg-pro-masonry-item',
                    gutterWidth: parseInt(nextgen_pro_masonry_settings.gutterWidth),
                    columnWidth: parseInt(nextgen_pro_masonry_settings.columnWidth)
                });
                $(window).trigger('refreshed');
            });

            $(window).on('resize orientationchange', function (event) {
                setTimeout(function () {
                    $self.masonry();
                }, 500);
            });
        });
    });
})(jQuery);

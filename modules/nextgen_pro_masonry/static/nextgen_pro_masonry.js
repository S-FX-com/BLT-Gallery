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
                    gutter: '.ngg-pro-masonry-gutter',
                    columnWidth: '.ngg-pro-masonry-sizer',
                    fitWidth: nextgen_pro_masonry_settings.center_gallery === '0' ? false : true
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

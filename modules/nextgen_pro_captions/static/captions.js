(function($) {

    var user_is_using_ie = false;
    if (navigator.appName.indexOf("Internet Explorer") !== -1) {
        if (navigator.appVersion.indexOf("MSIE 8") !== -1) {
            // This does not work with IE8
            return;
        }
        if (navigator.appVersion.indexOf("MSIE 1") !== -1) {
            user_is_using_ie = 10;
        }
    }

    var nggc_get_gallery = function(gallery_id) {
        if ('undefined' == typeof window.galleries) {
            return null;
        }
        var retval = null;

        $.each(window.galleries, function(index, gallery) {
            if (typeof gallery !== 'object') {
                return false;
            }
            if (gallery.ID === gallery_id) {
                retval = gallery;
            }
        });

        return retval;
    };

    var nggc_get_setting = function(gallery, name, def) {
        var tmp = def;
        if (typeof gallery.display_settings[name] != 'undefined' && gallery.display_settings[name] != '') {
            tmp = gallery.display_settings[name];
        } else {
            tmp = def;
        }
        if (tmp == 1)       tmp = true;
        if (tmp == 0)       tmp = false;
        if (tmp == '1')     tmp = true;
        if (tmp == '0')     tmp = false;
        if (tmp == 'false') tmp = false;
        if (tmp == 'true')  tmp = true;

        return tmp;
    };

    var nggc_allowed_tags = [
        "EM",
        "STRONG",
        'B',
        'DEL',
        'I',
        'INS',
        'MARK',
        'SMALL',
        'STRIKE',
        'SUB',
        'SUP',
        'TT',
        'U'
    ];

    var nggc_sanitize = function(string) {
        var element = document.createElement('div');
        element.innerHTML = string;
        nggc_real_sanitize(element);
        return element.innerHTML;
    };

    var nggc_real_sanitize = function(element) {
        var allowed_tags = Array.prototype.slice.apply(element.getElementsByTagName("*"), [0]);
        for (var i = 0; i < allowed_tags.length; i++) {
            if (nggc_allowed_tags.indexOf(allowed_tags[i].nodeName) == -1) {
                nggc_sanitize_replace(allowed_tags[i]);
            }
        }
    };

    var nggc_sanitize_replace = function(element) {
        var last = element;
        for (var i = element.childNodes.length - 1; i >= 0; i--) {
            var tmp = element.removeChild(element.childNodes[i]);
            element.parentNode.insertBefore(tmp, last);
            last = tmp;
        }
        element.parentNode.removeChild(element);
    };

    nggc_share_icons = {
        strip_html: function(html) {
            var tmp = document.createElement('div');
            tmp.innerHTML = html;
            return tmp.textContent || tmp.innerText || "";
        },
        create: function(target, image) {
            var image_id = image.data('image-id');
            var gallery_id = image.data('nplmodal-gallery-id');

            var base_url = encodeURIComponent(this.get_share_url(gallery_id, image_id));
            var url = encodeURIComponent(window.location.toString());
            var title = this.strip_html(image.data('title'));
            var summary = this.strip_html(image.data('description'));

            var twitter_icon = $('<i/>', {
                'data-href': 'https://twitter.com/share?url=' + this.get_share_url(gallery_id, image_id, 'full'),
                'class': 'ngg-caption-icon fa fa-twitter-square'
            });

            var facebook_url = 'https://www.facebook.com/share.php';
            facebook_url += '?u=' + encodeURIComponent(this.get_share_url(gallery_id, image_id, 'full'));
            var facebook_icon = $('<i/>', {
                'data-href': facebook_url,
                'class': 'ngg-caption-icon fa fa-facebook-square'
            });

            var pinterest_url = encodeURIComponent(this.get_share_url(gallery_id, image_id, 'full'));
            pinterest_url += '&url=' + url;
            pinterest_url += '&media=' + image.attr('href');
            pinterest_url += '&description=' + summary;
            var pinterest_icon = $('<i/>', {
                'data-href': 'http://pinterest.com/pin/create/button/?s=100' + pinterest_url,
                'class': 'ngg-caption-icon fa fa-pinterest-square'
            });

            var icons = [twitter_icon, facebook_icon, pinterest_icon];
            $(icons).each(function() {
                $(this).on('click', function(event) {
                    event.preventDefault();
                    var share_window = window.open($(this).data('href'), '_blank');
                    share_window.trigger('focus');
                    // improper, but otherwise the pro lightbox will open
                    return false;
                });
            });
            target.html('').append(icons);
        },
        get_share_url: function(gallery_id, image_id, named_size) {
            if (typeof(named_size) == 'undefined') {
                named_size = 'thumb';
            }

            var base_url = $.nplModal('get_setting', 'share_url')
                .replace('{gallery_id}', gallery_id)
                .replace('{image_id}', image_id)
                .replace('{named_size}', named_size);
            var site_url_link = $('<a/>').attr('href', $.nplModal('get_setting', 'wp_site_url'))[0];
            var parent_link = $('<a/>').attr('href', window.location.toString())[0];
            var base_link = $('<a/>').attr('href', base_url)[0];

            // check if site is in a sub-directory and shorten the prefix
            if (parent_link.pathname.indexOf(site_url_link.pathname) >= 0) {
                parent_link.pathname = parent_link.pathname.substr(site_url_link.pathname.length);
            }
            // shorten url by removing their common prefix
            if (parent_link.pathname.indexOf(base_link.pathname) >= 0) {
                parent_link.pathname = parent_link.pathname.substr(parent_link.pathname.length);
            }

            // this is odd but it's just how the 'share' controller expects it
            base_link.search = parent_link.search;
            if (base_link.search.length > 0) {
                base_link.search += '&';
            }
            base_link.search += 'uri=' + parent_link.pathname;

            return base_link.href;
        }
    };

    $.fn.nggcaption = function() {
        this.each(function() {
            var $this = $(this);

            var gallery = nggc_get_gallery($this.data('ngg-captions-id'));
            var style   = nggc_get_setting(gallery, 'captions_animation', 'slideup');
            var $img    = $this.find('img').first();

            // There's no src attribute; dont process this yet
            if (!$img.attr('src') && !$img.attr('srcset')) {
                return true;
            }

            var $figure     = $('<figure class="ngg-figure"></figure>');
            var $figcaption = $('<figcaption class="ngg-figcaption"></figcaption>');

            if (nggc_get_setting(gallery, 'captions_display_title', true)) {
                var $header = $('<h6>' + nggc_sanitize($this.data('title')) + '</h6>');
                if (!nggc_get_setting(gallery, 'captions_display_title', true)) {
                    $figure.addClass('nggc_no_title');
                }
            }

            var $body_div = $('<div class="nggc-body"/>');
            var $body_p = $('<p>' + nggc_sanitize($this.data('description')) + '</p>');
            $body_div.append($body_p);

            if (!nggc_get_setting(gallery, 'captions_display_description', true)) {
                $figure.addClass('nggc_no_description');
            }

            // remove any styles: they will be assigned to the new <figure>
            var classList    = $this.attr('class');
            var inlineStyles = $this.attr('style');
            var imageStyles  = $img.attr('style');

            $this.removeAttr('title');
            if (!$this.data('ngg-captions-nostylecopy')) {
                $this.removeAttr('class');
                $this.removeAttr('style');
            }

            // Always keep this class assigned
            this.classList.add('ngg-captions-processed');

            // each class is responsible for a different animation
            $figure.addClass('ngg-figure-' + style);
            $figcaption.addClass('ngg-figcaption-' + style);

            // add share icons if the NextGen Pro Lightbox is active & allows routing
            if ($.inArray(gallery.source, ['albums', 'random', 'random_images']) == -1
            &&  typeof window.nplModalSettings != 'undefined'
            &&  nplModalSettings['enable_routing'] == '1'
            &&  nplModalSettings['enable_sharing'] == '1'
            &&  nggc_get_setting(gallery, 'captions_display_sharing', true)) {
                var slug = gallery.slug;
                if (!slug || slug.indexOf('widget-ngg-images') == -1) {
                    var $iconwrapper = $('<div class="nggc-icon-wrapper"/>');
                    nggc_share_icons.create($iconwrapper, $this);
                    $(document).trigger('ngg-caption-add-icons', {
                        el: $iconwrapper,
                        image_id: $this.data('image-id'),
                        gallery_id: $this.data('nplmodal-gallery-id')
                    });
                    $figcaption.append($iconwrapper);
                }
            } else {
                $figure.addClass('nggc_no_sharing');
            }

            if (nggc_get_setting(gallery, 'captions_display_title', true)) {
                $figcaption.append($header);
            }

            if (nggc_get_setting(gallery, 'captions_display_description', true)) {
                $figcaption.append($body_div);
            }

            if (user_is_using_ie && user_is_using_ie === 10) {
                // IE10 does not support nested flexboxes; it will display description text without wrapping
                // to fix this "Titlebar" theme uses display:inline-block when the user has IE10
                $($figure).addClass("nggc-ie10");
            }

            // reassign the anchor & image styles to our figure
            if (!$this.data('ngg-captions-nostylecopy')) {
                $figure.attr('style', imageStyles);
                $figure.attr('style', inlineStyles);
            }

            $img.removeAttr('style');

            // because the blog gallery wrappers are wider than the image itself
            if (!$this.data('ngg-captions-nostylecopy')) {
                $figure.css({'max-width': parseInt($img[0].getAttribute('width')) + 'px'});
            }

            // put our figure object in its place as a wrapper
            $newfigure = $img.wrap($figure);
            $img.after($figcaption);

            if (!$this.data('ngg-captions-nostylecopy')) {
                $newfigure.addClass(classList);
            }

            // When the image has loaded then trigger the shave() processing
            $newfigure.imagesLoaded().done(function() {
                _unthrottled_nggc_adjust($figcaption);
            });

            $newfigure.parent('p').before($newfigure);
        });

        $(document).trigger('ngg-captions-added');
    };

    const _unthrottled_nggc_adjust = function($elements) {
        if (typeof $elements == 'undefined') {
            $elements = $('figcaption.ngg-figcaption');
        }

        // This is a hack specific to thickbox, which insists on giving its class
        // to the <img> element directly which doesn't work here
        $('div.nextgen_pro_thumbnail_grid a').each(function() {
            var $this = $(this);
            var $img = $this.find('img');
            if ($img.hasClass('thickbox')) {
                $img.removeClass('thickbox');
                $this.addClass('thickbox');
            }
        });

        // Concatenate our text so it won't overflow
        $elements.each(function() {
            const $self   = $(this);
            const gallery = nggc_get_gallery($self.parents('a').first().data('ngg-captions-id'));
            const style   = nggc_get_setting(gallery, 'captions_animation', 'slideup');


            const h6 = $self.find('h6');
            const h6_height = h6.length !== 0 ? h6.outerHeight() : 0;
            const total_height = $self.outerHeight();
            const icon_wrapper = $self.find('.nggc-icon-wrapper');
            const icon_wrapper_height = icon_wrapper.length !== 0 ? icon_wrapper.height() : 0;
            const body = $self.find('.nggc-body');
            const p    = $self.find('.nggc-body p');

            if (style === 'titlebar') {
                // Because 'titlebar' only allows one line for image titles we can use CSS to trim the title length
                // thus here we only process the image description
                if (p.length !== 0) {
                    shave(p[0], body.height());
                }
            } else {
                // Users can disable titles and descriptions; check for their existence first
                if (h6.length !== 0) {
                    shave(h6[0], (total_height - icon_wrapper_height));
                }
                if (p.length !== 0) {
                    shave(p[0], (total_height - icon_wrapper_height - h6_height - 20));
                }
            }
        });
    };

    const nggc_adjust = _.debounce(_unthrottled_nggc_adjust, 500, true);

    window.ngg_captions_adjust = nggc_adjust;

    const nggc_init = function(bind) {
        bind = bind || false;
        $('a[data-ngg-captions-enabled]:not(".ngg-captions-processed")').each(function() {
            this.classList.add('ngg-captions-processed');
            const $this = $(this);
            if (bind) {
                $(window).on('load', function () {
                    $this.nggcaption();
                });
            } else {
                $this.nggcaption();
            }
        });
    };

    nggc_init(true);

    $(window).on('refreshed', function() {
        nggc_init();
        nggc_adjust();
    });

    $(window).on('resize fullscreenchange mozfullscreenchange webkitfullscreenchange orientationchange', function() {
        nggc_adjust();
    });
})(jQuery);

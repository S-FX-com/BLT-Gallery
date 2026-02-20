(function($) {
    "use strict";

    function nplModal() {

        var core = {
            debug: false,
            debug_level: 1, // 1-4
            state: {
                slug: null,
                gallery_id: null,
                image_id: null,
                sidebar: null
            },
            selector: nextgen_lightbox_filter_selector($, $(".nextgen_pro_lightbox")),
            options: {},
            defaults: {
                speed: 'medium', // see jQuery docs for setting

                // the url is restored to this location when the lightbox closes
                initial_url: window.location.toString().split('#')[0],

                ajax_url: photocrati_ajax.url,
                router_slug: 'gallery'
            },

            init: function(parameters) {
                core.methods.log('nplModal.init()', {
                    parameters: parameters,
                    defaults: core.defaults,
                    selector: core.selector,
                    debug: core.debug,
                    debug_level: core.debug_level
                });

                var overlay   = $("<div id='npl_overlay'></div>");
                var wrapper   = $("<div id='npl_wrapper'></div>");
                var spinner   = $("<div id='npl_spinner_container' class='npl-loading-spinner hidden'><i id='npl_spinner' class='fa fa-spin fa-spinner'></i></div>");
                var btn_close = $("<div id='npl_button_close' class='hidden'><i class='fa fa-times'></i></div>");
                var content   = $("<div id='npl_content'></div>");
                var sidebar   = $("<div id='npl_sidebar'></div>");
                var s_overlay = $("<div id='npl_sidebar_overlay'><i class='fa fa-spin fa-spinner'/></div>");
                var toggle    = $("<i   id='npl_sidebar_toggle' class='fa fa-arrow-right'/>");

                if (core.methods.mobile.browser.ios()) {
                    overlay.addClass('npl_ios_no_opacity');
                    wrapper.addClass('npl_ios_hack');
                }
                if (core.methods.is_ie9()) {
                    wrapper.addClass('npl_ie9_flex_hack');
                }

                // Provide a hook for third-parties to add their own methods
                $(window).trigger('override_nplModal_methods', core.methods);

                overlay.css({background: core.methods.get_setting('background_color')});
                spinner.css({color: core.methods.get_setting('icon_color')});
                s_overlay.css({color: core.methods.get_setting('icon_color')});
                btn_close.css({color: core.methods.get_setting('overlay_icon_color')});
                sidebar.css({background: core.methods.get_setting('sidebar_background_color')});
                toggle.css({color: core.methods.get_setting('overlay_icon_color')});

                var body = $('body');
                body.append(overlay);
                body.append(wrapper);
                wrapper.append(spinner);
                wrapper.append(btn_close);
                wrapper.append(toggle);
                wrapper.append(content);
                wrapper.append(sidebar);
                wrapper.append(s_overlay);

                // get_setting() isn't available when declaring the base defaults
                parameters = $.extend(parameters, {router_slug: core.methods.get_setting('router_slug')});
                core.options = $.extend(core.defaults, parameters);

                core.methods.bind_images();
                core.methods.set_events();
                core.methods.mobile.init();

                // Restrict the padding so that we at *least* have half of the screen to display on
                if (parseInt(core.methods.get_setting('padding', '0')) > 0) {
                    var space        = core.methods.get_setting('padding', '0');
                    var space_unit   = core.methods.get_setting('padding_unit', 'px');
                    var maxWidth     = window.innerWidth  / 4;
                    var maxHeight    = window.innerHeight / 4;
                    var maxDimension = Math.min(maxWidth, maxHeight);

                    if (space_unit === 'px' && space > maxDimension) {
                        space = maxDimension;
                    }
                    else if (space_unit === '%' && space > 25) {
                        space = 25;
                    }

                    space = space + space_unit;

                    $("<style type='text/css'>#npl_wrapper.npl_open_with_padding {"
                        + 'top: '    + space + ';'
                        + 'bottom: ' + space + ';'
                        + 'left: '   + space + ';'
                        + 'right: '  + space + ';'
                    + " } </style>").appendTo("head");
                }

                core.methods.router.routes.push({
                    re: new RegExp('^' + core.options.router_slug + '\/(.*)\/(.*)\/(.*)$', 'i'),
                    handler: core.methods.url_handler
                });

                core.methods.router.routes.push({
                    re: new RegExp('^' + core.options.router_slug + '\/(.*)\/(.*)$', 'i'),
                    handler: core.methods.url_handler
                });

                core.methods.router.routes.push({
                    re: new RegExp('^' + core.options.router_slug + '$', 'i'),
                    handler: core.methods.close_modal
                });

                core.methods.router.routes.push({
                    re: '',
                    handler: core.methods.close_modal
                });

                // Ask the router to listen for routing requests
                // Safari and Opera don't fire the load event when the cache
                // has been cached, and the pageshow event should be used instead
                /**
                 SEE:
                 ------
                 Webkit: http://webkit.org/blog/516/webkit-page-cache-ii-the-unload-event/
                 Firefox: https://developer.mozilla.org/En/Using_Firefox_1.5_caching.
                 Chrome: https://code.google.com/p/chromium/issues/detail?id=2879
                 */
                ['load', 'pageshow'].map(function(eventType){
                    setTimeout(function() {
                        core.methods.router.listen();
                    }, 200);
                })

                // Hack for iOS and some Android browsers that can't handle position:fixed when the keyboard is open
                if (core.methods.mobile.browser.ios()
                ||  core.methods.mobile.browser.android()) {
                    wrapper.addClass('npl_mobile');
                }

                $(window).trigger('npl_initialization_complete');
            },

            methods: {
                _pre_open_callbacks: [],
                _is_open: false,

                getDPIRatio: function() {
                    var ratio = 1;

                    // To account for zoom, change to use deviceXDPI instead of systemXDPI
                    if (window.screen.systemXDPI !== undefined
                    &&  window.screen.logicalXDPI !== undefined
                    &&  window.screen.systemXDPI > window.screen.logicalXDPI) {
                        // Only allow for values > 1
                        ratio = window.screen.systemXDPI / window.screen.logicalXDPI;
                    } else if (window.devicePixelRatio !== undefined) {
                        ratio = window.devicePixelRatio;
                    }

                    core.methods.log('nplModal.getDPIRatio()', {
                        result: ratio
                    });
                    return ratio;
                },

                url_handler: function() {
                    var slug     = arguments[0];
                    var image_id = arguments[1];
                    var sidebar  = null;
                    if (arguments.length === 3) {
                        sidebar = arguments[2];
                        if (sidebar === '1') {
                            sidebar = 'comments';
                        }
                    }

                    // need to get slug, image_id, and sidebar
                    // determine the ID from our slug. if nothing comes back, assume we're already looking at the ID
                    var gallery_id = this.get_id_from_slug(slug);
                    if (!gallery_id) {
                        gallery_id = slug;
                    }

                    // Image_ID should remain an integer for all NGG provided images
                    // but it's passed to this method as a string
                    var old_state = this.get_state();
                    if (parseInt(image_id) === old_state.image_id) {
                        image_id = parseInt(image_id);
                    }

                    var state = {
                        gallery_id: gallery_id,
                        image_id: image_id,
                        sidebar: sidebar,
                        slug: slug
                    };

                    this.set_state(state);

                    $('#npl_content').trigger('npl_url_handler', [state]);

                    if (this.is_open() && gallery_id === core.state.gallery_id) {
                        return;
                    }

                    this.open_modal(gallery_id, image_id, sidebar);
                },

                run_pre_open_lightbox_callbacks: function(link, params) {
                    for (var i = 0; i < this._pre_open_callbacks.length; i++) {
                        var callback = this._pre_open_callbacks[i];
                        params = callback(link, params);
                    }
                    return params;
                },

                add_pre_open_callback: function(callback) {
                    this._pre_open_callbacks.push(callback);
                },

                get_state: function() {
                    return core.state;
                },

                set_state: function(state) {
                    core.methods.log("nplModal.set_state()", {
                        state: state
                    });
                    core.state = state;
                },

                get_setting: function (name, def) {
                    var tmp = '';
                    if (typeof nplModalSettings !== 'undefined'
                        &&  typeof nplModalSettings[name] !== 'undefined'
                        &&  nplModalSettings[name] !== '') {
                        tmp = window.nplModalSettings[name];
                    } else {
                        tmp = def;
                    }
                    if (tmp === 1)   tmp = true;
                    if (tmp === 0)   tmp = false;
                    if (tmp === '1') tmp = true;
                    if (tmp === '0') tmp = false;

                    core.methods.log('nplModal.get_setting()', {
                        name: name,
                        result: tmp
                    }, 3);
                    return tmp;
                },

                get_slug: function (gallery_id) {
                    var slug = gallery_id;
                    if ('undefined' === typeof window.galleries) { return slug; }

                    $.each(galleries, function(index, gallery) {
                        if (gallery.slug && gallery.ID === gallery_id) {
                            slug = gallery.slug;
                        }
                    });

                    core.methods.log('nplModal.get_slug()', {
                        gallery_id: gallery_id,
                        result: slug
                    });
                    return slug;
                },

                open: function($el) {
                    core.methods.log('nplModal.open()', {
                        el: $el
                    });

                    // Define parameters for opening the Pro Lightbox
                    var params = {
                        show_sidebar: '',
                        gallery_id: '!',
                        image_id: '!',
                        slug: null,
                        revert_image_id: '!',
                        open_the_lightbox: true
                    };

                    // Determine if we should show the comment sidebar
                    if ($el.data('nplmodal-show-comments')) {
                        params.show_sidebar = '/comments';
                    }

                    // Determine the gallery id
                    if ($el.data('nplmodal-gallery-id')) {
                        params.gallery_id = $el.data('nplmodal-gallery-id');
                    }

                    // Determine the image id
                    if ($el.data('nplmodal-image-id')) {
                        params.image_id = parseInt($el.data('nplmodal-image-id'));
                    } else if ($el.data('image-id')) {
                        params.image_id = parseInt($el.data('image-id'));
                    } else if (params.gallery_id === '!') {

                        var $image = $el.find('img');
                        params.image_id = $image.attr('src');
                        if ($image.attr('srcset')) {
                            var sizes = parseSrcset($image.attr('srcset'));
                            var largest_w = 0;
                            _.each(sizes, function (row) {
                                if (typeof row.w !== undefined && row.w > largest_w) {
                                    largest_w = row.w;
                                    params.image_id = row.url;
                                }
                            });
                        }
                    }

                    // Determine the slug
                    if (params.gallery_id !== '!') {
                        params.slug = this.get_slug(params.gallery_id);
                    }

                    // Run any registered callbacks for modifying lightbox params
                    params = this.run_pre_open_lightbox_callbacks($el, params);

                    // Are we to still open the lightbox?
                    if (params.open_the_lightbox) {
                        // open the pro-lightbox manually
                        if (params.gallery_id === '!' || !this.get_setting('enable_routing')) {
                            this.open_modal(params.gallery_id, params.image_id, null);
                        } else {
                            // open the pro-lightbox through our backbone.js router
                            core.methods.router.front_page_pushstate(params.gallery_id, params.image_id);

                            core.methods.set_state({
                                gallery_id: params.gallery_id,
                                image_id: params.image_id,
                                sidebar: params.show_sidebar,
                                slug: params.slug
                            });

                            this.router.navigate(
                                core.options.router_slug
                                + '/' + params.slug
                                + '/' + params.image_id
                                + params.show_sidebar
                            );

                            // some displays (random widgets) may need to disable routing
                            // but still pass an image-id to display on startup
                            if (params.revert_image_id !== '!') {
                                core.state.image_id = params.revert_image_id;
                            }
                        }
                    }
                },

                bind_images: function() {
                    // to handle ajax-pagination events this method is called on the 'refreshed' signal
                    var selector = nextgen_lightbox_filter_selector($, $(".nextgen_pro_lightbox"));

                    // Modify the selector to exclude any Photocrati Lightboxes
                    var new_selector = [];
                    for (var index = 0; index < selector.length; index++) {
                        var el = selector[index];
                        if (!$(el).hasClass('photocrati_lightbox_always') && !$(el).hasClass('decoy')) {
                            new_selector.push(el);
                        }
                    }

                    // Ensure that we're attaching the event to the A tag, not the img, if
                    // there is one.
                    // TODO: Should this be moved to nextgen_lightbox_filter_selector() ?
                    new_selector = new_selector.map(function(el) {
                        return $(el).hasClass('nextgen_pro_lightbox') && el.nodeName != 'A' && $(el).parents('a').length
                            ? $(el).parents('a')[0]
                            : el;
                    });
                    
                    core.selector = $(new_selector);
                    core.selector.on('click', function (event) {
                        // pass these by
                        if ($.inArray($(this).attr('target'), ['_blank', '_parent', '_top']) > -1) {
                            return;
                        }

                        // NextGEN Basic Thumbnails has an option to link to an imagebrowser display; this disables the effect
                        // code (we have no gallery-id) but we may be asked to open it anyway if lightboxes are set to apply
                        // to all images. Check for and do nothing in that scenario:
                        if ($(this).data('src')
                        &&  $(this).data('src').indexOf(core.methods.get_setting('router_slug') + '/image') !== -1
                        &&  !$(this).data('nplmodal-gallery-id')) {
                            return;
                        }

                        event.stopPropagation();
                        event.preventDefault();

                        if (event.handled !== true) {
                            event.handled = true;
                            core.methods.open($(this));
                        }
                    });
                },

                // establishes bindings of events to actions
                set_events: function() {
                    var self = this;

                    $(window).on('refreshed', self.bind_images);
                    $(window).on('keydown', self.handle_keyboard_input);

                    $('#npl_content').on('npl_images_ready', self.open_modal_final);

                    // handle exit clicks/touch events
                    $('#npl_overlay, #npl_button_close').on('touchstart click', function(event) {
                        event.stopPropagation();
                        event.preventDefault();
                        if (event.handled !== true) {
                            event.handled = true;
                            self.close_modal();
                        }
                    });
                },

                open_modal: function(gallery_id, image_id, sidebar) {
                    core.methods.log('nplModal.open_modal()', {
                        gallery_id: gallery_id,
                        image_id: image_id,
                        sidebar: sidebar
                    });

                    this._is_open = true;

                    // allow for cleanup handlers to run
                    $('#npl_content').trigger('npl_opening');

                    // disables browser scrollbar display
                    $('html, body').toggleClass('nextgen_pro_lightbox_open');
                    core.state.image_id   = image_id;
                    core.state.gallery_id = gallery_id;

                    $('#npl_spinner_container').removeClass('hidden');
                    $('#npl_button_close').removeClass('hidden');

                    this.fullsize.exit();
                    this.mobile.open();

                    // fetch_images(), when done building a list of images to feed to Galleria, will trigger
                    // an event on #npl_content that is handled by open_modal_final()
                    core.methods.fetch_images.fetch_images(gallery_id, image_id);
                },

                preload_image: function(image_obj) {
                    return new Promise(resolve => {
                        const image = new Image();
                        image.addEventListener('load', () => {
                            resolve();
                        });
                        image.src = image_obj.use_hdpi ? image_obj.srcsets.hdpi : image_obj.image;
                    });
                },

                open_modal_final: function(event, gallery_id) {
                    var show_ndx = 0;
                    var show_hdpi = core.methods.getDPIRatio() > 1;
                    var images = core.methods.fetch_images.gallery_image_cache[core.state.gallery_id];
                    var sidebar = core.state.sidebar;

                    $.each(images, function(index, element) {
                        // Mark the requested image as the one to show at startup
                        if (parseInt(core.state.image_id) === parseInt(element.image_id)) {
                            show_ndx = index;
                        }

                        // A WP media library or 'other' / non-nextgen image
                        if (typeof core.state.image_id == 'string' && typeof element.image_id == 'string') {
                            if (core.state.image_id === element.image_id) {
                                show_ndx = index;
                            }
                        }

                        // In case we're viewing a WP or non-NGG image
                        if (typeof element.full_use_hdpi !== 'undefined') {
                            // Massage our data for High-DPI screens
                            if (show_hdpi && element.full_use_hdpi) {
                                element.image = element.full_srcsets.hdpi;
                            } else {
                                // In case the plain 'image' is a dynamically-shrunk version:
                                // we always want to use the 'full' size image when available
                                element.image = element.full_image;
                            }
                        }
                    });

                    // Determine if carousel thumbnails should be enabled
                    var thumbnails       = core.methods.get_setting('enable_carousel', 'always');
                    var thumbnails_limit = core.methods.get_setting('carousel_thumbnails_limit', 250);
                    var gallery          = core.methods.get_gallery_from_id(gallery_id);
                    if (thumbnails === 'always') {
                        thumbnails = 'lazy';
                    } else if (thumbnails === 'never') {
                        thumbnails = false;
                    } else if (thumbnails === 'nomobile') {
                        if (core.methods.mobile.browser.any()) {
                            thumbnails = false;
                        } else {
                            thumbnails = 'lazy';
                        }
                    }

                    // Very large galleries can severely impact performance. Disabling carousel thumbnails
                    // can greatly relieve that impact however, so we automatically change for very large galleries
                    if (gallery && gallery.images_list_count >= thumbnails_limit) {
                        thumbnails = false;
                    }

                    if (!thumbnails) {
                        $('#npl_wrapper').addClass('nggpl-carousel-hidden');
                    }

                    if (sidebar
                    || (core.methods.get_setting('display_cart', false)     && core.methods.get_displayed_gallery_setting(gallery_id, 'is_ecommerce_enabled', false))
                    || (core.methods.get_setting('display_comments', false) && core.methods.get_setting('enable_comments', false))) {
                        $('#npl_wrapper').addClass('npl-sidebar-open npl-sidebar-overlay-open');
                    }

                    if (core.methods.get_setting('display_captions', false)) {
                        $('#npl_wrapper').addClass('npl-info-open');
                    }

                    if (core.methods.get_setting('style')) {
                        $('#npl_wrapper').addClass('npl-variant-' + core.methods.get_setting('style'))
                    }

                    if (!core.methods.get_setting('display_carousel', true) || core.methods.mobile.browser.any()) {
                        $('#npl_wrapper').addClass('npl-carousel-closed');
                    }

                    if (images.length >= thumbnails_limit) {
                        thumbnails = false;
                    }

                    core.methods.log('nplModal.open_modal() about to invoke Galleria.run()', {
                        thumbnails: thumbnails,
                        images: images,
                        show: show_ndx,
                        sidebar: sidebar
                    });

                    // Preload the first six images ourselves, THEN start Galleria. This is done to mitigate Galleria.js
                    // positioning images incorrectly when they are slow to load at startup and during transitions.
                    const preloads =  images.slice((show_ndx - 3), (show_ndx + 3));
                    const promises = preloads.map(image => core.methods.preload_image(image));

                    Promise.all(promises).then((values) => {
                        Galleria.run('#npl_content', {
                            responsive: true,
                            thumbQuality: false,
                            thumbnails: thumbnails,
                            preload: 0,
                            theme: 'nextgen_pro_lightbox',
                            dataSource: images,
                            show: show_ndx,
                            variation:           'nggpl-variant-' + core.methods.get_setting('style', ''),
                            transition:          core.methods.get_setting('transition_effect', 'slide'),
                            touchTransition:     core.methods.get_setting('touch_transition_effect', 'slide'),
                            imagePan:            core.methods.get_setting('image_pan', false),
                            pauseOnInteraction:  core.methods.get_setting('interaction_pause', true),
                            imageCrop:           core.methods.get_setting('image_crop', true),
                            transitionSpeed:    (core.methods.get_setting('transition_speed', 0.4) * 1000),
                            nggSidebar:          sidebar
                        });

                        // Set the background color right away, so that we don't have to wait for the theme init
                        $('#npl_content .galleria-container').css({background: core.methods.get_setting('background_color')});
                    });

                },

                // When rotaning or opening the keyboard some mobile browsers increase the user zoom level beyond the default.
                // To handle this we update the viewport setting to disable zooming when open_modal is run and restore it to
                // the original value when calling close_modal()
                mobile: {
                    meta: null,
                    original: null, // original viewport setting; it's restored at closing
                    adjust: true,
                    ontouch: ('ontouchstart' in window),
                    init: function() {
                        // suppress a warning in desktop chrome (provided no touch input devices are attached) that the following
                        // content meta-attribute we're about to set is invalid. it technically is, but it's the only way
                        // to make every mobile browser happy without ridiculous user agent matching that I've come across so far
                        if (!this.ontouch) {
                            this.adjust = false;
                        }
                        var doc = window.document;
                        if (!doc.querySelector) { return; } // this isn't available on pre 3.2 safari
                        this.meta     = doc.querySelector("meta[name=viewport]");
                        this.original = this.meta && this.meta.getAttribute("content");
                    },
                    open: function() {
                        if (this.adjust && this.meta) {
                            core.methods.log('nplModal.mobile.open()');
                            this.meta.setAttribute("content", this.original + ', width=device-width, height=device-height, initial-scale=1.0, minimum-scale=1, maximum-scale=1, user-scalable=0, shrink-to-fit=no');
                        }
                    },
                    close: function() {
                        if (this.adjust && this.meta) {
                            core.methods.log('nplModal.mobile.close()');
                            this.meta.setAttribute("content", this.original);
                        }
                    },
                    browser: {
                        any: function() {
                            return core.methods.mobile.browser.android()
                                || core.methods.mobile.browser.ios()
                                || core.methods.mobile.browser.windowsphone()
                                || core.methods.mobile.browser.blackberry();
                        },
                        android: function() {
                            return /Android/i.test(navigator.userAgent);
                        },
                        ios: function() {
                            return /crios|iP(hone|od|ad)/i.test(navigator.userAgent);
                        },
                        windowsphone: function() {
                            return /(iemobile|Windows Phone)/i.test(navigator.userAgent);
                        },
                        blackberry: function() {
                            return /(blackberry|RIM Tablet|BB10; )/i.test(navigator.userAgent);
                        }
                    }
                },

                is_ie9: function() {
                    return navigator.appVersion.indexOf("MSIE 9.") !== -1;
                },

                // hide our content and close up
                close_modal: function() {
                    if (!this._is_open) {
                        return;
                    }

                    core.methods.log('nplModal.close_modal()');

                    var content = $('#npl_content');

                    // allow for cleanup handlers to run
                    content.trigger('npl_closing');

                    // kill Galleria so it won't suck up memory in the background
                    const innerGalleria = content.data('galleria');
                    if ('undefined' !== typeof innerGalleria) {
                        innerGalleria.destroy();
                    }

                    this.fullsize.enter();

                    // for use with Galleria it is important that npl_content never have display:none set
                    $('#npl_spinner_container, #npl_button_close').addClass('hidden');

                    // enables displaying browser scrollbars
                    $('html, body').toggleClass('nextgen_pro_lightbox_open');

                    // Reset all statuses such as whether the sidebar has been triggered
                    $('#npl_wrapper').removeClass();

                    this.mobile.close();

                    // reset our modified url to our original state
                    if (this.get_setting('enable_routing')) {
                        if (history.pushState) {
                            history.pushState('', document.title, window.location.pathname + window.location.search);
                        } else {
                            window.location.hash = '';
                        }
                        if (this.get_setting('is_front_page') && history.pushState) {
                            history.pushState({}, document.title, core.options.initial_url);
                        }
                    }

                    this._is_open = false;
                },

                fullsize: {
                    _is_fullsize: false,

                    active: function() {
                        return this.fullsize._is_fullsize;
                    },

                    enter: function() {
                        core.methods.log('nplModal.fullsize.enter()');

                        $('#npl_wrapper').removeClass('npl_open_with_padding');
                        this._is_fullsize = true;
                    },

                    exit: function() {
                        core.methods.log('nplModal.fullsize.exit()');

                        if (parseInt(core.methods.get_setting('padding', '0')) > 0
                        &&  !core.methods.mobile.browser.ios()) {
                            $('#npl_wrapper').addClass('npl_open_with_padding');
                        }

                        this._is_fullsize = false;
                    },

                    toggle: function() {
                        core.methods.log('nplModal.fullsize.toggle()');

                        if (this.fullsize._is_fullsize) {
                            this.fullsize.exit();
                        } else {
                            this.fullsize.enter();
                        }
                        $(window).trigger('resize');
                    }
                },

                handle_keyboard_input: function(event) {
                    if (core.methods.is_open()) {
                        // escape key closes the modal
                        if (event.which === 27) {
                            core.methods.close_modal();
                        }
                    }
                },

                is_open: function() {
                    return this._is_open;
                },

                log: function(message, table, level) {
                    if (!core.debug) {
                        return;
                    }

                    if (typeof level === 'undefined') {
                        level = 1;
                    }

                    if (level > core.debug_level) {
                        return;
                    }

                    if (typeof table === 'undefined') {
                        console.log("NPL Debug:", message)
                    } else {
                        console.log("NPL Debug:", message, table);
                    }

                },

                logging: {
                    enable: function() {
                        console.log("Enabling NextGen Pro Lightbox debugging");
                        core.debug = true;
                    },
                    disable: function() {
                        console.log("Disabling NextGen Pro Lightbox debugging");
                        core.debug = false;
                    },
                    set_level: function(level) {
                        console.log("Setting NextGen Pro Lightbox debug level to " + level);
                        core.level = parseInt(level);
                    }
                },

                fetch_images: {
                    gallery_image_cache: [],
                    ajax_info: [],
                    ajax_interval: null,
                    ajax_delay: 1400,

                    is_cached: function(gallery_id, image_id) {
                        var found = false;
                        $.each(this.gallery_image_cache[gallery_id], function (ndx, image) {
                            if (image_id === image.image_id) {
                                found = true;
                            }
                        });

                        core.methods.log("nplModal.fetch_images.is_cached()", {
                            gallery_id: gallery_id,
                            image_id: image_id,
                            gallery_cache: this.gallery_image_cache[gallery_id],
                            found: found
                        }, 4);

                        return found;
                    },

                    fetch_images: function(gallery_id, image_id) {
                        core.methods.log('nplModal.fetch_images.fetch_images() beginning');
                        var self = this;

                        this.ajax_delay = core.methods.get_setting('ajax_delay', 1400);

                        if (typeof this.gallery_image_cache[gallery_id] === 'undefined') {
                            this.gallery_image_cache[gallery_id] = [];
                        }

                        var gallery = core.methods.get_gallery_from_id(gallery_id);

                        if (gallery === null) {
                            // It's not a NextGen gallery, just read from the page
                            return this.fetch_non_ngg_images(gallery_id);
                        }

                        // Gallery is already cached - just trigger the ready event
                        if (this.gallery_image_cache[gallery_id].length === gallery.images_list_count) {
                            return $('#npl_content').trigger('npl_images_ready', [gallery_id]);
                        }

                        // Build the cache from the list 'localized' on the page
                        $.each(gallery.images_list, function(ndx, image) {
                            if (!self.is_cached(gallery_id, image.image_id)) {
                                self.gallery_image_cache[gallery_id].push(image);
                            }
                        });

                        // Again check if our cache is full
                        if (this.gallery_image_cache[gallery_id].length === gallery.images_list_count) {
                            return $('#npl_content').trigger('npl_images_ready', [gallery_id]);
                        }

                        // Not all images are on this page: fetch by XHR - which handles the ready event
                        if (this.gallery_image_cache[gallery_id].length < gallery.images_list_count) {
                            return this.fetch_images_from_ajax(gallery_id);
                        }
                    },

                    fetch_non_ngg_images: function (gallery_id) {
                        core.methods.log('nplModal.fetch_images.fetch_non_ngg_images() beginning', {
                            gallery_id: gallery_id
                        });

                        var self = this;

                        core.selector.each(function() {
                            var anchor = $(this);

                            if (anchor.hasClass('ngg-trigger')) {
                                return true; // exclude NextGEN trigger icons
                            }

                            if (gallery_id !== '!' && gallery_id !== anchor.data('nplmodal-gallery-id')) {
                                return true; // exclude images from other galleries
                            }


                            if (gallery_id !== core.methods.get_state().gallery_id) {
                                return true; // exclude images from other galleries
                            }

                            if (core.methods.get_state().gallery_id === '!' && anchor.data('nplmodal-gallery-id')) {
                                return true; // when viewing non-nextgen images; exclude nextgen-images
                            }

                            var image         = $(this).find('img').first();
                            var gallery_image = {};
                            var expr          = /\.(jpeg|jpg|gif|png|bmp)$/i;

                            gallery_image.image = (anchor.data('fullsize') === undefined) ? anchor.attr('href') : anchor.data('fullsize');

                            if (typeof gallery_image.image !== 'undefined'
                            &&  !gallery_image.image.match(expr)
                            &&  image.attr('srcset')) {
                                var sizes = parseSrcset(image.attr('srcset'));
                                var largest_w = 0;

                                _.each(sizes, function (row) {
                                    if (typeof row.w !== undefined && row.w > largest_w) {
                                        largest_w = row.w;
                                        gallery_image.image = row.url;
                                    }
                                });
                            }

                            // Workaround WP' "link to attachment page" feature
                            if (!gallery_image.image.match(expr)) {
                                gallery_image.image = image.attr('src');
                            }

                            // When in doubt we id images by their href
                            gallery_image.image_id = (anchor.data('image-id') === undefined) ? gallery_image.image : anchor.data('image-id');

                            // no need to continue
                            if (self.is_cached(gallery_id, gallery_image.image_id)) {
                                return true;
                            }

                            // optional attributes
                            if (anchor.data('thumb') !== undefined) gallery_image.thumb = anchor.data('thumb');
                            else if (anchor.data('thumbnail') !== 'undefined') gallery_image.thumb = anchor.data('thumbnail');

                            if (anchor.data('title') !== undefined) {
                                gallery_image.title = anchor.data('title');
                            } else if (typeof image.attr('title') !== 'undefined') {
                                gallery_image.title = image.attr('title');
                            } else if (typeof anchor.siblings('.wp-caption-text').html() !== 'undefined') {
                                gallery_image.title = anchor.siblings('.wp-caption-text').html();
                            }

                            if (anchor.data('description') !== undefined) {
                                gallery_image.description = anchor.data('description');
                            } else {
                                gallery_image.description = image.attr('alt');
                            }

                            self.gallery_image_cache[gallery_id].push(gallery_image);
                        });

                        core.methods.log('nplModal.fetch_images.fetch_non_ngg_images() result', {
                            result: self.gallery_image_cache[gallery_id]
                        });

                        $('#npl_content').trigger('npl_images_ready', [gallery_id])
                    },

                    create_deferred_ajax: function(gallery_id, gallery, page) {
                        var self = this;
                        return $.ajax({
                            async: true,
                            url: core.options.ajax_url,
                            method: 'POST',
                            data: {
                                id: gallery_id,
                                gallery: gallery,
                                action: 'pro_lightbox_load_images',
                                lang: core.methods.get_setting('lang', null),
                                page: page
                            },
                            dataType: 'json',
                            success: function(data) {
                                core.methods.log('nplModal.fetch_images.create_deferred_ajax() response', {
                                    response: data
                                });
                                $.each(data, function(ndx, newimage) {
                                    if (!self.is_cached(gallery_id, newimage.image_id)) {
                                        self.gallery_image_cache[gallery_id].push(newimage);
                                    }
                                });
                            }
                        });
                    },

                    fetch_images_from_ajax: function(gallery_id, image_id) {
                        if (!core.methods.is_open()) {
                            return;
                        }

                        core.methods.log('nplModal.fetch_images.fetch_images_from_ajax()', {
                            gallery_id: gallery_id,
                            image_id: image_id
                        });

                        var self = this;
                        var original_gallery = core.methods.get_gallery_from_id(gallery_id);
                        var gallery = $.extend({}, original_gallery);
                        delete gallery.images_list;
                        delete gallery.display_settings;

                        var defers = [];
                        for (i = 0; i <= Math.ceil(gallery.images_list_count / core.methods.get_setting('localize_limit')); i++) {
                            defers.push(core.methods.fetch_images.create_deferred_ajax(gallery_id, gallery, i));
                        }

                        $.when.apply($, defers).then(function() {
                            $('#npl_content').trigger('npl_images_ready', [gallery_id]);
                        });

                        return self.gallery_image_cache[gallery_id];
                    }
                },

                get_gallery_from_id: function (gallery_id) {
                    if ('undefined' === typeof window.galleries) { return null; }
                    var retval = null;
                    $.each(window.galleries, function(index, gallery) {
                        if (gallery.ID === gallery_id || gallery.ID === parseInt(gallery_id)) {
                            retval = gallery;
                        }
                    });
                    return retval;
                },

                get_id_from_slug: function (slug) {
                    var id = slug;
                    if ('undefined' === typeof window.galleries) { return id; }

                    $.each(window.galleries, function(index, gallery) {
                        if (gallery.slug === slug) {
                            id = gallery.ID;
                        }
                    });
                    return id;
                },

                get_displayed_gallery_setting: function(gallery_id, name, def) {
                    var tmp = '';
                    var gallery = this.get_gallery_from_id(gallery_id);
                    if (gallery && typeof gallery.display_settings[name] !== 'undefined') {
                        tmp = gallery.display_settings[name];
                    } else {
                        tmp = def;
                    }
                    if (tmp === '1') tmp = true;
                    if (tmp === '0') tmp = false;
                    if (tmp === 1) tmp = true;
                    if (tmp === 0) tmp = false;

                    core.methods.log('nplModal.get_displayed_gallery_setting()', {
                        gallery_id: gallery_id,
                        name: name,
                        result: tmp
                    });

                    return tmp;
                },

                router: {
                    routes: [],
                    interval: null,

                    listen: function(current) {
                        var self = this;
                        current = current || '';

                        var listener = function() {
                            if (current !== self.get_fragment()) {
                                current = self.get_fragment();
                                self.match(current);
                            }
                        };
                        clearInterval(this.interval);
                        this.interval = setInterval(listener, 50);
                    },

                    get_fragment: function(url) {
                        url = url || window.location.href;
                        var match = url.match(/#(.*)$/);
                        var fragment = match ? match[1] : '';
                        return fragment.toString().replace(/\/$/, '').replace(/^\//, '');
                    },

                    navigate: function(path, notrigger) {
                        notrigger = notrigger || false;
                        if (notrigger) {
                            clearInterval(this.interval);
                        }

                        if (!core.methods.get_setting('enable_routing', false)) {
                            return;
                        }

                        path = path ? path : '';
                        window.location.href.match(/#(.*)$/);
                        window.location.href = window.location.href.replace(/#(.*)$/, '') + '#' + path;

                        if (notrigger) {
                            this.router.listen(this.router.get_fragment())
                        }
                    },

                    match: function(f) {
                        var fragment = f || this.get_fragment();
                        for (var i = 0; i < this.routes.length; i++) {
                            var match = fragment.match(this.routes[i].re);
                            if (match) {
                                match.shift();
                                this.routes[i].handler.apply(core.methods, match);
                                return this;
                            }
                        }
                    },

                    front_page_pushstate: function(gallery_id, image_id) {
                        if (!core.methods.get_setting('is_front_page') || gallery_id === undefined) {
                            return false;
                        }

                        if ('undefined' === typeof window.galleries) {
                            return false;
                        }

                        core.methods.log("beginning nplModal.router.front_page_pushstate()", {
                            gallery_id: gallery_id,
                            image_id: image_id
                        });

                        var url  = '';
                        var slug = gallery_id;

                        $.each(galleries, function(index, gallery) {
                            if (gallery.ID === gallery_id && typeof gallery.wordpress_page_root !== 'undefined') {
                                url = gallery.wordpress_page_root;
                                if (gallery.slug) {
                                    slug = gallery.slug;
                                }
                            }
                        });

                        url += '#' + this.get_fragment(core.methods.get_setting('router_slug') + '/' + slug + '/' + image_id);

                        // redirect those browsers that don't support history.pushState
                        if (history.pushState) {
                            history.pushState({}, document.title, url);
                            return true;
                        } else {
                            window.location = url;
                            return false;
                        }
                    }
                }
            }
        };

        this.core = core;
    }

    var nplModalObj = new nplModal();

    $.nplModal = function(param) {
        function getDescendantProp(obj, desc) {
            var arr = desc.split(".");
            while(arr.length) {
                obj = obj[arr.shift()];
            }
            return obj;
        }

        if (typeof param === 'undefined') {
            return nplModalObj.core.init.apply(nplModalObj, {});
        } else if (typeof param === 'object') {
            return nplModalObj.core.init.apply(nplModalObj, param);
        } else {
            var method  = getDescendantProp(nplModalObj.core.methods, param);
            if (method) {
                return method.apply(nplModalObj.core.methods, Array.prototype.slice.call(arguments, 1));
            } else {
                console.error('Method ' + param + ' does not exist on jQuery.nplModal');
            }
        }
    };

})(jQuery);

jQuery(document).ready(function($) {
    $.nplModal();
});

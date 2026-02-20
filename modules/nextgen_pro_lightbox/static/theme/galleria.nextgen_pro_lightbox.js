(function($) {

    // In case this theme is enqueued before Galleria.js is loaded we
    // will periodically check if Galleria exists before calling
    // Galleria.addTheme()
    var interval = setInterval(function() {
        if (typeof window.Galleria !== 'undefined') {
            clearInterval(interval);
            add_nggpl_theme();
        }
    }, 10);

    var add_nggpl_theme = function() {
        Galleria.addTheme({
            name: 'nextgen_pro_lightbox',
            author: 'Imagely',
            version: 2.0,
            defaults: {
                debug: false,
                responsive: true,
                carousel: true,
                thumbnails: 'lazy',
                fullscreen: false,
                trueFullscreen: false,
                fullscreenDoubleTap: false,
                maxScaleRatio: 1
            },
            init: function(options) {
                var self = this;

                Galleria.requires(1.41, 'This version of the NextGEN Pro Lightbox theme requires Galleria 1.4.1 or later');

                $.nplModal('log', 'theme initialization', {
                    options: options
                });

                // Some objects have aip variables - animation-in-progress - and are used to make jQuery animations smoother
                var methods = {
                    sidebar: {
                        _is_open: false,
                        _type: '',
                        is_open: function(state) {
                            if (typeof state !== 'undefined') {
                                this._is_open = state;
                            } else {
                                return this._is_open;
                            }
                        },
                        toggle: function(type) {
                            $.nplModal('log', 'theme sidebar.toggle()', {
                                type: type
                            });
                            if (this.is_open() && type === this.get_type()) {
                                this.close();
                            } else {
                                this.open(type);
                            }
                        },
                        open: function(type) {
                            $.nplModal('log', 'theme sidebar.open()', {
                                type: type
                            });

                            $('#npl_wrapper').addClass('npl-sidebar-open npl-sidebar-overlay-open');

                            this.render(type);
                            methods.sidebar.is_open(true);

                            var state = $.nplModal('get_state');
                            state.sidebar = type;

                            $.nplModal(
                                'router.navigate',
                                $.nplModal('get_setting', 'router_slug')
                                + '/' + state.slug
                                + '/' + state.image_id
                                + '/' + type,
                                true
                            );

                            self.resize();
                            self.trigger('npl_sidebar_opened');
                        },
                        close: function() {
                            $.nplModal('log', 'theme sidebar.close()');

                            $('#npl_wrapper').removeClass('npl-sidebar-open npl-sidebar-overlay-open');

                            methods.sidebar.is_open(false);

                            var state = $.nplModal('get_state');
                            state.sidebar = null;
                            $.nplModal(
                                'router.navigate',
                                $.nplModal('get_setting', 'router_slug')
                                + '/' + state.slug
                                + '/' + state.image_id,
                                true
                            );

                            self.resize();
                            self.trigger('npl_sidebar_closed');
                        },
                        render: function(type) {
                            $.nplModal('log', 'theme sidebar.render()', {
                                type: type
                            });

                            // switching to another sidebar type; flash the overlay
                            if (type !== this.get_type()) {
                                this._type = type;
                                $.nplModal('get_state').sidebar = type;
                            }

                            methods.sidebars[type].render(methods.galleria.get_current_image_id());
                            methods.sidebars[type].init();
                        },
                        get_type: function() {
                            return this._type;
                        },
                        events: {
                            bind: function() {
                                $.nplModal('log', 'theme sidebar.events.bind()');
                                $('#npl_content').on('npl_sidebar_rendered', this.rendered);
                                $('#npl_sidebar_toggle').on('click', function() {
                                    methods.sidebar.close();
                                });
                            },
                            rendered: function() {
                                $.nplModal('log', 'theme sidebar.events.rendered()');

                                // This is hidden except for narrow viewports by default, allows phone users to remember what photo they are 'on'
                                $('#nggpl-sidebar-thumbnail-img').attr('src', self.getData(self.getIndex()).image);
                            }
                        }
                    },
                    sidebars: {
                        datacache: {
                            _cache: [],
                            _current_image_ndx: null,
                            _last_image_ndx: null,

                            events: {
                                bind: function() {
                                    $.nplModal('log', 'theme sidebars.datacache.events.bind()');
                                    self.bind('loadstart', this.loadstart);
                                },

                                // Used to track which direction users are navigating in so we can intelligently
                                // pre-load sidebar data in advance of the image being selected
                                loadstart: function() {
                                    $.nplModal('log', 'theme sidebars.datacache.events.image()');
                                    // Initial value at startup
                                    if (methods.sidebars.datacache._current_image_ndx === null) {
                                        methods.sidebars.datacache._last_image_ndx = methods.sidebars.datacache._current_image_ndx = self.getIndex();
                                    } else {
                                        // Store the current index and shift the old value
                                        methods.sidebars.datacache._last_image_ndx = methods.sidebars.datacache._current_image_ndx;
                                        methods.sidebars.datacache._current_image_ndx = self.getIndex();
                                    }
                                }
                            },

                            get_carousel_navigation_direction: function() {
                                if (this._current_image_ndx === this._last_image_ndx) {
                                    return 'same';
                                } else {
                                    if (this._last_image_ndx === 0 && this._current_image_ndx === (self.getDataLength() - 1)) {
                                        return 'left';
                                    } else if (this._current_image_ndx === 0 && this._last_image_ndx === (self.getDataLength() -1)) {
                                        return 'right';
                                    } else {
                                        return (this._current_image_ndx < this._last_image_ndx) ? 'left' : 'right';
                                    }
                                }
                            },

                            // because the .length operator isn't accurate
                            get_cache_size: function() {
                                var size = this._cache.length;

                                $.nplModal('log', 'theme sidebars.comments.get_cache_size()', {
                                    result: size
                                });

                                return size;
                            },

                            // returns the image-id field of the first preceeding image found whose comments aren't cached
                            get_prev_uncached_image_id: function(id, step) {
                                step = step || 0;
                                var prev_image_id = self.getData(self.getPrev(methods.galleria.get_index_from_id(id) - step)).image_id;
                                if (this._cache[prev_image_id] && this.get_cache_size() < self.getDataLength()) {
                                    prev_image_id = this.get_prev_uncached_image_id(prev_image_id);
                                }

                                $.nplModal('log', 'theme sidebars.comments.get_prev_uncached_image_id()', {
                                    id: id,
                                    result: prev_image_id
                                });

                                return prev_image_id;
                            },

                            // returns the image-id field of the first following image found whose comments aren't cached
                            get_next_uncached_image_id: function(id, step) {
                                step = step || 0;
                                var next_image_id = self.getData(self.getNext(methods.galleria.get_index_from_id(id) + step)).image_id;
                                if (this._cache[next_image_id] && this.get_cache_size() < self.getDataLength()) {
                                    next_image_id = this.get_next_uncached_image_id(next_image_id);
                                }

                                $.nplModal('log', 'theme sidebars.comments.get_next_uncached_image_id()', {
                                    id: id,
                                    result: next_image_id
                                });

                                return next_image_id;
                            },

                            // expanded request method: adds first pre-ceding and following uncached id to the request
                            expanded_request: function(id, finished) {
                                $.nplModal('log', 'theme sidebars.comments.expanded_request()', {
                                    id: id
                                });

                                var id_array = (id instanceof Array) ? id : id.toString().split(',');

                                // a single ID was requested, so inject some extras so they can be cached in advance
                                if (id_array.length === 1) {
                                    var key = id_array[0];
                                    var prev = this.get_prev_uncached_image_id(key);
                                    var next = this.get_next_uncached_image_id(key);
                                    if (!this._cache[prev]) {
                                        this._cache[prev] = 'Placeholder stub';
                                        id_array.unshift(prev);
                                    }
                                    if (!this._cache[next] && prev !== next && id !== next) {
                                        this._cache[next] = 'Placeholder stub';
                                        id_array.push(next);
                                    }
                                }

                                id_array = this.sort_uniq(id_array);
                                this.request(id_array, 0, finished);
                            },

                            // handles the HTTP request to load comments & cache the results
                            request: function(id, page, finished) {
                                $.nplModal('log', 'theme sidebars.comments.request()', {
                                    id: id,
                                    page: page
                                });

                                var myself = this; // self is taken
                                var postdata = {
                                    action: 'pro_lightbox_get_sidebar_data',
                                    type:   'image',
                                    page:    page,
                                    id:      id.join(','),
                                    from:    window.parent.location.toString()
                                };
                                if ($.nplModal('get_setting', 'lang', false)) {
                                    postdata.lang = $.nplModal('get_setting', 'lang');
                                }

                                $.post(photocrati_ajax.url, postdata, function(data) {
                                    $.nplModal('log', 'theme sidebars.comments.request() response', {
                                        response: data
                                    });

                                    if (typeof(data) !== 'object') {
                                        data = JSON.parse(data);
                                    }
                                    for (var ndx in data) {
                                        myself._cache[ndx] = data[ndx];
                                    }
                                    if (typeof finished === 'function') {
                                        finished(data);
                                    }
                                });
                            },

                            // find and load the next un-cached results
                            load_more: function(id) {
                                $.nplModal('log', 'theme sidebars.comments.load_more()', {
                                    id: id
                                });

                                if (methods.nplModal.is_nextgen_gallery()
                                    &&  $.nplModal('get_setting', 'enable_routing', false)
                                    &&  $.nplModal('get_setting', 'enable_comments', false)) {
                                    var precache_ids = [];

                                    var direction = methods.sidebars.datacache.get_carousel_navigation_direction();
                                    var count = 3;

                                    // Retrieve X images in the same direction the user is navigating
                                    for (var i = 0; i <= count; i++) {
                                        if (direction === 'left') {
                                            var uncached_id = this.get_prev_uncached_image_id(id, i);
                                        } else {
                                            var uncached_id = this.get_next_uncached_image_id(id, i);
                                        }

                                        // Push the recently found IDs to this._cache so they can't be included again
                                        if (!this._cache[uncached_id]) {
                                            this._cache[uncached_id] = 'Placeholder stub';
                                            precache_ids.push(uncached_id);
                                        }
                                    }

                                    precache_ids = this.sort_uniq(precache_ids);

                                    if (precache_ids.length !== 0) {
                                        this.expanded_request(precache_ids);
                                    }
                                }
                            },

                            sort_uniq: function(ids) {
                                return ids.sort().filter(function(item, pos, ary) {
                                    return !pos || item !== ary[pos - 1];
                                })
                            },

                            get_cache_by_id: function(id) {
                                return this._cache[id];
                            }
                        },
                        comments: {
                            // called after render(), initialize logic & events
                            init: function() {
                                $.nplModal('log', 'theme sidebars.comments.init()');

                                if ($('#nggpl-comments-wrapper').length !== 1) { return; }

                                // jquery's .val() fails with hidden fields
                                var origin_url = document.getElementById("ngg_comment_origin_url");
                                if (typeof origin_url !== 'undefined' && origin_url !== null) {
                                    origin_url.value = window.location.href.toString();
                                }

                                // It is much faster to change the target attribute globally here than through WP hooks
                                $('#npl_sidebar').find('a').each(function() {
                                    if ($(this).attr('id') === 'nggpl-comment-logout') {
                                        $(this).attr('href', $(this).attr('href') + '?redirect_to=' + window.location.toString());
                                    } else {
                                        $(this).attr('target', '_blank');
                                    }
                                });

                                $('#nggpl-respond-form').on('submit', function (event) {
                                    event.preventDefault();
                                    var commentstatus = $('#nggpl-comment-status');
                                    $('#npl_wrapper').addClass('npl-sidebar-overlay-open');
                                    $.ajax({
                                        type: $(this).attr('method'),
                                        url: $(this).attr('action'),
                                        data: $(this).serialize(),
                                        dataType: 'json',
                                        success: function (data) {
                                            if (data.success === true) {
                                                $('#nggpl-comment').val('');
                                                $('#nggpl-comments-title').val('');
                                                var image_id = methods.galleria.get_current_image_id();
                                                methods.sidebars.datacache.expanded_request(image_id, function() {
                                                    methods.sidebar.render(methods.sidebars.comments.get_type(), image_id);
                                                });
                                            } else {
                                                commentstatus.addClass('error')
                                                    .html(data);
                                                $('#npl_wrapper').removeClass('npl-sidebar-overlay-open');
                                            }
                                        },
                                        complete: function (jqXHR, status) {
                                        },
                                        error: function (jqXHR) {
                                            commentstatus.addClass('error').html(jqXHR.responseText);
                                            $('#npl_wrapper').removeClass('npl-sidebar-overlay-open');
                                        }
                                    });
                                });

                                $("#npl_sidebar .nggpl-button, #nggpl-comment-form-wrapper input[type='submit']").each(function() {
                                    var $this = $(this);
                                    $this.css({
                                        'color': $.nplModal('get_setting', 'sidebar_button_color'),
                                        'background-color': $.nplModal('get_setting', 'sidebar_button_background')
                                    });
                                });

                                // handles 'Reply' links
                                $('.nggpl-reply-to-comment').on('click', function(event) {
                                    event.preventDefault();
                                    // all that wordpress needs is the comment_parent value
                                    $('#nggpl-comment_parent').val($(this).data('comment-id'));
                                    $('#nggpl-comment-reply-status').removeClass('hidden');

                                    // IE has issues setting focus on invisible elements. Be wary
                                    $('#nggpl-commentform').find(':input').filter(':visible').first().trigger('focus');
                                    $('#npl_sidebar').scrollTop($('#nggpl-comments-bottom').offset().top);
                                });

                                // handles "cancel reply" link
                                $('#nggpl-comment-reply-status a').on('click', function(event) {
                                    event.preventDefault();
                                    $('#nggpl-comment_parent').val('0');
                                    $('#nggpl-comment-reply-status').addClass('hidden');
                                });

                                // handles comment AJAX pagination
                                $('#nggpl-comment-nav-below a').on('click', function(event) {
                                    event.preventDefault();
                                    $('#npl_wrapper').addClass('npl-sidebar-overlay-open');
                                    var page_id = $(this).data('page-id');
                                    methods.sidebars.datacache.request(
                                        [methods.galleria.get_current_image_id()],
                                        page_id,
                                        function() {
                                            methods.sidebar.render('comments', methods.galleria.get_current_image_id());
                                        }
                                    );
                                });

                                self.trigger('npl_sidebar_rendered');

                                if (methods.nplModal.is_nextgen_gallery()
                                    &&  $.nplModal('get_setting', 'enable_routing', false)
                                    &&  $.nplModal('get_setting', 'enable_sharing', false)) {
                                    $('#nggpl-comments-image-share-icons').removeClass('disabled');
                                    methods.share_icons.create(
                                        '#nggpl-comments-image-share-icons',
                                        methods.icons.get_overlay_color()
                                    );
                                }

                                methods.sidebars.comments.wait_for_image_to_reveal();
                            },

                            wait_for_image_to_reveal: function() {
                                if (!methods.sidebars.comments.events.between_loadstart_and_finish) {
                                    $('#npl_wrapper').removeClass('npl-sidebar-overlay-open');
                                } else {
                                    methods.sidebars.comments.events.waiting_for_image_to_load = true;
                                }
                            },

                            // returns the display area content from cache
                            render: function(id) {
                                $.nplModal('log', 'theme sidebars.comments.render()', {
                                    id: id
                                });

                                var cache = methods.sidebars.datacache.get_cache_by_id(id);

                                if (!cache) {
                                    $('#npl_wrapper').addClass('npl-sidebar-overlay-open');
                                    methods.sidebars.datacache.expanded_request(id, function() {
                                        cache = methods.sidebars.datacache.get_cache_by_id(id);
                                        $('#npl_sidebar').html(cache.comments['rendered_view']);
                                        methods.sidebars.comments.init();
                                    });
                                } else if (cache === 'Placeholder stub') {
                                    $('#npl_wrapper').addClass('npl-sidebar-overlay-open');
                                    var interval = setInterval(function() {
                                        cache = methods.sidebars.datacache.get_cache_by_id(id);
                                        if (cache !== 'Placeholder stub') {
                                            $('#npl_sidebar').html(cache.comments['rendered_view']);
                                            methods.sidebars.comments.init();
                                            clearInterval(interval);
                                        }
                                    }, 250);
                                } else {
                                    $('#npl_sidebar').html(cache.comments['rendered_view']);
                                    $('#npl_wrapper').removeClass('npl-sidebar-overlay-open');
                                }
                            },

                            get_type: function() {
                                return 'comments';
                            },

                            events: {
                                waiting_for_image_to_load: false,
                                between_loadstart_and_finish: false,

                                bind: function() {
                                    $.nplModal('log', 'theme sidebars.comments.events.bind()');

                                    if (methods.nplModal.is_nextgen_gallery()
                                    &&  $.nplModal('get_setting', 'enable_routing', false)
                                    &&  $.nplModal('get_setting', 'enable_comments', false)
                                    &&  !methods.nplModal.is_random_source()) {
                                        self.bind('loadstart', this.loadstart);
                                        self.bind('npl_init', this.npl_init);
                                        self.bind('npl_init_keys', this.npl_init_keys);
                                        self.bind('image', this.image);
                                    }
                                },

                                npl_init: function() {
                                    $.nplModal('log', 'theme sidebars.comments.events.npl_init()');

                                    // Adds comment toolbar button
                                    var comment_button = $('<i/>')
                                        .addClass('nggpl-toolbar-button-comment fa fa-comment')
                                        .attr({'title': $.nplModal('get_setting', 'i18n').toggle_social_sidebar});
                                    methods.thumbnails.register_button(
                                        comment_button,
                                        function(event) {
                                            methods.sidebar.toggle(methods.sidebars.comments.get_type());
                                            event.preventDefault();
                                        }
                                    );
                                },

                                image: function() {
                                    methods.sidebars.comments.events.between_loadstart_and_finish = false;

                                    if (methods.sidebars.comments.events.waiting_for_image_to_load) {
                                        methods.sidebars.comments.events.waiting_for_image_to_load = false;
                                        $('#npl_wrapper').removeClass('npl-sidebar-overlay-open');
                                    }
                                },

                                _loadstart_run_once: false,
                                loadstart: function(e) {
                                    $.nplModal('log', 'theme sidebars.comments.events.npl_init_complete()');

                                    methods.sidebars.comments.events.between_loadstart_and_finish = true;

                                    if (methods.nplModal.is_nextgen_gallery() && !methods.sidebars.comments._loadstart_run_once) {
                                        // possibly display the comments sidebar at startup
                                        if (($.nplModal('get_state').sidebar && $.nplModal('get_state').sidebar === methods.sidebars.comments.get_type())
                                        ||  $.nplModal('get_setting', 'display_comments')) {
                                            methods.sidebar.open(methods.sidebars.comments.get_type());
                                        }
                                        methods.sidebars.comments._loadstart_run_once = true;
                                    } else if (methods.nplModal.is_nextgen_gallery()
                                           &&  $.nplModal('get_setting', 'enable_routing', false)
                                           &&  $.nplModal('get_setting', 'enable_comments', false)) {
                                            // updates the sidebar
                                            methods.sidebars.datacache.load_more(methods.galleria.get_current_image_id());
                                            if (methods.sidebar.is_open() && methods.sidebar.get_type() === methods.sidebars.comments.get_type()) {
                                                methods.sidebar.render(methods.sidebars.comments.get_type());
                                            }
                                    }
                                },

                                npl_init_keys: function() {
                                    $.nplModal('log', 'theme sidebars.comments.events.npl_init_keys()');
                                    var input_types = methods.galleria.get_keybinding_exclude_list();
                                    if (methods.nplModal.is_nextgen_gallery()
                                    &&  $.nplModal('get_setting', 'enable_routing', false)
                                    &&  $.nplModal('get_setting', 'enable_comments', false))
                                    {
                                        self.attachKeyboard({
                                            // spacebar
                                            32: function () {
                                                if (!$(document.activeElement).is(input_types)) {
                                                    methods.sidebar.toggle(methods.sidebars.comments.get_type());
                                                }
                                            }
                                        });
                                    }
                                }
                            }
                        }
                    },
                    thumbnails: {
                        is_open: function() {
                            return !$('#npl_wrapper').hasClass('npl-carousel-closed');
                        },
                        toggle: function() {
                            $.nplModal('log', 'theme thumbnails.toggle()');

                            if (this.is_open()) {
                                this.close();
                            } else {
                                this.open();
                            }
                        },
                        open: function() {
                            $.nplModal('log', 'theme thumbnails.open()');

                            $('#npl_wrapper').removeClass('npl-carousel-closed');
                            $('.galleria-dock-toggle-container i').toggleClass('fa-angle-up fa-angle-down');
                            $('.galleria-dock-toggle-container').find('[data-fa-i2svg]').toggleClass('fa-angle-up fa-angle-down');
                            $(window).trigger('resize');
                        },
                        close: function() {
                            $.nplModal('log', 'theme thumbnails.close()');

                            $('#npl_wrapper').addClass('npl-carousel-closed');
                            $('.galleria-dock-toggle-container i').toggleClass('fa-angle-up fa-angle-down');
                            $('.galleria-dock-toggle-container').find('[data-fa-i2svg]').toggleClass('fa-angle-up fa-angle-down');
                            $(window).trigger('resize');
                        },
                        adjust_container: function() {
                            $.nplModal('log', 'theme thumbnails.adjust_container()');
                            // Important: this must be delayed for a short while after Galleria.resize() is invoked to allow
                            // Galleria to finish repositioning itself and prevent possible race scenario.
                            setTimeout(function() {
                                var available_width = self.$('thumbnails-container').width()
                                    - self.$('nextgen-buttons').width()
                                    - self.$('thumb-nav-left').width()
                                    - self.$('thumb-nav-right').width();
                                if (available_width <= (70 * 4)) {
                                    self.$('container').addClass('nggpl-carousel-too-small');
                                } else {
                                    self.$('container').removeClass('nggpl-carousel-too-small');
                                }
                            }, 250);
                        },
                        _buttons: [],
                        register_button: function(button, callback) {
                            $.nplModal('log', 'theme thumbnails.register_button()', {
                                button: button
                            });

                            var wrapper = $('<span class="nggpl-button nggpl-toolbar-button"/>');
                            if ($.nplModal('get_setting', 'icon_background_enabled', false)
                            &&  $.nplModal('get_setting', 'icon_background_rounded', false)) {
                                wrapper.addClass('nggpl-rounded');
                            }
                            wrapper.html(button);
                            this._buttons.push(wrapper);
                            $(self._dom.stage).append(wrapper);

                            wrapper.on('click', function(event) {
                                callback(event)
                            });
                        },
                        get_registered_buttons: function() {
                            $.nplModal('log', 'theme thumbnails.get_registered_buttons', {
                                buttons: this._buttons
                            });
                            return this._buttons;
                        },
                        events: {
                            bind: function() {
                                $.nplModal('log', 'theme thumbnails.events.bind()');

                                self.bind('loadfinish', methods.thumbnails.adjust_container);
                                self.bind('loadfinish', this.loadfinish);
                                self.bind('npl_sidebar_opened', methods.thumbnails.adjust_container);
                                self.bind('npl_sidebar_closed', methods.thumbnails.adjust_container);
                                self.bind('npl_init', this.npl_init);
                                self.bind('npl_init_complete', this.npl_init_complete);

                                // This prevents multiple handlers from running simultaneously, and multiple
                                // events pile up when listening to 'resize' - only the last handler is invoked
                                // if 250ms pass after the last triggering.
                                methods.galleria.bind_once('resize', methods.thumbnails.adjust_container, 250);
                            },
                            npl_init: function() {
                                $.nplModal('log', 'theme thumbnails.events.npl_init()');

                                if ('numbers' === options.thumbnails) {
                                    self.$('container').addClass('nggpl-carousel-numbers');
                                    self.$('thumbnails-list').css('color', methods.icons.get_color());

                                    // Because numeric entries have a different width than at startup we
                                    // must manually invoke updateCarousel() here so that we don't start
                                    // with the carousel in the wrong position
                                    self.updateCarousel();

                                } else if ('lazy' === options.thumbnails) {
                                    // load all thumbnails, 10 at a time, until all have been retrieved
                                    self.lazyLoadChunks(10);
                                }

                                self.$('thumbnails-container').css({background: $.nplModal('get_setting', 'carousel_background_color')});

                                // create carousel next/prev links
                                var next_thumbs_button = $('<i/>')
                                    .addClass('fa fa-angle-right')
                                    .css({color: methods.icons.get_color()});
                                var prev_thumbs_button = $('<i/>')
                                    .addClass('fa fa-angle-left')
                                    .css({color: methods.icons.get_color()});
                                $(self._dom.stage).append(next_thumbs_button);
                                $(self._dom.stage).append(prev_thumbs_button);
                                self.append({'thumb-nav-left': prev_thumbs_button});
                                self.append({'thumb-nav-right': next_thumbs_button});

                                // Create thumbnails-container toggle button
                                self.addElement('dock-toggle-container');
                                var dock_toggle_container = self.$('dock-toggle-container')
                                    .css({background: $.nplModal('get_setting', 'carousel_background_color')});
                                var dock_toggle_button = $('<i/>').addClass('fa fa-angle-down')
                                    .css({color: $.nplModal('get_setting', 'carousel_text_color')});

                                // the container was marked hidden by the parent wrapper, adjust the angle-icon accordingly
                                if (!methods.thumbnails.is_open()) {
                                    dock_toggle_button.toggleClass('fa-angle-up fa-angle-down');
                                }

                                $(self._dom.stage).append(dock_toggle_button);
                                self.append({'dock-toggle-container': dock_toggle_button});
                                dock_toggle_container.on('click', self.proxy(function() {
                                    methods.thumbnails.toggle();
                                }));

                                // Add playback controls
                                var play_button = $('<i/>')
                                    .addClass('nggpl-toolbar-button-play fa fa-play')
                                    .attr({'title': $.nplModal('get_setting', 'i18n').play_pause});
                                if (this._playing) {
                                    play_button.removeClass('fa-play').addClass('fa-pause');
                                }
                                methods.thumbnails.register_button(
                                    play_button,
                                    function(event) {
                                        event.preventDefault();
                                        self.playToggle();
                                        var $target = $(event.currentTarget);
                                        $target.find('i').toggleClass('fa-play fa-pause');
                                        $target.find('[data-fa-i2svg]').toggleClass('fa-play fa-pause');
                                    }
                                );

                                // add info controls; handles animation of both the info & dock-toggle-container divs
                                var info_button = $('<i/>')
                                    .addClass('nggpl-toolbar-button-info fa fa-info')
                                    .attr({'title': $.nplModal('get_setting', 'i18n').toggle_image_info});
                                methods.thumbnails.register_button(
                                    info_button,
                                    function(event) {
                                        event.preventDefault();
                                        methods.info.toggle();
                                    }
                                );
                            },
                            npl_init_complete: function() {
                                $.nplModal('log', 'theme thumbnails.events.npl_init_complete()');

                                var display_buttons = methods.thumbnails.get_registered_buttons();
                                // assign all of our buttons a (possibly custom) color
                                for (i = 0; i <= (display_buttons.length - 1); i++) {
                                    display_buttons[i].css({
                                        'color': methods.icons.get_color(),
                                        'background-color': methods.icons.get_background()
                                    });
                                }
                                self.addElement('nextgen-buttons');
                                self.append({'nextgen-buttons': display_buttons});
                                self.prependChild('thumbnails-container', 'nextgen-buttons');
                                self.prependChild('info', 'dock-toggle-container');

                                if (!$.nplModal('mobile.browser.any')) {
                                    self.addIdleState(self.get('dock-toggle-button'), {opacity: 0});
                                }
                            },
                            // Hide the parent lightbox's spinner
                            _loadfinish_ran_once: false,
                            loadfinish: function() {
                                $.nplModal('log', 'theme thumbnails.events.loadfinish()');

                                if (methods.thumbnails.events._loadfinish_ran_once) {
                                    return;
                                }

                                $('#npl_spinner_container').addClass('hidden');
                                methods.thumbnails.events._loadfinish_ran_once = true;
                            }
                        }
                    },
                    info: {
                        is_open: function() {
                            return $('#npl_wrapper').hasClass('npl-info-open');
                        },

                        toggle: function() {
                            $.nplModal('log', 'theme info.toggle()');

                            if (this.is_open()) {
                                this.close();
                            } else {
                                this.open();
                            }
                        },

                        open: function() {
                            $.nplModal('log', 'theme info.open()');

                            $('#npl_wrapper').addClass('npl-info-open');
                        },

                        close: function() {
                            $.nplModal('log', 'theme info.close()');

                            $('#npl_wrapper').removeClass('npl-info-open');
                        },

                        events: {
                            bind: function() {
                                $.nplModal('log', 'theme info.events.bind()');

                                self.bind('npl_init', this.npl_init);
                                self.bind('loadfinish', this.loadfinish);
                            },

                            npl_init: function() {
                                $.nplModal('log', 'theme info.events.npl_init()');

                                var needsnewparent = self.$('info').detach();
                                self.$('thumbnails-container').append(needsnewparent);

                                // Add social share icons to the infobar. ID is important, sidebars could add their own icons
                                self.prependChild(
                                    'info-text',
                                    $('<div/>')
                                        .attr('id', 'galleria-image-share-icons')
                                        .attr('class', 'galleria-image-share-icons')
                                );
                                if (carousel_text_color = $.nplModal('get_setting', 'carousel_text_color')) {
                                    self.$('info-title').css({color: carousel_text_color});
                                    self.$('info-description').css({color: carousel_text_color});
                                }
                                self.$('info, info-text, info-title, info-description').css({background: $.nplModal('get_setting', 'carousel_background_color')});
                            },

                            _loadfinish_ran_once: false,
                            loadfinish: function() {
                                $.nplModal('log', 'theme info.events.loadfinish()');
                                // anchors in our image captions / descriptions must have target=_blank
                                self.$('info-title, info-description').find('a').each(function() {
                                    $(this).attr('target', '_blank');
                                    $(this).css('color', methods.icons.get_color());
                                });

                                methods.info.events._loadfinish_ran_once = true;
                            }
                        }
                    },
                    icons: {
                        get_color: function() {
                            return $.nplModal('get_setting', 'icon_color');
                        },
                        get_background: function() {
                            var iconcolor = $.nplModal('get_setting', 'carousel_background_color');
                            if ($.nplModal('get_setting', 'icon_background_enabled', false)) {
                                iconcolor = $.nplModal('get_setting', 'icon_background');
                            }
                            return iconcolor;
                        },
                        get_overlay_color: function() {
                            return $.nplModal('get_setting', 'overlay_icon_color');
                        }
                    },
                    nplModal: {
                        close: function() {
                            $.nplModal('close_modal');
                        },
                        is_random_source: function() {
                            var gallery = $.nplModal('get_gallery_from_id', $.nplModal('get_state').gallery_id);
                            var result = ($.inArray(gallery.source, ['random', 'random_images']) !== -1);

                            $.nplModal('log', 'theme nplModal.is_random_source()', {
                                result: result
                            });

                            return result;
                        },
                        is_nextgen_gallery: function() {
                            var retval = $.nplModal('get_state').gallery_id !== '!';

                            $.nplModal('log', 'theme nplModal.is_nextgen_gallery()', {
                                result: retval
                            });

                            return retval;
                        },
                        is_nextgen_widget: function() {
                            var retval = false;
                            var gallery = $.nplModal('get_gallery_from_id', $.nplModal('get_state').gallery_id);
                            var slug = gallery.slug;
                            if (slug) {
                                retval = slug.indexOf('widget-ngg-images') !== -1;
                            }

                            $.nplModal('log', 'theme nplModal.is_nextgen_widget()', {
                                result: retval
                            });

                            return retval;
                        },
                        events: {
                            bind: function() {
                                $.nplModal('log', 'theme nplModal.bind()');

                                if (Galleria.IPAD || Galleria.IPHONE) {
                                    self.$('container').addClass('nggpl-ios-browser');
                                }

                                // update the parent url when a new image has been chosen or the slideshow advances
                                if (methods.nplModal.is_nextgen_gallery()) {
                                    self.bind('image', this.image);
                                }

                                // handle updates to the current url once opened; most likely due to the back/forward button
                                if (methods.nplModal.is_nextgen_gallery()
                                &&  $.nplModal('get_setting', 'enable_routing', false)
                                &&  $.nplModal('get_setting', 'enable_comments', false)) {
                                    $('#npl_content').on('npl_url_handler', this.unhandled_url_change);
                                }

                                if ($.nplModal('get_setting', 'protect_images', false)) {
                                    $('.galleria-image').on('dragstart', function(event) {
                                        event.preventDefault();
                                    });
                                    self.bind('npl_init', this.npl_init);
                                    self.bind('npl_init_complete', this.npl_init_complete);
                                }

                                jQuery('#npl_content').on('npl_closing', this.closing);
                            },
                            npl_init: function() {
                                $.nplModal('log', 'theme nplModal.events.npl_init()');

                                self.addElement('image-protection');
                                document.oncontextmenu = function(event) {
                                    event = event || window.event;
                                    event.preventDefault();
                                };
                            },
                            npl_init_complete: function() {
                                $.nplModal('log', 'theme nplModal.events.npl_init_complete()');
                                self.prependChild('images', 'image-protection');
                            },
                            _image_ran_once: false,
                            image: function() {
                                $.nplModal('log', 'theme nplModal.events.image()');
                                if (methods.nplModal.events._image_ran_once) {
                                    if (!methods.nplModal.is_random_source()) {
                                        var image_id = self.getData(self.getIndex()).image_id;
                                        var sidebar_string = methods.sidebar.is_open() ? '/' + methods.sidebar.get_type() : '';
                                        var state = $.nplModal('get_state');
                                        state.image_id = image_id;
                                        $.nplModal('set_state', state);
                                        if ($.nplModal('get_setting', 'enable_routing', false)) {
                                            $.nplModal(
                                                'router.navigate',
                                                $.nplModal('get_setting', 'router_slug') + '/' + $.nplModal('get_state').slug + '/' + image_id + sidebar_string,
                                                true
                                            );
                                        }
                                    }
                                }
                                methods.nplModal.events._image_ran_once = true;
                            },
                            unhandled_url_change: function(event, state) {
                                $.nplModal('log', 'theme nplModal.unhandled_url_change()', {
                                    event: event,
                                    state: state
                                });
                                for (var i = 0; i <= (self.getDataLength() - 1); i++) {
                                    if (state.image_id === self.getData(i).image_id) {
                                        self.show(i);
                                    }
                                }
                                if (methods.sidebar.is_open() && state.sidebar == null) {
                                    methods.sidebar.close();
                                } else if (!methods.sidebar.is_open()
                                       &&  typeof state.sidebar !== 'undefined'
                                       &&  state.sidebar !== null) {
                                    methods.sidebar.open(state.sidebar);
                                }
                            },
                            closing: function() {
                                // without this our bound keys will continue to activate even aften
                                // Galleria.destroy() has been called and the container div emptied
                                self.detachKeyboard();
                            }
                        }
                    },
                    galleria: {
                        bind_once: function(event, callback, timeout) {
                            var timer_id = undefined;
                            window.addEventListener(event, function() {
                                if (typeof timer_id === 'undefined') {
                                    clearTimeout(timer_id);
                                    timer_id = undefined;
                                }
                                timer_id = setTimeout(function() {
                                    timer_id = undefined;
                                    callback();
                                }, timeout);
                            });
                        },
                        get_displayed_gallery_setting: function(name, def) {
                            var tmp = '';
                            var gallery = $.nplModal('get_gallery_from_id', $.nplModal('get_state').gallery_id);
                            if (gallery && typeof gallery.display_settings[name] !== 'undefined') {
                                tmp = gallery.display_settings[name];
                            } else {
                                tmp = def;
                            }
                            if (tmp === '1') tmp = true;
                            if (tmp === '0') tmp = false;
                            if (tmp === 1) tmp = true;
                            if (tmp === 0) tmp = false;

                            $.nplModal('log', 'theme galleria.get_displayed_gallery_setting()', {
                                name: name,
                                result: tmp
                            });

                            return tmp;
                        },
                        get_keybinding_exclude_list: function() {
                            return 'textarea, input';
                        },
                        get_current_image_id: function() {
                            return self.getData(self.getIndex()).image_id;
                        },
                        // returns the Galleria image-index based on the provided image id
                        get_index_from_id: function(id) {
                            var retval = null;
                            for (var i = 0; i <= (self.getDataLength() - 1); i++) {
                                if (id === self.getData(i).image_id) {
                                    retval = i;
                                }
                            }
                            return retval;
                        },
                        events: {
                            bind: function() {
                                $.nplModal('log', 'theme galleria.events.bind()');
                                self.bind('touchmove', this.touchmove);
                                self.bind('npl_init', this.npl_init);
                                self.bind('npl_init_keys', this.npl_init_keys);
                                self.bind('npl_init_complete', this.npl_init_complete);
                                self.bind('loadstart', this.loadstart);
                                self.bind('loadfinish', this.loadfinish);
                                $(window).on('resize orientationchange', this.browserchanged);
                            },
                            browserchanged: function() {
                                $.nplModal('log', 'theme galleria.events.browserchanged()');
                                setTimeout(function() {
                                    self.rescale();
                                }, 200);
                            },
                            npl_init: function() {
                                $.nplModal('log', 'theme galleria.events.npl_init()');
                                // for some reason this isn't an option that can be passed at startup
                                self.setPlaytime(($.nplModal('get_setting', 'slideshow_speed', 5) * 1000));

                                // Create next / back links
                                var next_image_button = $('<i/>')
                                    .addClass('fa fa-angle-right')
                                    .css({color: methods.icons.get_overlay_color()});
                                var prev_image_button = $('<i/>')
                                    .addClass('fa fa-angle-left')
                                    .css({color: methods.icons.get_overlay_color()});
                                $(self._dom.stage).append(next_image_button);
                                $(self._dom.stage).append(prev_image_button);
                                self.append({'image-nav-left': prev_image_button});
                                self.append({'image-nav-right': next_image_button});

                                self.$('counter').css({color: methods.icons.get_overlay_color()});
                            },
                            npl_init_keys: function() {
                                $.nplModal('log', 'theme galleria.events.npl_init_keys()');
                                var input_types = methods.galleria.get_keybinding_exclude_list();
                                self.attachKeyboard({
                                    left: function() {
                                        if (!$(document.activeElement).is(input_types)) {
                                            this.prev();
                                        }
                                    },
                                    right: function() {
                                        if (!$(document.activeElement).is(input_types)) {
                                            this.next();
                                        }
                                    },
                                    down: function() {
                                        if (!$(document.activeElement).is(input_types)) {
                                            methods.thumbnails.toggle();
                                        }
                                    },
                                    up: function() {
                                        if (!$(document.activeElement).is(input_types)) {
                                            methods.info.toggle();
                                        }
                                    },
                                    // escape key
                                    27: function(event) {
                                        event.preventDefault();
                                        methods.nplModal.close();
                                    }
                                });
                            },
                            npl_init_complete: function() {
                                $.nplModal('log', 'theme galleria.events.npl_init_complete()');
                                if (!$.nplModal('mobile.browser.any')) {
                                    self.addIdleState(self.get('counter'),            {opacity: 0});
                                    self.addIdleState(self.get('image-nav-left'),     {opacity: 0});
                                    self.addIdleState(self.get('image-nav-right'),    {opacity: 0});
                                }
                            },
                            touchmove: function(event) {
                                // prevent scrolling on elements without the 'scrollable' class
                                if (!$('.scrollable').has($(event.target)).length) {
                                    $.nplModal('log', 'theme galleria.events.touchmove() prevented scrolling');
                                    event.preventDefault();
                                }
                            },
                            loadstart: function(event) {
                                $.nplModal('log', 'theme galleria.events.loadstart()', {
                                    event: event
                                });

                                if (!event.cached) {
                                    var button = $('#npl_button_close').find('i');
                                    button.removeClass('fa-times-circle');
                                }
                            },
                            loadfinish: function() {
                                $.nplModal('log', 'theme galleria.events.loadfinish()');

                                var button = $('#npl_button_close').find('i');
                                button.addClass('fa-times');
                            }
                        }
                    },
                    share_icons: {
                        strip_html: function(html) {
                            var tmp = document.createElement('div');
                            tmp.innerHTML = html;
                            return tmp.textContent || tmp.innerText || "";
                        },
                        create: function(target, iconcolor) {
                            $.nplModal('log', 'theme share_icons.create()');
                            if (methods.nplModal.is_random_source()
                            ||  methods.nplModal.is_nextgen_widget()
                            ||  !methods.nplModal.is_nextgen_gallery()
                            ||  !$.nplModal('get_setting', 'enable_routing', false)) {
                                return false;
                            }

                            var id = self.getData(self.getIndex()).image_id;
                            var data = self.getData(self.getIndex());
                            var url = encodeURIComponent(window.location.toString());

                            var twitter_icon = $('<li/>').html(
                                $('<a/>', {'href': 'https://twitter.com/share?url=' + methods.share_icons.get_share_url(id, 'full'),
                                    'target': '_blank',
                                    'class': 'nggpl-comment-tweet-button',
                                    'title': $.nplModal('get_setting', 'i18n').share.twitter})
                                    .css({color: iconcolor})
                                    .html($('<i/>', {'class': 'fa fa-twitter-square'}))
                            );

                            var facebook_icon = $('<li/>').html(
                                $('<a/>', {
                                    'class': 'nggpl-comment-facebook-button',
                                    'title': $.nplModal('get_setting', 'i18n').share.facebook
                                }).css({color: iconcolor})
                                    .html($('<i/>', {'class': 'fa fa-facebook-square'}))
                            );

                            if (typeof $.nplModal('get_setting', 'facebook_app_id') !== 'undefined') {
                                facebook_icon.on('click', function(event) {
                                    event.preventDefault();
                                    FB.ui({
                                        method: 'share',
                                        href: methods.share_icons.get_share_url(id, 'full'),
                                        link: window.location.toString()
                                    }, function(response){
                                        // Debug response (optional)
                                        // console.log(response);
                                    });
                                });
                            } else {
                                var facebook_url = 'https://www.facebook.com/share.php';
                                facebook_url += '?u=' + encodeURIComponent(methods.share_icons.get_share_url(id, 'full'));
                                var anchor = facebook_icon.find('a');
                                anchor.attr('href', facebook_url);
                                anchor.attr('target', '_blank');
                            }

                            var pinterest_url = encodeURIComponent(methods.share_icons.get_share_url(id, 'full'));

                            // Pinterest restricts description length, and we must avoid making the request longer than browsers will allow
                            var summary = methods.share_icons.strip_html(data.description);
                            if (summary.length >= 446) {
                                summary = summary.substring(0, 492);
                                summary += '...';
                            }

                            pinterest_url += '&url=' + url;
                            pinterest_url += '&media=' + data.image;
                            pinterest_url += '&description=' + summary;
                            var pinterest_icon = $('<li/>').html(
                                $('<a/>', {'href': 'http://pinterest.com/pin/create/button/?s=100' + pinterest_url,
                                    'target': '_blank',
                                    'class': 'nggpl-comment-pinterest-button',
                                    'title': $.nplModal('get_setting', 'i18n').share.pinterest})
                                    .css({color: iconcolor})
                                    .html($('<i/>', {'class': 'fa fa-pinterest-square'}))
                            );

                            var icons = [twitter_icon, facebook_icon, pinterest_icon];

                            target = $(target);
                            target.html('');
                            $('<ul/>').appendTo(target);
                            target.find('ul').append(icons);
                        },
                        get_share_url: function(id, named_size) {
                            if (typeof(named_size) === 'undefined') {
                                named_size = 'thumb';
                            }

                            var gallery_id = $.nplModal('get_state').gallery_id;
                            var base_url = $.nplModal('get_setting', 'share_url')
                                .replace('{gallery_id}', gallery_id)
                                .replace('{image_id}', id)
                                .replace('{named_size}', named_size);
                            var site_url_link = $('<a/>').attr('href', $.nplModal('get_setting', 'wp_site_url'))[0];
                            var parent_link   = $('<a/>').attr('href', window.location.toString())[0];
                            var base_link     = $('<a/>').attr('href', base_url)[0];

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

                            $.nplModal('log', 'theme share_icons.get_share_url() result', {
                                id: id,
                                named_size: named_size,
                                result: base_link.href
                            });

                            return base_link.href;
                        },
                        events: {
                            bind: function() {
                                $.nplModal('log', 'theme share_icons.events.bind()');
                                self.bind('loadfinish', this.loadfinish);
                                self.bind('npl_init', this.npl_init);
                            },
                            npl_init: function() {
                                if (typeof $.nplModal('get_setting', 'facebook_app_id') !== 'undefined') {
                                    if (typeof window.fbAsyncInit === 'undefined') {
                                        window.fbAsyncInit = function() {
                                            FB.init({
                                                appId: $.nplModal('get_setting', 'facebook_app_id'),
                                                autoLogAppEvents: true,
                                                xfbml: true,
                                                version: 'v2.12'
                                            });
                                        };

                                        (function(d, s, id) {
                                            var js, fjs = d.getElementsByTagName(s)[0];
                                            if (d.getElementById(id)) { return; }
                                            js = d.createElement(s); js.id = id;
                                            js.src = "https://connect.facebook.net/en_US/sdk.js";
                                            fjs.parentNode.insertBefore(js, fjs);
                                        }(document, 'script', 'facebook-jssdk'));
                                    }
                                }
                            },
                            loadfinish: function() {
                                $.nplModal('log', 'theme share_icons.events.loadfinish()');
                                if (methods.nplModal.is_nextgen_gallery()
                                    &&  $.nplModal('get_setting', 'enable_routing', false)
                                    &&  $.nplModal('get_setting', 'enable_sharing', false)) {
                                    var iconcolor = $.nplModal('get_setting', 'carousel_text_color') ? $.nplModal('get_setting', 'carousel_text_color') : methods.icons.get_color();
                                    methods.share_icons.create(
                                        '#galleria-image-share-icons',
                                        iconcolor
                                    );
                                }
                            }
                        }
                    }
                };

                $.nplModal('log', 'theme initialization - about to bind events');

                // Load our modules
                methods.galleria.events.bind();
                methods.nplModal.events.bind();
                methods.thumbnails.events.bind();
                methods.info.events.bind();
                methods.share_icons.events.bind();
                methods.sidebar.events.bind();
                methods.sidebars.datacache.events.bind();
                methods.sidebars.comments.events.bind();

                $.nplModal('log', 'theme initialization - done binding events');

                $.nplModal('log', 'theme initialization - about to trigger npl_ready');
                $(self._target).trigger('npl_ready', {galleria_theme: self, methods: methods});

                $.nplModal('log', 'theme initialization - about to trigger npl_init');
                self.trigger('npl_init');

                $.nplModal('log', 'theme initialization - about to trigger npl_init_keys');
                self.trigger('npl_init_keys');

                $.nplModal('log', 'theme initialization - about to trigger npl_init_complete');
                self.trigger('npl_init_complete');
            }
        });
    };

}(jQuery));

<?php
/**
 * @property C_MVC_Controller|C_Display_Type_Controller|A_Search_Controller $object
 */
class A_Search_Controller extends Mixin
{
    public static $galleries_displayed = array();
    protected static $displayed_galleries_rendering = array();
    protected static $alternate_displayed_galleries = array();
    function get_alternate_displayed_gallery($displayed_gallery)
    {
        if (!empty(self::$alternate_displayed_galleries[$displayed_gallery->id()])) {
            return self::$alternate_displayed_galleries[$displayed_gallery->id()];
        }
        $user_search_term = $this->object->param('nggsearch');
        $displayed_gallery->display_settings = $this->validate_displayed_gallery_settings($displayed_gallery->display_settings);
        // Just to make the code a bit easier to read
        $displayed_gallery->display_settings = $this->validate_displayed_gallery_settings($displayed_gallery->display_settings);
        $display_settings = $displayed_gallery->display_settings;
        $gallery_ids = array();
        if ($displayed_gallery->source === 'galleries') {
            $gallery_ids = $displayed_gallery->container_ids;
        } elseif ($displayed_gallery->source === 'albums') {
            $gallery_ids = $this->object->get_album_children($displayed_gallery);
        }
        $params = $display_settings;
        $params['i18n'] = $this->object->get_i18n();
        $params['search_term'] = $user_search_term ? $user_search_term : '';
        // For browsers lacking javascript and/or search bots
        $params['form_submit_url'] = get_page_link();
        // For most users we redirect the user to the appropriate URL when the form submit event fires
        $params['form_redirect_url'] = $this->object->set_param_for(get_page_link(), 'nggsearch', 'ngg-search-placeholder');
        $search_results = array();
        if ($user_search_term) {
            $tagfilter_param = $this->object->param('tagfilter');
            if ($display_settings['enable_tag_filter'] && $tagfilter_param) {
                $tagfilter_param = explode(',', $tagfilter_param);
            }
            $search_results = $this->object->search_images($user_search_term, $gallery_ids, $this->object->get_term_ids($tagfilter_param, FALSE), $display_settings);
            $params['related_term_links'] = NULL;
            if ($display_settings['enable_tag_filter'] && !empty($search_results)) {
                $params['related_term_links'] = $this->object->get_related_terms_links($user_search_term, $search_results, $tagfilter_param);
            }
        }
        if (empty($search_results)) {
            if (!empty($user_search_term)) {
                $params['gallery_display'] = $this->object->render_partial("photocrati-nextgen_gallery_display#no_images_found", array(), TRUE);
            } else {
                $params['gallery_display'] = '';
            }
        } else {
            $renderer = C_Displayed_Gallery_Renderer::get_instance();
            $new_params = array('source' => 'images', 'image_ids' => $search_results, 'order_by' => 'sortorder', 'sortorder' => $search_results, 'display_type' => $display_settings['gallery_display_type'], 'is_ecommerce_enabled' => $display_settings['is_ecommerce_enabled']);
            /** @var C_Displayed_Gallery $new_displayed_gallery */
            $new_displayed_gallery = $renderer->params_to_displayed_gallery($new_params);
            if ($new_displayed_gallery && $new_displayed_gallery->validate()) {
                if (is_null($new_displayed_gallery->id())) {
                    $new_displayed_gallery->id(md5(json_encode($new_displayed_gallery->get_entity())));
                }
                self::$alternate_displayed_galleries[$displayed_gallery->id()] = $new_displayed_gallery;
                $params['gallery_display'] = $renderer->render($new_displayed_gallery, TRUE);
            }
        }
        $params = $this->object->prepare_display_parameters($displayed_gallery, $params);
        self::$displayed_galleries_rendering[$displayed_gallery->id()] = $this->object->render_partial(M_Imagely_Search_ID . '#default', $params, TRUE);
        if (!empty($new_displayed_gallery)) {
            return $new_displayed_gallery;
        }
        return $displayed_gallery;
    }
    /**
     * Does some hopefully unnecessary validation: because these variables are being used in raw SQl we want to be
     * thoroughly certain that ONLY these allowed strings are used
     *
     * @param array $settings
     * @return array
     */
    function validate_displayed_gallery_settings($settings)
    {
        if (!in_array($settings['order_by'], array('pid', 'galleryid', 'filename', 'imagedate'))) {
            $settings['order_by'] = 'pid';
        }
        if (!in_array(strtoupper($settings['order_direction']), array('ASC', 'DESC'))) {
            $settings['order_direction'] = 'ASC';
        }
        if (!in_array($settings['search_mode'], array('natural', 'boolean'))) {
            $settings['search_mode'] = 'natural';
        }
        return $settings;
    }
    /**
     * This effectively busts the standard template rendering cache
     *
     * @param C_Displayed_Gallery $displayed_gallery
     * @return string Rendered HTML
     */
    public function cache_action($displayed_gallery)
    {
        $id = $displayed_gallery->id();
        if ($this->object->param('nggsearch') && !isset(self::$galleries_displayed[$id])) {
            return $this->object->index_action($displayed_gallery, TRUE);
        }
    }
    /**
     * @param C_Displayed_Gallery $displayed_gallery
     * @param bool $return
     * @return string
     */
    public function index_action($displayed_gallery, $return = FALSE)
    {
        if (isset(self::$galleries_displayed[$displayed_gallery->id()])) {
            return '';
        } else {
            self::$galleries_displayed[$displayed_gallery->id()] = TRUE;
        }
        $user_search_term = $this->object->param('nggsearch');
        // In case the browser lacks javascript or is a bot the template's <form> uses the current post or page
        // URL so that we can redirect to the 'proper' search URL
        if ($user_search_term && $this->object->param('nggsearch-do-redirect')) {
            wp_redirect($this->object->set_param_for(get_page_link(), 'nggsearch', $user_search_term));
            exit;
        }
        // In case get_alternate_displayed_gallery() was not invoked during the wp_enqueue_scripts action
        if (empty(self::$displayed_galleries_rendering[$displayed_gallery->id()])) {
            $this->get_alternate_displayed_gallery($displayed_gallery);
        }
        return self::$displayed_galleries_rendering[$displayed_gallery->id()];
    }
    /**
     * Returns an array of related terms with their respective URL to filter or not filter based on those tags
     *
     * @param string $user_search_term
     * @param array $search_results
     * @param array $tagfilter_param
     * @return array
     */
    public function get_related_terms_links($user_search_term, $search_results = array(), $tagfilter_param = array())
    {
        $linked_terms = $this->object->get_image_terms($search_results);
        // The router's set_param_for() method will strip any space characters, so in case the user has
        // searched for a phrase we must first encode their search so results aren't ruined when adding pagination
        // or tag filters
        $user_search_term = str_replace(' ', '%20', $user_search_term);
        $tagfilter_links = array();
        // The current search is not restricted to any tags
        if (!$tagfilter_param) {
            $tagfilter_param = array();
        } else {
            // The current search is restricted to a tag(set) -- provide a 'clear all' link
            $clear_all_url = $this->object->set_param_for(get_page_link(), 'nggsearch', $user_search_term);
            $clear_all_url = $this->object->remove_param_for($clear_all_url, 'tagfilter');
            $tagfilter_links['ngg-clear-tag-filter'] = array('name' => __('Clear filters', 'nextgen-gallery-pro'), 'slug' => 'ngg-clear-tag-filter', 'type' => 'clearsearchfilters', 'url' => $clear_all_url, 'count' => 0);
        }
        foreach ($linked_terms as $linked_term) {
            // Skip linking to this term if it is exactly what the user searched for
            if (strtoupper($linked_term['name']) === strtoupper($user_search_term)) {
                continue;
            }
            if (in_array($linked_term['slug'], $tagfilter_param)) {
                // We are already filtering by this term, so generate a URL that removes it as a filterable term
                $new_list = $tagfilter_param;
                foreach ($new_list as $ndx => $new_list_item) {
                    if ($new_list_item == $linked_term['slug']) {
                        unset($new_list[$ndx]);
                    }
                }
                // There is at least one search term, so generate a URL that removes just this one term
                if (!empty($new_list)) {
                    $url_without_term = $this->object->set_param_for(get_page_link(), 'nggsearch', $user_search_term);
                    $url_without_term = $this->object->set_param_for($url_without_term, 'tagfilter', implode(',', $new_list));
                } else {
                    // With this term being removed from the filter there are no terms left: we want to link only to
                    // the base search without the tagfilter parameter present
                    $url_without_term = $this->object->set_param_for(get_page_link(), 'nggsearch', $user_search_term);
                    $url_without_term = $this->object->remove_param_for($url_without_term, 'tagfilter');
                }
                // Finally assemble the new array
                $tagfilter_links[$linked_term['slug']] = array('name' => $linked_term['name'], 'slug' => $linked_term['slug'], 'type' => 'del', 'url' => $url_without_term, 'count' => $linked_term['count']);
            } else {
                // This term is not being filtered: generate a URL that will include it in the tagfilter parameter
                $url_with_term = $this->object->set_param_for(get_page_link(), 'nggsearch', $user_search_term);
                $new_list = $tagfilter_param;
                $new_list[] = $linked_term['slug'];
                $url_with_term = $this->object->set_param_for($url_with_term, 'tagfilter', implode(',', $new_list));
                $tagfilter_links[$linked_term['slug']] = array('name' => $linked_term['name'], 'slug' => $linked_term['slug'], 'type' => 'add', 'url' => $url_with_term, 'count' => $linked_term['count']);
            }
        }
        return $tagfilter_links;
    }
    /**
     * Recursively fetches all album children and their children's children
     *
     * @param C_Displayed_Gallery|C_Album|stdClass $entity
     * @param bool $recursing
     * @return array
     */
    public function get_album_children($entity, $recursing = FALSE)
    {
        $retval = array();
        if (!$recursing) {
            $children = $entity->get_included_entities();
        } else {
            $mapper = C_Album_Mapper::get_instance();
            /** @var C_Album $album */
            $album = $mapper->find($entity->{$entity->id_field}, TRUE);
            $children = $album->get_galleries(TRUE);
            foreach ($this->object->get_child_albums($album) as $child_album) {
                $retval = array_merge($retval, $this->object->get_album_children($child_album, TRUE));
            }
        }
        foreach ($children as $child) {
            if (isset($child->is_gallery) && $child->is_gallery === '1' || get_class($child) === 'C_Gallery') {
                $retval[] = $child->{$child->id_field};
            } elseif (isset($child->is_album) && $child->is_album === '1' || get_class($child) === 'C_Album') {
                $retval = array_merge($retval, $this->object->get_album_children($child, TRUE));
            }
        }
        return $retval;
    }
    public function get_child_albums($album)
    {
        $mapper = C_Album_Mapper::get_instance();
        $album_key = $mapper->get_primary_key_column();
        return $mapper->select()->where(array("{$album_key} IN %s", $album->container_ids))->run_query();
    }
    /**
     * @return array
     */
    public function get_i18n()
    {
        return array('button_label' => __('Search Images', 'nextgen-gallery-pro'), 'input_placeholder' => __('Search term', 'nextgen-gallery-pro'));
    }
    /**
     * @param string $string
     * @return string
     */
    public function _array_trim($string)
    {
        return trim($string, "\"'\n\r");
    }
    /**
     * @param string $string
     * @return array
     */
    public function _split_text($string)
    {
        // Added slashes can interfere with the following regex
        $string = stripslashes($string);
        // Split the words into an array if separated by a space or comma
        preg_match_all('/".*?("|$)|((?<=[\\s",+])|^)[^\\s",+]+/', $string, $matches);
        $retval = array_map(array($this, '_array_trim'), $matches[0]);
        $retval = array_map('trim', $retval);
        // Include the original full string
        array_unshift($retval, $string);
        return $retval;
    }
    /**
     * @param array $terms
     * @param bool $search Whether to match exact term names or to do a LIKE search
     * @return array
     */
    public function get_term_ids($terms = array(), $search = FALSE)
    {
        global $wpdb;
        if (!is_array($terms)) {
            return [];
        }
        $sanitized_terms = array_map('sanitize_title', $terms);
        $terms = array_unique(array_merge($sanitized_terms, $terms));
        // In case the search term is a phrase and matches a multi-word tag we only return that first match
        if (count($terms) > 2) {
            $full_term = end($terms);
            $found_full_match = $this->get_term_ids([$full_term], $search);
            if (!empty($found_full_match)) {
                return $found_full_match;
            }
        }
        if ($search) {
            $and_clauses = array();
            foreach ($terms as $ndx => $term) {
                $maybe_or = $ndx >= 1 ? ' OR ' : '';
                $and_clauses[] = $wpdb->prepare(" {$maybe_or} t.`name` LIKE %s", '%' . $wpdb->esc_like($term) . '%');
            }
            $and_clauses = implode('', $and_clauses);
            $query = "SELECT DISTINCT t.`term_id`\n                    FROM `{$wpdb->terms}` t\n                    INNER JOIN `{$wpdb->term_taxonomy}` AS tt ON t.`term_id` = tt.`term_id`\n                    WHERE t.`name` IS NOT NULL\n                    AND tt.`taxonomy` = 'ngg_tag'\n                    AND ({$and_clauses})";
        } else {
            $slug_part = rtrim(str_repeat('%s,', count($terms)), ',');
            $query = $wpdb->prepare("SELECT DISTINCT t.`term_id`\n                    FROM `{$wpdb->terms}` t\n                    INNER JOIN `{$wpdb->term_taxonomy}` AS tt ON t.`term_id` = tt.`term_id`\n                    WHERE t.`slug` IN ({$slug_part})\n                    AND tt.`taxonomy` = 'ngg_tag'", $terms);
        }
        $results = $wpdb->get_col($query);
        if (is_array($results)) {
            return $results;
        } else {
            return array();
        }
    }
    /**
     * Retrieves a list of all tags that are applied to an array of image ID
     *
     * @param array $image_ids
     * @return array
     */
    public function get_image_terms($image_ids = array())
    {
        global $wpdb;
        $id_part = rtrim(str_repeat('%d,', count($image_ids)), ',');
        $query = $wpdb->prepare("SELECT DISTINCT t.`name`, t.`slug`, t.`term_id`, tt.`count`\n                    FROM `{$wpdb->term_relationships}` tr\n                    INNER JOIN `{$wpdb->term_taxonomy}` AS tt ON tr.`term_taxonomy_id` = tt.`term_taxonomy_id`\n                    INNER JOIN `{$wpdb->terms}` AS t ON tt.`term_id` = t.`term_id`\n                    WHERE tr.`object_id` IN ({$id_part})\n                    AND tt.`taxonomy` = 'ngg_tag'\n                    ORDER BY t.`name` ASC", $image_ids);
        $results = $wpdb->get_results($query, ARRAY_A);
        if (is_array($results)) {
            return $results;
        } else {
            return array();
        }
    }
    /**
     * @param string $request The search term
     * @param array $gallery_ids Restrict results to images assigned to these gallery ids
     * @param array $term_ids Restrict results to images assigned to these term ids
     * @param array $display_settings This displayed gallery's settings
     * @return array
     */
    public function search_images($request = '', $gallery_ids = array(), $term_ids = array(), $display_settings = array())
    {
        // First determine if we have a cached version of this search
        $transient_manager = C_Photocrati_Transient_Manager::get_instance();
        $cache_key = $transient_manager->generate_key('frontend_image_search', array($request, $gallery_ids, $term_ids, $display_settings));
        $cache_lookup = $transient_manager->get($cache_key, NULL);
        if (!is_null($cache_lookup)) {
            return $cache_lookup;
        }
        global $wpdb;
        // Assemble the WHERE clause
        $where_clause = "WHERE `nggpictures`.`pid` IS NOT NULL";
        // Restrict search results to a subset of galleries
        if (!empty($gallery_ids)) {
            $gallery_part = rtrim(str_repeat('%d,', count($gallery_ids)), ',');
            $where_clause .= $wpdb->prepare(" AND `nggpictures`.`galleryid` IN ({$gallery_part})", $gallery_ids);
        }
        // Assemble the matching against the image alttext and/or description
        $having_select_part = '';
        $having_clause = '';
        $having_fields = array();
        if ($display_settings['search_alttext']) {
            $having_fields[] = 'alttext';
        }
        if ($display_settings['search_description']) {
            $having_fields[] = 'description';
        }
        if (!empty($having_fields)) {
            // mySQL defaults to natural language mode; unless boolean mode is requested we don't need to specify the mode
            $search_mode_part = $display_settings['search_mode'] === 'boolean' ? 'IN BOOLEAN MODE' : '';
            $having_fields = implode(',', $having_fields);
            $having_select_part = $wpdb->prepare(", MATCH({$having_fields}) AGAINST (%s {$search_mode_part}) AS `relevance`", $request);
            $minimum_relevance = floatval($display_settings['minimum_relevance']);
            if ($minimum_relevance < 0) {
                $minimum_relevance = 0;
            }
            $having_clause = "HAVING `relevance` >= {$minimum_relevance}";
        }
        // Also search images based on their assigned tags
        if ($display_settings['search_tags']) {
            // Check if each word searched for is a tag
            // Search both for individual words and whole phrase; both sanitized and raw
            $tag_list = array($request);
            $tag_list_array = $this->object->_split_text($request);
            if (is_array($tag_list_array) && count($tag_list_array) >= 1) {
                $tag_list = array_merge($tag_list, $tag_list_array);
            }
            $sanitized_tag_list = array_map('sanitize_title', $tag_list);
            $tag_list = array_unique(array_merge($tag_list, $sanitized_tag_list));
            // If the searched phrase is a term, then include all images with that term
            $search_term_ids = $this->object->get_term_ids($tag_list, TRUE);
            if (!empty($search_term_ids)) {
                $tag_query_term_part = rtrim(str_repeat('%d,', count($search_term_ids)), ',');
                $tag_query = !empty($having_clause) ? ' OR ' : 'HAVING ';
                $tag_query .= "`nggpictures`.`pid` IN (\n                                   SELECT tr.`object_id`\n                                   FROM `{$wpdb->term_relationships}` AS tr\n                                   INNER JOIN `{$wpdb->term_taxonomy}` AS tt ON tr.`term_taxonomy_id` = tt.`term_taxonomy_id`\n                                   WHERE tt.`taxonomy` IN ('ngg_tag')\n                                   AND tt.`term_id` IN ({$tag_query_term_part}))";
                $having_clause .= $wpdb->prepare($tag_query, $search_term_ids);
            }
        }
        // Restrict results to only images with specific assigned terms
        if ($display_settings['enable_tag_filter'] && !empty($term_ids)) {
            $tag_filter_term_part = rtrim(str_repeat('%d,', count($term_ids)), ',');
            $tag_filter_having_clause = $wpdb->prepare("HAVING COUNT(*) >= %d", count($term_ids));
            $tag_filter_query = " AND `nggpictures`.`pid` IN (\n                                      SELECT tr.`object_id`\n                                      FROM `{$wpdb->term_relationships}` AS tr\n                                      INNER JOIN `{$wpdb->term_taxonomy}` AS tt ON tr.`term_taxonomy_id` = tt.`term_taxonomy_id`\n                                      WHERE tt.`taxonomy` IN ('ngg_tag')\n                                      AND tt.`term_id` IN ({$tag_filter_term_part})\n                                      GROUP BY tr.`object_id`\n                                      {$tag_filter_having_clause})";
            $where_clause .= $wpdb->prepare($tag_filter_query, $term_ids);
        }
        $order_clause = 'ORDER BY ';
        if ($display_settings['order_by_relevance'] && !empty($having_fields)) {
            $order_clause .= '`relevance` DESC, ';
        }
        $order_clause .= "`nggpictures`.`{$display_settings['order_by']}` {$display_settings['order_direction']}";
        $limit_clause = '';
        if (intval($display_settings['limit']) !== 0) {
            $limit_clause = $wpdb->prepare(" LIMIT %d", intval($display_settings['limit']));
        }
        // Build the final query
        $query = "SELECT `nggpictures`.`pid` {$having_select_part}\n                  FROM `{$wpdb->nggpictures}` AS `nggpictures`\n                  {$where_clause}\n                  {$having_clause}\n                  {$order_clause}\n                  {$limit_clause}";
        // Finally fetch the results of this insane query
        $results = $wpdb->get_col($query);
        // Cache the results for later lookups
        $transient_manager->set($cache_key, $results, NGG_DISPLAYED_GALLERY_CACHE_TTL);
        return $results;
    }
    public function enqueue_frontend_resources($displayed_gallery)
    {
        // Prevent the Pro Lightbox from calling get_entities() when no search is provided
        if (class_exists('M_Galleria') && !$this->object->param('nggsearch')) {
            M_Galleria::$localized_galleries[] = $displayed_gallery->ID();
        }
        // Normally we include this following call in every display type's enqueue_frontend_resources() method
        // however for this particular gallery it is not necessary *AND IT WILL DESTROY PERFORMANCE* !!
        // When viewing a child gallery that gallery will invoke this call_parent() method in a way that does not
        // cause page load times to increase four-fold! Do not uncomment this line.
        // $this->object->call_parent('enqueue_frontend_resources', $displayed_gallery);
        $this->object->enqueue_ngg_styles();
        wp_enqueue_style('nextgen_frontend_search_style', $this->object->get_static_url(M_Imagely_Search_ID . '#style.css'), array('dashicons'), NGG_SCRIPT_VERSION);
        wp_enqueue_script('nextgen_frontend_search_script', $this->object->get_static_url(M_Imagely_Search_ID . '#search.js'), array(), NGG_SCRIPT_VERSION, TRUE);
    }
}
/**
 * @mixin C_Form
 * @property C_Form|Mixin_Display_Type_Form $object
 */
class A_Search_Form extends Mixin_Display_Type_Form
{
    public function get_display_type_name()
    {
        return M_Imagely_Search_ID;
    }
    /**
     * Enqueues static resources required by this form
     */
    public function enqueue_static_resources()
    {
        $this->object->enqueue_script('nextgen_image_search_admin_form_js', $this->object->get_static_url(M_Imagely_Search_ID . '#admin.js'));
        $this->object->enqueue_style('nextgen_image_search_admin_form_css', $this->object->get_static_url(M_Imagely_Search_ID . '#admin.css'));
    }
    /**
     * Returns a list of fields to render on the settings page
     */
    public function _get_field_names()
    {
        return array('nextgen_frontend_search_gallery_display_type', 'nextgen_frontend_search_enable_tag_filter', 'nextgen_frontend_search_search_alttext', 'nextgen_frontend_search_search_description', 'nextgen_frontend_search_search_tags', 'nextgen_frontend_search_search_mode', 'nextgen_frontend_search_minimum_relevance', 'nextgen_frontend_search_limit', 'nextgen_frontend_search_order_by_relevance', 'nextgen_frontend_search_order_by', 'nextgen_frontend_search_order_direction');
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_frontend_search_enable_tag_filter_field($display_type)
    {
        return $this->object->_render_radio_field($display_type, 'enable_tag_filter', __('Enable filtering results by tag', 'nextgen-gallery-pro'), $display_type->settings['enable_tag_filter']);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_frontend_search_search_alttext_field($display_type)
    {
        return $this->object->_render_radio_field($display_type, 'search_alttext', __('Search image alttext', 'nextgen-gallery-pro'), $display_type->settings['search_alttext']);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_frontend_search_search_description_field($display_type)
    {
        return $this->object->_render_radio_field($display_type, 'search_description', __('Search image description', 'nextgen-gallery-pro'), $display_type->settings['search_description']);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_frontend_search_search_tags_field($display_type)
    {
        return $this->object->_render_radio_field($display_type, 'search_tags', __('Search image tags', 'nextgen-gallery-pro'), $display_type->settings['search_tags']);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_frontend_search_search_mode_field($display_type)
    {
        $options = array('natural' => __('Natural language', 'nextgen-gallery-pro'), 'boolean' => __('Boolean', 'nextgen-gallery-pro'));
        return $this->object->_render_select_field($display_type, 'search_mode', __('Database search mode', 'nextgen-gallery-pro'), $options, $display_type->settings['search_mode'], __('A natural language search treats the requested string as a phrase in text without any operators except for quotation marks. A boolean search uses special rules and operators such as the plus and minus symbols.', 'nextgen-gallery-pro'));
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_frontend_search_minimum_relevance_field($display_type)
    {
        return $this->object->_render_number_field($display_type, 'minimum_relevance', __('Minimum relevance', 'nextgen-gallery-pro'), $display_type->settings['minimum_relevance'], __('The database server assigns a relevance score to each possible image based on a number of factors with zero being not at all relevant. Users with smaller databases or images whose alttext or description only holds a few words will need a lower number here; possibly as low as 0.05. It is unlikely many users will need to raise this beyond one.', 'nextgen-gallery-pro'), FALSE, '', 0);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_frontend_search_limit_field($display_type)
    {
        return $this->object->_render_number_field($display_type, 'limit', __('Limit search results', 'nextgen-gallery-pro'), $display_type->settings['limit'], __('Limit search results to this amount. A setting of zero means no limitations are applied', 'nextgen-gallery-pro'), FALSE, '', 0);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_frontend_search_order_by_relevance_field($display_type)
    {
        return $this->object->_render_radio_field($display_type, 'order_by_relevance', __('Order by relevance first', 'nextgen-gallery-pro'), $display_type->settings['order_by_relevance'], __('When enabled search results will be ordered by their relevance first, then by the secondary order setting'));
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_frontend_search_order_by_field($display_type)
    {
        $options = array('pid' => __('Image ID', 'nextgen-gallery-pro'), 'galleryid' => __('Gallery ID', 'nextgen-gallery-pro'), 'filename' => __('Image filename', 'nextgen-gallery-pro'), 'imagedate' => __('Image date (EXIF or time of upload)', 'nextgen-gallery-pro'));
        return $this->object->_render_select_field($display_type, 'order_by', __('Order search results by', 'nextgen-gallery-pro'), $options, $display_type->settings['order_by']);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_frontend_search_order_direction_field($display_type)
    {
        $options = array('ASC' => __('Ascending', 'nextgen-gallery-pro'), 'DESC' => __('Descending', 'nextgen-gallery-pro'));
        return $this->object->_render_select_field($display_type, 'order_direction', __('Order direction of search results', 'nextgen-gallery-pro'), $options, $display_type->settings['order_direction']);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_frontend_search_gallery_display_type_field($display_type)
    {
        $options = array();
        $types = C_Display_Type_Mapper::get_instance()->find_by_entity_type('image');
        foreach ($types as $type) {
            if (!empty($type->hidden_from_ui) && $type->hidden_from_ui) {
                continue;
            }
            if ($type->name === M_Imagely_Search_ID) {
                continue;
            }
            $options[$type->name] = $type->title;
        }
        return $this->object->_render_select_field($display_type, 'gallery_display_type', __('Display results as', 'nextgen-gallery-pro'), $options, $display_type->settings['gallery_display_type']);
    }
}
/**
 * @property Mixin_DataMapper_Driver_Base $object
 */
class A_Search_Mapper extends Mixin
{
    function set_defaults($entity)
    {
        $this->call_parent('set_defaults', $entity);
        if ($entity->name === M_Imagely_Search_ID) {
            // What image attributes users are allowed to search by
            $this->object->_set_default_value($entity, 'settings', 'search_alttext', '1');
            $this->object->_set_default_value($entity, 'settings', 'search_description', '1');
            $this->object->_set_default_value($entity, 'settings', 'search_tags', '1');
            // Optional feature: allow frontend users to restrict search results by image tags
            $this->object->_set_default_value($entity, 'settings', 'enable_tag_filter', '1');
            // Which type of fulltext search to perform in mySQL
            $this->object->_set_default_value($entity, 'settings', 'search_mode', 'natural');
            // Query meta-attributes
            $this->object->_set_default_value($entity, 'settings', 'limit', '0');
            $this->object->_set_default_value($entity, 'settings', 'order_by', 'pid');
            $this->object->_set_default_value($entity, 'settings', 'order_direction', 'ASC');
            $this->object->_set_default_value($entity, 'settings', 'order_by_relevance', '1');
            $this->object->_set_default_value($entity, 'settings', 'minimum_relevance', '1');
            // The display type used to display results
            $this->object->_set_default_value($entity, 'settings', 'gallery_display_type', 'photocrati-nextgen_basic_thumbnails');
        }
    }
}
/**
 * @mixin C_Routing_App
 * @adapts I_Routing_App
 */
class A_Search_URLs extends Mixin
{
    function set_parameter_value($key, $value, $id = NULL, $use_prefix = FALSE, $url = FALSE)
    {
        $settings = C_NextGen_Settings::get_instance();
        $param_slug = preg_quote($settings->router_param_slug, '#');
        // it's difficult to make NextGEN's router work with spaces in parameter names without just encoding them
        // directly first; replace nggsearch's parameter's spaces with %20
        $url = preg_replace_callback("#(/{$param_slug}/.*)nggsearch--(.*)#", function ($matches) {
            return str_replace(' ', '%20', $matches[0]);
        }, $url);
        $retval = $this->call_parent('set_parameter_value', $key, $value, $id, $use_prefix, $url);
        return $this->_set_search_page_parameter($retval, $key, $value, $id, $use_prefix);
    }
    function _set_search_page_parameter($retval, $key, $value = NULL, $id = NULL, $use_prefix = NULL)
    {
        $settings = C_NextGen_Settings::get_instance();
        $param_slug = preg_quote($settings->router_param_slug, '#');
        // Convert the nggpage parameter to a slug
        if (preg_match("#(/{$param_slug}/.*)nggsearch--(.*)#", $retval, $matches)) {
            $retval = rtrim(str_replace($matches[0], rtrim($matches[1], "/") . "/search/" . ltrim($matches[2], "/"), $retval), "/");
        }
        if (preg_match("#(/{$param_slug}/.*)tagfilter--(.*)#", $retval, $matches)) {
            $retval = rtrim(str_replace($matches[0], rtrim($matches[1], "/") . "/tagfilter/" . ltrim($matches[2], "/"), $retval), "/");
        }
        return $retval;
    }
}
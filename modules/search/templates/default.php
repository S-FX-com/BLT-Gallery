<?php
/**
 * @var array $i18n
 * @var array $results
 * @var C_Displayed_Gallery $displayed_gallery
 * @var string $form_submit_url
 * @var string $form_redirect_url
 * @var string $gallery_display
 * @var string $search_term
 */
?>
<div class="ngg-image-search-container">

    <form method="POST"
          class="ngg-image-search-form"
          action="<?php print esc_attr($form_submit_url); ?>"
          data-submission-url="<?php print esc_attr($form_redirect_url); ?>">

        <input type="hidden"
               name="nggsearch-do-redirect"
               value="1"/>

        <input type="text"
               class="ngg-image-search-input"
               name="nggsearch"
               value="<?php print esc_attr($search_term); ?>"
               placeholder="<?php print esc_attr($i18n['input_placeholder']); ?>"/>

        <input type="submit"
               class="ngg-image-search-button"
               value="<?php print $i18n['button_label']; ?>"/>
    </form>

    <?php
    if (!empty($related_term_links)) { ?>
        <div class="ngg-image-search-filter">
            <h4>Filter by related tags</h4>
            <div class="ngg-filter-by-tags">
                <?php foreach ($related_term_links as $term_link) { ?>       
                    <a href="<?php print esc_attr($term_link['url']); ?>" class="button <?php echo $term_link['type']; ?>">
                        <?php print $term_link['name']; ?>
                    </a>
                <?php } ?>
            </div>
        </div>
    <?php } ?>

</div>

<?php
// This is the rendered child display-type with the search results
print $gallery_display;
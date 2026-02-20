<?php $this->start_element('nextgen_gallery.gallery_container', 'container', $displayed_gallery); ?>
<div class="ngg-galleria-parent <?php echo esc_attr($theme); ?>"
     data-id="<?php echo esc_attr($displayed_gallery_id); ?>"
     id="displayed_gallery_<?php echo esc_attr($displayed_gallery_id); ?>">
	<div class="ngg-galleria"></div>

    <div class="ngg-galleria-offscreen-seo-wrapper">
        <?php
        $this->start_element('nextgen_gallery.image_list_container', 'container', $images);
        foreach ($images as $image) {
            $this->start_element('nextgen_gallery.image_panel', 'item', $image);
            $this->start_element('nextgen_gallery.image', 'item', $image);
            ?>
            <a href="<?php echo esc_attr($storage->get_image_url($image)); ?>"
               title="<?php echo esc_attr($image->description); ?>"
               data-src="<?php echo esc_attr($storage->get_image_url($image)); ?>"
               data-thumbnail="<?php echo esc_attr($storage->get_image_url($image, 'thumb')); ?>"
               data-image-id="<?php echo esc_attr($image->{$image->id_field}); ?>"
               data-title="<?php echo esc_attr($image->alttext); ?>"
               data-description="<?php echo esc_attr(stripslashes($image->description)); ?>"
                <?php echo $effect_code ?>>
                <?php M_NextGen_PictureFill::render_picture_element($image, 'full'); ?>
            </a>
            <?php
            $this->end_element();
            $this->end_element();
        }
        $this->end_element();
        ?>
    </div>
</div>
<?php $this->end_element(); ?>
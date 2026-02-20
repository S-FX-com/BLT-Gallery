<?php $image = $item->image; ?>
<?php $this->start_element('nextgen_gallery.image_panel', 'item', $image); ?>
    <div class="tiled-gallery-item<?php if (isset($item->size)) { print " tiled-gallery-item-{$item->size}"; } ?>"
         itemprop="associatedMedia"
         itemscope
         itemtype="http://schema.org/ImageObject">
        <?php $this->start_element('nextgen_gallery.image', 'item', $image); ?>
            <?php
            $params     = apply_filters('ngg_pro_tile_image_dimensions', M_NextGen_Pro_Tile::$default_size_params, $image);
            $dynthumbs  = C_Dynamic_Thumbnails_Manager::get_instance();
            $image_size = $dynthumbs->get_size_name($params);
            ?>
            <a href='<?php print esc_attr($storage->get_image_url($image, 'full', TRUE))?>'
               border='0'
               itemprop='url'
               title="<?php print esc_attr($image->description); ?>"
               <?php print $effect_code; ?>
               data-image-id="<?php print esc_attr($image->{$image->id_field}); ?>"
               data-title="<?php echo esc_attr($image->alttext); ?>"
               data-description="<?php echo esc_attr(stripslashes($image->description)); ?>">
                <meta itemprop="width" content="<?php print esc_attr($image->tile_meta['width']); ?>">
                <meta itemprop="height" content="<?php print esc_attr($image->tile_meta['height']); ?>">
                <?php M_NextGen_PictureFill::render_picture_element(
                        $image,
                        $image_size,
                        array(
                            'itemprop' => "http://schema.org/image",
                            'data-original-width'  => $image->tile_meta['width'],
                            'data-original-height' => $image->tile_meta['height'],
                            'style' => "width: {$image->tile_meta['width']}px;
                                        height: {$image->tile_meta['height']}px;"

                        )
                ); ?>
            </a>
        <?php $this->end_element(); ?>
    </div>
<?php $this->end_element(); ?>
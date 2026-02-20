<?php
/**
 * @var C_Displayed_Gallery $displayed_gallery
 * @var C_Image[] $images
 * @var string $pagination
 * @var string $thumbnail_size_name
 */

$settings = $displayed_gallery->display_settings;

$this->start_element('nextgen_gallery.gallery_container', 'container', $displayed_gallery); ?>

    <div class="nextgen_pro_film" id="gallery_<?php echo esc_attr($id) ?>">
        <?php
        $this->start_element('nextgen_gallery.image_list_container', 'container', $images);

            $i = 0;

            foreach ($images as $image) {

                $template_params = array(
                    'index' => $i,
                    'class' => 'image-wrapper',
                    'image' => $image,
                );

                $this->start_element('nextgen_gallery.image_panel', 'item', $image);
                ?>
                    <div id="<?php echo esc_attr('ngg-image-' . $i) ?>" class="image-wrapper">

                        <?php $this->start_element('nextgen_gallery.image', 'item', $image); ?>

                            <a href="<?php echo esc_attr($storage->get_image_url($image)); ?>"
                               title="<?php echo esc_attr($image->description); ?>"
                               data-src="<?php echo esc_attr($storage->get_image_url($image)); ?>"
                               data-thumbnail="<?php echo esc_attr($storage->get_image_url($image, 'thumb')); ?>"
                               data-image-id="<?php echo esc_attr($image->{$image->id_field}); ?>"
                               data-title="<?php echo esc_attr($image->alttext); ?>"
                               data-description="<?php echo esc_attr(stripslashes($image->description)); ?>"
                               <?php echo $effect_code ?>>
                                <?php M_NextGen_PictureFill::render_picture_element(
                                    $image,
                                    $thumbnail_size_name,
                                    array('class' => 'nextgen_pro_film_image')
                                ); ?>
                            </a>

                            <?php if ($settings['alttext_display']) { ?>
                                <div class="nextgen_pro_film_title">
                                    <?php print esc_html($image->alttext); ?>
                                </div>
                            <?php } ?>

                            <?php if ($settings['description_display']) { ?>
                                <div class="nextgen_pro_film_description">
                                    <?php print esc_html($image->description); ?>
                                </div>
                            <?php } ?>

                        <?php $this->end_element(); ?>
                    </div>
                <?php
                $this->end_element();
                $i++;
            }
        $this->end_element();
        ?>
    </div>
    <?php
    if ($pagination) {
        echo $pagination;
    } else { ?>
        <div class="ngg-clear"></div>
    <?php } ?>

<?php $this->end_element();
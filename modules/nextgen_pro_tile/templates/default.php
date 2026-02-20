<?php $this->start_element('nextgen_gallery.gallery_container', 'container', $displayed_gallery); ?>
    <div class="tiled-gallery type-<?php print $type; ?> tiled-gallery-unresized"
         data-original-width="<?php print esc_attr($contentWidth); ?>"
         itemscope itemtype="http://schema.org/ImageGallery">
        <?php $this->start_element('nextgen_gallery.image_list_container', 'container', $images); ?>
            <?php
            $view = new C_MVC_View(
                'photocrati-nextgen_pro_tile#/' . $type . '-layout',
                array(
                    'rows'        => $rows,
                    'effect_code' => $effect_code,
                    'storage'     => $storage
                )
            );
            $view->render(FALSE);
            ?>
        <?php $this->end_element(); ?>
    </div>
<?php $this->end_element(); ?>
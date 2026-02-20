<?php foreach ($rows as $row) { ?>
    <div class="gallery-row"
         style="width: <?php print esc_attr($row->width); ?>px;
                height: <?php print esc_attr($row->height); ?>px;"
         data-original-width="<?php print esc_attr($row->width); ?>"
         data-original-height="<?php print esc_attr($row->height); ?>">
        <?php foreach ($row->groups as $group) { ?>
            <div class="gallery-group images-<?php print esc_attr(count($group->images)); ?>"
                 style="width: <?php print esc_attr($group->width); ?>px;
                        height: <?php print esc_attr($group->height); ?>px;"
                 data-original-width="<?php print esc_attr($group->width); ?>"
                 data-original-height="<?php print esc_attr($group->height); ?>">
                <?php foreach($group->items() as $item) {
                    $params = array(
                        'item'        => $item,
                        'effect_code' => $effect_code,
                        'storage'     => $storage
                    );
                    $view = new C_MVC_View('photocrati-nextgen_pro_tile#individual-image', $params);
                    $view->render(FALSE);
                } ?>
            </div>
        <?php } ?>
    </div>
<?php } ?>
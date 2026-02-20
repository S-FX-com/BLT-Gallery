/* do not remove or the next id is printed raw <?php echo $id; ?> */

#<?php echo $id ?> .image_container {
    padding: <?php echo $padding; ?>px;
    margin-bottom: <?php echo $spacing;?>px;
    <?php if (floatval($border_size) > 0) { ?>
    border: solid <?php echo $border_size; ?>px <?php echo $border_color; ?>;
    <?php } ?>
    background-color: <?php echo $background_color; ?>;
}

#<?php echo $id ?> .gallery_link a {
    margin-right: <?php echo $padding; ?>px;
}

#<?php echo $id ?> .caption_link a,
#<?php echo $id ?> .caption_link a:visited,
#<?php echo $id ?> .caption_link a:hover {
    color: <?php echo $caption_color; ?>;
    font-size: <?php echo $caption_size; ?>px;
}

#<?php echo $id ?> .image_description {
    color: <?php echo $description_color; ?>;
    font-size: <?php echo $description_size; ?>px;
}
/* <?php print $id ?> */
<?php
/**
 * @var int $longest
 * @var int $widest
 * @var string $border_color
 * @var string $border_size
 * @var string $frame_color
 * @var string $frame_size
 * @var string $image_spacing
 * @var int $description_display
 * @var int $description_font_size
 * @var string $description_font_color
 */
print "/* {$widest} - {$longest} */";

$width = $widest;
$width += intval($frame_size)  * 2;
$width += intval($border_size) * 2;

$height = $longest;
$height += intval($frame_size)  * 2;
$height += intval($border_size) * 2;
?>

#gallery_<?php print $id; ?> .image-wrapper {
    margin-left: <?php print intval($image_spacing); ?>px;
    margin-bottom: <?php print intval($image_spacing); ?>px;
    padding: <?php print intval($frame_size); ?>px;
    border: solid <?php print intval($border_size); ?>px <?php print $border_color; ?>;
    background-color: <?php print $frame_color ?>;
    max-width: <?php print intval($width); ?>px;
}

#gallery_<?php print $id; ?> .image-wrapper a {
    width: <?php print $widest; ?>px;
    height: <?php print $longest; ?>px;
}

#gallery_<?php print $id; ?> .nextgen_pro_film_title {
    <?php if (!empty($alttext_display)) { ?>
        <?php if (!empty($alttext_font_size)) { ?>
            font-size: <?php print intval($alttext_font_size); ?>px;
        <?php } ?>
        <?php if (!empty($alttext_font_color)) { ?>
            color: <?php print $alttext_font_color; ?>;
        <?php } ?>
    <?php } ?>
}

#gallery_<?php print $id; ?> .nextgen_pro_film_description {
    <?php if (!empty($description_display)) { ?>
        <?php if (!empty($description_font_size)) { ?>
            font-size: <?php print intval($description_font_size); ?>px;
        <?php } ?>
        <?php if (!empty($description_font_color)) { ?>
            color: <?php print $description_font_color; ?>;
        <?php } ?>
    <?php } ?>
}
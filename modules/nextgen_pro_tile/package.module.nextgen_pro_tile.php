<?php
/**
 * Adds validation for the NextGen Pro Tile display type
 * @mixin C_Display_Type
 * @adapts I_Display_Type
 */
class A_NextGen_Pro_Tile extends Mixin
{
    function validation()
    {
        return $this->call_parent('validation');
    }
}
/**
 * @mixin C_Display_Type_Controller
 * @adapts I_Display_Type_Controller for "photocrati-nextgen_pro_tile" context
 */
class A_NextGen_Pro_Tile_Controller extends Mixin
{
    /**
     * Renders the front-end display for the tile display type
     *
     * @param C_Displayed_Gallery $displayed_gallery
     * @param bool $return
     * @return string Rendered HTML
     */
    function index_action($displayed_gallery, $return = FALSE)
    {
        $images = $displayed_gallery->get_included_entities();
        // Are there images to display?
        if (!$images) {
            return $this->object->render_partial("photocrati-nextgen_gallery_display#no_images_found", array(), $return);
        }
        // This display type was forked / copied from JetPack's tiled gallery which does not have a clean way
        // of sharing a settings array through it's execution. C_NextGen_Pro_Tiled_Gallery stores the display settings
        // in a public, static variable as a singleton so that settings can be accessed anywhere during execution here.
        C_NextGen_Pro_Tiled_Gallery::$settings = $displayed_gallery->display_settings;
        $grouper = new C_NextGen_Pro_Tiled_Gallery_Grouper($images);
        C_NextGen_Pro_Tiled_Gallery_Shape::reset_last_shape();
        $params = array('rows' => $grouper->grouped_images, 'type' => 'rectangular', 'images' => $images, 'effect_code' => $this->object->get_effect_code($displayed_gallery), 'contentWidth' => C_NextGen_Pro_Tiled_Gallery::get_content_width(), 'storage' => C_Gallery_Storage::get_instance());
        $params = $this->object->prepare_display_parameters($displayed_gallery, $params);
        return $this->object->render_partial('photocrati-nextgen_pro_tile#default', $params, $return);
    }
    /**
     * Enqueues all static resources required by this display type
     *
     * @param C_Displayed_Gallery $displayed_gallery
     */
    function enqueue_frontend_resources($displayed_gallery)
    {
        $this->call_parent('enqueue_frontend_resources', $displayed_gallery);
        wp_enqueue_style('nextgen_pro_tile_style', $this->get_static_url('photocrati-nextgen_pro_tile#style.css'), array(), NGG_SCRIPT_VERSION);
        wp_enqueue_script('nextgen_pro_tile_script', $this->get_static_url('photocrati-nextgen_pro_tile#tile.js'), array(), NGG_SCRIPT_VERSION);
        $this->enqueue_ngg_styles();
    }
}
class C_NextGen_Pro_Tiled_Gallery
{
    public static $settings = array();
    public static function get_content_width()
    {
        $content_width = isset($GLOBALS['content_width']) ? $GLOBALS['content_width'] : 2000;
        if (!empty(self::$settings['override_maximum_width']) && !empty(self::$settings['maximum_width'])) {
            $content_width = self::$settings['maximum_width'];
        }
        return $content_width;
    }
}
class C_NextGen_Pro_Tiled_Gallery_Shape
{
    /** @var stdClass[] NextGen image objects */
    protected $images = array();
    /** @var int|NULL Count of images remaining  */
    protected $images_left = NULL;
    /** @var string[] */
    protected static $shapes_used = array();
    /**
     * @param stdClass[] $images
     */
    public function __construct($images)
    {
        $this->images = $images;
        $this->images_left = count($images);
    }
    /**
     * @param int $number_of_images
     * @return float|int
     */
    public function sum_ratios($number_of_images = 3)
    {
        $list = array();
        foreach ($this->images as $image) {
            $list[] = $image->tile_meta['ratio'];
        }
        return array_sum(array_slice($list, 0, $number_of_images));
    }
    /**
     * @return bool
     */
    public function next_images_are_symmetric()
    {
        return $this->images_left > 2 && $this->images[0]->tile_meta['ratio'] == $this->images[2]->tile_meta['ratio'];
    }
    /**
     * @param int $n
     * @return bool
     */
    public function is_not_as_previous($n = 1)
    {
        return !in_array(get_class($this), array_slice(self::$shapes_used, -$n));
    }
    /**
     * @return bool
     */
    public function is_wide_theme()
    {
        return C_NextGen_Pro_Tiled_Gallery::get_content_width() > 1000;
    }
    /**
     * @param stdClass $image
     * @return bool
     */
    public function image_is_landscape($image)
    {
        return $image->tile_meta['ratio'] >= 1 && $image->tile_meta['ratio'] < 2;
    }
    /**
     * @param stdClass $image
     * @return bool
     */
    public function image_is_portrait($image)
    {
        return $image->tile_meta['ratio'] < 1;
    }
    /**
     * @param stdClass $image
     * @return bool
     */
    public function image_is_panoramic($image)
    {
        return $image->tile_meta['ratio'] >= 2;
    }
    /**
     * @param string $last_shape
     */
    public static function set_last_shape($last_shape)
    {
        self::$shapes_used[] = $last_shape;
    }
    public static function reset_last_shape()
    {
        self::$shapes_used = array();
    }
}
class C_NextGen_Pro_Tiled_Gallery_Three extends C_NextGen_Pro_Tiled_Gallery_Shape
{
    /** @var array This shape's dimensions */
    public $shape = array(1, 1, 1);
    /**
     * @return bool
     */
    public function is_possible()
    {
        $ratio = $this->sum_ratios(3);
        $has_enough_images = $this->images_left >= 3 && !in_array($this->images_left, array(4, 6));
        return $has_enough_images && $this->is_not_as_previous(3) && ($ratio < 2.5 || $ratio < 5 && $this->next_images_are_symmetric() || $this->is_wide_theme());
    }
}
class C_NextGen_Pro_Tiled_Gallery_Four extends C_NextGen_Pro_Tiled_Gallery_Shape
{
    /** @var array This shape's dimensions */
    public $shape = array(1, 1, 1, 1);
    /**
     * @return bool
     */
    public function is_possible()
    {
        return $this->is_not_as_previous() && ($this->sum_ratios(4) < 3.5 && $this->images_left > 5 || $this->sum_ratios(4) < 7 && $this->images_left == 4);
    }
}
class C_NextGen_Pro_Tiled_Gallery_Five extends C_NextGen_Pro_Tiled_Gallery_Shape
{
    /** @var array This shape's dimensions */
    public $shape = array(1, 1, 1, 1, 1);
    /**
     * @return bool
     */
    public function is_possible()
    {
        return $this->is_wide_theme() && $this->is_not_as_previous() && $this->sum_ratios(5) < 5 && ($this->images_left == 5 || $this->images_left != 10 && $this->images_left > 6);
    }
}
class C_NextGen_Pro_Tiled_Gallery_Two_One extends C_NextGen_Pro_Tiled_Gallery_Shape
{
    /** @var array This shape's dimensions */
    public $shape = array(2, 1);
    /**
     * @return bool
     */
    public function is_possible()
    {
        return $this->is_not_as_previous(3) && $this->images_left >= 2 && $this->images[2]->tile_meta['ratio'] < 1.6 && $this->images[0]->tile_meta['ratio'] >= 0.9 && $this->images[0]->tile_meta['ratio'] < 2.0 && $this->images[1]->tile_meta['ratio'] >= 0.9 && $this->images[1]->tile_meta['ratio'] < 2.0;
    }
}
class C_NextGen_Pro_Tiled_Gallery_One_Two extends C_NextGen_Pro_Tiled_Gallery_Shape
{
    /** @var array This shape's dimensions */
    public $shape = array(1, 2);
    /**
     * @return bool
     */
    public function is_possible()
    {
        return $this->is_not_as_previous(3) && $this->images_left >= 2 && $this->images[0]->tile_meta['ratio'] < 1.6 && $this->images[1]->tile_meta['ratio'] >= 0.9 && $this->images[1]->tile_meta['ratio'] < 2.0 && $this->images[2]->tile_meta['ratio'] >= 0.9 && $this->images[2]->tile_meta['ratio'] < 2.0;
    }
}
class C_NextGen_Pro_Tiled_Gallery_One_Three extends C_NextGen_Pro_Tiled_Gallery_Shape
{
    /** @var array This shape's dimensions */
    public $shape = array(1, 3);
    /**
     * @return bool
     */
    public function is_possible()
    {
        return $this->is_not_as_previous(3) && $this->images_left > 3 && $this->image_is_portrait($this->images[0]) && $this->image_is_landscape($this->images[1]) && $this->image_is_landscape($this->images[2]) && $this->image_is_landscape($this->images[3]);
    }
}
class C_NextGen_Pro_Tiled_Gallery_Three_One extends C_NextGen_Pro_Tiled_Gallery_Shape
{
    /** @var array This shape's dimensions */
    public $shape = array(3, 1);
    /**
     * @return bool
     */
    public function is_possible()
    {
        return $this->is_not_as_previous(3) && $this->images_left > 3 && $this->image_is_portrait($this->images[3]) && $this->image_is_landscape($this->images[0]) && $this->image_is_landscape($this->images[1]) && $this->image_is_landscape($this->images[2]);
    }
}
class C_NextGen_Pro_Tiled_Gallery_Panoramic extends C_NextGen_Pro_Tiled_Gallery_Shape
{
    /** @var array This shape's dimensions */
    public $shape = array(1);
    /**
     * @return bool
     */
    public function is_possible()
    {
        return $this->image_is_panoramic($this->images[0]);
    }
}
class C_NextGen_Pro_Tiled_Gallery_Symmetric_Row extends C_NextGen_Pro_Tiled_Gallery_Shape
{
    /** @var array This shape's dimensions */
    public $shape = array(1, 2, 1);
    /**
     * @return bool
     */
    public function is_possible()
    {
        return $this->is_not_as_previous(5) && $this->images_left > 3 && $this->images_left != 5 && $this->image_is_portrait($this->images[0]) && $this->image_is_landscape($this->images[1]) && $this->image_is_landscape($this->images[2]) && $this->image_is_portrait($this->images[3]);
    }
}
class C_NextGen_Pro_Tiled_Gallery_Reverse_Symmetric_Row extends C_NextGen_Pro_Tiled_Gallery_Shape
{
    /** @var array This shape's dimensions */
    public $shape = array(2, 1, 2);
    /**
     * @return bool
     */
    public function is_possible()
    {
        return $this->is_not_as_previous(5) && $this->images_left > 15 && $this->image_is_landscape($this->images[0]) && $this->image_is_landscape($this->images[1]) && $this->image_is_portrait($this->images[2]) && $this->image_is_landscape($this->images[3]) && $this->image_is_landscape($this->images[4]);
    }
}
class C_NextGen_Pro_Tiled_Gallery_Long_Symmetric_Row extends C_NextGen_Pro_Tiled_Gallery_Shape
{
    /** @var array This shape's dimensions */
    public $shape = array(3, 1, 3);
    /**
     * @return bool
     */
    public function is_possible()
    {
        return $this->is_not_as_previous(5) && $this->images_left > 15 && $this->image_is_landscape($this->images[0]) && $this->image_is_landscape($this->images[1]) && $this->image_is_landscape($this->images[2]) && $this->image_is_portrait($this->images[3]) && $this->image_is_landscape($this->images[4]) && $this->image_is_landscape($this->images[5]) && $this->image_is_landscape($this->images[6]);
    }
}
class C_NextGen_Pro_Tiled_Gallery_Three_Columns extends C_NextGen_Pro_Tiled_Gallery_Shape
{
    /** @var array This shape's dimensions */
    public $shape = array();
    public function __construct($images)
    {
        parent::__construct($images);
        $total_ratio = $this->sum_ratios($this->images_left);
        $approximate_column_ratio = $total_ratio / 3;
        $column_one_images = $column_two_images = $column_three_images = $sum = 0;
        foreach ($this->images as $image) {
            if ($sum <= $approximate_column_ratio) {
                $column_one_images++;
            }
            if ($sum > $approximate_column_ratio && $sum <= 2 * $approximate_column_ratio) {
                $column_two_images++;
            }
            $sum += $image->tile_meta['ratio'];
        }
        $column_three_images = $this->images_left - $column_two_images - $column_one_images;
        if ($column_one_images) {
            $this->shape[] = $column_one_images;
        }
        if ($column_two_images) {
            $this->shape[] = $column_two_images;
        }
        if ($column_three_images) {
            $this->shape[] = $column_three_images;
        }
    }
    /**
     * @return bool
     */
    public function is_possible()
    {
        return !empty($this->shape);
    }
}
abstract class C_NextGen_Pro_Tiled_Gallery_Item
{
    /** @var stdClass NextGen image object */
    public $image;
    /**
     * @param stdClass $image NextGen image object
     */
    public function __construct($image)
    {
        $this->image = $image;
    }
}
class C_NextGen_Pro_Tiled_Gallery_Rectangular_Item extends C_NextGen_Pro_Tiled_Gallery_Item
{
    protected $size = 'large';
    /**
     * @param stdClass $image NextGen image object
     */
    public function __construct($image)
    {
        parent::__construct($image);
        if ($this->image->tile_meta['width'] < 250) {
            $this->size = 'small';
        }
    }
}
// Image grouping and HTML generation logic
class C_NextGen_Pro_Tiled_Gallery_Grouper
{
    /** @var int Margin between images (in pixels) */
    public $margin = 4;
    // This list is ordered. If you put a shape that's likely to occur on top, it will happen all the time.
    public $shapes = array('Reverse_Symmetric_Row', 'Long_Symmetric_Row', 'Symmetric_Row', 'One_Three', 'Three_One', 'One_Two', 'Five', 'Four', 'Three', 'Two_One', 'Panoramic');
    /** @var string Previously used shape: avoid repeating shapes if possible */
    public $last_shape = '';
    /** @var stdClass[] NextGen image objects */
    public $images = array();
    /** @var C_NextGen_Pro_Tiled_Gallery_Row[] */
    public $grouped_images = array();
    /**
     * @param stdClass[] $images NextGen image objects
     * @param string[] $shapes
     */
    public function __construct($images, $shapes = array())
    {
        $content_width = C_NextGen_Pro_Tiled_Gallery::get_content_width();
        // TODO: Uncomment when we roll out the margin setting
        // if (!empty(C_NextGen_Pro_Tiled_Gallery::$settings['margin']))
        //     $this->margin = C_NextGen_Pro_Tiled_Gallery::$settings['margin'];
        $this->overwrite_shapes($shapes);
        $this->images = $this->get_images_with_sizes($images);
        $this->grouped_images = $this->get_grouped_images();
        $this->apply_content_width($content_width);
    }
    /**
     * @param string[] $shapes
     */
    public function overwrite_shapes($shapes)
    {
        if (!empty($shapes)) {
            $this->shapes = $shapes;
        }
    }
    /**
     * @return array
     */
    public function get_current_row_size()
    {
        $images_left = count($this->images);
        if ($images_left < 3) {
            return array_fill(0, $images_left, 1);
        }
        foreach ($this->shapes as $shape_name) {
            $class_name = "C_NextGen_Pro_Tiled_Gallery_{$shape_name}";
            $shape = new $class_name($this->images);
            if ($shape->is_possible()) {
                C_NextGen_Pro_Tiled_Gallery_Shape::set_last_shape($class_name);
                return $shape->shape;
            }
        }
        C_NextGen_Pro_Tiled_Gallery_Shape::set_last_shape('Two');
        return array(1, 1);
    }
    /**
     * @param stdClass[] $images NextGen image objects
     * @return stdClass[] Modified $images array with tile_meta attribute added
     */
    public function get_images_with_sizes($images)
    {
        $images_with_sizes = array();
        foreach ($images as $image) {
            $size_params = apply_filters('ngg_pro_tile_image_dimensions', M_NextGen_Pro_Tile::$default_size_params, $image);
            $image_size = C_Dynamic_Thumbnails_Manager::get_instance()->get_size_name($size_params);
            if (empty($image->meta_data[$image_size])) {
                $dimensions = C_Gallery_Storage::get_instance()->calculate_image_size_dimensions($image, $image_size);
                $width = $dimensions['real_width'];
                $height = $dimensions['real_height'];
            } else {
                $width = $image->meta_data[$image_size]['width'];
                $height = $image->meta_data[$image_size]['height'];
            }
            $image->tile_meta = array();
            $image->tile_meta['width'] = $width;
            $image->tile_meta['height'] = $height;
            $image->tile_meta['width_orig'] = isset($width) && $width > 0 ? $width : 1;
            $image->tile_meta['height_orig'] = isset($height) && $height > 0 ? $height : 1;
            $image->tile_meta['ratio'] = $image->tile_meta['width_orig'] / $image->tile_meta['height_orig'];
            $image->tile_meta['ratio'] = $image->tile_meta['ratio'] ? $image->tile_meta['ratio'] : 1;
            $images_with_sizes[] = $image;
        }
        return $images_with_sizes;
    }
    /**
     * @return C_NextGen_Pro_Tiled_Gallery_Group[]
     */
    public function read_row()
    {
        $vector = $this->get_current_row_size();
        $row = array();
        foreach ($vector as $group_size) {
            $row[] = new C_NextGen_Pro_Tiled_Gallery_Group(array_splice($this->images, 0, $group_size));
        }
        return $row;
    }
    /**
     * @return C_NextGen_Pro_Tiled_Gallery_Row[]
     */
    public function get_grouped_images()
    {
        $grouped_images = array();
        while (!empty($this->images)) {
            $grouped_images[] = new C_NextGen_Pro_Tiled_Gallery_Row($this->read_row());
        }
        return $grouped_images;
    }
    // TODO: split in functions
    // TODO: do not stretch images
    /**
     * @param int $width
     */
    public function apply_content_width($width)
    {
        foreach ($this->grouped_images as $row) {
            $row->width = $width;
            $row->raw_height = 1 / $row->ratio * ($width - $this->margin * (count($row->groups) - $row->weighted_ratio));
            $row->height = round($row->raw_height);
            $this->calculate_group_sizes($row);
        }
    }
    /**
     * @param C_NextGen_Pro_Tiled_Gallery_Row $row
     */
    public function calculate_group_sizes($row)
    {
        // Storing the calculated group heights in an array for rounding them later while preserving their sum
        // This fixes the rounding error that can lead to a few ugly pixels sticking out in the gallery
        $group_widths_array = array();
        foreach ($row->groups as $group) {
            $group->height = $row->height;
            // Storing the raw calculations in a separate property to prevent rounding errors from cascading down and for diagnostics
            $group->raw_width = ($row->raw_height - $this->margin * count($group->images)) * $group->ratio + $this->margin;
            $group_widths_array[] = $group->raw_width;
        }
        $rounded_group_widths_array = C_NextGen_Pro_Constrained_Array_Rounding::get_rounded_constrained_array($group_widths_array, $row->width);
        foreach ($row->groups as $group) {
            $group->width = array_shift($rounded_group_widths_array);
            $this->calculate_image_sizes($group);
        }
    }
    /**
     * @param C_NextGen_Pro_Tiled_Gallery_Group $group
     */
    public function calculate_image_sizes($group)
    {
        // Storing the calculated image heights in an array for rounding them later while preserving their sum
        // This fixes the rounding error that can lead to a few ugly pixels sticking out in the gallery
        $image_heights_array = array();
        foreach ($group->images as $image) {
            $image->tile_meta['width'] = $group->width - $this->margin;
            // Storing the raw calculations in a separate property for diagnostics
            $image->raw_height = ($group->raw_width - $this->margin) / $image->tile_meta['ratio'];
            $image_heights_array[] = $image->raw_height;
        }
        $image_height_sum = $group->height - count($image_heights_array) * $this->margin;
        $rounded_image_heights_array = C_NextGen_Pro_Constrained_Array_Rounding::get_rounded_constrained_array($image_heights_array, $image_height_sum);
        foreach ($group->images as $image) {
            $image->tile_meta['height'] = array_shift($rounded_image_heights_array);
        }
    }
}
class C_NextGen_Pro_Tiled_Gallery_Row
{
    /** @var array C_NextGen_Pro_Tiled_Gallery_Group[] */
    public $groups = array();
    /** @var int|null */
    public $ratio = NULL;
    /** @var float|int|null */
    public $weighted_ratio = NULL;
    /**
     * @param C_NextGen_Pro_Tiled_Gallery_Group[] $groups
     */
    public function __construct($groups)
    {
        $this->groups = $groups;
        $this->ratio = $this->get_ratio();
        $this->weighted_ratio = $this->get_weighted_ratio();
    }
    /**
     * @return float|int
     */
    public function get_ratio()
    {
        $ratio = 0;
        foreach ($this->groups as $group) {
            $ratio += $group->ratio;
        }
        return $ratio > 0 ? $ratio : 1;
    }
    /**
     * @return float|int
     */
    public function get_weighted_ratio()
    {
        $weighted_ratio = 0;
        foreach ($this->groups as $group) {
            $weighted_ratio += $group->ratio * count($group->images);
        }
        return $weighted_ratio > 0 ? $weighted_ratio : 1;
    }
}
class C_NextGen_Pro_Tiled_Gallery_Group
{
    /** @var stdClass[] NextGen image objects */
    public $images = array();
    /** @var float|int|null */
    public $ratio = NULL;
    /**
     * @param stdClass[] $images NextGen image objects
     */
    public function __construct($images)
    {
        $this->images = $images;
        $this->ratio = $this->get_ratio();
    }
    /**
     * @return float|int
     */
    public function get_ratio()
    {
        $ratio = 0;
        foreach ($this->images as $image) {
            if ($image->tile_meta['ratio']) {
                $ratio += 1 / $image->tile_meta['ratio'];
            }
        }
        if (!$ratio) {
            return 1;
        }
        return 1 / $ratio;
    }
    /**
     * @return C_NextGen_Pro_Tiled_Gallery_Rectangular_Item[]
     */
    public function items()
    {
        $items = array();
        foreach ($this->images as $image) {
            $items[] = new C_NextGen_Pro_Tiled_Gallery_Rectangular_Item($image);
        }
        return $items;
    }
}
/**
 * Lets you round the numeric elements of an array to integers while preserving their sum.
 *
 * Usage:
 *
 * C_NextGen_Pro_Constrained_Array_Rounding::get_rounded_constrained_array($bound_array)
 * if a specific sum doesn't need to be specified for the bound array
 *
 * C_NextGen_Pro_Constrained_Array_Rounding::get_rounded_constrained_array($bound_array, $sum)
 * If the sum of $bound_array must equal $sum after rounding.
 *
 * If $sum is less than the sum of the floor of the elements of the array, the class defaults to using the sum of the array elements.
 */
class C_NextGen_Pro_Constrained_Array_Rounding
{
    public static function get_rounded_constrained_array($bound_array, $sum = false)
    {
        // Convert associative arrays before working with them and convert them back before returning the values
        $keys = array_keys($bound_array);
        $bound_array = array_values($bound_array);
        $bound_array_int = self::get_int_floor_array($bound_array);
        $lower_sum = array_sum(wp_list_pluck($bound_array_int, 'floor'));
        if (!$sum || $sum < $lower_sum) {
            // If value of sum is not supplied or is invalid, calculate the sum that the returned array is constrained to match
            $sum = array_sum($bound_array);
        }
        $diff_sum = $sum - $lower_sum;
        self::adjust_constrained_array($bound_array_int, $diff_sum);
        $bound_array_fin = wp_list_pluck($bound_array_int, 'floor');
        return array_combine($keys, $bound_array_fin);
    }
    private static function get_int_floor_array($bound_array)
    {
        $bound_array_int_floor = array();
        foreach ($bound_array as $i => $value) {
            $bound_array_int_floor[$i] = array('floor' => (int) floor($value), 'fraction' => $value - floor($value), 'index' => $i);
        }
        return $bound_array_int_floor;
    }
    private static function adjust_constrained_array(&$bound_array_int, $adjustment)
    {
        usort($bound_array_int, array('self', 'cmp_desc_fraction'));
        $start = 0;
        $end = $adjustment - 1;
        $length = count($bound_array_int);
        for ($i = $start; $i <= $end; $i++) {
            $bound_array_int[$i % $length]['floor']++;
        }
        usort($bound_array_int, array('self', 'cmp_asc_index'));
    }
    private static function cmp_desc_fraction($a, $b)
    {
        if ($a['fraction'] == $b['fraction']) {
            return 0;
        }
        return $a['fraction'] > $b['fraction'] ? -1 : 1;
    }
    private static function cmp_asc_index($a, $b)
    {
        if ($a['index'] == $b['index']) {
            return 0;
        }
        return $a['index'] < $b['index'] ? -1 : 1;
    }
}
/**
 * @mixin C_Form
 * @adapts I_Form for "photocrati-nextgen_pro_tile" context
 */
class A_NextGen_Pro_Tile_Form extends Mixin_Display_Type_Form
{
    function get_display_type_name()
    {
        return NGG_PRO_TILE;
    }
    function enqueue_static_resources()
    {
        wp_enqueue_script($this->get_display_type_name() . '-js', $this->get_static_url('photocrati-nextgen_pro_tile#settings.js'));
        $atp = C_Attach_Controller::get_instance();
        if ($atp != null && $atp->has_method('mark_script')) {
            $atp->mark_script($this->object->get_display_type_name() . '-js');
        }
    }
    /**
     * Returns a list of fields to render on the settings page
     */
    function _get_field_names()
    {
        return array('nextgen_pro_tile_override_maximum_width', 'nextgen_pro_tile_maximum_width');
    }
    /**
     * @param C_Display_Type $display_type
     * @return string Rendered HTML
     */
    function _render_nextgen_pro_tile_override_maximum_width_field($display_type)
    {
        return $this->_render_radio_field($display_type, 'override_maximum_width', __('Override maximum gallery width', 'nextgen-gallery-pro'), $display_type->settings['override_maximum_width'], __("Gallery width is set to your theme's content width but this can be overridden to create smaller galleries. If your theme does not provide the \$content_width feature the default will fallback to 2000px.", 'nextgen-gallery-pro'));
    }
    /**
     * @param C_Display_Type $display_type
     * @return string Rendered HTML
     */
    function _render_nextgen_pro_tile_maximum_width_field($display_type)
    {
        return $this->_render_number_field($display_type, 'maximum_width', __('Maximum gallery width', 'nextgen-gallery-pro'), $display_type->settings['maximum_width'], __('Measured in pixels', 'nextgen-gallery-pro'), empty($display_type->settings['override_maximum_width']) ? TRUE : FALSE, '', 100);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string Rendered HTML
     */
    function _render_nextgen_pro_tile_margin_field($display_type)
    {
        return $this->_render_number_field($display_type, 'margin', __('Margin between blocks of images', 'nextgen-gallery-pro'), $display_type->settings['margin'], __('Measured in pixels', 'nextgen-gallery-pro'), FALSE, 0, 0);
    }
}
/**
 * @mixin C_Display_Type_Mapper
 * @adapts I_Display_Type_Mapper
 */
class A_NextGen_Pro_Tile_Mapper extends Mixin
{
    function set_defaults($entity)
    {
        $this->call_parent('set_defaults', $entity);
        if (isset($entity->name) && $entity->name == NGG_PRO_TILE) {
            $this->object->_set_default_value($entity, 'settings', 'override_maximum_width', '0');
            $this->object->_set_default_value($entity, 'settings', 'maximum_width', '2000');
            $this->object->_set_default_value($entity, 'settings', 'margin', '4');
            $this->object->_set_default_value($entity, 'settings', 'ngg_triggers_display', 'never');
            $this->object->_set_default_value($entity, 'settings', 'is_ecommerce_enabled', '1');
            $this->object->_set_default_value($entity, 'settings', 'ngg_proofing_display', '0');
        }
    }
}
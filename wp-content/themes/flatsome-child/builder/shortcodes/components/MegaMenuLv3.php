<?php
function MegaMenuLv3($atts, $content)
{
    extract(shortcode_atts(array(
        'is_taxonomy' => true,
        'cat' => 174,
        'link_mega_menu' => 'javascript:void(0)',
        'label_mega_menu' => '',
        'class' => '',
    ), $atts));
    ob_start();

    // Kiểm tra xem ID thuộc về taxonomy nào
    $term_in_cat = get_term($cat, 'concept');
    $term_in_brand = get_term($cat, 'school-level');
    $term_in_tag = get_term($cat, 'district');
    $term_in_collection = get_term($cat, 'tour-album');
    
    $taxonomy = '';
    $category = null;


    if ($term_in_cat && !is_wp_error($term_in_cat) && $term_in_cat->term_id) {
        $taxonomy = 'concept';
        $category = $term_in_cat;
    } elseif ($term_in_brand && !is_wp_error($term_in_brand) && $term_in_brand->term_id) {
        $taxonomy = 'school-level';
        $category = $term_in_brand;
    } elseif ($term_in_tag && !is_wp_error($term_in_tag) && $term_in_tag->term_id) {
        $taxonomy = 'district';
        $category = $term_in_tag;
    } elseif ($term_in_collection && !is_wp_error($term_in_collection) && $term_in_collection->term_id) {
        $taxonomy = 'tour-album';
        $category = $term_in_collection;
    }

    // Nếu không tìm thấy term trong các taxonomy
    if (empty($taxonomy)) {
        echo 'No categories found.';
        return;
    }

?>
    <div class="_6bdx <?php echo $class; ?>">
        <?php
        if($is_taxonomy && $category){ ?>
            <div class="_8fjk">
                <a href="<?php echo esc_url(get_term_link($category)); ?>" class="_4faj"><?php echo $category->name; ?></a>
            </div>
        <?php }else{ ?>
            <div class="_8fjk">
                <a href="<?php echo $link_mega_menu; ?>" class="_4faj"><?php echo $label_mega_menu; ?></a>
            </div>
        <?php } ?>
    </div>
<?php
    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('MegaMenuLv3', 'MegaMenuLv3');

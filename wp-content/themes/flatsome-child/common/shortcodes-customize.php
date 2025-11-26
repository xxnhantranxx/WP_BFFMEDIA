<?php
// shortcode Năm
function SearchButton()
{
    ob_start();?>
    <div class="SearchButton">
        <div class="icon-search-customize" aria-label="Search">
            <img src="<?php echo get_stylesheet_directory_uri(); ?>/img/icons/icon_search.png" alt="Search" class="search-icon">
        </div>
        <div class="content-search">
            <?php echo get_search_form(); ?>
        </div>
	</div>
    <?php
    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}
add_shortcode('SearchButton', 'SearchButton');


// Get news widget
function newsWidget()
{
    ob_start();
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 3,
    );
    $the_query = new WP_Query($args);
?>
    <div class="wapper-footer-3 widget-sidebar">
        <div class="block_footer">
            <ul class="list-blogs">
                <?php // The Loop
                if ($the_query->have_posts()) :
                    while ($the_query->have_posts()) : $the_query->the_post();
                ?>
                        <li class="item-post">
                            <a href="<?php the_permalink(); ?>">
                                <div class="date-meta">
                                    <span class="day"><?php the_time('d'); ?></span>
                                    <span class="month"><?php the_time('M'); ?></span>
                                </div>
                                <div class="title-post-footer">
                                    <div class="cate-show">
                                        <?php
                                            $term_list = get_the_terms(get_the_ID(), 'category');
                                            $types ='';
                                            foreach($term_list as $tag_name){
                                                $types .= '<p>'.$tag_name->name.'</p>';
                                            }
                                            echo $types;
                                        ?>
                                    </div>
                                    <h2 class="textLine-2"><?php the_title(); ?></h2>
                                </div>
                            </a>
                        </li>
                <?php
                    endwhile;
                endif;
                // Reset Post Data
                wp_reset_postdata(); ?>
            </ul>
        </div>
    </div>
<?php
    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}
add_shortcode('SC_newsWidget', 'newsWidget');


function tagWidgetSidebar()
{
    ob_start();?>
    <div class="wapper-footer-4">
        <div class="block_footer">
            <h4 class="title-widget">Tags</h4>
            <div class="list-tag-footer">
    <?php
    $tags = get_tags(array(
        'orderby' => 'count',
        'order' => 'DESC',
        'number' => 10 // Số lượng tags muốn hiển thị
    ));

    if ($tags) :
        foreach ($tags as $tag) :
            echo '<a href="' . get_tag_link($tag->term_id) . '">' . $tag->name . '</a>';
        endforeach;
    endif; ?>
            </div>
        </div>
    </div>
    <?php
    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}
add_shortcode('SC_tagWidgetSidebar', 'tagWidgetSidebar');

//Topbar Info
function TopbarInfo()
{
  ob_start(); ?>
    <ul class="beryl-draw">
        <li class="_9twx">
            <img src="<?php echo get_stylesheet_directory_uri(); ?>/img/icons/phone_topbar.png" class="_0xha">
            <a href="tel:<?php echo get_field('phone_topbar', 'option'); ?>"><?php echo get_field('phone_topbar', 'option'); ?></a>
        </li>
        <li class="_9twx">
            <img src="<?php echo get_stylesheet_directory_uri(); ?>/img/icons/email_icon_topbar.png" class="_0xha">
            <a href="mailto:<?php echo get_field('email_topbar', 'option'); ?>"><?php echo get_field('email_topbar', 'option'); ?></a>
        </li>
    </ul>
<?php
  $contentShortcode = ob_get_contents();
  ob_end_clean();
  return $contentShortcode;
}
add_shortcode('TopbarInfo', 'TopbarInfo');
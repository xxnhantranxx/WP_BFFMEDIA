<?php
// Shortcode build
function Partner($atts)
{
    $atts = shortcode_atts(array(
        'class' => '',
    ), $atts);
    
    ob_start();
    
    // Kiểm tra và lấy dữ liệu partner một lần duy nhất
    if (have_rows('partner', 'option')) {
        // Lưu tất cả dữ liệu vào mảng để tránh truy vấn lặp lại
        $partners = array();
        while (have_rows('partner', 'option')) : the_row();
            $partners[] = array(
                'name' => get_sub_field('name_partner', 'option'),
                'image' => get_sub_field('image_logo_partner', 'option'),
                'url' => get_sub_field('url_partner', 'option'),
            );
        endwhile;
        
        // Hàm helper để render item partner
        $render_partner_item = function($partner) {
            $name = esc_attr($partner['name']);
            $image = esc_url($partner['image']);
            $url = esc_url($partner['url']);
            
            return sprintf(
                '<li class="item_Partner">
                    <div class="_1ldb">
                        <a href="%s" class="_3byi block" title="%s" target="_blank">
                            <img src="%s" class="_6aya" alt="%s">
                        </a>
                    </div>
                </li>',
                $url,
                $name,
                $image,
                $name
            );
        };
        
        // Hàm helper để render danh sách partner
        $render_partner_list = function($partners, $render_item) {
            $items = '';
            foreach ($partners as $partner) {
                $items .= $render_item($partner);
            }
            return '<ul class="list_Partner">' . $items . '</ul>';
        };
        
        $class = esc_attr($atts['class']);
        $list_html = $render_partner_list($partners, $render_partner_item);
        ?>
        <div class="Partner <?php echo $class; ?>">
            <div class="_5gcf">
                <?php echo $list_html; ?>
                <?php echo $list_html; ?>
                <?php echo $list_html; ?>
                <?php echo $list_html; ?>
            </div>
            <div class="_5gcf reverse">
                <?php echo $list_html; ?>
                <?php echo $list_html; ?>
                <?php echo $list_html; ?>
                <?php echo $list_html; ?>
            </div>
        </div>
        <?php
    }
    
    $contentShortcode = ob_get_clean();
    return $contentShortcode;
}

add_shortcode('Partner', 'Partner');

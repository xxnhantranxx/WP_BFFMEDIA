<?php
// Add page option
$url_icon = get_site_icon_url();
if( function_exists('acf_add_options_page') ) {
	acf_add_options_page(array(
		'page_title' 	=> 'Theme Options',
		'menu_title'	=> 'Theme Options',
		'menu_slug' 	=> 'theme-general-settings',
		'capability'	=> 'edit_posts',
		'icon_url' => $url_icon,
		'redirect'		=> false
	));
}

/*
 * Đặt đánh giá sao thành 4>5 sao cho kk star rating
 * Code này đã kèm thêm đánh giá các bài viết chưa được đánh giá
 * Author: levantoan.com
 * */
add_action( 'wp_ajax_update_kk_star_rating', 'devvn_update_kk_star_rating_func' );
function devvn_update_kk_star_rating_func() {
 
    global $wpdb;
 
    $posts_with_low_avg = $wpdb->get_results("
        SELECT p.ID as post_id, pm.meta_value as avg_rating
        FROM $wpdb->posts p
        LEFT JOIN $wpdb->postmeta pm
        ON p.ID = pm.post_id 
        AND pm.meta_key = '_kksr_avg'
        WHERE (pm.meta_value IS NULL OR CAST(pm.meta_value AS DECIMAL(3,2)) < 4)
        AND p.post_type IN ('album')
        AND p.post_status = 'publish'
    ");
 
    $posts_with_low_avg_table = array();
 
    if (!empty($posts_with_low_avg)) {
        foreach ($posts_with_low_avg as $post) {
            $post_id = $post->post_id;
 
            $count = (int) get_post_meta($post_id, '_kksr_count_default', true);
            $old_avg = (float) get_post_meta($post_id, '_kksr_avg_default', true);
 
            if(!$count) $count = rand(2340,5320); //chỗ này là lấy rand tổng số lần đánh giá nếu chưa có đánh giá nào. có thể thay đổi cho nhiều hơn
 
            $random_rating = mt_rand(48, 50) / 10; //chỗ này là số sao mong muốn. ví dụ muốn từ 4.5 đến 5 sao thì đổi thành mt_rand(45, 50) / 10
 
            $new_ratings = $count * $random_rating;
 
            $new_avg = $new_ratings / $count;
 
            update_post_meta($post_id, '_kksr_ratings_default', $new_ratings);
            update_post_meta($post_id, '_kksr_ratings', $new_ratings);
 
            update_post_meta($post_id, '_kksr_count_default', $count);
            update_post_meta($post_id, '_kksr_casts', $count);
 
            update_post_meta($post_id, '_kksr_avg_default', $new_avg);
            update_post_meta($post_id, '_kksr_avg', $new_avg);
 
            $posts_with_low_avg_table[] = compact('post_id', 'old_avg', 'new_avg');
 
        }
 
        $mess = '<span style="color: red;">Đã cập nhật đánh giá.</span>';
 
    } else {
        $mess = '<span style="color: green;">Không có bài viết nào cần cập nhật.</span>';
    }
 
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Đặt điểm đánh giá thủ công cho kk star rating</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                border: 0;
                font-size: 100%;
                font: inherit;
                vertical-align: baseline;
                line-height: 1;
                box-sizing: border-box;
                -moz-box-sizing: border-box;
                -webkit-box-sizing: border-box;
            }
            body {
                font-size: 14px;
            }
            .container{
                padding: 20px;
                max-width: 900px;
                width: 100%;
                margin: 0 auto;
            }
            table {
                width: 100%;
                border: 1px solid #ddd;
                border-collapse: collapse;
                border-spacing: 0;
            }
            table td, table th {
                padding: 5px;
                text-align: center;
                border: 1px solid #ddd;
            }
            h1 {
                font-size: 20px;
                text-align: center;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
 
    <div class="container">
        <h1><?php echo $mess;?></h1>
        <?php
        if($posts_with_low_avg_table){
            ?>
            <table>
                <thead>
                <tr>
                    <td>ID</td>
                    <td>Tên bài</td>
                    <td>Điểm đánh giá cũ</td>
                    <td>Điểm đánh giá mới</td>
                    <td>Link tới bài viết</td>
                </tr>
                </thead>
                <tbody>
                <?php
                foreach ($posts_with_low_avg_table as $item){
                    extract($item);
                    ?>
                    <tr>
                        <td><?php echo $post_id;?></td>
                        <td><?php echo get_the_title($post_id);?></td>
                        <td><?php echo $old_avg;?></td>
                        <td><?php echo $new_avg;?></td>
                        <td><a href="<?php echo get_the_permalink($post_id);?>" title="Xem bài viết" target="_blank">Xem bài viết</a> </td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
            <?php
        }
        ?>
    </div>
    </body>
    </html>
 
    <?php
 
    die();
}
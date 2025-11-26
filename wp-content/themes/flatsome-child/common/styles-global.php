<?php
add_action('wp_head','hook_css');
function hook_css()
{
?> <!-- Closing the PHP here -->
    <style>
        body {
            --Blur:<?php echo get_field('blur','option'); ?>;
            --Mau_1: <?php echo get_field('mau_1','option'); ?>;
            --Mau_2: <?php echo get_field('mau_2','option'); ?>;
            --Mau_3: <?php echo get_field('mau_3','option'); ?>;
            --Mau_4: <?php echo get_field('mau_4','option'); ?>;
            --Mau_5: <?php echo get_field('mau_5','option'); ?>;
            --Mau_6: <?php echo get_field('mau_6','option'); ?>;
            --Mau_7: <?php echo get_field('mau_7','option'); ?>;
            --Mau_8: <?php echo get_field('mau_8','option'); ?>;
            --Mau_9: <?php echo get_field('mau_9','option'); ?>;
        }
    </style>
<?php //Opening the PHP tag again
}
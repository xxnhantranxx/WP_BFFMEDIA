<?php

function Faqs($atts, $content)
{
    extract(shortcode_atts(array(
        'class' => '',
        'img' => '',
        'group_faq' => 'style1',
    ), $atts));
    ob_start();
    if ($group_faq == 'style1') {
        $row = 'faqs_chi_phi';
        $question = 'cau_hoi_chi_phi';
        $answer = 'cau_tra_loi_chi_phi';
    } elseif ($group_faq == 'style2') {
        $row = 'faqs_ky_yeu';
        $question = 'cau_hoi_ky_yeu';
        $answer = 'cau_tra_loi_ky_yeu';
    } elseif ($group_faq == 'style3') {
        $row = 'faqs_quay_video';
        $question = 'cau_hoi_quay_video';
        $answer = 'cau_tra_loi_quay_video';
    } elseif ($group_faq == 'style4') {
        $row = 'faqs_tour';
        $question = 'cau_hoi_tour';
        $answer = 'cau_tra_loi_tour';
    } elseif ($group_faq == 'style5') {
        $row = 'faqs_hau_ky_in_an';
        $question = 'cau_hoi_hau_ky_in_an';
        $answer = 'cau_tra_loi_hau_ky_in_an';
    }
?>
    <div class="Faqs <?php echo $class; ?>">
        <div class="_5wkl">
            <img src="<?php echo wp_get_attachment_image_url($img, 'full'); ?>" class="_3hqs">
        </div>
        <div class="_2brp">
            <div class="_8hgk"><?php echo do_shortcode($content); ?></div>
            <?php
            // Check rows exists.
            if (have_rows($row, 'option')): ?>
                <div class="ListFaqs">
                    <?php
                    // Loop through rows.
                    while (have_rows($row, 'option')) : the_row();
                        // Load sub field value.
                        $cau_hoi = get_sub_field($question, 'option');
                        $cau_tra_loi = get_sub_field($answer, 'option');
                    ?>
                        <div class="_3wuk FaqsItem">
                            <div class="_6gms heading-faq"><?php echo $cau_hoi; ?></div>
                            <div class="_5ncd content-faq"><?php echo $cau_tra_loi; ?></div>
                        </div>
                    <?php
                    endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php

    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('Faqs', 'Faqs');

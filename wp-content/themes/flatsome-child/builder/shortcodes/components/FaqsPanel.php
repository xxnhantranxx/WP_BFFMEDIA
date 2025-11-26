<?php

function FaqsPanel($atts)
{
    extract(shortcode_atts(array(
        'class' => '',
        'title' => '',
    ), $atts));
    ob_start();
?>
    <div class="FaqsPanel <?php echo $class; ?>">
        <div class="_3eun tab-head">
            <h2><?php echo $title; ?></h2>
            <div class="_1clx">
                <div class="_2szt tab-head-item active"><span class="_2ggn">Dịch vụ chụp ảnh kỷ yếu</span><i class="fa-light fa-arrow-right"></i></div>
                <div class="_2szt tab-head-item"><span class="_2ggn">Quay video – MV kỷ yếu</span><i class="fa-light fa-arrow-right"></i></div>
                <div class="_2szt tab-head-item"><span class="_2ggn">Tour du lịch kỷ yếu</span><i class="fa-light fa-arrow-right"></i></div>
                <div class="_2szt tab-head-item"><span class="_2ggn">Chi phí & thanh toán</span><i class="fa-light fa-arrow-right"></i></div>
                <div class="_2szt tab-head-item"><span class="_2ggn">Hậu kỳ & in ấn</span><i class="fa-light fa-arrow-right"></i></div>
            </div>
        </div>
        <div class="_4qsa tab-contents">
            <div class="tab-content active FaqsKyYeu">
                <div class="_0npn">Dịch vụ chụp ảnh kỷ yếu</div>
                <div class="_0yzi">
                <?php
                    // Check rows exists.
                    if (have_rows('faqs_ky_yeu', 'option')): ?>
                        <div class="ListFaqs">
                            <?php
                            // Loop through rows.
                            while (have_rows('faqs_ky_yeu', 'option')) : the_row();
                                // Load sub field value.
                                $cau_hoi = get_sub_field('cau_hoi_ky_yeu', 'option');
                                $cau_tra_loi = get_sub_field('cau_tra_loi_ky_yeu', 'option');
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
            </div>
            <div class="tab-content FaqsQuayVideo">
                <div class="_0npn">Quay video – MV kỷ yếu</div>
                <div class="_0yzi">
                <?php
                    // Check rows exists.
                    if (have_rows('faqs_quay_video', 'option')): ?>
                        <div class="ListFaqs">
                            <?php
                            // Loop through rows.
                            while (have_rows('faqs_quay_video', 'option')) : the_row();
                                // Load sub field value.
                                $cau_hoi = get_sub_field('cau_hoi_quay_video', 'option');
                                $cau_tra_loi = get_sub_field('cau_tra_loi_quay_video', 'option');
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
            </div>
            <div class="tab-content FaqsTour">
                <div class="_0npn">Tour du lịch kỷ yếu</div>
                <div class="_0yzi">
                <?php
                    // Check rows exists.
                    if (have_rows('faqs_tour', 'option')): ?>
                        <div class="ListFaqs">
                            <?php
                            // Loop through rows.
                            while (have_rows('faqs_tour', 'option')) : the_row();
                                // Load sub field value.
                                $cau_hoi = get_sub_field('cau_hoi_tour', 'option');
                                $cau_tra_loi = get_sub_field('cau_tra_loi_tour', 'option');
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
            </div>
            <div class="tab-content FaqsChiPhiThanhToan">
                <div class="_0npn">Chi phí & thanh toán</div>
                <div class="_0yzi">
                <?php
                    // Check rows exists.
                    if (have_rows('faqs_chi_phi', 'option')): ?>
                        <div class="ListFaqs">
                            <?php
                            // Loop through rows.
                            while (have_rows('faqs_chi_phi', 'option')) : the_row();
                                // Load sub field value.
                                $cau_hoi = get_sub_field('cau_hoi_chi_phi', 'option');
                                $cau_tra_loi = get_sub_field('cau_tra_loi_chi_phi', 'option');
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
            </div>
            <div class="tab-content FaqsHauKyInAn">
                <div class="_0npn">Hậu kỳ & in ấn</div>
                <div class="_0yzi">
                <?php
                    // Check rows exists.
                    if (have_rows('faqs_hau_ky_in_an', 'option')): ?>
                        <div class="ListFaqs">
                            <?php
                            // Loop through rows.
                            while (have_rows('faqs_hau_ky_in_an', 'option')) : the_row();
                                // Load sub field value.
                                $cau_hoi = get_sub_field('cau_hoi_hau_ky_in_an', 'option');
                                $cau_tra_loi = get_sub_field('cau_tra_loi_hau_ky_in_an', 'option');
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
            </div>
        </div>
    <?php

    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('FaqsPanel', 'FaqsPanel');

<?php get_header(); ?>
<section class="section HotTrendAlbum">
    <div class="section-bg fill"></div>
    <div class="section-content relative">
        <div class="row row-small">
            <div class="col large-12 medium-12 small-12 pb-0">
                <div class="col-inner">
                    <div class="poring-bins">
                        <div class="DetaiTopTour">
                            <div class="box-album">
                                <div class="BoxLeft">
                                    <?php if (get_field('hot_trend_tour')) { ?>
                                        <div class="_5pkq"><span>Tour nổi bật</span></div>
                                    <?php } ?>
                                    <div class="_6qdm"><?php the_title(); ?></div>
                                    <div class="_0rty">
                                        <div class="_8hzx">
                                            <span class="_9gkk"><?php echo get_field('price_tour'); ?></span>
                                            <span class="_9cbq">/</span>
                                            <span class="_5hhw"><?php echo get_field('quantity_tour'); ?></span>
                                        </div>
                                        <ul class="_2qoy">
                                            <?php if (get_field('date_tour')): ?>
                                                <li class="_0tmz"><span>Thời gian: </span><?php echo get_field('date_tour'); ?></li>
                                            <?php endif; ?>
                                            <?php if (get_field('location_tour')): ?>
                                                <li class="_0tmz"><span>Địa điểm: </span><?php echo get_field('location_tour'); ?></li>
                                            <?php endif; ?>
                                        </ul>
                                        <?php if (get_field('description_short_tour')): ?>
                                            <p class="_2vpq"><?php echo get_field('description_short_tour'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ButtonBookingGroup">
                                        <a href="#lightbox-album" class="button Booking" data-concept="<?php the_ID(); ?>"><span>Đặt tour kỷ yếu này</span></a>
                                    </div>
                                </div>
                                <div class="BoxRight">
                                    <div class="image-box">
                                        <?php echo get_the_post_thumbnail(get_the_ID(), 'full'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<section class="section MainSingleTour">
    <div class="section-bg fill"></div>
    <div class="section-content relative">
        <div class="row row-small mst_r1">
            <div class="col large-8 medium-8 small-12 mst_r1_c1">
                <div class="col-inner">
                    <div class="_1sqk">
                        <div class="_4obi">
                            <?php the_content(); ?>
                        </div>
                    </div>
                    <?php
                    // Check rows exists.
                    if (have_rows('dich_vu_bao_gom')): ?>
                        <div class="_0lub mb-30">
                            <div class="_4ezv">Dịch vụ bao gồm</div>
                            <div class="_9teg">
                                <?php
                                // Loop through rows.
                                while (have_rows('dich_vu_bao_gom')) : the_row();
                                    // Load sub field value.
                                    $ten_dich_vu_bao_gom = get_sub_field('ten_dich_vu_bao_gom');
                                ?>
                                    <div class="_8hcr">
                                        <i class="fa-light fa-check-double"></i>
                                        <span class="_8xnx"><?php echo $ten_dich_vu_bao_gom; ?></span>
                                    </div>
                                <?php
                                endwhile; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php
                    // Check rows exists.
                    if (have_rows('dich_vu_khong_bao_gom')): ?>
                        <div class="_0lub">
                            <div class="_4ezv">Dịch vụ không bao gồm</div>
                            <div class="_9teg">

                                <?php
                                // Loop through rows.
                                while (have_rows('dich_vu_khong_bao_gom')) : the_row();
                                    // Load sub field value.
                                    $ten_dich_vu_khong_bao_gom = get_sub_field('ten_dich_vu_khong_bao_gom');
                                ?>
                                    <div class="_8hcr">
                                        <i class="fa-light fa-xmark"></i>
                                        <span class="_8xnx"><?php echo $ten_dich_vu_khong_bao_gom; ?></span>
                                    </div>
                                <?php
                                endwhile; ?>

                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col large-4 medium-4 small-12 mst_r1_c1">
                <div class="col-inner sticky-sidebar">
                    <?php echo do_shortcode('[contact-form-7 id="1e090e8" title="Single Tour"]'); ?>
                    <div id="price-tour">
                        <?php
                        // Check rows exists.
                        if (have_rows('price_single_tour')): ?>
                            <?php
                            // Loop through rows.
                            while (have_rows('price_single_tour')) : the_row();
                                // Load sub field value.
                                $quantity_single_tour = get_sub_field('quantity_single_tour');
                                $price_detail_single_tour = get_sub_field('price_detail_single_tour');
                            ?>
                                <div class="price-item">
                                    <div class="price-item-title"><?php echo $quantity_single_tour; ?></div>
                                    <div class="price-item-price"><?php echo $price_detail_single_tour; ?></div>
                                </div>
                            <?php
                            endwhile; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php
// Check rows exists.
$images = get_field('thu_vien_anh_tour');
if ($images && is_array($images) && count($images) > 0): ?>
<section class="section GalleryTour">
	<div class="section-bg fill"></div>
	<div class="section-content relative">
		<div class="row row-small gallery_r1">
			<div class="col large-12 medium-12 small-12 gallery_r1_c1 pb-0">
				<div class="col-inner">
					<div class="heading_s8">
                        <h2>Hình ảnh thực tế từ khách hàng</h2>
                    </div>
				</div>
			</div>
            <div class="col large-12 medium-12 small-12 gallery_r1_c2 pb-0">
				<div class="col-inner">
					<div class="_3rac swiper">
                        <div class="swiper-wrapper">
                        <?php foreach ($images as $image): ?>
                            <div class="_3r1r swiper-slide">
                                <div class="_3r1s">
                                    <img src="<?php echo $image; ?>" class="_6yne">
                                </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="cntt-navigation">
                            <div class="cntt-button-gallery-prev cntt-button"><i class="fa-light fa-arrow-left-long"></i></div>
                            <div class="cntt-button-gallery-next cntt-button"><i class="fa-light fa-arrow-right-long"></i></div>
                        </div>
                    </div>
				</div>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>
<?php echo do_shortcode('[block id="cam-nhan-khach-hang"]'); ?>
<?php echo do_shortcode('[block id="tour-ky-yeu-de-xuat"]'); ?>
<?php echo do_shortcode('[block id="tin-tuc"]'); ?>
<?php get_footer(); ?>
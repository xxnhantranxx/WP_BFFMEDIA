jQuery(document).ready(function ($) {
    $(document).ready(function () {
        $('.copy-shiping').click(function() {
            var str = $('.copy-shiping > .shipping-adress').html();
            function listener(e) {
                e.clipboardData.setData("text/html", str);
                e.clipboardData.setData("text/plain", str);
                e.preventDefault();
            }
            document.addEventListener("copy", listener);
            document.execCommand("copy");
            document.removeEventListener("copy", listener);

            $(this).children('.icon-copy').addClass('copied');
            $(this).children('.icon-copy').text('Đã copy vào bộ nhớ tạm');
            setTimeout(() => {
                $(this).children('.icon-copy').removeClass('copied');
                $(this).children('.icon-copy').text('');
            }, 3000);
        });
        $('.wpcvb-popup-editor .select_all_attributes_style').click(function() {
            $('.wpcvb-popup-editor .wpcvb_attribute[name="pa_style"]').select2('open'); // Mở dropdown của select
            $('.wpcvb-popup-editor .wpcvb_attribute[name="pa_style"]').val($('.wpcvb-popup-editor .wpcvb_attribute[name="pa_style"] option').not('[value="wpcvb_any"]').map(function() {
                return $(this).val();
            })).trigger('change'); // Chọn tất cả các option
            $('.wpcvb-popup-editor .wpcvb_attribute[name="pa_style"]').select2('close'); // Đóng dropdown của select
        });

        $('.wpcvb-popup-editor .select_all_attributes_color').click(function() {
            $('.wpcvb-popup-editor .wpcvb_attribute[name="pa_color"]').select2('open'); // Mở dropdown của select
            $('.wpcvb-popup-editor .wpcvb_attribute[name="pa_color"]').val($('.wpcvb-popup-editor .wpcvb_attribute[name="pa_color"] option').not('[value="wpcvb_any"]').map(function() {
                return $(this).val();
            })).trigger('change'); // Chọn tất cả các option
            $('.wpcvb-popup-editor .wpcvb_attribute[name="pa_color"]').select2('close'); // Đóng dropdown của select
        });

        $('.wpcvb-popup-editor .select_all_attributes_size').click(function() {
            $('.wpcvb-popup-editor .wpcvb_attribute[name="pa_size"]').select2('open'); // Mở dropdown của select
            $('.wpcvb-popup-editor .wpcvb_attribute[name="pa_size"]').val($('.wpcvb-popup-editor .wpcvb_attribute[name="pa_size"] option').not('[value="wpcvb_any"]').map(function() {
                return $(this).val();
            })).trigger('change'); // Chọn tất cả các option
            $('.wpcvb-popup-editor .wpcvb_attribute[name="pa_size"]').select2('close'); // Đóng dropdown của select
        });

        $('.wpcvb-popup-generate .select_all_attributes_style').click(function() {
            $('.wpcvb-popup-generate .wpcvb_attribute[name="pa_style"]').select2('open'); // Mở dropdown của select
            $('.wpcvb-popup-generate .wpcvb_attribute[name="pa_style"]').val($('.wpcvb-popup-generate .wpcvb_attribute[name="pa_style"] option').not('[value="wpcvb_any"]').map(function() {
                return $(this).val();
            })).trigger('change'); // Chọn tất cả các option
            $('.wpcvb-popup-generate .wpcvb_attribute[name="pa_style"]').select2('close'); // Đóng dropdown của select
        });

        $('.wpcvb-popup-generate .select_all_attributes_color').click(function() {
            $('.wpcvb-popup-generate .wpcvb_attribute[name="pa_color"]').select2('open'); // Mở dropdown của select
            $('.wpcvb-popup-generate .wpcvb_attribute[name="pa_color"]').val($('.wpcvb-popup-generate .wpcvb_attribute[name="pa_color"] option').not('[value="wpcvb_any"]').map(function() {
                return $(this).val();
            })).trigger('change'); // Chọn tất cả các option
            $('.wpcvb-popup-generate .wpcvb_attribute[name="pa_color"]').select2('close'); // Đóng dropdown của select
        });

        $('.wpcvb-popup-generate .select_all_attributes_size').click(function() {
            $('.wpcvb-popup-generate .wpcvb_attribute[name="pa_size"]').select2('open'); // Mở dropdown của select
            $('.wpcvb-popup-generate .wpcvb_attribute[name="pa_size"]').val($('.wpcvb-popup-generate .wpcvb_attribute[name="pa_size"] option').not('[value="wpcvb_any"]').map(function() {
                return $(this).val();
            })).trigger('change'); // Chọn tất cả các option
            $('.wpcvb-popup-generate .wpcvb_attribute[name="pa_size"]').select2('close'); // Đóng dropdown của select
        });

        $('.wpcvb-popup-editor .remove_all_attributes_images').click(function() {
            $('.wpcvb-popup-editor .upload_image .upload_image_id').attr('value', '');
            $('.wpcvb-popup-editor .upload_image_button').removeClass('remove');
            $('.wpcvb-popup-editor .wpcvi-images-form-content > .wpcvi-images-ids').attr('value', '');
            $('.wpcvb-popup-editor .wpcvi-images-form-content > .wpcvi-images > li').remove();
        });
    });
});
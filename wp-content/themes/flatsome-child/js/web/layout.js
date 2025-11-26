jQuery(document).ready(function ($) {
    $(document).ready(function () {
        var widthWindow = $(window).width();
        $('#billing_country').select2({
            theme: "banking",
            containerCssClass: ':all:'
        });
        $('.woocommerce-billing-fields__field-wrapper>p.form-row input#billing_phone').closest('.phoneInputBill').prev('label').css(
            {
                "opacity": 1,
            }
        );

        $('.woocommerce-billing-fields__field-wrapper #billing_address_2_field').hide();
        $('<div class="option-address"><i class="fa-light fa-plus"></i> More apartments, suites, and more.</div>').insertBefore('#billing_address_2_field');
        $('.option-address').click(function () {
            $('.woocommerce-billing-fields__field-wrapper #billing_address_2_field').show();
            $(this).hide();
        })

        // Hiển thị trường #billing_state_field luôn luôn
        $('#billing_state_field').show();
        $('#billing_state').attr('type', 'text');
        $('#billing_state').removeClass('hidden');
        $('#billing_state').addClass('show');

        // Hiển thị trường #billing_postcode_field luôn luôn
        $('#billing_postcode_field').show();
        $('#billing_postcode').attr('type', 'text');
        $('#billing_postcode').removeClass('hidden');
        $('#billing_postcode').addClass('show');

        // Đảm bảo rằng trường này sẽ không bị ẩn khi chọn quốc gia khác
        $(document.body).on('country_to_state_changed', function(){
            $('#billing_state_field').show();
            $('#billing_state').attr('type', 'text'); 
            $('#billing_state').removeClass('hidden');
            $('#billing_state').addClass('show');

            $('#billing_postcode_field').show();
            $('#billing_postcode').attr('type', 'text'); 
            $('#billing_postcode').removeClass('hidden');
            $('#billing_postcode').addClass('show');
        });

        if (widthWindow < 550) {
            $('.review-toggle').click(function () {
                $(this).next('.dropdown-review-layout').slideToggle('fast');
                $(this).toggleClass('rotate');
                var textLable = $(this).find('.label span').text();
                if (textLable == 'Display information ') {
                    $(this).find('.label span').text('Display information ');
                } else {
                    $(this).find('.label span').text('Hide information ');
                }
            });
            $('.headding-order-summary .show-label').click(function () {
                $(this).parent('.headding-order-summary').next('.woocommerce-checkout-review-order').find('tbody').toggle();
                $(this).children('i').toggleClass('rotate');
            });
        }
    });
});
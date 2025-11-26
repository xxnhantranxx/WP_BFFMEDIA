<?php
// Add lightbox booking
function popup_form_contact()
{
  echo do_shortcode('[lightbox id="popup-form-contact" width="720px" padding="0px"][contact-form-7 id="6bab151" title="Contact form"][/lightbox]');

}
add_action('wp_footer', 'popup_form_contact');

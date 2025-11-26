<?php
/**
 * Absolute footer.
 *
 * @package          Flatsome\Templates
 * @flatsome-version 3.19.9
 */

$align = 'small-text-center';
if ( get_theme_mod( 'footer_bottom_align', '' ) == 'center' ) {
  $align = 'text-center';
}

ob_start();
do_action( 'flatsome_absolute_footer_secondary' );
$flatsome_absolute_footer_secondary = trim( ob_get_clean() );
$flatsome_footer_right_text = trim( get_theme_mod( 'footer_right_text', '' ) );
?>
<div class="overlay-footer"></div>

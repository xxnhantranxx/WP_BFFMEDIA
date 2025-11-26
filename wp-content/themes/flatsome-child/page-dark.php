<?php
/**
 * Template name: Page - Dark
 *
 * @package          Flatsome\Templates
 * @flatsome-version 3.18.0
 */

get_header('dark'); ?>

<?php do_action( 'flatsome_before_page' ); ?>

<div id="content" role="main" class="content-area">

		<?php while ( have_posts() ) : the_post(); ?>

			<?php the_content(); ?>

			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
			?>

		<?php endwhile; // end of the loop. ?>

</div>

<?php do_action( 'flatsome_after_page' ); ?>

<?php get_footer(); ?>

<?php
/** Standardvorlage für im Admin bearbeitbare Seiten. */
get_header();
while ( have_posts() ) :
	the_post();
	?>
	<section class="page-section<?php echo is_page( 'leistungen-preise' ) || get_the_ID() === (int) get_option( 'anubis_booking_page_id' ) ? ' page-section-cream' : ''; ?>"><article class="w page-content<?php echo get_the_ID() === (int) get_option( 'anubis_booking_page_id' ) ? ' booking-page' : ''; ?>">
		<a class="back-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">← Zur Startseite</a>
		<h1><?php the_title(); ?></h1>
		<?php the_content(); ?>
	</article></section>
	<?php
endwhile;
get_footer();

<?php
/**
 * Header-Template: Navigation mit Logo (logo-farbe.svg)
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header>
<div class="w">
<nav>
	<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<span class="ico">
			<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/logo-farbe.svg' ); ?>" alt="">
		</span>
		Anubis Pet Studio
	</a>
	<button class="bg" aria-label="Menü" aria-expanded="false" aria-controls="m">☰</button>
	<?php
	wp_nav_menu( array(
		'theme_location' => 'primary',
		'container'      => false,
		'items_wrap'     => '<ul id="%1$s">%3$s</ul>',
		'fallback_cb'    => 'anubis_theme_fallback_menu',
	) );
	?>
</nav>
</div>
</header>

<main id="main-content">

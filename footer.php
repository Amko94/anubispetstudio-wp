<?php
/**
 * Footer-Template
 */
?>
	</main><!-- #main-content -->
<?php anubis_render_social_links(); ?>

<footer>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> Anubis Pet Studio · <a href="<?php echo esc_url( anubis_impressum_url() ); ?>">Impressum</a> · <a href="<?php echo esc_url( anubis_privacy_url() ); ?>">Datenschutz</a></footer>

<?php wp_footer(); ?>
</body>
</html>

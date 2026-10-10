<?php
/**
 * Footer-Template
 */
?>
	</main><!-- #main-content -->
<?php anubis_render_social_links(); ?>

<footer>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> Anubis Pet Studio · <a href="<?php echo esc_url( anubis_impressum_url() ); ?>">Impressum</a> · <a href="<?php echo esc_url( anubis_privacy_url() ); ?>">Datenschutz</a></footer>

<?php if ( ! anubis_is_booking_page() ) : ?>
<div class="booking-fixed-action">
	<a class="btn pri" href="<?php echo esc_url( anubis_booking_url() ); ?>"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4M17 3v4M3 11h18m-13 5 2 2 4-4"/></svg><span>Termin buchen</span><span aria-hidden="true">→</span></a>
</div>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>

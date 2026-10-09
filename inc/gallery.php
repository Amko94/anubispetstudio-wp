<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
function anubis_gallery_init() {
 register_post_type( 'anubis_comparison', array(
  'labels' => array( 'name' => 'Vorher / Nachher', 'singular_name' => 'Bildpaar', 'add_new' => 'Bildpaar hinzufügen', 'add_new_item' => 'Neues Bildpaar hinzufügen', 'edit_item' => 'Bildpaar bearbeiten' ),
  'public' => false, 'show_ui' => true, 'menu_icon' => 'dashicons-format-gallery', 'supports' => array( 'title', 'editor', 'page-attributes' )
 ) );
 if ( get_option( 'anubis_gallery_page_initialized' ) ) { return; }
 $page = get_page_by_path( 'galerie' );
 $id = $page ? $page->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Vorher & Nachher', 'post_name' => 'galerie', 'post_content' => '<p class="gallery-intro">Ein frischer Schnitt, ein neues Fellgefühl. Entdecke die Veränderungen unserer vierbeinigen Gäste.</p> [anubis_gallery]' ), true );
 if ( ! is_wp_error( $id ) && $id ) { update_option( 'anubis_gallery_page_id', $id ); update_option( 'anubis_gallery_page_initialized', 1 ); }
}
add_action( 'init', 'anubis_gallery_init' );
function anubis_gallery_url() {
 $id = (int) get_option( 'anubis_gallery_page_id' );
 return $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : home_url( '/galerie/' );
}
function anubis_gallery_menu( $items, $args ) {
 if ( 'primary' !== $args->theme_location ) { return $items; }
 $url = esc_url( anubis_gallery_url() );
 if ( strpos( $items, 'href="' . $url . '"' ) !== false ) { return $items; }
 return $items . '<li class="menu-item"><a href="' . $url . '"' . ( is_page( (int) get_option( 'anubis_gallery_page_id' ) ) ? ' aria-current="page"' : '' ) . '>Galerie</a></li>';
}
add_filter( 'wp_nav_menu_items', 'anubis_gallery_menu', 20, 2 );
function anubis_gallery_boxes() { add_meta_box( 'anubis-gallery-images', '1. Vorher-Bild · 2. Nachher-Bild', 'anubis_gallery_fields', 'anubis_comparison', 'normal', 'high' ); }
add_action( 'add_meta_boxes', 'anubis_gallery_boxes' );
function anubis_gallery_fields( $post ) {
 wp_nonce_field( 'anubis_gallery_save', 'anubis_gallery_nonce' );
 echo '<p>Zuerst das Vorher-Bild, dann das Nachher-Bild auswählen oder hochladen. Nur vollständige, veröffentlichte Paare erscheinen in der Galerie. Titel und Beschreibung sind optional. Mit „Reihenfolge“ kannst du die Sortierung festlegen.</p><div style="display:flex;flex-wrap:wrap;gap:24px">';
 foreach ( array( 'before' => '1. Vorher', 'after' => '2. Nachher' ) as $key => $label ) {
  $id = (int) get_post_meta( $post->ID, '_anubis_' . $key, true );
  echo '<div class="anubis-gallery-field" style="flex:1;min-width:220px"><h3>' . esc_html( $label ) . '</h3><div class="anubis-gallery-preview">' . ( $id ? wp_get_attachment_image( $id, 'medium', false, array( 'style' => 'max-width:100%;height:auto' ) ) : '' ) . '</div><input type="hidden" name="anubis_' . esc_attr( $key ) . '" value="' . esc_attr( $id ) . '"><p><button type="button" class="button anubis-gallery-select" data-title="' . esc_attr( $label . '-Bild auswählen' ) . '">Bild auswählen / hochladen</button> <button type="button" class="button-link anubis-gallery-remove">Entfernen</button></p></div>';
 }
 echo '</div>';
}
function anubis_gallery_admin_scripts( $hook ) {
 $screen = get_current_screen();
 if ( ! $screen || 'anubis_comparison' !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) { return; }
 wp_enqueue_media();
 wp_enqueue_script( 'anubis-gallery-admin', get_template_directory_uri() . '/assets/js/gallery-admin.js', array( 'media-views' ), filemtime( get_template_directory() . '/assets/js/gallery-admin.js' ), true );
}
add_action( 'admin_enqueue_scripts', 'anubis_gallery_admin_scripts' );
function anubis_gallery_save( $post_id ) {
 if ( ! isset( $_POST['anubis_gallery_nonce'] ) || ! is_string( $_POST['anubis_gallery_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['anubis_gallery_nonce'] ) ), 'anubis_gallery_save' ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
 foreach ( array( 'before', 'after' ) as $key ) {
  if ( ! isset( $_POST[ 'anubis_' . $key ] ) || ! is_scalar( $_POST[ 'anubis_' . $key ] ) ) { continue; }
  $id = absint( $_POST[ 'anubis_' . $key ] );
  if ( $id && wp_attachment_is_image( $id ) ) { update_post_meta( $post_id, '_anubis_' . $key, $id ); }
  else { delete_post_meta( $post_id, '_anubis_' . $key ); }
 }
}
add_action( 'save_post_anubis_comparison', 'anubis_gallery_save' );
function anubis_gallery_shortcode() {
 $pairs = get_posts( array( 'post_type' => 'anubis_comparison', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'DESC' ) ) );
 $html = '';
 foreach ( $pairs as $pair ) {
  $before = (int) get_post_meta( $pair->ID, '_anubis_before', true );
  $after = (int) get_post_meta( $pair->ID, '_anubis_after', true );
  if ( ! wp_attachment_is_image( $before ) || ! wp_attachment_is_image( $after ) ) { continue; }
  $images = array( wp_get_attachment_image( $before, 'large' ), wp_get_attachment_image( $after, 'large' ) );
  if ( ! $images[0] || ! $images[1] ) { continue; }
  $html .= '<article class="gallery-pair"><div class="gallery-images">';
  foreach ( array( 'Vorher', 'Nachher' ) as $index => $label ) { $html .= '<figure>' . $images[ $index ] . '<figcaption>' . $label . '</figcaption></figure>'; }
  $html .= '</div><div class="gallery-caption">';
  if ( $pair->post_title ) { $html .= '<h2>' . esc_html( $pair->post_title ) . '</h2>'; }
  if ( $pair->post_content ) { $html .= wpautop( wp_kses_post( $pair->post_content ) ); }
  $html .= '</div></article>';
 }
 return $html ? '<div class="gallery-grid">' . $html . '</div>' : '<p class="gallery-empty">Hier zeigen wir bald die ersten Vorher- und Nachher-Bilder unserer Gäste.</p>';
}
add_shortcode( 'anubis_gallery', 'anubis_gallery_shortcode' );

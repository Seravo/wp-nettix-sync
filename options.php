<?php

add_action( 'init', 'nettix_check_cababilities' );
function nettix_check_cababilities() {
  if ( current_user_can('manage_options') ) {
    add_action( 'admin_menu', 'nettix_add_settings_page' );
    add_action( 'admin_init', 'nettix_init_settings' );
  }
}

function nettix_add_settings_page() {
  add_options_page( 'NettiX-asetukset', 'NettiX', 'manage_options', 'wp_nettix_sync', 'nettix_options_page' );
}

function nettix_init_settings() {
  register_setting( 'wp_nettix_options', 'nettix_options' );
  register_setting( 'wp_nettix_options', 'wp_nettix_dealerlist' );
  register_setting( 'wp_nettix_options', 'wp_nettix_adlist' );
  register_setting( 'wp_nettix_options', 'wp_nettix_json', array( 'type' => 'boolean' ) );

  add_settings_section( 'wp_nettix_option_urls', 'Nettix URLs', 'nettix_urls_string', 'wp_nettix_sync' );
  add_settings_field( 'wp_nettix_dealerlist', 'NettiX Dealerlist', 'nettix_dealerlist_string', 'wp_nettix_sync', 'wp_nettix_option_urls');
  add_settings_field( 'wp_nettix_adlist', 'NettiX Adlist', 'nettix_adlist_string', 'wp_nettix_sync', 'wp_nettix_option_urls');

  add_settings_section( 'wp_nettix_option_misc', 'Miscellaneous', 'nettix_misc_string', 'wp_nettix_sync');
  add_settings_field( 'wp_nettix_json', 'Nettix JSON', 'nettix_json_string', 'wp_nettix_sync', 'wp_nettix_option_misc' );
}

function nettix_options_page() {
?>
  <div class="wrap">
    <h1>NettiX options</h1>
    <form method="post" action="options.php">
      <?php settings_fields( 'wp_nettix_options' );
      do_settings_sections( 'wp_nettix_sync' );
      submit_button(); ?>
    </form>
  </div>
<?php
}

function nettix_urls_string() {
  echo '<p>Put the NettiX URL\'s to the corresponding field. Leave the other one empty. Adlist values should be separated with comma (,). For more information consult README.</p>';
}

function nettix_adlist_string() {
  $option = get_option( 'wp_nettix_adlist' );
  echo "<input id='wp_nettix_sync_option' name='wp_nettix_adlist' size='40' type='text' value='{$option}' />";
}

function nettix_dealerlist_string() {
  $option = get_option( 'wp_nettix_dealerlist' );
  echo "<input id='wp_nettix_sync_option' name='wp_nettix_dealerlist' size='40' type='text' value='{$option}' />";
}

function nettix_misc_string() {
  echo '<p>Nettix JSON setting is deprecated. Future releases will not tested with the JSON-format in any way.</p>';
}

function nettix_json_string() {
  $option = get_option( 'wp_nettix_json' );
  $checked = checked( 1, $option, 0);
  echo "<input id='wp_nettix_sync_option' name='wp_nettix_json' size='40' type='checkbox' value='1' {$checked} />";
}

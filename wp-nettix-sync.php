<?php
/**
 * Plugin Name: WP NettiX Sync
 * Plugin URI: https://github.com/Seravo/wp-nettix-sync
 * Description: Automatically import NettiX items to WordPress as posts with custom fields.
 * Version: 2.1
 * Author: Seravo Oy
 * Author URI: https://seravo.com
 * License: GPLv3 or later
*/
/**
 * Copyright 2015–2020 Seravo Oy
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 3, as
 * published by the Free Software Foundation.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Create a custom post type for NettiX items
 */
add_action( 'init', 'nettix_register_cpt' );
function nettix_register_cpt() {
  register_post_type( 'nettix',
    array(
      'labels' => array(
        'name'               => 'Vaihtoautot',
        'singular_name'      => 'Vaihtoauto',
        'menu_name'          => 'Vaihtoautot',
        'name_admin_bar'     => 'Vaihtoautot',
        'add_new'            => 'Lisää uusi',
        'add_new_item'       => 'Lisää uusi Vaihtoauto',
        'new_item'           => 'Uusi Vaihtoauto',
        'edit_item'          => 'Muokkaa Vaihtoautoa',
        'view_item'          => 'Näytä Vaihtoauto',
        'all_items'          => 'Kaikki Vaihtoautot',
        'search_items'       => 'Etsi Vaihtoautoja',
        'not_found'          => 'Kohdetta ei löytynyt.',
        'not_found_in_trash' => 'Roskakorissa ei ole tuotteita',
      ),
      'public' => true,
      'has_archive' => true,
      'menu_position' => 5,
      'rewrite' => array( 'slug' => 'vaihtoautot' ),
      'supports' => array(
        'title',
        'editor',
        'thumbnail',
        'custom-fields',
      ),
    )
  );

  register_post_type( 'nettixvene',
    array(
      'labels' => array(
        'name'               => 'Vaihtoveneet',
        'singular_name'      => 'Vaihtovene',
        'menu_name'          => 'Vaihtoveneet',
        'name_admin_bar'     => 'Vaihtoveneet',
        'add_new'            => 'Lisää uusi',
        'add_new_item'       => 'Lisää uusi Vaihtovene',
        'new_item'           => 'Uusi Vaihtovene',
        'edit_item'          => 'Muokkaa Vaihtovenettä',
        'view_item'          => 'Näytä Vaihtovene',
        'all_items'          => 'Kaikki Vaihtoveneet',
        'search_items'       => 'Etsi Vaihtoveneitä',
        'not_found'          => 'Kohdetta ei löytynyt.',
        'not_found_in_trash' => 'Roskakorissa ei ole tuotteita',
      ),
      'public' => true,
      'has_archive' => true,
      'menu_position' => 5,
      'rewrite' => array( 'slug' => 'vaihtoveneet' ),
      'supports' => array(
        'title',
        'editor',
        'thumbnail',
        'custom-fields',
      ),
    )
  );
}

/**
 * Clear schedules on deactivation
 */
register_deactivation_hook(__FILE__, 'nettix_clear_schedule');

function nettix_clear_schedule() {
	wp_clear_scheduled_hook('wp_nettix_sync_data');
}

function nettix_deprecated() {
    ?>
    <div class="notice notice-error">
      <p>NettiX Oy has shut down their API which the plugin wp-nettix-sync depended on, and thus the plugin cannot function anymore. <em>Please uninstall the wp-nettix-sync plugin.</em></p>
    </div>
    <?php
}
add_action( 'admin_notices', 'nettix_deprecated' );

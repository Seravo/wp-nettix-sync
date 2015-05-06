<?php
/**
 * Plugin Name: WordPress NettiX Sync
 * Plugin URI: http://seravo.fi
 * Description: This plugin syncs NettiX items to WordPress as posts with custom fields
 * Version: 1.0
 * Author: Antti Kuosmanen (Seravo Oy)
 * Author URI: http://seravo.fi
 * License: GPLv3
*/

/**
 * Copyright 2015 Antti Kuosmanen / Seravo Oy
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 3, as
 * published by the Free Software Foundation.a
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
add_action( 'init', '_wp_nettix_register_cpt' );
function _wp_nettix_register_cpt() {
  register_post_type( 'nettix',
    array(
      'labels' => array(
        'name'               => 'NettiX',
        'singular_name'      => 'NettiX',
        'menu_name'          => 'NettiX',
        'name_admin_bar'     => 'NettiX',
        'add_new'            => 'Lisää uusi',
        'add_new_item'       => 'Lisää uusi NettiX',
        'new_item'           => 'Uusi NettiX',
        'edit_item'          => 'Muokkaa NettiX',
        'view_item'          => 'Näytä NettiX',
        'all_items'          => 'Kaikki NettiX',
        'search_items'       => 'Etsi NettiX',
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
}

/**
 * Define once 15 mins interval
 */
add_filter('cron_schedules', '_wp_nettix_new_interval');
function _wp_nettix_new_interval($interval) {
    $interval['*/15'] = array('interval' => 15 * 60, 'display' => 'Once 15 minutes');
    return $interval;
}

/**
 * Schedule the sync action to be done hourly via WP-Cron
 */
add_action( 'wp', '_wp_nettix_setup_schedule' );
function _wp_nettix_setup_schedule() {
  if ( ! wp_next_scheduled( 'wp_nettix_sync_data' ) ) {
    wp_schedule_event( time(), '*/15', 'wp_nettix_sync_data');
  }
}

/**
 * Fetches data from NettiX
 */
add_action( 'wp_nettix_sync_data', '_wp_nettix_do_data_sync' );
function _wp_nettix_do_data_sync() {

  // start debug buffer
  ob_start();
  
  // give it some time...
  set_time_limit(180);

  // this helps with DEBUG
  header('Content-Type: text/html; charset=utf-8');

  global $nettix_sources;
  if( !is_array($nettix_sources) ) {
    $nettix_sources = array();
  }

  // wp-config.php:
  /*$nettix_sources = array(
    'http://www.nettiauto.com/yritys/{yrityksen_nimi}?id_template=7',
  );*/


  // get item links
  $links = array();
  foreach($nettix_sources as $src) {
    $links = array_merge( $links, _wp_nettix_parse_links( $src ) );
  }

  $links = array_unique( $links );

  // store available nettiX ids to keep track of published posts
  $available = array();

  // keep track of actions
  $added = array();
  $updated = array();
  $deleted = array();

  // store the data into wordpress posts
  foreach($links as $count => $link) {

    // limit for debug
    $meta = _wp_nettix_parse_meta( $link );

    // make this available
    $available[] = $meta['nettixID'];

    $post = array(
      'post_content'   => '',
      'post_name'      => sanitize_title( $meta['title'] ),
      'post_title'     => $meta['title'],
      'post_status'    => 'publish',
      'post_type'      => 'nettix',
    );

    // check if this already exists as a post
    $matching = get_posts( array(
      'post_type' => 'nettix',
      'meta_query' => array(
        array(
          'key' => 'nettixID',
          'value' => $meta['nettixID'],
        )
      )
    ));

    if( !empty($matching) ) {
      // post exists, update it
      $updated[] = $post['ID'] = $matching[0]->ID;
    }

    else {
      $added[] = $post_id;
    }

    $post_id = wp_insert_post( $post );


    // add submission data as meta values
    foreach($meta as $key => $value) {

      // store arrays in JSON
      if(is_array($value))
        $value = json_encode( $value );

      // add or update the value
      if( !add_post_meta($post_id, trim( $key ), sanitize_text_field( $value ), true) )
          update_post_meta($post_id, trim( $key ), sanitize_text_field( $value ) );

    }

  }

  // find posts to eliminate
  $eliminate = get_posts( array(
    'posts_per_page' => -1,
    'post_type' => 'nettix',
    'meta_query' => array(
      array(
        'key' => 'nettixID',
        'compare' => 'NOT IN',
        'value' => $available,
      )
    )
  ));

  // eliminate them
  foreach($eliminate as $post) {
    wp_delete_post($post->ID, true);
    $deleted[] = $post->ID;
  }

  echo "Added: ";
  print_r($added);

  echo "Updated: ";
  print_r($updated);

  echo "Deleted: ";
  print_r($deleted);

  $output = ob_get_clean();

  if(isset($_GET['nettix_do_sync'])) {
    print_r('<pre>' . $output . '</pre>');
    die();
  }

}


/**
 * Parses item meta from an item template
 *
 * @TODO Shameless crawl mining...
 * We need to port this to their private XML API at http://www.nettiauto.com/datapipe/xml/v2/
*/
function _wp_nettix_parse_meta($item) {

  // reset timer
  set_time_limit(180);

  // include dom parser lib
  require_once 'lib/Simple-HTML-DOM/simple_html_dom.php';

  // load the item meta
  $document = file_get_contents( $item );
  $document = str_get_html( utf8_encode( $document ) );


  $meta = array();

  // save nettix URL in meta fields
  $meta['source'] = $item;

  // get nettix ID
  foreach( $document->find('*[itemprop="productID"]') as $data ) {
    $meta['nettixID'] = $data->plaintext;
  }

  // get item title
  foreach( $document->find('title') as $data ) {
    $meta['title'] = $data->plaintext;
  }

  // get manufacturer
  foreach( $document->find('*[itemprop="manufacturer"]') as $data ) {
    $meta['Valmistaja'] = $data->plaintext;
  }

  // get model
  foreach( $document->find('*[itemprop="model"]') as $data ) {
    $meta['Malli'] = $data->plaintext;
  }

  // get subtitle
  foreach( $document->find('.vif_cnt_left h2.mb5') as $data ) {
    $meta['Tarkenne'] = $data->plaintext;
  }

  // get price
  foreach( $document->find('*[itemprop="price"]') as $data ) {
    $meta['Hinta'] = $data->plaintext;
  }

  // get VAT info
   foreach( $document->find('.vif_cnt_right span.mt3') as $data ) {
    $meta['ALV'] = $data->plaintext;
  }

  // get table keys and values
  foreach( $document->find('.relative td') as $n => $data ) {
    if($n % 3 == 0) {
      // key
      $key = trim( $data->plaintext );
    }
    if($n % 3 == 1) {
      // value
      $value = trim( $data->plaintext );
      $meta[$key] = $value;
    }
  }

  // get images
  $images = array();
  foreach( $document->find('.jcarousel-item a') as $data ) {
    $images[] = $data->href;
  }
  $meta['images'] = $images;

  // get technical titles
  $technical = $document->find('#acc_section');
  $technical = $technical[0]->innertext;
  foreach( $document->find('#acc_section h2') as $data ) {
    $key = trim( $data->plaintext );

    // value is any text after the h2 tag until the start of next
    $startpos = strpos($technical, $data->outertext) + strlen($data->outertext);
    $endpos = strpos($technical, '<h2', $startpos + 1);

    if(!$endpos)
      $endpos = strlen($technical);

    $value = substr($technical, $startpos, $endpos - $startpos);

    $meta[$key] = $value;
  }

  // get description
  foreach( $document->find('*[itemprop="description"] p') as $data ) {

    if(!$length = strpos($data->innertext, '<a href="javascript')) {
      //there may be a read less link in the end
      $length = strlen($data->innertext);
    }

    $meta['Lisätiedot'] = substr($data->innertext, 0, $length);
  }

  $document->clear(); // free up memory

  return $meta;

}


/**
 * Parses item links from a template 7 directory
 */
function _wp_nettix_parse_links($directory) {

  // include dom parser lib
  require_once 'lib/Simple-HTML-DOM/simple_html_dom.php';

  // load the directory page
  $document = file_get_contents( $directory );
  $document = str_get_html( utf8_encode( $document ) );

  // get items from directory
  $items = array();
  foreach( $document->find('a.tricky_link') as $item ) {
    $items[] = $item->href;
  }
  
  $document->clear(); // free up memory

  return $items;

}


/**
 * Run sync via GET parameters
 */
if(isset($_GET['nettix_do_sync'])) {
  add_action('init', '_wp_nettix_do_data_sync');
}


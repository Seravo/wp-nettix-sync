<?php
/**
 * Plugin Name: WP NettiX Sync
 * Plugin URI: https://github.com/Seravo/wp-nettix-sync
 * Description: Automatically import NettiX items to WordPress as posts with custom fields.
 * Version: 2.2.1
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
 * Require options.php for options page
 */
require_once dirname( __FILE__ ) .'/options.php' ;

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
 * Schedule the sync action to be done hourly via WP-Cron
 */
register_activation_hook( __FILE__, 'nettix_setup_schedule' );
function nettix_setup_schedule() {
  if ( ! wp_next_scheduled( 'wp_nettix_sync_data' ) ) {
    wp_schedule_event( time(), 'hourly', 'wp_nettix_sync_data');
  }
}


/**
 * Clear schedules on deactivation
 */
register_deactivation_hook(__FILE__, 'nettix_clear_schedule');

function nettix_clear_schedule() {
	wp_clear_scheduled_hook('wp_nettix_sync_data');
}

/**
 * Fetches data from NettiX
 */
add_action( 'wp_nettix_sync_data', 'nettix_do_data_sync' );
function nettix_do_data_sync() {
  // start debug buffer
  ob_start();

  // give it some time...
  set_time_limit(180);
  // this helps with DEBUG
  header('Content-Type: text/html; charset=utf-8');
  global $nettix_sources;
  $nettix_sources = nettix_get_links();
  if( !is_array($nettix_sources) ) {
    $nettix_sources = array();
  }
  // wp-config.php:
  /*$nettix_sources = 'http://www.nettiauto.com/yritys/{yrityksen_nimi}?id_template=7'*/
  // get item links

  // Keep track of fetch failures
  $fetch_failure = false;

  $links = array();
  foreach($nettix_sources as $src) {
    $linklist = nettix_parse_links( $src );

    // Ignore empty lists
    if ( $linklist[0] != "" ){
      $links = array_merge( $links, $linklist );
    }
  }

  // HACK: let's shuffle the order of links in order to avoid missing the same ones every time
  shuffle($links);

  // store available nettiX ids to keep track of published posts
  $available = array();
  // keep track of actions
  $added = array();
  $updated = array();
  $deleted = array();

  // Define the storing method before the loop.
  $nettix_json = nettix_get_option( 'wp_nettix_json' );
  if ( defined( 'NETTIX_JSON' ) && ! empty($nettix_json) ){
    nettix_from_config_to_db( 'wp_nettix_json', "1" );
    $nettix_json = "1";
  }
  if ( $nettix_json ){
    error_log( "wp-nettix-sync Notice: Using Nettix JSON is deprecated and will be removed in the near future." );
  }

  // store the data into wordpress posts
  foreach($links as $count => $link) {

    // Check if 'nettiauto' exists in link and assign the post_type accordingly
    $post_type = ( false !== strpos($link, 'nettiauto') ) ? 'nettix' : 'nettixvene';

    $meta = nettix_parse_meta( $link );

     //skip if meta not available
    if (!$meta) {
      $fetch_failure = true;
      echo "NETTIXdebug: Failure detected. Not deleting anything.";
      continue;
    }

    // make this available
    $available[] = $meta['nettixID'];
    $post = array(
      'post_content'   => '',
      'post_name'      => sanitize_title( $meta['title'] ),
      'post_title'     => $meta['title'],
      'post_status'    => 'publish',
		  'post_type'      => $post_type,
    );
    // check if this already exists as a post
    $matching = get_posts( array(
      'post_type' => $post_type,
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


    // This is deprecated.
    // Enter the loop by defining NETTIX_JSON in wp-config
    if ( $nettix_json ) {
      foreach ( $meta as $key => $value ) {
        if ( is_array( $value ) ) {
          $value = wp_json_encode( $value );
        }
        update_post_meta( $post_id, trim( $key ), sanitize_text_field( $value ) );
      }
    }
    else {

      //search doesn't work w/o some meta fields
      //this is not the best solution though

      $meta_keys = array(
		  'make'            => 'Valmistaja',
		  'location'        => 'Sijainti',
		  'driveType'       => 'Vetotapa',
		  'gearBoxType'     => 'Vaihteisto',
		  'fuelType'        => 'Moottori',
		  'isVatDeductible' => 'ALV',
		  'price'           => 'Hinta',
		  'year'            => 'Vuosimalli',
		  'mileage'         => 'Mittarilukema',
		  'color'           => 'Väri',
		  'lengthMeter'     => 'Pituus',
		  'widthMeter'      => 'Leveys',
		  'heightMeter'     => 'Korkeus',
		  'totalOwner'      => 'Omistajat',
		  'accessory'       => 'Lisävarusteet',
		  'engineInfo'      => 'Moottorin tiedot',
      );

      if ( $post_type == 'nettixvene' ) {
        $boat_meta_keys = array(
          'make'          => 'Venevalmistaja',
          'location'      => 'Venesijainti',
          'boatFuelType'  => 'Polttoainetyyppi',
          'boatType'      => 'Venetyyppi',
          'bodyMaterial'  => 'Runkomateriaali',
          'draughtMeter'  => 'Syveys',
          'sailInfo'      => 'Purjeen tiedot',
        );
        $meta_keys = array_merge( $meta_keys, $boat_meta_keys );
      }

      foreach ( $meta_keys as $key => $entry ) {
        if ( $key == 'location' ) {
          update_post_meta($post_id, $entry, sanitize_text_field($meta['locationInfo']['town']) );
        } elseif ( $meta[$key] == false ) {
          update_post_meta( $post_id, $entry, '');
        } elseif ( $key == 'boatFuelType' ) {
          if ( is_array( $meta['engineInfo']['engineFuelType'] ) ) {
            update_post_meta( $post_id, $entry, sanitize_text_field( $meta['engineInfo']['engineFuelType'][0] ) );
          } else {
            update_post_meta( $post_id, $entry, sanitize_text_field( $meta['engineInfo']['engineFuelType'] ) );
          }
        } elseif ( is_array( $meta[ $key ] ) ) {
          update_post_meta( $post_id, $entry, $meta[$key] );
        } else {
          update_post_meta( $post_id, $entry, sanitize_text_field($meta[$key]) );
        }
      }

      update_post_meta($post_id, 'nettixID', sanitize_text_field($meta['nettixID']) );

      $meta = wp_slash(wp_json_encode($meta));
      update_post_meta($post_id, 'xml', $meta );
    }
  }
  // Eliminate only if there were some links to begin with
  // For example removing a link from settings would erase everything
  if ( $nettix_sources && ! $fetch_failure ) {
    // find posts to eliminate
    $eliminate = get_posts( array(
      'posts_per_page' => -1,
      'post_type' => ['nettix', 'nettixvene'],
      'meta_query' => array(
        array(
          'key' => 'nettixID',
          'compare' => 'NOT IN',
          'value' => $available,
        )
      )
    ));
  }
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
 * Parses item meta from xml
 *
 *
 */
function nettix_parse_meta($item) {

  set_time_limit(180);

  $xml = simplexml_load_file($item);

  if(!$xml) return false; // do nothing if there's a failure

  $meta = nettix_xmlToArray($xml);

  $temp = array();
  $temp = $meta['ad'];
  unset($meta['ad']);
  $meta = array_merge($meta,$temp);

  $meta['source'] = $item;
  // get nettix ID
  $meta['nettixID'] = (string)$xml->id;
  // get item title
  $meta['title'] = (string)$xml->make .' '. (string)$xml->model .' '. (string)$xml->year . ' ' . (string)$xml->engineModel;

  $images = array();

  //if theres more than one image
  //the array structure is different
  if(isset($meta['media']['image'][0])){
    foreach($meta['media']['image'] as $image){
      if(isset($image['imgUrl'])){
        $images[] = $image['imgUrl'];
      }
    }
  }
  else{
    if(isset($meta['media']['image']['imgUrl'])){
      $images[] = $meta['media']['image']['imgUrl'];
    }
  }

  unset($meta['media']);
  $meta['images'] = $images;

  if(empty($meta['images'])) {
    $meta['images'] = array();
    $meta['images'][] = NETTIX_PLACEHOLDER_IMG;
  }

  return $meta;
}

/*
 * Parses item links from a template 7 directory
 */
function nettix_parse_links($directory) {
  $xml = simplexml_load_file($directory);
  $items = array();

  foreach( $xml->children() as $child){
    $items[] = (string)$child->adUrl;
  }
  return $items;
}
function nettix_get_links(){

  $nettix_dealerlist = nettix_get_option( 'wp_nettix_dealerlist' );
  $nettix_adlist = nettix_get_option( 'wp_nettix_adlist' );

  if( ! empty( $nettix_dealerlist ) ) {

    $xml = simplexml_load_file( $nettix_dealerlist );
    $items = array();

    foreach( $xml->children() as $child){
      $items[] = (string)$child->adListUrl;
    }
  }
  else if ( defined('NETTIX_DEALERLIST') ) {

    nettix_from_config_to_db( 'wp_nettix_dealerlist', NETTIX_DEALERLIST );
    $xml = simplexml_load_file( NETTIX_DEALERLIST );
    $items = array();

    foreach( $xml->children() as $child){
      $items[] = (string)$child->adListUrl;
    }
  }
  else if( ! empty( $nettix_adlist && $nettix_adlist != [""] ) ) {
    return $nettix_adlist;
  }
  else if ( defined('NETTIX_ADLIST') ) {
    nettix_from_config_to_db( 'wp_nettix_adlist', unserialize( NETTIX_ADLIST ) );
    return unserialize( NETTIX_ADLIST );
  }
  else {
    error_log( 'wp-nettix-sync Error: Datapipe URL not defined. Set it in wp-admin Settings->NettiX' );
  }

  return $items;
}

function nettix_from_config_to_db( $optionname, $content ) {
  if ( is_array($content) ) {
    update_option( $optionname, implode( ',', $content ) );
  }
  else {
    update_option( $optionname, $content );
  }
}

function nettix_get_option( $option ) {
  $db_option = get_option( $option );

  if ( $option === 'wp_nettix_adlist') {
    // Remove spaces and make an array
    $db_option = str_replace( ' ', '', $db_option );
    $db_option = explode( ',', $db_option);
  } elseif ( $option == 'wp_nettix_json' ) {
    if ( $db_option !== "1" ){
      $db_option = false;
    }
  }
  return $db_option;
}

function nettix_xmlToArray($xml, $options = array()) {
    $defaults = array(
        'namespaceSeparator' => ':',//you may want this to be something other than a colon
        'attributePrefix' => '@',   //to distinguish between attributes and nodes with the same name
        'alwaysArray' => array(),   //array of xml tag names which should always become arrays
        'autoArray' => true,        //only create arrays for tags which appear more than once
        'textContent' => '$',       //key used for the text content of elements
        'autoText' => true,         //skip textContent key if node has no attributes or child nodes
        'keySearch' => false,       //optional search and replace on tag and attribute names
        'keyReplace' => false       //replace values for above search values (as passed to str_replace())
    );
    $options = array_merge($defaults, $options);
    $namespaces = $xml->getDocNamespaces();
    $namespaces[''] = null; //add base (empty) namespace

    //get attributes from all namespaces
    $attributesArray = array();
    foreach ($namespaces as $prefix => $namespace) {
        foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
            //replace characters in attribute name
            if ($options['keySearch']) $attributeName =
                    str_replace($options['keySearch'], $options['keyReplace'], $attributeName);
            $attributeKey = $options['attributePrefix']
                    . ($prefix ? $prefix . $options['namespaceSeparator'] : '')
                    . $attributeName;
            $attributesArray[$attributeKey] = (string)$attribute;
        }
    }

    //get child nodes from all namespaces
    $tagsArray = array();
    foreach ($namespaces as $prefix => $namespace) {
        foreach ($xml->children($namespace) as $childXml) {
            //recurse into child nodes
            $childArray = nettix_xmlToArray($childXml, $options);
            list($childTagName, $childProperties) = each($childArray);

            //replace characters in tag name
            if ($options['keySearch']) $childTagName =
                    str_replace($options['keySearch'], $options['keyReplace'], $childTagName);
            //add namespace prefix, if any
            if ($prefix) $childTagName = $prefix . $options['namespaceSeparator'] . $childTagName;

            if (!isset($tagsArray[$childTagName])) {
                //only entry with this key
                //test if tags of this type should always be arrays, no matter the element count
                $tagsArray[$childTagName] =
                        in_array($childTagName, $options['alwaysArray']) || !$options['autoArray']
                        ? array($childProperties) : $childProperties;
            } elseif (
                is_array($tagsArray[$childTagName]) && array_keys($tagsArray[$childTagName])
                === range(0, count($tagsArray[$childTagName]) - 1)
            ) {
                //key already exists and is integer indexed array
                $tagsArray[$childTagName][] = $childProperties;
            } else {
                //key exists so convert to integer indexed array with previous value in position 0
                $tagsArray[$childTagName] = array($tagsArray[$childTagName], $childProperties);
            }
        }
    }

    //get text content of node
    $textContentArray = array();
    $plainText = trim((string)$xml);
    if ($plainText !== '') $textContentArray[$options['textContent']] = $plainText;

    //stick it all together
    $propertiesArray = !$options['autoText'] || $attributesArray || $tagsArray || ($plainText === '')
            ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;

    //return node as array
    return array(
        $xml->getName() => $propertiesArray
    );
}

/**
 * Run sync via GET parameters
 */
if(isset($_GET['nettix_do_sync'])) {
  add_action('init', 'nettix_do_data_sync');
}

function nettix_deprecated() {
    ?>
    <div class="notice notice-error">
      <p>NettiX Oy will shut down the API which the plugin wp-nettix-sync depends on and the plugin will stop working in August 2020 unless a major code rewrite is done. <em>Please contact sales@seravo.com if you want to fund development of the WP NettiX sync plugin.</em></p>
    </div>
    <?php
}
add_action( 'admin_notices', 'nettix_deprecated' );

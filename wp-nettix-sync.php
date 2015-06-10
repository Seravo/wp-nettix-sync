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
  $nettix_sources = _wp_nettix_get_links();
  if( !is_array($nettix_sources) ) {
    $nettix_sources = array();
  }
  // wp-config.php:
  /*$nettix_sources = 'http://www.nettiauto.com/yritys/{yrityksen_nimi}?id_template=7'*/
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
    /*foreach($meta as $key => $value) {
      // store arrays in JSON
      if(is_array($value))
      //$value = json_encode( $value );
      //add or update the value
      if( !add_post_meta($post_id, trim( $key ), sanitize_text_field($value), true) )
          update_post_meta($post_id, trim( $key ), sanitize_text_field($value), true );
    }*/

      //search doesn't work w/o some meta fields
      //this is not the best solution though
      
      $meta_keys = array(
      'make' => 'Valmistaja',
      'location' => 'Sijainti',
      'driveType' => 'Vetotapa',
      'gearBoxType' => 'Vaihteisto',
      'engineModel' => 'Moottori',
      'isVatDeductible' => 'ALV',
      //'Kunnossapitosopimus',
      'price' => 'Hinta',
      'year' => 'Vuosimalli',
      'mileage' => 'Mittarilukema',
      );

      foreach( $meta_keys as $key => $entry ){
        if($key == 'location'){
          if( !add_post_meta($post_id, $entry, sanitize_text_field($meta['locationInfo']['town']), true) ){
            update_post_meta($post_id, $entry, sanitize_text_field($meta['locationInfo']['town']), true );
          }
        }
        if( $meta[$key] == false ){}
        
        else{
          if( !add_post_meta( $post_id, $entry, sanitize_text_field($meta[$key]), true ) ){
          update_post_meta( $post_id, $entry, sanitize_text_field($meta[$key]), true );
          }
        }
      }
      
      
      if( !add_post_meta($post_id, 'nettixID', sanitize_text_field($meta['nettixID']), true) ){
          update_post_meta($post_id, 'nettixID', sanitize_text_field($meta['nettixID']), true );
        }
      
      $meta = wp_slash(json_encode($meta));
      if( !add_post_meta($post_id, 'xml', $meta, true) ){
          update_post_meta($post_id, 'xml', $meta, true );
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
 * Parses item meta from xml
 *
 * 
 */
function _wp_nettix_parse_meta($item) {
  
  set_time_limit(180);
  
  $xml = simplexml_load_file($item);
  $meta = xmlToArray($xml);
  //$meta = json_decode(json_encode((array)$xml),1);
  // save nettix URL in meta fields
  //unset($meta[0]);

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
  for($x=0;$x<count($meta['media']['image']);$x++){
    $images[] = $meta['media']['image'][$x]['imgUrl'];
  }
  unset($meta['media']);
  /*foreach( $meta['image'] as $element ) {
    $images[]=$element['imgUrl'];
  }*/
  $meta['images'] = $images;
  
  error_log(var_dump($meta,true),0);
  return $meta;
}
/**
 * Parses item links from a template 7 directory
 */
function _wp_nettix_parse_links($directory) {
  $xml = simplexml_load_file($directory);
  $items = array();
  
  foreach( $xml->children() as $child){
      $items[] = (string)$child->adUrl;
  }
  return $items;
}
function _wp_nettix_get_links(){ 
  $nettix_url = NETTIX_DEALERLIST; //set in wp-config
  $xml = simplexml_load_file($nettix_url);
  $items = array();
  
  foreach( $xml->children() as $child){
    $items[] = (string)$child->adListUrl;
  }
  return $items;  
}
function xmlToArray($xml, $options = array()) {
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
            $childArray = xmlToArray($childXml, $options);
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
  add_action('init', '_wp_nettix_do_data_sync');
}


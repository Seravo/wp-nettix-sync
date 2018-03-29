<?php

function nettix_parse_meta($item) {
  set_time_limit(180);

  $xml = simplexml_load_file($item);

  $meta = json_decode(json_encode((array)$xml),1);
  // save nettix URL in meta fields
  unset($meta[0]);
  $meta['source'] = $item;
  // get nettix ID
  $meta['nettixID'] = (string)$xml->id;
  // get item title
  $meta['title'] = (string)$xml->make .' '. (string)$xml->model .' '. (string)$xml->year;

  $images = array();

  for($x=0;$x<count($meta['media']['image']);$x++){
    $images[] = $meta['media']['image'][$x]['imgUrl'];
  }
  unset($meta['media']['image']);
  /*foreach( $meta['image'] as $element ) {
    $images[]=$element['imgUrl'];
  }*/
  $meta['images'] = $images;
  return $meta;
}

/**
 * Fetch the dealer list
 */
function nettix_get_links(){
  $nettix_url = '';
  $xml = simplexml_load_file($nettix_url);
  $items = array();

  foreach( $xml->children() as $child){
    $items[] = $child->adListUrl;
  }
  return $items;
}

/**
 *  Parse links for each individual ad
 */

function nettix_parse_links($directory) {
  $xml = simplexml_load_file($directory);
  $items = array();

  foreach( $xml->children() as $child){
      $items[] = $child->adUrl;
  }
  return $items;
}

$dealers = nettix_get_links();
foreach( $dealers as $link ){
  $links = nettix_parse_links($link);
  foreach($links as $ad){

    print_r(nettix_parse_meta($ad));

  }

}

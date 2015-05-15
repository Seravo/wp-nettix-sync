<?php

  function _wp_nettix_parse_meta($item) { 
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
$item = '';

print_r(_wp_nettix_parse_meta($item));


/*function _wp_nettix_parse_links($directory) {

  // get items from directory
  foreach( $document->find('a.tricky_link') as $item ) {
    $items[] = $item->href;
  }
  
  $document->clear(); // free up memory
  return $items;
}*/


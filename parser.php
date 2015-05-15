<?php

  function _wp_nettix_parse_meta($item) { 
  set_time_limit(180);
  
  $xml = simplexml_load_file($item);

  $meta = json_decode(json_encode((array)$xml),1);
  // save nettix URL in meta fields
  $meta['source'] = $item;
  // get nettix ID
  $meta['nettixID'] = (string)$xml->id;
  // get item title
  $meta['title'] = (string)$xml->make .' '. (string)$xml->model .' '. (string)$xml->year;
  // get manufacturer
  /*$meta['Valmistaja'] =(string)$xml->make;

  // get model
  $meta['Malli'] = (string)$xml->model;
  
  // get subtitle
  $meta['Tarkenne'] = (string)$xml->subType;

  // get price
  $meta['Hinta'] = (string)$xml->price;
  
  // get VAT info
  $meta['ALV'] = "";
  // get table keys and values
  
  // get images
  $images = array();
  
  foreach( $xml->media->image as $image ) {
    $images[] = (string)$image->imgUrl;
  }
  
  $meta['images'] = $images; //array
  // get technical titles
  foreach( $xml->techInfo->children() as $element ) {
    $meta[$element->getName()] = (string)$element;
  }
  
  // get description
  //$meta['Lisätiedot'] = $xml->description;*/
  
  return $meta;
}
$item = '';

var_dump(_wp_nettix_parse_meta($item));


/*function _wp_nettix_parse_links($directory) {

  // get items from directory
  foreach( $document->find('a.tricky_link') as $item ) {
    $items[] = $item->href;
  }
  
  $document->clear(); // free up memory
  return $items;
}*/


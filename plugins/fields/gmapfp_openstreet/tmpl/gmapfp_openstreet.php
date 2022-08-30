<?php
/**
 * GMapFP! Openstreet Field Project
 * Version J3_5
 * Creation date: Novembre 2021
 * Author: Fabrice4821 - https://creation-web.pro
 * Author email: webmaster@gmapfp.org
 *
 * @copyright   Copyright (C) 2011 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

$value = $field->value;
$json_layers = '';
$defaultLayer = '';
$datas = array();
$rand = '_'.$item->id.'_'.rand(99, 999);

if ($value == '')
{
	return;
}

if (property_exists($field, 'rawvalue'))
	$datas = explode(',', $field->rawvalue);
if (count($datas)>3) {
	$lat = $datas[0];
	$lng = $datas[1];
	$zoom = $datas[2];
	$lenth = strlen ($lat.$lng.$zoom)+3;
	$texte = str_replace('"', '\"', substr($field->rawvalue, $lenth));
} else {
	echo Text::_('PLG_FIELDS_GMAPFP_OPENSTREET_ERROR_DATAS');
	return;
}
				
$field->name = str_replace(array('-', ' '), '_', $field->name);
$targetid = 'jform_com_fields_' . $field->name.$rand;
$height = $fieldParams->get('mapheight');
$layers = '';
$baseLayers = array();
$aliasLayers = array();
$paramsLayers = $fieldParams->get('gmapfp_osm_layers');
if (is_string($paramsLayers))
	$json_layers = json_decode($paramsLayers);
if ($json_layers && json_last_error() == JSON_ERROR_NONE) {
	$paramsLayers = $json_layers;
}
if ($paramsLayers)
	foreach($paramsLayers AS $layer){
		$layers .= 'var '.$layer->alias.$rand.' = L.tileLayer("'.$layer->url.'", {';
		$layers .= 'attribution: "'.str_replace(array('"','‹'), array('\"','<'), $layer->attribution).'",';
		$layers .= 'minZoom: 1,';
		$layers .= 'maxZoom: '.$layer->max_zoom.',';
		$layers .= 'id: "'.$layer->alias.$rand.'"';
		$layers .= '});';
		$layers .= 'mes_layers'.$field->name.$rand.'["'.$layer->alias.$rand.'"] = '.$layer->alias.$rand.';';
		$baseLayers[] = '"'.$layer->name.'":'.$layer->alias.$rand;
		$aliasLayers[] = $layer->alias.$rand;
	}
$var_baseLayers = 'var baseLayers'.$field->name.$rand.' = {'.implode(',', $baseLayers).'};';

if (in_array($fieldParams->get('gmapfp_type_admin_osm'), $aliasLayers))
	$defaultLayer = '';
elseif (array_key_exists(0, $aliasLayers))
	$defaultLayer = 'stylemap'.$rand.' = "'.$aliasLayers[0].'";';
	
HTMLHelper::_('stylesheet', 'https://unpkg.com/leaflet/dist/leaflet.css');
HTMLHelper::_('script', 'https://unpkg.com/leaflet/dist/leaflet-src.js');

$js = ''."\r\n"
	.'var map'.$field->name.$rand.';'
	.'var marker1'.$field->name.$rand.';'
	.'var mes_layers'.$field->name.$rand.' = [];'
	.'var lat'.$field->name.$rand.' = '.$lat.';'
	.'var lng'.$field->name.$rand.' = '.$lng.';'
	.'var zoom'.$field->name.$rand.' = '.$zoom.';'
	.'var text'.$field->name.$rand.' = "'.$texte.'";'
	.''."\r\n"
	.'function init_osm'.$field->name.$rand.'() {'
	.'	var stylemap'.$rand.';'
	.''
	.$defaultLayer
	.''
	.$layers
	.''
	.'	var options'.$rand.' = {'
	.'		center: [lat'.$field->name.$rand.', lng'.$field->name.$rand.'],'
	.'		zoom: zoom'.$field->name.$rand.','
	.'		layers: [mes_layers'.$field->name.$rand.'[stylemap'.$rand.']]'
	.'	};'
	.''
	.'	map'.$field->name.$rand.' = L.map("map_'.$targetid.'", options'.$rand.');'
	.''
	//affichce l'échelle
	.'	L.control.scale().addTo(map'.$field->name.$rand.');'
	.''
	.$var_baseLayers
	.''
	.'	L.control.layers(baseLayers'.$field->name.$rand.').addTo(map'.$field->name.$rand.');'
	.''
	// Create a draggable marker which will later on be binded to a'
	.'	marker1'.$field->name.$rand.' = L.marker([lat'.$field->name.$rand.', lng'.$field->name.$rand.'], {draggable: false}).addTo(map'.$field->name.$rand.');'
	.'	marker1'.$field->name.$rand.'.bindPopup("'.$texte.'");'
	.''
	.'}'
	.''."\r\n"
	// Register an event listener to fire when the page finishes loading
	// .'jQuery( document ).ready(initialise_map_gmapfp'.$field->name.$rand.');'
	.'jQuery( document ).ready(init_osm'.$field->name.$rand.');'
	.''
	/* .'var tstGMapFP'.$field->name.$rand.';'
	.'var tstIntGMapFP'.$field->name.$rand.';'
	.''
	.'function CheckGMapFP'.$field->name.$rand.'() {'
	.'	if (tstGMapFP'.$field->name.$rand.') {'
	.'		if (tstGMapFP'.$field->name.$rand.'.offsetWidth != tstGMapFP'.$field->name.$rand.'.getAttribute("oldValue")) {'
	.'			tstGMapFP'.$field->name.$rand.'.setAttribute("oldValue",tstGMapFP'.$field->name.$rand.'.offsetWidth);'
	.'			init_osm'.$field->name.$rand.'();'
	.'		}'
	.'	}'
	.'}'
	.''
	.'function initialise_map_gmapfp'.$field->name.$rand.'() {'
	// Créer la div map
	.'	tstGMapFP'.$field->name.$rand.' = document.getElementById("map_'.$targetid.'");'
	.'  tstGMapFP'.$field->name.$rand.'.setAttribute("oldValue",0);'
	.'  tstIntGMapFP'.$field->name.$rand.' = setInterval("CheckGMapFP'.$field->name.$rand.'()",500);'
	.'}'
*/	.'';


echo '<div style="height: '.$height.'px" id="map_' . $targetid . '"></div>';
echo '<script>'.$js.'</script>';
// Factory::getDocument()->addScriptDeclaration($js); si fait par addscript, l'ajoute 2 fois ???

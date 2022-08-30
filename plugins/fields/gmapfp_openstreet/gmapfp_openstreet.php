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

JLoader::import('components.com_fields.libraries.fieldsplugin', JPATH_ADMINISTRATOR);

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
// use Joomla\Component\Fields\Administrator\Plugin\FieldsPlugin;
use Joomla\CMS\Form\Form;


class PlgFieldsGmapFP_Openstreet extends FieldsPlugin
{
	public function onCustomFieldsPrepareDom($field, DOMElement $parent, Form $form)
	{
		$fieldNode = parent::onCustomFieldsPrepareDom($field, $parent, $form);

		if (!$fieldNode)
		{
			return $fieldNode;
		}

		$doc = Factory::getDocument();
		$field->name_ = str_replace(array('-', ' '), '_', $field->name);
		$targetid = 'jform_com_fields_' . $field->name_;
		$datas = array();
		$json_layers = '';
		$defaultLayer = '';

		$plugin = PluginHelper::getPlugin('fields', 'gmapfp_openstreet');
		
		if(empty($plugin) or !property_exists($plugin, 'params')) {
			echo Text::_('PLG_FIELDS_GMAPFP_OPENSTREET_NON_CONFIGURE_OR_ACTIF');
		} else {
			$params = new Registry($plugin->params);
			$lat = '999999';
			if (property_exists($field, 'rawvalue'))
				$datas = explode(',', $field->rawvalue);
			if (count($datas)>3) {
				$lat = $datas[0];
				$lng = $datas[1];
				$lat_field = $datas[0];
				$lng_field = $datas[1];
				$zoom = $datas[2];
				$lenth = strlen ($lat.$lng.$zoom)+3;
				$texte = str_replace('"', '\"', substr($field->rawvalue, $lenth));
			} elseif ($field->default_value) {
				$datas = explode(',', $field->default_value);
				if (count($datas)>3) {
					$lat = $datas[0];
					$lng = $datas[1];
					$lat_field = $datas[0];
					$lng_field = $datas[1];
					$zoom = $datas[2];
					$lenth = strlen ($lat.$lng.$zoom)+3;
					$texte = substr($field->rawvalue, $lenth);
				}
			} 
			if($lat == '999999') {
				$lat = 47.91474210103591;
				$lng = 2.146266102790833;
				$lat_field = '';
				$lng_field = '';
				$zoom = 7;
				$texte = "";
			}

			$layers = '';
			$baseLayers = array();
			$aliasLayers = array();
			$paramsLayers = $params->get('gmapfp_osm_layers');
			if (is_string($paramsLayers))
				$json_layers = json_decode($paramsLayers);
			if ($json_layers && json_last_error() == JSON_ERROR_NONE) {
				$paramsLayers = $json_layers;
			}
			if ($paramsLayers)
				foreach($paramsLayers AS $layer){
					$layers .= 'var '.$layer->alias.' = L.tileLayer("'.$layer->url.'", {';
					$layers .= 'attribution: "'.str_replace(array('"','‹'), array('\"','<'), $layer->attribution).'",';
					$layers .= 'minZoom: 1,';
					$layers .= 'maxZoom: '.$layer->max_zoom.',';
					$layers .= 'id: "'.$layer->alias.'"';
					$layers .= '});';
					$layers .= 'mes_layers'.$field->name_.'["'.$layer->alias.'"] = '.$layer->alias.';';
					$baseLayers[] = '"'.$layer->name.'":'.$layer->alias;
					$aliasLayers[] = $layer->alias;
				}
			else {
					$layers .= 'var OSM = L.tileLayer("//{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png", {';
					$layers .= 'attribution: "© <a href=\"https://www.openstreetmap.org/copyright\" target=\"_blank\">OpenStreetMap</a> contributors",';
					$layers .= 'minZoom: 1,';
					$layers .= 'maxZoom: 20,';
					$layers .= 'id: "OSM"';
					$layers .= '});';
					$layers .= 'mes_layers'.$field->name_.'["OSM"] = OSM;';
					$baseLayers[] = '"Route":OSM';
					$aliasLayers[] = 'OSM';
					
					$layers .= 'var esri_sat = L.tileLayer("//server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}", {';
					$layers .= 'attribution: "©  <a href=\"http://www.esri.com/\" target=\"_blank\">Esri</a>i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community",';
					$layers .= 'minZoom: 1,';
					$layers .= 'maxZoom: 19,';
					$layers .= 'id: "esri_sat"';
					$layers .= '});';
					$layers .= 'mes_layers'.$field->name_.'["esri_sat"] = esri_sat;';
					$baseLayers[] = '"Satelite": esri_sat';
					$aliasLayers[] = 'esri_sat';
			}
			$var_baseLayers = 'baseLayers'.$field->name_.' = {'.implode(',', $baseLayers).'};';
			
			if (in_array($params->get('gmapfp_type_admin_osm'), $aliasLayers))
				$defaultLayer = '';
			elseif (array_key_exists(0, $aliasLayers))
				$defaultLayer = 'stylemap = "'.$aliasLayers[0].'";';
			
			HTMLHelper::_('stylesheet', 'https://unpkg.com/leaflet/dist/leaflet.css');
			HTMLHelper::_('script', 'https://unpkg.com/leaflet/dist/leaflet-src.js');
			
			HTMLHelper::stylesheet('https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css');
			HTMLHelper::script('https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js');

			$js = ''
				.'var map'.$field->name_.';'
				.'var marker1'.$field->name_.';'
				.'var mes_layers'.$field->name_.' = [];'
				.'var lat'.$field->name_.' = '.$lat.';'
				.'var lng'.$field->name_.' = '.$lng.';'
				.'var zoom'.$field->name_.' = '.$zoom.';'
				.'var text'.$field->name_.' = "'.$texte.'";'
				.'var searchControl = "";'
				.'var baseLayers'.$field->name_.' = "";'
				.''
				.'function init_osm'.$field->name_.'() {'
				.'	var stylemap;'
				.''
				.$defaultLayer
				.''
				.$layers
				.''
				.'	var options = {'
				.'		center: [lat'.$field->name_.', lng'.$field->name_.'],'
				.'		zoom: zoom'.$field->name_.','
				.'		layers: [mes_layers'.$field->name_.'[stylemap]]'
				.'	};'
				.''
				.'	map'.$field->name_.' = L.map("map'.$targetid.'", options);'
				.''
				//affichce l'échelle
				.'	L.control.scale().addTo(map'.$field->name_.');'
				.''
				.$var_baseLayers
				.''
				.'	L.control.layers(baseLayers'.$field->name_.').addTo(map'.$field->name_.');'
				.''
				// create the geocoding control and add it to the map
				// .'var geocoder = L.Control.Geocoder.nominatim();'
				.'var geocoder = L.Control.Geocoder.photon();'
				.'if (typeof URLSearchParams !== "undefined" && location.search) {'
				.'	var params = new URLSearchParams(location.search);'
				.'	var geocoderString = params.get("geocoder");'
				.'	if (geocoderString && L.Control.Geocoder[geocoderString]) {'
				.'		console.log("Using geocoder", geocoderString);'
				.'		geocoder = L.Control.Geocoder[geocoderString]();'
				.'	} else if (geocoderString) {'
				.'		console.warn("Unsupported geocoder", geocoderString);'
				.'	}'
				.'}'
				.''
				.''
				.'	var searchControl = L.Control.geocoder({'
				.'		defaultMarkGeocode: false,'
				.'		placeholder: "'.Text::_('PLG_FIELDS_GMAPFP_OPENSTREET_SEARCH_HERE').'",'
				.'		geocoder: geocoder,'
				.'		position: "topleft"'
				.'	}).addTo(map'.$field->name_.');'
				.''
				// listen for the results event and add every result to the map
				.'	searchControl.on("markgeocode", function(data) {'
				.'		marker1'.$field->name_.'.setLatLng(data.geocode.center);'
				.'		lat'.$field->name_.' = data.geocode.center.lat;'
				.'		lng'.$field->name_.' = data.geocode.center.lng;'
				.'		jQuery("#lat_'.$targetid.'").val(lat'.$field->name_.');'
				.'		jQuery("#lng_'.$targetid.'").val(lng'.$field->name_.');'
				.'		updateresult'.$field->name_.'();'
				.'	});'
				.''
				// Create a draggable marker which will later on be binded to a'
				.'	marker1'.$field->name_.' = L.marker([lat'.$field->name_.', lng'.$field->name_.'], {draggable: true}).addTo(map'.$field->name_.');'
				.'	marker1'.$field->name_.'.bindPopup("'.Text::_('PLG_FIELDS_GMAPFP_OPENSTREET_DRAG_ME').'");'."\r\n"
				.''
				.'	marker1'.$field->name_.'.on("dragend", function(ev){'
				.'		var position = ev.target;'
				.'		lat'.$field->name_.' = position.getLatLng().lat;'
				.'		lng'.$field->name_.' = position.getLatLng().lng;'
				.'		jQuery("#lat_'.$targetid.'").val(lat'.$field->name_.');'
				.'		jQuery("#lng_'.$targetid.'").val(lng'.$field->name_.');'
				.'		updateresult'.$field->name_.'();'
				.'	});'
				.''
				.'	map'.$field->name_.'.on("zoom", function(ev){'
				.'		var zoom = ev.target;'
				.'		zoom'.$field->name_.' = zoom.getZoom();'
				.'		updateresult'.$field->name_.'();'
				.'	});'
				.''
				.'}'
				.''
				// Register an event listener to fire when the page finishes loading
				.'jQuery( document ).ready(initialise_map_gmapfp'.$field->name_.');'
				.''
				.'var tstGMapFP'.$field->name_.';'
				.'var tstIntGMapFP'.$field->name_.';'
				.''
				.'function CheckGMapFP'.$field->name_.'() {'
				.'	if (tstGMapFP'.$field->name_.') {'
				.'		if (tstGMapFP'.$field->name_.'.offsetWidth != tstGMapFP'.$field->name_.'.getAttribute("oldValue")) {'
				.'			tstGMapFP'.$field->name_.'.setAttribute("oldValue",tstGMapFP'.$field->name_.'.offsetWidth);'
				.'			init_osm'.$field->name_.'();'
				.'		}'
				.'	}'
				.'}'
				.''
				.'function updateresult'.$field->name_.'() {'
				.'	jQuery("#'.$targetid.'").val(lat'.$field->name_.'+","+lng'.$field->name_.'+","+zoom'.$field->name_.'+","+text'.$field->name_.');'
				.'}'
				.''
				.'function lat_'.$field->name_.'(lat) {'
				.'	marker1'.$field->name_.'.setLatLng([lat,lng'.$field->name_.']);'
				.''
				.'}'
				.''
				.'function clearLatLng() {'
				.'		jQuery("#lat_'.$targetid.'").val(\'\');'
				.'		jQuery("#lng_'.$targetid.'").val(\'\');'
				.'		jQuery("#'.$targetid.'").val(\'\');'
				.'}'
				.''
				.'function initialise_map_gmapfp'.$field->name_.'() {'
				// Créer la div map
				// .'	jQuery("#'.$targetid.'").css("display", "none");'
				.'	jQuery("#'.$targetid.'").parent().prepend("<p><label for=\'text_'.$targetid.'\'>'.Text::_('PLG_FIELDS_GMAPFP_OPENSTREET_MARKER_TEXT').'</label></p><p><textarea style=\'width:100%\' onchange=\'text'.$field->name_.'=this.value;updateresult'.$field->name_.'();\' type=\'text\' id=\'text_'.$targetid.'\'>'.$texte.'</textarea></p>");'
				.'	jQuery("#'.$targetid.'").parent().prepend("'
				.'		<div class=\'row\'>'
				.'			<div class=\'col-lg-2\'><button class=\'btn btn-primary\' onclick=\'clearLatLng();return false;\'>'.Text::_('JCLEAR').'</button></div>'
				.'			<div class=\'col-lg-5\'><label for=\'lat_'.$targetid.'\'>'.Text::_('PLG_FIELDS_GMAPFP_OPENSTREET_LAT').'</label><input class=\'form-control\' onchange=\'lat_'.$field->name_.'(this.value);updateresult'.$field->name_.'();\' type=\'text\' id=\'lat_'.$targetid.'\' value=\''.$lat_field.'\'/></div>'
				.'			<div class=\'col-lg-5\'><label for=\'lng_'.$targetid.'\'>'.Text::_('PLG_FIELDS_GMAPFP_OPENSTREET_LNG').'</label><input class=\'form-control\' onchange=\'lng_'.$field->name_.'(this.value);updateresult'.$field->name_.'();\' type=\'text\' id=\'lng_'.$targetid.'\' value=\''.$lng_field.'\'/></div>'
				.'		</div>");'
				.'	jQuery("#'.$targetid.'").parent().prepend("<div style=\'height:300px;\' id=\'map'.$targetid.'\'></div>");'
				.'	jQuery("#'.$targetid.'").css("display", "none");'
				.'	tstGMapFP'.$field->name_.' = document.getElementById("map'.$targetid.'");'
				.'  tstGMapFP'.$field->name_.'.setAttribute("oldValue",0);'
				.'  tstIntGMapFP'.$field->name_.' = setInterval("CheckGMapFP'.$field->name_.'()",500);'
				.'}'
				.''
				;

			$doc->addScriptDeclaration($js);

		}

		// Set filter to user UTC
		$fieldNode->setAttribute('filter', 'raw');

		// Set field to use translated formats
		$fieldNode->setAttribute('translateformat', '0');

		return $fieldNode;
	}
}

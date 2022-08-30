<?php
/**
 * GMapFP! Openstreet Field Project
 * Version J3_5
 * Creation date: Octobre 2020
 * Author: Fabrice4821 - https://creation-web.pro
 * Author email: webmaster@gmapfp.org
 *
 * @copyright   Copyright (C) 2011 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

//'Plg' . str_replace('-', '', $this->group) . $this->element . 'InstallerScript'
class Plgfieldsgmapfp_openstreetInstallerScript{

	// Method to run after the install routine.
	function postflight($type, $parent) 
	{
		//active les plugins
		$this->enable_plugins();

		$this->affiche_bienvenue($type);
	}
	
	function update($parent) 
	{
		// echo serialize($parent);
		// echo '<p>The module has been updated to version</p>';
		// echo '<p>The module has been updated to version' . $parent->get('manifest')->version . '.</p>';
	}

	private function enable_plugins()
	{
		$db    = Factory::getDbo();
		
		// plg_fields_gmapfp_openstreet
		$query = $db->getQuery(true);
		$fields = array($db->quoteName('enabled') . ' = 1');
		$conditions = array($db->quoteName('name') . ' = ' . $db->quote('plg_fields_gmapfp_openstreet'));
		$query->update($db->quoteName('#__extensions'))->set($fields)->where($conditions);
		$db->setQuery($query);
		$db->execute();
		
	}

	function affiche_bienvenue($type) {

		if($type=='install' || $type=='update'){

			// Load language file for module
			$lang		= Factory::getLanguage();
			$langue		= substr((@$lang->getTag()),0,2);
			if ($langue!='fr') $langue = 'en';

			if ($langue=='fr') {
				echo '<h2>Fields - GMapFP Carte OpenStreet</h2>';
				echo "<p>Ce plugin vous permet de créer de nouveaux champs de type 'Carte OpenStreet Map' dans toutes les extensions où les champs personnalisés sont supportés.</p>";
				echo "<a href='https://creation-web.pro/' target='_blank'>GMapFP Développement</a>";
			} else {
				echo '<h2>Fields - GMapFP OpenStreet Map</h2>';
				echo "<p>This plugin lets you create new fields of type 'Open Street Map' in any extensions where custom fields are supported.</p>";
				echo "<a href='https://creation-web.pro/' target='_blank'>GMapFP Development</a>";
			}
		}
		return true;
	}
}
<?php
// No direct access to this file
defined('_JEXEC') or die;

if (!class_exists('ScriptAry'))
{
	include dirname(__FILE__) . '/scriptary.php';
}




// * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html




/**
 * Script file
 */

class plgContentAutoreadmoreInstallerScript extends ScriptAry {
	function __construct() {
		}
	/**
	 * method to install the component
	 *
	 * @return void
	 */
	function install($parent) {
		// $parent is the class calling this method
		//$parent->getParent()->setRedirectURL('index.php?option=com_helloworld');
	}

	/**
	 * method to uninstall the component
	 *
	 * @return void
	 */
	function uninstall($parent) {
		// $parent is the class calling this method
		//echo '<p>' . JText::_('COM_HELLOWORLD_UNINSTALL_TEXT') . '</p>';
	}

	/**
	 * method to update the component
	 *
	 * @return void
	 */
	function update($parent) {
		// $parent is the class calling this method
		//echo '<p>' . JText::_('COM_HELLOWORLD_UPDATE_TEXT') . '</p>';
	}

	/**
	 * method to run before an install/update/uninstall method
	 *
	 * @return void
	 */
	function preflight($type, $parent) {
		$manifest = $parent->getParent()->getManifest();
		$this->plg_name = 'autoreadmore';
		$this->plg_type = $manifest['group'];
		$this->plg_full_name = 'plg_'.$this->plg_type.'_'.$this->plg_name;
		$this->langShortCode = null;//is used for building joomfish links
		$this->default_lang = JComponentHelper::getParams('com_languages')->get('admin');
		$language = JFactory::getLanguage();

		$language->load($this->plg_full_name, dirname(__FILE__), 'en-GB', true);
		$language->load($this->plg_full_name, dirname(__FILE__), $this->default_lang, true);

		// $parent is the class calling this method
		// $type is the type of change (install, update or discover_install)
		//echo '<p>' . JText::_('COM_HELLOWORLD_PREFLIGHT_' . $type . '_TEXT') . '</p>';
	}

	/**
	 * method to run after an install/update/uninstall method
	 *
	 * @return void
	 */
	function postflight($type, $parent) {
		$manifest = $parent->getParent()->getManifest();

		if ($type == 'install') {
			//Get the smallest order value
			$db = JFactory::getDbo();
			// Create a new query object.
			$query = $db->getQuery(true);
			$query
				->select($db->quoteName(array('extension_id','element','ordering')))
				->from($db->quoteName('#__extensions'))
				->where($db->quoteName('type').'='.$db->Quote($manifest['type']))
				->where($db->quoteName('folder').'='.$db->Quote($manifest['group']))
				->order($db->quoteName('ordering').' ASC');

			$db->setQuery($query,0,1);
			$row = $db->loadAssoc();
			$ordering = $row['ordering']-1;

			$query = $db->getQuery(true);
			// Fields to update.
			$fields = array(
				$db->quoteName('ordering').'='.$db->Quote($ordering)
			);
			// Conditions for which records should be updated.
			$conditions = array(
				$db->quoteName('type').'='.$db->Quote($manifest['type']),
				$db->quoteName('folder').'='.$db->Quote($manifest['group']),
				$db->quoteName('element').'='.$db->Quote('autoreadmore')
			);
			$query->update($db->quoteName('#__extensions'))->set($fields)->where($conditions);
			$db->setQuery($query);


			try {// It's a DB usage construction to contain J2.5 and J3.0 approaches
				if ($result = $db->execute() ) {
					if ($db->getAffectedRows()>0) {
						$this->messages[] = JText::_('GJ_INSTALL_ORDERING_SET');
					}
					else {
						throw new Exception(JText::_('GJ_INSTALL_ORDERING_SET_FAILED'));
					}
				}
				else {
					throw new Exception($db->getErrorMsg());
				}

			} catch (Exception $e) {
				// Catch the error.
				JFactory::getApplication()->enqueueMessage($e->getMessage(), 'error');
			}
		}

		if ($type != 'uninstall') {
			$this->installExtensions($parent);
		}
		// $parent is the class calling this method
		// $type is the type of change (install, update or discover_install)
		//echo '<p>' . JText::_('COM_HELLOWORLD_POSTFLIGHT_' . $type . '_TEXT') . '</p>';
		if (!empty($this->messages)) {
			echo '<ul><li>'.implode('</li><li>',$this->messages).'</li></ul>';
		}
	}
	private function installExtensions ($parent) {
		jimport('joomla.filesystem.folder');
		jimport('joomla.installer.installer');

		JLoader::register('LanguagesModelInstalled', JPATH_ADMINISTRATOR.'/components/com_languages/models/installed.php');
		$lang = new LanguagesModelInstalled();
		$current_languages = $lang ->getData();
		$locales = array();
		foreach($current_languages as $lang) {
			$locales[]=$lang->language;
		}
		$extpath = dirname(__FILE__).'/extensions';
		if (!is_dir($extpath)) {
			return;
		}
		$folders = JFolder::folders ($extpath);
		foreach ($folders as $folder) {
			$folder_temp = explode('_',$folder,2);
			if (isset ($folder_temp[0])) {
				$check_if_language = $folder_temp[0];
				if (preg_match('~[a-z]{2}-[A-Z]{2}~',$check_if_language)) {
					if (!in_array($folder_temp[0],$locales)) {
						continue;
					}
				}

			}
			$installer = new JInstaller();

			if ($installer->install($extpath.'/'.$folder)) {
				//~ $manifest = $installer->getManifest();
				//~ $this->messages[] = JText::sprintf('COM_INSTALLER_INSTALL_SUCCESS','<b style="color:#0055BB;">['.$manifest->name.']<span style="color:green;">').'</span></b>';
			}
			else {
				$this->messages[] = '<span style="color:red;">'.$folder . ' '.JText::_('JERROR_AN_ERROR_HAS_OCCURRED') . '</span>';
			}
		}
	}


}
?>


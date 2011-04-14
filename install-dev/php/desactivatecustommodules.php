<?php
/*
* 2007-2011 PrestaShop 
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2011 PrestaShop SA
*  @version  Release: $Revision: 1.4 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/
function desactivate_custom_modules()
{
	// Get all modules then select only payment ones
	$arrInstalledModules = Module::getModulesInstalled();
	// get native module list 
	$module_list_xml = INSTALL_PATH.'/../config/modules_list.xml';
	$nativeModules = simplexml_load_file($module_list_xml);
	$nativeModules = $nativeModules->modules;
	if ($nativeModules['type'] == 'native')
	{
		foreach ($nativeModules->module as $module)
			$arrNativeModules[] = $module['name'].'';
	}
	$uninstallMe = array("rien");
	foreach($arrInstalledModules as $aModule)
	{
		if(!in_array($aModule['name'],$arrNativeModules))
		{
			$uninstallMe[] = $aModule['name'];
		}
	}
	Module::disableByName($uninstallMe);
	foreach ($modules AS $module)
	{
		$file = _PS_MODULE_DIR_.$module['name'].'/'.$module['name'].'.php';
		if (!file_exists($file))
			continue;
		$fd = fopen($file, 'r');
		if (!$fd)
			continue ;
		$content = fread($fd, filesize($file));
		if (preg_match_all('/extends PaymentModule/U', $content, $matches))
		{
			Db::getInstance()->Execute('
			INSERT INTO `'._DB_PREFIX_.'module_country` (id_module, id_country)
			SELECT '.(int)($module['id_module']).', id_country FROM `'._DB_PREFIX_.'country` WHERE active = 1');
			Db::getInstance()->Execute('
			INSERT INTO `'._DB_PREFIX_.'module_currency` (id_module, id_currency)
			SELECT '.(int)($module['id_module']).', id_currency FROM `'._DB_PREFIX_.'currency` WHERE deleted = 0');
		}
		fclose($fd);
	}
}



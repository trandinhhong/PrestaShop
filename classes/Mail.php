<?php
/*
* 2007-2010 PrestaShop 
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
*  @author Prestashop SA <contact@prestashop.com>
*  @copyright  2007-2010 Prestashop SA
*  @version  Release: $Revision: 1.4 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registred Trademark & Property of PrestaShop SA
*/

include_once(_PS_SWIFT_DIR_.'Swift.php');
include_once(_PS_SWIFT_DIR_.'Swift/Connection/SMTP.php');
include_once(_PS_SWIFT_DIR_.'Swift/Connection/NativeMail.php');
include_once(_PS_SWIFT_DIR_.'Swift/Plugin/Decorator.php');

class MailCore
{
	static public function Send($id_lang, $template, $subject, $templateVars, $to, $toName = NULL, $from = NULL, $fromName = NULL, $fileAttachment = NULL, $modeSMTP = NULL, $templatePath = _PS_MAIL_DIR_)
	{
		$configuration = Configuration::getMultiple(array('PS_SHOP_EMAIL', 'PS_MAIL_METHOD', 'PS_MAIL_SERVER', 'PS_MAIL_USER', 'PS_MAIL_PASSWD', 'PS_SHOP_NAME', 'PS_MAIL_SMTP_ENCRYPTION', 'PS_MAIL_SMTP_PORT', 'PS_MAIL_METHOD', 'PS_MAIL_TYPE'));
		if(!isset($configuration['PS_MAIL_SMTP_ENCRYPTION'])) $configuration['PS_MAIL_SMTP_ENCRYPTION'] = "off";
		if(!isset($configuration['PS_MAIL_SMTP_PORT'])) $configuration['PS_MAIL_SMTP_PORT'] = "default";

		if (!isset($from)) $from = $configuration['PS_SHOP_EMAIL'];
		if (!isset($fromName)) $fromName = $configuration['PS_SHOP_NAME'];

		if (!empty($from) AND !Validate::isEmail($from))
	 		die (Tools::displayError('error / mail: parameter "from" is corrupted'));
			
		if (!empty($fromName) AND !Validate::isMailName($fromName))
	 		die (Tools::displayError('error / mail: parameter "fromName" is corrupted'));
			
		if (!is_array($to) AND !Validate::isEmail($to))
	 		die (Tools::displayError('error / mail: parameter "to" is corrupted'));
			
		if (!is_array($templateVars))
	 		die (Tools::displayError('error / mail: parameter "templateVars" is not an array'));
		
		// Do not crash for this error, that may be a complicated customer name
		if (!empty($toName) AND !Validate::isMailName($toName))
	 		$toName = NULL;
			
		if (!Validate::isTplName($template))
	 		die (Tools::displayError('error / mail: template name is corrupted'));
			
		if (!Validate::isMailSubject($subject))
	 		die (Tools::displayError('error / mail: subject name is not valid'));

		/* Construct multiple recipients list if needed */
		if (is_array($to))
		{
			$to_list = new Swift_RecipientList();
			foreach ($to as $key => $addr)
			{
				$to_name = NULL;
				$addr = trim($addr);
				if (!Validate::isEmail($addr))
					die(Tools::displayError('Error: mail parameters are corrupted'));
				if ($toName AND is_array($toName) AND Validate::isGenericName($toName[$key]))
					$to_name = $toName[$key];
				$to_list->addTo($addr, $to_name);
			}
			$to_plugin = $to[0];
			$to = $to_list;
		} else {
			/* Simple recipient, one address */
			$to_plugin = $to;
			$to = new Swift_Address($to, $toName);
		}
		try {
			/* Connect with the appropriate configuration */
			if ((int)($configuration['PS_MAIL_METHOD']) == 2)
			{
				$connection = new Swift_Connection_SMTP($configuration['PS_MAIL_SERVER'], $configuration['PS_MAIL_SMTP_PORT'], ($configuration['PS_MAIL_SMTP_ENCRYPTION'] == "ssl") ? Swift_Connection_SMTP::ENC_SSL : (($configuration['PS_MAIL_SMTP_ENCRYPTION'] == "tls") ? Swift_Connection_SMTP::ENC_TLS : Swift_Connection_SMTP::ENC_OFF));
				$connection->setTimeout(4);
				if (!$connection)
					return false;
				if (!empty($configuration['PS_MAIL_USER']) AND !empty($configuration['PS_MAIL_PASSWD']))
				{
					$connection->setUsername($configuration['PS_MAIL_USER']);
					$connection->setPassword($configuration['PS_MAIL_PASSWD']);
				}
			}
			else
				$connection = new Swift_Connection_NativeMail();

			if (!$connection)
				return false;
			$swift = new Swift($connection);
			/* Get templates content */
			$iso = Language::getIsoById((int)($id_lang));
			if (!$iso)
				die (Tools::displayError('Error - No iso code for email !'));
			$template = $iso.'/'.$template;

			$moduleName = false;
			$overrideMail = false;

			// get templatePath
			if (preg_match('#'.__PS_BASE_URI__.'modules/#', $templatePath) AND preg_match('#modules/([a-z0-9_-]+)/#ui' , $templatePath , $res))
				$moduleName = $res[1];

			if ($moduleName !== false AND (file_exists(_PS_THEME_DIR_.'modules/'.$moduleName.'/mails/'.$template.'.txt') OR
				file_exists(_PS_THEME_DIR_.'modules/'.$moduleName.'/mails/'.$template.'.html')))
				$templatePath = _PS_THEME_DIR_.'modules/'.$moduleName.'/mails/';
			elseif (file_exists(_PS_THEME_DIR_.'mails/'.$template.'.txt') OR file_exists(_PS_THEME_DIR_.'mails/'.$template.'.html'))
			{
				$templatePath = _PS_THEME_DIR_.'mails/';
				$overrideMail  = true;
			}
			elseif (!file_exists($templatePath.$template.'.txt') OR !file_exists($templatePath.$template.'.html'))
				die(Tools::displayError('Error - The following email template is missing:').' '.$templatePath.$template.'.txt');

			$templateHtml = file_get_contents($templatePath.$template.'.html');
			$templateTxt = strip_tags(html_entity_decode(file_get_contents($templatePath.$template.'.txt'), NULL, 'utf-8'));

			if ($overrideMail AND file_exists($templatePath.$iso.'/lang.php'))
					include_once($templatePath.$iso.'/lang.php');
			elseif ($moduleName AND file_exists($templatePath.$iso.'/lang.php'))
				include_once(_PS_THEME_DIR_.'mails/'.$iso.'/lang.php');
			else
				include_once(dirname(__FILE__).'/../mails/'.$iso.'/lang.php');

			global $_LANGMAIL;
			/* Create mail and attach differents parts */
			$message = new Swift_Message('['.Configuration::get('PS_SHOP_NAME').'] '.((is_array($_LANGMAIL) AND key_exists($subject, $_LANGMAIL)) ? $_LANGMAIL[$subject] : $subject));
			$templateVars['{shop_logo}'] = (file_exists(_PS_IMG_DIR_.'logo_mail.jpg')) ? $message->attach(new Swift_Message_Image(new Swift_File(_PS_IMG_DIR_.'logo_mail.jpg'))) : ((file_exists(_PS_IMG_DIR_.'logo.jpg')) ? $message->attach(new Swift_Message_Image(new Swift_File(_PS_IMG_DIR_.'logo.jpg'))) : '');
			$templateVars['{shop_name}'] = Configuration::get('PS_SHOP_NAME');
			$templateVars['{shop_url}'] = 'http://'.Tools::getHttpHost(false, true).__PS_BASE_URI__;
			$swift->attachPlugin(new Swift_Plugin_Decorator(array($to_plugin => $templateVars)), 'decorator');
			if ($configuration['PS_MAIL_TYPE'] == 3 OR $configuration['PS_MAIL_TYPE'] == 2)
				$message->attach(new Swift_Message_Part($templateTxt, 'text/plain', '8bit', 'utf-8'));
			if ($configuration['PS_MAIL_TYPE'] == 3 OR $configuration['PS_MAIL_TYPE'] == 1)
				$message->attach(new Swift_Message_Part($templateHtml, 'text/html', '8bit', 'utf-8'));
			if ($fileAttachment AND isset($fileAttachment['content']) AND isset($fileAttachment['name']) AND isset($fileAttachment['mime']))
				$message->attach(new Swift_Message_Attachment($fileAttachment['content'], $fileAttachment['name'], $fileAttachment['mime']));
			/* Send mail */
			$send = $swift->send($message, $to, new Swift_Address($from, $fromName));
			$swift->disconnect();
			return $send;
		}

		catch (Swift_ConnectionException $e) { return false; }
	}

	static public function sendMailTest($smtpChecked, $smtpServer, $content, $subject, $type, $to, $from, $smtpLogin, $smtpPassword, $smtpPort = 25, $smtpEncryption)
	{
		$swift = NULL;
		$result = NULL;
		try
		{
			if($smtpChecked)
			{

				$smtp = new Swift_Connection_SMTP($smtpServer, $smtpPort, ($smtpEncryption == "off") ? Swift_Connection_SMTP::ENC_OFF : (($smtpEncryption == "tls") ? Swift_Connection_SMTP::ENC_TLS : Swift_Connection_SMTP::ENC_SSL));
				$smtp->setUsername($smtpLogin);
				$smtp->setpassword($smtpPassword);
				$smtp->setTimeout(5);
				$swift = new Swift($smtp);
			}
			else
				$swift = new Swift(new Swift_Connection_NativeMail());

			$message = new Swift_Message($subject, $content, $type);

			if ($swift->send($message, $to, $from))
				$result = true;
			else
				$result = 999;

			$swift->disconnect();
		}
		catch (Swift_Connection_Exception $e) { $result = $e->getCode(); }
		catch (Swift_Message_MimeException $e) { $result = $e->getCode(); }

		return $result;
	}

	static public function l($string)
	{
		global $_LANGMAIL;

		$key = str_replace('\'', '\\\'', $string);
		$id_lang = (!isset($cookie) OR !is_object($cookie)) ? (int)(Configuration::get('PS_LANG_DEFAULT')) : (int)($cookie->id_lang);

		$file = _PS_THEME_DIR_.'mails/'.Language::getIsoById((int)$id_lang).'/lang.php';
		if (file_exists($file))
			include_once($file);

		if (!is_array($_LANGMAIL))
			return (str_replace('"', '&quot;', $string));

		$str = (key_exists($key, $_LANGMAIL)) ? $_LANGMAIL[$key] : ((key_exists($key, $_LANGMAIL)) ? $_LANGMAIL[$key] : $string);
		$str = htmlentities($str, ENT_QUOTES, 'utf-8');

		return str_replace('"', '&quot;', addslashes($str));
	}
}
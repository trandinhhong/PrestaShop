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

var pc_serialScrollNbImagesDisplayed;
var pc_serialScrollNbImages;
var pc_serialScrollActualImagesIndex;

function pc_serialScrollFixLock(event, targeted, scrolled, items, position)
{
	serialScrollNbImages = $('#productscategory_list li:visible').length;
	serialScrollNbImagesDisplayed = 5;
	
	var leftArrow = position == 0 ? true : false;
	var rightArrow = position + serialScrollNbImagesDisplayed >= serialScrollNbImages ? true : false;
	
	$('a#productscategory_scroll_left').css('cursor', leftArrow ? 'default' : 'pointer').fadeTo(0, leftArrow ? 0 : 1);		
	$('a#productscategory_scroll_right').css('cursor', rightArrow ? 'default' : 'pointer').fadeTo(0, rightArrow ? 0 : 1).css('display', rightArrow ? 'none' : 'block');
	return true;
}

$(document).ready(function(){
//init the serialScroll for thumbs
	pc_serialScrollNbImages = $('#productscategory_list li').length;
	pc_serialScrollNbImagesDisplayed = 5;
	pc_serialScrollActualImagesIndex = 0;
	$('#productscategory_list').serialScroll({
		items:'li',
		prev:'a#productscategory_scroll_left',
		next:'a#productscategory_scroll_right',
		axis:'x',
		offset:0,
		stop:true,
		onBefore:pc_serialScrollFixLock,
		duration:300,
		step: 1,
		lazy:true,
		lock: false,
		force:false,
		cycle:false
	});
	$('#productscategory_list').trigger( 'goto', [middle-3] );
});
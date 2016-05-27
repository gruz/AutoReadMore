<?php
/**
 * AutoReadMore plugin
 *
 * @package		AutoReadMore
 * @author www.toao.net
 * @author Gruz <arygroup@gmail.com>
 * @copyright	Copyleft - All rights reversed
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');
jimport( 'joomla.plugin.plugin' );
jimport( 'gjfields.gjfields' );
jimport( 'gjfields.helper.plugin' );

$latest_gjfields_needed_version = '1.0.43';
$error_msg = 'Install the latest GJFields plugin version <span style="color:black;">'.__FILE__.'</span>: <a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>';

$isOk = true;
while (true) {
	$isOk = false;
	if (!class_exists('JPluginGJFields')) {
		$error_msg = 'Strange, but missing GJFields library for <span style="color:black;">'.__FILE__.'</span><br> The library should be installed together with the extension... Anyway, reinstall it: <a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>';
		break;
	}
	$gjfields_version = file_get_contents(JPATH_ROOT.'/libraries/gjfields/gjfields.xml');
	preg_match('~<version>(.*)</version>~Ui',$gjfields_version,$gjfields_version);
	$gjfields_version = $gjfields_version[1];
	if (version_compare($gjfields_version,$latest_gjfields_needed_version,'<')) {
		break;
	}
	$isOk = true;
	break;
}
if (!$isOk) {
	JFactory::getApplication()->enqueueMessage($error_msg, 'error');
	if (JFactory::getApplication()->isAdmin()) {
	}
}
else {


class plgContentAutoReadMoreCore extends JPluginGJFields {


	/**
	 * Defines some some variables and loads languages.
	 *
	 * It's the common function I use in many plugins
	 *
	 * @author Gruz <arygroup@gmail.com>
	 */
	public function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
		//~ $this->plg_name = $config['name'];
		//~ $this->plg_type = $config['type'];
		//~ $this->_loadLanguage();
	}


	/**
	 * Loads English and the national to prevent untranslated constants
	 *
	 * It's the common function I use in many plugins
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @return	void
	 */
	private function _loadLanguage() {
		$path = dirname(__FILE__);
		$ext_name = 'plg_'.$this->plg_type.'_'.$this->plg_name;
		$jlang = JFactory::getLanguage();
		$jlang->load($ext_name, $path, 'en-GB', true);
		$jlang->load($ext_name, $path, $jlang->getDefault(), true);
		$jlang->load($ext_name, $path, null, true);
	}


	/**
	 * Truncates the article text
	 *
	 * @param	string	$context 	The context of the content being passed to the plugin.
	 * @param	object	$article	The article object.
	 * @param	object	$params	The article params
	 * @param	int	$page	Is int 0 when is called not form an article, and empty when called from an article
	 *
	 */
	public function onContentPrepare($context, &$article, &$params, $page=null){
		$jinput = JFactory::getApplication()->input;
		if ($jinput->get('option',null,'CMD') == 'com_dump') {return;}
		$tmp = array_filter((array)$params);
		if (empty($tmp)) {return;} // It seems that modules don't have params but are parsed with the fucntion. So stop this.
		unset($tmp);
		$this->params_content = $params;

		// SOME SPECIAL RETURNS {
		if($context == 'easyblog.blog' && $jinput->get('view',null,'CMD')  == 'entry' ){// fix easyblog
			return;
		}
		if (strpos($context,'mod_') === 0) { // Not to work on modules, like mod_roksprocket.article
			return;
		}
		// SOME SPECIAL RETURNS }

		$debug = $this->paramGet('debug') ;
		if ($debug) {
			if (function_exists('dump')) {
				dump ($article,'context = '. $context);
			}
			else {
				if ($debug == 1) {
					JFactory::getApplication()->enqueueMessage(
					'Context : '.$context . '<br />'.
					'Title : '.@$article->title . '<br />'.
					'Id : '.@$article->id . '<br />'
					, 'warning');
				}
				elseif($debug == 2) {
					echo '<pre style="height:400px;overflow:auto;">';
					echo '<b>Context : '.$context . '</b><br />';
					print_r($article);
					echo '</pre>'.PHP_EOL;
				}
			}
		}

		$context_global = explode ('.',$context);
		$context_global = $context_global [0];
		if (!JFactory::getApplication()->isSite()) {return;}

		// check allowed context
		if ( $context_global =='com_virtuemart' ) { return;} // Hardcoded contexts
		$contextsToExclude = $this->paramGet('contextsToExclude');
		$contextsToExclude = array_map('trim',explode(",",$contextsToExclude));

		// Some hard-coded contexts to exclude
		$contextsToExclude[] = 'com_tz_portfolio.p_article';
		if (in_array($context_global,$contextsToExclude) || in_array($context,$contextsToExclude)  ) { return; }

		//if ($context != "com_content.article") {return;}
		$jinput = JFactory::getApplication()->input;
		$view = $jinput->get('view',null,'CMD');
		$article_id = $jinput->get('id',null,'INT');

		//I leave as a note for myself: if ($page === 0) { /*the article is loaded from a module, as far as I can see. But I use another method to check article or modue. */ }
		if (
			($view == "article" && $article->id == $article_id) ||
			($context == 'com_k2.item' && $article->id == $article_id)) {//it it's already a full article - go away'

			if (!isset($GLOBALS['joomlaAutoReadMorePluginArticleShown']) ) { // But leave a flag not to go aways in a module
				$GLOBALS['joomlaAutoReadMorePluginArticleShown'] = $article_id;
				return;
			}
		}
		if ($this->paramGet('Enabled_Front_Page') == 0 and $view=='featured') { return;}


		if (!isset($GLOBALS['+xji*;!1'])) {
			$doc = JFactory::getDocument();
			$csscode = $this->paramGet('csscode');
			$doc->addStyleDeclaration( $csscode);
			$GLOBALS['+xji*;!1'] = true;
		}

		if ($this->paramGet('Ignore_Existing_Read_More') == 0 && !empty($article->readmore) && $article->readmore>0 ) {
			if ($this->paramGet('Force_Image_Handle')) {
				$article->introtext = $this->getThumbNails($article->introtext,$article).$article->introtext;

			}
			return;
		}
		$articles_switch = $this->paramGet('articles_switch');

		$articles = $this->paramGet('articles');
		$articles = explode (',',$articles);

		$checkincats = true;
		switch ($articles_switch) {
			case '0'://no specific articles set
				$checkincats = true;
				break;
			case '1'://some articles are selected
				//if the article is among the seleted ones - do not check for cats
				if (in_array($article->id,$articles)) {
					$checkincats = false;
				}
				break;
			case '2'://some articles are excluded
				//if the article is among the excluded ones - return
				if (in_array($article->id,$articles)) {
					return;
				}
				break;
			default :
				return;
				break;
		}

		//check if the article is allowed based on the cats selection
		while (true) {
			if (!$checkincats) {break;}
			$categories_switch = $this->paramGet('categories_switch');
			if ($categories_switch == 0) { break; }

			$categories = (array)$this->paramGet('categories');
			$in_array = in_array ($article->catid,$categories);
			if (!$in_array) {
				$categories_text = explode(',',$this->paramGet('categories_text'));
				$in_array = in_array ($article->catid,$categories_text);
			}

			switch ($categories_switch) {
				case '0'://ALL CATS
					//do nothing
					break;
				case '1'://selected cats
					//selection list is empty or the category is not in the selection list
					if (empty ($categories) || !$in_array) { return; }
					break;
				case '2'://excludes cats
					if ($in_array) { return; }
					break;
				default :
					return;
					break;
			}
			break;
		}

		// How many characters are we allowed?
		//$app = & JFactory::getApplication();
		// get current menu item number of leading articles
		$app = JFactory::getApplication();

		//it's strange, but I couldn't call the variable as $params - it removes the header lin in that case. I can't believe, but true.
		// So don't use $params = $app->getParams(); Either use another var name like $gparams = $app->getParams(); or the direct call
		// as shown below: $app->getParams()->def('num_leading_articles', 0);
		$num_leading_articles = $app->getParams()->def('num_leading_articles', 0);

		/* It seems this link manipulations are not needed. Link is present in the article itself
		while (true) {
			$option = $jinput->get('option',null,'CMD');
			if (in_array($context_global,array ( // Do not create link for K2, VM and so on
					'com_k2',
					'com_virtuemart'
				) ))
			{
				break;
			}
			// Prepare the article link
			if ( $params->get('access-view') ) :
				$link = JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catid));
			else :
				$menu = JFactory::getApplication()->getMenu();
				$active = $menu->getActive();
				if (!isset($active->id)) {
					$active = $menu->getDefault();
				}
				$itemId = $active->id;
				$link1 = JRoute::_('index.php?option=com_users&view=login&Itemid=' . $itemId);
				$returnURL = JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catid));
				$link = new JURI($link1);
				$link->setVar('return', base64_encode($returnURL));
			endif;

			break;
		}
		*/


		// count how many times the plugin is called to know if the current article is a leading one
		$GLOBALS['plg_content_AutoReadMore_Count'] = (isset($GLOBALS['plg_content_AutoReadMore_Count'])) ? $GLOBALS['plg_content_AutoReadMore_Count']+1 : 1;

		if ($GLOBALS['plg_content_AutoReadMore_Count'] <= $num_leading_articles) {
			// This is a leading (full-width) article.
			$maxLimit = $this->paramGet('leadingMax');
		} else {
			// This is not a leading article.
			$maxLimit = $this->paramGet('introMax');
		}

		if (!is_numeric($maxLimit)) $maxLimit = 500;

		// What text are we working with?
//		!empty($article->readmore)
		if (!empty($article->introtext)) {
			$text = $article->introtext;
		} else {
			$text = $article->text;
		}
		$this->fulltext_loaded = false;//fulltext is not loaded in J2.5, we must load it manually if needed
		//if we ignore manual readmore and we know it's present, then we must load the full text
		if ($this->paramGet('Ignore_Existing_Read_More') == 1 && isset($article->readmore) && $article->readmore>0 ) {
			// damn, we must load the full text...
			$this->fulltext_loaded = true;
			$text .= $this->loadFullText ($article->id);
		}

		$thumbnails = $this->getThumbNails($text,$article);

		$this->trimming_dots = '';
		if ($this->paramGet ('add_trimming_dots') != 0) {
			$this->trimming_dots = $this->paramGet ('trimming_dots');
		}

		$limittype = $this->paramGet('limittype');

		if ($limittype == 0) {// Limit by chars
			if (JString::strlen(strip_tags($text)) > $maxLimit) {
				if ($this->paramGet('Strip_Formatting') == 1) {
					// First, remove all new lines
					$text = preg_replace("/\r\n|\r|\n/", "", $text);
					// Next, replace <br /> tags with \n
					$text = preg_replace("/<BR[^>]*>/i", "\n", $text);
					// Replace <p> tags with \n\n
					$text = preg_replace("/<P[^>]*>/i", "\n\n", $text);
					// Strip all tags
					$text = strip_tags($text);
					// Truncate
					$text = JString::substr($text, 0, $maxLimit);
					//$text = String::truncate($text, $maxLimit, '...', true);
					// Pop off the last word in case it got cut in the middle
					$text = preg_replace("/[.,!?:;]? [^ ]*$/", "", $text);
					// Add ... to the end of the article.
					$text = trim($text) . $this->trimming_dots ;
					// Replace \n with <br />
					$text = str_replace("\n", "<br />", $text);
				} else {
					// Truncate
					//$text = JString::substr($text, 0, $maxLimit);
					if (!class_exists('AutoReadMoreString')) { require_once (dirname(__FILE__).'/helpers/AutoReadMoreString.php'); }

					$text = AutoReadMoreString::truncate($text, $maxLimit, '&hellip;', true);

					// Pop off the last word in case it got cut in the middle
					$text = preg_replace("/[.,!?:;]? [^ ]*$/", "", $text);
					// Pop off the last tag, if it got cut in the middle.
					$text = preg_replace('/<[^>]*$/', '', $text);

					$text = $this->addTrimmingDots($text);
					// Use Tidy to repair any bad XHTML (unclosed tags etc)
					$text = AutoReadMoreString::cleanUpHTML($text);

				}
				// Add a "read more" link.
				$article->readmore = true;
			}
		}
		else 		if ($limittype == 1) {//Limit by words

			if (!class_exists('AutoReadMoreString')) { require_once (dirname(__FILE__).'/helpers/AutoReadMoreString.php'); }
			$text = AutoReadMoreString::truncateByWords($text,$maxLimit,$article->readmore);

			$text = $this->addTrimmingDots($text);
			$text = AutoReadMoreString::cleanUpHTML($text);

		}
		else if ($limittype == 2) {// Limit by paragraphs
			$paragraphs = explode ('</p>',$text);
			if(count($paragraphs)<=$maxLimit+1) {
				// do nothing, as we have $maxLimit paragraphs
			}
			else {
				$text = array();
				for ($i = 0; $i <$maxLimit ; $i++) {
					$text[] = $paragraphs[$i];
				}
				unset ($paragraphs);
				$text = implode('</p>',$text);
				$article->readmore = true;
			}

		}
		if ($this->paramGet('Strip_Formatting') == 1) {
			$text = strip_tags($text);
		}

		// If we have thumbnails, add it to $text.
		$text = $thumbnails . $text;
		// If Developer Mode is turned on, add some stuff.
		if ($this->paramGet('Developer_Mode') == 1) {
			$text = ''
			. '<div style="height:150px;width:100%;overflow:auto;">'
			. '<b>Developer information:</b><br /><pre>'
			. 'Developers: uncomment the next line in the code to display $GLOBALS.  If you see this message and do not know what it means, you should turn Developer_Mode off in the Auto Read More configuration.'
			. (htmlspecialchars(print_r($GLOBALS, 1))) // by default, this is commented out for security.  Only uncomment it if you know what you are doing.
			. '</pre></div>'
			. $text
			;
		}
		if ($this->paramGet('wrap_output') == 1) {
			$template = $this->paramGet('wrap_output_template');
			$text = str_replace('%OUTPUT%',$text,$template);
		}
		if ($this->paramGet('readmore_text') && empty($this->alternative_readmore)) {
			$article->alternative_readmore = JText::_($this->paramGet('readmore_text'));
		}

		$article->introtext = $text;
		$article->text = $text;
	}


	/**
	 * Add Trimming dots
	 *
	 * Full description (multiline)
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param	string	$text
	 * @return	string
	 */
	function addTrimmingDots($text) {
		// Add ... to the end of the article if the last character is a letter or a number.
		if ($this->paramGet ('add_trimming_dots') == 2) {
			if (preg_match('/\w/ui', JString::substr($text, -1))) { $text = trim($text) . $this->trimming_dots; }
		}
		else {
			$text = trim($text) . $this->trimming_dots;
		}
		return $text;
	}



	/**
	 * Returns the full text of the article based on the article id
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param 	integer	$id	The id of the artice to load
	 * @return 	string	The article fulltext
	 */
	function loadFullText ($id) {
		$article = JTable::getInstance("content");
		$article->load($id);
		return $article->fulltext;
	}


	/**
	 * Returns text with handled images - added classes, stripped attributes, if needed
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param	string	$text	HTML code of the article
	 * @param	object	$article	Article object for additional information like $article->id
	 * @return	array			Array of 2 string og HTML code
	 */
	function getThumbNails( & $text, & $article) {
		// Are we working with any thumbnails?
		if ($this->paramGet('Thumbnails') < 1) {
			return;
		}
		$thumbnails = array();
		// Extract all images from the article.
		$imagesfound  = preg_match_all('/<img [^>]*>/iu', $text, $matches);
		//if we found less thumbnail then expected and the fulltext is not loaded,
		// then load fulltext and search in it also
		$matches_tmp = array ();
		if ($imagesfound < $this->paramGet('Thumbnails') && !$this->fulltext_loaded) {
			$matches_tmp = $matches[0];
			$imagesfound  = preg_match_all('/<img [^>]*>/ui', $this->loadFullText ($article->id), $matches);
		}
		$matches = array_merge($matches_tmp,$matches[0]);

		// Loop through the thumbnails.
		for ($thumbnail = 0; $thumbnail < $this->paramGet('Thumbnails'); $thumbnail++) {
			if (!isset($matches[$thumbnail])) break;
			// Remove the image from $text
			$text = str_replace($matches[$thumbnail], '', $text);
			// See if we need to remove styling.
			if (trim($this->paramGet('Thumbnails_Class')) != '') {
				// Remove style, class, width, border, and height attributes.
				if ($this->paramGet('Strip_Image_Formatting')) {
					$matches[$thumbnail] = preg_replace('/(style|class|width|height|border) ?= ?[\'"][^\'"]*[\'"]/i', '', $matches[$thumbnail]);
					// Add CSS class name.
					$matches[$thumbnail] = preg_replace('@/?>$@', 'class="' . $this->paramGet('Thumbnails_Class') . '" />', $matches[$thumbnail]);
					// Add CSS class name.
				}
				else {
					$matches[$thumbnail] = preg_replace('@(class=["\'])@', '$1' . $this->paramGet('Thumbnails_Class').' ', $matches[$thumbnail], -1, $count);
					if ($count<1) {
						$matches[$thumbnail] = preg_replace('@/?>$@', 'class="' . $this->paramGet('Thumbnails_Class') . '" />', $matches[$thumbnail]);
					}
				}
			}

			if (trim($matches[$thumbnail]) != '') {
				$thumbnails[] = $matches[$thumbnail];
			}
		}

		if (empty($thumbnails) && trim($this->paramGet('default_image')) !='' ) {
			$thumbnails[] = '<img src="'.$this->paramGet('default_image').'">';
		}

		// Make this thumbnail a link.
		//$matches[$thumbnail] = "<a href='" . $link . "'>{$matches[$thumbnail]}</a>";
		// Add to the list of thumbnails.
		if ($this->paramGet('image_link_to_article')) {
			$jinput = JFactory::getApplication()->input;
			while (true) {
				$option = $jinput->get('option',null,'CMD');
				if (in_array($option,array ( // Do not create link for K2, VM and so on
						'com_k2',
						'com_virtuemart'
					) )) 	{
					if (!empty($article->link)) {
						$link = $article->link;
					}
					break;
				}
				// Prepare the article link
				if ( $this->params_content->get('access-view') ) :
					$link = JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catid));
				else :
					$menu = JFactory::getApplication()->getMenu();
					$active = $menu->getActive();
					if (!isset($active->id)) {
						$active = $menu->getDefault();
					}
					$itemId = $active->id;
					$link1 = JRoute::_('index.php?option=com_users&view=login&Itemid=' . $itemId);
					$returnURL = JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catid));
					$link = new JURI($link1);
					$link->setVar('return', base64_encode($returnURL));
				endif;
				break;
			}
			foreach ($thumbnails as $k=>$v) {
				$thumbnails[$k] = '<a href="'.$link.'">'.$v.'</a>';
			}
		}

		return implode(PHP_EOL,$thumbnails);

	}


}


class plgContentAutoReadMore extends plgContentAutoReadMoreCore {
	public function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
	}
}

}

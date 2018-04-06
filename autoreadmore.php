<?php
/**
 * AutoReadMore plugin
 *
 * @package    AutoReadMore
 *
 * @author     Gruz <arygroup@gmail.com>
 * @copyright  0000 Copyleft - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');
jimport('joomla.plugin.plugin');
jimport('gjfields.gjfields');
jimport('gjfields.helper.plugin');

$latest_gjfields_needed_version = '1.0.43';
$error_msg = 'Install the latest GJFields plugin version <span style="color:black;">'
				. __FILE__
				. '</span>: <a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>';

$isOk = true;

while (true)
{
	$isOk = false;

	if (!class_exists('JPluginGJFields'))
	{
		$error_msg = 'Strange, but missing GJFields library for <span style="color:black;">'
		. __FILE__
		. '</span><br> The library should be installed together with the extension... Anyway, reinstall it:
		<a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>';
		break;
	}

	$gjfields_version = file_get_contents(JPATH_ROOT . '/libraries/gjfields/gjfields.xml');
	preg_match('~<version>(.*)</version>~Ui', $gjfields_version, $gjfields_version);
	$gjfields_version = $gjfields_version[1];

	if (version_compare($gjfields_version, $latest_gjfields_needed_version, '<'))
	{
		break;
	}

	$isOk = true;
	break;
}

if (!$isOk)
{
	JFactory::getApplication()->enqueueMessage($error_msg, 'error');

	if (JFactory::getApplication()->isAdmin())
	{
	}
}
else
{
	$com_path = JPATH_SITE . '/components/com_content/';

	if (!class_exists('ContentRouter'))
	{
		// Require_once $com_path.'router.php';
		require_once $com_path . 'router.php';
	}

	if (!class_exists('ContentHelperRoute') )
	{
		// Require_once $com_path.'helpers/route.php';
		require_once $com_path . 'helpers/route.php';
	}

	/**
	 * Free plugin class
	 *
	 * @since  0
	 */
	class PlgContentAutoReadMoreCore extends JPluginGJFields
				{
		/**
		 * Loads English and the national to prevent untranslated constants
		 *
		 * It's the common function I use in many plugins
		 *
		 * @return  void
		 */
		private function _loadLanguage()
		{
			$path = dirname(__FILE__);
			$ext_name = 'plg_' . $this->plg_type . '_' . $this->plg_name;
			$jlang = JFactory::getLanguage();
			$jlang->load($ext_name, $path, 'en-GB', true);
			$jlang->load($ext_name, $path, $jlang->getDefault(), true);
			$jlang->load($ext_name, $path, null, true);
		}

		/**
		 * Truncates the article text
		 *
		 * @param   string  $context   The context of the content being passed to the plugin.
		 * @param   object  &$article  The article object
		 * @param   object  &$params   The article params
		 * @param   int     $page      Returns int 0 when is called not form an article, and empty when called from an article
		 *
		 * @return   void
		 */
		public function onContentPrepare($context, &$article, &$params, $page=null)
		{
			if (!JFactory::getApplication()->isSite())
			{
				return false;
			}

			$jinput = JFactory::getApplication()->input;

			if ($jinput->get('option', null, 'CMD') == 'com_dump')
			{
				return;
			}

			// SOME SPECIAL RETURNS }
			$debug = $this->paramGet('debug');

			if ($debug)
			{
				if (function_exists('dump') && false)
				{
					dump($article, 'context = ' . $context);
				}
				else
				{
					if ($debug == 1)
					{
						JFactory::getApplication()->enqueueMessage(
						'Context : ' . $context . '<br />' .
						'Title : ' . @$article->title . '<br />' .
						'Id : ' . @$article->id . '<br />' .
						'CatId : ' . @$article->catid . '<br />',
						'warning');
					}
					elseif ($debug == 2)
					{
						echo '<pre style="height:180px;overflow:auto;">';
						echo '<b>Context : ' . $context . '</b><br />';
						echo '<b>Content Item object : </b><br />';
						print_r($article);

						if (!empty($params))
						{
							echo '<b>Params:</b><br />';
							print_r($params);
						}
						else
						{
							echo '<b>Params NOT passed</b><br />';
						}

						echo '</pre>' . PHP_EOL;
					}
				}
			}

			if ($context == 'com_tags.tag')
			{
				// $context = $article->type_alias;
				$article->catid = $article->core_catid;
				$article->id = $article->content_item_id;
				$article->slug = $article->id . ':' . $article->core_alias;
			}

			$thereIsPluginCode = false;

			if ($this->paramGet('PluginCode') != 'ignore')
			{
				$possibleParams = array('text', 'introtext', 'fulltext');

				foreach ($possibleParams as $paramName)
				{
					if (isset($article->{$paramName}) && strpos($article->{$paramName}, '{autoreadmore}') !== false)
					{
						$article->{$paramName} = str_replace(
							array('{autoreadmore}', '<p>{autoreadmore}</p>', '<span>{autoreadmore}</span>'),
							'',
							$article->{$paramName}
						);
						$thereIsPluginCode = true;
					}
				}
			}

			if (!$this->_checkIfAllowedContext($context, $article))
			{
				return;
			}

			if (!$this->_checkIfAllowedCategoryAndItem($context, $article))
			{
				return;
			}

			if (is_object($params))
			{
				$this->params_content = $params;
			}
			elseif (is_array($params))
			{
				$this->params_content = (object) $params;
			}
			else
			{
				$this->params_content = new JRegistry;

				// Load my plugin params.
				$this->params_content->loadString($params, 'JSON');
			}

			// Add css code
			if (!isset($GLOBALS['+xji*;!1']))
			{
				$doc = JFactory::getDocument();
				$csscode = $this->paramGet('csscode');
				$doc->addStyleDeclaration($csscode);
				$GLOBALS['+xji*;!1'] = true;
			}

			if (isset($article->introtext))
			{
				// For core article
				$text = $article->introtext;
			}
			else
			{
				// In most non core content items and modules
				$text = $article->text;
			}

			// Fulltext is not loaded, we must load it manually if needed
			$this->fulltext_loaded = false;

			if ($this->paramGet('Ignore_Existing_Read_More') && isset($article->introtext) && isset($article->fulltext))
			{
				$text = $article->introtext . PHP_EOL . $article->fulltext;
				if (file_exists(JPATH_PLUGINS . "/content/cck")) { //check if Seblod is installed
				    $text = preg_replace( '/::cck::(\d+)::\/cck::/', '', $text ) ; //remove the ::cck:: tags
				    $text = preg_replace( '/::introtext::/', '', $text ) ; //remove the ::introtext:: tags, keep content
				    $text = preg_replace( '/::\/introtext::/', '', $text ) ; //remove the ::introtext:: tags, keep content
				    $text = preg_replace( '/::fulltext::/', '', $text ) ; //remove the ::fulltext:: tags, keep content
				    $text = preg_replace( '/::\/fulltext::/', '', $text ) ; //remove the ::fulltext:: tags, keep content
				}
				$this->fulltext_loaded = true;
			}
			elseif ($this->paramGet('Ignore_Existing_Read_More') && isset($article->readmore) && $article->readmore > 0 )
			{
				// If we ignore manual readmore and we know it's present, then we must load the full text
				$text .= $this->loadFullText($article->id);
				$this->fulltext_loaded = true;
			}

			$ImageAsHTML = true;

			if (in_array($context, array("com_content.featured", "com_content.category")))
			{
				$ImageAsHTML = $this->paramGet('ImageAsHTML');
			}

			$thumbnails = $this->getThumbNails($text, $article, $context, $ImageAsHTML);

			// How many characters are we allowed?
			$app = JFactory::getApplication();

			// Get current menu item number of leading articles
			// It's strange, but I couldn't call the variable as $params - it removes the header line in that case. I can't believe, but true.

			// So don't use $params = $app->getParams(); Either use another var name like $gparams = $app->getParams(); or the direct call
			// as shown below: $app->getParams()->def('num_leading_articles', 0);
			$num_leading_articles = $app->getParams()->def('num_leading_articles', 0);

			// Count how many times the plugin is called to know if the current article is a leading one
			$globalCount = $app->get('Count', 0, $this->plg_name);
			$globalCount++;
			$app->set('Count', $globalCount, $this->plg_name);

			// $GLOBALS['plg_content_AutoReadMore_Count'] = (isset($GLOBALS['plg_content_AutoReadMore_Count'])) ?
			// $GLOBALS['plg_content_AutoReadMore_Count']+1 : 1;

			// ~ if ($GLOBALS['plg_content_AutoReadMore_Count'] <= $num_leading_articles)
			if ($globalCount <= $num_leading_articles)
			{
				// This is a leading (full-width) article.
				$maxLimit = $this->paramGet('leadingMax');
			}
			else
			{
				// This is not a leading article.
				$maxLimit = $this->paramGet('introMax');
			}

			if (!is_numeric($maxLimit))
			{
				$maxLimit = 500;
			}

			$this->trimming_dots = '';

			if ($this->paramGet('add_trimming_dots') != 0)
			{
				$this->trimming_dots = $this->paramGet('trimming_dots');
			}

			$limittype = $this->paramGet('limittype');

			if (isset($article->readmore))
			{
				$original_readmore = $article->readmore;
			}

			$noSpaceLanguage = $this->paramGet('noSpaceLanguage');

			switch ($this->paramGet('PluginCode'))
			{
				case 'only':
					if (!$thereIsPluginCode)
					{
					// Set a fake limit type if no truncate is needed
						$limittype = -1;
					}
					break;
				case 'except':
					if ($thereIsPluginCode)
					{
					// Set a fake limit type if no truncate is needed
						$limittype = -1;
					}
					break;
				case 'ignore':
				default :
					break;
			}

			// Limit by chars
			if ($limittype == 0)
			{
				if (JString::strlen(strip_tags($text)) > $maxLimit)
				{
					// 2018-04-06 15:41:43 Since preserveTags was added for stripping tags, this approach
					// would not wok. So it's blocked for now with `false &&`. Let it stay for a while
					
					if (false && $this->paramGet('Strip_Formatting') == 1 ) 
					{
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

						// $text = String::truncate($text, $maxLimit, '...', true);
						// Pop off the last word in case it got cut in the middle
						$text = preg_replace("/[.,!?:;]? [^ ]*$/", "", $text);

						// Add ... to the end of the article.
						$text = trim($text) . $this->trimming_dots;

						// Replace \n with <br />
						$text = str_replace("\n", "<br />", $text);
					}
					else
					{
						// Truncate
						// $text = JString::substr($text, 0, $maxLimit);
						if (!class_exists('AutoReadMoreString'))
						{
							require_once dirname(__FILE__) . '/helpers/AutoReadMoreString.php';
						}

						$text = AutoReadMoreString::truncate($text, $maxLimit, '&hellip;', true, $noSpaceLanguage);

						if (!$noSpaceLanguage)
						{
							// Pop off the last word in case it got cut in the middle
							$text = preg_replace("/[.,!?:;]? [^ ]*$/", "", $text);
						}

						// Pop off the last tag, if it got cut in the middle.
						$text = preg_replace('/<[^>]*$/', '', $text);

						$text = $this->addTrimmingDots($text);

						// Use Tidy to repair any bad XHTML (unclosed tags etc)
						$text = AutoReadMoreString::cleanUpHTML($text);
					}
					// Add a "read more" link, makes sense only for com_content
					$article->readmore = true;
				}
			}
			// Limit by words
			elseif ($limittype == 1)
			{
				if (!class_exists('AutoReadMoreString'))
				{
					require_once dirname(__FILE__) . '/helpers/AutoReadMoreString.php';
				}

				$original_length = JString::strlen($text);
				$text = AutoReadMoreString::truncateByWords($text, $maxLimit, $article->readmore);
				$newLength = JString::strlen($text);

				if ($newLength !== $original_length)
				{
					$article->readmore = true;
				}

				$text = $this->addTrimmingDots($text);
				$text = AutoReadMoreString::cleanUpHTML($text);
			}
			// Limit by paragraphs
			elseif ($limittype == 2)
			{
				$paragraphs = explode('</p>', $text);

				if (count($paragraphs) <= ($maxLimit + 1))
				{
					// Do nothing, as we have $maxLimit paragraphs
				}
				else
				{
					$text = array();

					for ($i = 0; $i < $maxLimit; $i++)
					{
						$text[] = $paragraphs[$i];
					}

					unset ($paragraphs);
					$text = implode('</p>', $text);
					$article->readmore = true;
				}
			}

			if ($this->paramGet('Strip_Formatting') == 1)
			{
				$tags = trim($this->paramGet('preserveTags', null));
				if ($this->paramGet('addNBSP')) {
					$text = preg_replace("/<\/P> /i", "</p>", $text);
					$text = preg_replace("/<\/P>\n/i", "</p>", $text);
					$text = preg_replace("/<\/P>/i", "</p>&nbsp;", $text);
				}
				$text = strip_tags($text, $tags);
			}

			// If we have thumbnails, add it to $text.
			if ($this->paramGet('Force_Image_Count'))
			{
				$text = preg_replace("/<img[^>]+\>/i", '', $text);
			}

			$text = $thumbnails . $text;

			if ($this->paramGet('wrap_output') == 1)
			{
				$template = $this->paramGet('wrap_output_template');
				$text = str_replace('%OUTPUT%', $text, $template);
			}

			if ($this->paramGet('readmore_text') && empty($this->alternative_readmore))
			{
				$article->alternative_readmore = JText::_($this->paramGet('readmore_text'));
			}

			$debug_addon = '';

			if ($debug)
			{
				$debug_addon = '<code>[DEBUG: AutoReadMore fired here]</code>';
			}

			$article->introtext = $text . $debug_addon;
			$article->text = $text . $debug_addon;

			if (isset($article->readmore) && !$article->readmore)
			{
				if (!$this->paramGet('Ignore_Existing_Read_More') && isset($original_readmore))
				{
					$article->readmore = $original_readmore;
				}
			}
		}

		/**
		 * Checkis if the content item has to be parsed by the plugin
		 *
		 * @param   string  $context  Context
		 * @param   object  $article  Content item object
		 *
		 * @return   bool  True if has to be parsed
		 */
		public function _checkIfAllowedCategoryAndItem ($context, $article)
		{
			if (!isset($article->catid) && !isset($article->id) )
			{
				return true;
			}

			$data = array();

			// Prepare data from joomla core articles or frontpage
			if (($this->paramGet('joomla_articles')		&& 	$context == 'com_content.category')
				||	($this->paramGet('Enabled_Front_Page')	&& 	$context == 'com_content.featured')	)
			{
				$prefix = '';

				if ($context == 'com_content.featured')
				{
					$prefix = 'fp_';
				}

				$row = array(
					'category_switch' => $this->paramGet($prefix . 'categories_switch'),
					'category_ids' => $this->paramGet($prefix . 'categories'),
					'item_switch' => $this->paramGet($prefix . 'articles_switch'),
					'item_ids' => $this->paramGet($prefix . 'id'),
				);
				$data[$context] = $row;
			}

			$context_switch = $this->paramGet('context_switch');

			if ($context_switch == 'include')
			{
				$paramsContexts = $this->paramGet('contextsToInclude');
				$contextsToInclude = json_decode($paramsContexts);

				// The default joomla installation procedure doesn't store defaut params into the DB in the correct way
				if (!empty($paramsContexts) && $contextsToInclude === null)
				{
					$paramsContexts = str_replace("'", '"', $paramsContexts);
					$contextsToInclude = json_decode($paramsContexts);
				}

				if (!empty($contextsToInclude) && !empty($contextsToInclude->context))
				{
					foreach ($contextsToInclude->context as $k => $v)
					{
						if ($v != $context)
						{
							continue;
						}

						$row = array(
							'category_switch' => $contextsToInclude->context_categories_switch[$k],
							'category_ids' => $contextsToInclude->categories_ids[$k],
							'item_switch' => $contextsToInclude->context_content_items_switch[$k],
							'item_ids' => $contextsToInclude->context_content_item_ids[$k],
						);
						$data[$context] = $row;
					}
				}
			}

			if (empty($data[$context]))
			{
				return true;
			}

			$item_switch = $data[$context]['item_switch'];
			$item_ids = $data[$context]['item_ids'];

			if (!is_array($item_ids))
			{
				$item_ids = array_map('trim', explode(',', $item_ids));
			}

			$category_switch = $data[$context]['category_switch'];
			$category_ids = $data[$context]['category_ids'];

			if (!is_array($category_ids))
			{
				$category_ids = array_map('trim', explode(',', $category_ids));
			}

			switch ($item_switch)
			{
				// Some articles are selected
				case '1':
					if (in_array($article->id, $item_ids))
					{
						return true;
					}
					break;

				// Some articles are excluded
				case '2':
					// If the article is among the excluded ones - return false
					if (in_array($article->id, $item_ids))
					{
						return false;
					}
					break;

				// No specific articles set
				case '0':
				default :
					break;
			}

			$in_array = in_array($article->catid, $category_ids);

			switch ($category_switch)
			{
				// ALL CATS
				case '0':
					return true;
					break;

				// Selected cats
				case '1':
					if ($in_array)
					{
						return true;
					}

					return false;
					break;

				// Excludes cats
				case '2':
					if ($in_array)
					{
						return false;
					}

					return true;
					break;
				default :
					break;
			}

			return true;
		}

		/**
		 * Check if current context is allowed either by settings or by some hardcoded rules
		 *
		 * @param   string  $context  Context passed to the onContentPrepare method
		 * @param   object  $article  Content item object
		 *
		 * @return  bool  true if allowed, false otherwise
		 */
		public function _checkIfAllowedContext ($context, $article)
		{
			$jinput = JFactory::getApplication()->input;
			$context_global = explode('.', $context);
			$context_global = $context_global[0];

			// Some hard-coded contexts to exclude
			$hard_coded_exclude_global_contexts = array(
				'com_virtuemart', // Never fire for VirtueMart
			);

			$contextsToExclude = array(
				'com_tz_portfolio.p_article', // Never run for full article
				'com_content.article', // Never run for full article

				// 'mod_custom.content', // never run at a custom HTML module - DISABLED here,
				// because the user must be allowed to choose this. At some circumstances joomla HTML modules may be needed to cut
			);

			if (in_array($context_global, $hard_coded_exclude_global_contexts) || in_array($context, $contextsToExclude)  )
			{
				return false;
			}

			// SOME SPECIAL RETURNS {
			// Fix easyblog
			if ($context == 'easyblog.blog' && $jinput->get('view', null, 'CMD') == 'entry')
			{
				return false;
			}

			$view = $jinput->get('view', null, 'CMD');
			$article_id = $jinput->get('id', null, 'INT');

			if (isset($article->id))
			{
				if ( ($view == "article" && $article->id == $article_id)
					|| ($context == 'com_k2.item' && $article->id == $article_id))
				{
					// If it's already a full article - go away'
					if (!isset($GLOBALS['joomlaAutoReadMorePluginArticleShown']) )
					{
						// But leave a flag not to go aways in a module
						$GLOBALS['joomlaAutoReadMorePluginArticleShown'] = $article_id;

						return false;
					}
				}
			}

			if ($this->paramGet('Enabled_Front_Page') == 0 and $context == 'com_content.featured')
			{
				return false;
			}
			elseif ($this->paramGet('Enabled_Front_Page') == 1 and $context == 'com_content.featured')
			{
				return true;
			}

			if ($this->paramGet('joomla_articles') == 0 and $context == 'com_content.category')
			{
				return false;
			}
			elseif ($context == 'com_content.categories')
			{
				if ($this->paramGet('joomla_articles_parse_category'))
				{
					return true;
				}
				else
				{
					return false;
				}
			}
			elseif ($this->paramGet('joomla_articles') == 1 and in_array($context, ['com_content.category', 'com_content.categories']) )
			{
				// If it's an article, as a category desc doesn't contain anything in it's object except ->text
				if (isset($article->id))
				{
					return true;
				}
				else
				{
					if ($this->paramGet('joomla_articles_parse_category'))
					{
						return true;
					}
					else
					{
						return false;
					}
				}
			}

			$context_switch = $this->paramGet('context_switch');

			switch ($context_switch)
			{
				case 'include':
					$paramsContexts = $this->paramGet('contextsToInclude');
					$contextsToInclude = json_decode($paramsContexts);

					// The default joomla installation procedure doesn't store defaut params into the DB in the correct way
					if (!empty($paramsContexts) && $contextsToInclude === null)
					{
						$paramsContexts = str_replace("'", '"', $paramsContexts);
						$contextsToInclude = json_decode($paramsContexts);
					}

					if (!empty($contextsToInclude) && !empty($contextsToInclude->context))
					{
						foreach ($contextsToInclude->context as $k => $v)
						{
							if ($context == $v)
							{
								return true;
							}
						}
					}

					return false;
				case 'exclude':
					// Not to work on modules, like mod_roksprocket.article
					if ($this->paramGet('exclude_mod_contexts') && strpos($context, 'mod_') === 0)
					{
						return false;
					}

					$contextsToExclude = $this->paramGet('contextsToExclude');
					$contextsToExclude = array_map('trim', explode(",", $contextsToExclude));

					if (in_array($context, $contextsToExclude))
					{
						return false;
					}
					break;
				case 'all_enabled':
					return true;
					break;
				case 'all_disabled':
					// This check just in case, should never come here in such a case
					if (!in_array($context, array('com_content.category', 'com_content.featured')))
					{
						return false;
					}
					break;
				default :
					break;
			}

			return true;
		}

		/**
		 * Add Trimming dots
		 *
		 * @param   string  $text  Text to add trimming symbol(s)
		 *
		 * @return  string  Text with added trimming symbol(s)
		 */
		public function addTrimmingDots($text)
		{
			// Add ... to the end of the article if the last character is a letter or a number.
			if ($this->paramGet('add_trimming_dots') == 2)
			{
				if (preg_match('/\w/ui', JString::substr($text, -1)))
				{
					$text = trim($text) . $this->trimming_dots;
				}
			}
			else
			{
				$text = trim($text) . $this->trimming_dots;
			}

			return $text;
		}

		/**
		 * Returns the full text of the article based on the article id
		 *
		 * @param   integer  $id  The id of the artice to load
		 *
		 * @return   string  The article fulltext
		 */
		public function loadFullText ($id)
		{
			$article = JTable::getInstance("content");
			$article->load($id);

			$article->fulltext_loaded = true;

			return $article->fulltext;
		}

		/**
		 * Returns text with handled images - added classes, stripped attributes, if needed
		 *
		 * @param   string  &$text        HTML code of the article
		 * @param   object  &$article     Article object for additional information like $article->id
		 * @param   text    $context      Context
		 * @param   bool    $ImageAsHTML  If to return html code or to update the $article object
		 *
		 * @return   text  HTML code to include containing images
		 */
		public function getThumbNails( & $text, & $article, $context, $ImageAsHTML = true)
		{
			// Are we working with any thumbnails?
			if ($this->paramGet('Thumbnails') < 1)
			{
				return;
			}

			$thumbnails = array();

			switch ($this->paramGet('image_search_pattern')) {
				case 'custom':
					$patterns = explode(PHP_EOL, $this->paramGet('image_search_pattern_custom'));
					$patterns = array_map('trim', $patterns);
					break;
				case 'a_wrapped':
					$patterns = ['~<a[^>]+><img [^>]+></a>~ui'];
					break;
				case 'img_only':
				default :
					$patterns = ['~<img [^>]*>~iu'];
					break;
			}

			$totalThumbNails = $this->paramGet('Thumbnails');

			// ~ $patterns = [
					// ~ '/<img [^>]*>/iu',
					// ~ '~<a[^>]+><img [^>]+></a>~ui',
			// ~ ];

			$total_matches = [];

			$fulltext = '';

			foreach ($patterns as $pattern)
			{
				// Extract all images from the article.
				$imagesfound  = preg_match_all($pattern, $text, $matches);

				// If we found less thumbnail then expected and the fulltext is not loaded,
				// then load fulltext and search in it also
				$matches_tmp = array ();

				$json = json_decode($article->images);

				if (!empty($json->image_intro))
				{
					$totalThumbNails--;
				}

				if ($totalThumbNails < 0)
				{
					$totalThumbNails = 0;
				}

				if ($imagesfound < $totalThumbNails && empty($fulltext))
				{

					if (isset($article->fulltext))
					{
						$fulltext = $article->fulltext;
					}
					elseif(isset($article->id) && !$this->fulltext_loaded && in_array($context, array('com_content.category','com_content.featured')))
					{
						$this->loadFullText($article->id);
					}

					$matches_tmp = $matches[0];
					$imagesfound  = preg_match_all($pattern, $fulltext, $matches);
				}

				$matches = array_merge($matches_tmp, $matches[0]);


				foreach ($matches as $km => $match)
				{
					$placeholder = '// ##mygruz20170704012529###' . $km . '###// ##mygruz20170704012529';
					$text = str_replace($match, $placeholder, $text);
					$fulltext = str_replace($match, $placeholder, $fulltext);

					if (!in_array($match, $total_matches))
					{
						$total_matches[$placeholder] = $match;
					}
				}
			}

			$matches = [];

			foreach ($total_matches as $placeholder => $match)
			{
				$text = str_replace($placeholder, $match, $text);

				$matches[] = $match;
			}

			// Loop through the thumbnails.
			for ($thumbnail = 0; $thumbnail < $totalThumbNails; $thumbnail++)
			{
				if (!isset($matches[$thumbnail]))
				{
					break;
				}

				// Remove the image from $text
				$text = str_replace($matches[$thumbnail], '', $text);

				// See if we need to remove styling.
				if (trim($this->paramGet('Thumbnails_Class')) != '')
				{
					// Remove style, class, width, border, and height attributes.
					if ($this->paramGet('Strip_Image_Formatting'))
					{
						// Add CSS class name.
						$matches[$thumbnail] = preg_replace('/(style|class|width|height|border) ?= ?[\'"][^\'"]*[\'"]/i', '', $matches[$thumbnail]);
						$matches[$thumbnail] = preg_replace('@/?>$@', 'class="' . $this->paramGet('Thumbnails_Class') . '" />', $matches[$thumbnail]);
					}
					else
					{
						$matches[$thumbnail] = preg_replace('@(class=["\'])@', '$1' . $this->paramGet('Thumbnails_Class') . ' ', $matches[$thumbnail], -1, $count);

						if ($count < 1)
						{
							$matches[$thumbnail] = preg_replace('@/?>$@', 'class="' . $this->paramGet('Thumbnails_Class') . '" />', $matches[$thumbnail]);
						}
					}
				}

				if (trim($matches[$thumbnail]) != '')
				{
					if ($ImageAsHTML)
					{
						$thumbnails[] = $matches[$thumbnail];
					}
					elseif ($thumbnail === 0 )
					{
						// Just flag for later see if there was at least one image
						$thumbnails[] = '';

						foreach (array('image_intro' => 'src', 'image_intro_alt' => 'alt',  'image_intro_caption' => 'title') as $k => $v)
						{
							$match = null;
							preg_match('@' . $v . '="([^"]+)"@', $matches[$thumbnail], $match);

							// ${$v} = array_pop($match);
							$json->{$k} = array_pop($match);
						}
					}
				}
			}

			if (empty($thumbnails) && trim($this->paramGet('default_image')) != '' )
			{
				$Thumbnails_Class = $this->paramGet('Thumbnails_Class');
				$Thumbnails_Class_Check = trim($Thumbnails_Class);

				if (!empty($Thumbnails_Class_Check))
				{
					$Thumbnails_Class = ' class="' . $Thumbnails_Class . '"';
				}
				else
				{
					$Thumbnails_Class = '';
				}

				if ($ImageAsHTML)
				{
					$thumbnails[] = '<img ' . $Thumbnails_Class . ' src="' . $this->paramGet('default_image') . '">';
				}
				elseif (empty($json->image_intro))
				{
					$json->image_intro = $this->paramGet('default_image');
				}
			}

			if (!$ImageAsHTML)
			{
				$article->images = json_encode($json);
			}

			// Make this thumbnail a link.
			// $matches[$thumbnail] = "<a href='" . $link . "'>{$matches[$thumbnail]}</a>";

			// Add to the list of thumbnails.
			if ($this->paramGet('image_link_to_article') && $ImageAsHTML)
			{
				$jinput = JFactory::getApplication()->input;

				while (true)
				{
					$option = $jinput->get('option', null, 'CMD');

					// Do not create link for K2, VM and so on
					if (in_array(
							$option,
							array (
								'com_k2',
								'com_virtuemart'
							)
						))
					{
						if (!empty($article->link))
						{
							$link = $article->link;
						}

						break;
					}

					if (!isset($article->catid))
					{
						break;
					}

					if (!isset($article->slug))
					{
						$article->slug = '';
					}

					if (isset($article->router) && isset($article->catid))
					{
						$link = JRoute::_(call_user_func($article->router, $article->slug, $article->catid));
						break;
					}

					if (isset($article->link))
					{
						$link = $article->link;
						$link = JRoute::_($link);
						break;
					}

					// Prepare the article link
					if ( $this->params_content->get('access-view') )
					{
						$link = JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catid));
					}
					else
					{
						$menu = JFactory::getApplication()->getMenu();
						$active = $menu->getActive();

						if (!isset($active->id))
						{
							$active = $menu->getDefault();
						}

						$itemId = $active->id;
						$link1 = JRoute::_('index.php?option=com_users&view=login&Itemid=' . $itemId);
						$returnURL = JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catid));
						$link = new JURI($link1);
						$link->setVar('return', base64_encode($returnURL));
					}

					break;
				}

				if (isset($link))
				{
					foreach ($thumbnails as $k => $v)
					{
						$thumbnails[$k] = '<a href="' . $link . '">' . $v . '</a>';
					}
				}
			}

			if ($this->paramGet('Force_Image_Count'))
			{
					foreach ($thumbnails as $k => $v)
					{
						if ($k > ($totalThumbNails - 1) )
						{
							unset($thumbnails[$k]);
						}
					}
			}

			if ($this->paramGet('wrap_image_output') == 1)
			{
				$template = $this->paramGet('wrap_image_output_template');
				foreach ($thumbnails as $kk => $vv)
				{
					$thumbnails[$kk] = str_replace('%OUTPUT%', $vv, $template);
				}
			}

			return implode(PHP_EOL, $thumbnails);
		}
	}

	/**
	 * Main plugin class. Used as a wrapper for possible paid extensions approach
	 *
	 * @since  0
	 */
	class PlgContentAutoReadMore extends PlgContentAutoReadMoreCore
				{
	}
}

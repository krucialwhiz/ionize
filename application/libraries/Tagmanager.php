<?php
/**
 * Ionize
 *
 * @package		Ionize
 * @author		Ionize Dev Team
 * @license		http://ionizecms.com/doc-license
 * @link		http://ionizecms.com
 * @since		Version 0.92
 *
 */

/**
 * Ionize Tagmanager Class
 *
 * Gives a controller Ionize basic FTL tags 
 *
 * @package		Ionize
 * @subpackage	Libraries
 * @category	TagManager Libraries
 *
 */
class TagManager
{
	protected static $tags = array();
	
	protected static $_inited = false;
	
	protected static $folders = array();
	
	protected $trigger_else = 0;

	public $ci;

	/*
	 * Extended fields prefix. Needs to be the same as the one defined in /models/base_model
	 *
	 */
	protected $extend_field_prefix = 	'ion_';
	

	/**
	 * The tags with their corresponding methods that this class provides (selector => methodname).
	 * 
	 * Add extra in subclasses to provide additional tags.
	 * 
	 * @var array
	 */
	protected $tag_definitions = array
	(
		'debug' =>				'tag_debug',
		'field' =>				'tag_field',
		'config' => 			'tag_config',
		'base_url' =>			'tag_base_url',
		'partial' => 			'tag_partial',
		'widget' =>				'tag_widget',
		'translation' => 		'tag_translation',
		'name' => 				'tag_name',
		'site_title' => 		'tag_site_title',
		'meta_keywords' => 		'tag_meta_keywords',
		'meta_description' => 	'tag_meta_description',
		'setting' => 			'tag_setting',
		'time' =>				'tag_time',
		'if' =>					'tag_if',
		'else' =>				'tag_else',
		'set' =>				'tag_set',
		'get' =>				'tag_get',
		'php' =>				'tag_php',
		'jslang' =>				'tag_jslang'
	);


	// ------------------------------------------------------------------------


	/**
	 * Initializes the FTL Manager.
	 * 
	 * @return void
	 */
	public static function init()
	{
		if(self::$_inited)
		{
			return;
		}
		
		// Inlude array of module definition. This file is generated by module installation in Ionize.
		// This file contains definition for installed modules only.
		include APPPATH.'config/modules.php';

		// Put modules arrays keys to lowercase
		self::$folders = array_combine(array_map('strtolower', array_values($modules)), array_values($modules));

		// Tags from /config/modules.php
		// Commented, because we try to get the tag names dynamilly from tag definition class
		// So tags don't need to be defined in the module config file.
		// if (isset($tags))
		// self::$tags = $tags;

		// Needed if you want to write something like this in you controller :
		// new PhotoGallery_Tags();
		// spl_autoload_register('TagManager::autoload');

		/*
		 * Loads automatiquely all installed modules tags
		 *
		 */
		foreach (self::$folders as $module)
		{
			self::autoload($module.'_Tags');
		}
	}


	// ------------------------------------------------------------------------


	/**
	 * Autoloads tag carrying classes from modules.
	 * 
	 * @param  string	<module_name>_<tag_definition_file_name>
	 * @return bool
	 */
	public static function autoload($class)
	{
		$class = strtolower($class);

		if(false !== $p = strpos($class, '_'))
		{
			// Module name
			$plugin = substr($class, 0, $p);
			
			// Class file name (usually 'tags')
			$file_name = substr($class, $p + 1);
		}
		else
		{
			return false;
		}
		
		
		/* If modules are installed : Get the modules tags definition
		 * Modules tags definition must be stored in : /modules/your_module/libraires/tags.php
		 * 
		 */
		if(isset(self::$folders[$plugin]))
		{
			// Only load the tags definition class if the file exists.
			if(file_exists(MODPATH.self::$folders[$plugin].'/libraries/'.$file_name.EXT))
			{
				require_once MODPATH.self::$folders[$plugin].'/libraries/'.$file_name.EXT;

				// Get tag definition class name
				$methods = get_class_methods($class);
				
				// Store tags definitions into self::$tags
				// add module enclosing tag
				self::$tags[$plugin] = $class.'::index';

				// Use of module name as namespace for the module to avoid modules tags collision
				foreach ($methods as $method)
				{
					self::$tags[$plugin.':'.$method] = $class.'::'.$method;
				}
				
				return true;
			}
			else
			{
				log_message('warning', 'Cannot find tag definitions for module "'.self::$folders[$plugin].'".');
			}
		}
	}

	// ------------------------------------------------------------------------


	/**
	 * Adds tags from plugins.
	 * 
	 * @param  FTL_Context
	 * @return void
	 */
	public static function add_plugin_tags(FTL_Context $con)
	{
		foreach(self::$tags as $selector => $callback)
		{
			$con->define_tag($selector, $callback);
		}
	}


	// ------------------------------------------------------------------------


	/**
	 * Adds the tags for the current class;
	 * 
	 * @param  FTL_Context
	 * @return void
	 */
	public final function add_tags(FTL_Context $con)
	{
		foreach($this->tag_definitions as $t => $m)
		{
			$con->define_tag($t, array($this, $m));
		}
	}

	
	// ------------------------------------------------------------------------


	/**
	 * Adds global tags to the context.
	 * 
	 * @param  FTL_Context
	 * @return void
	 */
	public function add_globals(FTL_Context $con)
	{
		// Add all basic settings to the globals
		/*
		$settings = Settings::get_settings();	

		foreach($settings as $k=>$v)
		{
			// Do not add the languages array
			if ( ! is_array($v))
				$con->globals->$k = $v;	
		}
		*/
		
		// Stores vars
		$con->globals->vars = array();
		
		// Global settings
		$con->globals->site_title = Settings::get('site_title');
		$con->globals->google_analytics = Settings::get('google_analytics');
		
		// Theme
		$con->globals->theme = Theme::get_theme();
		$con->globals->theme_url = base_url() . Theme::get_theme_path();
		
		// Current Lang code
		$con->globals->current_lang = Settings::get_lang();
		
		// Menus
		$con->globals->menus = Settings::get('menus');
	}


	// ------------------------------------------------------------------------


	/** 
	 * Get the current page data.
	 * 
	 * @param	FTL_Context		FTL_ArrayContext array object
	 * @param	string			Page name
	 * @return	array			Array of the page data. Can be empty.
	 */
	protected function get_current_page(FTL_Context $con, $page_name)
	{
		// Ignore the page named 'page' and get the home page
		if ($page_name == 'page')
		{
			return $this->get_home_page($con);
		}
		else
		{
			return $this->get_page($con, $page_name);
		}
	}


	// ------------------------------------------------------------------------


	/**
	 * Get the website Home page
	 * The Home page is the first page from the main menu (ID : 1)
	 * 
	 * @param	FTL_Context		FTL_ArrayContext array object
	 * @return	Array			Home page data array or an empty array if no home page is found
	 */
	protected function get_home_page(FTL_Context $con)
	{
		$return = array();
	
		if( ! empty($con->globals->pages))
		{
			$pages = array_values(array_filter($con->globals->pages, create_function('$row','return $row["id_menu"] == 1;')));
			
			foreach($pages as $page)
			{
				if ($page['home'] == 1)
				{
					return $page;
				}
			}
			if(isset($pages[0])) return $pages[0];
		}

		return $return;
	}


	// ------------------------------------------------------------------------


	/**
	 * Get one page regarding to its name
	 * 
	 * @param	string	Page name
	 * @return	array	Page data array
	 */
	protected function get_page(FTL_Context $con, $page_name)
	{
		$pages = array_values(array_filter($con->globals->pages, create_function('$row','return $row["url"] == "'. $page_name .'";')));
	
		if ( !empty($pages))
			return $pages[0];
		else
			return array();
	}


	// ------------------------------------------------------------------------


	/**
	 * Returns a dynamic attribute value
	 * Used with attributes which can get data from a database field.
	 *
	 * @param	FTL_Binding object		The current tag object
	 * @param	String					Attributes name
	 *
	 * @return	Mixed	The attribute value of false if nothing is found
	 *
	 */
	protected function get_attribute($tag, $attr)
	{
		// Try to get the couple array:field
		// "array" is the data array. For example "page" or "article"
		// $ar[0] : the data array name
		// $ar[1] : the field to get
		if ( ! empty($tag->attr[$attr]))
		{
			$ar = explode(':', $tag->attr[$attr]);
			
			// If no explode result, simply return the attribute value
			// In this case, the tag doesn't ask for a dynamic value, but just gives a value
			// (no ":" separator)
			if (!isset($ar[1]))
				return $tag->attr[$attr];
	
			// Here, there is a field to get
			if (isset($tag->locals->$ar[0]))
			{
				// Element can be page, article, etc.
				$element = $tag->locals->$ar[0];
			
				// First : try to get the field in the standard fields
				// exemple : $tag->locals->page[field]
				if ( ! isset($element[$ar[1]]))
				{
					// Second : Try to get the field in the extend fields
					// exemple : $tag->locals->page[ion_field]
					if ( ! isset($element[$this->extend_field_prefix.$ar[1]]))
					{
						return false;
					}
					else
					{
						// Try to get the value
						if ( ! empty($element[$this->extend_field_prefix.$ar[1]]))
						{
							return $element[$this->extend_field_prefix.$ar[1]];
						}
						return false;
					}
				}
				else
				{
					// Try to get the value.
					// Else return false
					if ( ! empty($element[$ar[1]]))
					{
						return $element[$ar[1]];
					}
					else
					{
						return false;
					}
				}
			}
		}
		// For safety, return false
		return false;
	}


	// ------------------------------------------------------------------------
	// Tags definition stars here
	// ------------------------------------------------------------------------


	/**
	 * Returns a trace of one $tag->object
	 * ONLY TO BE USED IN DEV !!!
	 *
	 */
	public function tag_debug($tag)
	{
		// local var name
		$name = (isset($tag->attr['name']) ) ? $tag->attr['name'] : false;

		$obj = isset($tag->locals->{$name}) ? $tag->locals->{$name} : null;

		if ( ! is_null($obj) && $name != false)
		{
			trace($tag->locals->{$name});
		}	
	
		return '';
	}


	// ------------------------------------------------------------------------


	/**
	 * NOT AN OFFICIAL TAG
	 * Needs to be improved
	 *
	 */
	public function tag_if($tag)
	{
		$condition = $tag->attr['condition'];
		
		// The object from which get the data to check
		$condition = explode(" ", $condition);
		
		@list($variable, $operator, $value) = $condition;
		
		if (isset($variable) && isset($operator) && isset($value))
		{
			$catched_variable = NULL;
			$catched_value = $value;
			
			// Var from an object ? Ex : article:index
			if(($pos = strpos($variable, ':')) != 0)
			{
				$item = NULL;
				$array = substr($variable, 0, $pos);
				$variable = substr($variable, $pos + 1);
				
				// Try to get the element to test from locals
				if ( ! empty($tag->locals->{$array}))
				{
					$item = $tag->locals->{$array};
				}
				// Try to get the item from the globals
				else if ( ! empty($tag->globals->{$array}))
				{
					$item = $tag->locals->{$array};
				}
				
				if ( ! is_null($item))
					$catched_variable = $item[$variable];
			}
			// Global var		
			else
			{
				if ( ! empty($tag->globals->{$variable}))
				{
					$catched_variable = $tag->globals->{$variable};
				}				
			}
			
			// Value from an object ?
			if(($pos = strpos($value, ':')) != 0)
			{
				$item = NULL;
				$array = substr($value, 0, $pos);
				$value = substr($value, $pos + 1);
				
				// Try to get the element to test from locals
				if ( ! empty($tag->locals->{$array}))
				{
					$item = $tag->locals->{$array};
				}
				// Try to get the item from the globals
				else if ( ! empty($tag->globals->{$array}))
				{
					$item = $tag->locals->{$array};
				}
				
				if ( ! is_null($item))
					$catched_value = $item[$value];
			}
			// Global var		
			else
			{
				if ( ! empty($tag->globals->{$value}))
				{
					$catched_value = $tag->globals->{$value};
				}				
			}
			
			// We have the internal value, comparison with asked one.
			if ( ! is_null($catched_variable) && ! is_null($catched_value))
			{
				$test = ( "return ($catched_variable $operator $catched_value);" );

				if (eval($test) == TRUE)
				{
					return $tag->expand();
				}
				else
				{
					$this->trigger_else++;
				}
			}
		}
		else
		{
			return 'Error in your conditional expression : ' . $tag->name . ':' .$tag->attr['condition'];
		}
	}


	// ------------------------------------------------------------------------


	/**
	 * NOT OFFICIAL TAG
	 * Needs to be improved
	 *
	 */
	public function tag_else($tag)
	{
		if($this->trigger_else > 0)
		{
			$this->trigger_else--;
	
			return $tag->expand();
		}
	} 

	
	// ------------------------------------------------------------------------


	/**
	 * NOT OFFICIAL TAG
	 * Needs to be improved
	 *
	 */
	public function tag_php($tag)
	{
		ob_start();
		eval($tag->expand());
		$c = ob_get_contents();
		ob_end_clean();
		return $c;	
	}

	
	// ------------------------------------------------------------------------


	/**
	 * Sets a global var
	 *
	 */
	public function tag_set($tag)
	{
		$var = ( !empty ($tag->attr['var'])) ? $tag->attr['var'] : null;
		$value = ( !empty ($tag->attr['value'])) ? $tag->attr['value'] : null;

		if ( ! is_null($var))
		{
			$tag->globals->{$var} = $value;
		}
		
		return $value;
	}

	
	// ------------------------------------------------------------------------
	
	
	/**
	 * Gets a global var
	 *
	 */
	public function tag_get($tag)
	{
		$var = ( !empty ($tag->attr['var'])) ? $tag->attr['var'] : null;

		if ( ! is_null($var) && !empty($tag->globals->{$var}))
		{
			return $tag->globals->{$var};
		}
		
		return '';
	}

	
	// ------------------------------------------------------------------------


	/**
	 * Returns the base URL of the website, with or without lang code in the URL
	 *
	 */
	public static function tag_base_url($tag) 
	{
		// don't display the lang URL (by default)
		$lang_url = false;

		// The lang code in the URL is forced by the tag
		$force_lang = (isset($tag->attr['force_lang'])) ? true : false;


		// Set all languages online if connected as editor or more
		if( Connect()->is('editors', true))
		{
			Settings::set_all_languages_online();
		}

		if (isset($tag->attr['lang']) && $tag->attr['lang'] == 'true' OR $force_lang === true)
		{
			if (count(Settings::get_online_languages()) > 1 )
			{
				// forces the lang code to be in the URL, for each language
				if ($force_lang === true)
				{
					return base_url() . Settings::get_lang() .'/';
				}
				// More intelligent : Detects if the current lang is the default one and don't return the lang code this lang code
				else
				{
					if (Settings::get_lang() != Settings::get_lang('default'))
					{
						return base_url() . Settings::get_lang() .'/';
					}
				}
			}
		}

		return base_url();
	}

	
	// ------------------------------------------------------------------------

	
	/**
	 * Get one field from a data array
	 * Used to get extended fields values
	 * First, this tag tries to get and extended field value.
	 * If nothing is found, he tries to get a core field value
	 * It is possible to force the core value by setting the "core" attribute to true
	 *
	 * @usage : <ion:field name="<field_name>" from="<table_name>" <core="true"> />
	 *
	 * @return String	The field value
	 *
	 */
	public function tag_field($tag)
	{
		// Object type : page, article, media
		$from = (isset($tag->attr['from']) ) ? $tag->attr['from'] : false;
		
		// Name of the field to get
		$name = (isset($tag->attr['name']) ) ? $tag->attr['name'] : false;
		
		// Format of the returned field (useful for dates)
		$format = (isset($tag->attr['format']) ) ? $tag->attr['format'] : false;
		
		// Force to get the field name from core. To be used when the field has the same name as one core field
		$force_core = (isset($tag->attr['core']) && $tag->attr['core'] == true ) ? true : false;

		$obj = isset($tag->locals->{$from}) ? $tag->locals->{$from} : null;

		if ( ! is_null($obj) && $name != false)
		{
			$value = '';
			
			// If force core field value, return it.
			if ($force_core === true && !empty($obj[$name]))
			{
				return self::enclose($tag, $obj[$name]);
			}
			
			// Try to get the extend field value			
			if ( ! empty($obj[$this->extend_field_prefix.$name]))
			{
				// If "format" attrbute is defined, suppose the field is a date ...
				if ($format && $obj[$this->extend_field_prefix.$name] != '')
				{
					return self::enclose($tag, (self::format_date($tag, $obj[$this->extend_field_prefix.$name])));
				}

				return self::enclose($tag, $obj[$this->extend_field_prefix.$name]);
			}
			// Else, get the core field value
			else if (!empty($obj[$name]))
			{
				return self::enclose($tag, $obj[$name]);
			}
		}
		
		// Return empty value to avoid errors
		return '';
	}
	
	
	// ------------------------------------------------------------------------


	/**
	 * Loads a partial view from a FTL tag
	 * Callback function linked to the tag <ion:partial />
	 * 
	 * @param	FTL_Binding		The binded tag to parse
	 *
	 */
	public function tag_partial($tag)
	{
		// Compatibility reason
		$view = ( ! empty($tag->attr['view'])) ?$tag->attr['view'] : NULL;
		
		if (is_null($view))
		{
			$view = ( ! empty($tag->attr['path'])) ?$tag->attr['path'] : NULL;
		}
		
		if ( ! is_null($view))
		{
			if(isset($tag->attr['php']) && $tag->attr['php'] == 'true')
			{
				return $this->ci->load->view($view, array(), true);
			}
			else
			{
				$file = Theme::load($view);
				return $tag->parse_as_nested($file);
			}
		}
		else
		{
			show_error('TagManager : Please use the attribute <b>"path"</b> when using the tag <b>partial</b>');
		}
	}


	// ------------------------------------------------------------------------


	/**
	 * Loads a widget
	 * Callback function linked to the tag <ion:widget />
	 * 
	 * @param	FTL_Binding		The binded tag to parse
	 *
	 */
	public function tag_widget($tag)
	{
		$name = $tag->attr['name'];
		
		return Widget::run($name, array_slice(array_values($tag->attr), 1)); 
	}


	// ------------------------------------------------------------------------


	/**
	 * Gets a tranlation value from a key
	 * Callback function linked to the tag <ion:translation />
	 * 
	 * @param	FTL_Binding		The binded tag to parse
	 *
	 */
	public function tag_translation($tag)
	{
		// Kind of article : Get only the article linked to the given view
		$term = (isset($tag->attr['item'] )) ? $tag->attr['item'] : FALSE ;
		
		if ($term === FALSE)
			$term = (isset($tag->attr['term'] )) ? $tag->attr['term'] : FALSE ;
		
		if ($term !== FALSE)
		{
			// Return the auto-linked translation value
			if (array_key_exists($term, $this->ci->lang->language) && $this->ci->lang->language[$term] != '') 
			{
				return auto_link($this->ci->lang->language[$term], 'both', true);
			}
			// Return the term index prefixed by "#" if no translation is found
			else
			{
				return '#'.$term;
			}
		}
		return;
	}
	
	/**
	 *
	 *
	 */
	public function tag_jslang($tag)
	{
		// Returned Object name
		$object = ( ! empty($tag->attr['object'] )) ? $tag->attr['object'] : 'Lang' ;

		// Files from where load the langs
		$files = ( ! empty($tag->attr['files'] )) ? explode(',', $tag->attr['files']) : array(Theme::get_theme());
		
		// JS framework
		$fm = ( ! empty($tag->attr['framework'] )) ? $tag->attr['framework'] : 'jQuery' ;
		
		// Returned language array
		$translations = array();
		
		// If $files doesn't contains the current theme lang name, add it !
		if ( ! in_array(Theme::get_theme(), $files) )
		{
			$files[] = Theme::get_theme();
		}
		
		if ((Settings::get_lang() != '') && !empty($files))
		{
			foreach ($files as $file)
			{
				$paths = array(
					APPPATH.'language/'.Settings::get_lang().'/'.$file.'_lang'.EXT,
					Theme::get_theme_path().'language/'.Settings::get_lang().'/'.$file.'_lang'.EXT
				);
				
				foreach ($paths as $path)
				{
					if (is_file($path) && '.'.end(explode('.', $path)) == EXT)
					{
						include $path;
						if ( ! empty($lang))
						{
							$translations = array_merge($translations, $lang);
							unset($lang);
						}
					}
				}
			}
		}
		$json = json_encode($translations);
		
		$js = "var $object = $json;";
		
		/*
		$.extend(Lang, {
			get: function(key) { return this[key]; },
			set: function(key, value) { this[key] = value;}
		});
		*/
		switch($fm)
		{
			case 'jQuery':
				$js .= "
					Lang.get = function (key) { return this[key]; };
					Lang.set = function(key, value) { this[key] = value;};
				";
				break;
			
			case 'mootools':
				$js .= "
					Lang.get = function (key) { return this[key]; };
					Lang.set = function(key, value) { this[key] = value;};
				";
				break;
		}
		
		return '<script type="text/javascript">'.$js.'</script>';
		
	}
	
	
	// ------------------------------------------------------------------------


	/**
	 * Gets a config value from the CI config file
	 * Callback function linked to the tag <ion:config />
	 * 
	 * @param	FTL_Binding		The binded tag to parse
	 *
	 * @usage	<ion:config item="<the_config_item>" />
	 *
	 */
	public function tag_config($tag)
	{
		// Config item asked
		$item = (isset($tag->attr['item'] )) ? $tag->attr['item'] : false ;
	
		if ($item !== false)
		{
			return config_item($item);
		}
		return;
	}
	

	// ------------------------------------------------------------------------


	/**
	 * Gets a setting value
	 * Callback function linked to the tag <ion:setting />
	 * 
	 * @param	FTL_Binding		The binded tag to parse
	 *
	 */
	public function tag_setting($tag)
	{
		// Setting item asked
		$item = (isset($tag->attr['item'] )) ? $tag->attr['item'] : false ;
	
		if ($item !== false)
		{
			return Settings::get($item);
		}
		return;
	}
	

	// ------------------------------------------------------------------------


	/**
	 * Shared tags callback functions
	 * 
	 * @return 
	 */
	public static function tag_name($tag) 
	{
		$use_global = isset($tag->attr['use_global']) ? true : false;
		
		if ($use_global == true)
		{
			return $tag->globals->page['name'];
		}
		else
		{
			return $tag->locals->page['name'];
		}
	}


	// ------------------------------------------------------------------------


	/**
	 * Returns the local meta keywords if found, otherwise the global ones.
	 * 
	 * @param  FTL_Binding
	 * @return string
	 */
	public function tag_site_title($tag)
	{
		return $this->enclose($tag, Settings::get('site_title'));
	}


	// ------------------------------------------------------------------------


	/**
	 * Returns the local meta keywords if found, otherwise the global ones.
	 * 
	 * @param  FTL_Binding
	 * @return string
	 */
	public function tag_meta_keywords($tag)
	{
		if( ! empty($tag->locals->page['meta_keywords']))
		{
			return $tag->locals->page['meta_keywords'];
		}
		return Settings::get('meta_keywords');
	}


	// ------------------------------------------------------------------------


	/**
	 * Returns the local meta keywords if found, otherwise the global ones.
	 * 
	 * @param  FTL_Binding
	 * @return string
	 */
	public function tag_meta_description($tag)
	{
		if( ! empty($tag->locals->page['meta_description']))
		{
			return $tag->locals->page['meta_description'];
		}
		return Settings::get('meta_description');
	}
	

	// ------------------------------------------------------------------------


	/**
	 * Returns the local meta keywords if found, otherwise the global ones.
	 * 
	 * @param  FTL_Binding
	 * @return string
	 */
	public function tag_time($tag)
	{
		return md5(time());
	}	


	// ------------------------------------------------------------------------


	/**
	 * Enclose a tag value depending on the enclosing HTML tag
	 *
	 * @example : <ion:page:title tag="<h1>" class="class" id="id" 
	 *
	 */
	protected function enclose($tag, $value)
	{
//		$html_tag = isset($tag->attr['tag']) ? $tag->attr['tag'] : false;
//		$class = isset($tag->attr['class']) ? ' class="' . $tag->attr['class'] . '"' : false;
//		$id = isset($tag->attr['id']) ? ' id="' . $tag->attr['id'] . '"' : false;

		$prefix = $suffix = '';
		
		$html_tag = self::get_attribute($tag, 'tag');
		$class = self::get_attribute($tag, 'class');
		$id = self::get_attribute($tag, 'id');
		
		if ( ! empty($class)) $class = ' class="'.$class.'"';
		if ( ! empty($id)) $id = ' id="'.$id.'"';
		
		// helper
		$helper = (isset($tag->attr['helper']) ) ? $tag->attr['helper'] : FALSE;
		
		if ($helper !== FALSE)
		{
			$helper = explode(':', $helper);
			
			$helper_name = ( ! empty($helper[0])) ? $helper[0] : FALSE;
			$helper_func = ( ! empty($helper[1])) ? $helper[1] : FALSE;
			
			if($helper_name !== FALSE && $helper_func !== FALSE)
			{
				$CI =& get_instance();
				
				$CI->load->helper($helper_name);
				
				if (function_exists($helper_func))
					$value = call_user_func($helper_func, $value);
				else
					return self::show_tag_error($tag->name, 'Error when calling <b>'.$helper_name.'->'.$helper_func.'</b>. This helper function doesn\'t exist');
			}
		}
		
		
		
		// Process the value through the passed in function name.
		if ( ! empty($tag->attr['function'])) $value = self::php_process($value, $tag->attr['function'] );
		
		if ($html_tag !== false)
		{
			$prefix = '<' . $html_tag . $id . $class . '>';
			$suffix = '</' . $html_tag .'>';
		}
		
		if ( ! empty ($value) )
			return $prefix . $value . $suffix;
		else
			return '';
	}
	

	// ------------------------------------------------------------------------


	/**
	 * Format the given date and return the expanded tag
	 *
	 */
	protected static function format_date($tag, $date)
	{
		$date = strtotime($date);
		
		if ($date)
		{
			$format = isset($tag->attr['format']) ? $tag->attr['format'] : 'Y-m-d H:i:s';		
			
			// Get date in the wished format
			$date = (String) date($format, $date);

			/*
			 * Get translation, if mandatory
			 * Date translations are located in the files : /themes/your_theme/language/xx/date_lang.php
			 *
			 */
			if (preg_match('/D|l|F|M/', $format) && strlen($format) == 1)
				$date = lang(strtolower($date));

			return $date;
		}
		return $tag->expand();
	}


	// ------------------------------------------------------------------------


	/**
	 * Process the input through the called functions and return the result
	 * 
	 * @param	Mixed				The value to process
	 * @param	String / Array		String or array of PHP functions
	 *
	 * @return	Mixed				The processed result
	 */
	protected static function php_process($value, $functions)
	{
		if ( ! is_array($functions))
		{
			$functions = explode(',', $functions);
		}
		
		foreach($functions as $func)
		{
			if (function_exists($func))
			{
				$value = $func($value);
			}
		}
		
		return $value;
	}

	
	// ------------------------------------------------------------------------


	/**
	 * Displays an error concerning one tag use
	 * 
	 * @param	String		Tag name
	 * @param	String		Message
	 * @param	String		Error template
	 *
	 * @return	String		Error message
	 *
	 */
	protected static function show_tag_error($tag_name, $message, $template = 'error_tag')
	{
		
		$message = '<p>'.implode('</p><p>', ( ! is_array($message)) ? array($message) : $message).'</p>';


		ob_start();
		include(APPPATH.'errors/'.$template.EXT);
		$buffer = ob_get_contents();

		ob_end_clean();
		return $buffer;
	}


}


TagManager::init();


/* End of file Tagmanager.php */
/* Location: /application/libraries/Tagmanager.php */
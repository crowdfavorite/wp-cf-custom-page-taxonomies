<?php
/*
Plugin Name: CF Custom Page Taxonomies 
Plugin URI: http://crowdfavorite.com 
Description: Allows custom taxonomies for pages to display ui widgets on the edit-page page 
Version: .25 
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/

// ini_set('display_errors', '1'); ini_set('error_reporting', E_ALL);

if (!defined('PLUGINDIR')) {
	define('PLUGINDIR','wp-content/plugins');
}


if (is_file(trailingslashit(ABSPATH.PLUGINDIR).basename(__FILE__))) {
	define('CFCPT_FILE', trailingslashit(ABSPATH.PLUGINDIR).basename(__FILE__));
}
else if (is_file(trailingslashit(ABSPATH.PLUGINDIR).dirname(__FILE__).'/'.basename(__FILE__))) {
	define('CFCPT_FILE', trailingslashit(ABSPATH.PLUGINDIR).dirname(__FILE__).'/'.basename(__FILE__));
}

$cfcpt;

/**
 * Initializes the plugin by instantiating an instance of the
 * CFCustomPagesTaxonomies class (if one does not exist yet)
 * 
 * @return object an instance of the CFCustomPagesTaxonomies class
**/
function cfcpt_init() {
	global $cfcpt;

    if (!is_object($cfcpt)) {
        // does not currently exist, so create it
        $cfcpt = new CFCustomPagesTaxonomies();

    } // if
    return $cfcpt;
}
add_action('init', 'cfcpt_init');

function cfcpt_request_handler() {
	global $cfcpt;
	if (!empty($_GET['cf_action'])) {
		switch ($_GET['cf_action']) {
			case 'cfcpt_admin_js':
				cfcpt_admin_js();
				break;
		}
	}
}
add_action('init', 'cfcpt_request_handler');

wp_enqueue_script('jquery');

function cfcpt_admin_js() {
	global $cfcpt;
	$cfcpt->add_page_tax_menu();
}
if (is_admin()) {
	wp_enqueue_script('cfcpt_admin_js', trailingslashit(get_bloginfo('url')).'?cf_action=cfcpt_admin_js', array('jquery'));
	wp_deregister_script('page');
	wp_enqueue_script('post');
}

function cfcpt_edit_post($post_id) {
	global $cfcpt;
	$cfcpt->register_tax_metaboxes();
}
add_action('admin_head','cfcpt_edit_post');

function cfcpt_create_meta_box($post,$box) {
	global $cfcpt;
	$cfcpt->create_tax_metabox($post,$box);
}

// Classes

/**
* Class that creates custom taxonomies and adds all needed infrastructure to
* mange them on pages (wp handles posts for you)
* 
* Future
* @todo add new hierarchical capability
*/
class CFCustomPagesTaxonomies {
	
	var $config = '';
	
	/**
	 * Constructor for the CFCustomPagesTaxonomies class.
	**/
	function CFCustomPagesTaxonomies() {
		$this->config  = apply_filters('cf_add_taxonomy_form', array());
		if (count($this->config) > 0) {
			$this->create_taxonomies();
		}
		else {
			error_log('[CFCPT] Configuration array missing!');
		}
	}
	
	/**
	 * loops through all taxonomies defined in $this->config, checks if the
	 * tax exists, creating one if needed
	**/
	function create_taxonomies() {
		foreach ($this->config as $tax => $tax_info) {
			if (!is_taxonomy($tax_info['tax_name'])) {
				register_taxonomy($tax_info['tax_name'], $tax_info['tax_scope'], array('hierarchical'=>FALSE, 'label'=>$tax_info['tax_label'], 'query_var'=>$tax_info['tax_name'], 'rewrite'=>FALSE));
			}
			if (!empty($tax_info['tax_meta_key'])) {
				$convert_tax = new CFPostMetaToTax($tax_info['tax_meta_key'],$tax_info['tax_name']);
				$convert_tax->assign_terms_to_posts($tax_info['tax_remove_meta']);
			}
		}
	}
	
	/**
	 * calls add_meta_box for each non-hierarchical page taxonomy 
	**/
	function register_tax_metaboxes() {
		foreach ( get_object_taxonomies('page') as $tax_name ) {
			if ( !is_taxonomy_hierarchical($tax_name) ) {
				$taxonomy = get_taxonomy($tax_name);
				$label = isset($taxonomy->label) ? esc_attr($taxonomy->label) : $tax_name;
							
				add_meta_box('tagsdiv-' . $tax_name, $label, 'cfcpt_create_meta_box', 'page', 'side','low');
			}
		}
	}
	
	/**
	 * Decides which meta box should be created (tag style | cat style),
	 * initializes variables need for either type.
	 * 
	 * @param object $post - passed from add_meta_box
	 * @param array $box - passed from add_meta_box
	**/
	function create_tax_metabox($post, $box) {
			$tax_name = esc_attr(substr($box['id'], 8));
			$taxonomy = get_taxonomy($tax_name);
			$helps = isset($taxonomy->helps) ? esc_attr($taxonomy->helps) : __('Separate tags with commas.');
			switch ($this->config[$tax_name]['tax_style']) {
				default:
				case 'tag':
					$this->tag_style_tax_box($tax_name,$taxonomy,$helps,$post,$box);
					break;
				case 'category':
				case 'cat':
					$this->cat_style_tax_box($tax_name,$taxonomy,$helps,$post,$box);
					break;
			}
		
	}
	
	/**
	 * Creates a tag style meta box for the taxonomy
	 * 
	 * @param string $tax_name name of the taxonomy
	 * @param array $taxonomy array containing information for taxonomy (id, slug, etc)
	 * @param string $helps a string containing a help message to be displayed
	 * @param object $post
	 * @param array $box information passed from add_meta_box
	**/
	function tag_style_tax_box($tax_name, $taxonomy, $helps, $post, $box) {
		?>
		<div class="tagsdiv" id="<?php echo $tax_name; ?>">
			<div class="jaxtag">
			<div class="nojs-tags hide-if-js">
			<p><?php _e('Add or remove tags'); ?></p>
			<textarea name="<?php echo "tax_input[$tax_name]"; ?>" class="the-tags" id="tax-input[<?php echo $tax_name; ?>]"><?php echo esc_attr(get_terms_to_edit( $post->ID, $tax_name )); ?></textarea></div>

			<span class="ajaxtag hide-if-no-js">
				<label class="screen-reader-text" for="new-tag-<?php echo $tax_name; ?>"><?php echo $box['title']; ?></label>
				<input type="text" id="new-tag-<?php echo $tax_name; ?>" name="newtag[<?php echo $tax_name; ?>]" class="newtag form-input-tip" size="16" autocomplete="off" value="<?php esc_attr_e('Add new tag'); ?>" />
				<input type="button" class="button tagadd" value="<?php esc_attr_e('Add'); ?>" tabindex="3" />
			</span></div>
			<p class="howto"><?php echo $helps; ?></p>
			<div class="tagchecklist"></div>
		</div>
		<p class="tagcloud-link hide-if-no-js"><a href="#titlediv" class="tagcloud-link" id="link-<?php echo $tax_name; ?>"><?php printf( __('Choose from the most used tags in %s'), $box['title'] ); ?></a></p>
		<?php
	}
	
	/**
	 * Creates a Category style meta box for the taxonomy. Based on post_tags_meta_box().
	 * 
	 * @todo currently adding/removing terms to a post is buggy, figure out why...
	 * 
	 * @param string $tax_name name of the taxonomy
	 * @param array $taxonomy array containing information for taxonomy (id, slug, etc)
	 * @param string $helps a string containing a help message to be displayed
	 * @param object $post
	 * @param array $box information passed from add_meta_box
	**/
	function cat_style_tax_box($tax_name,$taxonomy,$helps,$post,$box) {
		$terms = get_terms($tax_name, array('orderby'=>'count', 'order'=>'DESC', 'hide_empty'=>FALSE));
		foreach ($terms as $term) {
			$checked = '';
			if (is_object_in_term($post->ID, $tax_name, $term->name) === TRUE) {
				$checked=' checked="checked"';
			}
			echo '<p><input type="checkbox" name="tax_input['.$tax_name.']" value="'.$term->name.'" id="'.$term->name.'"'.$checked.'><label for="'.$term->name.'">'.$term->name.'</label></p>';
		}
		echo '<p><a id="add_tax_term" href="'.site_url('wp-admin/edit-tags.php?taxonomy='.$tax_name).'">Add New '.$this->config[$tax_name]['tax_label'].'</a></p>';
	}
	
	/**
	 * Creates the JS to add a link to the taxonomy admin page on in the menu-pages div
	 * 
	 * @todo make this work with wp_enqueue_script
	*/
	function add_page_tax_menu() {
		global $wp_version;
		header('Content-type: text/javascript');?>
	jQuery(function($) {
			<?php foreach ($this->config as $tax => $tax_info): ?>
				<?php if ($tax_info['tax_scope'] == 'page'):?>
		$("#menu-pages .wp-submenu ul").append("<li><a tabindex=\"1\" href=\"edit-tags.php?taxonomy=<?php echo $tax_info['tax_name']; ?>\"><?php echo $tax_info['tax_label']; ?></a></li>");
				<?php endif; ?>
			<?php endforeach; ?>
	});
		<?php
		die();
	}
}

/**
* This class converts post meta to a custom taxonomy.
* Looks up all posts/pages with a particular post meta key and adds the value as a term in a given taxonomy.
*/
class CFPostMetaToTax
{
	var $meta_key;
	var $tax_name;
	var $post_queue;
	
	/**
     * Constructor
     * @param string $post_meta_name the name of the post meta key to look for
     * @param string $taxonomy_name name of the taxonomy to which the post meta value should be added as a term
	**/
	function CFPostMetaToTax($post_meta_name, $taxonomy_name = null) {
		global $wpdb;
		if (empty($taxonomy_name)) {
			$meta_name_array = explode('_',$post_meta_name);
			$taxonomy_name = $meta_name_array[0];
		}
		$this->meta_key = $post_meta_name;
		$this->tax_name = $taxonomy_name;
		$this->_tax_exists();
		$query = '
			SELECT '.$wpdb->postmeta.'.post_id, '.$wpdb->postmeta.'.meta_value
			FROM '.$wpdb->postmeta.'
			WHERE '.$wpdb->postmeta.'.meta_key = "'.$this->meta_key.'";
		';
		$this->post_queue = $wpdb->get_results($query);
		// $post_query_array = array(
		// 	'meta_key' => $this->meta_key,
		// );
		// $this->post_queue = new WP_Query($post_query_array);
	}
	
	/**
	 * Checks if the taxonomy exists.  If not, it register's it.
	*/
	function _tax_exists() {
		if (!is_taxonomy($this->tax_name)) {
			register_taxonomy($this->tax_name, array('post','page'), array('hierarchical'=>FALSE, 'label'=> $this->tax_name, 'query_var'=>TRUE, 'rewrite'=>TRUE));
		}
	}
	
	/**
	 * associates terms stored inf post meta with the posts in the post_queue
	 * and the tax name.  If the term does not exist yet, it creates it.
	 * 
	 * @param bool $remove_meta if true removes the post meta containing the
	 * term defaults to FALSE
	**/
	function assign_terms_to_posts($remove_meta = FALSE) {
		foreach ($this->post_queue as $post => $details) {
			$term_slug = strtolower(str_ireplace(' ', '-', $details->meta_value));
			if (!is_term($details->meta_value, $this->tax_name)) {
				wp_insert_term($details->meta_value, $this->tax_name, array('slug'=>$term_slug));
			}
			wp_set_object_terms($details->post_id, $term_slug, $this->tax_name, TRUE);
		}
		if ($remove_meta) {
			delete_post_meta_by_key($this->meta_key);
		}
	}	
}

//a:22:{s:11:"plugin_name";s:27:"CF Custom Plugin Taxonomies";s:10:"plugin_uri";s:24:"http://crowdfavorite.com";s:18:"plugin_description";s:78:"Allows custom taxonomies for pages to display ui widgets on the edit-page page";s:14:"plugin_version";s:3:".25";s:6:"prefix";s:5:"cfcpt";s:12:"localization";N;s:14:"settings_title";s:21:"Mange Page Taxonomies";s:13:"settings_link";s:15:"Page Taxonomies";s:4:"init";s:1:"1";s:7:"install";s:1:"1";s:9:"post_edit";s:1:"1";s:12:"comment_edit";b:0;s:6:"jquery";s:1:"1";s:6:"wp_css";b:0;s:5:"wp_js";b:0;s:9:"admin_css";s:1:"1";s:8:"admin_js";s:1:"1";s:15:"request_handler";s:1:"1";s:6:"snoopy";b:0;s:11:"setting_cat";s:1:"1";s:14:"setting_author";b:0;s:11:"custom_urls";s:1:"1";}

?>
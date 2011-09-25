<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Lists;

use Pf4wp\WordpressPlugin;

/**
 * This class extends WP_List_Table to provide Gallery listings
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Lists
 */
class Galleries extends \WP_List_Table
{
    /** The owner of this class */
    protected $owner;
    
    /** Items per page to display */
    protected $per_page;
    
    private $mode = 'list';
    
    /**
     * Constructor [Override]
     *
     * @param WordpressPlugin $owner The owner of this list
     */
    public function __construct(WordpressPlugin $owner, $per_page = 20)
    {
        $this->owner    = $owner;
        $this->per_page = $per_page;

        parent::__construct(
            array(
                'plural' => 'galleries',
                'singular' => 'gallery',
                'ajax' => false,
            )
        );
    }
    
    /**
     * Displays the list [Override]
     *
     * This redirects the output and returns it instead
     * @return string The list to display
     */
    function display()
    {
        ob_start();
        parent::display();
        return ob_get_clean();
    }
    
    /**
     * Checks if the user has the required permission(s)  [Override]
     *
     * @return bool Returns `true` if the user has the required permission(s), `false` otherwise
     */
    function ajax_user_can()
    {
        return current_user_can('edit_theme_options');
    }
    
    /**
     * Prepares a list of items for displaying [Override]
     *
     * It uses set_pagination_args(), providing the total amount of items,
     * total amount of pages and items per page. 
     * 
     * It also fills the variable $items exposed by \WP_List_Table with the 
     * actual items (in the same order as the columns).
     */
    function prepare_items()
    {
        global $wpdb;
        
        $db_galleries = $wpdb->prefix . \Myatu\WordPress\BackgroundManager\Main::DB_GALLERIES;
        $db_photos    = $wpdb->prefix . \Myatu\WordPress\BackgroundManager\Main::DB_PHOTOS;
        
        // Grab the request data, if any
        $orderby    = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'id';
        $order      = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc';
        $this->mode = (!empty($_REQUEST['mode'])) ? $_REQUEST['mode'] : 'list';
        
        // Ensure we have valid request values
        $orderby = in_array($orderby, array_keys($this->get_sortable_columns())) ? $orderby : 'id';
        $order   = ($order == 'asc') ? 'ASC' : 'DESC';
                
        // Starting point
        $start = ($this->get_pagenum()-1) * $this->per_page;
        
        $this->items = $wpdb->get_results("SELECT *, 
            (SELECT COUNT(*) FROM `{$db_photos}` 
                WHERE `{$db_photos}`.`bgm_gallery_id` = `{$db_galleries}`.`id`
            ) AS `photos`
            FROM `{$db_galleries}`
            ORDER BY `{$orderby}` {$order}
            LIMIT {$start},{$this->per_page}"
        );
        
        $this->set_pagination_args(
            array(
                'total_items' => count($this->items),
                'per_page'    => $this->per_page,
            )
		);
    }
    
    /**
     * Provides the columns [Override]
     *
     * The array contains internal name to title relations. A special
     * column with the name `cb` contains a checkbox `<input type="checkbox" />`
     * as the title.
     *
     * @return array Array containing internal name => title relations
     */
    function get_columns()
    {
        return array(
            'cb'          => '<input type="checkbox" />',
            'name'        => __('Name', $this->owner->getName()),
            'description' => __('Description', $this->owner->getName()),
            'photos'      => __('Photos', $this->owner->getName()),
        );
    }
    
	/**
	 * Get a list of sortable columns [Override]
     *
     * The format is:
	 * `'internal-name' => array( 'orderby', true )`
	 *
	 * If the second parameter is `true`, then it will be the initial sorting order
	 *
	 * @return array Array containing sortable columns
	 */
	function get_sortable_columns()
    {
		return array(
            'name'        => array('name', true),
            'description' => array('description', false),
            'photos'      => array('photos', false),
        );
	}
    
    /**
     * Returns to WP_List_Table what columns are available [Override]
     *
     * @return array Array containing all columns, hidden columns and which ones are sortable
     */
    function get_column_info()
    {
        return array(
            $this->get_columns(),
            get_user_option('manage' . get_current_screen()->id . 'columnshidden'),
            $this->get_sortable_columns()
        );
    }
    
	/**
	 * Get an associative array (option_name => option_title) with the list
	 * of bulk actions available on this table. [Override]
     *
	 * @return array
	 */
	function get_bulk_actions() {
		return array(
            'trash_all' => __('Move to Trash', $this->owner->getName())
        );
	}
    
    /**
     * Add view switcher to pagination top row [Override]
     *
     * Switches between `list` or `excerpt`
     */
    function pagination($which) {
		parent::pagination($which);

		if ($which == 'top')
			$this->view_switcher($this->mode);
	}
    
    /**
     * Displays a checkbox column
     */
    function column_cb($item)
    {
        echo '<input type="checkbox" name="delete_item[]" value="' . $item['id'] . '" />';
	}
    
    /**
     * Displays a simple column item
     */
    function column_default($item, $column_name)
    {
        echo $item[$column_name];
    }
    
    /**
     * Displays the name and actions
     */
    function column_name($item)
    {
        echo $item['name'];
        
        $actions = array('Test' => '#');
        
        $this->row_actions($actions);
    }
    
}
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
    
    private $mode  = 'list';
    private $trash = false;
    
    /**
     * Constructor [Override]
     *
     * @param WordpressPlugin $owner The owner of this list
     */
    public function __construct(WordpressPlugin $owner, $trash = false, $per_page = 20)
    {
        $this->owner = $owner;
        
        $this->setPerPage($per_page);
        $this->setTrash($trash);

        parent::__construct(
            array(
                'plural' => 'galleries',
                'singular' => 'gallery',
                'ajax' => false,
            )
        );
    }
    
    /**
     * Sets the items to display per page
     */
    public function setPerPage($per_page)
    {
        $this->per_page = $per_page;
    }
    
    /**
     * Sets whether we should display trash, instead of active items
     */
    public function setTrash($trash)
    {
        $this->trash = $trash;
    }
    
    /**
     * Returns whether this is a Trash listing
     *
     * @return bool Returns `true` if this is a Trash listing, `false` otherwise
     */
    public function isTrash()
    {
        return $this->trash;
    }
    
    /**
     * Renders the list
     *
     * This redirects the display() output and returns it instead
     * 
     * @return string The list to display
     */
    function render()
    {
        ob_start();
        $this->display();
        return ob_get_clean();
    }
    
    /**
     * Prepares a list of items for displaying [Override]
     *
     * It uses set_pagination_args(), providing the total amount of items,
     * total amount of pages and items per page. 
     * 
     * It also fills the variable $items exposed by \WP_List_Table with the 
     * actual items.
     */
    function prepare_items()
    {
        global $wpdb;
        
        $db_galleries = $wpdb->prefix . \Myatu\WordPress\BackgroundManager\Main::DB_GALLERIES;
        $db_photos    = $wpdb->prefix . \Myatu\WordPress\BackgroundManager\Main::DB_PHOTOS;
        
        // Grab the request data, if any
        $orderby    = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'id';
        $order      = (!empty($_REQUEST['order']))   ? $_REQUEST['order']   : 'asc';
        $this->mode = (!empty($_REQUEST['mode']))    ? $_REQUEST['mode']    : 'list';
        
        // Ensure we have valid request values
        $orderby = in_array($orderby, array_keys($this->get_sortable_columns())) ? $orderby : 'id';
        $order   = ($order == 'asc') ? 'ASC' : 'DESC';
        
        // Figure out how many items and pages we have
        $total_items = $this->owner->getGalleryCount(!$this->trash);
        if (($total_pages = ceil($total_items / $this->per_page)) < 1)
            $total_pages = 1;
            
        // Get a sensible page number from the user selection
        $paged = $this->get_pagenum();
        if ($paged > $total_pages) {
            $page_num = $total_pages;
        } else if ($paged < 1) {
            $page_num = 1;
        } else {
            $page_num = $paged;
        }
        
        // Select a starting point for the DB
        $start = ($page_num-1) * $this->per_page;
        
        // Whether we are displaying active or trashed galleries
        $trash = ($this->trash) ? 'TRUE' : 'FALSE';
        
        // Query the DB ...
        $this->items = $wpdb->get_results("SELECT `id`, `name`, `description`, 
            (SELECT COUNT(*) FROM `{$db_photos}` 
                WHERE `{$db_photos}`.`bgm_gallery_id` = `{$db_galleries}`.`id`
            ) AS `photos`
            FROM `{$db_galleries}`
            WHERE `trash` = {$trash}
            ORDER BY `{$orderby}` {$order}
            LIMIT {$start},{$this->per_page}"
        );
        
        // ... and finally set the pagination args. 
        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'total_pages' => $total_pages,
                'per_page'    => $this->per_page,
            )
		);
    }

	/**
	 * Message to be displayed when there are no items [Override]
	 */
	function no_items()
    {
		echo __('No photo sets found.', $this->owner->getName());
	}    
    
    /**
     * Provides the columns [Override]
     *
     * The array contains internal name to title relations. A special
     * column with the name `cb` contains a checkbox `<input type="checkbox" />`
     * as the title.
     *
     * The prefixing is simply to give the `Trash` its own set of columns and
     * associated user settings, managed by both Pf4wp and WP_Table_List.
     *
     * @return array Array containing internal name => title relations
     */
    function get_columns()
    {
        $prefix = ($this->trash) ? 'trash_' : '';
            
        return array(
            'cb'                  => '<input type="checkbox" />',
            'name'                => __('Name', $this->owner->getName()),
            $prefix.'description' => __('Description', $this->owner->getName()),
            $prefix.'photos'      => __('Photos', $this->owner->getName()),
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
        $prefix = ($this->trash) ? 'trash_' : '';
        
		return array(
            'name'                => array('name', true),
            $prefix.'description' => array('description', false),
            $prefix.'photos'      => array('photos', false),
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
	function get_bulk_actions()
    {
        // Active bulk actions
        if (!$this->trash)
            return array('trash_all' => __('Move to Trash', $this->owner->getName()));
        
        // else Trash bulk actions
        return array(
            'delete_all'  => __('Delete Permanently', $this->owner->getName()),
            'restore_all' => __('Restore', $this->owner->getName()),
        );
	}
    
    /**
	 * Extra controls to be displayed between bulk actions and pagination [Override]
	 */
    function extra_tablenav($which)
    {
        if (!current_user_can('edit_theme_options'))
            return;

        if ($which == 'top') {
            if (!$this->trash) {
                // Active items
                printf(
                    '<div class="alignleft actions"><a href="%s" class="button-secondary action" style="display:inline-block">%s</a></div>',
                    esc_url(add_query_arg('edit', 'new')),
                    __('Add New', $this->owner->getName())
                );
            } else {
                // Trash items
                printf(
                    '<input type="submit" value="%s" class="button-secondary apply" id="%s" name="%2$s" />',
                    __('Empty Trash', $this->owner->getName()),
                    'delete_all'
                );
            }
        }
    }
    
    /**
     * Add view switcher to pagination top row [Override]
     *
     * Switches between `list` or `excerpt`
     */
    function pagination($which)
    {
		parent::pagination($which);

		if ($which == 'top' && !$this->trash)
			$this->view_switcher($this->mode);
	}
    
    /**
     * Displays a checkbox column
     */
    function column_cb($item)
    {
        echo '<input type="checkbox" name="ids[]" value="' . $item->id . '" />';
	}
    
    /**
     * Displays a column (default, if no specific `column_...` function is found).
     */
    function column_default($item, $column)
    {
        echo htmlspecialchars($item->$column);
    }
    
    /**
     * Displays the `Photos` column in the Trash
     */
    function column_trash_photos($item)
    {
        $this->column_default($item, 'photos');
    }

    /**
     * Displays the `Description` column in the Trash
     */
    function column_trash_description($item)
    {
        $this->column_default($item, 'description');
    }
    
    /**
     * Displays the name and actions
     */
    function column_name($item)
    {
        $a_link = '<a href="%s" title="%s">%s</a>';
        
        if (!$this->trash) {
            // The links (for active items)
            $edit_link  = esc_url(add_query_arg(array('edit' => $item->id)));
            $trash_link = esc_url(add_query_arg(array('trash' => 1, 'ids' => $item->id,  '_wpnonce' => wp_create_nonce('trash-gallery'))));
            
            // Print the title of the item
            printf($a_link, $edit_link, 
                htmlspecialchars(sprintf(__('Edit "%s"', $this->owner->getName()), $item->name)), 
                sprintf('<strong>%s</strong>', htmlspecialchars($item->name))
            );
            
            // Output the actions
            $actions = array(
                'edit'  => sprintf($a_link, $edit_link, __('Edit this photo set', $this->owner->getName()), __('Edit', $this->owner->getName())),
                'trash' => sprintf($a_link, $trash_link, __('Move this photo set to the Trash', $this->owner->getName()), __('Trash', $this->owner->getName())),
            );
        } else {
            // The links (for trashed items)
            $restore_link = esc_url(add_query_arg(array('restore' => 1, 'ids' => $item->id, '_wpnonce' => wp_create_nonce('restore-gallery'))));
            $delete_link  = esc_url(add_query_arg(array('delete' => 1, 'ids' => $item->id, '_wpnonce' => wp_create_nonce('delete-gallery'))));
            
            printf('<strong>%s</strong>', htmlspecialchars($item->name));
            
            $actions = array(
                'untrash' => sprintf($a_link, $restore_link, __('Restore this item from the Trash', $this->owner->getName()), __('Restore', $this->owner->getName())),
                'delete'  => sprintf($a_link, $delete_link, __('Delete this item permanently', $this->owner->getName()), __('Delete Permanently', $this->owner->getName())),
            );
        }
        
        echo $this->row_actions($actions);
    }
    
}
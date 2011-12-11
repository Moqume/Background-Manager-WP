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
 * This class extends WP_List_Table to provide Image Set listings (Galleries)
 *
 * It uses the following actions/filters:
 *
 * - `myatu_bgm_galleries_custom_column(object $item, string $column_name)`
 *   Action; Displays (echoes) the contents of a custom column (see bgm_galleries_columns)
 *
 * - `myatu_bgm_galleries_columns(array $columns, object $item, bool $is_trash)`
 *   Filters `columns`; `is_trash` if currently displaying the Trash.
 *
 * - `myatu_bgm_galleries_actions(array $actions, object $item, bool $is_trash)`
 *   Filters `actions`; `is_trash` if currently displaying the Trash.
 *
 * It also uses the standard WordPress `the_title` filter.
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
        // Grab the request data, if any
        $orderby    = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'date';
        $order      = (!empty($_REQUEST['order']))   ? $_REQUEST['order']   : 'desc';
        
        // Ensure we have valid request values
        $orderby = (in_array($orderby, array('date', 'title'))) ? $orderby : 'date';
        $order   = ($order == 'asc') ? 'ASC' : 'DESC';

        // Figure out how many items and pages we have
        $total_items = $this->owner->getGalleryCount(!$this->trash);
        if (($total_pages = ceil($total_items / $this->per_page)) < 1)
            $total_pages = 1;
            
        // Get a sensible page number from the user selection
        $page_num = $this->get_pagenum();
        if ($page_num > $total_pages) {
            $page_num = $total_pages;
        } else if ($page_num < 1) {
            $page_num = 1;
        }
        
        // Get the image sets
        $this->items = get_posts(array(
            'numberposts' => $this->per_page,
            'offset'      => ($page_num-1) * $this->per_page,
            'orderby'     => $orderby,
            'order'       => $order,
            'post_type'   => \Myatu\WordPress\BackgroundManager\Main::PT_GALLERY,
            'post_status' => ($this->trash) ? 'trash' : '',
        ));
        
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
		_e('No image sets found.', $this->owner->getName());
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
        if ($this->owner->inEdit())
            return array();
            
        $prefix = ($this->trash) ? 'trash_' : '';
            
        return array(
            'cb'                  => '<input type="checkbox" />',
            'title'               => __('Title', $this->owner->getName()),
            $prefix.'description' => __('Description', $this->owner->getName()),
            $prefix.'images'      => __('Images', $this->owner->getName()),
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
     * @see \WP_Query\get_posts()
	 * @return array Array containing sortable columns
	 */
	function get_sortable_columns()
    {
		return array('title' => array('title', true));
	}
    
    /**
     * Returns to WP_List_Table what columns are available [Override]
     *
     * @return array Array containing all columns, hidden columns and which ones are sortable
     */
    function get_column_info()
    {
        $columns = apply_filters('myatu_bgm_galleries_columns', $this->get_columns(), $this->trash);
        
        return array(
            $columns,
            (array)get_user_option('manage' . get_current_screen()->id . 'columnshidden'),
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
        $result = array();

        if (!$this->trash && EMPTY_TRASH_DAYS) {
            $result['trash_all'] = __('Move to Trash', $this->owner->getName());
        } else {
            if (EMPTY_TRASH_DAYS)
                $result['restore_all'] = __('Restore', $this->owner->getName());
                
            $result['delete_all'] = __('Delete Permanently', $this->owner->getName());
        }
        
        return $result;
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
                    '<div class="alignleft actions"><input type="submit" value="%s" class="button-secondary apply" id="%s" name="%2$s" /></div>',
                    __('Empty Trash', $this->owner->getName()),
                    'empty_trash'
                );
            }
        }
    }
    
    /** Default column display function */
    function column_default($item, $column_name)
    {
        do_action('myatu_bgm_galleries_custom_column', $item, $column_name);
    }
    
    /** Displays a checkbox column */
    function column_cb($item)
    {
        echo '<input type="checkbox" name="ids[]" value="' . $item->ID . '" />';
	}
    
    /** Displays the description of the item */
    function column_description($item)
    {
        echo (empty($item->post_content)) ? '&nbsp;' : htmlspecialchars($item->post_content);
    }
    
    /** 
     * Displays the image count of the item
     */
    function column_images($item)
    {
        echo $this->owner->images->getCount($item->ID);
    }
    
    /** Displays the `Images` column in the Trash */
    function column_trash_images($item)
    {
        $this->column_images($item);
    }

    /** Displays the `Description` column in the Trash */
    function column_trash_description($item)
    {
        $this->column_description($item);
    }
    
    /**
     * Creates an action link
     *
     * @param string $action Action to create the link for
     * @param int $id The item ID
     * @param bool|string $text Optional text to display (automatically determined if set to `false`)
     * @param bool|string $text Optional title (hint) to display (automatically determined if set to `false`)
     * @param string $class Optional class to add to the action link
     * @return string
     */
    public function actionLink($action, $id, $text = false, $title = false, $class = '')
    {
        $link = '<a href="%s" title="%s" class="%s">%s</a>';

        switch ($action) {
            case 'edit':
                $title = (!$title) ? __('Edit this Image Set', $this->owner->getName()) : $title;
                $text  = (!$text) ? __('Edit', $this->owner->getName()) : $text;
                return sprintf($link, esc_url(add_query_arg(array('edit'=>$id, 'action'=>false, 'ids'=>false, '_wpnonce'=>false, 'order'=>false, 'orderby'=>false))), $title, $class, $text);
                
            case 'trash':
                $nonce =  wp_create_nonce(\Myatu\WordPress\BackgroundManager\Main::NONCE_TRASH_GALLERY . $id);
                $title = (!$title) ? __('Move this Image Set to the Trash', $this->owner->getName()) : $title;
                $text  = (!$text) ? __('Trash', $this->owner->getName()) : $text;
                break;
                
            case 'delete':
                $nonce = wp_create_nonce(\Myatu\WordPress\BackgroundManager\Main::NONCE_DELETE_GALLERY . $id);
                $title = (!$title) ? __('Delete this Image Set permanently', $this->owner->getName()) : $title;
                $text  = (!$text) ? __('Delete Permanently', $this->owner->getName()) : $text;
                break;
            
            case 'restore':
                $nonce = wp_create_nonce(\Myatu\WordPress\BackgroundManager\Main::NONCE_RESTORE_GALLERY . $id);
                $title = (!$title) ? __('Restore this Image Set from the Trash', $this->owner->getName()) : $title;
                $text  = (!$text) ? __('Restore', $this->owner->getName()) : $text;
                break;
                
            default:
                return '';
        }
                
        return sprintf($link, esc_url(add_query_arg(array('action' => $action, 'ids' => $id, '_wpnonce' => $nonce, 'edit'=>false))), $title, $class, $text);
    }   
    
    /** Displays the name and actions */
    function column_title($item)
    {
        if (!$this->trash) {
            // Print the name of the gallery
            echo $this->actionLink('edit', $item->ID, sprintf('<strong>%s</strong>', htmlspecialchars($item->post_title)), sprintf(__('Edit &#8220;%s&#8221;', $this->owner->getName()), get_the_title($item->ID)));
            
            // Print active state
            if ($this->owner->options->active_gallery == $item->ID)
                printf(' - <strong>%s</strong>', __('Active Background', $this->owner->getName()));
            
            // Set the actions (start off with an `edit` link)
            $actions = array($this->actionLink('edit', $item->ID));
            
            if (EMPTY_TRASH_DAYS) {
                $actions['trash'] = $this->actionLink('trash', $item->ID);
            } else {
                $actions['delete'] = $this->actionLink('delete', $item->ID);
            }
        } else {
            // Print the name of the gallery
            printf('<strong>%s</strong>', htmlspecialchars($item->post_title));
            
            // And set the actions
            $actions = array(
                'untrash' => $this->actionLink('restore', $item->ID),
                'delete'  => $this->actionLink('delete', $item->ID),
            );
        }
        
        $actions = apply_filters('myatu_bgm_galleries_actions', $actions, $item, $this->trash);
        
        echo $this->row_actions($actions);
    }
    
}
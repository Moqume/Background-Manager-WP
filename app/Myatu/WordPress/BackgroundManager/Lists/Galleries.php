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
    
    /**
     * Constructor [Override]
     *
     * @param WordpressPlugin $owner The owner of this list
     */
    public function __construct(WordpressPlugin $owner, $per_page)
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
        $orderby = (isset($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : '';
        $order = (isset($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc';
                
        $results = array();
        $results[] = array('id'=>1, 'name'=>'Test1', 'desc'=>'Blah blah');
        $results[] = array('id'=>2, 'name'=>'Test2', 'desc'=>'Meh meh');
        $results[] = array('id'=>3, 'name'=>'Test3', 'desc'=>'Doh doh');
        $total = count($results);
        
        if (!empty($results) && !empty($orderby)) {
            $current = current($results);
            if (array_key_exists($orderby, $current)) {
                foreach($results as $key => $row)
                    $sorter[$key] = $row[$orderby];
        
                $dir = ($order == 'desc') ? SORT_DESC : SORT_ASC;
                array_multisort($sorter, $dir, $results);
            }
        }
        
        $results = array_chunk($results, $this->per_page);
        
        $this->items = $results[$this->get_pagenum()-1];
        
        $this->set_pagination_args(
            array(
                'total_items' => $total,
                'per_page' => $this->per_page,
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
            'cb' => '<input type="checkbox" />',
            'name' => 'Name',
            'desc' => 'Description',
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
            'name' => array('name', true),
            'desc' => array('desc', false),
        );
	}
    
    function get_column_info()
    {
        return array(
            $this->get_columns(), 
            array(), 
            $this->get_sortable_columns()
        );
    }
    
    function column_cb($item)
    {
        echo '<input type="checkbox" name="delete_item[]" value="' . $item['id'] . '" />';
	}
    
    function column_default($item, $column_name)
    {
        echo $item[$column_name];
    }
    
    

}
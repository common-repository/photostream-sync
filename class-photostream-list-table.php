<?php
/**
 * Note: WP_List_Table might be depreciated in the future.
 */
if( !class_exists( 'WP_List_Table' ) ){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Photostream_List_Table extends WP_List_Table {
    
    /**
     * Constuctor function.
     * Defines 
     */
    function __construct() {
        global $status, $page;
                
        // Set parent defaults
        parent::__construct( array(
            'singular'  => 'photostream',     	// Singular name of the listed records
            'plural'    => 'photostreams',    	// Plural name of the listed records
            'ajax'      => false        			
        ) );   
    }
    
    /**
     * Display for the checkbox column.
     * 
     * @param  array $item 
     * @return string      
     */
    function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ 'photostream',      
            /*$2%s*/ $item['key'] //The value of the checkbox should be the record's id
        );
    }

    /**
     * Content for the photostream column.
     * 
     * @param  array $item 
     * @return string html that will populate the column.
     */
    function column_photostream( $item ) {
        
        global $__photostream;

        $delete_nonce = wp_create_nonce( 'photostream-delete-'.$item['key'] );
        $current_url = admin_url( 'upload.php?page='.$_GET['page'] );

        $edit_photostream_link   = esc_url( add_query_arg( array( 'view' => 'edit', 'key' => $item['key'] ), $current_url  ) );
        $import_photostream_link = esc_url( add_query_arg( array( 'view' => 'import', 'key' => $item['key'] ), $current_url  ) );
        $delete_photostream_link = esc_url( add_query_arg( array( 'view' => 'delete','key' => $item['key'], 'ps-nonce' => $delete_nonce ), $current_url  ) );
        
        $actions = array(
            'edit'      => sprintf( '<a href="%s" class="edit" >%s</a>',      $edit_photostream_link,   esc_html__( 'Edit', 'photostream' ) ),
            'import'    => sprintf( '<a href="%s" class="import" >%s</a>',  $import_photostream_link,   esc_html__( 'Import', 'photostream' ) ),
            'delete'    => sprintf( '<a href="%s" class="delete" >%s</a>',  $delete_photostream_link,   esc_html__( 'Delete', 'photostream' ) )

        );

        $sync_icon = ( $item['sync'] ? '<div class="dashicons dashicons-backup" title="' . esc_attr__( 'Syncing Enabled', 'photostream' ) . '"></div> ' : '' );
        $actions['info'] = $__photostream->get_processed_media_info( $item['key'] );
        
        //Return the title contents
        return $sync_icon. sprintf( '%1$s  %2$s ',
            /*$1%s*/ ucfirst( $item['title'] )  ,
            /*$2%s*/ $this->row_actions( $actions )
        );
        
    }

    /**
     * Content for the link column.
     * 
     * @param  array $item 
     * @return string html that will populate the column.
     */
    function column_link( $item ) {

        return '<a target="_blank" href="'.esc_url( $item['url'] ).'">'.esc_html( $item['url'] ).'</a>';
    }

    /**
     * Photostream Author 
     *     
     * @param  array $item 
     * @return string
     */
    function column_as_author( $item ) {
        $user = get_userdata( $item['as_author'] );

        return $user->display_name;
    }
    
    
    /**
     * Returns array of columns to diplay in the title.
     * 
     * @return array columns to display.
     */
    function get_columns() {
        $columns = array(

           //  'cb'                    => '<input type="checkbox" />', //Render a checkbox instead of text
            'photostream'           => esc_html__( 'Photostream', 'photostream' ),
            'link'   	            => esc_html__( 'Link', 'photostream' ),
            'as_author'             => esc_html__( 'Post as', 'photostream' ),
          
            
      
        );
        return $columns;
    }

    /**
     * Prepares the items for display
     * 
     * @return null
     */
    function prepare_items() {

        global $__photostream;
        /**
         * Items per page
         */
        $per_page = 10;
        
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = array( $columns, $hidden, $sortable );
        
        $current_page = $this->get_pagenum();
        $this->items = $__photostream->get_streams_data( $current_page, $per_page );
        $total_items = count( $__photostream->get_streams() );  //$drafts_for_friends->get_shared_count();
        
    
        $this->set_pagination_args( array(
            'total_items' => $total_items,                      // calculate the total number of items
            'per_page'    => $per_page,                         //Determine how many items to show on a page
            'total_pages' => ceil( $total_items / $per_page )   // Calculate the total number of pages
        ) );
    }
    
}

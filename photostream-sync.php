<?php

/*
Plugin Name: Photostream-sync
Description: Synchronize your public iCloud photostreams to your WordPress installation. Import images, Import videos, create gallery posts, and more.
Author: Demitrious Kelly <apokalyptik@apokalyptik.com>
Version: 2.1.2
Author URI: http://blog.apokalyptik.com/
*/
define('PHOTOSTREAM_SYNC_DEV', true);

require dirname( __FILE__ ) . '/class-photostream-client.php';
require dirname( __FILE__ ) . '/class-photostream-list-table.php';

class Photostream {

	var $file_version  		= '2.1.1';
	var $streams 			= null;
	var $streaminfo 		= array();
	var $processing_errors 	= array();
	var $error_message 		= null;
	var $add_data 			= array();
	var $dev 				= false;
	var $key                = false;
	var $view 				= false;
	protected $admin_page 		= null;

	function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu_page' ) );
		$this->processing_errors = array(
			'new' => array( 'validation' => array() ),
		);
		add_action( 'wp_ajax_photostream_import_media', array( $this, 'ajax_import_media') );
		$this->admin_page = admin_url( "upload.php?page=photostream" );

		$plugin_basename = plugin_basename( __FILE__ );
		
		add_filter( 'plugin_action_links_'.$plugin_basename, array( $this, 'add_action_links' ) );
	}

	function get_admin_page(){
		return $this->admin_page;	
	}
	
	/**
	 * Add the menu item. 
	 */
	function add_admin_menu_page() {
		load_plugin_textdomain( 'photostream' );
		$page = add_media_page(
			esc_html( __( 'Photostreams', 'photostream' ) ),
			esc_html( __( 'Photostreams', 'photostream' ) ),
			'import', // @todo: change permission so that people that can uplaod media can add a photo stream
			'photostream',
			array( $this, 'display_admin_page' )
		);
		
		add_action( "load-$page", array( $this, 'admin_enqueue' ) );
	}

	function add_action_links( $links ) {
		$links['settings'] = '<a href="'.esc_url( $this->admin_page ).'">'. __( 'Settings', 'photostream' ) . '</a>';
		return $links;
	}
	
	/**
	 * Process the post data & laod the scripts and styles.
	 * 	
	 * @return null
	 */
	function admin_enqueue() {
		// process the form
		// add new, delete, update
		$this->process_admin_page_post();

		wp_enqueue_script( 'photostream', plugin_dir_url( __FILE__ ) . 'static/photostream.js',	 array( 'jquery', 'heartbeat' ), $this->file_version, true );
		wp_enqueue_style(  'photostream', plugin_dir_url( __FILE__ ) . 'static/photostream.css', array(),           $this->file_version       );
		
		wp_register_script( 'photostream-add',    plugin_dir_url( __FILE__ ) . 'static/photostream-add.js',	   array( 'jquery', 'post' ), $this->file_version, true );
		wp_register_script( 'photostream-import', plugin_dir_url( __FILE__ ) . 'static/photostream-import.js', array( 'jquery', ), $this->file_version, true );
	}
	
	/**
	 * Helper function for displaying the possible text variable. 
	 * 
	 * @return null 
	 */
	function display_text_transform_options() {
		?><div class="transform_options">
		<a href="#" onClick="jQuery(this).parent().find('div:first').toggle('fast'); return false;"><?php esc_html_e( 'Show Options', 'photostream'); ?></a>
		<div>
		stream-&gt;title, stream-&gt;key<br/>
		owner-&gt;firstName, owner-&gt;lastName, owner-&gt;fullName<br/>
		image-&gt;caption, image-&gt;guid, image-&gt;date(<a href="http://us2.php.net/manual/en/function.date.php" target="_blank"><?php  esc_html_e( 'format',  'photostream' ); ?></a>)<br/>
		batch-&gt;guid, batch-&gt;date(<a href="http://us2.php.net/manual/en/function.date.php" target="_blank"><?php  esc_html_e( 'format',  'photostream' ); ?></a>)<br/>
		fetch-&gt;date(<a href="http://us2.php.net/manual/en/function.date.php" target="_blank"><?php  esc_html_e( 'format',  'photostream' ); ?></a>)
		</div>
		</div>
		<?php 
	}

	/**
	 * Display the admin page.
	 * 
	 * @return null
	 */
	function display_admin_page() {

		$streams = $this->get_streams();
		if ( empty( $streams ) )
			$streams = array();

		$import_action_set 	= $edit_action_set = false;

		$this->view = ( isset( $_GET['view'] ) && in_array( $_GET['view'], array( 'init', 'add', 'edit', 'import', 'delete', 'add_import' ) ) ? $_GET['view'] : 'init' );
		$this->key  = ( isset( $_GET['key'] ) && in_array( $_GET['key'] , $streams ) ? $_GET['key'] : false );
		$invalid_key = __( 'Sorry, the stream you are looking for doesn\'t exits', 'photostream' );
		?>
		<div class="wrap photostream-wrap">
			<h2>
				<?php esc_html_e( 'Photostreams', 'photostream' ); ?> 
				<?php if( in_array( $this->view,  array( 'edit', 'import', 'delete' )) ) { ?>
					<a class="return-to" href="<?php echo esc_url( $this->admin_page ); ?>"><?php esc_html_e( 'Back to Photostreams' , 'photostream'); ?></a>
				<?php } ?>
			</h2>
			<?php
			// show error message
			if( $this->error_message ) { 
				$this->display_error( $this->error_message ); 
			}

			switch( $this->view ){

				case 'init':
				case 'add':
				case 'add_import':
					$step = ( !empty( $this->add_data ) ? $_POST['step'] : 1 );
					$step = ( 'add_import' == $this->view ? 3 :$step );
					$this->admin_ui_add( $step );

					if( $step == 1 ) 
						$this->admin_ui_manage();

				
				break;

				case 'edit':
					if( !$this->key ) {
						$this->display_error( $invalid_key ) ;
						return;

						}
					$this->admin_ui_edit_stream();
				break;

				case 'delete':
					if( !$this->key ) {
						$this->display_error( $invalid_key ) ;
						return;
					}
					$this->admin_ui_delete_stream();
				break;

				case "import":
					if( !$this->key ) {
						$this->display_error( $invalid_key ) ;
						return;
					}
					$this->admin_ui_import_stream();
				break;
			}

			?>
			
		</div> <!-- end of wrap -->
		<?php
	}

	/**
	 * Display the table list view for the streams.
	 * 
	 * @return null
	 */
	function admin_ui_manage() {
		$streams = $this->get_streams();
		
		if( empty( $streams ) )
			return;
		?>
		<div class="ps-sync-manage" >
			<h3><?php esc_html_e( 'Manage Your Streams', 'photostream' ); ?></h3>	
			<?php
			// include the lis
			$list_table = new Photostream_List_Table();
	    	// Fetch, prepare, sort, and filter our data.
	    	$list_table->prepare_items();
			?>
			<form id="manage-photostream-filter" method="post" action="<?php echo admin_url('upload.php'); ?>?page=<?php echo esc_attr( $_REQUEST['page'] ); ?>">
			
			<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
			
			<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get the stream data for the list table. 
	 * 
	 * @return array of data
	 */
	function get_streams_data( $page = 1, $number_per_page = 10 ) {

		$streams = $this->get_streams();
		$streams_data = array();
		
		$start 		= ( $page * $number_per_page - $number_per_page );
		$end_count 	= $page * $number_per_page;
		
		$streams = array_values($streams);
		 
		for ($i = $start; $i < $end_count; $i++) {
			
			if( !isset( $streams[$i]) )
				break;
			
			$key = $streams[$i];
			$stream_info = $this->get_stream_info( $key );
			
			$streams_data[] = array(
				'key' 				=> $key,
				'import' 			=> $stream_info->enabled,
				'title' 			=> $stream_info->title,
				'url'				=> 'https://www.icloud.com/photostream/#'.esc_attr( $key ),
				'sync'				=> $stream_info->enabled,
				'as_author'			=> $stream_info->user,
				'last_completed' 	=> ( isset( $stream_info->last_completed ) ? $stream_info->last_completed : false )
 				);
		}

		return $streams_data;
	}

	/**
	 * Split the adding into 2 parts.
	 * 
	 * @param  int $step What step should we be at.
	 * @return null
	 */
	function admin_ui_add( $step ) {

			
		switch( $step ){
			
			case 1:
			default:

				$this->admin_ui_add_url(); // step 1 
			break;

			case 2:
				// Could be the same as edit... 
				$this->admin_ui_add_step( $step );
				$this->admin_ui_add_step2(); // we are looking for more info... 
			break;
			case 3:
				$this->admin_ui_add_step( $step );
				$this->admin_ui_import_stream();
			break;
		}
	}

	function admin_ui_add_step( $step_count ) {
		$steps = array( __( 'Add URL', 'photostream' ) , __( 'Configure Photostream', 'photostream' ) , __( 'Import Media', 'photostream' ) );
		?><ul class="steps-shell"> <?php
		for($i = 0; isset( $steps[$i] ) ;$i++ ) {
			$class = 'step';
			if( $i + 1 == $step_count ){
				$class = 'step active-step';

			}
			?>
			<li class="<?php echo  $class; ?>"><?php echo  esc_html__( 'Step', 'photostream' ) . ' ' . ( $i + 1 ); ?>: <span><?php echo esc_html( $steps[$i]); ?></span></li>
			<?php 
		} ?>
		</ul>
		<?php
	}

	/**
	 * Simple form for adding the photostream.
	 * 
	 * @return form html
	 */
	function admin_ui_add_url() { ?>
		<h3><?php esc_html_e( 'Add Photostream', 'photostream'); ?></h3>
		<div class="add-photostream-url">
			<form method="post">
				<?php wp_nonce_field( 'ps-sync-add-1', 'ps-nonce' ); ?>
				<input type="hidden" name="section" value="add-url" />
				<strong><?php esc_html_e('Photostream URL', 'photostream'); ?></strong><br />

					<input id="photostream-url" class="regular-text code" type="text" value="" placeholder="https://www.icloud.com/photostream/" name="photostream-url"> 
					<input id="submit-add-photosteam-url" class="button button-primary submit-form" type="submit" value="<?php esc_attr_e( 'Next', 'photostream' ); ?>" name="submit" /><span class="spinner"></span>
					<p id="help-message"><em><?php esc_html_e( 'Enter the URL of your public iCloud photostream', 'photostream' ); echo '<br />'; _e( 'It should look something like this' , 'photostream'); ?> "https://www.icloud.com/photostream/#nnnnnnnnnnnnn".</em></p>
					<input name="step" type="hidden" value="2" />
			</form>
		</div>
		<div class="info-box">
			<strong><?php _e('Steps to create a public photostreams URL on the iPhone', 'photostream'); ?></strong>
			<ol>
			    <li><?php _e('Open the photos app', 'photostream');?></li>
			    <li><?php _e('Tap "Photo Stream" (bottom)', 'photostream');?></li>
			    <li><?php _e('Tap + (top)', 'photostream');?></li>
			    <li><?php _e('Give it a name, make sure "Public Website" is "On"', 'photostream');?></li>
			    <li><?php _e('Tap Create (top)', 'photostream');?></li>
			    <li><?php _e('Back at the list get the details for this new Stream (tap the blue right arrow)', 'photostream');?></li>
			    <li><?php _e('Down at the bottom is the link to the photostream.', 'photostream');?></li>
		    </ol>
		</div>
		<?php
	}

	/**
	 * Form for configuring advanced options when creating a new stream.
	 * 
	 * @return form html
	 */
	function admin_ui_add_step2() { ?>
		<div class="stream-info">
			<h3><?php esc_html_e('Adding photostream', 'photostream'); ?>: <em><?php echo esc_html( $this->add_data['title'] ); ?></em></h3>
			<p class="info"><strong><?php echo esc_html( $this->add_data['title'] ); ?></strong> <?php esc_html_e( 'photostream contains', 'photostream');?> <?php echo $this->add_data['stream_media_info']; ?>. <a target="_blank" href="<?php echo esc_url($this->add_data['url']); ?>" title="<?php esc_attr_e( 'Go to photo stream' , 'photostream' ); ?>  <?php echo esc_attr( $this->add_data['title'] ); ?>"><?php esc_html_e( 'Visit photostream', 'photostream' ); ?></a> #<?php echo esc_html( $this->add_data['key'] ); ?></p>
		</div>
		<?php
		$this->admin_ui_advance_form( 'add', $this->add_data['key'] );
	}

	/**
	 * Edit Form
	 * @return 
	 */
	function admin_ui_edit_stream() { 
		
		$stream = $this->get_stream_info( $this->key );
		$url = 'https://www.icloud.com/photostream/#'.esc_attr( $this->key );
		
		?>
		<div class="stream-info">
			<h3><?php esc_html_e( 'Editing photostream', 'photostream' ); ?>: <em><?php echo esc_html( $stream->title ); ?></em></h3>
			<p class="info"><strong><?php echo esc_html(  $stream->title ); ?></strong> <?php esc_html_e( 'photostream added' ); ?> <?php echo $this->get_processed_media_info( $this->key ); ?>. <a target="_blank" href="<?php echo esc_url( $url ); ?>" title="<?php _e( 'Go to photo stream', 'photostream' ); ?>. <?php echo esc_attr( $stream->title ); ?>"><?php esc_html_e( 'Visit photostream', 'photostream' ); ?></a> #<?php echo esc_html( $this->key ); ?></p>
		</div>
		<?php 

		$this->admin_ui_advance_form( 'edit', $this->key );
	}

	/**
	 * Get information on how many galleries, photos and videos a particular stream 
	 * already imported into the site.
	 * 
	 * @param  string $key 
	 * @return srting html
	 */
	function get_processed_media_info( $key ) {
		
		$processed_galleries 	= $this->count_processed_galleries( $key );
		$processed_photos 		= $this->count_processed_photos( $key );
		$processed_videos 		= $this->count_processed_videos( $key );
		
		return $this->get_media_info( $processed_galleries, $processed_photos, $processed_videos );

	}

	/**
	 * Return html containing how many galleries, photos and video a steam contains in a human readable fashion
	 * 
	 * @param  array $data           
	 * @param  int $number_of_groups 
	 * @return string html                   
	 */
	function get_stream_media_info( $data, $number_of_galleries ) {
		
		$number_of_videos = $number_of_photos = 0;
		foreach( $data->photos as $media ) {
			if ( isset( $media->mediaAssetType ) && $media->mediaAssetType == 'video' ) {
				$number_of_videos++;
			} else {
				$number_of_photos++;
			}
		}
		
		return $this->get_media_info( $number_of_galleries, $number_of_photos, $number_of_videos );
	}

	/**
	 * Return a html string containing a human readable description of the number of galleries, phtotos and videos
	 * @param  int $number_of_galleries
	 * @param  int $number_of_photos
	 * @param  int $number_of_videos
	 * @return string html
	 */
	function get_media_info( $number_of_galleries, $number_of_photos, $number_of_videos ) {

		$messages = array(
			'gallery' 	=> _n_noop('%s Gallery', '%s Galleries' ),
			'photo' 	=> _n_noop('%s Picture', '%s Pictures'),
			'video' 	=> _n_noop('%s Video', '%s Videos')
		);

		$display_galleries = $display_photos = $display_videos = '';
		$first_seperater = $second_seperater = ' '.__('and').' ';
		if( $number_of_galleries > 0 ) {
			$display_galleries = "<strong>" . esc_html( sprintf( translate_nooped_plural( $messages['gallery'], $number_of_galleries,  'photostream' ), $number_of_galleries ) ) ."</strong>";
		} else {
			$first_seperater   = ''; 
		}	
		
		if( $number_of_photos > 0 ) {
			$display_photos = "<strong>" . esc_html( sprintf( translate_nooped_plural( $messages['photo'], $number_of_photos, 'photostream' ), $number_of_photos ) ) ."</strong>";		
		}
		
		if( $number_of_videos  > 0 ) {
			$display_videos = "<strong>" . esc_html( sprintf( translate_nooped_plural( $messages['video'], $number_of_videos, 'photostream'  ), $number_of_videos  ) ) ."</strong>";
			$second_seperater = ( !empty( $display_photos ) ? ", " : "" );
		} else {
			$second_seperater = '';
			if( empty( $display_photos ) )
				$first_seperater   = '';
		}
				
		return $display_galleries . $first_seperater . $display_photos . $second_seperater. $display_videos;
		
	}


	/**
	 * Combine the edit form with the edit form. 
	 * 
	 * @param  string $action either edit or add.
	 * @param  string $key    Unique stream key.
	 * @return Display html   
	 */
	function admin_ui_advance_form( $action, $key ) { 

		// needed for the advanced form.
		wp_enqueue_script( 'photostream-add' );
		wp_localize_script( 'photostream-add', 'photostream_add', array( 
				'show' => __( 'Show Advanced Options', 'photostream'), 
				'hide' => __( 'Hide Advanced Options' , 'photostream') 
				));
		switch( $action ) {

			case 'add':

				$nonce_action = 'ps-sync-add-2';
				$form_section = '[add]';
				
				$stream = (object) array(
					'enabled' 			=> true,
					'user'				=> get_current_user_id(),
					'password'			=> '',
					'post_title'		=> '',
					'use_caption'		=> '',
					'post_status'		=> 'draft',
					'tags'	 			=> array(),
					'cats'				=> array(),
					'post_type'			=> 'post',
					'gallery_shortcode' => '',
					'video_shortcode'	=> '',
					'rename_file'		=> '',
					);
				/*
				$stream->enabled = true;
				$stream->user = ;

				*/
			break;

			case 'edit':
				
				$nonce_action = 'ps-sync-edit-' . $key;
				$form_section = '[edit]['.esc_attr( $key ).']';
				$stream = $this->get_stream_info( $key );
				
			break;


		}
		?>

		<form method="post">
		<?php wp_nonce_field( $nonce_action , 'ps-nonce' ); ?>

		<input type="hidden" name="section" value="<?php echo esc_attr( $action ); ?>" />
		<input type="hidden" name="photostream<?php echo $form_section; ?>[stream]" value="<?php echo esc_attr( $key ); ?>"  />
		
		<table class="form-table">
			<tbody>
				<tr >
					<th><?php esc_html_e( 'Enable Syncing', 'photostream' ); ?></th>
					<td>
						<input type="hidden" 	name="photostream<?php echo $form_section; ?>[enabled]" value="0"/>
						<input type="checkbox" 	name="photostream<?php echo $form_section; ?>[enabled]" value="1" <?php checked( true, $stream->enabled ); ?> />
						<span class="help"><?php esc_html_e( 'Enable, or disable whether importing should happen for this stream.', 'photostream' ); ?></span>
					</td>

				</tr>
			</tbody>
		</table>
		<h3><?php _e( 'Gallery Settings', 'photostream' ); ?></h3>
		<table class="form-table">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Gallery Titles' ); ?></th>
					<td>
						<input type="text" name="photostream<?php echo $form_section; ?>[post_title]" value="<?php esc_attr_e( $stream->post_title ); ?>"
							placeholder="stream-&gt;title - batch-&gt;date(Y-m-d)" class="regular-text" />
						<p class="help"><?php esc_html_e( 'Configure the post title for gallery posts. Click "show options" for formatting help.', 'photostream' ); ?></p>
						<?php $this->display_text_transform_options(); ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Gallery Author', 'photostream' ); ?></th>
					<td>
						<?php wp_dropdown_users( array( 'name' => 'photostream' . $form_section . '[user]' , 'selected' => $stream->user) ); ?>
						<span class="help"><?php esc_html_e( 'Choose the WordPress user that this plugin is acting as when importing this photostream.', 'photostream' ); ?></span>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Default Password', 'photostream' ); ?></th>
					<td><input name="photostream<?php echo $form_section; ?>[password]" class="regular-text" type="text" value="<?php esc_attr_e( $stream->password ); ?>" />
					<p class="help"><?php esc_html_e( 'Password Protect all gallery posts made during import', 'photostream' ); ?></p>
					</td>
				</tr>
				
				<tr>
					<th><?php esc_html_e( 'Gallery Caption Use', 'photostream' ); ?></th>
					<td>
						<select name="photostream<?php echo $form_section; ?>[use_caption]">
							<option value="" ><?php esc_html_e( 'Do not use caption', 'photostream' ); ?></option>
							<option value="above" <?php selected( 'above', $stream->use_caption ); ?> ><?php esc_html_e( "Place the caption above the gallery", 'photostream' ); ?></option>
							<option value="below" <?php selected( 'below', $stream->use_caption ); ?> ><?php esc_html_e( "Place the caption below the gallery", 'photostream' ); ?></option>
						</select>
						<p class="help"><?php esc_html_e( 'Configure how a caption should be included in the gallery posts when one is available.', 'photostream' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Gallery Publishing', 'photostream' ); ?></th>
					<td>
						<select name="photostream<?php echo $form_section; ?>[post_status]">
							<option value="publish" <?php selected( 'publish', $stream->post_status ); ?> ><?php esc_html_e( 'Publish', 'photostream'); ?></option>
							<option value="draft"   <?php selected( 'draft',   $stream->post_status ); ?> ><?php esc_html_e( 'Draft', 'photostream'); ?></option>
						</select>
						<span class="help"><?php esc_html_e( 'Choose whether gallery posts should be published automatically or kept as drafts.', 'photostream' ); ?></span>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Gallery Tags', 'photostream' ); ?></th>
					<td>
						<p class="help"><?php esc_html_e( 'Choose one or more tags for gallery posts.', 'photostream' ); ?></p>
						<div class="tagsdiv" id="post_tag">
							<div id="#tagsdiv-post_tag">
								<div class="jaxtag">
									<div class="nojs-tags hide-if-js">
									<p><?php esc_html_e('Add or remove tags', 'photostream'); ?></p>
									<textarea id="tax-input-post_tag" class="the-tags" cols="20" rows="3" name="photostream<?php echo $form_section; ?>[tags]"><?php esc_attr_e( implode( ', ', $stream->tags ) ); ?></textarea></div>
								 		<div class="ajaxtag hide-if-no-js">
										<label for="new-tag-post_tag" class="screen-reader-text"><?php esc_html_e('Tags', 'photostream'); ?></label>
										<div class="taghint" style=""><?php esc_html_e('Add New Tag', 'photostream');?></div>
										<p><input type="text" placeholder="tag1, tag2, tag3" value="" autocomplete="off" size="16" class="newtag form-input-tip" name="newtag[post_tag]" id="new-tag-post_tag">
										<input type="button" value="Add" class="button tagadd"></p>
									</div>
									<p class="howto"><?php esc_html_e( 'Separate tags with commas', 'photostream' );?></p>
								</div>
								<div class="tagchecklist"></div>
							</div>
						</div>

					</td>

				</tr>
				<tr>
					<th><?php esc_html_e( 'Gallery Categories', 'photostream' ); ?></th>
					<td>
						<ul class="gallery-categories"><?php wp_category_checklist( 0, 0, $stream->cats ); ?></ul>
						<p class="help"><?php esc_html_e( 'Choose one or more categories for gallery posts.', 'photostream' ); ?></p>
					</td>
					<?php 
					// @todo: Make adding categories nicer... 
					// When the user select the category display the list of categories next to the help?>
				</tr>
			</tbody>
		</table>
		<p><a href="#advance-settings" id="advance-settings-toggle"><?php _e( 'Show Advance Settings', 'photostream' ); ?></a></p>

		<div class="advance-settings" >
		<h3><?php _e( 'Advance Settings', 'photostream' ); ?></h3>
		<table class="form-table">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Gallery Post Type' , 'photostream'); ?></th>
					<td>
						<select name="photostream<?php echo $form_section; ?>[post_type]">
						<?php foreach ( get_post_types() as $type ) { ?>
							<option value="<?php esc_attr_e( $type ); ?>"  <?php selected( $type, $stream->post_type ); ?>><?php esc_html_e( $type ); ?></option>
						<?php } ?>
						</select>
						<p class="help"><?php esc_html_e( 'Choose a post type for gallery posts. This is an advanced setting for users writing themes around this plugin.', 'photostream' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Gallery Shortcode', 'photostream' ); ?></th>
					<td>
						<input name="photostream<?php echo $form_section; ?>[gallery_shortcode]" type="text" placeholder="[gallery]" value="<?php esc_attr_e($stream->gallery_shortcode); ?>" />
						<p class="help"><?php esc_html_e( 'Choose a custom gallery shortcode for gallery posts. This is an advanced setting and is   best left alone unless you have a specific reason to change it.' , 'photostream'); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Customize Video Shortcode' ); ?></th>
					<td>
						<input name="photostream<?php echo $form_section; ?>[video_shortcode]" type="text" placeholder="loop='off' autoplay='off'" value="<?php esc_attr_e($stream->video_shortcode); ?>" />
						<p class="help"><?php esc_html_e( 'Add parameters to the [video] shortcode. This goes inside the shortcode so, unlike the gallery setting, you should not supply the entire shortcode. This is an advanced setting and is best  left alone unless you have a specific reason to change it.', 'photostream' ); ?></p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Media Filename Config', 'photostream' ); ?></th>
					<td>
						<input 
							type="text" 
							name="photostream<?php echo $form_section; ?>[rename_file]" 
							class="regular-text"
							value="<?php esc_attr_e( $stream->rename_file ); ?>"
							placeholder="stream->title - owner->fullName -- file->date(Y-m-d).jpg"/>
						<p class="help"><?php esc_html_e( 'Configure the filename to save imported media as. Click "show options" for formatting help.', 'photostream' ); ?></p>
						<?php $this->display_text_transform_options(); ?>
					</td>	
				</tr>
			</tbody>
		</table>
		</div>
		<br/>

		<?php 
			$submit_text = ( ( 'edit' == $this->view ) ? __( 'Update Photostream', 'photostream' ) :  __( 'Add Photostream and Import Media', 'photostream' ) ); 
			$this->display_submit_form( $submit_text ); ?>
		</form>
		<?php 
	}
	/**
	 * Displays the submit form html 
	 * @param  string $submit_text The text the user clicks on to submit the form.
	 * @return null  
	 */
	function display_submit_form( $submit_text ){ ?> 
		<div class="submit-shell">
			<input type="submit" class='button-primary submit-form' value="<?php echo esc_attr( $submit_text ); ?>"><span class="spinner"></span>
		</div> 
		
		<span class="cancel-link"> <?php esc_html_e( 'or', 'photostream' ); ?> 
		<a href="<?php echo esc_url( $this->admin_page ); ?>"><?php esc_html_e( 'cancel return to Photostreams', 'photostream'); ?></a></span>
		<?php 
	}

	/**
	 * Import Stream UI Admin Panel 
	 * 
	 * @return null
	 */
	function admin_ui_import_stream() {

		if( !$this->key ) {
			return $this->display_error( 'Sorry but this key doesn\'t stream to exits', 'photostream' );
		}

		$stream = $this->get_stream_info( $this->key );

		$client = new Photostream_Client( $this->key );

		if( !is_object($client ) ) 
			return $this->display_error( 'It seems that the shotostream URL is offline', 'photostream' );
		
		$data = $client->get();
		$url = $client->get_url();
		if( !is_object( $data ) )
			return $this->display_error(  'It seems that the shotostream URL is offline', 'photostream' );
		

		if( sizeof( $data->photos ) < 1 )
			return $this->display_error( 'It looks like you don\'t have any media in this photostream!' );
		
		$groups = $client->groups();
		// don't need the client any more
		unset( $client ); ?>
		<div class="stream-info">
			<h3><?php esc_html_e( 'Importing photostream', 'photostream' ); ?>: <em><?php echo esc_html( $stream->title ); ?></em></h3>
			<p class="info"><strong><?php echo esc_html( $stream->title ); ?></strong> <?php esc_html_e( 'photostream contains', 'photostream' );?> <?php echo $this->get_stream_media_info( $data, count( $groups ) ); ?>. <a target="_blank" href="<?php echo esc_url( $url ); ?>" title="<?php _e( 'Go to photo stream '); ?>. <?php echo esc_attr( $stream->title ); ?>"><?php esc_html_e( 'Visit photostream', 'photostream' ); ?></a> #<?php echo esc_html( $this->key ); ?></p>
		</div>
		
		<div id="progress-bar"><div id="progress-bar-status"></div></div>
		<?php 
		// Generate the posts from the groups if they are not there already
		$this->generate_posts_from_groups( $stream, $groups );
		

		$gallery_query = $this->get_processed_galleries( $this->key );
		// The Loop
		if ( $gallery_query->have_posts() ) {
			while ( $gallery_query->have_posts() ) {

				$gallery_query->the_post();
				
				$categories_list = get_the_category_list( __( ', ' ) );
				$tag_list = get_the_tag_list( '', __( ', ' ) );
				
				$date = sprintf( '<time class="entry-date" datetime="%1$s">%2$s</time>',
					esc_attr( get_the_date( 'c' ) ),
					esc_html( get_the_date() )
				);

				if ( $tag_list ) {
					$utility_text = __( 'Gallery was posted in %1$s and tagged %2$s on %3$s<span class="by-author"> by %4$s</span>.', 'photostream' );
				} elseif ( $categories_list ) {
					$utility_text = __( 'Gallery was posted in %1$s on %3$s<span class="by-author"> by %4$s</span>.', 'photostream' );
				} else {
					$utility_text = __( 'Gallery was posted on %3$s<span class="by-author"> by %4$s</span>.', 'photostream' );
				}
				
				switch( get_post_status() ) {
					case 'publish':
						$view_link = '<a class="button" href="' . get_permalink( ) . '" >'.esc_html__( 'View', 'photostream' ) . '</a>';
					break;

					default:
						$view_link = '<a class="button" href="' . site_url( '?p='.get_the_ID() ) . '" >'.esc_html__( 'Preview', 'photostream' ) . '</a>';
					break;
				}
				?>
				<div class="gallery">
					<h4><?php the_title(); ?> </h4>
					<a href="<?php echo get_edit_post_link(); ?>" class="button"><?php esc_html_e( 'Edit', 'photostream'); ?></a> <span class="separator">|</span> <?php echo $view_link; ?>
					<p class="byline"><?php printf( $utility_text, $categories_list, $tag_list, $date, get_the_author() ); ?></p>
					<ul id="gallery-<?php echo esc_attr( get_the_id() ); ?>" class="attachemnt-grid"></ul>
				</div>
				<?php
			} // end while

		} else {
			// No posts found
			esc_html_e( "No Galleries Found!", 'photostream' );
		}
		/* Restore original Post Data */
		wp_reset_postdata();

		$photos_data = array();

		foreach( $data->photos as $photo ) {
			$photos_data[] = array(
				'photoGuid' => $photo->photoGuid,
				'group_id'	=> $photo->batchGuid
			);
		}

		$js_data = array( 
			'photos' 		=> $photos_data,
			'stream_key' 	=> $this->key,
			'start_text'	=> __( 'Start Importing of Media', 'photostream'),
			'done'			=> __( 'Done', 'photostream' ),
			'finished'		=> __( 'Success, we just finish adding all media', 'photostream' ) .' <a href="'. $this->admin_page. '">'.__('return to photostreams', 'photostream'). '</a>.',
			'close'			=> __( 'If you leave this page your import will be interupted', 'photostream' )
			);

		wp_enqueue_script(  'photostream-import' );
		wp_localize_script( 'photostream-import', 'photostreamImport', $js_data );
		
	}
	/**
	 * Displays the error massage and links them back to the main setting page
	 * @param  string $error Error message to be displayed
	 * @return true        
	 */
	function display_error( $error ) { ?>
		<div id="message" class="error">
			<p><?php echo esc_html( $error ); ?></p>
			<?php if( ! in_array( $this->view, array( 'add', 'init' ) ) ) { ?>
				<p><a href="<?php echo esc_url( $this->admin_page ); ?>"><?php _e( 'Return to manage photostream', 'photostream' ); ?></a></p>
			<?php } ?>
		</div>
		<?php 
		return true;
	}
	/**
	 * Delete Stream UI Admin Panel 
	 * 
	 * @return html
	 */
	function admin_ui_delete_stream() {

		$stream = $this->get_stream_info( $this->key );

		$num_of_galleries = $this->count_processed_galleries( $this->key );
		$number_of_photos = $this->count_processed_photos( $this->key );
		$number_of_videos = $this->count_processed_videos( $this->key );

		$galleries_display = $this->get_media_info( $num_of_galleries, 0, 0 );
		$media_display	   = $this->get_media_info( 0, $number_of_photos, $number_of_videos );

		?>
		<div class="stream-info">
			<h3><?php esc_html_e('Deleting photostream', 'photostream'); ?>: <em><?php echo esc_html( $stream->title ); ?></em></h3>
			<p class="info"><strong><?php echo esc_html(  $stream->title ); ?></strong> <?php esc_html_e( 'photostream has', 'photostream' ); ?> <?php echo $this->get_processed_media_info( $this->key ); ?>. <a target="_blank" href="<?php echo esc_url( $url ); ?>" title="<?php esc_attr_e( 'Go to photo stream', 'photostream' ); ?>. <?php echo esc_attr( $stream->title ); ?>"><?php esc_html_e( 'Visit photostream', 'photostream' ); ?></a> #<?php echo esc_html( $this->key ); ?></p>
		</div>

		<form action="<?php echo esc_url( $this->admin_page ); ?>" method="post">
			<input type="hidden" name="key" value="<?php echo esc_attr( $this->key ); ?>" />
			<input type="hidden" name="section" value="delete" />
			<?php wp_nonce_field( 'photostream-delete-' . $this->key, 'ps-nonce' ); ?>

			<p>
			<?php 
			esc_html_e( 'Are you sure you want to delete this', 'photostream' ); 
			echo ' <strong>' . esc_html( $stream->title ). '</strong> ' ;   
			esc_html_e( 'photostream?', 'photostream' );
			?></p>

			<p><label><input type="checkbox" name="delete_galleries"  /> <?php esc_html_e( 'Also delete the associated', 'photostream' ); ?> <?php echo $galleries_display; ?></label></p>
			<p><label><input type="checkbox" name="delete_media"  />  <?php esc_html_e( 'Also delete the associated', 'photostream' ); ?> <?php echo $media_display; ?></label></p>
		
			<?php $this->display_submit_form( __('Delete Photostream', 'photostream') ); ?>

		</form>
		<?php 
		
	}

	/**
	 * Proccess the users form once they have added a url.
	 * Check that the url is correct, that we are not already syncing it. 
	 * Display any errors. 
	 * 
	 * Show the user how many new posts they are about to create. 
	 * Show the user how many photo they are about to add. 
	 * 
	 */
	function process_admin_add_url() {
		
		if ( !wp_verify_nonce( $_POST['ps-nonce'], 'ps-sync-add-1' ) ){
			$this->error_message = __( 'Can\'t do that!', 'photostream' );
			return;
		}

		// $new_stream = stripslashes_deep( $_POST['photostream-url'] );
		$key = $this->parse_user_input_photostream_key( $_POST['photostream-url'] );
		if( !$key ) {
			$this->error_message = __( 'Photostream URL was not right, please try again!', 'photostream' );
			return;
		}

		if( in_array( $key, $this->get_streams() ) ){
			$this->error_message = __( 'Sorry, you can\'t add the same stream twice.', 'photostream' );
			return;
		}

		$client = new Photostream_Client( $key );
		if( !is_object($client ) ) {
			$this->error_message = __( 'Sorry something went wrong here and we couldn\'t find your photos.', 'photostream' );
			return;
		}
		
		$data = $client->get();
		
		if( !is_object( $data ) ) {
			$this->error_message = __( 'Double check your photostream URL. It doesn\'t work!', 'photostream' );
			return;
		}
		if( sizeof( $data->photos ) < 1 ) {
			$this->error_message = __( 'It looks like you don\'t have any media in this photostream!', 'photostream' );
			return;
		}

		$groups = $client->groups();

		$this->add_data = array(
			'url'			=> $client->get_url(),
			'key'			=> $key,
			'title' 		=> $client->title(),
			'stream_media_info' => $this->get_stream_media_info( $data, count( $groups ) ),
			
			);
		unset( $client );
		
	}

	/**
	 * Add Phtostream to DB.
	 * 
	 */
	function process_admin_add_post() {
		
		if ( !wp_verify_nonce( $_POST['ps-nonce'], 'ps-sync-add-2' ) ){
			$this->error_message = __( 'Can\'t do that!', 'photostream' );
			return false;
		}

		$new_stream = stripslashes_deep( $_POST['photostream']['add'] );
		$key = $this->parse_user_input_photostream_key( $_POST['photostream']['add']['stream'] );
		$new_stream['key'] = $key;
		if ( !empty( $new_stream['tags'] ) )
			$new_stream['tags'] = preg_split( '/ *, */', $new_stream['tags'] );
		else
			$new_stream['tags'] = array();

		if( !empty( $_POST['newtag']['post_tag'] ) )
			$new_stream['tags'] = array_merge ( $new_stream['tags'] , preg_split( '/ *, */', $_POST['newtag']['post_tag'] ) );
				

		$new_stream['cats'] = empty( $_POST['post_category'] ) ? array() : $_POST['post_category'];
		$client = new Photostream_Client( $key );
		$new_stream['title'] = $client->title(); 
		unset( $client );
		
		if ( empty( $new_stream['title'] ) )
			$new_stream['title'] = $key;
		
		
		return $this->add_stream( $new_stream );

	}

	/** 
	 * Delete the photostream.
	 * Leave the posts and images as they are.
	 * 
	 */
	function process_admin_delete_stream() {
		
		$key = $_POST['key'];
	
		if ( !wp_verify_nonce( $_POST['ps-nonce'], 'photostream-delete-'.$key ) ){
			$this->error_message = __( 'Can\'t do that!', 'photostream' );
			return;
		}

		$this->remove_stream( $key );
		$client = new Photostream_Client( $key );
		$client->erase();
		unset( $client );

		if( isset( $_POST['delete_galleries'] ) ){
			$this->delete_galleries( $key );
		}

		if( isset( $_POST['delete_media'] ) ){
			$this->delete_media( $key );
		}
	}

	/**
	 * Process the updates to photostream settings.
	 * 
	 */
	function process_admin_edit_post() {

		foreach( $_POST['photostream']['edit'] as $key => $data ) {
			$data = stripslashes_deep( $data );
			if ( !wp_verify_nonce( $_POST['ps-nonce'], 'ps-sync-edit-' . $key ) )
				continue;
			unset( $data['ps-nonce'] );
			
			if ( !empty( $data['delete'] ) ) {
				$this->remove_stream( $key );
				$client = new Photostream_Client( $key );
				$client->erase();
				continue;
			} else {
				unset( $data['delete'] );
				$stream = $this->get_stream_info( $key );
				
				if ( !empty( $data['tags'] ) )
					$data['tags'] = preg_split( '/ *, */', $data['tags'] );
				else
					$data['tags'] = array();
				
				if( !empty( $_POST['newtag']['post_tag'] ) )
					$data['tags'] = array_merge ( $data['tags'] , preg_split( '/ *, */', $_POST['newtag']['post_tag'] ) );
				
				
				foreach( $data as $option => $val )
					$stream->$option = $val;
				if ( !empty( $_POST['post_category'] ) )
					$stream->cats = $_POST['post_category'];
				else
					$stream->cats = array();
				$this->add_stream( $stream );
			}
		}
	}
	/**
	 * Controller that helps process different the stream data.
	 * Redirect the user if if necessery.
	 * 
	 * @return null
	 */
	function process_admin_page_post() {
		
		if( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) )
			return;

		if ( empty( $_POST ) )
			return;
		
		switch( $_POST['section'] ) {
			case 'add-url': 
				$this->process_admin_add_url(); 
			break;

			case 'add':
				if( $this->process_admin_add_post() )
					wp_redirect(  $this->admin_page .'&view=add_import&key='. $_POST['photostream']['add']['stream']   );
				else
					wp_redirect( $this->admin_page );
			break;
			
			case 'edit':
				$this->process_admin_edit_post(); 
				wp_redirect( $this->admin_page );
			break;

			case 'delete':
				$this->process_admin_delete_stream();
				wp_redirect( $this->admin_page );
			break;
		}
	}

	/**
	 * Finds all the photos belonging to a particular stream.
	 * 
	 * @param  string $key 	Photostream key.
	 * @return int      	Number of imported photos.
	 */
	function count_processed_photos( $key ) {
		
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->postmeta LEFT JOIN $wpdb->posts ON $wpdb->postmeta.post_id = $wpdb->posts.ID WHERE $wpdb->postmeta.meta_key = 'ps_stream' AND $wpdb->postmeta.meta_value = %s AND $wpdb->posts.post_type ='attachment' AND $wpdb->posts.post_mime_type LIKE %s", $key, 'image/%' ) );

	}

	/**
	 * Finds all the videos belonging to a particular stream.
	 * 
	 * @param  string $key 	Photostream key.
	 * @return int      	Number of imported photos.
	 */
	function count_processed_videos( $key ) {
		
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->postmeta LEFT JOIN $wpdb->posts ON $wpdb->postmeta.post_id = $wpdb->posts.ID WHERE $wpdb->postmeta.meta_key = 'ps_stream' AND $wpdb->postmeta.meta_value = %s AND $wpdb->posts.post_type ='attachment' AND $wpdb->posts.post_mime_type LIKE %s", $key, 'video/%' ) );

	}

	/**
	 * Finds all the posts/pages/other post types belonging to a particular stream.
	 * 
	 * @param  string $key 	Photostream key.
	 * @return int      	Number of imported photos.
	 */
	function count_processed_galleries( $key ) {
		
		global $wpdb;
		
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->postmeta LEFT JOIN $wpdb->posts ON $wpdb->postmeta.post_id = $wpdb->posts.ID WHERE $wpdb->postmeta.meta_key = 'ps_stream' AND $wpdb->postmeta.meta_value = %s AND $wpdb->posts.post_type != 'attachment' AND $wpdb->posts.post_status != %s", $key, 'trash' ) );
		
	}

	/**
	 * Get all the processed galleries that we have in our database.
	 * 
	 * @param  string $key Stream key. 
	 * @return array
	 */
	function get_processed_galleries( $key ) {
		$args = array(
		'posts_per_page'   => -1,
		'orderby'          => 'post_date',
		'order'            => 'DESC',
		'meta_key'         => 'ps_stream',
		'meta_value'       => $key,
		'post_type'        => 'any',
		'post_status'      => array( 'draft', 'publish' ),
		'suppress_filters' => true 
		);

		$query = new WP_Query( $args );
		return $query;
	}

	/**
	 * Delete the galleries accociated with a stream.
	 * 
	 * @param  string $key 
	 * @return 
	 */
	function delete_galleries( $key ) {
		global $wpdb;

		$gallery_query = $this->get_processed_galleries( $key );
		// The Loop
		if ( $gallery_query->have_posts() ) {
			while ( $gallery_query->have_posts() ) {
				$gallery_query->the_post();
				wp_delete_post( get_the_id() );
			}
			wp_reset_postdata();
		}
	}

	/**
	 * Delete all media associated with a stream.
	 * 
	 * @param  string $key
	 * @return 
	 */
	function delete_media( $key ) {
		$args = array(
		'posts_per_page'   => -1,
		'orderby'          => 'post_date',
		'order'            => 'DESC',
		'meta_key'         => 'ps_stream',
		'meta_value'       => $key,
		'post_type'        => 'attachment',
		'post_status'      => array( 'inherit' ),
		'suppress_filters' => true 
		);
		
		
		$query = new WP_Query( $args );
		// The Loop
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				wp_delete_attachment(  get_the_id(), true );
			}
			wp_reset_postdata();
		}
	}

	/**
	 * Imports invividual picture. Return JSON if successful.
	 * 
	 */
	function ajax_import_media() {

		// process the list of 
		@error_reporting( 0 ); // Don't break the JSON result

		header( 'Content-type: application/json' );
		
		$key 		= $_POST['stream'];
		$photo_id 	= $_POST['photo_id'];
		$group_key 	= $_POST['group_id'];
		
		$stream = $this->get_stream_info( $key );

		$group_posts = get_posts( array( 
			'meta_key' => 'ps_batch_guid',
			'meta_value' => $group_key,
			'post_parent' => 0,
			'post_status' => array( 'draft', 'publish' ),
			'post_type' => $stream->post_type,
			'posts_per_page' => -1
		) );
		if( empty( $group_posts ) ){
			$this->error_message = __( 'You first need to create the Gallery', 'photostream' );
			return;
		}
		$group_post_id = $group_posts[0]->ID;
		
		$client = new Photostream_Client( $key );

		if( !is_object($client ) ) {
			$this->error_message = __( 'It seems that the shotostream URL is offline', 'photostream' );
			return;
		}

		$data = $client->get();
		
		if( !is_object( $data ) ) {
			$this->error_message = __( 'It seems that the shotostream URL is offline', 'photostream' );
		}

		if( sizeof( $data->photos ) < 1 ) {
			$this->error_message = __( 'It looks like you don\'t have any media in this photostream!', 'photostream' );	
		}
		
		$photos_data = array();
		foreach( $data->photos as $photo){
			
			if( $photo_id == $photo->photoGuid ){
				$image = $photo;

				break;
			}

		}

		if ( ! current_user_can( 'upload_files' ) )
			die( json_encode( array( 'success' => __( "Your user account doesn't have permission to upload media", 'photostream' ) ) ) );

		@set_time_limit( 900 ); // 5 minutes per image should be 

		// Does the image already exists in our database? 
		$image_data = $this->get_processed_image( $image ); 
		
		if( !empty( $image_data ) ) {
			
			$mine_type =  explode("/", $image_data[0]->post_mime_type );
			
			switch( $mine_type[0] ) {
				case 'image':
					$html = wp_get_attachment_image_src( $image_data[0]->ID, array( 100, 100 )   );
				
				break;

				default:
					$html 	= '<img src="'.wp_mime_type_icon( $image_data[0]->post_mime_type ).'" />';
				break;
			} 
			$html = wp_get_attachment_image( $image_data[0]->ID, array( 118, 118 ), true ); // . ' alt="' . esc_attr( $image_data[0]->post_title ). '" width="100" height="100" />';
			die( json_encode( array( 'html' => $html, 'group_id' =>  $group_post_id ) ) );
		}

		$processed = $this->process_group_photo( $stream, $group_post_id, $group_key, $image );
		
		if ( $processed['type'] == 'video/mp4' ) {

				$group_post = get_post( $group_post_id, ARRAY_A );
				if ( !empty( $stream->video_shortcode ) ) {
					$group_post['post_content'] = $group_post['post_content'] . sprintf( '[video mp4="%s" %s]', $processed['url'], $stream->video_shortcode );
				} else {
					$group_post['post_content'] = $group_post['post_content'] . sprintf( '[video mp4="%s"]', $processed['url'] );
				}
				
				wp_update_post( $group_post );	
		}

		// Does the image already exists in our database? 
		$image_data = $this->get_processed_image( $image ); 
		
		if( !empty( $image_data ) ) {
			
			$mine_type =  explode("/", $image_data[0]->post_mime_type );
			
			switch( $mine_type[0] ) {
				case 'image':
					$html = wp_get_attachment_image_src( $image_data[0]->ID, array( 100, 100 )   );
				
				break;

				default:
					$html 	= '<img src="'.wp_mime_type_icon( $image_data[0]->post_mime_type ).'" />';
				break;
			} 
			$html = wp_get_attachment_image( $image_data[0]->ID, array( 118, 118 ), true ); // . ' alt="' . esc_attr( $image_data[0]->post_title ). '" width="100" height="100" />';
			die( json_encode( array( 'html' => $html, 'group_id' =>  $group_post_id ) ) );
		}
	}

	/**
	 * Creates the posts but not the images. Runs on the import page.
	 * 
	 * @param  object $stream 
	 * @param  array $groups
	 * @return null
	 */
	function generate_posts_from_groups( $stream, $groups ){
		
		// Don't download the image just yet.
		$images = array();
		foreach( array_reverse( array_keys( $groups ) ) as $group_key ) {
			$images_data = $groups[$group_key];
			$images_first_data = $images_data[0];
			
			$images_first_data->derivatives = array();
			$images[] = $images_first_data;

			$this->process_stream_group( $stream, $group_key ,  $images );	
		}
	}

	/**
	 * Create or update posts with galleries.
	 * 
	 * @param  object $stream    
	 * @param  string $group_key The identifiying key of the group.
	 * @param  array  $images    Array of image objects.
	 * @return null            
	 */
	function process_stream_group( &$stream, $group_key, &$images ) {

		if ( empty( $stream->processed_groups[$group_key] ) )
			$stream->processed_groups[$group_key] = array();
		
		$group_post_id = 0;
		
		$group_posts = get_posts( array( 
			'meta_key' => 'ps_batch_guid',
			'meta_value' => $group_key,
			'post_parent' => 0,
			'post_status' => array( 'draft', 'publish' ),
			'post_type' => $stream->post_type
		) );
		
		if ( empty( $group_posts ) ) {
			$post = array(
				'post_author' => $stream->user,
				'post_content' => '[gallery]',
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( $images[0]->batchDateCreated ) ) ,
				'post_title' => 'stream->title - batch->date(Y-m-d)',
				'post_status' => 'draft',
				'post_type' => $stream->post_type,
				'tags_input' => empty( $stream->tags ) ? '' : implode( ', ', $stream->tags ),
			);
		
			if ( !empty( $stream->gallery_shortcode ) )
				$post['post_content'] = $stream->gallery_shortcode;
			if ( !empty( $images[0]->caption ) && $stream->use_caption ) {
				if ( $stream->use_caption == 'above' )
					$post['post_content'] = $images[0]->caption . "\n" . $post['post_content'];
				else
					$post['post_content'] = $post['post_content']. "\n" . $images[0]->caption;
			}
		
			if ( !empty( $stream->post_title ) )
				$post['post_title'] = $stream->post_title;
		
			$post['post_title'] = $this->apply_stream_centric_text_transforms( $post['post_title'], $stream );
			$post['post_title'] = $this->apply_image_centric_text_transforms( $post['post_title'], $images[0] );
			$post['post_title'] = $this->apply_generic_text_transforms( $post['post_title'] );
		
			if ( !empty( $stream->password ) )
				$post['post_password'] = $stream->password;
		
			$group_post_id = wp_insert_post( $post );
			if ( empty( $group_post_id ) )
				return false; // what?
			update_post_meta( $group_post_id, 'ps_stream', $stream->key );
			update_post_meta( $group_post_id, 'ps_batch_guid', $group_key );
			$group_post = get_post( $group_post_id, ARRAY_A );
			set_post_format( $group_post_id, 'gallery' );
		
		} else {
		
			$group_post_id = $group_posts[0]->ID;
			$group_post = get_post( $group_post_id, ARRAY_A );
		
		}

		if ( !empty( $stream->cats ) )
			wp_set_post_terms( $group_post_id, $stream->cats, 'category' );
		else
			wp_set_post_terms( $group_post_id, '', 'category' );

		if ( !empty( $stream->tags ) )
			wp_set_post_terms( $group_post_id, $stream->tags, 'post_tag' );
		else
			wp_set_post_terms( $group_post_id, '', 'post_tag' );

		$ready = true;
		
		foreach( $images as $image ) {
			if ( empty( $image ) )
				continue;
			
			if ( !empty( $stream->processed_groups[$group_key][$image->photoGuid] ) )
				continue;
			
			// does the image already exist in our db?
			$image_posts = $this->get_processed_image( $image );
			if( !empty( $image_posts ) )
				continue;

			$processed = $this->process_group_photo( $stream, $group_post_id, $group_key, $image );
			
			if ( !$processed )
				$ready = false;
			
			if ( $processed['type'] == 'video/mp4' ) {
			
				if ( !empty( $stream->video_shortcode ) ) {
					$group_post['post_content'] = $group_post['post_content'] . sprintf( '[video mp4="%s" %s]', $processed['url'], $stream->video_shortcode );
				} else {
					$group_post['post_content'] = $group_post['post_content'] . sprintf( '[video mp4="%s"]', $processed['url'] );
				}
				wp_update_post($group_post);
			
			}
		}
		
		if ( !$ready )
			return;
		
		$group_post = get_post( $group_post_id, ARRAY_A );
		$group_post['post_status'] = $stream->post_status;
		wp_update_post( $group_post );
	}

	/** 
	 * A way to check if the image already exists in the database.
	 * 
	 * @param  object $image 
	 * @return array of WP Posts 
	 */
	function get_processed_image( $image ){
		return get_posts( array( 
			'meta_key' => 'ps_photo_guid',
			'meta_value' => $image->photoGuid,
			'post_status' => null,
			'post_type' => 'attachment'
		) );

	}

	/**
	 * Add the image from iClouds to our database as media and an attachemnt to the gallery.
	 * 
	 * @param  object $stream        
	 * @param  int $group_post_id    Post ID.	
	 * @param  string $group_key     Uniquly identifing sting.
	 * @param  object $image         Image data.
	 * @return bool                  Was the image added.
	 */
	function process_group_photo( &$stream, $group_post_id, $group_key, $image ) {
		
		if ( empty( $image ) )
			return false;
		
		if ( empty( $image->derivatives ) )
			return false;
		
		$derivatives = get_object_vars( $image->derivatives );
		
		$width = max( array_keys( $derivatives ) );
		
		if ( $stream->rename_file )
			$path = $stream->rename_file;
		else
			$path = 'stream->title - owner->fullName -- image->date(Y-m-d).jpg';
		
		$path = $this->apply_stream_centric_text_transforms( $path, $stream );
		$path = $this->apply_image_centric_text_transforms( $path, $image );
		$path = $this->apply_generic_text_transforms( $path );
		$path = preg_replace( '/\.(jpe?g|mp4)$/i', '', $path );
		
		if ( isset( $image->mediaAssetType ) && $image->mediaAssetType == 'video' )
			$path = "$path.mp4";
		else
			$path = "$path.jpg";
		
		if ( empty( $path ) )
			return false;
		
		$mime = wp_check_filetype( $path );
		
		if ( empty( $mime ) || empty( $mime['type'] ) )
			return false;
		
		if ( isset( $image->mediaAssetType ) && $image->mediaAssetType == 'video' ) {
			foreach( array( '720p', '360p' ) as $resolution ) {
				foreach( $derivatives[$resolution]->_wp_ps_url as $url ) {
					$bits = wp_remote_retrieve_body( $response = wp_remote_get( $url ) );
					if ( $bits )
						break;
				}
				if ( $bits )
					break;
			}
		} else {
			foreach( $derivatives[$width]->_wp_ps_url as $url ) {
				$response  = wp_remote_get( $url );
				$bits = wp_remote_retrieve_body( $response  );
				if ( $bits )
					break;
			}
		}
		
		if ( empty( $bits ) )
			return false;
		
		if ( $bits === 'Unauthorized' )
			return false;
		
		$upload = wp_upload_bits( $path, null, $bits );
		
		if ( ! empty($upload['error']) )
			return false;
		
		$attachment = array(
			'post_title' => $path,
			'post_content' => '',
			'post_type' => 'attachment',
			'post_parent' => $group_post_id,
			'post_mime_type' => $mime['type'],
			'guid' => $upload[ 'url' ]
		);

		$exif = false;
		if ( !isset( $image->mediaAssetType ) || $image->mediaAssetType != 'video' ) {
			if ( function_exists( 'exif_read_data' ) )
				$exif = exif_read_data( $upload[ 'file' ] );
		}
		
		$id = wp_insert_attachment( $attachment, $upload[ 'file' ], $group_post_id );
		
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		update_post_meta( $id, 'ps_stream', $stream->key );
		update_post_meta( $id, 'ps_batch_guid', $group_post_id );
		update_post_meta( $id, 'ps_photo_guid', $image->photoGuid );
		update_post_meta( $id, 'ps_image_data', $image );
		if ( $exif && is_array( $exif ) ) { 
			foreach( $exif as $exif_idx => $exif_val ) {
				update_post_meta( $id, 'ps_exif_' . $exif_idx, $exif_val );
			}
		}
		$struct = array(
			'id'   => strval( $id ),
			'file' => $upload['file'],
			'url'  => $upload[ 'url' ],
			'type' => $mime['type']
		);
		$stream->processed_groups[$group_key][$image->photoGuid] = time();
		return apply_filters( 'wp_handle_upload', $struct, 'upload' );
	}

	/**
	 * Generates the posts and images from the stream.
	 * Used in the cron update.
	 * 
	 * @param  object $stream 
	 * @return null         
	 */
	function process_stream( $stream ) {
		$stream->last_processed = time();
		$stream->last_completed = time();
		if ( empty( $stream->processed_groups ) )
			$stream->processed_groups = array();
		$client = new Photostream_Client( $stream->key );
		if ( !$client->get() ) {
			unset( $client );
			return false;
		}
		$groups = $client->groups();
		foreach( array_reverse( array_keys( $groups ) ) as $group_key ) {
			$images = $groups[$group_key];
			$this->process_stream_group( $stream, $group_key, $images ); 
		}
		if ( !empty( $stream->processed_groups ) ) {
			foreach( $stream->processed_groups as $key => $group ) {
				if ( !empty( $groups[$key] ) )
					continue;
				unset( $stream->processed_groups[$key] );
			}
		}
		$this->add_stream( $stream );
		unset( $client );
	}

	/**
	 * Called via cron, to process the stream.
	 * 
	 * @return null
	 */
	function do_scheduled_work() {
		if ( !function_exists('wp_generate_attachment_metadata') )
			include ABSPATH . 'wp-admin/includes/image.php';
		if ( !function_exists('wp_read_video_metadata') )
			include ABSPATH . 'wp-admin/includes/media.php';
		$streams = get_option( "photostream_streams" );
		if ( empty( $streams ) )
			return;
		foreach( $streams as $key ) {
			$stream = $this->get_stream_info( $key );
			if ( !$stream->enabled )
				continue;
			$process = false;
			if ( empty( $stream->last_processed ) )
				$process = true;
			else if ( empty( $stream->last_completed ) )
				$process = true;
			else if ( $stream->last_completed < $stream->last_processed )
				$process = true;
			else if ( ( time() - $stream->last_completed ) > 1800 )
				$process = true;
			if ( !$process )
				continue;
			$this->process_stream( $stream );
		}
	}

	/**
	 * Relaces the text with stream data.
	 * 
	 * @param  string $text   
	 * @param  object $stream 
	 * @return string         Replaced string.
	 */
	function apply_stream_centric_text_transforms( $text, $stream ) {
		if ( empty( $text ) || empty( $stream ) )
			return $text;
		$text = str_replace( 'stream->title', $stream->title, $text );
		$text = str_replace( 'stream->key', $stream->key, $text );
		return $text;
	}

	/**
	 * Replace the text with image data.
	 * 
	 * @param  string $text  
	 * @param  object $image 
	 * @return string        Replaced string.
	 */
	function apply_image_centric_text_transforms( $text, $image ) {
		if ( empty( $text ) || empty( $image ) )
			return $text;
		// Owner replacements
		$text = str_replace( 'owner->firstName', $image->contributorFirstName, $text );
		$text = str_replace( 'owner->lastName', $image->contributorLastName, $text );
		$text = str_replace( 'owner->fullName', $image->contributorFullName, $text );

		// Image Replacements
		$text = str_replace( 'image->', 'file->', $text ); // Maintain backwards compatibility
		$text = str_replace( 'video->', 'file->', $text ); // Just in case someone tries this...
		$text = str_replace( 'file->caption', $image->caption, $text );
		$text = str_replace( 'file->guid', $image->photoGuid, $text );
		if ( preg_match_all( '/file->date\(([^)]+)\)/', $text, $matches ) ) {
			$datetime = strtotime( $image->dateCreated );
			foreach( array_keys( $matches[0] ) as $m )
				$text = str_replace( $matches[0][$m], date( $matches[1][$m], $datetime ), $text );
		}

		// Batch Replacements
		$text = str_replace( 'batch->guid', $image->batchGuid, $text );
		if ( preg_match_all( '/batch->date\(([^)]+)\)/', $text, $matches ) ) {
			$datetime = strtotime( $image->batchDateCreated );
			foreach( array_keys( $matches[0] ) as $m )
				$text = str_replace( $matches[0][$m], date( $matches[1][$m], $datetime ), $text );
		}
		return $text;
	}

	/**
	 * Replaces the date with string.
	 * 
	 * @param  string $text 
	 * @return string       Replaced string
	 */	
	function apply_generic_text_transforms( $text ) {
		if ( empty( $text ) )
			return $text;
		// Current DateTime Replacements
		if ( preg_match_all( '/fetch->date\(([^)]+)\)/', $text, $matches ) ) {
			foreach( array_keys( $matches[0] ) as $m )
				$text = str_replace( $matches[0][$m], date( $matches[1][$m] ), $text );
		}
		return $text;
	}

	/**
	 * Returns the options key of the stream.
	 * 		
	 * @param  string $stream Stream key
	 * @return srting
	 */
	function stream_option_key( $stream ) {
		return sprintf( "photostream_i_%s", preg_replace( '/[^0-9a-z]/i', '-', $stream ) );
	}

	/**
	 * Clears the stream cached info.
	 * 
	 * @return null
	 */
	function clear_thread_stream_cache() {
		$this->streams = null;
		$this->streaminfo = array();
	}

	/**
	 * Returns all streams.
	 * 
	 * @return array Array of stream keys.
	 */
	function get_streams() {
		if ( null !== $this->streams )
			return $this->streams;
		$this->streams = get_option( "photostream_streams" );
		if ( empty( $this->streams ) )
			$this->streams = array();
		return $this->streams;
	}

	/**
	 * Adds the stream data to options table and cache.
	 * 
	 * @param object $stream
	 * @return bool Did we add the stream.
	 */
	function add_stream( $stream ) {
		if ( is_array( $stream ) )
			$stream = (object)$stream;
		if ( !is_object( $stream ) )
			return false;
		$stream->_version = 1;
		update_option( $this->stream_option_key( $stream->key ), $stream );
		$list = $this->get_streams();
		$list[] = $stream->key;
		$list = array_filter( array_unique( $list ) );
		update_option( 'photostream_streams', $list );
		$this->clear_thread_stream_cache();
		return true;
	}

	/**
	 * Remove the stream from options table and cache.
	 * 
	 * @param  string $stream_key
	 * @return null
	 */
	function remove_stream( $stream_key ) {
		delete_option( $this->stream_option_key( $stream_key ) );
		$list = preg_grep( 
			'/^' . preg_quote( $stream_key ).'$/',
			$this->get_streams(),
			PREG_GREP_INVERT
		);
		$list = array_filter( array_unique( $list ) );
		update_option( 'photostream_streams', $list );
		$this->clear_thread_stream_cache();
	}

	/**
	 * [get_stream_info description]
	 * @param  [type] $stream [description]
	 * @return [type]         [description]
	 */
	function get_stream_info( $stream ) {
		$key = $this->stream_option_key( $stream );
		if ( array_key_exists( $key, $this->streaminfo ) )
			return $this->streaminfo[$key];
		$this->streaminfo[$key] = get_option( $key );
		if ( empty( $this->streaminfo[$key] ) ) {
			$this->streaminfo[$key] = (object)array(
				'shard' => $stream{1},
			);
		}
		return $this->streaminfo[$key];
	}
	/**
	 * Find the stream key from user input.
	 * @param  string $key Potentially a url or a key.
	 * @return string      
	 */
	function parse_user_input_photostream_key( $key ) {
		if ( 0 === strpos( $key, '#' ) )
			$key = substr( $key, 1 );
		else if ( false !== strpos( $key, '//' ) || false !==  strpos( $key, '#' ) ) 
			$key = parse_url( $key, PHP_URL_FRAGMENT );
		if ( !preg_match( '/^[0-9a-zA-Z]+$/', $key ) )
			return false;
		return $key;
	}
	/**
	 * A way to add processing error.
	 * @param [type] $section    [description]
	 * @param [type] $subsection [description]
	 * @param [type] $severity   [description]
	 * @param [type] $message    [description]
	 */
	function add_processing_error( $section, $subsection, $severity, $message ) {
		$this->processing_errors[$section][$subsection][] = (object)array( 
			'severity' => $severity, 
			'message' => $message 
		);
	}
	/**
	 * Validation functionf or the photostream.
	 * @param  string $key potential stream key
	 * @return bool   
	 */
	function validate_photostream_key( $key ) {
		$rval = (object)array(
			'shard' => 1,
			'last_fetch_time' => 0,
			'debug' => '',
		);
		$clean_key = $this->parse_user_input_photostream_key( $key );
		if ( empty( $clean_key ) ) {
			$this->add_processing_error( 
				'new', 
				'validation', 
				0, 
				sprintf( __('%s does not look like a valid Photostream key.', 'photostream'), $key )
			);
			return false;
		}
	}

}
/**
 * Do work for the on cron.
 * 
 * @return [type] [description]
 */
function photostream_cron() {
	$GLOBALS['__photostream']->do_scheduled_work();
}
add_action( 'photostream_hourly_cron', 'photostream_cron' );

/**
 * Schedule cron for every 15 mintes.
 * 
 * @param  array $schedules 
 * @return array
 */
function photostream_cron_add_quarter_hourly( $schedules ) {
	$schedules['quarter_hourly'] = array(
		'interval' => 900,
		'display' => __( 'Every 15 minutes', 'photostream' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'photostream_cron_add_quarter_hourly' );

/**
 * On plugin activation.
 * @return null
 */
function photostream_activation() {
	if ( !wp_next_scheduled( 'photostream_hourly_cron' ) )
		wp_schedule_event( time(), 'quarter_hourly', 'photostream_hourly_cron');
}
register_activation_hook( __FILE__, 'photostream_activation' );

/**
 * On plugin deactivation.
 * @return null 
 */
function photostream_deactivation() {
	if ( wp_next_scheduled( 'photostream_hourly_cron' ) ) 
		wp_clear_scheduled_hook('photostream_hourly_cron');
}
register_deactivation_hook( __FILE__, 'photostream_deactivation' );

$GLOBALS['__photostream'] = new Photostream();

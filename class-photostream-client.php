<?php

class Photostream_Client {

	private $key = null;
	private $shard = null;
	private $data = null;
	private $request = null;
	private $info = null;
	private $deleted = false;
	private $updated_data = false;
	private $updated_info = false;

	public function __construct( $photostream_key ) {
		$this->key = $photostream_key;
		$this->shard = substr( $this->key, 1, 1 );
		$this->info = get_option( $this->info_key() );
		if ( !is_object( $this->info ) ) {
			$this->updated_info = true;
			$this->info = new StdClass();
			$this->save();
		}
	}

	public function __destruct() {
		$this->save();
	}

	public function get() {
		if ( !empty( $this->data ) ) {
			$this->feed_photo_urls();
			return $this->data;
		}
		//if ( !defined( 'DOING_AJAX' ) || !DOING_AJAX ) {
			if( isset( $this->info->last_fetch_success ) ) {
				if ( ( time() - $this->info->last_fetch_success ) < 3600 )
					$data = get_transient( $this->data_key() );	
			}
			
		//}
		if ( empty( $data ) )
			$data = $this->feed();
		if ( empty( $data ) )
			return false;
		$this->data = $data;
		$this->feed_photo_urls();
		return $this->data;
	}

	public function groups() {
		$data = $this->get();
		if ( empty( $data ) )
			return $data;
		$groups = array();
		foreach( $data->photos as $photo ) {
			if ( empty( $groups[ $photo->batchGuid ] ) )
				$groups[ $photo->batchGuid ] = array();
			$groups[ $photo->batchGuid ][] = $photo;
		}
		return $groups;
	}
	public function get_total_image_size() {
		$data = $this->get();
		if ( empty( $data ) )
			return $data;

		foreach( $data->photos as $photo ) {

		}

	}

	public function erase() {
		delete_transient( $this->data_key() );
		delete_option( $this->info_key() );
		$this->deleted = true;
	}

	private function post( $url, $body ) {
		global $__photostream;
		$response = wp_remote_post( $url, array( 'body' => $body ) );
		if( is_wp_error( $response ) ) {
			$end_message = '<br /><a href="' . esc_url( $__photostream->get_admin_page() ) . '" > '.esc_html__( 'Go Back and try again!', 'photostream' ).'</a>';
			wp_die( $response->get_error_message().$end_message );
		}
		if ( !empty( $response['body'] ) && $response['body']{0} == '{' ) {
			$response['body'] = json_decode( $response['body'] );
		}
		return $this->request = $response;
	}

	private function url( $type ) {
		return sprintf( 
			'https://p%s-sharedstreams.icloud.com/%s/sharedstreams/%s',
			str_pad( max( 1, abs( intval( base_convert( $this->shard, 36, 10 ) ) ) ), 2, '0', STR_PAD_LEFT ),
			$this->key,
			rawurlencode( $type )
		);
	}

	private function feed_photo_urls() {
		if ( empty( $this->data->photos ) )
			return false;
		$need_urls_for = array();
		foreach( $this->data->photos as $photo ) {
			if ( !empty( $photo->_wp_ps_data ) && !empty( $photo->_wp_ps_data->urls ) )
				continue;
			$photo->_wp_ps_data = (object)array( 'urls' => array() );
			$need_urls_for[ $photo->photoGuid ] = $photo;
		}
		if ( empty( $need_urls_for ) )
			return true;
		foreach( array_chunk( $need_urls_for, 25, true ) as $need_urls_for_chunk ) {
			$urls = $this->asset_urls( array_keys( $need_urls_for_chunk ) );
			if ( empty( $urls ) || empty( $urls['response'] ) || $urls['response']['code'] != 200 )
				return false;
			$this->updated_data = true;
			$urls = $urls['body'];
			foreach( $need_urls_for_chunk as &$photo ) {
				foreach( $photo->derivatives as &$derivative ) {
					$derivative->_wp_ps_url = array();
					$checksum = $derivative->checksum;
					$item = $urls->items->$checksum;
					$location = $item->url_location;
					$location = $urls->locations->$location;
					foreach( $location->hosts as $host ) {
						$derivative->_wp_ps_url[] = sprintf(
							"%s://%s%s",
							$location->scheme,
							$host,
							$item->url_path 
						);
					}
					$photo->_wp_ps_data->urls[] = $derivative->checksum;
				}
			}
		}
	}

	function title() {
		$this->get();
		return $this->data->streamName;
	}

	function get_url() {
		$this->get();
		return 'https://www.icloud.com/photostream/#'.esc_attr( $this->key );
	}

	private function feed( $avoid_shard_redirect_loop=false ) {
		$post = $this->post(
			$url = $this->url( 'webstream' ),
			json_encode( (object)array( 'streamCtag' => null ) )
		);
		$response_code = strval( $post['response']['code'] );
		switch( substr( $response_code, 0, 1 ) ) {
			case '2':
				$this->info->last_fetch_success = time();
				$this->data = $post['body'];
				$this->updated_data = true;
				$this->updated_info = true;
				return $this->data;
			case '3':
				$this->info->last_fetch_redirect = time();
				if ( $avoid_shard_redirect_loop )
					return false;
				if ( empty( $post['headers'] ) )
					return false;
				if ( empty( $post['headers']['x-apple-mme-host'] ) )
					return false;
				if ( !preg_match( '/^p(\d+)-sharedstreams\.icloud\.com/', $post['headers']['x-apple-mme-host'], $m ) ) {
					$this->info->shard = intval( $m[1] );
					$this->updated_info = true;
				}
				return $this->feed( true );
			default:
				$this->info->last_bad_request = $post;
				$this->updated_info = true;
				return false;
		}
	}	
	
	private function asset_urls( $photo_guids ) {
		return $this->post(
			$this->url( 'webasseturls' ),
			json_encode( (object)array( 'photoGuids' => $photo_guids ) )
		);
	}
	
	private function info_key() {
		return sprintf( "photostream_%s_info", $this->key );
	}

	private function data_key() {
		return sprintf( "photostream_%s_data", $this->key );
	}

	private function save() {
		if ( $this->deleted )
			return;
		if ( !empty( $this->data ) && $this->updated_data )
			set_transient( $this->data_key(), $this->data, 3600 );
		if ( !empty( $this->info ) && $this->updated_info )
			update_option( $this->info_key(), $this->info );
	}

}


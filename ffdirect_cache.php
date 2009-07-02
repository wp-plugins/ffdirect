<?php
class ffdirect_cache {
	var $BASE_CACHE ;
	var $MAX_AGE = 600;

	function ffdirect_cache($base='', $age='') {
		$this->BASE_CACHE = WP_CONTENT_DIR . '/cache';
		if ( $base ) {
			$this->BASE_CACHE = $base;
		}
		if ( $age ) {
			$this->MAX_AGE = $age;
		}
		if (!is_dir($this->BASE_CACHE)) mkdir($this->BASE_CACHE,'755') ;
	}

	function set($key, $val) {
		$filename = $this->BASE_CACHE . '/ffdirect_' . md5( $key );
		file_put_contents($filename,$val);
		return true;
	}

	function get($key) {
		$filename = $this->BASE_CACHE . '/ffdirect_' . md5( $key );
		if ( (file_exists($filename)) && ((filectime($filename) + $this->MAX_AGE) > time()) ) {
			$val = file_get_contents($filename) ;
			return $val ;
		} else {
			return FALSE ;
		}
	}
}
?>

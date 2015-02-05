<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * myCRED_Protect class
 * @since 0.1
 * @version 1.3
 */
if ( ! class_exists( 'myCRED_Protect' ) && ! defined( 'MYCRED_DISABLE_PROTECTION' ) ) :
	class myCRED_Protect {

		public $skey;

		/**
		 * Construct
		 */
		public function __construct( $custom_key = NULL ) {
			if ( $custom_key !== NULL )
				$this->skey = $custom_key;
			else {
				$skey = mycred_get_option( 'mycred_key', false );
				if ( $skey === false )
					$skey = $this->reset_key();

				$this->skey = $skey;
			}
		}

		/**
		 * Reset Key
		 */
		public function reset_key() {
			$skey = wp_generate_password( 16, true, true );
			mycred_update_option( 'mycred_key', $skey );
			$this->skey = $skey;
		}

		/**
		 * Encode
		 */
		public function do_encode( $value = NULL ) {
			if ( $value === NULL || empty( $value ) ) return false;

			if ( function_exists( 'mcrypt_encrypt' ) ) {
				$text = $value;
				$iv_size = mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB );
				$iv = mcrypt_create_iv( $iv_size, MCRYPT_RAND );
				$crypttext = mcrypt_encrypt( MCRYPT_RIJNDAEL_256, $this->skey, $text, MCRYPT_MODE_ECB, $iv );
				return trim( $this->do_safe_b64encode( $crypttext ) );
			}

			return $value;
		}

		/**
		 * Decode
		 */
		public function do_decode( $value ) {
			if ( $value === NULL || empty( $value ) ) return false;

			if ( function_exists( 'mcrypt_decrypt' ) ) {
				$crypttext = $this->do_safe_b64decode( $value ); 
				$iv_size = mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB );
				$iv = mcrypt_create_iv( $iv_size, MCRYPT_RAND );
				$decrypttext = mcrypt_decrypt( MCRYPT_RIJNDAEL_256, $this->skey, $crypttext, MCRYPT_MODE_ECB, $iv );
				return trim( $decrypttext );
			}

			return $value;
		}

		/**
		 * Retrieve
		 */
		protected function do_retrieve( $value ) {
			if ( $value === NULL || empty( $value ) ) return false;

			if ( function_exists( 'mcrypt_decrypt' ) ) {
				$crypttext = $this->do_safe_b64decode( $value ); 
				$iv_size = mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB );
				$iv = mcrypt_create_iv( $iv_size, MCRYPT_RAND );
				$decrypttext = mcrypt_decrypt( MCRYPT_RIJNDAEL_256, $this->skey, $crypttext, MCRYPT_MODE_ECB, $iv );
				$string = trim( $decrypttext );
				parse_str( $string, $output );
				return $output;
			}

			return $value;
		}

		/**
		 * Safe Encode
		 */
		protected function do_safe_b64encode( $string ) {
			$data = base64_encode( $string );
			$data = str_replace( array( '+', '/', '=' ), array( '-', '_', '' ), $data );
			return $data;
		}

		/**
		 * Safe Decode
		 */
		protected function do_safe_b64decode( $string ) {
			$data = str_replace( array( '-', '_' ), array( '+', '/' ), $string );
			$mod4 = strlen( $data ) % 4;
			if ( $mod4 ) {
				$data .= substr( '====', $mod4 );
			}
			return base64_decode( $data );
		}
	}
endif;

/**
 * Load myCRED Protect
 * @since 0.1
 * @version 1.1
 */
if ( ! function_exists( 'mycred_protect' ) ) :
	function mycred_protect()
	{
		if ( ! class_exists( 'myCRED_Protect' ) || defined( 'MYCRED_DISABLE_PROTECTION' ) ) return false;

		global $mycred_protect;

		if ( ! isset( $mycred_protect ) || ! is_object( $mycred_protect ) )
			$mycred_protect = new myCRED_Protect();

		return $mycred_protect;
	}
endif;
?>
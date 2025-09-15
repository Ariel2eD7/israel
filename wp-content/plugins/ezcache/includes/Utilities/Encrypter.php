<?php

namespace Upress\EzCache\Utilities;

use ErrorException;
use RuntimeException;

class Encrypter {
	/*
	 * Encrypter class adapted from Laravel's Illuminate\Encryption\Encrypter
	 */


	/**
	 * The encryption key.
	 *
	 * @var string
	 */
	protected $key;

	protected $method = 'AES-256-CBC';

	function __construct() {
		$this->key = defined( 'AUTH_KEY' ) ? AUTH_KEY : hash( 'sha256', 'g^%Zth*%Km88-DdTrSFMNsNb&S77d4pu' );
	}

	/**
	 * Encrypt the given value.
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	public function encrypt( $value ) {
		$iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( $this->method ) );

		$value = serialize( $value );
		$value = base64_encode( openssl_encrypt( $value, $this->method, $this->key, 0, $iv ) );

		// Once we have the encrypted value we will go ahead base64_encode the input
		// vector and create the MAC for the encrypted value so we can verify its
		// authenticity. Then, we'll JSON encode the data in a "payload" array.
		$mac = $this->hash( $iv = base64_encode( $iv ), $value );

		return base64_encode( json_encode( compact( 'iv', 'value', 'mac' ) ) );
	}

	/**
	 * Decrypt the given value.
	 *
	 * @param string $payload
	 *
	 * @return mixed
	 * @throws ErrorException
	 */
	public function decrypt( $payload ) {
		$payload = $this->getJsonPayload( $payload );

		$value = base64_decode( $payload['value'] );
		$iv    = base64_decode( $payload['iv'] );

		$decrypted = openssl_decrypt( $value, $this->method, $this->key, false, $iv );

		return unserialize( $decrypted );
	}


	/**
	 * Get the JSON array from the given payload.
	 *
	 * @param string $payload
	 *
	 * @return array
	 *
	 * @throws ErrorException
	 */
	protected function getJsonPayload( $payload ) {
		$payload = json_decode( base64_decode( $payload ), true );

		// If the payload is not valid JSON or does not have the proper keys set we will
		// assume it is invalid and bail out of the routine since we will not be able
		// to decrypt the given value. We'll also check the MAC for this encryption.
		if ( ! $payload || $this->invalidPayload( $payload ) ) {
			throw new ErrorException( 'Invalid data.' );
		}

		if ( ! $this->validMac( $payload ) ) {
			throw new ErrorException( 'MAC is invalid.' );
		}

		return $payload;
	}

	/**
	 * Determine if the MAC for the given payload is valid.
	 *
	 * @param array $payload
	 *
	 * @return bool
	 *
	 * @throws RuntimeException
	 */
	protected function validMac( array $payload ) {
		$calcMac = $this->hash( $payload['iv'], $payload['value'] );

		return hash_equals( $calcMac, $payload['mac'] );
	}

	/**
	 * Create a MAC for the given value.
	 *
	 * @param string $iv
	 * @param string $value
	 *
	 * @return string
	 */
	protected function hash( $iv, $value ) {
		return hash_hmac( 'sha256', $iv . $value, $this->key );
	}

	/**
	 * Verify that the encryption payload is valid.
	 *
	 * @param array|mixed $data
	 *
	 * @return bool
	 */
	protected function invalidPayload( $data ) {
		return ! is_array( $data ) || ! isset( $data['iv'] ) || ! isset( $data['value'] ) || ! isset( $data['mac'] );
	}
}

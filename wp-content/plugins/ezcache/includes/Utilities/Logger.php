<?php
namespace Upress\EzCache\Utilities;

class Logger {
	/**
	 * Send an error message to the defined error handling routines if debug is enabled
	 * @param string $message <p>
	 * The error message that should be logged.
	 * </p>
	 * @param int $message_type <p>
	 * Says where the error should go. The possible message types are as
	 * follows:
	 * </p>
	 * <p>
	 * <table>
	 * error_log log types
	 * <tr valign="top">
	 * <td>0</td>
	 * <td>
	 * message is sent to PHP's system logger, using
	 * the Operating System's system logging mechanism or a file, depending
	 * on what the error_log
	 * configuration directive is set to. This is the default option.
	 * </td>
	 * </tr>
	 * <tr valign="top">
	 * <td>1</td>
	 * <td>
	 * message is sent by email to the address in
	 * the destination parameter. This is the only
	 * message type where the fourth parameter,
	 * extra_headers is used.
	 * </td>
	 * </tr>
	 * <tr valign="top">
	 * <td>2</td>
	 * <td>
	 * No longer an option.
	 * </td>
	 * </tr>
	 * <tr valign="top">
	 * <td>3</td>
	 * <td>
	 * message is appended to the file
	 * destination. A newline is not automatically
	 * added to the end of the message string.
	 * </td>
	 * </tr>
	 * <tr valign="top">
	 * <td>4</td>
	 * <td>
	 * message is sent directly to the SAPI logging
	 * handler.
	 * </td>
	 * </tr>
	 * </table>
	 * </p>
	 * @param string|null $destination [optional] <p>
	 * The destination. Its meaning depends on the
	 * message_type parameter as described above.
	 * </p>
	 * @param string|null $additional_headers [optional] <p>
	 * The extra headers. It's used when the message_type
	 * parameter is set to 1.
	 * This message type uses the same internal function as
	 * mail does.
	 * </p>
	 *
	 * @link https://php.net/manual/en/function.error-log.php
	 * @see error_log
	 *
	 * @return bool true on success or false on failure.
	 */
	public static function log($message, $message_type = 0, $destination = null, $additional_headers = null) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return error_log($message, $message_type, $destination, $additional_headers);
		}

		return false;
	}
}

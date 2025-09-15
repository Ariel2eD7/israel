<?php
namespace Upress\EzCache\FileOptimizer;

use MatthiasMullie\Minify\JS;
use Upress\EzCache\Cache;

class JsCombiner extends BaseFileOptimizer {
	static $FILE_TYPE = 'js';

	protected $minifier;
	protected $jquery_url;
	protected $minify_base_path;
	protected $minify_base_url;
	protected $in_location;
	protected $include_inline;
	private $scripts = [];

	function __construct( $cache_dir, $cache_url, $location='all', $include_inline=true ) {
		$this->minifier = new JS();
		$this->jquery_url = $this->get_jquery_url();
		$this->minify_base_path = $cache_dir;
		$this->minify_base_url  = $cache_url;
		$this->in_location  = $location;
		$this->include_inline  = $include_inline;
	}

	/**
	 * Gets jQuery URL if defer JS safe mode is active
	 *
	 * @return bool|string
	 */
	protected function get_jquery_url() {
		global $wp_scripts;

		if ( ! isset( $wp_scripts->registered['jquery-core']->src ) ) {
			return false;
		}

		if ( '' === $this->get_url_component( $wp_scripts->registered['jquery-core']->src, PHP_URL_HOST ) ) {
			return wp_parse_url( site_url( $wp_scripts->registered['jquery-core']->src ), PHP_URL_PATH );
		}

		return $wp_scripts->registered['jquery-core']->src;
	}

	/**
	 * Minifies and combines JavaScripts into one
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	function optimize( $html ) {
		$html_buff = $html;
		if ( 'head' === $this->in_location ) {
			$html_buff = substr( $html_buff, 0, strpos( $html_buff, '</head>' ) );
		} else if ( 'body' === $this->in_location ) {
			$html_buff = substr( $html_buff, strpos( $html_buff, '</head>' ) );
		}
		$scripts = $this->find( '<script.*<\/script>', $html_buff );

		if ( ! $scripts ) {
			return $html;
		}

		// Parses found nodes to keep only the ones to combine
		$combine_scripts = $this->parse( $scripts );

		if ( empty( $combine_scripts ) ) {
			return $html;
		}

		// Gets content for each script either from inline or from src
		$content = $this->get_content();

		if ( empty( $content ) ) {
			return $html;
		}

		// Creates the minify URL if the minification is successful
		$minify_url = $this->combine( $content );

		if ( ! $minify_url ) {
			return $html;
		}

		$minify_script_tag = '<script src="' . esc_url( $minify_url ) . '" data-minify="1"></script>';

		if ( 'head' === $this->in_location ) {
			$html = str_replace( '</head>', "{$minify_script_tag}\n</head>", $html );
		} else {
			$html = str_replace( '</body>', "{$minify_script_tag}\n</body>", $html );
		}

		$combine_scripts = array_filter( $combine_scripts );
		foreach ( $combine_scripts as $script ) {
			$html = str_replace( $script[0], '', $html );
		}

		return $html;
	}

	/**
	 * Parses found nodes to keep only the ones to combine
	 *
	 * @param array $scripts scripts corresponding to JS file or content.
	 *
	 * @return array
	 */
	function parse( $scripts ) {
		$scripts = array_map( function( $script ) {
			preg_match( '/<script\s+([^>]+[\s\'"])?src\s*=\s*[\'"]\s*?([^\'"]+\.js(?:\?[^\'"]*)?)\s*?[\'"]([^>]+)?\/?>/Umsi', $script[0], $matches );

			if ( isset( $matches[2] ) ) {
				if ( preg_match( '/^\/\//ui', $matches[2] ) ) {
					$matches[2] = 'http' . (is_ssl() ? 's' : '') . ':' . $matches[2];
				}

				if ( $this->is_minify_excluded_file( $matches ) ) {
					return [];
				}

				if ( $this->is_external_file( $matches[2] ) ) {
					foreach ( $this->get_excluded_external_file_path() as $excluded_file ) {
						if ( false !== strpos( $matches[2], $excluded_file ) ) {
							return [];
						}
					}

					$this->scripts[] = [
						'type'    => 'url',
						'content' => $matches[2],
					];

					return $script;
				}

				if ( $this->jquery_url && false !== strpos( $matches[2], $this->jquery_url ) ) {
					return [];
				}

				$file_path = $this->get_file_path( $matches[2] );

				if ( ! $file_path ) {
					return [];
				}

				$this->scripts[] = [
					'type'    => 'file',
					'content' => $file_path,
				];
			} elseif ( ! isset( $matches[2] ) && $this->include_inline ) {
				preg_match( '/<script\b([^>]*)>(?:\/\*\s*<!\[CDATA\[\s*\*\/)?\s*([\s\S]*?)\s*(?:\/\*\s*\]\]>\s*\*\/)?<\/script>/msi', $script[0], $matches_inline );

				if ( strpos( $matches_inline[1], 'type' ) !== false && ! preg_match( '/type\s*=\s*["\']?(?:text|application)\/(?:(?:x\-)?javascript|ecmascript)["\']?/i', $matches_inline[1] ) ) {
					return [];
				}

				if ( false !== strpos( $matches_inline[1], 'src=' ) ) {
					return [];
				}

				$test_localize_script = str_replace( array( "\r", "\n" ), '', $matches_inline[2] );

				if ( in_array( $test_localize_script, $this->get_localized_scripts(), true ) ) {
					return [];
				}

				foreach ( $this->get_excluded_inline_content() as $excluded_content ) {
					if ( false !== strpos( $matches_inline[2], $excluded_content ) ) {
						return [];
					}
				}

				$this->scripts[] = [
					'type'    => 'inline',
					'content' => $matches_inline[2],
				];
			}

			return $script;
		}, $scripts );

		return array_filter( $scripts );
	}

	/**
	 * Gets content for each script either from inline or from src
	 *
	 * @return string
	 */
	protected function get_content() {
		$content = '';

		foreach ( $this->scripts as $script ) {
			if ( 'file' === $script['type'] ) {
				$file_content = file_get_contents( $script['content'] );
				$content     .= $file_content;

				$this->minifier->add( $file_content );
			} elseif ( 'url' === $script['type'] ) {
				$file_content = file_get_contents( esc_url( $script['content'] ) );
				$content     .= $file_content;

				$this->minifier->add( $file_content );
			} elseif ( 'inline' === $script['type'] ) {
				$inline_js = rtrim( $script['content'], ";\n\t\r" ) . ';';
				$content  .= $inline_js;

				$this->minifier->add( $inline_js );
			}
		}

		return $content;
	}

	/**
	 * Creates the minify URL if the minification is successful
	 *
	 * @param string $content Content to minify & combine.

	 * @return string|bool The minify URL if successful, false otherwise
	 */
	protected function combine( $content ) {
		if ( empty( $content ) ) {
			return false;
		}

		$filename      = md5( $content ) . '.js';
		$minified_file = $this->minify_base_path . $filename;

		if ( file_exists( $minified_file ) ) {
			touch( $minified_file );
		} else {
			$minified_content = $this->minifier->minify();

			if ( ! $minified_content ) {
				return false;
			}

			wp_mkdir_p( dirname( $minified_file ) );
			$minify_filepath = file_put_contents( $minified_file, $minified_content );

			if ( ! $minify_filepath ) {
				return false;
			}
		}

		return $this->minify_base_url . $filename;
	}

	/**
	 * Patterns in content excluded from being combined
	 *
	 * @return array
	 */
	protected function get_excluded_inline_content() {
		return [
			'document.write',
			'google_ad',
			'edToolbar',
			'gtag',
			'_gaq.push',
			'_gaLt',
			'GoogleAnalyticsObject',
			'syntaxhighlighter',
			'adsbygoogle',
			'ci_cap_',
			'_stq',
			'nonce',
			'post_id',
			'LogHuman',
			'idcomments_acct',
			'ch_client',
			'sc_online_t',
			'_stq',
			'bannersnack_embed',
			'vtn_player_type',
			'ven_video_key',
			'ANS_customer_id',
			'tdBlock',
			'tdLocalCache',
			'"url":',
			'td_live_css_uid',
			'tdAjaxCount',
			'lazyLoadOptions',
			'adthrive',
			'loadCSS',
			'google_tag_params',
			'clicky_custom',
			'clicky_site_ids',
			'NSLPopupCenter',
			'_paq',
			'gtm',
			'dataLayer',
			'RecaptchaLoad',
			'recaptcha',
			'WPCOM_sharing_counts',
			'jetpack_remote_comment',
			'scrapeazon',
			'subscribe-field',
			'contextly',
		];
	}

	/**
	 * Patterns in URL excluded from being combined
	 *
	 * @return array
	 */
	protected function get_excluded_external_file_path() {
		return [
			'html5.js',
			'show_ads.js',
			'histats.com/js',
			'ws.amazon.com/widgets',
			'/ads/',
			'intensedebate.com',
			'scripts.chitika.net/',
			'jotform.com/',
			'gist.github.com',
			'forms.aweber.com',
			'video.unrulymedia.com',
			'stats.wp.com',
			'stats.wordpress.com',
			'widget.rafflecopter.com',
			'widget-prime.rafflecopter.com',
			'releases.flowplayer.org',
			'c.ad6media.fr',
			'cdn.stickyadstv.com',
			'www.smava.de',
			'contextual.media.net',
			'app.getresponse.com',
			'adserver.reklamstore.com',
			's0.wp.com',
			'wprp.zemanta.com',
			'files.bannersnack.com',
			'smarticon.geotrust.com',
			'js.gleam.io',
			'ir-na.amazon-adsystem.com',
			'web.ventunotech.com',
			'verify.authorize.net',
			'ads.themoneytizer.com',
			'embed.finanzcheck.de',
			'imagesrv.adition.com',
			'js.juicyads.com',
			'form.jotformeu.com',
			'speakerdeck.com',
			'content.jwplatform.com',
			'ads.investingchannel.com',
			'app.ecwid.com',
			'www.industriejobs.de',
			's.gravatar.com',
			'googlesyndication.com',
			'a.optmstr.com',
			'a.optmnstr.com',
			'adthrive.com',
			'mediavine.com',
			'js.hsforms.net',
			'googleadservices.com',
			'f.convertkit.com',
			'recaptcha/api.js',
		];
	}

	/**
	 * Gets all localized scripts data to exclude them from combine.
	 *
	 * @return array
	 */
	protected function get_localized_scripts() {
		static $localized_scripts;

		if ( isset( $localized_scripts ) ) {
			return $localized_scripts;
		}

		$localized_scripts = [];

		foreach ( array_unique( wp_scripts()->queue ) as $item ) {
			$data = wp_scripts()->print_extra_script( $item, false );

			if ( empty( $data ) ) {
				continue;
			}

			$localized_scripts[] = '/* <![CDATA[ */' . $data . '/* ]]> */';
		}

		return $localized_scripts;
	}

}

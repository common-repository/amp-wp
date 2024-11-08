<?php
/**
 * Component for iframe
 *
 * @since 1.0.0
 */
class Amp_WP_IFrame_Component implements Amp_WP_Component_Interface {

	/**
	 * Store list of services that exclusively support
	 *
	 * @since 1.0.0
	 * @var array
	 */
	public $support_sites = array(
		'youtube',
		'twitter',
		'facebook',
		'vimeo',
		'soundcloud',
		'vine',
		'instagram',
		'instagr',
	);

	/**
	 * @see   Amp_WP_Component_Base::$enable_enqueue_scripts
	 *
	 * @var     bool
	 * @since       1.0.0
	 */
	public $enable_enqueue_scripts = false;

	/**
	 * Contract implementation
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function config() {
		return array(
			'scripts' => array(
				'amp-iframe' => 'https://cdn.ampproject.org/v0/amp-iframe-0.1.js',
			),
		);
	}

	/**
	 * Added hooks for 'embed_oembed_html' and 'wp_video_shortcode'
	 *
	 * @return void
	 */
	public function head() {
		add_filter( 'embed_oembed_html', array( $this, 'amp_embedded' ), 8, 2 );
		add_action( 'wp_video_shortcode', array( $this, 'wp_video_shortcode' ), 8, 2 );
	}

	/**
	 * Change Popular Embedded Websites to AMP Version
	 *
	 * @param string $html
	 * @param string $url
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function amp_embedded( $html, $url, $options = array() ) {
		if ( ! preg_match( '#https?://(?:www|m)?\.?([^\.]+)#', $url, $matched ) ) {
			return $html;
		}
		$provider = $matched[1];
		if ( ! in_array( $provider, $this->support_sites ) ) {
			return $html;
		}
		switch ( $provider ) {
			case 'youtube':
				$video_id = self::extract_youtube_video_id( $url );
				if ( $video_id ) {
					$dim = $this->get_iframe_dimension( $html, 'height', 'width', $options );

					// $dim[0] has Height whereas $dim[1] has width;
					return $this->amp_youtube_html( $video_id, '270', '480' );
				}
				break;
			case 'twitter':
				if ( preg_match( '#https?://(?:www\.)?twitter\.com/\w{1,15}/status(?:es)?/(.*)#i', $url, $matched ) ) {
					$tweet_id = array_pop( $matched );
					$width    = $this->get_iframe_dimension( $html, false, 'data-width', $options );
					return $this->amp_twitter_html( $tweet_id, $width );
				}
				break;
			case 'facebook':
				if ( preg_match( '#https?://www\.facebook\.com/.*/posts/.*#i', $url ) ) {
					return $this->amp_facebook_html( $url );
				}
				if ( preg_match( '#https?://www\.facebook\.com/.*/videos/.*#i', $url ) ) {
					return $this->amp_facebook_html( $url, true );
				}
				break;
			case 'vimeo':
				if ( preg_match( '#https?://(?:.+\.)?vimeo\.com/.*?(\d+)$#i', $url, $matched ) ) {
					$video_id = array_pop( $matched );
					$dim      = $this->get_iframe_dimension( $html, 'height', 'width', $options );
					return $this->amp_vimeo_html( $video_id, $dim[0], $dim[1] );
				}
				break;
			case 'soundcloud':
				if ( preg_match( '#https?://(www\.)?soundcloud\.com/.*#i', $url, $matched ) ) {
					$track_id = $this->get_soundcloud_track_id( $html );
					if ( $track_id ) {
						$dim = $this->get_iframe_dimension( $html, 'height', false, $options );
						return $this->amp_soundcloud_html( $track_id, $dim[0] );
					}
				}
				break;
			case 'vine':
				if ( preg_match( '#https?://vine\.co/v/(.*)#i', $url, $matched ) ) {
					$vine_id = $matched[1];
					$dim     = $this->get_iframe_dimension( $html, 'height', 'width', $options );
					return $this->amp_vine_html( $vine_id, $dim[0], $dim[1] );
				}
				break;
			case 'instagr':
			case 'instagram':
				if ( preg_match( '#https?://(?:www\.)?instagr(?:\.am|am\.com)/p/([^\/]+)#i', $url, $matched ) ) {
					$shortcode = $matched[1];
					return $this->amp_instagram_html( $shortcode );
				}
				break;
		}
		return $html;
	}

	/**
	 * Get specific html attribute from html string
	 *
	 * @param string $html
	 * @param string $attr
	 * @param string $tag
	 *
	 * @return bool|string string attribute value on success or false on failure.
	 * @since 1.0.0
	 */
	public function get_html_attr( $html, $attr, $tag = 'iframe' ) {
		if ( preg_match(
			"'<$tag\s.*?$attr\s*=\s*
                                        ([\"\'])?
                                        (?(1) (.*?)\\1 | ([^\s\>]+))
                                        'isx",
			$html,
			$match
		) ) {
			return array_pop( $match );
		}
		return false;
	}

	/**
	 * Generate iFrame Dimensions
	 *
	 * @param   string $string
	 * @param   string $height_attr
	 * @param   string $width_attr
	 * @param   array  $defaults
	 *
	 * @return  array
	 * @since       1.0.0
	 */
	public function get_iframe_dimension( $string, $height_attr = 'height', $width_attr = 'width', $defaults = array() ) {

		$width = ! empty( $defaults['width'] ) ? $defaults['width'] : 480;

		if ( $width_attr ) {
			$_width = $this->get_html_attr( $string, $width_attr );
			if ( $_width ) {
				$width = $_width;
			}
		}

		$height = ! empty( $defaults['height'] ) ? $defaults['height'] : 480;
		if ( $height_attr ) {
			$_height = $this->get_html_attr( $string, $height_attr );
			if ( $_height ) {
				$height = $_height;
			}
		}
		return array( $height, $width );
	}

	/**
	 * Generate amp-twitter html
	 *
	 * @param string $tweet_id
	 * @param int    $width
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function amp_twitter_html( $tweet_id, $width ) {
		amp_wp_enqueue_script( 'amp-twitter', 'https://cdn.ampproject.org/v0/amp-twitter-0.1.js' );
		return sprintf( '<amp-twitter width="%d" height="%d" layout="responsive" data-tweetid="%s"></amp-twitter>', $width, $width, $tweet_id );
	}

	/**
	 * Generate amp-facebook html
	 *
	 * @param string $url
	 * @param bool   $is_video Boolean variable.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function amp_facebook_html( $url, $is_video = false ) {
		amp_wp_enqueue_script( 'amp-facebook', 'https://cdn.ampproject.org/v0/amp-facebook-0.1.js' );
		$attr = '';
		if ( $is_video ) {
			$attr .= ' data-embed-as="video"'; }
		return sprintf( '<amp-facebook %s width="700" height="400" layout="responsive" data-href="%s">', $attr, esc_url( $url ) );
	}

	/**
	 * Generate amp-youtube html
	 *
	 * @param string $video_id
	 * @param int    $height
	 * @param int    $width
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function amp_youtube_html( $video_id, $height = 270, $width = 480 ) {
		amp_wp_enqueue_script( 'amp-youtube', 'https://cdn.ampproject.org/v0/amp-youtube-0.1.js' );
		return sprintf( '<amp-youtube width="%d" height="%d" layout="responsive" data-videoid="%s"></amp-youtube>', $width, $height, $video_id );
	}

	/**
	 * Generate amp-vimeo html
	 *
	 * @param string $video_id
	 * @param int    $height
	 * @param int    $width
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function amp_vimeo_html( $video_id, $height = 270, $width = 480 ) {
		amp_wp_enqueue_script( 'amp-vimeo', 'https://cdn.ampproject.org/v0/amp-vimeo-0.1.js' );
		return sprintf( '<amp-vimeo data-videoid="%s" layout="responsive" width="%d" height="%d"></amp-vimeo>', esc_attr( $video_id ), $width, $height );
	}

	/**
	 * Generate amp-soundcloud html
	 *
	 * @param string $track_id
	 * @param int    $height
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function amp_soundcloud_html( $track_id, $height = 270 ) {
		amp_wp_enqueue_script( 'amp-soundcloud', 'https://cdn.ampproject.org/v0/amp-soundcloud-0.1.js' );
		return sprintf( '<amp-soundcloud height="%d" layout="fixed-height" data-trackid="%s" data-visual="true"></amp-soundcloud>', $height, esc_attr( $track_id ) );
	}

	/**
	 * Generate amp-soundcloud html
	 *
	 * @param string $vine_id
	 * @param int    $height
	 * @param int    $width
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function amp_vine_html( $vine_id, $height = 1, $width = 1 ) {
		amp_wp_enqueue_script( 'amp-vine', 'https://cdn.ampproject.org/v0/amp-vine-0.1.js' );
		return sprintf( '<amp-vine width="%d" height="%d" layout="responsive" data-vineid="%s"></amp-vine>', $width, $height, esc_attr( $vine_id ) );
	}

	/**
	 * Generate amp-instagram html
	 *
	 * @param string $shortcode
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function amp_instagram_html( $shortcode ) {
		amp_wp_enqueue_script( 'amp-instagram', 'https://cdn.ampproject.org/v0/amp-instagram-0.1.js' );
		return sprintf( '<amp-instagram data-shortcode="%s" width="1" height="1" layout="responsive"></amp-instagram>', esc_attr( $shortcode ) );
	}

	/**
	 * Retrieve soundcloud track-id from embed html code
	 *
	 * @param string $html
	 *
	 * @since 1.0.0
	 * @return string|bool  track id on success or false on failure.
	 */
	public function get_soundcloud_track_id( $html ) {
		parse_str( urldecode( $this->get_html_attr( $html, 'src' ) ), $vars );
		if ( ! empty( $vars['url'] ) ) {
			if ( preg_match( '#soundcloud.com/tracks/(.+)$#i', $vars['url'], $matched ) ) {
				return $matched[1];
			}
		}
		return false;
	}

	/**
	 * Transform <iframe> tags to <img-iframe> tags
	 *
	 * @param   Amp_WP_Html_Util $instance
	 * @since       1.0.0
	 *
	 * @return  Amp_WP_Html_Util
	 */
	public function transform( Amp_WP_Html_Util $instance ) {
		$elements = $instance->getElementsByTagName( 'iframe' );

		/**
		 * @var DOMElement $element
		 */
		$nodes_count = $elements->length;
		if ( $nodes_count ) {
			$this->enable_enqueue_scripts = true;
			for ( $i = $nodes_count - 1; $i >= 0; $i -- ) {
				if ( ! $element = $elements->item( $i ) ) {
					continue;
				}

				$this->sanitize( $element );
				$attributes = $instance->filter_attributes( $instance->get_node_attributes( $element ) );
				$attributes = $this->filter_attributes( $attributes );
				if ( ! empty( $attributes['src'] ) && ( $embedded = $this->amp_embedded( '', $attributes['src'], $attributes ) ) ) {
					$instance->set_outer_HTML( $element, $embedded );
				} else {
					$instance->replace_node( $element, 'amp-iframe', $attributes );
				}
			}
		}
		return $instance;
	}

	/**
	 * This is our workaround to enforce max sizing with layout=responsive.
	 *
	 * We want elements to not grow beyond their width and shrink to fill the screen on viewports smaller than their
	 * width.
	 *
	 * See https://github.com/ampproject/amphtml/issues/1280#issuecomment-171533526
	 * See https://github.com/Automattic/amp-wp/issues/101
	 *
	 * @copyright credit goes to automattic amp - github.com/Automattic/amp-wp
	 *
	 * @since     1.0.0
	 */
	public function enforce_sizes_attribute( $attributes ) {
		if ( ! isset( $attributes['width'], $attributes['height'] ) ) {
			return $attributes;
		}
		$max_width  = $attributes['width'];
		$_max_width = amp_wp_get_container_width();
		if ( ( $_max_width ) && $max_width > $_max_width ) {
			$max_width = $_max_width;
		}
		$attributes['sizes'] = sprintf( '(min-width: %1$dpx) %1$dpx, 100vw', absint( $max_width ) );
		return $attributes;
	}

	/**
	 * Filter iFrame attributes
	 *
	 * @param array $attributes
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	protected function filter_attributes( $attributes ) {
		$results = array();
		foreach ( $attributes as $key => $value ) {
			switch ( $key ) {
				case 'frameborder':
					if ( 'no' === $value ) {
						$value = '0';
					} elseif ( '0' !== $value && '1' !== $value ) {
						$value = '0';
					}
					if ( '0' !== $value ) {
						$results[ $key ] = $value; }
					break;

				case 'allowfullscreen':
				case 'allowtransparency':
				case 'class':
				case 'sandbox':
				case 'src':
				case 'sizes':
					if ( '0' !== $value ) {
						$results[ $key ] = $value; }
					break;
			}
		}

		if ( ! isset( $results['sandbox'] ) ) {
			$results['sandbox'] = 'allow-scripts allow-same-origin';
		}

		// Height is required.
		if ( empty( $results['height'] ) && isset( $attributes['src'] ) ) {
			if ( ! empty( $attributes['amp-height'] ) ) {
				$results['height'] = $attributes['amp-height'];
			} elseif ( ! empty( $attributes['height'] ) ) {
				$results['height'] = $attributes['height'];
			} else {
				$results    ['height'] = $this->get_frame_height( $attributes['src'] );
			}
		}
		if ( isset( $attributes['width'] ) ) {
			$results['width'] = $attributes['width'];
		}

		return $results;
	}

	/**
	 * Returns appropriate frame height
	 *
	 * @param $url
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function get_frame_height( $url ) {

		// Default height.
		$height = 400;
		if ( preg_match( '#^https?://.*?\.soundcloud\.com#i', $url ) ) {
			$height = 156;
		}
		return $height;
	}

	/**
	 * Builds the Video Shortcode Output.
	 *
	 * This implements the functionality of the Video Shortcode for displaying
	 * WordPress mp4s in a post.
	 *
	 * @since       1.0.0
	 *
	 * @global  int $content_width
	 * @staticvar   int $instance
	 *
	 * @param   string $output Shortcode content.
	 *
	 * @param array  $attr {
	 *     Attributes of the shortcode.
	 *     @type string $src      URL to the source of the video file. Default empty.
	 * }
	 *
	 * @return  string|void HTML content to display video.
	 */
	public function wp_video_shortcode( $output, $attr ) {
		if ( ! empty( $attr['src'] ) ) {
			$url     = trim( $attr['src'] );
			$_output = $this->amp_embedded( '', $url );
			if ( $_output ) {
				return $_output;
			}
			if ( substr( $url, 0, 8 ) === 'https://' ) {
				$width  = $this->get_html_attr( $output, 'width', 'video' );
				$height = $this->get_html_attr( $output, 'height', 'video' );
				amp_wp_enqueue_script( 'amp-video', 'https://cdn.ampproject.org/v0/amp-video-0.1.js' );
				return sprintf( '<amp-video width="%s" height="%s" src="%s" layout="responsive" controls></amp-video>', $width, $height, $url );
			}
		}
		return $output;
	}

	/**
	 * Grab YouTube Video ID from Given URL.
	 *
	 * @param   string $url
	 *
	 * @since       1.2.1
	 * @return  string  video id on success or empty string on failure.
	 */
	public static function extract_youtube_video_id( $url ) {
		$video_id = '';
		if ( preg_match( '#https?://(?:(?:m|www)\.)?youtube\.com/watch\?(.*)#i', $url, $matched ) ) {
			parse_str( $matched[1], $vars );
			if ( ! empty( $vars['v'] ) ) {
				$video_id = $vars['v'];
			}
		} elseif ( preg_match( '#https?://(?:(?:m|www)\.)?youtube\.com/embed/([^\/\?\&]+)#i', $url, $matched ) ) {
			$video_id = $matched[1];
		} elseif ( preg_match( '#https?://(?:(?:m|www)\.)?youtu\.be/([^\/\?\&]+)#i', $url, $matched ) ) {
			$video_id = $matched[1];
		}
		return $video_id;
	}

	/**
	 * Sanitize iframe element.
	 *
	 * @param DOMElement $element
	 *
	 * @since 1.5.13
	 */
	protected function sanitize( $element ) {
		// Fix: remove aparat iframe extra tags
		if ( preg_match( '/aparat/i', $element->parentNode->getAttribute( 'class' ) ) && $element->previousSibling ) {
			if ( in_array( $element->previousSibling->tagName, array( 'div', 'span' ), true ) ) {
				$element->parentNode->removeChild( $element->previousSibling );
			}
		}
	}
}

// Register component class.
amp_wp_register_component( 'Amp_WP_IFrame_Component' );

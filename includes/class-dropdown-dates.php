<?php
/**
 * WC Bookings Dropdown Dates - Main Class
 *
 * @package WC_Bookings_Dropdown
 */

defined( 'ABSPATH' ) || exit;

class WC_Bookings_Dropdown_Dates {
	
	/**
	 * Single instance of the class
	 *
	 * @var WC_Bookings_Dropdown_Dates
	 */
	private static $instance = null;
	
	/**
	 * Track if dates have been built to avoid duplicates
	 *
	 * @var bool
	 */
	private $dates_built = false;
	
	/**
	 * Get single instance
	 *
	 * @return WC_Bookings_Dropdown_Dates
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}
	
	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_filter( 'booking_form_fields', array( $this, 'modify_booking_form_fields' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_footer', array( $this, 'add_inline_scripts' ) );
	}
	
	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts() {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		
		global $post;
		$product = wc_get_product( $post->ID );
		
		if ( ! $product || ! is_wc_booking_product( $product ) ) {
			return;
		}
		
		wp_enqueue_style(
			'wc-bookings-dropdown',
			WC_BOOKINGS_DROPDOWN_PLUGIN_URL . 'assets/css/dropdown.css',
			array(),
			WC_BOOKINGS_DROPDOWN_VERSION
		);
		
		wp_enqueue_script(
			'wc-bookings-dropdown',
			WC_BOOKINGS_DROPDOWN_PLUGIN_URL . 'assets/js/dropdown.js',
			array( 'jquery' ),
			WC_BOOKINGS_DROPDOWN_VERSION,
			true
		);
		
		wp_localize_script( 'wc-bookings-dropdown', 'WooBookingsDropdown', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'secure'   => wp_create_nonce( 'woo-bookings-dropdown-refreshing-dates' ),
		) );
	}
	
	/**
	 * Modify booking form fields to use dropdown
	 *
	 * @param array $fields Booking form fields
	 * @param WC_Booking_Form $booking_form The booking form object
	 * @return array Modified fields
	 */
	public function modify_booking_form_fields( $fields, $booking_form = null ) {
		if ( is_admin() || $this->dates_built ) {
			return $fields;
		}
		
		// Get product from booking form object
		$product = null;
		if ( $booking_form && is_object( $booking_form ) && method_exists( $booking_form, 'get_product' ) ) {
			$product = $booking_form->get_product();
		}
		
		// Fallback to global if booking form didn't provide product
		if ( ! $product ) {
			global $product, $post;
			
			if ( ! $product || ! is_wc_booking_product( $product ) ) {
				if ( $post ) {
					$product = wc_get_product( $post->ID );
				}
			}
		}
		
		if ( ! $product || ! is_wc_booking_product( $product ) ) {
			return $fields;
		}
		
		$new_fields = array();
		$selected_resource = 0;
		$reset_options_index = false;
		$i = 0;
		
		foreach ( $fields as $field ) {
			$new_fields[ $i ] = $field;
			
			if ( 'select' === $field['type'] && ! isset( $field['availability_rules'] ) ) {
				$field['availability_rules'] = $product->get_availability_rules();
			}
			
			if ( 'select' === $field['type'] && isset( $field['options'] ) ) {
				$resource_keys = array_keys( $field['options'] );
				$selected_resource = reset( $resource_keys );
				
				if ( false !== $reset_options_index && isset( $field['availability_rules'][ $selected_resource ] ) ) {
					$availability_rules = $selected_resource < 1 || ! isset( $field['availability_rules'][ $selected_resource ] ) 
						? $field['availability_rules'] 
						: $field['availability_rules'][ $selected_resource ];
					
					$new_fields[ $reset_options_index ]['options'] = $this->build_date_options( 
						$product, 
						$availability_rules, 
						$field 
					);
				}
			}
			
			// Replace date picker with dropdown
			if ( 'date-picker' === $field['type'] && false === $this->dates_built ) {
				if ( ! isset( $field['availability_rules'] ) ) {
					$field['availability_rules'] = $product->get_availability_rules();
				}
				
				$max = isset( $field['max_date'] ) ? $field['max_date'] : $product->get_max_date();
				$now = strtotime( 'midnight', current_time( 'timestamp' ) );
				$max_date = strtotime( "+{$max['value']} {$max['unit']}", $now );
				
				$availability_rules = $selected_resource < 1 || ! isset( $field['availability_rules'][ $selected_resource ] ) 
					? $field['availability_rules'] 
					: $field['availability_rules'][ $selected_resource ];
				
				$available_dates = $this->build_date_options( 
					$product, 
					$availability_rules, 
					$field, 
					$max_date 
				);
				
				if ( false === $available_dates || empty( $available_dates ) ) {
					return $fields;
				}
				
				// Hide original date picker
				$new_fields[ $i ]['class'] = array( 'picker-hidden' );
				
				// Add dropdown field
				$i++;
				$new_fields[ $i ] = $field;
				$new_fields[ $i ]['type'] = 'select';
				$new_fields[ $i ]['name'] = 'wc_bookings_field_start_date';
				$new_fields[ $i ]['options'] = $available_dates;
				$new_fields[ $i ]['class'] = array( 'picker-chooser' );
				
				if ( 0 === $selected_resource ) {
					$reset_options_index = $i;
				}
				
				$this->dates_built = true;
			}
			
			$i++;
		}
		
		return $new_fields;
	}
	
	/**
	 * Build date dropdown options
	 *
	 * @param WC_Product $product Product object
	 * @param array $rules Availability rules
	 * @param array $field Field configuration
	 * @param int|null $max_date Maximum date timestamp
	 * @return array|false Date options or false if none available
	 */
	private function build_date_options( $product, $rules, $field, $max_date = null ) {
		global $post;
		
		$dates = array();
		$course_id = $post->ID;
		$resource_id = isset( $field['availability_rules'][0]['resource_id'] ) 
			? $field['availability_rules'][0]['resource_id'] 
			: 0;
		
		if ( null === $max_date ) {
			$max = $product->get_max_date();
			$now = strtotime( 'midnight', current_time( 'timestamp' ) );
			$max_date = strtotime( "+{$max['value']} {$max['unit']}", $now );
		}
		
		$now_timestamp = strtotime( 'midnight', current_time( 'timestamp' ) );
		
		foreach ( $rules as $dateset ) {
			$years = false;
			
			if ( isset( $dateset['type'] ) && 'custom' === $dateset['type'] ) {
				$years = isset( $dateset['range'] ) ? $dateset['range'] : false;
			}
			
			if ( empty( $years ) ) {
				continue;
			}
			
			foreach ( $years as $year => $months ) {
				foreach ( $months as $month => $days ) {
					foreach ( $days as $day => $avail ) {
						if ( ! $avail ) {
							continue;
						}
						
						$dtime = strtotime( "{$year}-{$month}-{$day}" );
						$js_date = date( 'Y-n-j', $dtime );
						
						if ( $dtime <= $now_timestamp || $dtime > $max_date - 1 ) {
							continue;
						}
						
						if ( isset( $field['fully_booked_days'][ $js_date ] ) ) {
							continue;
						}
						
						$remaining_places = $this->get_remaining_places(
							$course_id,
							$resource_id,
							$dtime
						);
						
						if ( $remaining_places <= 0 ) {
							continue;
						}
						
						$dates[ $dtime ] = sprintf(
							'%s%s',
							date_i18n( 'F jS, Y', $dtime ),
							$remaining_places < 999 ? sprintf( ' (%d %s remaining)', $remaining_places, _n( 'place', 'places', $remaining_places, 'wc-bookings-dropdown' ) ) : ''
						);
					}
				}
			}
		}
		
		if ( empty( $dates ) ) {
			return false;
		}
		
		ksort( $dates );
		
		$formatted_dates = array();
		foreach ( $dates as $timestamp => $label ) {
			$formatted_dates[ date( 'Y-m-d', $timestamp ) ] = $label;
		}
		
		return array( '' => __( 'Please Select', 'wc-bookings-dropdown' ) ) + $formatted_dates;
	}
	
	/**
	 * Calculate remaining places for a booking
	 *
	 * @param int $product_id Product ID
	 * @param int $resource_id Resource ID
	 * @param int $start_date Start date timestamp
	 * @return int Number of remaining places
	 */
	private function get_remaining_places( $product_id, $resource_id, $start_date ) {
		$product = get_wc_product_booking( wc_get_product( $product_id ) );
		
		if ( ! $product ) {
			return 0;
		}
		
		$has_persons = $product->has_persons();
		
		if ( ! $has_persons ) {
			return 999;
		}
		
		$duration = $product->get_duration();
		$duration_unit = $product->get_duration_unit();
		$end_date = strtotime( "+{$duration} {$duration_unit}", $start_date );
		
		try {
			if ( method_exists( $product, 'get_available_bookings' ) ) {
				$available = $product->get_available_bookings( $start_date, $end_date, $resource_id, 1 );
				
				if ( is_int( $available ) && $available > 0 ) {
					return $available;
				}
			}
			
			if ( method_exists( $product, 'get_qty' ) ) {
				$qty = $product->get_qty();
				
				if ( $qty > 0 ) {
					$data_store = WC_Data_Store::load( 'booking' );
					$existing_bookings = $data_store->get_bookings_in_date_range( 
						$start_date, 
						$end_date, 
						$product_id, 
						$resource_id 
					);
					
					$booked = count( $existing_bookings );
					$remaining = $qty - $booked;
					
					return max( 0, $remaining );
				}
			}
			
			return 999;
			
		} catch ( Exception $e ) {
			return 999;
		}
	}
	
	/**
	 * Add inline scripts and styles
	 */
	public function add_inline_scripts() {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		
		global $post;
		$product = wc_get_product( $post->ID );
		
		if ( ! $product || ! is_wc_booking_product( $product ) ) {
			return;
		}
		?>
		<script>
			jQuery(function($) {
				if ($('.picker-chooser').length && $('.wc-bookings-date-picker-date-fields').length) {
					$('.picker-chooser').insertBefore('.wc-bookings-date-picker-date-fields');
				}
				
				$('select#wc_bookings_field_start_date').on('change', function() {
					var selectedDate = $(this).val();
					
					if (!selectedDate) return;
					
					var dateParts = selectedDate.split('-');
					
					$('input[name*="wc_bookings_field_start_date_year"]').val(dateParts[0]);
					$('input[name*="wc_bookings_field_start_date_month"]').val(dateParts[1]);
					$('input[name*="wc_bookings_field_start_date_day"]').val(dateParts[2]);
				});
			});
		</script>
		<?php
	}
}
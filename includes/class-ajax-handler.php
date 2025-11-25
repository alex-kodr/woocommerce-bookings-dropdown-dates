<?php
/**
 * WC Bookings Dropdown Dates - AJAX Handler
 *
 * @package WC_Bookings_Dropdown
 */

defined( 'ABSPATH' ) || exit;

class WC_Bookings_Dropdown_Ajax_Handler {
	
	/**
	 * Single instance of the class
	 *
	 * @var WC_Bookings_Dropdown_Ajax_Handler
	 */
	private static $instance = null;
	
	/**
	 * Get single instance
	 *
	 * @return WC_Bookings_Dropdown_Ajax_Handler
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
		add_action( 'wp_ajax_wswp_refresh_dates', array( $this, 'refresh_dates' ) );
		add_action( 'wp_ajax_nopriv_wswp_refresh_dates', array( $this, 'refresh_dates' ) );
	}
	
	/**
	 * AJAX handler for refreshing dates when resource changes
	 */
	public function refresh_dates() {
		check_ajax_referer( 'woo-bookings-dropdown-refreshing-dates', 'security' );
		
		if ( ! isset( $_REQUEST['product_id'] ) ) {
			wp_send_json( array( 'success' => false ) );
			return;
		}
		
		$product_id = absint( $_REQUEST['product_id'] );
		$resource_id = isset( $_REQUEST['resource_id'] ) ? absint( $_REQUEST['resource_id'] ) : 0;
		
		$product = wc_get_product( $product_id );
		
		if ( ! $product || ! is_wc_booking_product( $product ) ) {
			wp_send_json( array( 'success' => false ) );
			return;
		}
		
		$booking_form = new WC_Booking_Form( $product );
		$picker = $this->get_picker( $product, $booking_form );
		
		if ( ! $picker ) {
			wp_send_json( array( 'success' => false ) );
			return;
		}
		
		$field = $picker->get_args();
		$rules = $product->get_availability_rules( $resource_id );
		
		$max = $product->get_max_date();
		$now = strtotime( 'midnight', current_time( 'timestamp' ) );
		$max_date = strtotime( "+{$max['value']} {$max['unit']}", $now );
		
		$dates = $this->build_options( $rules, $field, $max_date, $product_id, $resource_id );
		
		if ( ! empty( $dates ) ) {
			wp_send_json( array(
				'success' => true,
				'dates'   => $dates,
			) );
		} else {
			wp_send_json( array( 'success' => false ) );
		}
	}
	
	/**
	 * Get the appropriate picker for the product
	 *
	 * @param WC_Product $product Product object
	 * @param WC_Booking_Form $booking_form Booking form object
	 * @return object|null Picker object or null
	 */
	private function get_picker( $product, $booking_form ) {
		$duration_unit = $product->get_duration_unit();
		
		switch ( $duration_unit ) {
			case 'month':
				if ( class_exists( 'WC_Booking_Form_Month_Picker' ) ) {
					return new WC_Booking_Form_Month_Picker( $booking_form );
				}
				break;
			
			case 'day':
			case 'night':
				if ( class_exists( 'WC_Booking_Form_Date_Picker' ) ) {
					return new WC_Booking_Form_Date_Picker( $booking_form );
				}
				break;
			
			case 'minute':
			case 'hour':
				if ( class_exists( 'WC_Booking_Form_Datetime_Picker' ) ) {
					return new WC_Booking_Form_Datetime_Picker( $booking_form );
				}
				break;
		}
		
		return null;
	}
	
	/**
	 * Build date options
	 *
	 * @param array $rules Availability rules
	 * @param array $field Field configuration
	 * @param int $max_date Maximum date timestamp
	 * @param int $product_id Product ID
	 * @param int $resource_id Resource ID
	 * @return array|false Date options or false
	 */
	private function build_options( $rules, $field, $max_date, $product_id, $resource_id ) {
		$dates = array();
		
		foreach ( $rules as $dateset ) {
			if ( isset( $dateset['type'] ) && 'custom:daterange' === $dateset['type'] ) {
				if ( isset( $dateset['from'] ) && isset( $dateset['to'] ) && isset( $dateset['bookable'] ) && 'yes' === $dateset['bookable'] ) {
					$from_date = strtotime( $dateset['from'] );
					$to_date = strtotime( $dateset['to'] );
					
					for ( $current = $from_date; $current <= $to_date; $current = strtotime( '+1 day', $current ) ) {
						$js_date = date( 'Y-n-j', $current );
						
						if ( $current <= time() || $current > $max_date - 1 ) {
							continue;
						}
						
						if ( isset( $field['fully_booked_days'][ $js_date ] ) ) {
							continue;
						}
						
						$remaining_places = $this->get_remaining_places(
							$product_id,
							$resource_id,
							$current
						);
						
						if ( $remaining_places <= 0 ) {
							continue;
						}
						
						$dates[ $current ] = sprintf(
							'%s%s',
							date_i18n( 'F jS, Y', $current ),
							$remaining_places < 999 ? sprintf( ' (%d %s remaining)', $remaining_places, _n( 'place', 'places', $remaining_places, 'wc-bookings-dropdown' ) ) : ''
						);
					}
				}
				continue;
			}
			
			$years = false;
			
			if ( isset( $dateset['type'] ) && 'custom' === $dateset['type'] ) {
				$years = isset( $dateset['range'] ) ? $dateset['range'] : false;
			} elseif ( isset( $dateset[0] ) && 'custom' === $dateset[0] ) {
				$years = isset( $dateset[1] ) ? $dateset[1] : false;
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
						
						if ( $dtime <= time() || $dtime > $max_date - 1 ) {
							continue;
						}
						
						if ( isset( $field['fully_booked_days'][ $js_date ] ) ) {
							continue;
						}
						
						$remaining_places = $this->get_remaining_places(
							$product_id,
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
		
		return array( '' => __( 'Select a course date', 'wc-bookings-dropdown' ) ) + $formatted_dates;
	}
		
	/**
	 * Calculate remaining places
	 *
	 * @param int $product_id Product ID
	 * @param int $resource_id Resource ID
	 * @param int $start_date Start date timestamp
	 * @return int Remaining places
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
}
<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class AlexMigrate_Ajax_Actions {

	public function __construct() {
		$this->loadAjaxActions();
	}

	protected function loadAjaxActions() {
		$hooks = [
			'alexm_get_future_bookings',
			'alexm_get_restaurants',
			'alexm_process_booking',
		];

		foreach ($hooks as $action){
			$func = str_replace('alexm_', '', $action);
			add_action( 'wp_ajax_'.$action, array($this, $func));
			add_action( 'wp_ajax_nopriv_'.$action, array($this, $func));
		}
	}

	public function get_restaurants() {

		$the_query = new WP_Query([
			'post_type' => 'qrr_restaurant',
			'posts_per_page' => -1
		]);

		$restaurants = [];

		if ($the_query->have_posts()) {
			while ( $the_query->have_posts() ) {
				$the_query->the_post();

				$post_id = get_the_ID();
				$restaurants[] = [
					'id' => $post_id,
					'title' => get_the_title(),
					'alex_id' => 0, // For mapping
					'shift_id' => 0 // For mapping
				];
			}
		}

		$alex_restaurants = \Alexr\Models\Restaurant::get();
		$list_alex = [];
		foreach ($alex_restaurants as $item){
			$list_alex[] = [
				'id' => $item->id,
				'name' => $item->name
			];
		}

		$shifts = \Evavel\Query\Query::table('restaurant_setting')
			->where('meta_key', 'shift')
			->get();

		//ray($shifts);

		$list_shifts = [];
		foreach ($shifts as $shift) {
			$meta = json_decode($shift->meta_value);
			$list_shifts[] = [
				'id' => $shift->id,
				'restaurant_id' => $shift->restaurant_id,
				'name' => $meta->name
			];
		}


		wp_send_json_success([
			'restaurants' => $restaurants,
			'alex_restaurants' => $list_alex,
			'shifts' => $list_shifts
		]);
	}

	public function get_shifts() {

	}

	public function get_future_bookings() {

		$the_query = new WP_Query([
			'post_type' => 'qrr_booking',
			'posts_per_page' => -1
		]);

		$bookings = [];

		if ($the_query->have_posts()) {
			while ( $the_query->have_posts() ) {
				$the_query->the_post();

				$post_id = get_the_ID();
				$model = new QRR_Booking_Model($post_id);
				$rest_id = $model->get_restaurant_id();
				$date_str = $model->get_date();

				$bookings[] = [
					'id' => $post_id,
					'rest_id' => $rest_id,
					'title' => get_the_title(),
					'restaurant' => $rest_id > 0 ? get_the_title($rest_id) : 'no restaurant',
					'date' => $date_str,
					'email' => $model->get_user_email(),
					'phone' => $model->get_phone()
				];
			}
		}


		wp_send_json_success([
			'bookings' => $bookings
		]);
	}

	public function process_booking()
	{
		//ray('Processing booking');
		//ray($_REQUEST);

		$bookingId = intval($_REQUEST['bookingId']);
		$isFutureBooking = $_REQUEST['isFutureBooking'] == 'yes';
		$alexRestaurantId = intval($_REQUEST['alexRestaurantId']);
		$alexShiftId = intval($_REQUEST['alexShiftId']);
		$deleteAll = $_REQUEST['checkDelete'] == 'yes';
		$importPast = $_REQUEST['checkImportPast'] == 'yes';
		$importFuture = $_REQUEST['checkImportFuture'] == 'yes';

		/*ray([
			'$bookingId' => $bookingId,
			'$isFutureBooking' => $isFutureBooking,
			'$alexRestaurantId' =>$alexRestaurantId,
			'$deleteAll' => $deleteAll,
			'$importPast' => $importPast,
			'$importFuture' => $importFuture
		]);*/

		// Check if is future or is past
		// Check if has to be imported as future
		// Check if has to be imported as past
		// For importing -> Check can be mapped with the new restaurant id
		// Check if has to be deleted

		if ($importFuture || $importPast)
		{
			if ($alexRestaurantId == 0) {
				wp_send_json_error(['error' => 'restaurant_not_mapped']);
			}

			if ($importFuture && $isFutureBooking) {
				$this->importBooking($bookingId, $alexRestaurantId, $alexShiftId);
			} else if ($importPast) {
				$this->importBooking($bookingId, $alexRestaurantId, $alexShiftId);
			}
		}

		if ($deleteAll) {
			$this->deleteBooking($bookingId);
		}

		wp_send_json_success();
	}

	protected function importBooking($booking_id, $alex_restaurant_id, $alex_shift_id)
	{
		// Map fields: name, email, party, shift, date, duration
		//ray('ImportBooking');

		$model = new QRR_Booking_Model($booking_id);

		// Sin fecha no hay reserva
		$date = $model->get_date();
		if (!$date) {
			return false;
		}

		// Sin restaurante no ha reserva
		$rest_id = $model->get_restaurant_id();
		if (!$rest_id || $rest_id == 0) {
			return false;
		}


		$fields = get_post_meta($booking_id, 'qrr_custom_fields', true);
		$notes = "";
		if (is_array($fields)){
			foreach($fields as $field){
				if (isset($field['id']) && $field['id'] == 'qrr-message'){
					$notes = $field['value'];
				}
			}
		}

		$duration = intval($model->get_duration()) * 60;
		if ($duration == 0) {
			$duration = 120*60;
		}
		$status = $model->get_status();
		$list_status = [
			'qrr-pending' => \Alexr\Enums\BookingStatus::PENDING,
			'qrr-confirmed' => \Alexr\Enums\BookingStatus::BOOKED,
			'qrr-rejected' => \Alexr\Enums\BookingStatus::DENIED,
			'qrr-cancelled' => \Alexr\Enums\BookingStatus::CANCELLED
		];
		$status = isset($list_status[$status]) ? $list_status[$status] : \Alexr\Enums\BookingStatus::PENDING;

		$day = $model->get_date_day();
		$email = $model->get_user_email();
		$customer_name = $model->get_user_name();
		$time = evavel_Hm_to_seconds($model->get_date_hour());
		$phone = $model->get_phone();

		$customer_id = $this->getOrCreateCustomer($email, $customer_name, $phone, $alex_restaurant_id);

		$args = [
			'type' => 'online',
			'restaurant_id' => $alex_restaurant_id,
			'customer_id' => $customer_id,
			'date' =>  $day,
			'time' => $time,
			'duration' => $duration,
			'party' => $model->get_party(),
			'shift_event_id' => $alex_shift_id > 0 ? $alex_shift_id : null,
			'shift_event_name' => get_post_meta($booking_id, 'qrr_schedule_name', true),
			'language' => 'en', // @todo
			'status' => $status,
			'first_name' => $customer_name,
			'email' => $email,
			'phone' => $model->get_phone(),
			'notes' => $notes
		];

		// Si hay una reserva con los mismos datos entonces no se importa
		$booking = \Alexr\Models\Booking::where('restaurant_id', $alex_restaurant_id)
		                                ->where('email', $email)
		                                ->where('date', $day)
		                                ->where('time', $time)
		                                ->first();

		if ($booking) { return false; }

		// @TODO booking messages, custom fields, add emails sent?

		$booking = \Alexr\Models\Booking::create($args);
		$booking->save();

		return true;
	}

	protected function getOrCreateCustomer($email, $name, $phone, $alex_restaurant_id)
	{
		$customer = \Alexr\Models\Customer::where('restaurant_id', $alex_restaurant_id)->where('email', $email)->first();
		if ($customer) {
			return $customer->id;
		}

		$customer = \Alexr\Models\Customer::create([
			'restaurant_id' => $alex_restaurant_id,
			'email' => $email,
			'name' => $name,
			'phone' => $phone
		]);

		$customer->save();

		return $customer->id;
	}

	protected function deleteBooking($booking_id)
	{
		//ray('DeleteBooking ' . $booking_id);

		wp_delete_post($booking_id, true);

		return true;
	}
}

new AlexMigrate_Ajax_Actions;

<?php

namespace RRZE\RSVP;

defined('ABSPATH') || exit;

class BookingForm
{
    protected $options;

    protected $email;

    protected $idm;

    protected $template;

    protected $roomId;

    protected $ssoRequired = false;

    protected $sso = false;

    public function __construct()
    {
        $settings = new Settings(plugin()->getFile());
        $this->options = (object) $settings->getOptions();
        $this->email = new Email;
        $this->idm = new IdM;
        $this->template = new Template;
    }

    public function onLoaded()
    {
        add_action('template_redirect', [$this, 'ssoLogin']);
        add_action('template_redirect', [$this, 'bookingSubmitted']);
        add_filter('the_content', [$this, 'form']);
        add_action('wp_ajax_UpdateCalendar', [$this, 'ajaxUpdateCalendar']);
        add_action('wp_ajax_nopriv_UpdateCalendar', [$this, 'ajaxUpdateCalendar']);
        add_action('wp_ajax_UpdateForm', [$this, 'ajaxUpdateForm']);
        add_action('wp_ajax_nopriv_UpdateForm', [$this, 'ajaxUpdateForm']);
        add_action('wp_ajax_ShowItemInfo', [$this, 'ajaxShowItemInfo']);
        add_action('wp_ajax_nopriv_ShowItemInfo', [$this, 'ajaxShowItemInfo']);
    }

    public function ssoLogin()
    {
        global $post;
        if (is_a($post, '\WP_Post') && is_page() && ($this->roomId = Functions::getRoomIdByFormPageId($post->ID))) {
            $this->ssoRequired = (get_post_meta($this->roomId, 'rrze-rsvp-room-sso-required', true) == 'on');
            if ($this->ssoRequired) {
                $this->sso = $this->idm->tryLogIn(true);
            }
        }
    }

    public function form(string $content)
    {
        global $post;

        if (!is_page() || !($this->roomId = Functions::getRoomIdByFormPageId($post->ID))) {
            return $content;
        }

        wp_enqueue_style('rrze-rsvp-shortcode');

        $this->ssoRequired = (get_post_meta($this->roomId, 'rrze-rsvp-room-sso-required', true) == 'on');

        if ($content = $this->ssoAuthenticationError()) {
            return $content;
        }
        if ($content = $this->postDataError()) {
            return $content;
        }
        if ($content = $this->saveError()) {
            return $content;
        }
        if ($content = $this->multipleBookingError()) {
            return $content;
        }
        if ($content = $this->seatUnavailableError()) {
            return $content;
        }
        if ($content = $this->bookedNotice()) {
            return $content;
        }

        wp_enqueue_script('rrze-rsvp-shortcode');
        wp_localize_script('rrze-rsvp-shortcode', 'rsvp_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rsvp-ajax-nonce'),
        ]);

        if ($this->ssoRequired && !$this->sso) {
            return '<div class="alert alert-warning" role="alert">' . sprintf('%sSSO not available.%s Please activate SSO authentication or remove the SSO option from the room.', '<strong>', '</strong><br />') . '</div>';
        }

        $getDate = isset($_GET['bookingdate']) ? sanitize_text_field($_GET['bookingdate']) : false;
        $getTime = isset($_GET['timeslot']) ? sanitize_text_field($_GET['timeslot']) : false;
        $getRoom = isset($_GET['room_id']) ? absint($_GET['room_id']) : false;
        $getSeat = isset($_GET['seat_id']) ? absint($_GET['seat_id']) : false;
        $getInstant = (isset($_GET['instant']) && $_GET['instant'] == '1');

        $CommentEnabled = (get_post_meta($this->roomId, 'rrze-rsvp-room-notes-check', true) == 'on');

        if ($getRoom && $getDate) {
            $availability = Functions::getRoomAvailability(
                $getRoom,
                $getDate,
                date('Y-m-d', strtotime($getDate . ' +1 days'))
            );
        }

        $days = absint(get_post_meta($this->roomId, 'rrze-rsvp-room-days-in-advance', true));

        $dateComponents = getdate();
        $month          = $dateComponents['mon'];
        $year           = $dateComponents['year'];
        $start          = date_create();
        $end            = date_create();
        date_modify($end, '+' . $days . ' days');

        $data = [];
        $data['room_id'] = $this->roomId;
        $data['room_name'] = get_the_title($this->roomId);
        $data['action_link'] = get_permalink();
        $data['post_nonce'] = wp_nonce_field('post_nonce', 'rrze_rsvp_post_nonce_field');
        $data['book_a_seat_at'] = sprintf(__('Book a seat at: <strong>%s</strong>', 'rrze-rsvp'), $data['room_name']);
        $data['select_data_and_time'] = __('Select date and time', 'rrze-rsvp');
        $data['select_a_date'] = __('Please select a date.', 'rrze-rsvp');
        $data['available_timeslots'] = __('Available time slots:', 'rrze-rsvp');

        // Instant check-in
        $data['instant_checkin'] = $getInstant;

        // Calendar
        $data['calendar'] = $this->buildCalendar($month, $year, $start, $end, $this->roomId, $getDate);

        // Booking date
        $data['booking_date'] = $getDate;
        $data['select_timeslot'] = $getDate ? $this->buildTimeslotSelect($this->roomId, $getDate, $getTime, $availability) : '';

        // Booking timeslot
        $data['booking_timeslot'] = ($getDate && $getTime);
        $data['select_seat'] = ($getDate && $getTime) ? $this->buildSeatSelect($this->roomId, $getDate, $getTime, $getSeat, $availability) : '';

        // SSO enabled?
        $data['sso_enabled'] = $this->sso;

        // Customer IdM Data
        $customerData = $this->idm->getCustomerData();
        $data['customer']['lastname'] = $customerData['customer_lastname'];
        $data['customer']['firstname'] = $customerData['customer_firstname'];
        $data['customer']['email'] = $customerData['customer_email'];

        // Customer input data
        $data['customer_data_title'] = __('Your data', 'rrze-rsvp');
        $data['customer_lastname'] = __('Last name', 'rrze-rsvp');
        $data['customer_firstname'] = __('First name', 'rrze-rsvp');
        $data['customer_email'] = __('Email', 'rrze-rsvp');
        $data['customer_phone'] = __('Phone Number', 'rrze-rsvp');
        $data['customer_phone_description'] = __('In order to track contacts during the measures against the corona pandemic, it is necessary to record the telephone number.', 'rrze-rsvp');

        // Comment enabled?
        $data['is_comment_enabled'] = $CommentEnabled;
        $data['comment_label'] = get_post_meta($this->roomId, 'rrze-rsvp-room-notes-label', true);

        // DSVGO text
        $data['dsvgo_text'] = __('Ich bin damit einverstanden, dass meine Kontaktdaten für die Dauer des Vorganges der Platzbuchung und bis zu 4 Wochen danach zum Zwecke der Nachverfolgung gemäß der gesetzlichen Grundlagen zur Corona-Bekämpfung gespeichert werden dürfen. Ebenso wird Raumverantwortlichen und Veranstalter von Sprechstunden das Recht eingeräumt, während der Dauer des Buchungsprozesses und bis zum Ende des ausgewählten Termins Einblick in folgende Buchungsdaten zu nehmen: E-Mailadresse, Name, Vorname. Raumverantwortliche und Veranstalter von Sprechstunden erhalten diese Daten allein zum Zweck der Durchführung und Verwaltung des Termins gemäß §6 Abs1 a DSGVO. Die Telefonnummer wird nur zum Zwecke der Kontaktverfolgung aufgrund der gesetzlicher Grundlagen zur Pandemiebekämpfung für Gesundheitsbehörden erfasst.', 'rrze-rsvp');

        // Submit button text
        $data['submit_booking'] = __('Submit booking', 'rrze-rsvp');

        return $this->template->getContent('form/booking-form', $data);
    }

    protected function ssoAuthenticationError()
    {
        if (!isset($_GET['booking']) || !wp_verify_nonce($_GET['booking'], 'sso_authentication')) {
            return '';
        }

        $data = [];
        $data['sso_authentication_error'] = true;
        $data['sso_authentication'] = __('SSO error', 'rrze-rsvp');
        $data['message'] = __("Error retrieving your data from SSO. Please try again or contact the website administrator.", 'rrze-rsvp');

        return $this->template->getContent('form/booking-error', $data);
    }

    protected function postDataError()
    {
        if (!isset($_GET['booking']) || !wp_verify_nonce($_GET['booking'], 'post_data')) {
            return '';
        }

        $data = [];
        $data['booking_data_error'] = true;
        $data['booking_data'] =  __('Booking data', 'rrze-rsvp');
        $data['message'] =  __('Invalid or missing booking data.', 'rrze-rsvp');

        return $this->template->getContent('form/booking-error', $data);
    }

    protected function saveError()
    {
        if (!isset($_GET['booking']) || !wp_verify_nonce($_GET['booking'], 'save_error')) {
            return '';
        }

        $data = [];
        $data['booking_save_error'] = true;
        $data['booking_save'] =  __('Save booking', 'rrze-rsvp');
        $data['message'] =  __('Error saving the booking.', 'rrze-rsvp');

        return $this->template->getContent('form/booking-error', $data);
    }

    protected function multipleBookingError()
    {
        if (!isset($_GET['booking']) || !wp_verify_nonce($_GET['booking'], 'multiple_booking')) {
            return '';
        }

        $data = [];
        $data['multiple_booking_error'] = true;
        $data['multiple_booking'] = __('Multiple Booking', 'rrze-rsvp');
        $data['message'] = __('<strong>You have already booked a seat for the specified time slot.</strong><br>If you want to change your booking, please cancel the existing booking first. You will find the link to do so in your confirmation email.', 'rrze-rsvp');

        return $this->template->getContent('form/booking-error', $data);
    }

    protected function seatUnavailableError()
    {
        if (!isset($_GET['url']) || !isset($_GET['booking']) || !wp_verify_nonce($_GET['booking'], 'seat_unavailable')) {
            return '';
        }

        $url = $_GET['url'];
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $data = [];
        $data['seat_unavailable_error'] = true;
        $data['seat_already_booked'] = __('Seat already booked', 'rrze-rsvp');
        $data['message'] = __('<strong>Sorry! The seat you selected is no longer available.</strong><br>Please try again.', 'rrze-rsvp');
        $data['backlink'] = sprintf(__('<a href="%s">Back to booking form &rarr;</a>', 'rrze-rsvp'), $url);

        return $this->template->getContent('form/booking-error', $data);
    }

    protected function bookedNotice()
    {
        if (!isset($_GET['id']) || !isset($_GET['booking']) || !wp_verify_nonce($_GET['booking'], 'booked')) {
            return '';
        }

        $bookingId = absint($_GET['id']);
        $booking = Functions::getBooking($bookingId);
        if (!$booking) {
            return '';
        }

        $data = [];
        $roomId = $booking['room'];
        $autoconfirmation = (get_post_meta($roomId, 'rrze-rsvp-room-auto-confirmation', true) == 'on');
        $forceToConfirm = (get_post_meta($roomId, 'rrze-rsvp-room-force-to-confirm', true) == 'on');
        $forceToCheckin = (get_post_meta($roomId, 'rrze-rsvp-room-force-to-checkin', true) == 'on');

        $data['date'] = $booking['date'];
        $data['date_label'] = __('Date', 'rrze-rsvp');
        $data['time'] = $booking['time'];
        $data['time_label'] = __('Time', 'rrze-rsvp');
        $data['room_name'] = $booking['room_name'];
        $data['room_label'] = __('Room', 'rrze-rsvp');
        $data['seat_name'] = $booking['seat_name'];
        $data['seat_label'] = __('Seat', 'rrze-rsvp');

        // Customer data
        $data['customer']['name'] = sprintf('%s %s', $booking['guest_firstname'], $booking['guest_lastname']);
        $data['customer']['email'] = $booking['guest_email'];
        $data['data_sent_to_customer_email'] = sprintf(__('These data were also sent to your email address <strong>%s</strong>.', 'rrze-rsvp'), $booking['guest_email']);

        // Force to confirm
        $data['force_to_confirm'] = $forceToConfirm;
        $data['confirm_your_booking'] = __('Your reservation has been submitted. Please confirm your booking!', 'rrze-rsvp');
        $data['confirmation_request_sent'] = __('An email with the confirmation link and your booking information has been sent to your email address.<br><strong>Please note that unconfirmed bookings automatically expire after one hour.</strong>', 'rrze-rsvp');
        // !Force to confirm
        $data['reservation_submitted'] = __('Your reservation has been submitted. Thank you for booking!', 'rrze-rsvp');

        // Autoconfirmation
        $data['autoconfirmation'] = $autoconfirmation;
        $data['your_reservation'] = __('Your reservation:', 'rrze-rsvp');
        // !Autoconfirmation
        $data['your_reservation_request'] = __('Your reservation request:', 'rrze-rsvp');

        // !Force to confirm && autoconfirmation
        $data['place_has_been_reserved'] = __('<strong>This seat has been reserved for you.</strong><br>You can cancel it at any time if you cannot keep the appointment. You can find information on this in your confirmation email.', 'rrze-rsvp');
        // !Force to confirm && !autoconfirmation
        $data['reservation_request'] = __('<strong>Please note that this is only a reservation request.</strong><br>It only becomes binding as soon as we confirm your booking by email.', 'rrze-rsvp');

        // Force to check-in
        $data['force_to_checkin'] = $forceToCheckin;
        $data['check_in_when_arrive'] = __('Please remember to <strong>check in</strong> to your seat when you arrive!', 'rrze-rsvp');

        return $this->template->getContent('form/booking-booked', $data);
    }

    public function bookingSubmitted()
    {
        if (!isset($_POST['rrze_rsvp_post_nonce_field']) || !wp_verify_nonce($_POST['rrze_rsvp_post_nonce_field'], 'post_nonce')) {
            return;
        }

        array_walk_recursive(
            $_POST,
            function (&$value) {
                if (is_string($value)) {
                    $value = wp_strip_all_tags(trim($value));
                }
            }
        );

        $posted_data = $_POST;
        $booking_date = sanitize_text_field($posted_data['rsvp_date']);
        $booking_start = sanitize_text_field($posted_data['rsvp_time']);
        $booking_timestamp_start = strtotime($booking_date . ' ' . $booking_start);
        $booking_seat = absint($posted_data['rsvp_seat']);
        $booking_phone = sanitize_text_field($posted_data['rsvp_phone']);
        $booking_instant = (isset($posted_data['rsvp_instant']) && $posted_data['rsvp_instant'] == '1');
        $booking_comment = (isset($posted_data['rsvp_comment']) ? sanitize_textarea_field($posted_data['rsvp_comment']) : '');
        $booking_dsgvo = (isset($posted_data['rsvp_dsgvo']) && $posted_data['rsvp_dsgvo'] == '1');

        if ($this->sso) {
            if ($this->idm->isAuthenticated()) {
                $sso_data = $this->idm->getCustomerData();
                $booking_lastname  = $sso_data['customer_lastname'];
                $booking_firstname  = $sso_data['customer_firstname'];
                $booking_email  = $sso_data['customer_email'];
            } else {
                $redirectUrl = add_query_arg(
                    [
                        'booking' => wp_create_nonce('sso_authentication')
                    ],
                    get_permalink()
                );
                wp_redirect($redirectUrl);
                exit;
            }
        } else {
            $booking_lastname = sanitize_text_field($posted_data['rsvp_lastname']);
            $booking_firstname = sanitize_text_field($posted_data['rsvp_firstname']);
            $booking_email = sanitize_email($posted_data['rsvp_email']);
        }

        // Postdaten überprüfen
        if (
            !$booking_dsgvo
            || !Functions::validateDate($booking_date)
            || !Functions::validateTime($booking_start, 'H:i')
            || !get_post_meta($booking_seat, 'rrze-rsvp-seat-room', true)
            || empty($booking_lastname)
            || empty($booking_firstname)
            || !filter_var($booking_email, FILTER_VALIDATE_EMAIL)
            || empty($booking_phone)
        ) {
            $redirectUrl = add_query_arg(
                [
                    'booking' => wp_create_nonce('post_data')
                ],
                get_permalink()
            );
            wp_redirect($redirectUrl);
            exit;
        }

        // Überprüfen ob bereits eine Buchung mit gleicher E-Mail-Adresse zur gleichen Zeit vorliegt
        $check_args = [
            'post_type' => 'booking',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'rrze-rsvp-booking-guest-email',
                    'value' => $booking_email
                ],
                [
                    'key' => 'rrze-rsvp-booking-start',
                    'value' => $booking_timestamp_start
                ],
                [
                    'key' => 'rrze-rsvp-booking-status',
                    'value' => ['booked', 'confirmed', 'checked-in'],
                    'compare' => 'IN',
                ]
            ],
            'nopaging' => true,
        ];
        $check_bookings = get_posts($check_args);
        if (!empty($check_bookings)) {
            $redirectUrl = add_query_arg(
                [
                    'booking' => wp_create_nonce('multiple_booking')
                ],
                get_permalink()
            );
            wp_redirect($redirectUrl);
            exit;
        }

        // Überprüfen ob der Platz in der Zwischenzeit bereits anderweitig gebucht wurde
        $check_availability = Functions::getSeatAvailability($booking_seat, $booking_date, date('Y-m-d', strtotime($booking_date . ' +1 days')));
        $seat_available = false;
        foreach ($check_availability[$booking_date] as $timeslot) {
            if (strpos($timeslot, $booking_start) == 0) {
                $seat_available = true;
                break;
            }
        }
        if (!$seat_available) {
            $permalink = get_permalink($this->options->general_booking_page);
            $room_id = get_post_meta($booking_seat, 'rrze-rsvp-seat-room', true);

            $redirectUrl = add_query_arg(
                [
                    'url' => sprintf('%s?room_id=%s&bookingdate=%s&timeslot=%s', $permalink, $room_id, $booking_date, $booking_start),
                    'booking' => wp_create_nonce('seat_unavailable')
                ],
                get_permalink()
            );
            wp_redirect($redirectUrl);
            exit;
        }

        $room_id = get_post_meta($booking_seat, 'rrze-rsvp-seat-room', true);
        $room_meta = get_post_meta($room_id);
        $room_timeslots = isset($room_meta['rrze-rsvp-room-timeslots']) ? unserialize($room_meta['rrze-rsvp-room-timeslots'][0]) : '';

        $autoconfirmation = get_post_meta($room_id, 'rrze-rsvp-room-auto-confirmation', true) == 'on' ? true : false;
        $forceToConfirm = get_post_meta($room_id, 'rrze-rsvp-room-force-to-confirm', true) == 'on' ? true : false;
        $forceToCheckin = get_post_meta($room_id, 'rrze-rsvp-room-force-to-checkin', true) == 'on' ? true : false;

        foreach ($room_timeslots as $week) {
            foreach ($week['rrze-rsvp-room-weekday'] as $day) {
                $schedule[$day][$week['rrze-rsvp-room-starttime']] = $week['rrze-rsvp-room-endtime'];
            }
        }
        $weekday = date('N', $booking_timestamp_start);
        $booking_end = array_key_exists($booking_start, $schedule[$weekday]) ? $schedule[$weekday][$booking_start] : $booking_start;
        $booking_timestamp_end = strtotime($booking_date . ' ' . $booking_end);

        //Buchung speichern
        $new_draft = [
            'post_status' => 'publish',
            'post_type' => 'booking',
        ];
        $booking_id = wp_insert_post($new_draft);

        // Booking save error
        if (!$booking_id || is_wp_error($booking_id)) {
            $redirectUrl = add_query_arg(
                [
                    'booking' => wp_create_nonce('save_error')
                ],
                get_permalink()
            );
            wp_redirect($redirectUrl);
            exit;
        }

        // Successful booking saved
        update_post_meta($booking_id, 'rrze-rsvp-booking-start', $booking_timestamp_start);
        $weekday = date_i18n('w', $booking_timestamp_start);
        update_post_meta($booking_id, 'rrze-rsvp-booking-end', $booking_timestamp_end);
        update_post_meta($booking_id, 'rrze-rsvp-booking-seat', $booking_seat);
        update_post_meta($booking_id, 'rrze-rsvp-booking-guest-lastname', $booking_lastname);
        update_post_meta($booking_id, 'rrze-rsvp-booking-guest-firstname', $booking_firstname);
        update_post_meta($booking_id, 'rrze-rsvp-booking-guest-email', $booking_email);
        update_post_meta($booking_id, 'rrze-rsvp-booking-guest-phone', $booking_phone);
        if ($autoconfirmation) {
            $status = 'confirmed';
            $timestamp = current_time('timestamp');
            if ($booking_instant && $booking_date == date('Y-m-d', $timestamp) && $booking_timestamp_start < $timestamp) {
                $status = 'checked-in';
            }
        } else {
            $status = 'booked';
        }
        update_post_meta($booking_id, 'rrze-rsvp-booking-status', $status);
        update_post_meta($booking_id, 'rrze-rsvp-booking-notes', $booking_comment);
        update_post_meta($booking_id, 'rrze-rsvp-booking-dsgvo', $booking_dsgvo);
        if ($forceToConfirm) {
            update_post_meta($booking_id, 'rrze-rsvp-customer-status', 'booked');
        }

        // E-Mail senden
        if ($autoconfirmation) {
            if ($forceToConfirm) {
                $this->email->bookingRequestedCustomer($booking_id);
            } else {
                $this->email->bookingConfirmedCustomer($booking_id);
            }
        } else {
            if ($this->options->email_notification_if_new == 'yes' && $this->options->email_notification_email != '') {
                $to = $this->options->email_notification_email;
                $subject = _x('[RSVP] New booking received', 'Mail Subject for room admin: new booking received', 'rrze-rsvp');
                $this->email->bookingRequestedAdmin($to, $subject, $booking_id);
            }
        }

        // Redirect zur Seat-Seite, falls
        if ($status == 'checked-in') {
            wp_redirect(get_permalink($booking_seat));
            exit;
        }

        $redirectUrl = add_query_arg(
            [
                'id' => $booking_id,
                'booking' => wp_create_nonce('booked')
            ],
            get_permalink()
        );
        wp_redirect($redirectUrl);
        exit;
    }

    /*
     * Inspirationsquelle:
     * https://css-tricks.com/snippets/php/build-a-calendar-table/
     */
    public function buildCalendar($month, $year, $start = '', $end = '', $room = '', $bookingdate_selected = '')
    {
        if ($start == '')
            $start = date_create();
        if (!is_object($end))
            $end = date_create($end);
        if ($room == 'select')
            $room = '';
        // Create array containing abbreviations of days of week.
        $daysOfWeek = array('Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So');
        // What is the first day of the month in question?
        $firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
        $firstDayOfMonthObject = date_create($firstDayOfMonth);
        // How many days does this month contain?
        $numberDays = date('t', $firstDayOfMonth);
        // Retrieve some information about the first day of the
        // month in question.
        $dateComponents = getdate($firstDayOfMonth);
        // What is the name of the month in question?
        $monthName = $dateComponents['month'];
        // What is the index value (0-6) of the first day of the month in question.
        // (BB: adapted to European index (Mo = 0)
        $dayOfWeek = $dateComponents['wday'] - 1;
        if ($dayOfWeek == -1)
            $dayOfWeek = 6;
        $today_day = date("d");
        $today_day = ltrim($today_day, '0');
        $bookingDaysStart = $start;
        $bookingDaysEnd = $end;
        $endDate = date_format($bookingDaysEnd, 'Y-m-d');
        $startDate = date_format($bookingDaysStart, 'Y-m-d');
        $link_next = '<a href="#" class="cal-skip cal-next" data-direction="next">&gt;&gt;</a>';
        $link_prev = '<a href="#" class="cal-skip cal-prev" data-direction="prev">&lt;&lt;</a>';
        $availability = Functions::getRoomAvailability($room, $startDate, $endDate);

        // Create the table tag opener and day headers
        $calendar = '<table class="rsvp_calendar" data-period="' . date_i18n('Y-m', $firstDayOfMonth) . '" data-end="' . $endDate . '">';
        $calendar .= "<caption>";
        if ($bookingDaysStart <= date_create($year . '-' . $month)) {
            $calendar .= $link_prev;
        }
        $calendar .= date_i18n('F Y', $firstDayOfMonth);
        if ($bookingDaysEnd >= date_create($year . '-' . $month . '-' . $numberDays)) {
            $calendar .= $link_next;
        }
        //print $remainingBookingDays;
        $calendar .= "</caption>";
        $calendar .= "<tr>";
        // Create the calendar headers
        foreach ($daysOfWeek as $day) {
            $calendar .= "<th class='header'>$day</th>";
        }
        // Create the rest of the calendar
        // Initiate the day counter, starting with the 1st.
        $currentDay = 1;
        $calendar .= "</tr><tr>";
        // The variable $dayOfWeek is used to
        // ensure that the calendar
        // display consists of exactly 7 columns.
        if ($dayOfWeek > 0) {
            $calendar .= "<td colspan='$dayOfWeek'>&nbsp;</td>";
        }
        $month = str_pad($month, 2, "0", STR_PAD_LEFT);
        while ($currentDay <= $numberDays) {
            // Seventh column (Saturday) reached. Start a new row.
            if ($dayOfWeek == 7) {
                $dayOfWeek = 0;
                $calendar .= "</tr><tr>";
            }
            $currentDayRel = str_pad($currentDay, 2, "0", STR_PAD_LEFT);
            $date = "$year-$month-$currentDayRel";
            $currentDate = date_create($date);
            $class = '';
            $title = '';
            $active = true;
            if ($date < date_format($bookingDaysStart, 'Y-m-d') || $date > date_format($bookingDaysEnd, 'Y-m-d')) {
                $active = false;
                $title = __('Not bookable (outside booking period)', 'rrze-rsvp');
            } else {
                $active = false;
                $class = 'soldout';
                $title = __('Not bookable (soldout or room blocked)', 'rrze-rsvp');
                if ($room == '') {
                } else {
                    if (isset($availability[$date])) {
                        foreach ($availability[$date] as $timeslot) {
                            if (!empty($timeslot)) {
                                $active = true;
                                $class = 'available';
                                $title = __('Seats available', 'rrze-rsvp');
                            }
                        }
                    }
                }
            }

            $input_open = '<span class="inactive">';
            $input_close = '</span>';
            if ($active) {
                if ($bookingdate_selected == $date || ($bookingdate_selected == false && $date == $startDate)) {
                    $selected = 'checked="checked"';
                } else {
                    $selected = '';
                }
                //$selected = $bookingdate_selected == $date ? 'checked="checked"' : '';
                $input_open = "<input type=\"radio\" id=\"rsvp_date_$date\" value=\"$date\" name=\"rsvp_date\" $selected required aria-required='true'><label for=\"rsvp_date_$date\">";
                $input_close = '</label>';
            }
            $calendar .= "<td class='day $class' rel='$date' title='$title'>" . $input_open . $currentDay . $input_close . "</td>";
            // Increment counters
            $currentDay++;
            $dayOfWeek++;
        }
        // Complete the row of the last week in month, if necessary
        if ($dayOfWeek != 7) {
            $remainingDays = 7 - $dayOfWeek;
            $calendar .= "<td colspan='$remainingDays'>&nbsp;</td>";
        }
        $calendar .= "</tr>";
        $calendar .= "</table>";
        return $calendar;
    }

    public function buildDateBoxes($days = 14)
    {
        $content = '';
        for ($i = 0; $i <= $days; $i++) {
            $timestamp = mktime(0, 0, 0, date("m"), date("d") + $i, date("Y"));
            $techtime1 = date('Y-m-d_09-00', $timestamp);
            $techtime2 = date('Y-m-d_14-30', $timestamp);
            $content .= '<div class="rsvp-datebox">';
            $content .= date_i18n("D", $timestamp) . ', ' . date_i18n(get_option('date_format'), $timestamp);
            $content .= '<br /> <input type="radio" id="seat_' . $techtime1 . '" name="datetime" value="' . $techtime1 . '" disabled>'
                . '<label for="seat_' . $techtime1 . '" class="disabled"> 09:00-13:30 Uhr</label>';
            $content .= '<br /> <input type="radio" id="seat_' . $techtime2 . '" name="datetime" value="' . $techtime2 . '" disabled>'
                . '<label for="seat_' . $techtime2 . '" class="disabled"> 14:30-19:00 Uhr</label><br />';
            $content .= '';
            $content .= '</div>';
        }
        $content .= '<button class="show-more btn btn-default btn-block">&hellip;' . __('More', 'rrze-rsvp') . '&hellip;</button>';

        return $content;
    }

    public function ajaxUpdateCalendar()
    {
        check_ajax_referer('rsvp-ajax-nonce', 'nonce');
        $period = explode('-', $_POST['month']);
        $mod = ($_POST['direction'] == 'next' ? 1 : -1);
        $start = date_create();
        $end = sanitize_text_field($_POST['end']);
        $room = (int)$_POST['room'];
        $content = '';
        $content .= $this->buildCalendar($period[1] + $mod, $period[0], $start, $end, $room);
        echo $content;
        wp_die();
    }

    public function ajaxUpdateForm()
    {
        check_ajax_referer('rsvp-ajax-nonce', 'nonce');
        $room = ((isset($_POST['room']) && $_POST['room'] > 0) ? (int)$_POST['room'] : '');
        $date = (isset($_POST['date']) ? sanitize_text_field($_POST['date']) : false);
        $time = (isset($_POST['time']) ? sanitize_text_field($_POST['time']) : false);
        $response = [];
        if ($date !== false) {
            $response['time'] = '<div class="rsvp-time-select error">' . __('Please select a date.', 'rrze-rsvp') . '</div>';
        }
        if (!$date || !$time) {
            $response['seat'] = '<div class="rsvp-seat-select error">' . __('Please select a date and a time slot.', 'rrze-rsvp') . '</div>';
        }
        $availability = Functions::getRoomAvailability($room, $date, date('Y-m-d', strtotime($date . ' +1 days')));
        //        var_dump($availability);

        if ($date) {
            $response['time'] = $this->buildTimeslotSelect($room, $date, $time, $availability);
            if ($time) {
                $response['seat'] = $this->buildSeatSelect($room, $date, $time, false, $availability);
            }
        }
        wp_send_json($response);
    }

    public function ajaxShowItemInfo()
    {
        if (!isset($_POST['id'])) {
            echo '';
            wp_die();
        }
        $id = (int)$_POST['id'];
        $content = '';
        $seat_name = get_the_title($id);
        $equipment = get_the_terms($id, 'rrze-rsvp-equipment');
        $content .= '<div class="rsvp-item-info">';
        if ($equipment !== false) {
            $content .= '<div class="rsvp-item-equipment"><h5 class="small">' . sprintf(__('Seat %s', 'rrze-rsvp'), $seat_name) . '</h5>';
            foreach ($equipment as $e) {
                $e_arr[] = $e->name;
            }
            $content .= '<p><strong>' . __('Equipment', 'rrze-rsvp') . '</strong>: ' . implode(', ', $e_arr) . '</p>';
            $content .= '</div>';
        }
        $content .= '</div>';
        echo $content;
        wp_die();
    }

    private function buildTimeslotSelect($room, $date, $time = false, $availability)
    {
        $slots = [];
        $timeSelects = '';
        $slots = array_keys($availability[$date]);
        foreach ($slots as $slot) {
            $slot_value = explode('-', $slot)[0];
            $id = 'rsvp_time_' . sanitize_title($slot_value);
            $checked = checked($time !== false && $time == $slot_value, true, false);
            $timeSelects .= "<div class='form-group'><input type='radio' id='$id' value='$slot_value' name='rsvp_time' " . $checked . " required aria-required='true'><label for='$id'>$slot</label></div>";
        }
        if ($timeSelects == '') {
            $timeSelects .= __('No time slots available.', 'rrze-rsvp');
        }
        return '<div class="rsvp-time-select">' . $timeSelects . '</div>';
    }

    private function buildSeatSelect($room, $date, $time, $seat_id, $availability)
    {
        foreach ($availability as $xdate => $xtime) {
            foreach ($xtime as $k => $v) {
                $k_new = explode('-', $k)[0];
                $availability[$xdate][$k_new] = $v;
                unset($availability[$xdate][$k]);
            }
        }
        $seats = (isset($availability[$date][$time])) ? $availability[$date][$time] : [];
        //var_dump($seats);
        $seatSelects = '';
        foreach ($seats as $seat) {
            $seatname = get_the_title($seat);
            $id = 'rsvp_seat_' . sanitize_title($seat);
            $checked = checked($seat_id !== false && $seat == $seat_id, true, false);
            $seatSelects .= "<div class='form-group'>"
                . "<input type='radio' id='$id' value='$seat' name='rsvp_seat' $checked required aria-required='true'>"
                . "<label for='$id'>$seatname</label>"
                . "</div>";
        }
        if ($seatSelects == '') {
            $seatSelects = __('Please select a date and a time slot.', 'rrze-rsvp');
        }
        return '<h4>' . __('Available seats:', 'rrze-rsvp') . '</h4><div class="rsvp-seat-select">' . $seatSelects . '</div>';
    }
}

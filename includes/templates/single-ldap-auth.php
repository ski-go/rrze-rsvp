<?php
namespace RRZE\RSVP;

defined('ABSPATH') || exit;



$ldapInstance = new LDAP;
$template = new Template;

$roomId = isset($_GET['room_id']) ? absint($_GET['room_id']) : null;
$room = $roomId ? sprintf('?room_id=%d', $roomId) : '';
$seat = isset($_GET['seat_id']) ? sprintf('&seat_id=%d', absint($_GET['seat_id'])) : '';
$bookingDate = isset($_GET['bookingdate']) ? sprintf('&bookingdate=%s', sanitize_text_field($_GET['bookingdate'])) : '';
$timeslot = isset($_GET['timeslot']) ? sprintf('&timeslot=%s', sanitize_text_field($_GET['timeslot'])) : '';
$nonce = isset($_GET['nonce']) ? sprintf('&nonce=%s', sanitize_text_field($_GET['nonce'])) : '';        


$bookingId = isset($_GET['id']) && !$roomId ? sprintf('?id=%s', absint($_GET['id'])) : '';
$action = isset($_GET['action']) && !$roomId ? sprintf('&action=%s', sanitize_text_field($_GET['action'])) : '';


if ($ldapInstance->isAuthenticated()) {
    $redirectUrl = sprintf('%s%s%s%s%s%s%s%s', trailingslashit(get_permalink()), $bookingId, $action, $room, $seat, $bookingDate, $timeslot, $nonce);

    $data = $ldapInstance->getCustomerData();
    var_dump($data);

    echo '<br><br><br>';
    echo $redirectUrl . '||||';


    exit;
    wp_redirect($redirectUrl);
    exit; 
}

$data = [];
$data['title'] = __('Authentication Required', 'rrze-rsvp');
$data['please_login'] = __('Please login with your UB-AD username', 'rrze-rsvp');

get_header();

/*
 * div-/Seitenstruktur für FAU- und andere Themes
 */
if (Helper::isFauTheme()) {
    get_template_part('template-parts/hero', 'small');
    $divOpen = '<div id="content">
        <div class="container">
            <div class="row">
                <div class="col-xs-12">
                    <main id="droppoint">
                        <h1 class="screen-reader-text">' . get_the_title() . '</h1>
                        <div class="inline-box">
                            <div class="content-inline">';
    $divClose = '</div>
                        </div>
                    </main>
                </div>
            </div>
        </div>
    </div>';
} else {
    $divOpen = '<div id="content">
        <div class="container">
            <div class="row">
                <div class="col-xs-12">
                <h1 class="entry-title">' . get_the_title() . '</h1>';
    $divClose = '</div>
            </div>
        </div>
    </div>';
}


/*
 * Eigentlicher Content
 */
echo $divOpen;

echo $template->getContent('auth/require-ldap-auth', $data);

echo $divClose;

wp_enqueue_style('rrze-rsvp-shortcode');

get_footer();

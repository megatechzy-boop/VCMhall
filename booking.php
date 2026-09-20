<?php
declare(strict_types=1);
require __DIR__ . '/booking-store.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function booking_response(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

try {
    $db = booking_db();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $hall = strtolower(trim((string) ($_GET['hall'] ?? 'big')));
        if (!in_array($hall, ['small', 'big'], true)) booking_response(422, ['error' => 'Choose a valid hall.']);
        $query = $db->prepare('SELECT booked_date FROM bookings WHERE hall = ? AND booked_date >= ? ORDER BY booked_date');
        $query->execute([$hall, booking_today()]);
        booking_response(200, ['today' => booking_today(), 'hall' => $hall, 'bookedDates' => $query->fetchAll(PDO::FETCH_COLUMN)]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        booking_response(405, ['error' => 'Method not allowed.']);
    }
    if (!empty($_POST['website'])) {
        booking_response(200, ['message' => 'Your request has been received.']);
    }

    $row = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'event_type' => trim((string) ($_POST['event'] ?? '')),
        'hall' => strtolower(trim((string) ($_POST['hall'] ?? 'big'))),
        'event_date' => trim((string) ($_POST['date'] ?? '')),
        'guests' => trim((string) ($_POST['guests'] ?? '')),
        'message' => trim((string) ($_POST['message'] ?? '')),
    ];
    $events = ['Wedding', 'Engagement', 'Reception', 'Birthday', 'Naming Ceremony', 'Family Function', 'Corporate Event', 'Social Gathering', 'Venue Visit', 'Other Event'];
    if ($row['name'] === '' || strlen($row['name']) > 120 || !preg_match('/^[0-9+() -]{10,18}$/', $row['phone'])
        || ($row['email'] !== '' && (!filter_var($row['email'], FILTER_VALIDATE_EMAIL) || strlen($row['email']) > 254))
        || !in_array($row['event_type'], $events, true) || !in_array($row['hall'], ['small', 'big'], true) || !booking_valid_date($row['event_date'])
        || ($row['guests'] !== '' && (!ctype_digit($row['guests']) || (int) $row['guests'] < 1 || (int) $row['guests'] > 10000))
        || strlen($row['message']) > 2000) {
        booking_response(422, ['error' => 'Please check the name, phone, event, date and other details.']);
    }
    $row['guests'] = $row['guests'] === '' ? null : (int) $row['guests'];
    if (!in_array($row['hall'], ['small', 'big'], true)) booking_response(422, ['error' => 'Please choose a valid hall.']);

    $db->beginTransaction();
    try {
        $check = $db->prepare('SELECT 1 FROM bookings WHERE booked_date = ? AND hall = ? LIMIT 1');
        $check->execute([$row['event_date'], $row['hall']]);
        if ($check->fetchColumn()) {
            $db->rollBack();
            booking_response(409, ['error' => 'This date is already booked. Please choose another date.']);
        }
        $insert = $db->prepare('INSERT INTO bookings (name, phone, email, event_type, hall, event_date, guests, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([$row['name'], $row['phone'], $row['email'], $row['event_type'], $row['hall'], $row['event_date'], $row['guests'], $row['message']]);
        $id = (int) $db->lastInsertId();
        $activity = $db->prepare("INSERT INTO booking_activity (booking_id, action, details) VALUES (?, 'enquiry_created', 'Website enquiry received')");
        $activity->execute([$id]);
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
    try {
        booking_send_email($db, $id, $row);
    } catch (Throwable $error) {
        error_log('Booking email error: ' . $error->getMessage());
    }
    booking_response(201, ['message' => 'Enquiry saved. Our team will contact you. Your date is not booked yet.', 'reference' => $id]);
} catch (Throwable $error) {
    error_log('Booking error: ' . $error->getMessage());
    booking_response(503, ['error' => 'Booking service is temporarily unavailable. Please call the venue.']);
}

-- Import into the dedicated MySQL database before enabling the booking forms.
CREATE TABLE IF NOT EXISTS bookings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(18) NOT NULL,
    email VARCHAR(254) NOT NULL DEFAULT '',
    event_type VARCHAR(40) NOT NULL,
    event_date DATE NOT NULL,
    booked_date DATE DEFAULT NULL,
    guests INT UNSIGNED DEFAULT NULL,
    message TEXT NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    source VARCHAR(16) NOT NULL DEFAULT 'website',
    email_state VARCHAR(32) NOT NULL DEFAULT 'not_configured',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY one_booking_per_date (booked_date),
    KEY event_date_index (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

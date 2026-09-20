-- Run once against an existing VCM Hall database before deploying the new admin dashboard.
-- Existing enquiry/booking rows and their current statuses are preserved.

ALTER TABLE bookings
    ADD COLUMN total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER email_state,
    ADD COLUMN internal_notes TEXT NULL AFTER total_amount,
    ADD COLUMN follow_up_at DATETIME DEFAULT NULL AFTER internal_notes,
    ADD COLUMN follow_up_completed_at DATETIME DEFAULT NULL AFTER follow_up_at,
    ADD COLUMN confirmed_at DATETIME DEFAULT NULL AFTER follow_up_completed_at,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD KEY status_event_date_index (status, event_date),
    ADD KEY follow_up_index (follow_up_at, follow_up_completed_at);

UPDATE bookings
SET confirmed_at = created_at
WHERE status = 'confirmed' AND confirmed_at IS NULL;

UPDATE bookings
SET source = 'other'
WHERE source = 'admin';

CREATE TABLE booking_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(24) NOT NULL DEFAULT 'other',
    reference VARCHAR(100) NOT NULL DEFAULT '',
    notes VARCHAR(500) NOT NULL DEFAULT '',
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY booking_payment_index (booking_id, received_at),
    CONSTRAINT booking_payments_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_activity (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(40) NOT NULL,
    details VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY booking_activity_index (booking_id, created_at),
    CONSTRAINT booking_activity_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

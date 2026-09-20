-- Complete VCM Hall admin upgrade for an existing database.
-- Run this file once in phpMyAdmin after the original bookings schema is installed.
-- It preserves existing booking/enquiry rows and assigns old rows to Big Hall.

-- 1. Dashboard fields, payment history and activity history.
ALTER TABLE bookings
    ADD COLUMN total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER email_state,
    ADD COLUMN internal_notes TEXT NULL AFTER total_amount,
    ADD COLUMN follow_up_at DATETIME DEFAULT NULL AFTER internal_notes,
    ADD COLUMN follow_up_completed_at DATETIME DEFAULT NULL AFTER follow_up_at,
    ADD COLUMN confirmed_at DATETIME DEFAULT NULL AFTER follow_up_completed_at,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD KEY status_event_date_index (status, event_date),
    ADD KEY follow_up_index (follow_up_at, follow_up_completed_at);

UPDATE bookings SET confirmed_at = created_at WHERE status = 'confirmed' AND confirmed_at IS NULL;
UPDATE bookings SET source = 'other' WHERE source = 'admin';

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

-- 2. Enquiries, linked conversion and enquiry history.
ALTER TABLE bookings
    ADD COLUMN record_type VARCHAR(16) NOT NULL DEFAULT 'enquiry' AFTER status,
    ADD COLUMN enquiry_id BIGINT UNSIGNED DEFAULT NULL AFTER record_type,
    ADD KEY record_type_status_index (record_type, status),
    ADD KEY linked_enquiry_index (enquiry_id),
    ADD CONSTRAINT linked_enquiry_fk FOREIGN KEY (enquiry_id) REFERENCES bookings (id) ON DELETE SET NULL;

UPDATE bookings SET record_type = CASE
    WHEN status = 'blocked' THEN 'block'
    WHEN status = 'confirmed' OR confirmed_at IS NOT NULL THEN 'booking'
    ELSE 'enquiry'
END;

CREATE TABLE enquiry_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    enquiry_id BIGINT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    author VARCHAR(80) NOT NULL DEFAULT 'Admin',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY enquiry_note_index (enquiry_id, created_at),
    CONSTRAINT enquiry_notes_fk FOREIGN KEY (enquiry_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE enquiry_followups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    enquiry_id BIGINT UNSIGNED NOT NULL,
    scheduled_at DATETIME NOT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY enquiry_followup_index (enquiry_id, scheduled_at, completed_at),
    CONSTRAINT enquiry_followups_fk FOREIGN KEY (enquiry_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO enquiry_notes (enquiry_id, note, author, created_at)
SELECT id, internal_notes, 'Admin', created_at FROM bookings
WHERE record_type = 'enquiry' AND internal_notes IS NOT NULL AND internal_notes <> '';

INSERT INTO enquiry_followups (enquiry_id, scheduled_at, completed_at, created_at)
SELECT id, follow_up_at, follow_up_completed_at, created_at FROM bookings
WHERE record_type = 'enquiry' AND follow_up_at IS NOT NULL;

-- 3. Settings and event types.
CREATE TABLE settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NOT NULL,
    setting_group VARCHAR(40) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key),
    KEY settings_group_index (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY event_type_name_unique (name),
    KEY event_type_order_index (sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO event_types (name, sort_order) VALUES
('Wedding',1),('Engagement',2),('Reception',3),('Birthday',4),('Naming Ceremony',5),('Family Function',6),('Corporate Event',7),('Social Gathering',8),('Venue Visit',9),('Other Event',10);

-- 4. Independent Big Hall and Small Hall availability.
ALTER TABLE bookings
    ADD COLUMN hall VARCHAR(16) NOT NULL DEFAULT 'big' AFTER event_type,
    DROP INDEX one_booking_per_date,
    ADD UNIQUE KEY one_booking_per_hall_date (booked_date, hall),
    ADD KEY hall_event_date_index (hall, event_date);

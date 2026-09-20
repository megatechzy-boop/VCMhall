-- Import into the dedicated MySQL database before enabling the booking forms.
CREATE TABLE IF NOT EXISTS bookings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(18) NOT NULL,
    email VARCHAR(254) NOT NULL DEFAULT '',
    event_type VARCHAR(40) NOT NULL,
    hall VARCHAR(16) NOT NULL DEFAULT 'big',
    event_date DATE NOT NULL,
    booked_date DATE DEFAULT NULL,
    guests INT UNSIGNED DEFAULT NULL,
    message TEXT NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    record_type VARCHAR(16) NOT NULL DEFAULT 'enquiry',
    enquiry_id BIGINT UNSIGNED DEFAULT NULL,
    source VARCHAR(16) NOT NULL DEFAULT 'website',
    email_state VARCHAR(32) NOT NULL DEFAULT 'not_configured',
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    internal_notes TEXT NULL,
    follow_up_at DATETIME DEFAULT NULL,
    follow_up_completed_at DATETIME DEFAULT NULL,
    confirmed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY one_booking_per_hall_date (booked_date, hall),
    KEY event_date_index (event_date),
    KEY record_type_status_index (record_type, status),
    KEY linked_enquiry_index (enquiry_id),
    KEY status_event_date_index (status, event_date),
    KEY follow_up_index (follow_up_at, follow_up_completed_at),
    CONSTRAINT linked_enquiry_fk FOREIGN KEY (enquiry_id) REFERENCES bookings (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_payments (
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

CREATE TABLE IF NOT EXISTS booking_activity (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(40) NOT NULL,
    details VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY booking_activity_index (booking_id, created_at),
    CONSTRAINT booking_activity_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enquiry_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    enquiry_id BIGINT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    author VARCHAR(80) NOT NULL DEFAULT 'Admin',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY enquiry_note_index (enquiry_id, created_at),
    CONSTRAINT enquiry_notes_fk FOREIGN KEY (enquiry_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enquiry_followups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    enquiry_id BIGINT UNSIGNED NOT NULL,
    scheduled_at DATETIME NOT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY enquiry_followup_index (enquiry_id, scheduled_at, completed_at),
    CONSTRAINT enquiry_followups_fk FOREIGN KEY (enquiry_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NOT NULL,
    setting_group VARCHAR(40) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key),
    KEY settings_group_index (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_types (
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

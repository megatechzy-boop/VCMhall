-- Run once after 20260919_admin_dashboard.sql and before deploying the Enquiries page.
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
SELECT id, internal_notes, 'Admin', created_at
FROM bookings
WHERE record_type = 'enquiry' AND internal_notes IS NOT NULL AND internal_notes <> '';

INSERT INTO enquiry_followups (enquiry_id, scheduled_at, completed_at, created_at)
SELECT id, follow_up_at, follow_up_completed_at, created_at
FROM bookings
WHERE record_type = 'enquiry' AND follow_up_at IS NOT NULL;

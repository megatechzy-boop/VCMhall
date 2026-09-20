CREATE TABLE bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    phone TEXT NOT NULL,
    email TEXT NOT NULL DEFAULT '',
    event_type TEXT NOT NULL,
    hall TEXT NOT NULL DEFAULT 'big' CHECK (hall IN ('small','big')),
    event_date TEXT NOT NULL,
    booked_date TEXT,
    guests INTEGER,
    message TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'pending',
    record_type TEXT NOT NULL DEFAULT 'enquiry',
    enquiry_id INTEGER REFERENCES bookings(id) ON DELETE SET NULL,
    source TEXT NOT NULL DEFAULT 'website',
    email_state TEXT NOT NULL DEFAULT 'not_configured',
    total_amount NUMERIC NOT NULL DEFAULT 0,
    internal_notes TEXT,
    follow_up_at TEXT,
    follow_up_completed_at TEXT,
    confirmed_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (booked_date, hall)
);
CREATE INDEX status_event_date_index ON bookings (status, event_date);
CREATE INDEX record_type_status_index ON bookings (record_type, status);
CREATE INDEX linked_enquiry_index ON bookings (enquiry_id);
CREATE INDEX follow_up_index ON bookings (follow_up_at, follow_up_completed_at);

CREATE TABLE booking_payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    booking_id INTEGER NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    amount NUMERIC NOT NULL,
    payment_method TEXT NOT NULL DEFAULT 'other',
    reference TEXT NOT NULL DEFAULT '',
    notes TEXT NOT NULL DEFAULT '',
    received_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX booking_payment_index ON booking_payments (booking_id, received_at);

CREATE TABLE booking_activity (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    booking_id INTEGER NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    action TEXT NOT NULL,
    details TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX booking_activity_index ON booking_activity (booking_id, created_at);

CREATE TABLE enquiry_notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    enquiry_id INTEGER NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    note TEXT NOT NULL,
    author TEXT NOT NULL DEFAULT 'Admin',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX enquiry_note_index ON enquiry_notes (enquiry_id, created_at);

CREATE TABLE enquiry_followups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    enquiry_id INTEGER NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    scheduled_at TEXT NOT NULL,
    completed_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX enquiry_followup_index ON enquiry_followups (enquiry_id, scheduled_at, completed_at);

CREATE TABLE settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT NOT NULL,
    setting_group TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX settings_group_index ON settings (setting_group);

CREATE TABLE event_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    enabled INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX event_type_order_index ON event_types (sort_order, id);
INSERT INTO event_types(name,sort_order) VALUES
('Wedding',1),('Engagement',2),('Reception',3),('Birthday',4),('Naming Ceremony',5),('Family Function',6),('Corporate Event',7),('Social Gathering',8),('Venue Visit',9),('Other Event',10);

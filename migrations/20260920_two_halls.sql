-- Adds independent availability for the Small Hall and Big Hall.
-- Existing production records remain assigned to the Big Hall.
ALTER TABLE bookings
    ADD COLUMN hall VARCHAR(16) NOT NULL DEFAULT 'big' AFTER event_type,
    DROP INDEX one_booking_per_date,
    ADD UNIQUE KEY one_booking_per_hall_date (booked_date, hall),
    ADD KEY hall_event_date_index (hall, event_date);

-- Run once after the earlier dashboard and enquiries migrations.
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

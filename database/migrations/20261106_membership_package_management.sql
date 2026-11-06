-- Apply once to an existing COE Workgrid database to support admin-managed packages.
ALTER TABLE membership_plans
    ADD COLUMN description VARCHAR(500) NOT NULL DEFAULT '' AFTER monthly_price,
    ADD COLUMN features TEXT NOT NULL AFTER description,
    ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER features;

UPDATE membership_plans SET
    description = CASE id
        WHEN 1 THEN 'A welcoming place to find your rhythm.'
        WHEN 2 THEN 'A consistent routine, with room to focus.'
        WHEN 3 THEN 'More privacy and space to do your best work.'
        ELSE description
    END,
    features = CASE id
        WHEN 1 THEN 'Flexible coworking access\nFast Wi-Fi\nTea and coffee'
        WHEN 2 THEN 'Dedicated desk access\nMeeting room hours\nAll Daylight benefits'
        WHEN 3 THEN 'Private workspace access\nPriority meeting rooms\nAll Workweek benefits'
        ELSE features
    END,
    is_featured = CASE WHEN id = 2 THEN 1 ELSE 0 END
WHERE id IN (1, 2, 3);

USE agribase;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    user_id INT NOT NULL PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE farms
    ADD COLUMN approval_status
        ENUM('Pending', 'Approved', 'Rejected')
        NOT NULL DEFAULT 'Pending'
        AFTER growth_stage;

ALTER TABLE farms
    ADD COLUMN approved_by INT NULL
        AFTER approval_status;

ALTER TABLE farms
    ADD COLUMN approved_at DATETIME NULL
        AFTER approved_by;

ALTER TABLE farms
    ADD COLUMN rejection_reason TEXT NULL
        AFTER approved_at;

ALTER TABLE farms
    ADD COLUMN updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
        AFTER created_at;

ALTER TABLE farms
    ADD INDEX idx_farms_status (approval_status);

ALTER TABLE farms
    ADD CONSTRAINT fk_farms_approved_by
        FOREIGN KEY (approved_by)
        REFERENCES users(id)
        ON DELETE SET NULL;

/*
|--------------------------------------------------------------------------
| IMPORTANT
|--------------------------------------------------------------------------
|
| Existing farms from the old schema are moved to Pending.
| This preserves the new rule that an administrator must validate
| every farm before it can be monitored.
|
| New farms created by farmers will also be Pending.
|
*/

UPDATE farms
SET approval_status = 'Pending',
    approved_by = NULL,
    approved_at = NULL,
    rejection_reason = NULL;

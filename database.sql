CREATE DATABASE IF NOT EXISTS agribase
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE agribase;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'farmer') NOT NULL DEFAULT 'farmer',
    phone VARCHAR(30) NULL,
    address VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE password_reset_tokens (
    user_id INT NOT NULL PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE farms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT NOT NULL,
    farm_name VARCHAR(150) NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    crop VARCHAR(50) NOT NULL,
    area_hectares DECIMAL(8,2) NOT NULL DEFAULT 0,
    growth_stage VARCHAR(80) NULL,

    approval_status ENUM('Pending', 'Approved', 'Rejected')
        NOT NULL DEFAULT 'Pending',

    approved_by INT NULL,
    approved_at DATETIME NULL,
    rejection_reason TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_farms_farmer (farmer_id),
    INDEX idx_farms_status (approval_status),

    CONSTRAINT fk_farms_farmer
        FOREIGN KEY (farmer_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_farms_approved_by
        FOREIGN KEY (approved_by)
        REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE pest_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    crop VARCHAR(50) NOT NULL,
    growth_stage VARCHAR(80) NOT NULL,
    plant_part VARCHAR(80) NOT NULL,
    symptoms TEXT NOT NULL,
    affected_area DECIMAL(5,2) NOT NULL,
    severity ENUM('Minor', 'Moderate', 'Severe') NOT NULL,
    problem VARCHAR(150) NOT NULL,
    risk ENUM('Low', 'Moderate', 'High') NOT NULL,
    recommendation TEXT NOT NULL,
    assessed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_pest_farm (farm_id),
    INDEX idx_pest_risk (risk),

    CONSTRAINT fk_pest_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_pest_assessed_by
        FOREIGN KEY (assessed_by)
        REFERENCES users(id)
        ON DELETE RESTRICT
);

CREATE TABLE incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    farm_id INT NOT NULL,

    status ENUM(
        'Reported',
        'Under Review',
        'Verified',
        'Monitoring',
        'Resolved'
    ) NOT NULL DEFAULT 'Reported',

    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_incidents_farm (farm_id),
    INDEX idx_incidents_status (status),

    CONSTRAINT fk_incidents_assessment
        FOREIGN KEY (assessment_id)
        REFERENCES pest_assessments(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_incidents_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE CASCADE
);

CREATE TABLE incident_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    assessed_by INT NOT NULL,
    assessment TEXT NOT NULL,
    procedures TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_incident_assessments_incident (incident_id),

    CONSTRAINT fk_incident_assessments_incident
        FOREIGN KEY (incident_id)
        REFERENCES incidents(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_incident_assessments_admin
        FOREIGN KEY (assessed_by)
        REFERENCES users(id)
        ON DELETE RESTRICT
);

CREATE TABLE production_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    crop VARCHAR(50) NOT NULL,
    growth_stage VARCHAR(80) NOT NULL,

    overall_condition ENUM(
        'Healthy',
        'Fair',
        'At Risk'
    ) NOT NULL,

    plant_condition VARCHAR(100) NOT NULL,
    pest_presence VARCHAR(50) NOT NULL,
    affected_area DECIMAL(5,2) NOT NULL,
    production_condition VARCHAR(100) NOT NULL,
    recommendation TEXT NOT NULL,
    assessed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_production_farm (farm_id),

    CONSTRAINT fk_production_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_production_assessed_by
        FOREIGN KEY (assessed_by)
        REFERENCES users(id)
        ON DELETE RESTRICT
);

CREATE TABLE advisories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    crop VARCHAR(50) NOT NULL,
    risk_level ENUM('Low', 'Moderate', 'High')
        NOT NULL DEFAULT 'Low',
    content TEXT NOT NULL,
    details TEXT NULL,
    image_path VARCHAR(255) NULL,
    image_alt VARCHAR(180) NULL,
    status ENUM('Published', 'Draft')
        NOT NULL DEFAULT 'Published',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_advisories_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE RESTRICT
);

CREATE TABLE system_settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value TEXT NOT NULL
);

INSERT INTO users (
    full_name,
    username,
    email,
    password_hash,
    role,
    phone,
    address
)
VALUES
(
    'System Administrator',
    'admin',
    'admin@agribase.local',
    SHA2('admin123', 256),
    'admin',
    '09170000000',
    'AgriBase Office'
),
(
    'Juan Dela Cruz',
    'juan',
    'juan@agribase.local',
    SHA2('farmer123', 256),
    'farmer',
    '09170000001',
    'Campo'
),
(
    'Maria Reyes',
    'maria',
    'maria@agribase.local',
    SHA2('farmer123', 256),
    'farmer',
    '09170000002',
    'Pautao'
);

INSERT INTO farms (
    farmer_id,
    farm_name,
    barangay,
    crop,
    area_hectares,
    growth_stage,
    approval_status,
    approved_by,
    approved_at
)
VALUES
(
    2,
    'Farm 001',
    'Campo',
    'Rice',
    2.50,
    'Vegetative',
    'Approved',
    1,
    NOW()
),
(
    2,
    'Coconut Farm 01',
    'Campo',
    'Coconut',
    1.80,
    'Fruiting',
    'Approved',
    1,
    NOW()
),
(
    3,
    'Farm 002',
    'Pautao',
    'Coconut',
    2.00,
    'Flowering',
    'Approved',
    1,
    NOW()
);

INSERT INTO advisories (
    title,
    crop,
    risk_level,
    content,
    status,
    created_by
)
VALUES
(
    'Monitor Leaf Discoloration',
    'Rice',
    'Moderate',
    'Inspect affected leaves, record the affected area, and continue regular field monitoring. Escalate when symptoms spread.',
    'Published',
    1
),
(
    'Inspect Coconut Crown',
    'Coconut',
    'Low',
    'Regularly inspect the crown and fruiting area for visible damage and document changes through assessments.',
    'Published',
    1
),
(
    'High-Risk Incident Follow-up',
    'Rice & Coconut',
    'High',
    'High-risk records should be reviewed promptly and followed by a field verification or appropriate agricultural intervention.',
    'Published',
    1
);

INSERT INTO system_settings (
    setting_key,
    setting_value
)
VALUES
(
    'organization_name',
    'AgriBase Agricultural Monitoring'
),
(
    'barangay_scope',
    'Campo, Cabugao, Pautao'
),
(
    'report_footer',
    'Generated by AgriBase'
)
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value);

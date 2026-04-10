-- Family Planning Module Database Schema
-- For remera1 database

-- Main Family Planning General Info table
CREATE TABLE IF NOT EXISTS fam_general_info (
    fam_general_info_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NULL,
    upid VARCHAR(50) NULL,
    consultation_date DATE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    practitioner_id VARCHAR(100) NULL,
    practitioner_name VARCHAR(255) NULL,
    date_of_birth DATE NULL,
    age VARCHAR(20) NULL,
    gender VARCHAR(20) NULL,
    phone_number VARCHAR(30) NULL,
    education_level VARCHAR(50) NULL,
    profession VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    district VARCHAR(100) NULL,
    sector VARCHAR(100) NULL,
    cellule VARCHAR(100) NULL,
    village VARCHAR(100) NULL,
    catchment_area VARCHAR(100) NULL,
    marital_status VARCHAR(50) NULL,
    accompanied_by_partner ENUM('no', 'yes') DEFAULT 'no',
    partner_full_name VARCHAR(255) NULL,
    partner_date_of_birth DATE NULL,
    partner_phone_number VARCHAR(30) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_patient_id (patient_id),
    INDEX idx_upid (upid),
    INDEX idx_consultation_date (consultation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Family Planning Consultation table
CREATE TABLE IF NOT EXISTS fam_consultation (
    fam_consultation_id INT AUTO_INCREMENT PRIMARY KEY,
    fam_general_info_id INT NOT NULL,
    is_surgical ENUM('no', 'yes') DEFAULT 'no',
    method_offered VARCHAR(255) NULL,
    history_heart_disease TINYINT(1) DEFAULT 0,
    history_liver_disease TINYINT(1) DEFAULT 0,
    history_kidney_disease TINYINT(1) DEFAULT 0,
    history_diabetes TINYINT(1) DEFAULT 0,
    history_hypertension TINYINT(1) DEFAULT 0,
    gravidity INT NULL,
    parity INT NULL,
    birth_spacing VARCHAR(50) NULL,
    living_children INT NULL,
    children_deceased INT NULL,
    desired_children INT NULL,
    lmp_first_date DATE NULL,
    pregnancy_status VARCHAR(20) NULL,
    date_of_last_abortion DATE NULL,
    birth_limitation VARCHAR(100) NULL,
    menstrual_regularity VARCHAR(20) NULL,
    breastfeeding_status VARCHAR(100) NULL,
    sexual_activity_level VARCHAR(100) NULL,
    sexual_partners_count INT NULL,
    pregnancy_complications TEXT NULL,
    consultation_type VARCHAR(100) NULL,
    used_contraception_before ENUM('no', 'yes') NULL,
    desired_fp_method VARCHAR(255) NULL,
    previous_methods_used TEXT NULL,
    transport_to_facility VARCHAR(100) NULL,
    transport_back_home VARCHAR(100) NULL,
    referring_facility VARCHAR(255) NULL,
    knowledge_of_other_methods TEXT NULL,
    person_informed_client VARCHAR(255) NULL,
    rumors_heard_about_fp TEXT NULL,
    reasons_not_wanting_children TEXT NULL,
    respiratory_illness ENUM('no', 'yes') NULL,
    anemia_bleeding_dis ENUM('no', 'yes') NULL,
    genital_abnormalities ENUM('no', 'yes') NULL,
    current_medications TEXT NULL,
    weight_kg DECIMAL(5,2) NULL,
    height_cm DECIMAL(5,2) NULL,
    temperature_c DECIMAL(4,1) NULL,
    rr INT NULL,
    pulse_bpm INT NULL,
    spo2_percentage DECIMAL(4,1) NULL,
    general_condition TEXT NULL,
    heart_observations TEXT NULL,
    abdomen_palpation TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fam_general_info_id) REFERENCES fam_general_info(fam_general_info_id) ON DELETE CASCADE,
    INDEX idx_fam_general_info_id (fam_general_info_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Family Planning Investigations table
CREATE TABLE IF NOT EXISTS fam_investigations (
    fam_investigation_id INT AUTO_INCREMENT PRIMARY KEY,
    fam_general_info_id INT NOT NULL,
    examination_name VARCHAR(255) NOT NULL,
    results TEXT NULL,
    date_performed DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fam_general_info_id) REFERENCES fam_general_info(fam_general_info_id) ON DELETE CASCADE,
    INDEX idx_fam_general_info_id (fam_general_info_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Family Planning Conclusion table
CREATE TABLE IF NOT EXISTS fam_conclusion (
    fam_conclusion_id INT AUTO_INCREMENT PRIMARY KEY,
    fam_general_info_id INT NOT NULL,
    diagnosis TEXT NULL,
    conclusion_text TEXT NULL,
    eligible_fp_methods TEXT NULL,
    method_initiation_date DATE NULL,
    first_followup_date DATE NULL,
    method_duration VARCHAR(100) NULL,
    chosen_method VARCHAR(255) NULL,
    method_offered_by_provider VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fam_general_info_id) REFERENCES fam_general_info(fam_general_info_id) ON DELETE CASCADE,
    INDEX idx_fam_general_info_id (fam_general_info_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Family Planning Treatments table
CREATE TABLE IF NOT EXISTS fam_treatments (
    fam_treatment_id INT AUTO_INCREMENT PRIMARY KEY,
    fam_general_info_id INT NOT NULL,
    medication_name VARCHAR(255) NOT NULL,
    dosage VARCHAR(100) NULL,
    route VARCHAR(100) NULL,
    frequency VARCHAR(100) NULL,
    duration VARCHAR(100) NULL,
    date_prescribed DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fam_general_info_id) REFERENCES fam_general_info(fam_general_info_id) ON DELETE CASCADE,
    INDEX idx_fam_general_info_id (fam_general_info_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Family Planning Follow-up table
CREATE TABLE IF NOT EXISTS fam_followup (
    fam_followup_id INT AUTO_INCREMENT PRIMARY KEY,
    fam_general_info_id INT NOT NULL,
    visit_date DATE NULL,
    consultation_type VARCHAR(100) NULL,
    practitioner_name VARCHAR(255) NULL,
    weight_kg DECIMAL(5,2) NULL,
    liver_examination TEXT NULL,
    breast_examination TEXT NULL,
    bp_systolic INT NULL,
    bp_diastolic INT NULL,
    bp_interpretation VARCHAR(100) NULL,
    chief_complaints TEXT NULL,
    previous_fp_method VARCHAR(255) NULL,
    fp_method_provided VARCHAR(255) NULL,
    method_duration VARCHAR(100) NULL,
    next_appointment_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fam_general_info_id) REFERENCES fam_general_info(fam_general_info_id) ON DELETE CASCADE,
    INDEX idx_fam_general_info_id (fam_general_info_id),
    INDEX idx_visit_date (visit_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Family Planning Discharge table
CREATE TABLE IF NOT EXISTS fam_discharge (
    fam_discharge_id INT AUTO_INCREMENT PRIMARY KEY,
    fam_general_info_id INT NOT NULL UNIQUE,
    discharging_status VARCHAR(50) NULL,
    discharge_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fam_general_info_id) REFERENCES fam_general_info(fam_general_info_id) ON DELETE CASCADE,
    INDEX idx_fam_general_info_id (fam_general_info_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
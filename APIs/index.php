<?php
/**
 * Family Planning API
 * Single endpoint for full-record operations
 *
 * GET  ?section=full-record&record_id=<id> - Load a record
 * POST ?section=full-record - Save a record
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../MODELs/config/database.php';
require_once __DIR__ . '/../CONTROLLERs/helpers/functions.php';

$section = $_GET['section'] ?? null;

// Initialize database connection early
$pdo = getDB();

if (!in_array($section, ['full-record', 'patients'])) {
    jsonResponse(['error' => 'Invalid section'], 400);
}

// Handle patients list endpoint
if ($section === 'patients' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $search = $_GET['search'] ?? '';
        $limit = (int)($_GET['limit'] ?? 50);

        $sql = "SELECT up.upid, up.patient_id, up.client_id, p.beneficiary, p.age, p.sex, p.tel,
                       p.district, p.section, p.cellule, p.village
                FROM upid_patients up
                LEFT JOIN patients p ON up.patient_id = p.patient_id
                WHERE up.status = 1";

        if ($search) {
            $sql .= " AND (up.upid LIKE ? OR p.beneficiary LIKE ?)";
            $stmt = $pdo->prepare($sql . " LIMIT $limit");
            $searchParam = "%$search%";
            $stmt->execute([$searchParam, $searchParam]);
        } else {
            $stmt = $pdo->query($sql . " LIMIT $limit");
        }

        $patients = $stmt->fetchAll();

        // Get lookup tables for address names
        $districtStmt = $pdo->query("SELECT district_id, district FROM districts_client");
        $districts = $districtStmt->fetchAll();
        $districtMap = array_column($districts, 'district', 'district_id');

        $sectorStmt = $pdo->query("SELECT location_id, section FROM sector");
        $sectors = $sectorStmt->fetchAll();
        $sectorMap = [];
        foreach ($sectors as $s) {
            $sectorMap[$s['location_id']] = $s['section'];
        }

        $villageStmt = $pdo->query("SELECT village_id, village, cell_id FROM villages");
        $villages = $villageStmt->fetchAll();
        $villageMap = [];
        $cellIdToName = [];
        foreach ($villages as $v) {
            $villageMap[$v['village_id']] = $v['village'];
            // Store cell_id to cell name mapping (cell_id is the cellule code like 22103)
            if ($v['cell_id'] && !isset($cellIdToName[$v['cell_id']])) {
                $cellIdToName[$v['cell_id']] = $v['village']; // Use first village name as cellule name
            }
        }

        // Get province mapping from database
        $provinceByDistrict = [];
        $provinceNames = [
            1 => 'East',
            2 => 'Kigali',
            3 => 'North',
            4 => 'South',
            5 => 'West'
        ];

        // Query districts with province_id
        $provinceStmt = $pdo->query("SELECT district_id, district, province_id FROM districts_client");
        $provinceDistricts = $provinceStmt->fetchAll();
        foreach ($provinceDistricts as $pd) {
            $provinceByDistrict[$pd['district']] = $provinceNames[$pd['province_id']] ?? '';
        }

        // Get users for practitioner dropdown
        $userStmt = $pdo->query("SELECT id, fullname, post FROM users");
        $users = $userStmt->fetchAll();
        $userMap = [];
        foreach ($users as $u) {
            $userMap[$u['id']] = ['name' => $u['fullname'], 'post' => $u['post']];
        }

        // Get catchment area (from facilities table)
        $facilityStmt = $pdo->query("SELECT facility_id, facility FROM facilities WHERE online = 1");
        $facilities = $facilityStmt->fetchAll();
        $facilityMap = array_column($facilities, 'facility', 'facility_id');

        // Format for dropdown
        $result = array_map(function($p) use ($districtMap, $sectorMap, $villageMap, $cellIdToName, $provinceByDistrict, $userMap, $facilityMap) {
            $name = $p['beneficiary'] ?? 'Unknown';

            // Get sector code and name directly from patient data
            $sectorCode = $p['section']; // This is the sector field in patients table
            $sectorName = isset($sectorMap[$sectorCode]) ? $sectorMap[$sectorCode] : $sectorCode;

            // Get cellule name from cell_id using village lookup
            $celluleCode = $p['cellule'];
            $celluleName = $cellIdToName[$celluleCode] ?? $celluleCode;

            // Get district info to find province
            $districtId = $p['district'];
            $districtName = $districtMap[$districtId] ?? $districtId;
            $provinceName = $provinceByDistrict[$districtName] ?? '';

            return [
                'upid' => $p['upid'],
                'patient_id' => $p['patient_id'],
                'client_id' => $p['client_id'],
                'label' => $p['upid'] . ' - ' . $name,
                'name' => $name,
                'date_of_birth' => $p['age'],
                'sex' => $p['sex'],
                'phone' => $p['tel'],
                'province' => $provinceName,
                'district' => $districtName,
                'sector' => $sectorName,
                'cellule' => $celluleName,
                'village' => $villageMap[$p['village']] ?? $p['village'],
                'catchment_area' => '',
                'marital_status' => '',
                'accompanied_by_partner' => 'no',
                'practitioner_id' => '',
                'practitioner_name' => '',
                'profession' => ''
            ];
        }, $patients);

        jsonResponse($result);

    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

if ($section !== 'full-record') {
    jsonResponse(['error' => 'Invalid section'], 400);
}

// Handle GET request - Load record
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $recordId = $_GET['record_id'] ?? null;

    if (!$recordId) {
        jsonResponse(['error' => 'Record ID required'], 400);
    }

    try {
        // Get general info
        $stmt = $pdo->prepare("SELECT * FROM fam_general_info WHERE fam_general_info_id = ?");
        $stmt->execute([$recordId]);
        $generalInfo = $stmt->fetch();

        if (!$generalInfo) {
            jsonResponse(['error' => 'Record not found'], 404);
        }

        // Get consultation
        $stmt = $pdo->prepare("SELECT * FROM fam_consultation WHERE fam_general_info_id = ?");
        $stmt->execute([$recordId]);
        $consultation = $stmt->fetch();

        // Get investigations
        $stmt = $pdo->prepare("SELECT examination_name, results, date_performed FROM fam_investigations WHERE fam_general_info_id = ?");
        $stmt->execute([$recordId]);
        $investigations = $stmt->fetchAll();

        // Get conclusion
        $stmt = $pdo->prepare("SELECT * FROM fam_conclusion WHERE fam_general_info_id = ?");
        $stmt->execute([$recordId]);
        $conclusion = $stmt->fetch();

        // Get treatments
        $stmt = $pdo->prepare("SELECT medication_name, dosage, route, frequency, duration, date_prescribed FROM fam_treatments WHERE fam_general_info_id = ?");
        $stmt->execute([$recordId]);
        $treatments = $stmt->fetchAll();

        // Get follow-ups
        $stmt = $pdo->prepare("SELECT * FROM fam_followup WHERE fam_general_info_id = ? ORDER BY visit_date DESC");
        $stmt->execute([$recordId]);
        $followups = $stmt->fetchAll();

        // Get discharge
        $stmt = $pdo->prepare("SELECT * FROM fam_discharge WHERE fam_general_info_id = ?");
        $stmt->execute([$recordId]);
        $discharge = $stmt->fetch();

        // Merge all data
        $data = array_merge($generalInfo, $consultation ?? []);

        // Add nested data
        $data['investigations'] = $investigations;
        $data['treatments'] = $treatments;
        $data['followups'] = $followups;
        $data['discharge'] = $discharge ?? [];

        jsonResponse($data);

    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Handle POST request - Save record
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getJsonInput();
    $patientIdFromForm = $input['patient_id'] ?? null;

    try {
        $pdo->beginTransaction();

        // Get or create patient_id from UPID
        $patientId = null;
        if (!empty($input['upid'])) {
            $patientId = getPatientIdByUpid($input['upid'], $pdo);
        }

        // If no existing patient, we can still save with null patient_id
        // (the UPID will be stored in the fam_general_info table)

        // 1. Insert/Update General Info
        if ($patientIdFromForm) {
            // Update existing record
            $stmt = $pdo->prepare("SELECT fam_general_info_id FROM fam_general_info WHERE fam_general_info_id = ?");
            $stmt->execute([$patientIdFromForm]);
            $existing = $stmt->fetch();

            if ($existing) {
                $recordId = $existing['fam_general_info_id'];

                $sql = "UPDATE fam_general_info SET
                    patient_id = ?, upid = ?, consultation_date = ?, full_name = ?,
                    practitioner_id = ?, practitioner_name = ?, date_of_birth = ?, age = ?,
                    gender = ?, phone_number = ?, education_level = ?, profession = ?,
                    province = ?, district = ?, sector = ?, cellule = ?, village = ?,
                    catchment_area = ?, marital_status = ?, accompanied_by_partner = ?,
                    partner_full_name = ?, partner_date_of_birth = ?, partner_phone_number = ?
                    WHERE fam_general_info_id = ?";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $patientId, $input['upid'] ?? null, $input['consultation_date'] ?? null,
                    $input['full_name'] ?? null, $input['practitioner_id'] ?? null,
                    $input['practitioner_name'] ?? null, $input['date_of_birth'] ?? null,
                    $input['age'] ?? null, $input['gender'] ?? null, $input['phone_number'] ?? null,
                    $input['education_level'] ?? null, $input['profession'] ?? null,
                    $input['province'] ?? null, $input['district'] ?? null, $input['sector'] ?? null,
                    $input['cellule'] ?? null, $input['village'] ?? null, $input['catchment_area'] ?? null,
                    $input['marital_status'] ?? null, $input['accompanied_by_partner'] ?? 'no',
                    $input['partner_full_name'] ?? null, $input['partner_date_of_birth'] ?? null,
                    $input['partner_phone_number'] ?? null, $recordId
                ]);
            }
        }

        if (!isset($recordId)) {
            // Insert new record
            $sql = "INSERT INTO fam_general_info (
                patient_id, upid, consultation_date, full_name, practitioner_id, practitioner_name,
                date_of_birth, age, gender, phone_number, education_level, profession,
                province, district, sector, cellule, village, catchment_area, marital_status,
                accompanied_by_partner, partner_full_name, partner_date_of_birth, partner_phone_number
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $patientId, $input['upid'] ?? null, $input['consultation_date'] ?? null,
                $input['full_name'] ?? null, $input['practitioner_id'] ?? null,
                $input['practitioner_name'] ?? null, $input['date_of_birth'] ?? null,
                $input['age'] ?? null, $input['gender'] ?? null, $input['phone_number'] ?? null,
                $input['education_level'] ?? null, $input['profession'] ?? null,
                $input['province'] ?? null, $input['district'] ?? null, $input['sector'] ?? null,
                $input['cellule'] ?? null, $input['village'] ?? null, $input['catchment_area'] ?? null,
                $input['marital_status'] ?? null, $input['accompanied_by_partner'] ?? 'no',
                $input['partner_full_name'] ?? null, $input['partner_date_of_birth'] ?? null,
                $input['partner_phone_number'] ?? null
            ]);
            $recordId = $pdo->lastInsertId();
        }

        // 2. Insert/Update Consultation
        // Delete existing consultation and re-insert
        $stmt = $pdo->prepare("DELETE FROM fam_consultation WHERE fam_general_info_id = ?");
        $stmt->execute([$recordId]);

        $sql = "INSERT INTO fam_consultation (
            fam_general_info_id, is_surgical, method_offered, history_heart_disease, history_liver_disease,
            history_kidney_disease, history_diabetes, history_hypertension, gravidity, parity, birth_spacing,
            living_children, children_deceased, desired_children, lmp_first_date, pregnancy_status,
            date_of_last_abortion, birth_limitation, menstrual_regularity, breastfeeding_status,
            sexual_activity_level, sexual_partners_count, pregnancy_complications, consultation_type,
            used_contraception_before, desired_fp_method, previous_methods_used, transport_to_facility,
            transport_back_home, referring_facility, knowledge_of_other_methods, person_informed_client,
            rumors_heard_about_fp, reasons_not_wanting_children, respiratory_illness, anemia_bleeding_dis,
            genital_abnormalities, current_medications, weight_kg, height_cm, temperature_c, rr,
            pulse_bpm, spo2_percentage, general_condition, heart_observations, abdomen_palpation
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $recordId, $input['is_surgical'] ?? 'no', $input['method_offered'] ?? null,
            $input['history_heart_disease'] ?? 0, $input['history_liver_disease'] ?? 0,
            $input['history_kidney_disease'] ?? 0, $input['history_diabetes'] ?? 0, $input['history_hypertension'] ?? 0,
            $input['gravidity'] ?? null, $input['parity'] ?? null, $input['birth_spacing'] ?? null,
            $input['living_children'] ?? null, $input['children_deceased'] ?? null, $input['desired_children'] ?? null,
            $input['lmp_first_date'] ?? null, $input['pregnancy_status'] ?? null,
            $input['date_of_last_abortion'] ?? null, $input['birth_limitation'] ?? null,
            $input['menstrual_regularity'] ?? null, $input['breastfeeding_status'] ?? null,
            $input['sexual_activity_level'] ?? null, $input['sexual_partners_count'] ?? null,
            $input['pregnancy_complications'] ?? null, $input['consultation_type'] ?? null,
            $input['used_contraception_before'] ?? null, $input['desired_fp_method'] ?? null,
            $input['previous_methods_used'] ?? null, $input['transport_to_facility'] ?? null,
            $input['transport_back_home'] ?? null, $input['referring_facility'] ?? null,
            $input['knowledge_of_other_methods'] ?? null, $input['person_informed_client'] ?? null,
            $input['rumors_heard_about_fp'] ?? null, $input['reasons_not_wanting_children'] ?? null,
            $input['respiratory_illness'] ?? null, $input['anemia_bleeding_dis'] ?? null,
            $input['genital_abnormalities'] ?? null, $input['current_medications'] ?? null,
            $input['weight_kg'] ?? null, $input['height_cm'] ?? null, $input['temperature_c'] ?? null,
            $input['rr'] ?? null, $input['pulse_bpm'] ?? null, $input['spo2_percentage'] ?? null,
            $input['general_condition'] ?? null, $input['heart_observations'] ?? null, $input['abdomen_palpation'] ?? null
        ]);

        // 3. Save Investigations
        $stmt = $pdo->prepare("DELETE FROM fam_investigations WHERE fam_general_info_id = ?");
        $stmt->execute([$recordId]);

        if (!empty($input['investigations'])) {
            $invStmt = $pdo->prepare("INSERT INTO fam_investigations (fam_general_info_id, examination_name, results, date_performed) VALUES (?, ?, ?, ?)");
            foreach ($input['investigations'] as $inv) {
                if (!empty($inv['examination_name'])) {
                    $invStmt->execute([
                        $recordId,
                        $inv['examination_name'],
                        $inv['results'] ?? null,
                        $inv['date_performed'] ?? null
                    ]);
                }
            }
        }

        // 4. Insert/Update Conclusion
        $stmt = $pdo->prepare("DELETE FROM fam_conclusion WHERE fam_general_info_id = ?");
        $stmt->execute([$recordId]);

        $sql = "INSERT INTO fam_conclusion (
            fam_general_info_id, diagnosis, conclusion_text, eligible_fp_methods, method_initiation_date,
            first_followup_date, method_duration, chosen_method, method_offered_by_provider
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $recordId, $input['diagnosis'] ?? null, $input['conclusion_text'] ?? null,
            $input['eligible_fp_methods'] ?? null, $input['method_initiation_date'] ?? null,
            $input['first_followup_date'] ?? null, $input['method_duration'] ?? null,
            $input['chosen_method'] ?? null, $input['method_offered_by_provider'] ?? null
        ]);

        // 5. Save Treatments
        $stmt = $pdo->prepare("DELETE FROM fam_treatments WHERE fam_general_info_id = ?");
        $stmt->execute([$recordId]);

        if (!empty($input['treatments'])) {
            $treatStmt = $pdo->prepare("INSERT INTO fam_treatments (fam_general_info_id, medication_name, dosage, route, frequency, duration, date_prescribed) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($input['treatments'] as $t) {
                if (!empty($t['medication_name'])) {
                    $treatStmt->execute([
                        $recordId,
                        $t['medication_name'],
                        $t['dosage'] ?? null,
                        $t['route'] ?? null,
                        $t['frequency'] ?? null,
                        $t['duration'] ?? null,
                        $t['date_prescribed'] ?? null
                    ]);
                }
            }
        }

        // 6. Save Follow-ups (only save one follow-up per record for now - latest)
        $stmt = $pdo->prepare("DELETE FROM fam_followup WHERE fam_general_info_id = ?");
        $stmt->execute([$recordId]);

        // Check if followup data exists in the main form (from followUp tab)
        if (!empty($input['visit_date']) || !empty($input['chief_complaints'])) {
            $sql = "INSERT INTO fam_followup (
                fam_general_info_id, visit_date, consultation_type, practitioner_name, weight_kg,
                liver_examination, breast_examination, bp_systolic, bp_diastolic, bp_interpretation,
                chief_complaints, previous_fp_method, fp_method_provided, method_duration, next_appointment_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $recordId, $input['visit_date'] ?? null, $input['consultation_type'] ?? null,
                $input['practitioner_name'] ?? null, $input['weight_kg'] ?? null,
                $input['liver_examination'] ?? null, $input['breast_examination'] ?? null,
                $input['bp_systolic'] ?? null, $input['bp_diastolic'] ?? null,
                $input['bp_interpretation'] ?? null, $input['chief_complaints'] ?? null,
                $input['previous_fp_method'] ?? null, $input['fp_method_provided'] ?? null,
                $input['method_duration'] ?? null, $input['next_appointment_date'] ?? null
            ]);
        }

        // 7. Save Discharge
        $stmt = $pdo->prepare("DELETE FROM fam_discharge WHERE fam_general_info_id = ?");
        $stmt->execute([$recordId]);

        if (!empty($input['discharging_status'])) {
            $sql = "INSERT INTO fam_discharge (fam_general_info_id, discharging_status, discharge_date) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$recordId, $input['discharging_status'] ?? null, $input['discharge_date'] ?? null]);
        }

        // 8. Save vitals to vital_sign table (if patient_id exists)
        if ($patientId) {
            $vitals = [
                'weight_kg' => $input['weight_kg'] ?? null,
                'height_cm' => $input['height_cm'] ?? null,
                'temperature_c' => $input['temperature_c'] ?? null,
                'rr' => $input['rr'] ?? null,
                'pulse_bpm' => $input['pulse_bpm'] ?? null,
                'spo2_percentage' => $input['spo2_percentage'] ?? null
            ];
            saveVitalSigns($patientId, $vitals, 5, null, $input['consultation_date'] ?? null);

            // Also save follow-up vitals if they exist
            if (!empty($input['visit_date'])) {
                $followupVitals = [
                    'weight_kg' => $input['weight_kg'] ?? null,
                    'bp_systolic' => $input['bp_systolic'] ?? null,
                    'bp_diastolic' => $input['bp_diastolic'] ?? null
                ];
                saveVitalSigns($patientId, $followupVitals, 5, null, $input['visit_date']);
            }
        }

        // 9. Save investigations as diagnostics (if patient_id exists)
        if ($patientId && !empty($input['investigations'])) {
            saveInvestigationsAsDiagnostics($patientId, $input['investigations'], $input['practitioner_name'] ?? 'FAM');
        }

        // 10. Save treatments as orders (if patient_id exists)
        if ($patientId && !empty($input['treatments'])) {
            saveTreatmentsAsOrders($patientId, $input['treatments'], 5, $input['practitioner_name'] ?? 'FAM');
        }

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'record_id' => $recordId,
            'message' => 'Record saved successfully'
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Invalid request method
jsonResponse(['error' => 'Method not allowed'], 405);
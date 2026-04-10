<?php
/**
 * Helper Functions for Family Planning API
 */

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function getJsonInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        parse_str($input, $data);
    }
    return $data ?? [];
}

function sanitizeInput($value) {
    if ($value === null || $value === '') return null;
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function getPatientIdByUpid($upid, $pdo) {
    if (empty($upid)) return null;

    $stmt = $pdo->prepare("SELECT patient_id FROM upid_patients WHERE upid = ? LIMIT 1");
    $stmt->execute([$upid]);
    $result = $stmt->fetch();
    return $result ? $result['patient_id'] : null;
}

function saveVitalSigns($patient_id, $vitals, $service = 5, $user_id = null, $date = null) {
    if (!$patient_id || empty($vitals)) return;

    $pdo = getDB();
    $vitalMap = [
        'weight_kg' => 'Weight',
        'height_cm' => 'Height',
        'temperature_c' => 'Temperature',
        'rr' => 'RR',
        'pulse_bpm' => 'Pulse',
        'spo2_percentage' => 'SpO2',
        'bp_systolic' => 'BP Systolic',
        'bp_diastolic' => 'BP Diastolic'
    ];

    $date = $date ?? date('Y-m-d');

    foreach ($vitals as $field => $value) {
        if (isset($vitalMap[$field]) && $value !== null && $value !== '') {
            // Get or create vital_id
            $vitalName = $vitalMap[$field];
            $stmt = $pdo->prepare("SELECT vital_id FROM vital WHERE vital_name = ?");
            $stmt->execute([$vitalName]);
            $vital = $stmt->fetch();

            if (!$vital) {
                $stmt = $pdo->prepare("INSERT INTO vital (vital_name) VALUES (?)");
                $stmt->execute([$vitalName]);
                $vitalId = $pdo->lastInsertId();
            } else {
                $vitalId = $vital['vital_id'];
            }

            // Insert vital sign - use 0 as default for user_id if not provided
            $userId = $user_id ?? 0;
            $stmt = $pdo->prepare("INSERT INTO vital_sign (patient_id, vital_id, value, date, service, user_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$patient_id, $vitalId, $value, $date, $service, $userId]);
        }
    }
}

function saveInvestigationsAsDiagnostics($patient_id, $investigations, $user = null) {
    if (!$patient_id || empty($investigations)) return;

    $pdo = getDB();
    $date = date('Y-m-d');

    foreach ($investigations as $inv) {
        $examName = sanitizeInput($inv['examination_name']);
        $results = sanitizeInput($inv['results']);
        $performedDate = $inv['date_performed'] ?? $date;

        if ($examName) {
            // Check if diagnostic exists, if not create it
            $stmt = $pdo->prepare("SELECT id FROM diags WHERE english = ? OR french = ? LIMIT 1");
            $stmt->execute([$examName, $examName]);
            $diag = $stmt->fetch();

            if (!$diag) {
                $stmt = $pdo->prepare("INSERT INTO diags (code, english, french) VALUES (?, ?, ?)");
                $code = 'FP-' . strtoupper(substr(md5($examName), 0, 6));
                $stmt->execute([$code, $examName, $examName]);
                $diagId = $pdo->lastInsertId();
            } else {
                $diagId = $diag['id'];
            }

            // Save to diag_client
            $stmt = $pdo->prepare("INSERT INTO diag_client (client_id, diag_id, date, ref, user) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$patient_id, $diagId, $performedDate, $results, $user ?? 'FAM']);
        }
    }
}

function saveTreatmentsAsOrders($patient_id, $treatments, $service = 5, $user = null) {
    if (!$patient_id || empty($treatments)) return;

    $pdo = getDB();
    $date = date('Y-m-d');

    foreach ($treatments as $t) {
        $medName = sanitizeInput($t['medication_name']);
        $dosage = sanitizeInput($t['dosage']);
        $duration = sanitizeInput($t['duration']);
        $prescribedDate = $t['date_prescribed'] ?? $date;

        if ($medName) {
            // Check if product exists
            $stmt = $pdo->prepare("SELECT prod_id FROM products WHERE description LIKE ? LIMIT 1");
            $stmt->execute(['%' . $medName . '%']);
            $product = $stmt->fetch();

            $item = $medName . ($dosage ? ' ' . $dosage : '') . ($duration ? ' for ' . $duration : '');

            // Save to orders
            $stmt = $pdo->prepare("INSERT INTO orders (client_id, item, type, quantity, service, date, user, done) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$patient_id, $item, 'treatment', 1, $service, $prescribedDate, $user ?? 'FAM', 1]);
        }
    }
}

function saveConsumablesAsOrders($patient_id, $consumables, $service = 5, $user = null) {
    if (!$patient_id || empty($consumables)) return;

    $pdo = getDB();
    $date = date('Y-m-d');

    foreach ($consumables as $c) {
        $item = sanitizeInput($c['item'] ?? $c['name']);
        if ($item) {
            $stmt = $pdo->prepare("INSERT INTO orders (client_id, item, type, quantity, service, date, user, done) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$patient_id, $item, 'consumable', 1, $service, $date, $user ?? 'FAM', 1]);
        }
    }
}
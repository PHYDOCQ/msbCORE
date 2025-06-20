<?php
/**
 * DamageAssessment Class
 * Handles vehicle damage assessment for body repair and paint jobs
 * Compatible with PHP 8.2.6
 */

class DamageAssessment {
    private $db;
    private $table = 'damage_assessments';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create new damage assessment
     */
    public function create(array $data): int {
        $this->db->beginTransaction();
        
        try {
            // Validate required fields
            $requiredFields = ['work_order_id', 'vehicle_id', 'assessor_id', 'damage_type', 'damage_location'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field {$field} is required");
                }
            }
            
            // Set default values
            $data['assessment_date'] = $data['assessment_date'] ?? date('Y-m-d');
            $data['damage_severity'] = $data['damage_severity'] ?? 'moderate';
            $data['status'] = $data['status'] ?? 'pending';
            $data['created_at'] = date('Y-m-d H:i:s');
            
            // Handle photos JSON
            if (isset($data['photos_before']) && is_array($data['photos_before'])) {
                $data['photos_before'] = json_encode($data['photos_before']);
            }
            
            $assessmentId = $this->db->insert($this->table, $data);
            
            // Log activity
            $this->logActivity($assessmentId, 'create', 'Damage assessment created');
            
            $this->db->commit();
            return $assessmentId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get assessment by ID
     */
    public function findById(int $id): ?array {
        $assessment = $this->db->selectOne(
            "SELECT da.*, wo.work_order_number, v.license_plate, v.brand, v.model, 
                    u.full_name as assessor_name
             FROM {$this->table} da
             LEFT JOIN work_orders wo ON da.work_order_id = wo.id
             LEFT JOIN vehicles v ON da.vehicle_id = v.id
             LEFT JOIN users u ON da.assessor_id = u.id
             WHERE da.id = :id",
            ['id' => $id]
        );
        
        if ($assessment && $assessment['photos_before']) {
            $assessment['photos_before'] = json_decode($assessment['photos_before'], true);
        }
        
        return $assessment ?: null;
    }
    
    /**
     * Get assessments by work order
     */
    public function getByWorkOrder(int $workOrderId): array {
        $assessments = $this->db->select(
            "SELECT da.*, u.full_name as assessor_name
             FROM {$this->table} da
             LEFT JOIN users u ON da.assessor_id = u.id
             WHERE da.work_order_id = :work_order_id
             ORDER BY da.created_at DESC",
            ['work_order_id' => $workOrderId]
        );
        
        // Decode JSON fields
        foreach ($assessments as &$assessment) {
            if ($assessment['photos_before']) {
                $assessment['photos_before'] = json_decode($assessment['photos_before'], true);
            }
        }
        
        return $assessments;
    }
    
    /**
     * Update assessment
     */
    public function update(int $id, array $data): bool {
        try {
            // Handle photos JSON
            if (isset($data['photos_before']) && is_array($data['photos_before'])) {
                $data['photos_before'] = json_encode($data['photos_before']);
            }
            
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            $result = $this->db->update(
                $this->table,
                $data,
                'id = :id',
                ['id' => $id]
            );
            
            if ($result) {
                $this->logActivity($id, 'update', 'Damage assessment updated');
            }
            
            return $result;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get assessment statistics
     */
    public function getStatistics(array $filters = []): array {
        $whereClause = '1=1';
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $whereClause .= ' AND da.assessment_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause .= ' AND da.assessment_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['damage_type'])) {
            $whereClause .= ' AND da.damage_type = :damage_type';
            $params['damage_type'] = $filters['damage_type'];
        }
        
        $stats = $this->db->selectOne(
            "SELECT 
                COUNT(*) as total_assessments,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_count,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
                COUNT(CASE WHEN insurance_claim = 1 THEN 1 END) as insurance_claims,
                AVG(estimated_cost) as avg_estimated_cost,
                SUM(estimated_cost) as total_estimated_cost
             FROM {$this->table} da
             WHERE {$whereClause}",
            $params
        );
        
        // Get damage type breakdown
        $damageTypes = $this->db->select(
            "SELECT damage_type, COUNT(*) as count, AVG(estimated_cost) as avg_cost
             FROM {$this->table} da
             WHERE {$whereClause}
             GROUP BY damage_type
             ORDER BY count DESC",
            $params
        );
        
        return [
            'overview' => $stats,
            'damage_types' => $damageTypes
        ];
    }
    
    /**
     * Upload assessment photos
     */
    public function uploadPhotos(int $assessmentId, array $files): array {
        try {
            $uploadedFiles = [];
            $uploadDir = 'assessments/' . $assessmentId;
            
            foreach ($files as $file) {
                $result = Utils::uploadFile($file, $uploadDir, ALLOWED_IMAGE_EXTENSIONS);
                if ($result['success']) {
                    $uploadedFiles[] = [
                        'filename' => $result['filename'],
                        'original_name' => $result['original_name'],
                        'path' => $result['path'],
                        'size' => $result['size'],
                        'uploaded_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
            
            // Update assessment with new photos
            if (!empty($uploadedFiles)) {
                $assessment = $this->findById($assessmentId);
                $existingPhotos = $assessment['photos_before'] ?? [];
                $allPhotos = array_merge($existingPhotos, $uploadedFiles);
                
                $this->update($assessmentId, ['photos_before' => $allPhotos]);
            }
            
            return $uploadedFiles;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Generate assessment report
     */
    public function generateReport(int $assessmentId): array {
        $assessment = $this->findById($assessmentId);
        if (!$assessment) {
            throw new Exception('Assessment not found');
        }
        
        // Get related repair tasks
        $repairTasks = $this->db->select(
            "SELECT * FROM body_repair_tasks 
             WHERE damage_assessment_id = :assessment_id
             ORDER BY created_at",
            ['assessment_id' => $assessmentId]
        );
        
        // Calculate totals
        $totalEstimatedHours = array_sum(array_column($repairTasks, 'estimated_hours'));
        $totalActualHours = array_sum(array_column($repairTasks, 'actual_hours'));
        
        return [
            'assessment' => $assessment,
            'repair_tasks' => $repairTasks,
            'summary' => [
                'total_estimated_hours' => $totalEstimatedHours,
                'total_actual_hours' => $totalActualHours,
                'efficiency_rate' => $totalEstimatedHours > 0 ? 
                    round(($totalActualHours / $totalEstimatedHours) * 100, 2) : 0
            ]
        ];
    }
    
    /**
     * Log activity
     */
    private function logActivity(int $assessmentId, string $action, string $description): void {
        if (isset($_SESSION['user_id'])) {
            $this->db->insert('user_activities', [
                'user_id' => $_SESSION['user_id'],
                'action' => "assessment_{$action}",
                'description' => $description,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
}
?>

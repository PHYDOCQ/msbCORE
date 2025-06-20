<?php
/**
 * PaintJob Class
 * Handles paint job management for automotive workshop
 * Compatible with PHP 8.2.6
 */

class PaintJob {
    private $db;
    private $table = 'paint_jobs';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create new paint job
     */
    public function create(array $data): int {
        $this->db->beginTransaction();
        
        try {
            // Validate required fields
            $requiredFields = ['work_order_id', 'vehicle_id', 'paint_type', 'painter_id'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field {$field} is required");
                }
            }
            
            // Set default values
            $data['layers_count'] = $data['layers_count'] ?? 1;
            $data['status'] = $data['status'] ?? 'scheduled';
            $data['quality_check'] = $data['quality_check'] ?? 'pending';
            $data['created_at'] = date('Y-m-d H:i:s');
            
            // Handle JSON fields
            if (isset($data['photos_progress']) && is_array($data['photos_progress'])) {
                $data['photos_progress'] = json_encode($data['photos_progress']);
            }
            if (isset($data['photos_final']) && is_array($data['photos_final'])) {
                $data['photos_final'] = json_encode($data['photos_final']);
            }
            
            // Check booth availability if specified
            if (!empty($data['booth_number'])) {
                $this->checkBoothAvailability($data['booth_number'], $data['start_time'] ?? null);
            }
            
            $paintJobId = $this->db->insert($this->table, $data);
            
            // Reserve booth if specified
            if (!empty($data['booth_number'])) {
                $this->reserveBooth($data['booth_number'], $data['work_order_id']);
            }
            
            // Log activity
            $this->logActivity($paintJobId, 'create', 'Paint job created');
            
            $this->db->commit();
            return $paintJobId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get paint job by ID
     */
    public function findById(int $id): ?array {
        $paintJob = $this->db->selectOne(
            "SELECT pj.*, wo.work_order_number, v.license_plate, v.brand, v.model,
                    u1.full_name as painter_name, u2.full_name as quality_checker_name,
                    wb.bay_name as booth_name
             FROM {$this->table} pj
             LEFT JOIN work_orders wo ON pj.work_order_id = wo.id
             LEFT JOIN vehicles v ON pj.vehicle_id = v.id
             LEFT JOIN users u1 ON pj.painter_id = u1.id
             LEFT JOIN users u2 ON pj.quality_checker_id = u2.id
             LEFT JOIN workshop_bays wb ON pj.booth_number = wb.bay_number
             WHERE pj.id = :id",
            ['id' => $id]
        );
        
        if ($paintJob) {
            // Decode JSON fields
            if ($paintJob['photos_progress']) {
                $paintJob['photos_progress'] = json_decode($paintJob['photos_progress'], true);
            }
            if ($paintJob['photos_final']) {
                $paintJob['photos_final'] = json_decode($paintJob['photos_final'], true);
            }
        }
        
        return $paintJob ?: null;
    }
    
    /**
     * Get paint jobs by work order
     */
    public function getByWorkOrder(int $workOrderId): array {
        $paintJobs = $this->db->select(
            "SELECT pj.*, u1.full_name as painter_name, u2.full_name as quality_checker_name
             FROM {$this->table} pj
             LEFT JOIN users u1 ON pj.painter_id = u1.id
             LEFT JOIN users u2 ON pj.quality_checker_id = u2.id
             WHERE pj.work_order_id = :work_order_id
             ORDER BY pj.created_at DESC",
            ['work_order_id' => $workOrderId]
        );
        
        // Decode JSON fields
        foreach ($paintJobs as &$paintJob) {
            if ($paintJob['photos_progress']) {
                $paintJob['photos_progress'] = json_decode($paintJob['photos_progress'], true);
            }
            if ($paintJob['photos_final']) {
                $paintJob['photos_final'] = json_decode($paintJob['photos_final'], true);
            }
        }
        
        return $paintJobs;
    }
    
    /**
     * Update paint job
     */
    public function update(int $id, array $data): bool {
        try {
            // Handle JSON fields
            if (isset($data['photos_progress']) && is_array($data['photos_progress'])) {
                $data['photos_progress'] = json_encode($data['photos_progress']);
            }
            if (isset($data['photos_final']) && is_array($data['photos_final'])) {
                $data['photos_final'] = json_encode($data['photos_final']);
            }
            
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            $result = $this->db->update(
                $this->table,
                $data,
                'id = :id',
                ['id' => $id]
            );
            
            if ($result) {
                $this->logActivity($id, 'update', 'Paint job updated');
            }
            
            return $result;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Start paint job
     */
    public function startJob(int $id): bool {
        try {
            $paintJob = $this->findById($id);
            if (!$paintJob) {
                throw new Exception('Paint job not found');
            }
            
            if ($paintJob['status'] !== 'scheduled') {
                throw new Exception('Paint job is not in scheduled status');
            }
            
            $result = $this->update($id, [
                'status' => 'in_progress',
                'start_time' => date('Y-m-d H:i:s')
            ]);
            
            if ($result) {
                $this->logActivity($id, 'start', 'Paint job started');
            }
            
            return $result;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Complete paint job
     */
    public function completeJob(int $id, array $completionData = []): bool {
        try {
            $paintJob = $this->findById($id);
            if (!$paintJob) {
                throw new Exception('Paint job not found');
            }
            
            $updateData = [
                'status' => 'completed',
                'end_time' => date('Y-m-d H:i:s')
            ];
            
            // Add completion data if provided
            if (!empty($completionData['photos_final'])) {
                $updateData['photos_final'] = $completionData['photos_final'];
            }
            if (!empty($completionData['notes'])) {
                $updateData['notes'] = $completionData['notes'];
            }
            
            $result = $this->update($id, $updateData);
            
            if ($result) {
                // Release booth
                if ($paintJob['booth_number']) {
                    $this->releaseBooth($paintJob['booth_number']);
                }
                
                $this->logActivity($id, 'complete', 'Paint job completed');
            }
            
            return $result;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get paint job statistics
     */
    public function getStatistics(array $filters = []): array {
        $whereClause = '1=1';
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $whereClause .= ' AND DATE(pj.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause .= ' AND DATE(pj.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['painter_id'])) {
            $whereClause .= ' AND pj.painter_id = :painter_id';
            $params['painter_id'] = $filters['painter_id'];
        }
        
        $stats = $this->db->selectOne(
            "SELECT 
                COUNT(*) as total_jobs,
                COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_count,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_count,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
                COUNT(CASE WHEN quality_check = 'passed' THEN 1 END) as quality_passed,
                COUNT(CASE WHEN quality_check = 'failed' THEN 1 END) as quality_failed,
                AVG(surface_area) as avg_surface_area,
                AVG(TIMESTAMPDIFF(HOUR, start_time, end_time)) as avg_completion_hours
             FROM {$this->table} pj
             WHERE {$whereClause}",
            $params
        );
        
        // Get paint type breakdown
        $paintTypes = $this->db->select(
            "SELECT paint_type, COUNT(*) as count, AVG(surface_area) as avg_area
             FROM {$this->table} pj
             WHERE {$whereClause}
             GROUP BY paint_type
             ORDER BY count DESC",
            $params
        );
        
        // Get painter performance
        $painterStats = $this->db->select(
            "SELECT u.full_name as painter_name, 
                    COUNT(*) as jobs_completed,
                    AVG(TIMESTAMPDIFF(HOUR, pj.start_time, pj.end_time)) as avg_hours,
                    COUNT(CASE WHEN pj.quality_check = 'passed' THEN 1 END) as quality_passed
             FROM {$this->table} pj
             JOIN users u ON pj.painter_id = u.id
             WHERE {$whereClause} AND pj.status = 'completed'
             GROUP BY pj.painter_id, u.full_name
             ORDER BY jobs_completed DESC",
            $params
        );
        
        return [
            'overview' => $stats,
            'paint_types' => $paintTypes,
            'painter_performance' => $painterStats
        ];
    }
    
    /**
     * Check booth availability
     */
    private function checkBoothAvailability(string $boothNumber, ?string $startTime = null): bool {
        $booth = $this->db->selectOne(
            "SELECT * FROM workshop_bays WHERE bay_number = :booth_number",
            ['booth_number' => $boothNumber]
        );
        
        if (!$booth) {
            throw new Exception('Paint booth not found');
        }
        
        if ($booth['status'] !== 'available') {
            throw new Exception('Paint booth is not available');
        }
        
        return true;
    }
    
    /**
     * Reserve booth
     */
    private function reserveBooth(string $boothNumber, int $workOrderId): bool {
        return $this->db->update(
            'workshop_bays',
            [
                'status' => 'occupied',
                'current_work_order_id' => $workOrderId,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'bay_number = :booth_number',
            ['booth_number' => $boothNumber]
        );
    }
    
    /**
     * Release booth
     */
    private function releaseBooth(string $boothNumber): bool {
        return $this->db->update(
            'workshop_bays',
            [
                'status' => 'available',
                'current_work_order_id' => null,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'bay_number = :booth_number',
            ['booth_number' => $boothNumber]
        );
    }
    
    /**
     * Log activity
     */
    private function logActivity(int $paintJobId, string $action, string $description): void {
        if (isset($_SESSION['user_id'])) {
            $this->db->insert('user_activities', [
                'user_id' => $_SESSION['user_id'],
                'action' => "paint_job_{$action}",
                'description' => $description,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
}
?>

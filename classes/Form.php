
<?php

class Form {
    private $conn;
    private $table_name = "forms";

    // Form properties
    public $id;
    public $form_type;
    public $title;
    public $description;
    public $is_active;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create new form
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                 SET form_type = :form_type,
                     title = :title,
                     description = :description,
                     is_active = :is_active";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->form_type = htmlspecialchars(strip_tags($this->form_type));
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->is_active = (int)$this->is_active;

        // Bind parameters
        $stmt->bindParam(':form_type', $this->form_type);
        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':is_active', $this->is_active);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Get all forms
     */
    public function read() {
        $query = "SELECT id, form_type, title, description, is_active, created_at
                  FROM " . $this->table_name . "
                  ORDER BY form_type, title";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Get form by ID
     */
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->form_type = $row['form_type'];
            $this->title = $row['title'];
            $this->description = $row['description'];
            $this->is_active = $row['is_active'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    /**
     * Update form
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET title = :title,
                      description = :description,
                      is_active = :is_active
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->is_active = (int)$this->is_active;

        // Bind parameters
        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Delete form
     */
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }

   /**
     * Get form sections with questions
     */
    public function getFormStructure($viewer_type = 'both') {
        $query = "SELECT fs.id as section_id, fs.section_title, fs.section_description, fs.section_order,
                         fs.visible_to,
                         fq.id as question_id, fq.question_text, fq.question_description,
                         fq.response_type, fq.options, fq.is_required, fq.question_order
                  FROM form_sections fs
                  LEFT JOIN form_questions fq ON fs.id = fq.section_id AND fq.is_active = 1
                  WHERE fs.form_id = :form_id AND fs.is_active = 1
                  AND (fs.visible_to = 'both' 
                       OR fs.visible_to = :viewer_type
                       OR (:viewer_type = 'both' AND fs.visible_to IN ('employee', 'reviewer')))
                  ORDER BY fs.section_order, fq.question_order";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':form_id', $this->id);
        $stmt->bindParam(':viewer_type', $viewer_type);
        $stmt->execute();

        $structure = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $section_id = $row['section_id'];
            
            if (!isset($structure[$section_id])) {
                $structure[$section_id] = [
                    'id' => $section_id,
                    'title' => $row['section_title'],
                    'description' => $row['section_description'],
                    'order' => $row['section_order'],
                    'visible_to' => $row['visible_to'],
                    'questions' => []
                ];
            }

            if ($row['question_id']) {
                $structure[$section_id]['questions'][] = [
                    'id' => $row['question_id'],
                    'text' => $row['question_text'],
                    'description' => $row['question_description'],
                    'response_type' => $row['response_type'],
                    'options' => !is_null($row['options']) ? json_decode($row['options'], true) : null,
                    'is_required' => $row['is_required'],
                    'order' => $row['question_order']
                ];
            }
        }

        return array_values($structure);
    }
    /**
     * Get form by user role
     */
    public function getFormByRole($role) {
        $role = strtolower(trim($role));

        $form_type_map = [
            'manager' => 'management',
            'employee' => 'general',
            'worker' => 'worker'
        ];

        $form_type = $form_type_map[$role] ?? 'general';

        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE form_type = :form_type AND is_active = 1 
                  ORDER BY created_at DESC
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':form_type', $form_type);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->id = $row['id'];
            $this->form_type = $row['form_type'];
            $this->title = $row['title'];
            $this->description = $row['description'];
            $this->is_active = $row['is_active'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    /**
     * Count forms
     */
    public function count() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }
/**
     * Get form sections with questions filtered by visibility
     */
    public function getFormStructureFiltered($viewer_type = 'both') {
        $query = "SELECT fs.id as section_id, fs.section_title, fs.section_description, 
                         fs.section_order, fs.visible_to,
                         fq.id as question_id, fq.question_text, fq.question_description,
                         fq.response_type, fq.options, fq.is_required, fq.question_order
                  FROM form_sections fs
                  LEFT JOIN form_questions fq ON fs.id = fq.section_id AND fq.is_active = 1
                  WHERE fs.form_id = :form_id AND fs.is_active = 1
                  AND (fs.visible_to = 'both' OR fs.visible_to = :viewer_type)
                  ORDER BY fs.section_order, fq.question_order";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':form_id', $this->id);
        $stmt->bindParam(':viewer_type', $viewer_type);
        $stmt->execute();

        $structure = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $section_id = $row['section_id'];
            
            if (!isset($structure[$section_id])) {
                $structure[$section_id] = [
                    'id' => $section_id,
                    'title' => $row['section_title'],
                    'description' => $row['section_description'],
                    'order' => $row['section_order'],
                    'visible_to' => $row['visible_to'],
                    'questions' => []
                ];
            }

            if ($row['question_id']) {
                $structure[$section_id]['questions'][] = [
                    'id' => $row['question_id'],
                    'text' => $row['question_text'],
                    'description' => $row['question_description'],
                    'response_type' => $row['response_type'],
                    'options' => !is_null($row['options']) ? json_decode($row['options'], true) : null,
                    'is_required' => $row['is_required'],
                    'order' => $row['question_order']
                ];
            }
        }

        return array_values($structure);
    }
    /**
     * Check if form is being used in appraisals
     */
    public function isInUse() {
        $query = "SELECT COUNT(*) as count FROM appraisals WHERE form_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'] > 0;
    }
}
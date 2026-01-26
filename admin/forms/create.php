
<?php
// admin/forms/create.php
require_once __DIR__ . '/../../config/config.php';

if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $form_type = sanitize($_POST['form_type'] ?? '');
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($form_type) || empty($title)) {
            $error_message = 'Form type and title are required.';
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();
                $form = new Form($db);

                $form->form_type = $form_type;
                $form->title = $title;
                $form->description = $description;
                $form->is_active = $is_active;

                if ($form->create()) {
                    logActivity($_SESSION['user_id'], 'CREATE', 'forms', $form->id, null, 
                               ['form_type' => $form_type, 'title' => $title], 
                               'Created new form: ' . $title);
                    
                    // Create default sections based on form type
                    createDefaultSections($db, $form->id, $form_type);
                    
                    redirect('index.php', 'Form created successfully!', 'success');
                } else {
                    $error_message = 'Failed to create form. Please try again.';
                }
            } catch (Exception $e) {
                error_log("Form create error: " . $e->getMessage());
                $error_message = 'An error occurred. Please try again.';
            }
        }
    }
}

/**
 * Create default sections for a form
 */
function createDefaultSections($db, $form_id, $form_type) {
    $sections = [
        ['title' => 'Cultural Values', 'description' => 'H³CIS Cultural Values Assessment', 'order' => 1],
        ['title' => 'Performance Assessment', 'description' => 'Core performance competencies evaluation', 'order' => 2],
        ['title' => 'Key Achievements in the Past Year', 'description' => 'Notable accomplishments and contributions', 'order' => 3],
        ['title' => 'Main Objectives for Next Year', 'description' => 'Goals and deliverables for the coming year', 'order' => 4],
        ['title' => 'Training and Development Needs', 'description' => 'Skill development and learning requirements', 'order' => 5]
    ];

    foreach ($sections as $section) {
        $query = "INSERT INTO form_sections (form_id, section_title, section_description, section_order) 
                  VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$form_id, $section['title'], $section['description'], $section['order']]);
        
        $section_id = $db->lastInsertId();
        
        // Create default questions based on section and form type
        createDefaultQuestions($db, $section_id, $section['title'], $form_type);
    }
}

/**
 * Create default questions for sections
 */
function createDefaultQuestions($db, $section_id, $section_title, $form_type) {
    $questions = [];
    
    switch ($section_title) {
        case 'Cultural Values':
            $cultural_values = [
                'Hard Work - Commitment to diligence and perseverance in all aspects of Operations',
                'Honesty - Integrity in dealings with customers, partners and stakeholders',
                'Harmony - Fostering Collaborative relationships and a balanced work environment',
                'Customer Focus - Striving to be the "Only Supplier of Choice" by enhancing customer competitiveness',
                'Innovation - Embracing transformation and agility, as symbolized by their "Evolving with Momentum" theme',
                'Sustainability - Rooted in organic growth and long-term value creation, reflected in their visual metaphors'
            ];
            
            foreach ($cultural_values as $index => $value) {
                $questions[] = [
                    'text' => $value,
                    'type' => 'textarea',
                    'order' => $index + 1
                ];
            }
            break;
            
        case 'Performance Assessment':
            $performance_areas = [
                'Striving for Excellence - Consistently pursue and achieve outstanding performance',
                'Momentum for Growth - Set up and communicate clear goals and strategies',
                'Accountability for Results - Be eager to take up job accountability and ownership',
                'Recognition for Achievement - Provide ongoing and constructive feedback on work performance',
                'Teamwork with Fun - Motivate and influence people to achieve common goals through teamwork'
            ];
            
            // Add additional areas based on form type
            if ($form_type == 'management') {
                $performance_areas = array_merge($performance_areas, [
                    'Leadership - Lead by example and serve as a role model',
                    'Building Partnership & Customer Orientation - Adopt a positive and proactive approach',
                    'Planning and Organizing - Prioritize, coordinate and organize various resources',
                    'Decision Making - Be able to identify root cause of a problem and use objective data',
                    'Innovation and Change - Be aware of changing market needs and capitalize on opportunities',
                    'Communication Skills - Listen actively and understand the needs and expectations',
                    'Strategic Mentality - Build keen business acumen and be familiar with industry characteristics'
                ]);
            } elseif ($form_type == 'general') {
                $performance_areas = array_merge($performance_areas, [
                    'Building Partnership & Customer Orientation - Adopt a positive and proactive approach',
                    'Planning and Organizing - Prioritize, coordinate and organize various resources',
                    'Decision Making - Be able to identify root cause of a problem and use objective data',
                    'Innovation and Change - Be aware of changing market needs and capitalize on opportunities',
                    'Communication Skills - Listen actively and understand the needs and expectations'
                ]);
            } elseif ($form_type == 'worker') {
                $performance_areas = array_merge($performance_areas, [
                    'Work Safety - Follow safety legislation and rules',
                    'Building Partnership & Customer Orientation - Adopt a positive and proactive approach',
                    'Communication Skills - Listen actively and understand the needs and expectations'
                ]);
            }
            
            foreach ($performance_areas as $index => $area) {
                $questions[] = [
                    'text' => $area,
                    'type' => 'rating_10',
                    'order' => $index + 1
                ];
            }
            break;
            
        case 'Key Achievements in the Past Year':
            $questions[] = [
                'text' => 'Describe your key achievements and contributions in the past year',
                'type' => 'textarea',
                'order' => 1
            ];
            break;
            
        case 'Main Objectives for Next Year':
            for ($i = 1; $i <= 5; $i++) {
                $questions[] = [
                    'text' => "Objective $i (Please specify deliverable with time frame)",
                    'type' => 'textarea',
                    'order' => $i
                ];
            }
            break;
            
        case 'Training and Development Needs':
            $training_options = [
                'Business Writing', 'Distribution Management', 'Product Knowledge',
                'Change Management', 'Finance for Non Finance Executives', 'Project Management',
                'Communication Skills', 'Leadership Skills', 'Risk Management',
                'Consultative Selling', 'Negotiation & Influencing Skills', 'Team Effectiveness & Cohesiveness',
                'Creative Thinking', 'Presentation Skills', 'Time Management',
                'Critical Thinking', 'Problem Solving & Decision Making', 'Value Proposition'
            ];
            
            if ($form_type == 'worker') {
                $training_options = ['5S Methodology', 'Microsoft Office', 'Quality Assurance Mindset', 
                                   'Communication Skills', 'Product Knowledge', 'Work Safety'];
            }
            
            $questions[] = [
                'text' => 'Current Training and Development Needs (Please select 3 areas with highest priority)',
                'type' => 'checkbox',
                'order' => 1,
                'options' => json_encode($training_options)
            ];
            
            $questions[] = [
                'text' => 'Progress Status or Comments on Last Year\'s Training Needs',
                'type' => 'textarea',
                'order' => 2
            ];
            break;
    }
    
    // Insert questions
    foreach ($questions as $question) {
        $query = "INSERT INTO form_questions (section_id, question_text, response_type, options, question_order) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $section_id, 
            $question['text'], 
            $question['type'],
            $question['options'] ?? null,
            $question['order']
        ]);
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-plus-circle me-2"></i>Create New Form
            </h1>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Forms
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Form Details</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="form_type" class="form-label">Form Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="form_type" name="form_type" required>
                            <option value="">Select form type...</option>
                            <option value="management" <?php echo (($_POST['form_type'] ?? '') == 'management') ? 'selected' : ''; ?>>
                                Management Staff
                            </option>
                            <option value="general" <?php echo (($_POST['form_type'] ?? '') == 'general') ? 'selected' : ''; ?>>
                                General Staff
                            </option>
                            <option value="worker" <?php echo (($_POST['form_type'] ?? '') == 'worker') ? 'selected' : ''; ?>>
                                Workers
                            </option>
                        </select>
                        <div class="form-text">Different form types have different performance assessment criteria.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Form Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" 
                               placeholder="Enter form title..." required
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  placeholder="Enter form description..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                   <?php echo isset($_POST['is_active']) ? 'checked' : 'checked'; ?>>
                            <label class="form-check-label" for="is_active">
                                Active (form can be used for appraisals)
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Create Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

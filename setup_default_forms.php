
<?php
require_once 'config/config.php';

echo "Performance Appraisal System - Default Forms Setup\n";
echo "==================================================\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if default forms already exist
    $stmt = $db->query("SELECT COUNT(*) as count FROM forms");
    $count = $stmt->fetch()['count'];
    
    if ($count > 0) {
        echo "Default forms already exist. Skipping setup.\n";
        exit;
    }
    
    echo "Setting up default forms...\n\n";
    
    // Create default forms
    $forms = [
        ['management', 'Performance Appraisal Form (For Management Staff)', 'Comprehensive appraisal form for management level staff'],
        ['general', 'Performance Appraisal Form (For General Staff)', 'Standard appraisal form for general staff members'],
        ['worker', 'Performance Appraisal Form (For Workers)', 'Simplified appraisal form for operational workers']
    ];
    
    foreach ($forms as $form_data) {
        echo "Creating form: {$form_data[1]}...\n";
        
        $form = new Form($db);
        $form->form_type = $form_data[0];
        $form->title = $form_data[1];
        $form->description = $form_data[2];
        $form->is_active = 1;
        
        if ($form->create()) {
            echo "✓ Form created successfully\n";
            
            // Create default sections for this form
            createDefaultSections($db, $form->id, $form_data[0]);
            echo "✓ Default sections and questions added\n\n";
        } else {
            echo "✗ Failed to create form\n\n";
        }
    }
    
    echo "Default forms setup completed successfully!\n";
    echo "\nYou can now:\n";
    echo "1. Login with admin@company.com / password\n";
    echo "2. Create users in Admin → User Management\n";
    echo "3. Customize forms in Admin → Form Management\n";
    
} catch (Exception $e) {
    echo "Error setting up default forms: " . $e->getMessage() . "\n";
    exit(1);
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
?>
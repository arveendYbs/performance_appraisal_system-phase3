
<?php
// admin/sections/create.php
require_once __DIR__ . '/../../config/config.php';

if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$form_id = $_GET['form_id'] ?? 0;
if (!$form_id) {
    redirect('../forms/index.php', 'Form ID is required.', 'error');
}

$error_message = '';

// Get form details
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $form_query = "SELECT title FROM forms WHERE id = ?";
    $stmt = $db->prepare($form_query);
    $stmt->execute([$form_id]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$form) {
        redirect('../forms/index.php', 'Form not found.', 'error');
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $error_message = 'Invalid request. Please try again.';
        } else {
            $section_title = sanitize($_POST['section_title'] ?? '');
            $section_description = sanitize($_POST['section_description'] ?? '');
            $section_order = intval($_POST['section_order'] ?? 1);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($section_title)) {
                $error_message = 'Section title is required.';
            } else {
                $query = "INSERT INTO form_sections (form_id, section_title, section_description, section_order, is_active, visible_to) 
                         VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$form_id, $section_title, $section_description, $section_order, $is_active, $_POST['visible_to'] ?? 'both'])) {
                    $section_id = $db->lastInsertId();
                    
                    logActivity($_SESSION['user_id'], 'CREATE', 'form_sections', $section_id, null,
                               ['form_id' => $form_id, 'title' => $section_title],
                               'Created section: ' . $section_title);
                    
                    redirect('index.php?form_id=' . $form_id, 'Section created successfully!', 'success');
                } else {
                    $error_message = 'Failed to create section. Please try again.';
                }
            }
        }
    }
    
    // Get next order number
    $order_query = "SELECT COALESCE(MAX(section_order), 0) + 1 as next_order FROM form_sections WHERE form_id = ?";
    $stmt = $db->prepare($order_query);
    $stmt->execute([$form_id]);
    $next_order = $stmt->fetch()['next_order'];
    
} catch (Exception $e) {
    error_log("Section create error: " . $e->getMessage());
    redirect('../forms/index.php', 'An error occurred.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-plus-circle me-2"></i>Add New Section
                </h1>
                <small class="text-muted">Form: <?php echo htmlspecialchars($form['title']); ?></small>
            </div>
            <a href="index.php?form_id=<?php echo $form_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Sections
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Section Details</h5>
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
                        <label for="section_title" class="form-label">Section Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="section_title" name="section_title" required
                               placeholder="Enter section title..."
                               value="<?php echo htmlspecialchars($_POST['section_title'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="section_description" class="form-label">Description</label>
                        <textarea class="form-control" id="section_description" name="section_description" rows="3"
                                  placeholder="Enter section description..."><?php echo htmlspecialchars($_POST['section_description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="section_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="section_order" name="section_order" 
                               min="1" value="<?php echo $next_order; ?>">
                        <div class="form-text">Sections will be displayed in this order</div>
                    </div>
                    <div class="mb-3">
                        <label for="visible_to" class="form-label">Section Visibility <span class="text-danger">*</span></label>
                        <select class="form-select" id="visible_to" name="visible_to" required>
                            <option value="both" <?php echo (($_POST['visible_to'] ?? 'both') == 'both') ? 'selected' : ''; ?>>
                                Both Employee & Reviewer
                            </option>
                            <option value="employee" <?php echo (($_POST['visible_to'] ?? '') == 'employee') ? 'selected' : ''; ?>>
                                Employee Only
                            </option>
                            <option value="reviewer" <?php echo (($_POST['visible_to'] ?? '') == 'reviewer') ? 'selected' : ''; ?>>
                                Reviewer Only
                            </option>
                        </select>
                        <div class="form-text">
                            Choose who can see this section:
                            <ul class="mt-2 mb-0">
                                <li><strong>Both:</strong> Section appears in employee form and manager review</li>
                                <li><strong>Employee Only:</strong> Section only appears when employee fills the form</li>
                                <li><strong>Reviewer Only:</strong> Section only appears during manager review</li>
                            </ul>
                        </div>
                    </div>

               
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Active (section will be displayed in forms)
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php?form_id=<?php echo $form_id; ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Create Section
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

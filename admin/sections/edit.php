
<?php
// admin/sections/edit.php
require_once __DIR__ . '/../../config/config.php';

if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$section_id = $_GET['id'] ?? 0;
if (!$section_id) {
    redirect('index.php', 'Section ID is required.', 'error');
}

$error_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get section details
    $query = "SELECT fs.*, f.title as form_title, f.id as form_id 
              FROM form_sections fs 
              JOIN forms f ON fs.form_id = f.id 
              WHERE fs.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$section_id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section) {
        redirect('index.php', 'Section not found.', 'error');
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
                $query = "UPDATE form_sections 
                         SET section_title = ?, section_description = ?, section_order = ?, is_active = ? 
                         WHERE id = ?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$section_title, $section_description, $section_order, $is_active, $section_id])) {
                    logActivity($_SESSION['user_id'], 'UPDATE', 'form_sections', $section_id,
                               ['title' => $section['section_title']], ['title' => $section_title],
                               'Updated section: ' . $section_title);
                    
                    redirect('index.php?form_id=' . $section['form_id'], 'Section updated successfully!', 'success');
                } else {
                    $error_message = 'Failed to update section. Please try again.';
                }
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Section edit error: " . $e->getMessage());
    redirect('index.php', 'An error occurred.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-pencil me-2"></i>Edit Section
                </h1>
                <small class="text-muted">Form: <?php echo htmlspecialchars($section['form_title']); ?></small>
            </div>
            <a href="index.php?form_id=<?php echo $section['form_id']; ?>" class="btn btn-outline-secondary">
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
                               value="<?php echo htmlspecialchars($section['section_title']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="section_description" class="form-label">Description</label>
                        <textarea class="form-control" id="section_description" name="section_description" rows="3"><?php echo htmlspecialchars($section['section_description']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="section_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="section_order" name="section_order" 
                               min="1" value="<?php echo $section['section_order']; ?>">
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                   <?php echo $section['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">
                                Active (section will be displayed in forms)
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php?form_id=<?php echo $section['form_id']; ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Update Section
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

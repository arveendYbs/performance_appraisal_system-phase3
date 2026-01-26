
<?php
require_once __DIR__ . '/../../config/config.php';

if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$form_id = $_GET['id'] ?? 0;
if (!$form_id) {
    redirect('index.php', 'Form ID is required.', 'error');
}

$error_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    $form = new Form($db);
    $form->id = $form_id;
    
    if (!$form->readOne()) {
        redirect('index.php', 'Form not found.', 'error');
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $error_message = 'Invalid request. Please try again.';
        } else {
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($title)) {
                $error_message = 'Form title is required.';
            } else {
                $query = "UPDATE forms SET title = ?, description = ?, is_active = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$title, $description, $is_active, $form_id])) {
                    logActivity($_SESSION['user_id'], 'UPDATE', 'forms', $form_id, 
                               ['title' => $form->title], ['title' => $title], 
                               'Updated form: ' . $title);
                    
                    redirect('index.php', 'Form updated successfully!', 'success');
                } else {
                    $error_message = 'Failed to update form. Please try again.';
                }
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Form edit error: " . $e->getMessage());
    redirect('index.php', 'An error occurred.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-pencil me-2"></i>Edit Form
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
                        <label for="form_type" class="form-label">Form Type</label>
                        <input type="text" class="form-control" value="<?php echo ucfirst($form->form_type); ?>" disabled>
                        <div class="form-text">Form type cannot be changed after creation</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Form Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" 
                               placeholder="Enter form title..." required
                               value="<?php echo htmlspecialchars($form->title); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  placeholder="Enter form description..."><?php echo htmlspecialchars($form->description); ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                   <?php echo $form->is_active ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">
                                Active (form can be used for appraisals)
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Update Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

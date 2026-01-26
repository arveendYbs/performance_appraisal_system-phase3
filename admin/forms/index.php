<?php
// admin/forms/index.php

require_once __DIR__ . '/../../config/config.php';

// Check admin access
if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $form = new Form($db);
    
    // Get all forms
    $stmt = $form->read();
    $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Forms index error: " . $e->getMessage());
    $forms = [];
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-file-earmark-text me-2"></i>Form Management
            </h1>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Create New Form
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($forms)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-file-earmark-x display-1 text-muted mb-3"></i>
                    <h5>No Forms Found</h5>
                    <p class="text-muted mb-3">Create your first appraisal form to get started.</p>
                    <a href="create.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Create Form
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Form Type</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($forms as $form_data): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo $form_data['form_type'] == 'management' ? 'primary' : ($form_data['form_type'] == 'general' ? 'info' : 'success'); ?>">
                                        <?php echo ucfirst($form_data['form_type']); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo htmlspecialchars($form_data['title']); ?></strong></td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars(substr($form_data['description'], 0, 100)); ?>
                                        <?php echo strlen($form_data['description']) > 100 ? '...' : ''; ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge <?php echo $form_data['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $form_data['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><small><?php echo formatDate($form_data['created_at']); ?></small></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="../sections/index.php?form_id=<?php echo $form_data['id']; ?>" 
                                           class="btn btn-outline-info" title="Manage Sections">
                                            <i class="bi bi-list-ul"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $form_data['id']; ?>" 
                                           class="btn btn-outline-primary" title="Edit Form">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="delete.php?id=<?php echo $form_data['id']; ?>" 
                                           class="btn btn-outline-danger" title="Delete Form"
                                           onclick="return confirmDelete('Are you sure you want to delete this form? This will also delete all associated sections and questions.')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

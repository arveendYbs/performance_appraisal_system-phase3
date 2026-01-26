
<?php
// admin/sections/index.php
require_once __DIR__ . '/../../config/config.php';

if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$form_id = $_GET['form_id'] ?? 0;
if (!$form_id) {
    redirect('../forms/index.php', 'Form ID is required.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get form details
    $form = new Form($db);
    $form->id = $form_id;
    if (!$form->readOne()) {
        redirect('../forms/index.php', 'Form not found.', 'error');
    }
    
    // Get sections for this form
    $query = "SELECT fs.*, COUNT(fq.id) as question_count
              FROM form_sections fs
              LEFT JOIN form_questions fq ON fs.id = fq.section_id AND fq.is_active = 1
              WHERE fs.form_id = ? AND fs.is_active = 1
              GROUP BY fs.id
              ORDER BY fs.section_order";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$form_id]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Sections index error: " . $e->getMessage());
    redirect('../forms/index.php', 'An error occurred.', 'error');
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-list-ul me-2"></i>Manage Sections
                </h1>
                <small class="text-muted">Form: <?php echo htmlspecialchars($form->title); ?></small>
            </div>
            <div>
                <a href="../questions/index.php?form_id=<?php echo $form_id; ?>" class="btn btn-outline-info me-2">
                    <i class="bi bi-question-circle me-2"></i>Manage Questions
                </a>
                <a href="create.php?form_id=<?php echo $form_id; ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Add Section
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($sections)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-list-ul display-1 text-muted mb-3"></i>
                    <h5>No Sections Found</h5>
                    <p class="text-muted mb-3">Add sections to organize your form questions.</p>
                    <a href="create.php?form_id=<?php echo $form_id; ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Add First Section
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Section Title</th>
                                <th>Description</th>
                                <th>Questions</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="sortable-sections">
                            <?php foreach ($sections as $section): ?>
                            <tr data-section-id="<?php echo $section['id']; ?>">
                                <td>
                                    <i class="bi bi-grip-vertical text-muted me-2" style="cursor: move;"></i>
                                    <span class="badge bg-secondary"><?php echo $section['section_order']; ?></span>
                                </td>
                                <td><strong><?php echo htmlspecialchars($section['section_title']); ?></strong></td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars(substr($section['section_description'], 0, 80)); ?>
                                        <?php echo strlen($section['section_description']) > 80 ? '...' : ''; ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $section['question_count']; ?> questions</span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $section['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $section['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="../questions/index.php?section_id=<?php echo $section['id']; ?>" 
                                           class="btn btn-outline-info" title="Manage Questions">
                                            <i class="bi bi-question-circle"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $section['id']; ?>" 
                                           class="btn btn-outline-primary" title="Edit Section">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="delete.php?id=<?php echo $section['id']; ?>" 
                                           class="btn btn-outline-danger" title="Delete Section"
                                           onclick="return confirmDelete('Are you sure you want to delete this section? This will also delete all questions in this section.')">
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

<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script>
$(document).ready(function() {
    // Make sections sortable
    $("#sortable-sections").sortable({
        handle: '.bi-grip-vertical',
        update: function(event, ui) {
            var order = [];
            $('#sortable-sections tr').each(function(index) {
                order.push({
                    id: $(this).data('section-id'),
                    order: index + 1
                });
            });
            
            // Send AJAX request to update order
            $.post('../../api/update_section_order.php', {
                sections: order,
                csrf_token: '<?php echo generateCSRFToken(); ?>'
            }).done(function(response) {
                console.log('Order updated successfully');
            }).fail(function() {
                alert('Failed to update order. Please refresh and try again.');
            });
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
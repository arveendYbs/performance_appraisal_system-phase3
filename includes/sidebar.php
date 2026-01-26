
<?php
ob_start(); 
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$current_path = $_SERVER['REQUEST_URI'];



// Check user roles
$is_admin = hasRole('admin');
$is_manager = hasRole('manager');
// Check if user is HR
$is_hr = false;
if (isset($_SESSION['user_id'])) {
    $database = new Database();
    $db = $database->getConnection();
    $current_user = new User($db);
    $current_user->id = $_SESSION['user_id'];
    $current_user->readOne();
    $is_hr = $current_user->isHR();
}
// Check if user is Top Management
$is_top_management = false;
if (isset($_SESSION['user_id'])) {
    $current_user = new User($db);
    $current_user->id = $_SESSION['user_id'];
    $current_user->readOne();
    $is_top_management = $current_user->isTopManagement();
}

// Check if user can access team features
/* $can_manage_team = canAccessTeamFeatures();
$is_team_lead = !$is_manager && $can_manage_team; */ // Team lead = has subordinates but not manager role

// Check if user can access team features
$can_manage_team = canAccessTeamFeatures();
$is_dept_manager = isDepartmentManager();
$is_team_lead = !hasRole('manager') && !$is_dept_manager && $can_manage_team;
?>
      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">


  <!-- jQuery -->
    <link href="/assets/css/custom.css" rel="stylesheet">
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Select2 JS -->

<!-- Select2 CSS + JS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.full.min.js"></script>

    <link href="/assets/css/custom.css" rel="stylesheet">
<div class="sidebar" id="sidebar">
    <div class="logo">
    <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" 
         alt="Company Logo" 
         style="height: 70px; margin-bottom: 0.5rem;"
         onerror="this.style.display='none';">
    <h4>
    <strong>E-Appraisal System</strong></h4>
    </div>
    
    <nav class="nav flex-column">
        <!-- Dashboard -->
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/index.php" 
               class="nav-link <?php echo ($current_page == 'index.php' && strpos($current_path, '/admin/') === false && strpos($current_path, '/employee/') === false && strpos($current_path, '/manager/') === false) ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </div>

        <?php if (hasRole('admin') || $is_hr): ?>
        <!-- Administration Section -->
        <div class="nav-header">Administration</div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/admin/forms/" 
               class="nav-link <?php echo (strpos($current_path, '/admin/forms/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-text"></i> Manage Forms
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/admin/sections/" 
               class="nav-link <?php echo (strpos($current_path, '/admin/sections/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-list-ul"></i> Form Sections
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/admin/questions/" 
               class="nav-link <?php echo (strpos($current_path, '/admin/questions/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-question-circle"></i> Questions
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/admin/users/" 
               class="nav-link <?php echo (strpos($current_path, '/admin/users/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Manage Users
            </a>
        </div>
 
        <?php if (hasRole('admin') ): ?>

        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/admin/audit/" 
               class="nav-link <?php echo (strpos($current_path, '/admin/audit/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-clock-history"></i> Audit Logs
            </a>
        </div>
        <?php endif; ?>
        <?php endif; ?>


        <!-- Add this section after HR section -->
        <?php if ($is_top_management): ?>
        <div class="nav-header">Top Management</div>
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/admin/top-management/index.php" 
            class="nav-link <?php echo (strpos($current_path, '/admin/top-management/index.php') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-graph-up-arrow"></i> Executive Dashboard
            </a>
        </div>

        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/admin/top-management/managers.php" 
            class="nav-link <?php echo ($current_page == 'managers.php')? 'active' : ''; ?>">
                <i class="bi bi-graph-up-arrow"></i> Manager/HOD Performance
            </a>
        </div>
        <?php endif; ?>


        <?php if (hasRole('manage') || hasRole('admin')): ?>
        <!-- Management Section -->
        <div class="nav-header">Management</div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/manager/review/pending.php" 
               class="nav-link <?php echo (strpos($current_path, '/manager/review/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-clipboard-check"></i> Review Appraisals
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/manager/team.php" 
               class="nav-link <?php echo ($current_page == 'team.php') ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> My Team
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/manager/reports.php" 
               class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                <i class="bi bi-graph-up"></i> Reports
            </a>
        </div>
        <?php endif; ?>


            <!-- Add this HR section in your sidebar -->
            <?php if ($is_hr): ?>
            <div class="nav-header">HR</div>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/hr/') !== false ? 'active' : ''; ?>" 
                href="<?php echo BASE_URL; ?>/hr/index.php">
                    <i class="bi bi-briefcase me-2"></i>HR Dashboard
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#hrMenu" role="button" 
                aria-expanded="false" aria-controls="hrMenu">
                    <i class="bi bi-diagram-3 me-2"></i>HR Functions
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="hrMenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/hr/appraisals/index.php">
                                <i class="bi bi-clipboard-data me-2"></i>All Appraisals
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/hr/employees/index.php">
                                <i class="bi bi-people me-2"></i>Employees
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/hr/reports/index.php">
                                <i class="bi bi-graph-up me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/hr/reports/reports.php">
                                <i class="bi bi-file-earmark-arrow-down me-2"></i>Reports Export
                            </a>
                        </li>

                    </ul>
                </div>
            </li>
            <?php endif; ?>

        <!-- Manager/Team Lead Section - Shows for managers OR anyone with subordinates -->
         <?php if ($can_manage_team): ?>
        <div class="nav-header">
            <?php 
            if ($is_dept_manager) {
                echo 'Department Manager';
            } elseif ($is_team_lead) {
                echo 'Team Lead';
            } else {
                echo 'Manager';
            }
            ?>
        </div>
        

       
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/manager/review/pending.php" 
               class="nav-link <?php echo (strpos($current_path, '/manager/review/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-clipboard-check"></i> Review Appraisals
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/manager/team.php" 
               class="nav-link <?php echo (strpos($current_path, '/manager/team.php') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> 
                <?php 
                if ($is_dept_manager) {
                    echo 'Department Team';
                } else {
                    echo 'My Team';
                }
                ?>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/manager/reports.php" 
               class="nav-link <?php echo (strpos($current_path, '/manager/reports.php') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-graph-up"></i> Reports
            </a>
        </div>
        <?php endif; ?>
        <!-- Employee Section -->
        <div class="nav-header">Employee</div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/employee/appraisal/" 
               class="nav-link <?php echo (strpos($current_path, '/employee/appraisal/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-clipboard-data"></i> My Appraisal
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/employee/history.php" 
               class="nav-link <?php echo ($current_page == 'history.php' && strpos($current_path, '/employee/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-clock-history"></i> Appraisal History
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/employee/profile.php" 
               class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <i class="bi bi-person"></i> My Profile
            </a>
        </div>
    </nav>
</div>

<div class="topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-link mobile-toggle me-2" id="sidebar-toggle" type="button">
            <i class="bi bi-list fs-4"></i>
        </button>
        <h5 class="mb-0">
            <?php
            $page_titles = [
                'index.php' => 'Dashboard',
                'forms' => 'Form Management',
                'sections' => 'Section Management', 
                'questions' => 'Question Management',
                'users' => 'User Management',
                'audit' => 'Audit Logs',
                'pending.php' => 'Pending Reviews',
                'team.php' => 'My Team',
                'reports.php' => 'Reports',
                'appraisal' => 'My Appraisal',
                'history.php' => 'Appraisal History',
                'profile.php' => 'My Profile'
            ];
            
            $current_title = 'Dashboard';
            foreach ($page_titles as $key => $title) {
                if (strpos($current_path, $key) !== false || $current_page == $key) {
                    $current_title = $title;
                    break;
                }
            }
            echo $current_title;
            ?>
        </h5>
    </div>
    
    <div class="dropdown">
        <button class="btn btn-link dropdown-toggle d-flex align-items-center p-0" type="button" data-bs-toggle="dropdown">
            <div class="me-3 text-end">
                <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                <small class="text-muted"><?php echo htmlspecialchars($_SESSION['user_position']); ?></small>
            </div>
            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-person-fill text-white"></i>
            </div>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <div class="dropdown-header">
                    <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong><br>
                    <small class="text-muted"><?php echo htmlspecialchars($_SESSION['user_email']); ?></small>
                </div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/employee/profile.php">
                <i class="bi bi-person me-2"></i>My Profile
            </a></li>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/employee/appraisal/">
                <i class="bi bi-clipboard-data me-2"></i>My Appraisal
            </a></li>
            <?php if (hasRole('admin')): ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/">
                <i class="bi bi-gear me-2"></i>Administration
            </a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/auth/logout.php">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
            </a></li>
        </ul>
    </div>
</div>

<div class="main-content">
    <!-- Flash messages -->
    <?php displayFlashMessage(); ?>

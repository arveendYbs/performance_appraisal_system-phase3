<?php
// menu.php
session_start();
if (!isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = "Guest User"; // for demo
}
$user_name = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- Professional Theme & Red Accent --- */

        :root {
            --primary-color: #D9232D; /* Our main red accent */
            --primary-hover: #B31D25; /* Darker red for hover */
            --body-bg: #f5f6fa;
            --card-bg: #ffffff;
            --text-color: #333;
            --text-muted: #6c757d;
            --card-shadow: 0 4px 12px rgba(0,0,0,0.05);
            --card-shadow-hover: 0 8px 20px rgba(0,0,0,0.08);
        }

        body {
            background-color: var(--body-bg);
            font-family: 'Poppins', sans-serif; /* Professional font */
            color: var(--text-color);
        }
/* --- Custom Separator Line (HR) --- */
        hr {
            margin: 2rem 0; /* Adjust vertical spacing */
            border: 0;
            border-top: 2px solid var(--primary-color); /* Apply red color and make it thicker */
            opacity: 0.5; /* Reduce opacity slightly so it's not too harsh */
        }
        /* --- Page Header --- */
        .page-header {
            border-bottom: 1px solid #dee2e6;
        }

        /* --- NEW: Logo Styling --- */
        .company-logo {
            height: 95px; /* Adjust this height to fit your logo */
            width: auto; /* Maintains aspect ratio */
        }
        
        .page-header h2 {
            font-weight: 300; /* Lighter weight for "Welcome," */
        }
        .page-header h2 strong {
            font-weight: 600; /* Bolder weight for username */
            color: var(--text-color);
        }

        /* --- Section Titles --- */
        .section-title {
            font-weight: 800;
            margin-top: 1.5rem;
            margin-bottom: 1.25rem;
            color: #222;
            border-left: 4px solid var(--primary-color); /* Red accent */
            padding-left: 0.75rem;
        }
        .section-title .fa-solid {
            color: var(--primary-color); /* Red accent for icon */
            margin-right: 0.5rem;
        }

        /* --- Category Cards --- */
        .category-card {
            border: none;
            border-radius: 12px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
        }

        .category-card:hover {
            transform: translateY(-8px); /* More noticeable pop */
            box-shadow: var(--card-shadow-hover);
        }

        .card-icon {
            font-size: 2.25rem;
            color: var(--primary-color); /* Red accent */
        }

        .category-card h5 {
            font-weight: 600;
            color: #333;
        }

        .category-card p {
            color: var(--text-muted);
            font-size: 0.9rem;
            flex-grow: 1; /* Key for pushing button to bottom */
        }

        /* --- Red Accent Buttons --- */
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 500;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }
/* --- NEW: Small AI Card Adjustments --- */
        .ai-card .card-icon {
            font-size: 1.75rem; /* Slightly smaller icon */
        }
        .ai-card .btn {
            padding: 0.25rem 0.5rem; /* Smaller button */
            font-size: 0.8rem;
        }
        /* Ensure the AI cards are compact */
        .ai-card.h-100 {
            height: auto !important; 
        }
    </style>
</head>
<body>

<div class="container-fluid py-5">
    
    <header class="d-flex justify-content-between align-items-center pb-4 mb-4 page-header">
        
        <div class="d-flex align-items-center">
            
            <img src="assets/images/logo.png" alt="Company Logo" class="company-logo me-3">
            
            <h2 class="mb-0 fw-light">Welcome to <strong>YBS Portal Directory</strong>
                <!-- <strong class="fw-semibold"><?= htmlspecialchars($user_name) ?></strong></h2> -->
        </div>
<!-- 
        <a href="logout.php" class="btn btn-outline-danger">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
        </a> -->
    </header>
<h4 class="section-title"><i class="fa-solid fa-globe"></i> General Systems</h4>
    <div class="row g-4">
        <div class="col-md-3">
            <div class="card category-card p-3 h-100 d-flex flex-column">
                <i class="fa-solid fa-building card-icon"></i>
                <h5 class="mt-3">Company Website</h5>
                <p>Company Official Website.</p>
                <a href="https://www.ybsinternational.com/" class="btn btn-primary btn-sm mt-auto" target="_blank">Open</a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card category-card p-3 h-100 d-flex flex-column">
                <i class="fa-solid fa-file card-icon"></i>
                <h5 class="mt-3">ePR</h5>
                <p>Purchase Request.</p>
                <a href="https://orientalfastech.com/erp/login.php" class="btn btn-primary btn-sm mt-auto" target="_blank">Open</a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card category-card p-3 h-100 d-flex flex-column">
                <i class="fa-solid fa-folder-open card-icon"></i>
                <h5 class="mt-3">YBS ShareFolder</h5>
                <p>Purchase Request.</p>
                <a href="https://ybsinternationalbhd.sharepoint.com/sites/YBS/Shared%20Documents/Forms/AllItems.aspx?viewid=9947a176%2Dc9da%2D4430%2Db4ba%2D1493c63f0111&p=true&ga=1" class="btn btn-primary btn-sm mt-auto" target="_blank">Open</a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card category-card p-3 h-100 d-flex flex-column">
                <i class="fa-solid fa-archive card-icon"></i>
                <h5 class="mt-3">INFOR</h5>
                <p>PRD</p>
                <a href="https://mingle-sso.ne1.inforcloudsuite.com/XUE6S9MT7JCJCXVJ_PRD/as/authorization.oauth2?client_id=infor~g-LqhqFwRI302t7LUEwulql9ZhdyRrmgCVeX1VWUVTE_OIDC&response_type=code&redirect_uri=https://mingle-portal.ne1.inforcloudsuite.com/sso/callback&scope=openid&state=XUE6S9MT7JCJCXVJ_PRD~tszsky0r1SfhiIJUXFhEs39LA9E49cURkCkgKn-nd_d6xuL8jzX0W2Hfk1TRtLH8jBu9YciUy0XdzxxcFvjZqJbmLEdu5S9ZsDDIgGySY2iAMKxpkBzJnyOliTPw5mkw&code_challenge=hiistNJJTeRztD5m-4dLbAjWaayP5G5VROq8YNNDpGw&code_challenge_method=S256" class="btn btn-primary btn-sm mt-auto" target="_blank">Open</a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card category-card p-3 h-100 d-flex flex-column">
                <i class="fa-solid fa-archive card-icon"></i>
                <h5 class="mt-3">INFOR</h5>
                <p>TRN</p>
                <a href="https://mingle-sso.ne1.inforcloudsuite.com/XUE6S9MT7JCJCXVJ_PRD/as/authorization.oauth2?client_id=infor~g-LqhqFwRI302t7LUEwulql9ZhdyRrmgCVeX1VWUVTE_OIDC&response_type=code&redirect_uri=https://mingle-portal.ne1.inforcloudsuite.com/sso/callback&scope=openid&state=XUE6S9MT7JCJCXVJ_PRD~tszsky0r1SfhiIJUXFhEs39LA9E49cURkCkgKn-nd_d6xuL8jzX0W2Hfk1TRtLH8jBu9YciUy0XdzxxcFvjZqJbmLEdu5S9ZsDDIgGySY2iAMKxpkBzJnyOliTPw5mkw&code_challenge=hiistNJJTeRztD5m-4dLbAjWaayP5G5VROq8YNNDpGw&code_challenge_method=S256" class="btn btn-primary btn-sm mt-auto" target="_blank">Open</a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card category-card p-3 h-100 d-flex flex-column">
                <i class="fa-solid fa-file-invoice card-icon"></i>
                <h5 class="mt-3">ERFQ</h5>
                <p>Quotation submission platform</p>
                <a href="http://orientalfastech.com/erfq_portal/erfq_portal.php" class="btn btn-primary btn-sm mt-auto" target="_blank">Open</a>
            </div>
        </div>
    </div>



    <!-- HR -->
    <hr><h4 class="section-title"><i class="fa-solid fa-users"></i> HR Systems</h4>
    <div class="row g-4">
        <div class="col-md-3">
            <div class="card category-card p-3 h-100 d-flex flex-column">
                <i class="fa-solid fa-user-check card-icon"></i>
                <h5 class="mt-3">E-Appraisal</h5>
                <p>Manage and review employee appraisals .</p>
                <a href="http://175.143.14.225:8080/performance_appraisal_system/" class="btn btn-primary btn-sm mt-auto" target="_blank">Open</a>
            </div>
        </div>
 <!--        <div class="col-md-4">
            <div class="card category-card p-3 h-100 d-flex flex-column">
                <i class="fa-solid fa-calendar-days card-icon"></i>
                <h5 class="mt-3">Leave Management</h5>
                <p>Apply, approve, and track staff leave applications.</p>
                <a href="#" class="btn btn-outline-secondary btn-sm mt-auto disabled" target="_blank">Coming Soon</a>
            </div>
        </div> -->
        <div class="col-md-3">
            <div class="card category-card p-3 h-100 d-flex flex-column">
                <i class="fa-solid fa-envelope card-icon"></i>
                <h5 class="mt-3">INFO-TECH HRMS</h5>
                <p>For HRMS Solution .</p>
                <a href="https://login-infotech.com/Login?rUrl=https%3a%2f%2fwww.infotech-cloudhr.com.my%2fLogin.aspx" class="btn btn-primary btn-sm mt-auto" target="_blank">Open</a>
            </div>
        </div>
    </div>

    

    <!-- MIS -->
    <hr><h4 class="section-title"><i class="fa-solid fa-chart-line"></i> MIS Systems</h4>
    <div class="row g-4"> <div class="col-md-3">
            <div class="card category-card p-3 h-100 d-flex flex-column">
                <i class="fa-solid fa-database card-icon"></i>
                <h5 class="mt-3">IT Request System</h5>
                <p>testing.</p>
                <a href="http://localhost/sdlc_tracker" class="btn btn-primary btn-sm mt-auto" target="_blank">Open</a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card category-card p-3 h-100 d-flex flex-column">
                <i class="fa-solid fa-bar-chart card-icon"></i>
                <h5 class="mt-3">Management Review Dashboard</h5>
                <p>Dashboard for top management review</p>
                <a href="https://ybsinternationalbhd.sharepoint.com/sites/YBS/SitePages/Management-Review.aspx" class="btn btn-primary btn-sm mt-auto" target="_blank">Open</a>
            </div>
        </div>
    </div>
    
    
    <hr class="my-5">

    <h4 class="section-title"><i class="fa-solid fa-brain"></i> Quick Links</h4>
    <div class="row g-3">
        
        

        <div class="col-6 col-sm-4 col-md-2">
            <div class="card category-card ai-card p-3 h-100 d-flex flex-column text-center">
                <i class="fa-solid fa-microchip card-icon mx-auto"></i>
                <h5 class="mt-3 mb-2">Copilot</h5>
                <a href="https://copilot.microsoft.com/" class="btn btn-primary mt-auto" target="_blank">Launch</a>
            </div>
        </div>
        
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
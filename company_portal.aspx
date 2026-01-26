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
        :root {
            --primary-color: #D9232D;
            --primary-hover: #B31D25;
            --body-bg: #f5f6fa;
            --card-bg: #ffffff;
            --text-color: #333;
            --text-muted: #6c757d;
            --card-shadow: 0 4px 12px rgba(0,0,0,0.05);
            --card-shadow-hover: 0 8px 20px rgba(0,0,0,0.08);
        }

        body {
            background-color: var(--body-bg);
            font-family: 'Poppins', sans-serif;
            color: var(--text-color);
            /* Ensure body fits nicely if iframe is small */
            min-height: 100vh;
        }

        hr {
            margin: 2rem 0;
            border: 0;
            border-top: 2px solid var(--primary-color);
            opacity: 0.5;
        }

        .page-header {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }

        .company-logo {
            height: 60px; /* Adjusted for better proportion */
            width: auto;
        }

        .page-header h2 {
            font-weight: 300;
            margin: 0;
        }

        .page-header h2 strong {
            font-weight: 600;
            color: var(--text-color);
        }

        .section-title {
            font-weight: 800;
            margin-top: 1.5rem;
            margin-bottom: 1.25rem;
            color: #222;
            border-left: 4px solid var(--primary-color);
            padding-left: 0.75rem;
        }

        .section-title .fa-solid {
            color: var(--primary-color);
            margin-right: 0.5rem;
        }

        .category-card {
            border: none;
            border-radius: 12px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow-hover);
        }

        .card-icon {
            font-size: 2.25rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .category-card h5 {
            font-weight: 600;
            color: #333;
            margin-top: 0.5rem;
        }

        .category-card p {
            color: var(--text-muted);
            font-size: 0.9rem;
            flex-grow: 1;
            margin-bottom: 1rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 500;
            width: 100%; /* Make buttons full width for better mobile touch targets */
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        .ai-card .card-icon {
            font-size: 1.75rem;
        }

        .ai-card .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        /* Responsive tweaks */
        @media (max-width: 576px) {
            .page-header {
                flex-direction: column;
                text-align: center;
            }
            .page-header .d-flex {
                flex-direction: column;
            }
            .company-logo {
                margin-right: 0;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="container py-5">

    <header class="page-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <!-- Using a placeholder logo since local file won't load -->
            <img src="https://placehold.co/200x80/D9232D/ffffff?text=YBS+Logo" alt="Company Logo" class="company-logo me-md-3">

            <h2 class="fw-light">
                Welcome to <strong>YBS Portal Directory</strong>
            </h2>
        </div>
    </header>

    <h4 class="section-title">
        <i class="fa-solid fa-globe"></i> General Systems
    </h4>

    <div class="row g-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card category-card p-4 h-100 d-flex flex-column text-center">
                <div><i class="fa-solid fa-building card-icon"></i></div>
                <h5>Company Website</h5>
                <p>Company Official Website.</p>
                <a href="https://www.ybsinternational.com/" class="btn btn-primary mt-auto" target="_blank">Open</a>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card category-card p-4 h-100 d-flex flex-column text-center">
                <div><i class="fa-solid fa-file card-icon"></i></div>
                <h5>ePR</h5>
                <p>Purchase Request.</p>
                <a href="https://orientalfastech.com/erp/login.php" class="btn btn-primary mt-auto" target="_blank">Open</a>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card category-card p-4 h-100 d-flex flex-column text-center">
                <div><i class="fa-solid fa-folder-open card-icon"></i></div>
                <h5>YBS ShareFolder</h5>
                <p>Document Sharing.</p>
                <a href="https://ybsinternationalbhd.sharepoint.com/sites/YBS/Shared%20Documents/Forms/AllItems.aspx" class="btn btn-primary mt-auto" target="_blank">Open</a>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card category-card p-4 h-100 d-flex flex-column text-center">
                <div><i class="fa-solid fa-archive card-icon"></i></div>
                <h5>INFOR</h5>
                <p>PRD System.</p>
                <a href="#" class="btn btn-primary mt-auto" target="_blank">Open</a>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card category-card p-4 h-100 d-flex flex-column text-center">
                <div><i class="fa-solid fa-boxes-stacked card-icon"></i></div>
                <h5>INFOR</h5>
                <p>TRN System.</p>
                <a href="#" class="btn btn-primary mt-auto" target="_blank">Open</a>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card category-card p-4 h-100 d-flex flex-column text-center">
                <div><i class="fa-solid fa-file-invoice card-icon"></i></div>
                <h5>ERFQ</h5>
                <p>Quotation submission platform.</p>
                <a href="http://orientalfastech.com/erfq_portal/erfq_portal.php" class="btn btn-primary mt-auto" target="_blank">Open</a>
            </div>
        </div>
    </div>

    <hr>
    <h4 class="section-title"><i class="fa-solid fa-users"></i> HR Systems</h4>

    <div class="row g-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card category-card p-4 h-100 d-flex flex-column text-center">
                <div><i class="fa-solid fa-user-check card-icon"></i></div>
                <h5>E-Appraisal</h5>
                <p>Manage and review employee appraisals.</p>
                <a href="http://175.143.14.225:8080/performance_appraisal_system/" class="btn btn-primary mt-auto" target="_blank">Open</a>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card category-card p-4 h-100 d-flex flex-column text-center">
                <div><i class="fa-solid fa-address-card card-icon"></i></div>
                <h5>INFO-TECH HRMS</h5>
                <p>HRMS Solution.</p>
                <a href="https://login-infotech.com/Login" class="btn btn-primary mt-auto" target="_blank">Open</a>
            </div>
        </div>
    </div>

    <hr>
    <h4 class="section-title"><i class="fa-solid fa-chart-line"></i> MIS Systems</h4>

    <div class="row g-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card category-card p-4 h-100 d-flex flex-column text-center">
                <div><i class="fa-solid fa-database card-icon"></i></div>
                <h5>IT Request System</h5>
                <p>Internal Tracking.</p>
                <a href="http://localhost/sdlc_tracker" class="btn btn-primary mt-auto" target="_blank">Open</a>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card category-card p-4 h-100 d-flex flex-column text-center">
                <div><i class="fa-solid fa-square-poll-vertical card-icon"></i></div>
                <h5>Management Review</h5>
                <p>Top management dashboard.</p>
                <a href="https://ybsinternationalbhd.sharepoint.com/sites/YBS/SitePages/Management-Review.aspx" class="btn btn-primary mt-auto" target="_blank">Open</a>
            </div>
        </div>
    </div>

    <hr class="my-5">

    <h4 class="section-title"><i class="fa-solid fa-brain"></i> Quick Links</h4>

    <div class="row g-3">
        <div class="col-6 col-sm-4 col-md-3">
            <div class="card category-card ai-card p-3 h-100 d-flex flex-column text-center">
                <div><i class="fa-solid fa-microchip card-icon"></i></div>
                <h5 class="mt-2 mb-2 fs-6">Copilot</h5>
                <a href="https://copilot.microsoft.com/" class="btn btn-primary mt-auto btn-sm" target="_blank">Launch</a>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
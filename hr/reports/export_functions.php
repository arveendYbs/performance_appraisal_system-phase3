<?php
// hr/reports/export_functions.php

use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Generate detailed export (one sheet per employee)
 */
function generateDetailedExport($spreadsheet, $appraisals, $company_name, $year, $db) {
    // Create summary sheet first
    $summarySheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Summary');
    $spreadsheet->addSheet($summarySheet, 0);
    createSummarySheet($summarySheet, $appraisals, $company_name, $year);

    // Create individual employee sheets
    $sheetIndex = 1;
    foreach ($appraisals as $appraisal) {
        $sheet_name = substr(preg_replace('/[^a-zA-Z0-9 ]/', '', $appraisal['employee_name'] ?? 'Employee'), 0, 31);
        $worksheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $sheet_name);
        $spreadsheet->addSheet($worksheet, $sheetIndex);
        $sheetIndex++;

        // Get form structure and responses
        $responses = [];
        $form_structure = [];
        
        if (!empty($appraisal['form_id'])) {
            try {
                $form = new Form($db);
                $form->id = $appraisal['form_id'];
                $form_structure = $form->getFormStructure() ?: [];

                $resp_query = "SELECT r.*, fq.question_text, fq.response_type, fs.section_title
                               FROM responses r
                               JOIN form_questions fq ON r.question_id = fq.id
                               JOIN form_sections fs ON fq.section_id = fs.id
                               WHERE r.appraisal_id = ?
                               ORDER BY fs.section_order, fq.question_order";
                $resp_stmt = $db->prepare($resp_query);
                $resp_stmt->execute([$appraisal['appraisal_id']]);
                $responses = $resp_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $resp_stmt->closeCursor();
            } catch (Exception $e) {
                error_log("Error fetching responses for appraisal {$appraisal['appraisal_id']}: " . $e->getMessage());
            }
        }

        createEmployeeSheet($worksheet, $appraisal, $form_structure, $responses);
    }
}

/**
 * Generate summary export (all employees in rows)
 */
function generateSummaryExport($spreadsheet, $appraisals, $company_name, $year, $db) {
    // Create main data sheet
    $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Appraisals Summary');
    $spreadsheet->addSheet($sheet, 0);
    
    $row = 1;
    
    // Title
    $sheet->setCellValue('A' . $row, 'PERFORMANCE APPRAISAL SUMMARY - ' . strtoupper($company_name) . ' (' . $year . ')');
    $sheet->mergeCells('A' . $row . ':P' . $row);
    $sheet->getStyle('A' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0066CC']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $row += 2;
    
    // Statistics summary
    $stats = calculateStatistics($appraisals);
    $sheet->setCellValue('A' . $row, 'Total Employees:');
    $sheet->setCellValue('B' . $row, count($appraisals));
    $sheet->setCellValue('D' . $row, 'Average Score:');
    $sheet->setCellValue('E' . $row, number_format($stats['avg_score'], 2) . '%');
    $sheet->setCellValue('G' . $row, 'A Grades:');
    $sheet->setCellValue('H' . $row, $stats['grades']['A']);
    $sheet->setCellValue('J' . $row, 'B+ Grades:');
    $sheet->setCellValue('K' . $row, $stats['grades']['B+']);
    $sheet->setCellValue('M' . $row, 'Other:');
    $sheet->setCellValue('N' . $row, $stats['grades']['B'] + $stats['grades']['B-'] + $stats['grades']['C']);
    
    $sheet->getStyle('A' . $row . ':N' . $row)->getFont()->setBold(true);
    $row += 2;
    
    // Column headers
    $headers = [
        'No', 'Employee Name', 'Emp No', 'Position', 'Department', 'Site',
        'Grade', 'Score (%)', 'Manager', 'Submitted', 'Reviewed',
        'Self Rating Avg', 'Manager Rating Avg', 'Performance Score',
        'Form Title', 'Status'
    ];
    
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $row, $header);
        $col++;
    }
    
    $sheet->getStyle('A' . $row . ':P' . $row)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $row++;
    
    // PRE-FETCH all ratings at once to avoid statement conflicts
    $all_ratings = [];
    foreach ($appraisals as $appraisal) {
        try {
            $ratings_query = "SELECT 
                        AVG(CASE WHEN employee_rating IS NOT NULL AND employee_rating != '' THEN CAST(employee_rating AS DECIMAL(10,2)) END) as employee_avg,
                        AVG(CASE WHEN manager_rating IS NOT NULL AND manager_rating != '' THEN CAST(manager_rating AS DECIMAL(10,2)) END) as manager_avg,
                        COUNT(*) as total_questions
                      FROM responses r
                      JOIN form_questions fq ON r.question_id = fq.id
                      WHERE r.appraisal_id = ?
                      AND fq.response_type IN ('rating_5', 'rating_10')";
            
            $stmt = $db->prepare($ratings_query);
            $stmt->execute([$appraisal['appraisal_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            
            $employee_avg = $result['employee_avg'] ?? null;
            $manager_avg = $result['manager_avg'] ?? null;
            
            $all_ratings[$appraisal['appraisal_id']] = [
                'employee_avg' => $employee_avg ? number_format($employee_avg, 2) : '-',
                'manager_avg' => $manager_avg ? number_format($manager_avg, 2) : '-',
                'performance_score' => ($employee_avg && $manager_avg) 
                    ? number_format(($employee_avg + $manager_avg) / 2, 2) 
                    : '-'
            ];
        } catch (Exception $e) {
            error_log("Error fetching ratings for appraisal {$appraisal['appraisal_id']}: " . $e->getMessage());
            $all_ratings[$appraisal['appraisal_id']] = [
                'employee_avg' => '-',
                'manager_avg' => '-',
                'performance_score' => '-'
            ];
        }
    }
    
    // Data rows
    $no = 1;
    foreach ($appraisals as $appraisal) {
        $ratings = $all_ratings[$appraisal['appraisal_id']] ?? [
            'employee_avg' => '-',
            'manager_avg' => '-',
            'performance_score' => '-'
        ];
        
        $sheet->setCellValue('A' . $row, $no++);
        $sheet->setCellValue('B' . $row, $appraisal['employee_name'] ?? '-');
        $sheet->setCellValue('C' . $row, $appraisal['emp_number'] ?? '-');
        $sheet->setCellValue('D' . $row, $appraisal['position'] ?? '-');
        $sheet->setCellValue('E' . $row, $appraisal['department'] ?? '-');
        $sheet->setCellValue('F' . $row, $appraisal['site'] ?? '-');
        $sheet->setCellValue('G' . $row, $appraisal['grade'] ?? '-');
        $sheet->setCellValue('H' . $row, number_format($appraisal['total_score'] ?? 0, 2));
        $sheet->setCellValue('I' . $row, $appraisal['manager_name'] ?? '-');
        $sheet->setCellValue('J' . $row, !empty($appraisal['employee_submitted_at']) ? date('Y-m-d', strtotime($appraisal['employee_submitted_at'])) : '-');
        $sheet->setCellValue('K' . $row, !empty($appraisal['manager_reviewed_at']) ? date('Y-m-d', strtotime($appraisal['manager_reviewed_at'])) : '-');
        $sheet->setCellValue('L' . $row, $ratings['employee_avg']);
        $sheet->setCellValue('M' . $row, $ratings['manager_avg']);
        $sheet->setCellValue('N' . $row, $ratings['performance_score']);
        $sheet->setCellValue('O' . $row, $appraisal['form_title'] ?? '-');
        $sheet->setCellValue('P' . $row, 'Completed');
        
        // Grade color coding
        $gradeColors = [
            'A' => '00B050',
            'B+' => '92D050',
            'B' => 'FFC000',
            'B-' => 'FF9900',
            'C' => 'FF0000'
        ];
        if (isset($gradeColors[$appraisal['grade']])) {
            $sheet->getStyle('G' . $row)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $gradeColors[$appraisal['grade']]]],
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']]
            ]);
        }
        
        // Score color coding
        $score = $appraisal['total_score'] ?? 0;
        if ($score >= 85) {
            $scoreColor = 'C6EFCE';
        } elseif ($score >= 75) {
            $scoreColor = 'FFEB9C';
        } elseif ($score >= 60) {
            $scoreColor = 'FFC7CE';
        } else {
            $scoreColor = 'FF0000';
            $sheet->getStyle('H' . $row)->getFont()->getColor()->setRGB('FFFFFF');
        }
        $sheet->getStyle('H' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($scoreColor);
        
        $row++;
    }
    
    // Apply borders to all data
    $lastRow = $row - 1;
    $sheet->getStyle('A5:P' . $lastRow)->applyFromArray([
        'borders' => ['allBorders' => [
            'borderStyle' => Border::BORDER_THIN, 
            'color' => ['rgb' => 'CCCCCC']
        ]]
    ]);
    
    // Auto-size columns
    foreach (range('A', 'P') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Freeze header row
    $sheet->freezePane('A6');
    
    // Add filters
    $sheet->setAutoFilter('A5:P' . $lastRow);
}
/**
 * Generate comprehensive export with all scores and training needs
 */
function generateComprehensiveExport($spreadsheet, $appraisals, $company_name, $year, $db) {
    // Create main data sheet
    $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Comprehensive Report');
    $spreadsheet->addSheet($sheet, 0);
    
    $row = 1;
    
    // Title
    $sheet->setCellValue('A' . $row, 'PERFORMANCE ASSESSMENT - COMPREHENSIVE REPORT');
    $sheet->mergeCells('A' . $row . ':AZ' . $row);
    $sheet->getStyle('A' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0066CC']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $row++;
    
    $sheet->setCellValue('A' . $row, $company_name . ' - ' . $year);
    $sheet->mergeCells('A' . $row . ':AZ' . $row);
    $sheet->getStyle('A' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 12],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $row += 2;
    
    // Build headers dynamically
    $baseHeaders = [
        'Company', 'Dept', 'Name', 'Staff No.', 'Form', 'Role', 'Position', 
        'Date Joined', 'Period'
    ];
    
    // Find max questions across all forms
    $maxQuestions = 0;
    $maxTrainingNeeds = 0;
    
    foreach ($appraisals as $appraisal) {
        if (!empty($appraisal['form_id'])) {
            // Count rating questions
            $query = "SELECT COUNT(*) as cnt 
                      FROM form_questions fq
                      JOIN form_sections fs ON fq.section_id = fs.id
                      WHERE fs.form_id = ? 
                      AND fq.response_type IN ('rating_5', 'rating_10')";
            $stmt = $db->prepare($query);
            $stmt->execute([$appraisal['form_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $qCount = $result['cnt'] ?? 0;
            $maxQuestions = max($maxQuestions, $qCount);
            $stmt->closeCursor();
            
            // Count training needs questions
            $query = "SELECT COUNT(*) as cnt 
                      FROM form_questions fq
                      JOIN form_sections fs ON fq.section_id = fs.id
                      WHERE fs.form_id = ? 
                      AND fq.response_type = 'training_needs'";
            $stmt = $db->prepare($query);
            $stmt->execute([$appraisal['form_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $tCount = $result['cnt'] ?? 0;
            $maxTrainingNeeds = max($maxTrainingNeeds, $tCount);
            $stmt->closeCursor();
        }
    }
    
    // Build complete header array
    $headers = $baseHeaders;
    
    // Employee scores section
    for ($i = 1; $i <= $maxQuestions; $i++) {
        $headers[] = 'Q' . $i;
    }
    $headers[] = 'Total';
    $headers[] = 'Score';
    $headers[] = 'Rating';
    
    // Manager scores section
    for ($i = 1; $i <= $maxQuestions; $i++) {
        $headers[] = 'Q' . $i;
    }
    $headers[] = 'Total';
    $headers[] = 'Score';
    $headers[] = 'Final Rating';
    
    // Training needs section
    for ($i = 1; $i <= $maxTrainingNeeds; $i++) {
        $headers[] = 'T' . $i;
    }
    
    // Write section headers
    $headerRow = $row;
    $colIndex = 1; // Start from 1, not 0
    
    // Base info section (no header)
    $baseEndCol = count($baseHeaders);
    
    // Employee Assessment section header
    $startCol = $baseEndCol + 1;
    $endCol = $startCol + $maxQuestions + 2; // Questions + Total + Score + Rating
    $startLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startCol);
    $endLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($endCol);
    $sheet->setCellValue($startLetter . $row, 'Performance Assessment - Employee Scores');
    $sheet->mergeCells($startLetter . $row . ':' . $endLetter . $row);
    $sheet->getStyle($startLetter . $row)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    
    // Manager Assessment section header
    $startCol = $endCol + 1;
    $endCol = $startCol + $maxQuestions + 2;
    $startLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startCol);
    $endLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($endCol);
    $sheet->setCellValue($startLetter . $row, 'Performance Assessment - Manager Scores');
    $sheet->mergeCells($startLetter . $row . ':' . $endLetter . $row);
    $sheet->getStyle($startLetter . $row)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '70AD47']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    
    // Training needs section header
    if ($maxTrainingNeeds > 0) {
        $startCol = $endCol + 1;
        $endCol = $startCol + $maxTrainingNeeds - 1;
        $startLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startCol);
        $endLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($endCol);
        $sheet->setCellValue($startLetter . $row, 'Training & Development Needs');
        $sheet->mergeCells($startLetter . $row . ':' . $endLetter . $row);
        $sheet->getStyle($startLetter . $row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
    }
    
    $row++;
    
    // Column headers
    $colIndex = 1; // Start from column 1 (A)
    foreach ($headers as $header) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        $sheet->setCellValue($colLetter . $row, $header);
        $colIndex++;
    }
    
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex - 1);
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '44546A']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
    ]);
    $sheet->getRowDimension($row)->setRowHeight(20);
    $row++;
    
    // Data rows
    foreach ($appraisals as $appraisal) {
        $colIndex = 1; // IMPORTANT: Start from 1, not 0
        
        // Base information
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), $company_name);
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), $appraisal['department'] ?? '-');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), $appraisal['employee_name'] ?? '-');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), $appraisal['emp_number'] ?? '-');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), $appraisal['form_title'] ?? '-');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), ucfirst($appraisal['role'] ?? 'Employee'));
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), $appraisal['position'] ?? '-');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), $appraisal['date_joined'] ?? '-');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), 
            ($appraisal['appraisal_period_from'] ?? '') . ' to ' . ($appraisal['appraisal_period_to'] ?? ''));
        
        // Get rating responses
        $ratingQuery = "SELECT r.employee_rating, r.manager_rating, fq.question_order
                        FROM responses r
                        JOIN form_questions fq ON r.question_id = fq.id
                        JOIN form_sections fs ON fq.section_id = fs.id
                        WHERE r.appraisal_id = ?
                        AND fq.response_type IN ('rating_5', 'rating_10')
                        ORDER BY fs.section_order, fq.question_order";
        $stmt = $db->prepare($ratingQuery);
        $stmt->execute([$appraisal['appraisal_id']]);
        $ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        
        // Employee scores
        $employeeTotal = 0;
        $employeeCount = 0;
        foreach ($ratings as $rating) {
            $score = $rating['employee_rating'] ?? '';
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), $score);
            if (is_numeric($score)) {
                $employeeTotal += floatval($score);
                $employeeCount++;
            }
        }
        // Fill empty question columns
        for ($i = count($ratings); $i < $maxQuestions; $i++) {
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), '');
        }
        
        $employeeScore = $employeeCount > 0 ? round(($employeeTotal / $employeeCount) * 10, 2) : 0;
        
        // Calculate grade inline
        if ($employeeScore >= 85) {
            $employeeGrade = 'A';
        } elseif ($employeeScore >= 75) {
            $employeeGrade = 'B+';
        } elseif ($employeeScore >= 65) {
            $employeeGrade = 'B';
        } elseif ($employeeScore >= 60) {
            $employeeGrade = 'B-';
        } else {
            $employeeGrade = 'C';
        }
        
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), $employeeTotal);
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), $employeeScore);
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), $employeeGrade);
        
        // Manager scores
        $managerTotal = 0;
        $managerCount = 0;
        foreach ($ratings as $rating) {
            $score = $rating['manager_rating'] ?? '';
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), $score);
            if (is_numeric($score)) {
                $managerTotal += floatval($score);
                $managerCount++;
            }
        }
        // Fill empty question columns
        for ($i = count($ratings); $i < $maxQuestions; $i++) {
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), '');
        }
        
        $managerScore = $managerCount > 0 ? round(($managerTotal / $managerCount) * 10, 2) : 0;
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), $managerTotal);
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), $managerScore);
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), $appraisal['grade'] ?? '-');
        
        // Training needs
        $trainingQuery = "SELECT r.employee_response, fq.question_order
                          FROM responses r
                          JOIN form_questions fq ON r.question_id = fq.id
                          JOIN form_sections fs ON fq.section_id = fs.id
                          WHERE r.appraisal_id = ?
                          AND fq.response_type = 'training_needs'
                          ORDER BY fs.section_order, fq.question_order";
        $stmt = $db->prepare($trainingQuery);
        $stmt->execute([$appraisal['appraisal_id']]);
        $training = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        
        foreach ($training as $t) {
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), 
                $t['employee_response'] ?? '');
        }
        // Fill empty training columns
        for ($i = count($training); $i < $maxTrainingNeeds; $i++) {
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++), '');
        }
        
        $row++;
    }
    
    // Apply borders to all data
    $lastRow = $row - 1;
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex - 1);
    $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $lastRow)->applyFromArray([
        'borders' => ['allBorders' => [
            'borderStyle' => Border::BORDER_THIN, 
            'color' => ['rgb' => 'CCCCCC']
        ]]
    ]);
    
    // Auto-size columns
    for ($i = 1; $i < $colIndex; $i++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }
    
    // Freeze panes
    $sheet->freezePane('J' . ($headerRow + 2));
    
    // Add filters
    $sheet->setAutoFilter('A' . ($headerRow + 1) . ':' . $lastCol . $lastRow);
}
/**
 * Calculate statistics
 */
function calculateStatistics($appraisals) {
    $stats = [
        'avg_score' => 0,
        'grades' => ['A' => 0, 'B+' => 0, 'B' => 0, 'B-' => 0, 'C' => 0]
    ];
    
    $total_score = 0;
    foreach ($appraisals as $appraisal) {
        $total_score += $appraisal['total_score'] ?? 0;
        $grade = $appraisal['grade'] ?? '';
        if (isset($stats['grades'][$grade])) {
            $stats['grades'][$grade]++;
        }
    }
    
    $stats['avg_score'] = count($appraisals) > 0 ? $total_score / count($appraisals) : 0;
    
    return $stats;
}

/**
 * Create summary sheet (for detailed export)
 */
function createSummarySheet($sheet, $appraisals, $company_name, $year) {
    // Title
    $sheet->setCellValue('A1', 'Performance Appraisal Summary Report');
    $sheet->mergeCells('A1:H1');
    $sheet->getStyle('A1')->applyFromArray([
        'font'=>['bold'=>true,'size'=>16,'color'=>['rgb'=>'FFFFFF']],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'0066CC']],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER]
    ]);
    $sheet->getRowDimension(1)->setRowHeight(30);

    // Info
    $sheet->setCellValue('A2','Company:');
    $sheet->setCellValue('B2',$company_name);
    $sheet->setCellValue('A3','Year:');
    $sheet->setCellValue('B3',$year);
    $sheet->setCellValue('A4','Generated:');
    $sheet->setCellValue('B4',date('Y-m-d H:i:s'));
    $sheet->setCellValue('A5','Total Appraisals:');
    $sheet->setCellValue('B5',count($appraisals));
    $sheet->getStyle('A2:A5')->getFont()->setBold(true);

    // Statistics
    $stats = ['A'=>0,'B+'=>0,'B'=>0,'B-'=>0,'C'=>0];
    $total_score = 0;
    foreach ($appraisals as $app) {
        $grade = $app['grade'] ?? '-';
        $score = $app['total_score'] ?? 0;
        if (isset($stats[$grade])) $stats[$grade]++;
        $total_score += $score;
    }
    $avg_score = count($appraisals) ? $total_score / count($appraisals) : 0;

    $sheet->setCellValue('D2','Grade Distribution:');
    $sheet->getStyle('D2')->getFont()->setBold(true);
    $row = 3;
    foreach ($stats as $grade => $count) {
        $sheet->setCellValue('D'.$row, $grade.':');
        $sheet->setCellValue('E'.$row, $count);
        $row++;
    }

    $sheet->setCellValue('D8','Average Score:');
    $sheet->setCellValue('E8',number_format($avg_score,2).'%');
    $sheet->getStyle('D8')->getFont()->setBold(true);

    // Employee table
    $row = 10;
    $headers = ['No.','Employee Name','Emp No','Position','Department','Grade','Score (%)','Review Date'];
    $col='A';
    foreach($headers as $h) $sheet->setCellValue($col++.$row,$h);

    $sheet->getStyle('A'.$row.':H'.$row)->applyFromArray([
        'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'4472C4']],
        'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]]
    ]);

    $row++;
    $no=1;
    foreach($appraisals as $app) {
        $sheet->setCellValue('A'.$row,$no++);
        $sheet->setCellValue('B'.$row,$app['employee_name'] ?? '-');
        $sheet->setCellValue('C'.$row,$app['emp_number'] ?? '-');
        $sheet->setCellValue('D'.$row,$app['position'] ?? '-');
        $sheet->setCellValue('E'.$row,$app['department'] ?? '-');
        $sheet->setCellValue('F'.$row,$app['grade'] ?? '-');
        $sheet->setCellValue('G'.$row,number_format($app['total_score'] ?? 0,2));
        $sheet->setCellValue('H'.$row,!empty($app['manager_reviewed_at']) ? date('Y-m-d',strtotime($app['manager_reviewed_at'])) : '-');

        // Grade colors
        $colors=['A'=>'00B050','B+'=>'92D050','B'=>'FFC000','B-'=>'FF9900','C'=>'FF0000'];
        if(isset($colors[$app['grade']])) {
            $sheet->getStyle('F'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colors[$app['grade']]);
            $sheet->getStyle('F'.$row)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        }
        $row++;
    }

    foreach(range('A','H') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
}

/**
 * Create individual employee sheet
 */
function createEmployeeSheet($sheet,$appraisal,$form_structure,$responses){
    $row=1;
    $sheet->setCellValue('A'.$row,'PERFORMANCE APPRAISAL REPORT');
    $sheet->mergeCells('A'.$row.':D'.$row);
    $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle('A'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0066CC');
    $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row+=2;

    $info=[
        ['Employee Name:',$appraisal['employee_name']??'-','Employee No:',$appraisal['emp_number']??'-'],
        ['Position:',$appraisal['position']??'-','Department:',$appraisal['department']??'-'],
        ['Site:',$appraisal['site']??'-','Date Joined:',$appraisal['date_joined']??'-'],
        ['Period:',!empty($appraisal['appraisal_period_from'])?($appraisal['appraisal_period_from'].' to '.$appraisal['appraisal_period_to']):'-','',''],
        ['Final Grade:',$appraisal['grade']??'-','Total Score:',number_format($appraisal['total_score']??0,2)],
        ['Reviewed By:',$appraisal['manager_name']??'-','Review Date:',!empty($appraisal['manager_reviewed_at'])?date('Y-m-d',strtotime($appraisal['manager_reviewed_at'])):'-']
    ];

    foreach($info as $i){
        $sheet->setCellValue('A'.$row,$i[0]);
        $sheet->setCellValue('B'.$row,$i[1]);
        $sheet->setCellValue('C'.$row,$i[2]);
        $sheet->setCellValue('D'.$row,$i[3]);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $sheet->getStyle('C'.$row)->getFont()->setBold(true);
        $row++;
    }
    $row+=2;

    $current_section='';
    foreach($responses as $r){
        $section=$r['section_title']??'General';
        if($current_section!=$section){
            $current_section=$section;
            $sheet->setCellValue('A'.$row,$current_section);
            $sheet->mergeCells('A'.$row.':D'.$row);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
            $row++;
            $sheet->setCellValue('A'.$row,'Question');
            $sheet->setCellValue('B'.$row,'Employee Response');
            $sheet->setCellValue('C'.$row,'Manager Response');
            $sheet->setCellValue('D'.$row,'Comments');
            $sheet->getStyle('A'.$row.':D'.$row)->getFont()->setBold(true);
            $sheet->getStyle('A'.$row.':D'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');
            $row++;
        }

        $sheet->setCellValue('A'.$row,$r['question_text']??'-');
        $sheet->setCellValue('B'.$row,$r['employee_response']??$r['employee_rating']??'-');
        $sheet->setCellValue('C'.$row,$r['manager_response']??$r['manager_rating']??'-');
        $sheet->setCellValue('D'.$row,implode("\n",array_filter([$r['employee_comments']??'',$r['manager_comments']??''])));
        $sheet->getStyle('A'.$row.':D'.$row)->getAlignment()->setWrapText(true);
        $row++;
    }

    foreach(range('A','D') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);
}
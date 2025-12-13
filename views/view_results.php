<?php
require_once '../config.php';
requireAdmin();

$conn = getDBConnection();

// Get session ID from URL parameter or use latest
$selectedSessionId = isset($_GET['session_id']) ? intval($_GET['session_id']) : null;

// Get all sessions for dropdown
$allSessionsQuery = "SELECT id, session_name, status, created_at FROM voting_sessions WHERE deleted_at IS NULL ORDER BY id DESC";
$allSessions = $conn->query($allSessionsQuery);

// Get the selected session or latest session
if ($selectedSessionId) {
    $sessionQuery = "SELECT * FROM voting_sessions WHERE id = ? AND deleted_at IS NULL";
    $stmt = $conn->prepare($sessionQuery);
    $stmt->bind_param("i", $selectedSessionId);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    // Get the latest session when no session_id is provided
    $sessionQuery = "SELECT * FROM voting_sessions WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 1";
    $sessionResult = $conn->query($sessionQuery);
    $session = $sessionResult->fetch_assoc();
}

$noSession = false;
if (!$session) {
    $noSession = true;
}

// Get all positions
$positionsQuery = "SELECT * FROM positions WHERE deleted_at IS NULL ORDER BY position_order";
$positions = $conn->query($positionsQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Results - VoteSystem Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            min-height: 100vh;
            padding-bottom: 2rem;
        }

        /* Enhanced Navbar */
        .modern-navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 1rem 2rem;
            margin-bottom: 2rem;
        }

        .navbar-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .brand-text h1 {
            font-size: 1.5rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-text p {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .modern-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Enhanced Cards */
        .modern-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.75rem 2rem;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }

        .card-body {
            padding: 2rem;
        }

        /* Session Selector */
        .session-selector {
            margin-bottom: 2rem;
        }

        .session-selector label {
            display: block;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .session-selector select {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid #d1fae5;
            border-radius: 12px;
            font-size: 1rem;
            background: white;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        .session-selector select:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        /* Session Header */
        .session-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .session-details h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .session-meta {
            color: #6b7280;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.375rem 0.875rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-locked {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-paused {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Export Actions */
        .export-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn-modern {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.5);
        }

        .btn-secondary {
            background: white;
            color: #1f2937;
            border: 2px solid #d1fae5;
        }

        .btn-secondary:hover {
            border-color: #10b981;
            background: #f0fdf4;
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(52, 211, 153, 0.4);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(52, 211, 153, 0.5);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.4);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.5);
        }

        /* Position Card */
        .position-card {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 0;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .position-card:hover {
            border-color: #10b981;
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.15);
        }

        .position-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .position-header h3 {
            font-size: 1.25rem;
            font-weight: 700;
        }

        .priority-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.375rem 0.875rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .results-content {
            padding: 1.5rem 2rem;
        }

        /* Candidate Result */
        .candidate-result {
            display: flex;
            align-items: center;
            padding: 1.25rem;
            border-bottom: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            gap: 1.5rem;
        }

        .candidate-result:hover {
            background: #f0fdf4;
        }

        .candidate-result:last-child {
            border-bottom: none;
        }

        .candidate-result.winner {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border-left: 5px solid #10b981;
        }

        .candidate-info {
            flex: 1;
        }

        .candidate-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .candidate-id {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .winner-badge {
            background: #10b981;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }

        .vote-stats {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .vote-count {
            font-size: 2rem;
            font-weight: 700;
            color: #10b981;
            min-width: 60px;
            text-align: center;
        }

        .vote-bar {
            width: 200px;
            height: 12px;
            background: #e5e7eb;
            border-radius: 50px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);
        }

        .vote-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.75rem;
            border-radius: 50px;
            transition: width 0.5s ease;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.75rem;
        }

        .empty-description {
            color: #6b7280;
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }

        .no-votes {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
            font-size: 0.95rem;
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .modern-navbar,
            .session-selector,
            .no-print {
                display: none !important;
            }

            .modern-container {
                max-width: 100%;
                padding: 20mm;
            }

            .modern-card {
                box-shadow: none;
                border: 1px solid #e5e7eb;
                page-break-inside: avoid;
                margin-bottom: 1.5rem;
            }

            .card-body {
                padding: 1.5rem;
            }

            .session-info {
                display: block;
            }

            .export-actions {
                display: none !important;
            }

            .session-details h2 {
                font-size: 1.75rem;
                margin-bottom: 0.5rem;
            }

            .position-card {
                page-break-inside: avoid;
                border: 1px solid #e5e7eb;
                margin-bottom: 1.5rem;
            }

            .position-header {
                background: #f3f4f6 !important;
                color: #1f2937 !important;
                border-bottom: 2px solid #10b981;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .position-header h3 {
                color: #1f2937;
            }

            .priority-badge {
                background: white;
                color: #10b981;
                border: 2px solid #10b981;
            }

            .candidate-result {
                border-bottom: 1px solid #e5e7eb;
                page-break-inside: avoid;
            }

            .candidate-result.winner {
                background: #f0fdf4 !important;
                border-left: 4px solid #10b981;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .winner-badge {
                background: #10b981 !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .vote-bar {
                border: 1px solid #e5e7eb;
            }

            .vote-bar-fill {
                background: #10b981 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .status-badge {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Print header */
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 2rem;
                padding-bottom: 1rem;
                border-bottom: 3px solid #10b981;
            }

            .print-title {
                font-size: 2rem;
                font-weight: 800;
                color: #111827;
                margin-bottom: 0.5rem;
                text-transform: uppercase;
            }

            .print-subtitle {
                font-size: 1.125rem;
                color: #6b7280;
                font-weight: 500;
            }

            /* Print footer */
            .print-footer {
                display: block !important;
                margin-top: 2rem;
                padding-top: 1rem;
                border-top: 2px solid #e5e7eb;
                font-size: 0.75rem;
                color: #6b7280;
                text-align: center;
            }
        }

        /* Print-only elements */
        .print-header,
        .print-footer {
            display: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modern-container {
                padding: 0 1rem;
            }

            .navbar-content {
                flex-direction: column;
                gap: 1rem;
            }

            .session-info {
                flex-direction: column;
            }

            .export-actions {
                flex-direction: column;
                width: 100%;
            }

            .btn-modern {
                width: 100%;
                justify-content: center;
            }

            .candidate-result {
                flex-direction: column;
                align-items: flex-start;
            }

            .vote-stats {
                width: 100%;
                justify-content: space-between;
            }

            .vote-bar {
                flex: 1;
                min-width: 100px;
            }

            .position-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Enhanced Navbar -->
    <nav class="modern-navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <div class="brand-text">
                    <h1>VoteSystem Pro</h1>
                    <p>Election Results</p>
                </div>
            </div>
            <div class="user-section">
                <a href="../admin/admin_dashboard.php" class="btn-modern btn-secondary">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </nav>
    
    <div class="modern-container">
        <!-- Print Header (only visible when printing) -->
        <div class="print-header">
            <div class="print-title">Official Election Results</div>
            <div class="print-subtitle">VoteSystem Pro</div>
            <div style="margin-top: 0.5rem; font-size: 0.875rem;">
                Generated on <?php echo date('F d, Y \a\t h:i A'); ?>
            </div>
        </div>

        <?php if ($noSession): ?>
            <div class="modern-card fade-in">
                <div class="card-body">
                    <div class="empty-state">
                        <h2 class="empty-title">No Voting Sessions Yet</h2>
                        <p class="empty-description">There are no voting sessions to display results for.</p>
                        <p style="color: #a0aec0; font-size: 0.875rem; margin-bottom: 1.5rem;">Create a voting session to get started!</p>
                        <a href="../helper/create_session.php" class="btn-modern btn-primary">
                            Create New Session
                        </a>
                    </div>
                </div>
            </div>
        <?php else: 
            $sessionId = $session['id'];
        ?>
        
        <!-- Session Selector -->
        <div class="modern-card fade-in">
            <div class="card-body session-selector">
                <label for="session-select">Select Election Session</label>
                <select id="session-select" onchange="window.location.href='view_results.php?session_id=' + this.value">
                    <?php 
                    $allSessions->data_seek(0);
                    while ($sess = $allSessions->fetch_assoc()): 
                        $selected = ($sess['id'] == $sessionId) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $sess['id']; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($sess['session_name']); ?> 
                            (<?php echo strtoupper($sess['status']); ?>) - 
                            <?php echo date('M d, Y', strtotime($sess['created_at'])); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        
        <!-- Session Header -->
        <div class="modern-card fade-in" style="animation-delay: 0.1s;">
            <div class="card-body">
                <div class="session-info">
                    <div class="session-details">
                        <h2><?php echo htmlspecialchars($session['session_name']); ?></h2>
                        <div class="session-meta">
                            <span><strong>Created:</strong> <?php echo date('F d, Y h:i A', strtotime($session['created_at'])); ?></span>
                            <span style="margin: 0 0.5rem;">•</span>
                            <span class="status-badge badge-<?php echo $session['status']; ?>">
                                <?php echo strtoupper($session['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="export-actions">
                        <a href="../admin/export_results_excel.php?session_id=<?php echo $sessionId; ?>" class="btn-modern btn-success">
                            Export to Excel
                        </a>
                        <a href="../admin/export_results_pdf.php?session_id=<?php echo $sessionId; ?>" class="btn-modern btn-danger" target="_blank">
                            Export to PDF
                        </a>
                        <button onclick="window.print()" class="btn-modern btn-primary">
                            Print Results
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <?php 
        // Track elected users for higher positions
        $electedUsers = [];
        
        if ($positions->num_rows === 0): ?>
            <div class="modern-card fade-in" style="animation-delay: 0.2s;">
                <div class="card-body">
                    <div class="empty-state">
                        <p class="empty-description">No positions created yet.</p>
                    </div>
                </div>
            </div>
        <?php else:
            $delay = 0.2;
            while ($position = $positions->fetch_assoc()): 
                $delay += 0.1;
                $positionId = $position['id'];
                
                // First, check if there's a stored winner for this position in this session
                $storedWinnerQuery = "SELECT w.user_id, 
                                      TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)) AS full_name,
                                      u.student_id
                                      FROM winners w
                                      JOIN users u ON w.user_id = u.id
                                      WHERE w.session_id = ? AND w.position_id = ?";
                $winnerStmt = $conn->prepare($storedWinnerQuery);
                $winnerStmt->bind_param("ii", $sessionId, $positionId);
                $winnerStmt->execute();
                $storedWinner = $winnerStmt->get_result()->fetch_assoc();
                $winnerStmt->close();
                
                // If winner exists, compute their vote count dynamically
                if ($storedWinner) {
                    $storedWinner['vote_count'] = getWinnerVoteCount($sessionId, $positionId, $storedWinner['user_id'], $conn);
                }
                
                // Get all candidates who received votes for this position
                $resultsQuery = "SELECT v.candidate_id, c.user_id,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)), ''),
                    MAX(v.snapshot_candidate_name)
                ) AS full_name,
                COALESCE(u.student_id, MAX(v.snapshot_candidate_student_id)) AS student_id,
                COUNT(v.id) as vote_count
                FROM votes v
                LEFT JOIN candidates c ON v.candidate_id = c.id
                LEFT JOIN users u ON c.user_id = u.id
                WHERE v.session_id = ? AND v.position_id = ? AND v.deleted_at IS NULL
                GROUP BY v.candidate_id, c.user_id, u.first_name, u.middle_name, u.last_name, u.student_id
                ORDER BY vote_count DESC, full_name";
                
                $stmt = $conn->prepare($resultsQuery);
                $stmt->bind_param("ii", $sessionId, $positionId);
                $stmt->execute();
                $results = $stmt->get_result();
                
                // Get total votes for percentage calculation
                $totalVotesQuery = "SELECT COUNT(*) as total FROM votes WHERE session_id = ? AND position_id = ? AND deleted_at IS NULL";
                $totalStmt = $conn->prepare($totalVotesQuery);
                $totalStmt->bind_param("ii", $sessionId, $positionId);
                $totalStmt->execute();
                $totalVotes = $totalStmt->get_result()->fetch_assoc()['total'];
                
                // Determine the winner
                $winner = null;
                $tempResults = [];
                
                if ($storedWinner) {
                    $winner = $storedWinner;
                    $electedUsers[] = $storedWinner['user_id'];
                }
                
                // Collect all results
                while ($row = $results->fetch_assoc()) {
                    if (!$row['full_name'] && $storedWinner && $row['candidate_id']) {
                        $row['full_name'] = $storedWinner['full_name'];
                        $row['student_id'] = $storedWinner['student_id'];
                        $row['user_id'] = $storedWinner['user_id'];
                    }
                    
                    $tempResults[] = $row;
                    
                    if (!$winner && $row['full_name'] && $row['user_id'] && !in_array($row['user_id'], $electedUsers) && $row['vote_count'] > 0) {
                        $winner = $row;
                        if ($row['user_id']) {
                            $electedUsers[] = $row['user_id'];
                        }
                    }
                }
            ?>
            
            <div class="position-card fade-in" style="animation-delay: <?php echo $delay; ?>s;">
                <div class="position-header">
                    <h3><?php echo htmlspecialchars($position['position_name']); ?></h3>
                    <span class="priority-badge">Priority <?php echo $position['position_order']; ?></span>
                </div>
                
                <div class="results-content">
                    <?php if (count($tempResults) > 0 || $storedWinner): 
                        if (count($tempResults) == 0 && $storedWinner) {
                            $tempResults[] = $storedWinner;
                        }
                        
                        foreach ($tempResults as $result): 
                            $percentage = $totalVotes > 0 ? ($result['vote_count'] / $totalVotes) * 100 : 0;
                            $isWinner = ($winner && isset($result['user_id']) && isset($winner['user_id']) && $winner['user_id'] === $result['user_id']);
                    ?>
                        <div class="candidate-result <?php echo $isWinner ? 'winner' : ''; ?>">
                            <div class="candidate-info">
                                <div class="candidate-name">
                                    <?php 
                                    if ($result['full_name']) {
                                        echo htmlspecialchars($result['full_name']); 
                                    } else {
                                        echo '<span style="color: #a0aec0;">Candidate (data removed)</span>';
                                    }
                                    ?>
                                    <?php if ($isWinner): ?>
                                        <span class="winner-badge">WINNER</span>
                                    <?php endif; ?>
                                </div>
                                <div class="candidate-id">
                                    <?php 
                                    if (isset($result['student_id']) && $result['student_id']) {
                                        echo htmlspecialchars($result['student_id']); 
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="vote-stats">
                                <div class="vote-count"><?php echo $result['vote_count']; ?></div>
                                <div class="vote-bar">
                                    <div class="vote-bar-fill" style="width: <?php echo $percentage; ?>%">
                                        <?php echo round($percentage); ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <div class="no-votes">
                            No votes cast for this position yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php 
                $stmt->close();
                $totalStmt->close();
            endwhile;
        endif; 
        ?>
        
        <?php endif; ?>

        <!-- Print Footer (only visible when printing) -->
        <div class="print-footer">
            <div style="margin-bottom: 0.5rem;">
                <strong>Report Date:</strong> <?php echo date('F d, Y'); ?> | 
                <strong>Generated By:</strong> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </div>
            <div>
                VoteSystem Pro - Official Election Results Document<br>
                This report contains verified and finalized election results
            </div>
        </div>
    </div>
    
    <?php $conn->close(); ?>
    
    <script>
        // Auto-refresh only if viewing active session
        <?php if (!$noSession && $session['status'] === 'active'): ?>
        setTimeout(function() {
            location.reload();
        }, 10000);
        <?php endif; ?>
    </script>
</body>
</html>
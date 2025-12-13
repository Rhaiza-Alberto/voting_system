<?php
require_once '../config.php';
requireAdmin();

$conn = getDBConnection();

// Get latest or specified session
$sessionId = isset($_GET['session_id']) ? $_GET['session_id'] : null;

if ($sessionId) {
    $sessionQuery = "SELECT * FROM voting_sessions WHERE id = ?";
    $stmt = $conn->prepare($sessionQuery);
    $stmt->bind_param("i", $sessionId);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $sessionQuery = "SELECT * FROM voting_sessions ORDER BY id DESC LIMIT 1";
    $session = $conn->query($sessionQuery)->fetch_assoc();
}

if (!$session) {
    die("No voting session found");
}

$sessionId = $session['id'];
$sessionName = $session['session_name'];

// Get all positions
$positionsQuery = "SELECT * FROM positions ORDER BY position_order";
$positions = $conn->query($positionsQuery);

// Get total votes
$totalVotesQuery = "SELECT COUNT(*) as total FROM votes WHERE session_id = ?";
$stmt = $conn->prepare($totalVotesQuery);
$stmt->bind_param("i", $sessionId);
$stmt->execute();
$totalVotes = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get unique voters
$uniqueVotersQuery = "SELECT COUNT(DISTINCT voter_id) as total FROM votes WHERE session_id = ?";
$stmt = $conn->prepare($uniqueVotersQuery);
$stmt->bind_param("i", $sessionId);
$stmt->execute();
$uniqueVoters = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Track elected users
$electedUsers = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Results - <?php echo htmlspecialchars($sessionName); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.6;
            color: #1f2937;
            background: #ffffff;
            padding: 30px;
        }
        
        /* Control Panel */
        .control-panel {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 1.25rem;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            display: flex;
            gap: 0.75rem;
            border: 2px solid #e5e7eb;
        }
        
        .control-btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
        }
        
        .btn-export {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.4);
        }
        
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(59, 130, 246, 0.5);
        }
        
        .btn-back {
            background: white;
            color: #1f2937;
            border: 2px solid #d1fae5;
        }
        
        .btn-back:hover {
            background: #f0fdf4;
            border-color: #10b981;
            transform: translateY(-2px);
        }
        
        /* Document Container */
        .document-wrapper {
            max-width: 900px;
            margin: 0 auto;
            background: white;
        }
        
        /* Header Section */
        .document-header {
            text-align: center;
            padding: 2.5rem 0;
            border-bottom: 3px solid #10b981;
            margin-bottom: 2.5rem;
            position: relative;
        }
        
        .header-badge {
            display: inline-block;
            width: 70px;
            height: 70px;
            margin: 0 auto 1.25rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }
        
        .header-icon {
            width: 36px;
            height: 36px;
            background: white;
            border-radius: 8px;
        }
        
        .document-title {
            font-size: 2rem;
            font-weight: 800;
            color: #111827;
            margin-bottom: 0.75rem;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }
        
        .document-subtitle {
            font-size: 1.5rem;
            color: #10b981;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .document-timestamp {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
            margin-top: 1rem;
        }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
            margin-bottom: 2.5rem;
            page-break-inside: avoid;
        }
        
        .info-item {
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #10b981;
        }
        
        .info-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        .info-value {
            font-size: 1.125rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .status-tag {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active { background: #d1fae5; color: #065f46; }
        .status-locked { background: #fee2e2; color: #991b1b; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-paused { background: #dbeafe; color: #1e40af; }
        
        /* Summary Box */
        .summary-box {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 2px solid #10b981;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2.5rem;
            page-break-inside: avoid;
        }
        
        .summary-heading {
            font-size: 1.125rem;
            font-weight: 700;
            color: #065f46;
            margin-bottom: 1.5rem;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            text-align: center;
        }
        
        .stat-box {
            padding: 1rem;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: #10b981;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #065f46;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Position Block */
        .position-block {
            margin-bottom: 2.5rem;
            page-break-inside: avoid;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .position-title-bar {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1.25rem 1.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .position-name {
            font-size: 1.375rem;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        
        .position-badge {
            background: rgba(255, 255, 255, 0.25);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        
        /* Results List */
        .results-list {
            background: white;
            padding: 1.5rem;
        }
        
        .result-row {
            display: flex;
            align-items: center;
            padding: 1.25rem;
            border-bottom: 1px solid #e5e7eb;
            gap: 1.5rem;
        }
        
        .result-row:last-child {
            border-bottom: none;
        }
        
        .result-row.winner-row {
            background: linear-gradient(90deg, #ecfdf5 0%, #d1fae5 100%);
            border-left: 4px solid #10b981;
            margin-left: -1.5rem;
            margin-right: -1.5rem;
            padding-left: calc(1.25rem + 4px);
        }
        
        .rank-badge {
            min-width: 50px;
            text-align: center;
        }
        
        .rank-number {
            font-weight: 800;
            font-size: 1.5rem;
            color: #10b981;
        }
        
        .candidate-details {
            flex: 1;
        }
        
        .candidate-name-text {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .candidate-id-text {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .vote-display {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        
        .vote-number {
            font-weight: 700;
            font-size: 1.375rem;
            color: #1f2937;
            min-width: 60px;
            text-align: center;
        }
        
        .progress-wrapper {
            width: 180px;
        }
        
        .progress-track {
            background: #e5e7eb;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-indicator {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.75rem;
            min-width: 35px;
            border-radius: 10px;
        }
        
        .winner-indicator {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #9ca3af;
            font-style: italic;
        }
        
        /* Footer Section */
        .document-footer {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid #10b981;
            page-break-inside: avoid;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }
        
        .footer-detail {
            font-size: 0.875rem;
            color: #4b5563;
        }
        
        .footer-label-text {
            font-weight: 700;
            color: #1f2937;
            margin-right: 0.5rem;
        }
        
        .footer-disclaimer {
            text-align: center;
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 1.25rem;
            padding-top: 1.25rem;
            border-top: 1px solid #e5e7eb;
            line-height: 1.6;
        }
        
        @media print {
            .control-panel {
                display: none !important;
            }
            
            body {
                padding: 0;
            }
            
            .position-block {
                page-break-inside: avoid;
            }
            
            .summary-box {
                page-break-inside: avoid;
            }
            
            .document-footer {
                page-break-inside: avoid;
            }
        }
        
        @media (max-width: 768px) {
            .control-panel {
                position: static;
                margin-bottom: 1.5rem;
                flex-direction: column;
            }
            
            .control-btn {
                width: 100%;
                justify-content: center;
            }
            
            .info-grid,
            .stats-row,
            .footer-grid {
                grid-template-columns: 1fr;
            }
            
            .result-row {
                flex-wrap: wrap;
            }
            
            .vote-display {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
    <!-- Control Panel -->
    <div class="control-panel">
        <button onclick="exportToPDF()" class="control-btn btn-export">
            Download PDF
        </button>
        <a href="../views/view_results.php" class="control-btn btn-back">
            Back to Results
        </a>
    </div>
    
    <!-- Document Content -->
    <div class="document-wrapper" id="pdf-content">
        <!-- Header -->
        <div class="document-header">
            <h1 class="document-title">Official Election Results</h1>
            <div class="document-subtitle"><?php echo htmlspecialchars($sessionName); ?></div>
            <div class="document-timestamp">
                Report Generated: <?php echo date('F d, Y \a\t h:i A'); ?>
            </div>
        </div>
        
        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Election Status</div>
                <div class="info-value">
                    <span class="status-tag status-<?php echo $session['status']; ?>">
                        <?php echo strtoupper($session['status']); ?>
                    </span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Election Date</div>
                <div class="info-value">
                    <?php echo date('F d, Y', strtotime($session['created_at'])); ?>
                </div>
            </div>
        </div>
        
        <!-- Summary Box -->
        <div class="summary-box">
            <div class="summary-heading">Election Overview</div>
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $totalVotes; ?></div>
                    <div class="stat-label">Total Votes</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $uniqueVoters; ?></div>
                    <div class="stat-label">Participants</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $positions->num_rows; ?></div>
                    <div class="stat-label">Positions</div>
                </div>
            </div>
        </div>
        
        <!-- Position Results -->
        <?php
        $positions->data_seek(0);
        while ($position = $positions->fetch_assoc()):
            $positionId = $position['id'];
            $positionName = $position['position_name'];
            
            // Check for stored winner
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
            
            if ($storedWinner) {
                $storedWinner['vote_count'] = getWinnerVoteCount($sessionId, $positionId, $storedWinner['user_id'], $conn);
            }
            
            // Get all candidates and votes
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
                            WHERE v.session_id = ? AND v.position_id = ?
                            GROUP BY v.candidate_id, c.user_id, u.first_name, u.middle_name, u.last_name, u.student_id
                            ORDER BY vote_count DESC, full_name";
            
            $stmt = $conn->prepare($resultsQuery);
            $stmt->bind_param("ii", $sessionId, $positionId);
            $stmt->execute();
            $results = $stmt->get_result();
            
            // Get total votes for position
            $posVotesQuery = "SELECT COUNT(*) as total FROM votes WHERE session_id = ? AND position_id = ?";
            $posStmt = $conn->prepare($posVotesQuery);
            $posStmt->bind_param("ii", $sessionId, $positionId);
            $posStmt->execute();
            $positionTotalVotes = $posStmt->get_result()->fetch_assoc()['total'];
            $posStmt->close();
            
            // Determine winner
            $winner = null;
            $tempResults = [];
            
            if ($storedWinner) {
                $winner = $storedWinner;
                $electedUsers[] = $storedWinner['user_id'];
            }
            
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
            
            if (count($tempResults) == 0 && $storedWinner) {
                $tempResults[] = $storedWinner;
            }
        ?>
        
        <div class="position-block">
            <div class="position-title-bar">
                <div class="position-name"><?php echo htmlspecialchars($positionName); ?></div>
                <div class="position-badge">Priority <?php echo $position['position_order']; ?></div>
            </div>
            
            <div class="results-list">
                <?php if (count($tempResults) > 0):
                    $rank = 1;
                    foreach ($tempResults as $result):
                        $isWinner = ($winner && isset($result['user_id']) && isset($winner['user_id']) && $winner['user_id'] === $result['user_id']);
                        $percentage = $positionTotalVotes > 0 ? ($result['vote_count'] / $positionTotalVotes * 100) : 0;
                ?>
                    <div class="result-row <?php echo $isWinner ? 'winner-row' : ''; ?>">
                        <div class="rank-badge">
                            <div class="rank-number"><?php echo $rank; ?></div>
                        </div>
                        <div class="candidate-details">
                            <div class="candidate-name-text">
                                <?php 
                                if ($result['full_name']) {
                                    echo htmlspecialchars($result['full_name']); 
                                } else {
                                    echo '<span style="color: #9ca3af;">Candidate (data removed)</span>';
                                }
                                ?>
                            </div>
                            <?php if (isset($result['student_id']) && $result['student_id']): ?>
                                <div class="candidate-id-text">ID: <?php echo htmlspecialchars($result['student_id']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="vote-display">
                            <div class="vote-number"><?php echo $result['vote_count']; ?></div>
                            <div class="progress-wrapper">
                                <div class="progress-track">
                                    <div class="progress-indicator" style="width: <?php echo $percentage; ?>%">
                                        <?php echo round($percentage); ?>%
                                    </div>
                                </div>
                            </div>
                            <?php if ($isWinner): ?>
                                <div class="winner-indicator">Winner</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php
                        $rank++;
                    endforeach;
                else:
                ?>
                    <div class="no-results">
                        No candidates nominated for this position
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php
            $stmt->close();
        endwhile;
        ?>
        
        <!-- Footer -->
        <div class="document-footer">
            <div class="footer-grid">
                <div class="footer-detail">
                    <span class="footer-label-text">Report Date:</span>
                    <?php echo date('F d, Y'); ?>
                </div>
                <div class="footer-detail">
                    <span class="footer-label-text">Generated By:</span>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </div>
            </div>
            <div class="footer-disclaimer">
                VoteSystem Pro - Official Election Results Document<br>
                This report contains verified and finalized election results
            </div>
        </div>
    </div>
    
    <?php $conn->close(); ?>
    
    <script>
        function exportToPDF() {
            const controlPanel = document.querySelector('.control-panel');
            controlPanel.style.display = 'none';
            
            const element = document.getElementById('pdf-content');
            const sessionName = '<?php echo addslashes($sessionName); ?>';
            const timestamp = new Date().toISOString().slice(0, 10);
            const filename = `Election_Results_${sessionName.replace(/[^a-zA-Z0-9]/g, '_')}_${timestamp}.pdf`;
            
            const opt = {
                margin: [12, 12, 12, 12],
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2,
                    useCORS: true,
                    letterRendering: true,
                    logging: false
                },
                jsPDF: { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'portrait' 
                },
                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
            };
            
            html2pdf().set(opt).from(element).save().then(function() {
                controlPanel.style.display = 'flex';
            });
        }
    </script>
</body>
</html>
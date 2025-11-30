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
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', 'Arial', 'Helvetica', sans-serif;
            line-height: 1.6;
            color: #2d3748;
            background: white;
            padding: 20px;
        }
        
        /* Header Section */
        .document-header {
            text-align: center;
            padding: 30px 0;
            border-bottom: 4px solid #10b981;
            margin-bottom: 30px;
            position: relative;
        }
        
        .logo-placeholder {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            font-weight: bold;
        }
        
        .document-title {
            font-size: 32px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .document-subtitle {
            font-size: 20px;
            color: #10b981;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .document-date {
            font-size: 14px;
            color: #718096;
            margin-top: 10px;
        }
        
        /* Metadata Grid */
        .metadata-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        
        .metadata-card {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            padding: 20px;
            border-radius: 8px;
            border-left: 5px solid #10b981;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        
        .metadata-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #718096;
            margin-bottom: 8px;
            display: block;
        }
        
        .metadata-value {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .status-indicator {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active { background: #c6f6d5; color: #22543d; }
        .status-locked { background: #fed7d7; color: #742a2a; }
        .status-pending { background: #e2e8f0; color: #4a5568; }
        .status-paused { background: #feebc8; color: #744210; }
        
        /* Summary Statistics */
        .summary-section {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 2px solid #10b981;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 35px;
            page-break-inside: avoid;
        }
        
        .summary-title {
            font-size: 18px;
            font-weight: 700;
            color: #065f46;
            margin-bottom: 20px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            text-align: center;
        }
        
        .summary-item {
            padding: 15px;
        }
        
        .summary-number {
            font-size: 42px;
            font-weight: 800;
            color: #10b981;
            line-height: 1;
            margin-bottom: 8px;
        }
        
        .summary-label {
            font-size: 13px;
            color: #065f46;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Position Results */
        .position-section {
            margin-bottom: 35px;
            page-break-inside: avoid;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .position-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 18px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .position-name {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        
        .position-meta {
            font-size: 13px;
            opacity: 0.95;
            font-weight: 600;
        }
        
        .position-priority {
            background: rgba(255,255,255,0.25);
            padding: 6px 14px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 700;
            margin-left: 15px;
        }
        
        /* Results Table */
        .results-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        .results-table thead {
            background: #f7fafc;
        }
        
        .results-table th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4a5568;
            border-bottom: 2px solid #cbd5e0;
        }
        
        .results-table td {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        
        .results-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .rank-cell {
            font-weight: 800;
            font-size: 20px;
            color: #10b981;
            width: 60px;
            text-align: center;
        }
        
        .candidate-cell {
            font-weight: 600;
            color: #2d3748;
        }
        
        .candidate-name {
            font-size: 15px;
            margin-bottom: 3px;
        }
        
        .candidate-id {
            font-size: 12px;
            color: #718096;
        }
        
        .votes-cell {
            font-weight: 700;
            font-size: 18px;
            color: #2d3748;
            text-align: center;
            width: 80px;
        }
        
        .percentage-cell {
            width: 140px;
        }
        
        .percentage-bar {
            background: #e2e8f0;
            height: 24px;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }
        
        .percentage-fill {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 11px;
            min-width: 40px;
        }
        
        .status-cell {
            text-align: center;
            width: 100px;
        }
        
        .winner-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 15px;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        }
        
        .winner-row {
            background: linear-gradient(90deg, #ecfdf5 0%, #d1fae5 100%);
            border-left: 5px solid #10b981 !important;
        }
        
        .winner-row td {
            border-bottom-color: #a7f3d0 !important;
        }
        
        .no-candidates-message {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
            font-style: italic;
            font-size: 15px;
        }
        
        /* Footer */
        .document-footer {
            margin-top: 50px;
            padding-top: 25px;
            border-top: 3px solid #10b981;
            page-break-inside: avoid;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .footer-item {
            font-size: 13px;
            color: #4a5568;
        }
        
        .footer-label {
            font-weight: 700;
            color: #2d3748;
            margin-right: 8px;
        }
        
        .footer-note {
            text-align: center;
            font-size: 11px;
            color: #718096;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        /* Control Buttons */
        .control-panel {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            display: flex;
            gap: 10px;
        }
        
        .control-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-pdf {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }
        
        .btn-pdf:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        
        .btn-back {
            background: #6b7280;
            color: white;
        }
        
        .btn-back:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }
        
        @media print {
            .control-panel {
                display: none !important;
            }
            
            body {
                padding: 0;
            }
            
            .position-section {
                page-break-inside: avoid;
            }
            
            .summary-section {
                page-break-inside: avoid;
            }
            
            .document-footer {
                page-break-inside: avoid;
            }
        }
        
        @media (max-width: 768px) {
            .metadata-section,
            .summary-grid {
                grid-template-columns: 1fr;
            }
            
            .control-panel {
                position: static;
                margin-bottom: 20px;
                flex-direction: column;
            }
            
            .control-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
    <!-- Control Panel -->
    <div class="control-panel">
        <button onclick="exportToPDF()" class="control-btn btn-pdf">
            📥 Export to PDF
        </button>
        <a href="view_results.php" class="control-btn btn-back" style="text-decoration: none;">
            ← Back
        </a>
    </div>
    
    <!-- Document Content -->
    <div class="document-content">
        <!-- Header -->
        <div class="document-header">
            <div class="logo-placeholder">🗳️</div>
            <h1 class="document-title">OFFICIAL ELECTION RESULTS</h1>
            <div class="document-subtitle"><?php echo htmlspecialchars($sessionName); ?></div>
            <div class="document-date">
                Generated on <?php echo date('F d, Y \a\t h:i A'); ?>
            </div>
        </div>
        
        <!-- Metadata Section -->
        <div class="metadata-section">
            <div class="metadata-card">
                <span class="metadata-label">Session Status</span>
                <div class="metadata-value">
                    <span class="status-indicator status-<?php echo $session['status']; ?>">
                        <?php echo strtoupper($session['status']); ?>
                    </span>
                </div>
            </div>
            <div class="metadata-card">
                <span class="metadata-label">Session Created</span>
                <div class="metadata-value">
                    <?php echo date('F d, Y', strtotime($session['created_at'])); ?>
                </div>
            </div>
        </div>
        
        <!-- Summary Statistics -->
        <div class="summary-section">
            <div class="summary-title">Election Summary</div>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-number"><?php echo $totalVotes; ?></div>
                    <div class="summary-label">Total Votes</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number"><?php echo $uniqueVoters; ?></div>
                    <div class="summary-label">Students Voted</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number"><?php echo $positions->num_rows; ?></div>
                    <div class="summary-label">Positions</div>
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
        
        <div class="position-section">
            <div class="position-header">
                <div>
                    <div class="position-name"><?php echo htmlspecialchars($positionName); ?></div>
                    <div class="position-meta">Total Votes: <?php echo $positionTotalVotes; ?></div>
                </div>
                <div class="position-priority">Priority #<?php echo $position['position_order']; ?></div>
            </div>
            
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Candidate</th>
                        <th>Votes</th>
                        <th>Percentage</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($tempResults) > 0):
                        $rank = 1;
                        $prevVotes = -1;
                        $actualRank = 1;
                        
                        foreach ($tempResults as $result):
                            if ($result['vote_count'] != $prevVotes) {
                                $actualRank = $rank;
                            }
                            
                            $isWinner = ($winner && isset($result['user_id']) && isset($winner['user_id']) && $winner['user_id'] === $result['user_id']);
                            $percentage = $positionTotalVotes > 0 ? ($result['vote_count'] / $positionTotalVotes * 100) : 0;
                    ?>
                        <tr class="<?php echo $isWinner ? 'winner-row' : ''; ?>">
                            <td class="rank-cell">#<?php echo $actualRank; ?></td>
                            <td class="candidate-cell">
                                <div class="candidate-name">
                                    <?php 
                                    if ($result['full_name']) {
                                        echo htmlspecialchars($result['full_name']); 
                                    } else {
                                        echo '<span style="color: #a0aec0;">Candidate (data removed)</span>';
                                    }
                                    ?>
                                </div>
                                <?php if (isset($result['student_id']) && $result['student_id']): ?>
                                    <div class="candidate-id"><?php echo htmlspecialchars($result['student_id']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="votes-cell"><?php echo $result['vote_count']; ?></td>
                            <td class="percentage-cell">
                                <div class="percentage-bar">
                                    <div class="percentage-fill" style="width: <?php echo $percentage; ?>%">
                                        <?php echo number_format($percentage, 1); ?>%
                                    </div>
                                </div>
                            </td>
                            <td class="status-cell">
                                <?php if ($isWinner): ?>
                                    <span class="winner-badge">🏆 Winner</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php
                            $prevVotes = $result['vote_count'];
                            $rank++;
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="5" class="no-candidates-message">
                                No candidates nominated for this position
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php
            $stmt->close();
        endwhile;
        ?>
        
        <!-- Footer -->
        <div class="document-footer">
            <div class="footer-content">
                <div class="footer-item">
                    <span class="footer-label">Generated:</span>
                    <?php echo date('F d, Y \a\t h:i A'); ?>
                </div>
                <div class="footer-item">
                    <span class="footer-label">Generated By:</span>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </div>
            </div>
            <div class="footer-note">
                This is an official document from the Classroom Voting System<br>
                All results are final and verified
            </div>
        </div>
    </div>
    
    <?php $conn->close(); ?>
    
    <script>
        function exportToPDF() {
            // Hide control panel
            const controlPanel = document.querySelector('.control-panel');
            controlPanel.style.display = 'none';
            
            const element = document.querySelector('.document-content');
            const sessionName = '<?php echo addslashes($sessionName); ?>';
            const timestamp = new Date().toISOString().slice(0, 10);
            const filename = `Election_Results_${sessionName.replace(/[^a-zA-Z0-9]/g, '_')}_${timestamp}.pdf`;
            
            const opt = {
                margin: [10, 10, 10, 10],
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
                // Show control panel again
                controlPanel.style.display = 'flex';
            });
        }
    </script>
</body>
</html>
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>Election Results - <?php echo htmlspecialchars($sessionName); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            padding: 40px;
            background: white;
            color: #000;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #10b981;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #10b981;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header .subtitle {
            font-size: 18px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .meta-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
            padding: 0;
        }
        
        .meta-item {
            background: #f7fafc;
            padding: 20px 25px;
            border-radius: 12px;
            border-left: 4px solid #10b981;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .meta-label {
            font-weight: bold;
            color: #333;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 8px;
        }
        
        .meta-value {
            color: #666;
            font-size: 18px;
            font-weight: 600;
            display: block;
        }
        
        .status-badge {
            display: inline-block;
            font-weight: 600;
            font-size: 16px;
            text-transform: uppercase;
        }
        
        .summary {
            background: #e6f7f0;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 40px;
            border-left: 5px solid #10b981;
        }
        
        .summary h2 {
            color: #10b981;
            font-size: 20px;
            margin-bottom: 15px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .summary-item {
            text-align: center;
        }
        
        .summary-number {
            font-size: 32px;
            font-weight: bold;
            color: #10b981;
        }
        
        .summary-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .position-section {
            margin-bottom: 40px;
            page-break-inside: avoid;
        }
        
        .position-header {
            background: #10b981;
            color: white;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
            margin-bottom: 0;
        }
        
        .position-title {
            font-size: 20px;
            font-weight: bold;
        }
        
        .position-votes {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
        }
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e2e8f0;
            border-radius: 0 0 8px 8px;
            overflow: hidden;
        }
        
        .results-table th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid #e2e8f0;
            color: #333;
        }
        
        .results-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #333;
        }
        
        .results-table tr:last-child td {
            border-bottom: none;
        }
        
        .results-table tr.winner {
            background: #c6f6d5;
            font-weight: bold;
        }
        
        .winner-badge {
            background: #10b981;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .rank {
            font-weight: bold;
            color: #10b981;
            font-size: 18px;
        }
        
        .percentage {
            color: #666;
            font-weight: 600;
        }
        
        .no-candidates {
            text-align: center;
            padding: 30px;
            color: #999;
            font-style: italic;
        }
        
        .footer {
            margin-top: 60px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .btn {
            padding: 10px 20px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        
        .btn:hover {
            background: #059669;
        }
        
        .btn-secondary {
            background: #6b7280;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                padding: 20px;
            }
            
            .position-section {
                page-break-inside: avoid;
            }
            
            @page {
                margin: 1.5cm;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn">Print / Save as PDF</button>
        <a href="view_results.php" class="btn btn-secondary">← Back</a>
    </div>
    
    <div class="header">
        <h1>ELECTION RESULTS</h1>
        <div class="subtitle"><?php echo htmlspecialchars($sessionName); ?></div>
    </div>
    
    <div class="meta-info">
        <div class="meta-item">
            <span class="meta-label">Status</span>
            <span class="meta-value">
                <span class="status-badge">
                    <?php echo strtoupper($session['status']); ?>
                </span>
            </span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Date & Time</span>
            <span class="meta-value"><?php echo date('F d, Y h:i A', strtotime($session['created_at'])); ?></span>
        </div>
    </div>
    
    <div class="summary">
        <h2>Summary</h2>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-number"><?php echo $totalVotes; ?></div>
                <div class="summary-label">Total Votes Cast</div>
            </div>
            <div class="summary-item">
                <div class="summary-number"><?php echo $uniqueVoters; ?></div>
                <div class="summary-label">Students Who Voted</div>
            </div>
            <div class="summary-item">
                <div class="summary-number"><?php echo $positions->num_rows; ?></div>
                <div class="summary-label">Positions</div>
            </div>
        </div>
    </div>
    
    <?php
    $positions->data_seek(0);
    while ($position = $positions->fetch_assoc()):
        $positionId = $position['id'];
        $positionName = $position['position_name'];
        
        // Get candidates and their votes - use snapshot data when candidate has been deleted
        $resultsQuery = "SELECT 
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
        
        // Get total votes for this position
        $posVotesQuery = "SELECT COUNT(*) as total FROM votes WHERE session_id = ? AND position_id = ?";
        $posStmt = $conn->prepare($posVotesQuery);
        $posStmt->bind_param("ii", $sessionId, $positionId);
        $posStmt->execute();
        $positionTotalVotes = $posStmt->get_result()->fetch_assoc()['total'];
        $posStmt->close();
    ?>
    
    <div class="position-section">
        <div class="position-header">
            <div class="position-title"><?php echo htmlspecialchars($positionName); ?></div>
            <div class="position-votes">Total Votes: <?php echo $positionTotalVotes; ?></div>
        </div>
        
        <table class="results-table">
            <thead>
                <tr>
                    <th width="8%">Rank</th>
                    <th width="35%">Candidate Name</th>
                    <th width="15%">Student ID</th>
                    <th width="12%">Votes</th>
                    <th width="15%">Percentage</th>
                    <th width="15%">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($results->num_rows > 0):
                    $rank = 1;
                    $prevVotes = -1;
                    $actualRank = 1;
                    
                    while ($result = $results->fetch_assoc()):
                        // Handle ties
                        if ($result['vote_count'] != $prevVotes) {
                            $actualRank = $rank;
                        }
                        
                        $isWinner = ($actualRank == 1 && $result['vote_count'] > 0);
                        $percentage = $positionTotalVotes > 0 ? ($result['vote_count'] / $positionTotalVotes * 100) : 0;
                ?>
                    <tr class="<?php echo $isWinner ? 'winner' : ''; ?>">
                        <td><span class="rank">#<?php echo $actualRank; ?></span></td>
                        <td><?php echo htmlspecialchars($result['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($result['student_id']); ?></td>
                        <td><strong><?php echo $result['vote_count']; ?></strong></td>
                        <td><span class="percentage"><?php echo number_format($percentage, 2); ?>%</span></td>
                        <td>
                            <?php if ($isWinner): ?>
                                <span class="winner-badge">WINNER</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php
                        $prevVotes = $result['vote_count'];
                        $rank++;
                    endwhile;
                else:
                ?>
                    <tr>
                        <td colspan="6" class="no-candidates">No candidates nominated for this position</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php
        $stmt->close();
    endwhile;
    $conn->close();
    ?>
    
    <div class="footer">
        <p><strong>Report Generated:</strong> <?php echo date('F d, Y h:i A'); ?></p>
        <p><strong>Generated By:</strong> <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
        <p style="margin-top: 10px; font-size: 11px;">Classroom Voting System - Official Election Results</p>
    </div>
</body>
</html>
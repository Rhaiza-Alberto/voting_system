<?php
require_once 'config.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Guide</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="modern-navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <div class="brand-icon">📖</div>
                <div class="brand-text">
                    <h1>Quick Guide</h1>
                    <p>How to use the system</p>
                </div>
            </div>
            <a href="admin_dashboard.php" class="btn-modern btn-secondary">← Back</a>
        </div>
    </nav>

    <div class="modern-container">
        <div class="grid-2">
            <!-- Getting Started -->
            <div class="modern-card">
                <div class="card-header">
                    <h2 class="card-title">🚀 Getting Started</h2>
                </div>
                <div class="card-body">
                    <ol style="line-height: 2; color: #374151;">
                        <li><strong>Add Students</strong> - Import or manually add students</li>
                        <li><strong>Create Groups</strong> - Organize students by class/section</li>
                        <li><strong>Setup Positions</strong> - Define election positions</li>
                        <li><strong>Create Session</strong> - Start a new election</li>
                    </ol>
                </div>
            </div>

            <!-- Running Elections -->
            <div class="modern-card">
                <div class="card-header">
                    <h2 class="card-title">🗳️ Running Elections</h2>
                </div>
                <div class="card-body">
                    <ol style="line-height: 2; color: #374151;">
                        <li><strong>Nominate Candidates</strong> - Add candidates per position</li>
                        <li><strong>Open Position</strong> - Open one position for voting</li>
                        <li><strong>Students Vote</strong> - They vote in real-time</li>
                        <li><strong>Close & Determine Winner</strong> - Finalize results</li>
                        <li><strong>Repeat</strong> - Continue with next position</li>
                        <li><strong>Lock Session</strong> - Complete the election</li>
                    </ol>
                </div>
            </div>

            <!-- Multiple Sessions -->
            <div class="modern-card">
                <div class="card-header">
                    <h2 class="card-title">📋 Multiple Sessions</h2>
                </div>
                <div class="card-body">
                    <ul style="line-height: 2; color: #374151; margin-left: 1.5rem;">
                        <li>Create different sessions for different classes</li>
                        <li>Each session can have its own student group</li>
                        <li>Only ONE session can be active at a time</li>
                        <li>Lock completed sessions to preserve data</li>
                        <li>View results from any past session</li>
                    </ul>
                </div>
            </div>

            <!-- Best Practices -->
            <div class="modern-card">
                <div class="card-header">
                    <h2 class="card-title">✨ Best Practices</h2>
                </div>
                <div class="card-body">
                    <ul style="line-height: 2; color: #374151; margin-left: 1.5rem;">
                        <li>Clean up old candidates before new sessions</li>
                        <li>Test with a small group first</li>
                        <li>Monitor voting progress in real-time</li>
                        <li>Export results for records</li>
                        <li>Review audit logs regularly</li>
                    </ul>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div class="modern-card">
                <div class="card-header">
                    <h2 class="card-title">🔧 Troubleshooting</h2>
                </div>
                <div class="card-body">
                    <div style="line-height: 2; color: #374151;">
                        <p><strong>Students can't vote:</strong></p>
                        <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                            <li>Check if they're in the correct group</li>
                            <li>Verify session is active</li>
                            <li>Ensure position is open</li>
                        </ul>
                        
                        <p><strong>Can't create session:</strong></p>
                        <ul style="margin-left: 1.5rem;">
                            <li>Lock or complete existing active session</li>
                            <li>Clean up old candidates if needed</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Support -->
            <div class="modern-card">
                <div class="card-header">
                    <h2 class="card-title">💬 Need Help?</h2>
                </div>
                <div class="card-body">
                    <p style="color: #374151; margin-bottom: 1rem;">
                        For additional support or questions:
                    </p>
                    <div style="background: #f9fafb; padding: 1rem; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <p style="font-weight: 600; color: #111827;">Contact System Administrator</p>
                        <p style="color: #6b7280; font-size: 0.875rem; margin-top: 0.5rem;">
                            Email: admin@school.edu
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
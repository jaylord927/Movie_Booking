<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

require_once $root_dir . '/partials/admin-header.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = '';
$success = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $suggestion_id = intval($_POST['suggestion_id']);
    $status = htmlspecialchars(trim($_POST['status']));
    $admin_notes = htmlspecialchars(trim($_POST['admin_notes'] ?? ''));
    
    $stmt = $conn->prepare("UPDATE suggestions SET status = ?, admin_notes = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status, $admin_notes, $suggestion_id);
    
    if ($stmt->execute()) {
        $success = "Suggestion status updated successfully!";
    } else {
        $error = "Failed to update suggestion: " . $conn->error;
    }
    $stmt->close();
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $stmt = $conn->prepare("DELETE FROM suggestions WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $success = "Suggestion deleted successfully!";
    } else {
        $error = "Failed to delete suggestion: " . $conn->error;
    }
    $stmt->close();
}

// Fetch all suggestions
$suggestions_result = $conn->query("
    SELECT s.*, u.u_name as registered_user_name, u.u_email as registered_user_email
    FROM suggestions s
    LEFT JOIN users u ON s.user_id = u.u_id
    ORDER BY 
        CASE s.status
            WHEN 'Pending' THEN 1
            WHEN 'Reviewed' THEN 2
            WHEN 'Implemented' THEN 3
            ELSE 4
        END,
        s.created_at DESC
");

$suggestions = [];
if ($suggestions_result) {
    while ($row = $suggestions_result->fetch_assoc()) {
        $suggestions[] = $row;
    }
}

// Get counts
$count_result = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Reviewed' THEN 1 ELSE 0 END) as reviewed,
        SUM(CASE WHEN status = 'Implemented' THEN 1 ELSE 0 END) as implemented
    FROM suggestions
");
$counts = $count_result ? $count_result->fetch_assoc() : ['total' => 0, 'pending' => 0, 'reviewed' => 0, 'implemented' => 0];

$conn->close();
?>

<div class="admin-content" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); border-radius: 20px; border: 2px solid rgba(52, 152, 219, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">Manage Suggestions</h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">Review and manage customer feedback and ideas</p>
    </div>

    <!-- Statistics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 25px; border-radius: 12px; text-align: center;">
            <div style="font-size: 2rem; color: white; margin-bottom: 10px;">
                <i class="fas fa-lightbulb"></i>
            </div>
            <div style="font-size: 2rem; font-weight: 800; color: white; margin-bottom: 5px;">
                <?php echo $counts['total']; ?>
            </div>
            <div style="color: rgba(255,255,255,0.9); font-size: 0.9rem;">Total Suggestions</div>
        </div>

        <div style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); padding: 25px; border-radius: 12px; text-align: center;">
            <div style="font-size: 2rem; color: white; margin-bottom: 10px;">
                <i class="fas fa-clock"></i>
            </div>
            <div style="font-size: 2rem; font-weight: 800; color: white; margin-bottom: 5px;">
                <?php echo $counts['pending']; ?>
            </div>
            <div style="color: rgba(255,255,255,0.9); font-size: 0.9rem;">Pending</div>
        </div>

        <div style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 25px; border-radius: 12px; text-align: center;">
            <div style="font-size: 2rem; color: white; margin-bottom: 10px;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div style="font-size: 2rem; font-weight: 800; color: white; margin-bottom: 5px;">
                <?php echo $counts['reviewed']; ?>
            </div>
            <div style="color: rgba(255,255,255,0.9); font-size: 0.9rem;">Reviewed</div>
        </div>

        <div style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); padding: 25px; border-radius: 12px; text-align: center;">
            <div style="font-size: 2rem; color: white; margin-bottom: 10px;">
                <i class="fas fa-star"></i>
            </div>
            <div style="font-size: 2rem; font-weight: 800; color: white; margin-bottom: 5px;">
                <?php echo $counts['implemented']; ?>
            </div>
            <div style="color: rgba(255,255,255,0.9); font-size: 0.9rem;">Implemented</div>
        </div>
    </div>

    <?php if ($error): ?>
        <div style="background: rgba(231, 76, 60, 0.2); color: #ff9999; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; text-align: center; border: 1px solid rgba(231, 76, 60, 0.3);">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; text-align: center; border: 1px solid rgba(46, 204, 113, 0.3);">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Suggestions List -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-lightbulb"></i> All Suggestions (<?php echo count($suggestions); ?>)
        </h2>
        
        <?php if (empty($suggestions)): ?>
        <div style="text-align: center; padding: 50px; color: rgba(255, 255, 255, 0.6);">
            <i class="fas fa-lightbulb fa-3x" style="margin-bottom: 20px; opacity: 0.5;"></i>
            <p style="font-size: 1.1rem;">No suggestions found.</p>
        </div>
        <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <?php foreach ($suggestions as $suggestion): 
                $status_color = '';
                $status_bg = '';
                
                switch($suggestion['status']) {
                    case 'Pending':
                        $status_color = '#f39c12';
                        $status_bg = 'rgba(243, 156, 18, 0.2)';
                        break;
                    case 'Reviewed':
                        $status_color = '#3498db';
                        $status_bg = 'rgba(52, 152, 219, 0.2)';
                        break;
                    case 'Implemented':
                        $status_color = '#2ecc71';
                        $status_bg = 'rgba(46, 204, 113, 0.2)';
                        break;
                }
            ?>
            <div style="background: rgba(255, 255, 255, 0.03); border-radius: 12px; padding: 25px; border: 1px solid rgba(52, 152, 219, 0.2);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; flex-wrap: wrap;">
                            <span style="background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; padding: 6px 15px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas <?php echo $suggestion['status'] == 'Pending' ? 'fa-clock' : ($suggestion['status'] == 'Reviewed' ? 'fa-check-circle' : 'fa-star'); ?>"></i>
                                <?php echo $suggestion['status']; ?>
                            </span>
                            <span style="color: rgba(255,255,255,0.6); font-size: 0.85rem;">
                                <i class="far fa-calendar"></i> <?php echo date('M d, Y h:i A', strtotime($suggestion['created_at'])); ?>
                            </span>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-user" style="color: #3498db;"></i>
                                <span style="color: white; font-weight: 600;">
                                    <?php 
                                    if ($suggestion['user_id']) {
                                        echo htmlspecialchars($suggestion['registered_user_name'] ?? 'Unknown');
                                    } else {
                                        echo htmlspecialchars($suggestion['user_name'] ?? 'Guest');
                                    }
                                    ?>
                                </span>
                            </div>
                            
                            <?php if ($suggestion['user_email'] || $suggestion['registered_user_email']): ?>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-envelope" style="color: #3498db;"></i>
                                <span style="color: var(--pale-red);">
                                    <?php echo htmlspecialchars($suggestion['registered_user_email'] ?? $suggestion['user_email']); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($suggestion['user_id']): ?>
                            <span style="background: rgba(52, 152, 219, 0.2); color: #3498db; padding: 3px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: 600;">
                                Registered User
                            </span>
                            <?php else: ?>
                            <span style="background: rgba(149, 165, 166, 0.2); color: #95a5a6; padding: 3px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: 600;">
                                Guest
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button onclick="openEditModal(<?php echo $suggestion['id']; ?>, '<?php echo addslashes($suggestion['suggestion']); ?>', '<?php echo $suggestion['status']; ?>', '<?php echo addslashes($suggestion['admin_notes'] ?? ''); ?>')" 
                                style="padding: 8px 16px; background: rgba(52, 152, 219, 0.2); color: #3498db; border: 1px solid rgba(52, 152, 219, 0.3); border-radius: 6px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                            <i class="fas fa-edit"></i> Update
                        </button>
                        <a href="?page=admin/manage-suggestions&delete=<?php echo $suggestion['id']; ?>" 
                           onclick="return confirm('Are you sure you want to delete this suggestion?')"
                           style="padding: 8px 16px; background: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid rgba(231, 76, 60, 0.3); border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
                
                <div style="background: rgba(0, 0, 0, 0.2); padding: 20px; border-radius: 10px; margin-bottom: 15px;">
                    <p style="color: white; font-size: 1rem; line-height: 1.6; margin-bottom: 10px; font-style: italic;">
                        "<?php echo nl2br(htmlspecialchars($suggestion['suggestion'])); ?>"
                    </p>
                </div>
                
                <?php if (!empty($suggestion['admin_notes'])): ?>
                <div style="background: rgba(52, 152, 219, 0.1); padding: 15px; border-radius: 8px; border-left: 4px solid #3498db;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                        <i class="fas fa-sticky-note" style="color: #3498db;"></i>
                        <span style="color: white; font-weight: 600;">Admin Notes</span>
                    </div>
                    <p style="color: rgba(255,255,255,0.9); font-size: 0.95rem; line-height: 1.5;">
                        <?php echo nl2br(htmlspecialchars($suggestion['admin_notes'])); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; padding: 20px;">
    <div style="background: #2c3e50; border-radius: 15px; padding: 30px; max-width: 600px; width: 100%; border: 1px solid rgba(52, 152, 219, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(52, 152, 219, 0.3);">
            <h3 style="color: #3498db; font-size: 1.3rem;">Update Suggestion</h3>
            <button onclick="closeEditModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        
        <form method="POST" action="" id="editForm">
            <input type="hidden" name="suggestion_id" id="editSuggestionId">
            <input type="hidden" name="update_status" value="1">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Suggestion</label>
                <div id="editSuggestionText" style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; color: rgba(255,255,255,0.9); font-style: italic; border: 1px solid rgba(255,255,255,0.1);"></div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Status</label>
                <select name="status" id="editStatus" required style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 8px; color: white; font-size: 1rem;">
                    <option value="Pending" style="background: #2c3e50; color: white;">Pending</option>
                    <option value="Reviewed" style="background: #2c3e50; color: white;">Reviewed</option>
                    <option value="Implemented" style="background: #2c3e50; color: white;">Implemented</option>
                </select>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Admin Notes</label>
                <textarea name="admin_notes" id="editAdminNotes" rows="4" style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 8px; color: white; font-size: 1rem; resize: vertical;" placeholder="Add notes about this suggestion..."></textarea>
            </div>
            
            <div style="text-align: center; margin-top: 25px;">
                <button type="submit" style="padding: 12px 30px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fas fa-save"></i> Update Status
                </button>
                <button type="button" onclick="closeEditModal()" style="padding: 12px 30px; background: rgba(255, 255, 255, 0.1); color: white; border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; margin-left: 10px;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    input:focus, select:focus, textarea:focus {
        outline: none;
        background: rgba(255, 255, 255, 0.12);
        border-color: #3498db;
        box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2);
    }
    
    button:hover {
        transform: translateY(-2px);
        opacity: 0.9;
    }
    
    tr:hover {
        background: rgba(255, 255, 255, 0.03) !important;
    }
    
    :root {
        --admin-primary: #2c3e50;
        --admin-secondary: #34495e;
        --admin-accent: #3498db;
        --admin-success: #2ecc71;
        --admin-danger: #e74c3c;
        --admin-warning: #f39c12;
        --admin-light: #ecf0f1;
        --admin-dark: #1a252f;
    }
    
    @media (max-width: 768px) {
        .admin-content {
            padding: 15px;
        }
    }
</style>

<script>
function openEditModal(id, suggestion, status, adminNotes) {
    document.getElementById('editSuggestionId').value = id;
    document.getElementById('editSuggestionText').innerHTML = '"' + suggestion + '"';
    document.getElementById('editStatus').value = status;
    document.getElementById('editAdminNotes').value = adminNotes;
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target == modal) {
        closeEditModal();
    }
}

// Auto-dismiss alerts
setTimeout(() => {
    const alerts = document.querySelectorAll('[style*="background: rgba(231, 76, 60, 0.2)"], [style*="background: rgba(46, 204, 113, 0.2)"]');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 500);
    });
}, 5000);
</script>

</div>
</body>
</html> 
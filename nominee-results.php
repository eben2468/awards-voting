<?php
/**
 * Nominee Results Portal
 * Awards Voting System
 */

require_once 'config/database.php';

$page_title = 'Nominee Results Portal';
$pdo = getDBConnection();

$accessCode = sanitize($_POST['access_code'] ?? $_GET['code'] ?? '');
$category = null;
$error = '';

if (!empty($accessCode)) {
    $stmt = $pdo->prepare("
        SELECT c.*, e.title as event_title, e.id as event_id, e.status as event_status
        FROM categories c
        JOIN events e ON c.event_id = e.id
        WHERE c.access_code = ?
    ");
    $stmt->execute([$accessCode]);
    $category = $stmt->fetch();

    if (!$category) {
        $error = 'Invalid access code. Please check and try again.';
    } else if (!$category['allow_results']) {
        $error = 'Results for this category are not yet available. Please contact the administrator.';
        $category = null;
    } else {
        // Fetch all nominees in this category to show standings
        $standingsStmt = $pdo->prepare("
            SELECT name, vote_count, photo, shortcode
            FROM nominees
            WHERE category_id = ? AND status != 'disqualified'
            ORDER BY vote_count DESC
        ");
        $standingsStmt->execute([$category['id']]);
        $standings = $standingsStmt->fetchAll();
        
        $totalVotes = array_sum(array_column($standings, 'vote_count'));
    }
}

include 'includes/header.php';
?>

<div class="nominee-portal-wrapper">
    <!-- Hero Section -->
    <section class="portal-hero py-5">
        <div class="container-fluid px-4 px-md-5">
            <div class="row align-items-center">
                <div class="col-lg-<?php echo $category ? '8' : '12'; ?> text-<?php echo $category ? 'start' : 'center'; ?>">
                    <h1 class="display-4 fw-bold text-white mb-2">Results Portal</h1>
                    <p class="lead text-white-50 mb-0">Live Performance Dashboard & Category Insights</p>
                </div>
                <?php if ($category): ?>
                <div class="col-lg-4 text-end d-none d-lg-block">
                    <div class="glass-badge py-2 px-4 rounded-pill d-inline-block">
                        <i class="bi bi-clock-history me-2"></i>Updated Real-time
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="portal-body py-5 bg-light">
        <div class="container-fluid px-4 px-md-5">
            <?php if (!$category): ?>
                <div class="row justify-content-center">
                    <div class="col-lg-5">
                        <div class="login-card card border-0 shadow-lg overflow-hidden rounded-4">
                            <div class="card-body p-5">
                                <div class="text-center mb-4">
                                    <div class="icon-shape bg-primary bg-opacity-10 text-primary rounded-circle mb-3 mx-auto">
                                        <i class="bi bi-shield-lock fs-2"></i>
                                    </div>
                                    <h3 class="fw-bold">Welcome Back</h3>
                                    <p class="text-muted">Enter category access code below</p>
                                </div>

                                <?php if ($error): ?>
                                    <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-center">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        <?php echo $error; ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="nominee-results">
                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">Category Access Code</label>
                                        <div class="input-group input-group-lg">
                                            <span class="input-group-text bg-light border-0"><i class="bi bi-key"></i></span>
                                            <input type="text" name="access_code" class="form-control bg-light border-0" placeholder="e.g. CAT-A1B2C3D4" required>
                                        </div>
                                        <small class="text-muted mt-2 d-block text-center">Contact your event administrator or organizer for this code.</small>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-lg w-100 py-3 shadow-sm btn-modern">
                                        Access Dashboard <i class="bi bi-arrow-right ms-2"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <!-- Sidebar: Category Info & Quick Stats -->
                    <div class="col-xl-4">
                        <div class="row g-4 sticky-top" style="top: 100px;">
                            <div class="col-12">
                                <div class="profile-card card border-0 shadow-sm rounded-4 overflow-hidden">
                                    <div class="profile-header p-4 text-center text-white">
                                        <div class="avatar-wrapper mb-3 position-relative d-inline-block">
                                            <div class="bg-white rounded-circle border border-4 border-white shadow-lg d-flex align-items-center justify-content-center" 
                                                 style="width: 140px; height: 140px;">
                                                <i class="bi bi-collection fs-1 text-primary"></i>
                                            </div>
                                        </div>
                                        <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($category['name']); ?></h3>
                                        <p class="text-white-50 mb-0"><?php echo htmlspecialchars($category['event_title']); ?></p>
                                        <div class="mt-3">
                                            <span class="badge bg-white bg-opacity-20 rounded-pill px-3">
                                                <i class="bi bi-calendar3 me-2"></i>Live Tracking
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-6">
                                                <div class="stat-widget p-3 rounded-4 bg-light border-0">
                                                    <small class="text-muted d-block mb-1 text-uppercase fw-bold" style="font-size: 0.7rem;">Total Category Votes</small>
                                                    <h4 class="mb-0 fw-bold text-primary"><?php echo number_format($totalVotes); ?></h4>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="stat-widget p-3 rounded-4 bg-light border-0">
                                                    <small class="text-muted d-block mb-1 text-uppercase fw-bold" style="font-size: 0.7rem;">Total Nominees</small>
                                                    <h4 class="mb-0 fw-bold text-primary"><?php echo count($standings); ?></h4>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($standings)): ?>
                                        <div class="mt-4 pt-3 border-top">
                                            <h6 class="fw-bold mb-3">Current Leader</h6>
                                            <div class="d-flex align-items-center p-3 rounded-4 bg-primary bg-opacity-10 border border-primary border-opacity-10">
                                                <img src="<?php echo $standings[0]['photo'] ? 'uploads/nominees/' . $standings[0]['photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($standings[0]['name']) . '&size=45&background=6366f1&color=fff'; ?>" 
                                                     class="rounded-circle me-3" style="width: 45px; height: 45px; object-fit: cover;">
                                                <div>
                                                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($standings[0]['name']); ?></h6>
                                                    <small class="text-primary fw-bold"><?php echo number_format($standings[0]['vote_count']); ?> Votes</small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer bg-white p-4 border-0 text-center">
                                        <a href="nominee-results.php" class="btn btn-outline-secondary w-100 rounded-pill">
                                            <i class="bi bi-box-arrow-left me-2"></i>Logout Dashboard
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content: Category Standings -->
                    <div class="col-xl-8">
                        <div class="standings-card card border-0 shadow-sm rounded-4 overflow-hidden">
                            <div class="card-header bg-white p-4 border-bottom-0 d-flex justify-content-between align-items-center">
                                <h4 class="fw-bold mb-0"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Full Category Standings</h4>
                                <div class="badge bg-light text-muted p-2 rounded-3 border">
                                    <i class="bi bi-people me-2"></i><?php echo count($standings); ?> Total Nominees
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light text-muted" style="font-size: 0.85rem;">
                                            <tr>
                                                <th class="ps-4 border-0">RANK</th>
                                                <th class="border-0">NOMINEE</th>
                                                <th class="border-0">SHORTCODE</th>
                                                <th class="border-0">VOTES</th>
                                                <th class="border-0 pe-4">SHARE</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($standings as $index => $s): ?>
                                                <tr>
                                                    <td class="ps-4 border-0">
                                                        <?php if ($index == 0): ?>
                                                            <span class="standing-rank rank-1">1st</span>
                                                        <?php elseif ($index == 1): ?>
                                                            <span class="standing-rank rank-2">2nd</span>
                                                        <?php elseif ($index == 2): ?>
                                                            <span class="standing-rank rank-3">3rd</span>
                                                        <?php else: ?>
                                                            <span class="standing-rank text-muted"><?php echo $index + 1; ?>th</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="border-0">
                                                        <div class="d-flex align-items-center">
                                                            <div class="nominee-avatar me-3">
                                                                <img src="<?php echo $s['photo'] ? 'uploads/nominees/' . $s['photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($s['name']) . '&size=45&background=f1f1f1&color=333'; ?>" 
                                                                     class="rounded-circle" style="width: 45px; height: 45px; object-fit: cover;">
                                                            </div>
                                                            <div>
                                                                <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($s['name']); ?></h6>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="border-0">
                                                        <code class="text-primary bg-primary bg-opacity-10 px-2 py-1 rounded"><?php echo $s['shortcode']; ?></code>
                                                    </td>
                                                    <td class="border-0">
                                                        <span class="fw-bold"><?php echo number_format($s['vote_count']); ?></span>
                                                    </td>
                                                    <td class="pe-4 border-0" style="width: 150px;">
                                                        <?php $share = $totalVotes > 0 ? round(($s['vote_count'] / $totalVotes) * 100, 1) : 0; ?>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                                <div class="progress-bar bg-primary" style="width: <?php echo $share; ?>%"></div>
                                                            </div>
                                                            <span class="small fw-bold"><?php echo $share; ?>%</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<style>
:root {
    --portal-primary: #6366f1;
    --portal-secondary: #a855f7;
    --portal-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
}

.portal-hero {
    background: var(--portal-gradient);
    margin-top: 76px;
    position: relative;
    overflow: hidden;
}

.portal-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 500px;
    height: 500px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    z-index: 0;
}

.glass-badge {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    font-weight: 500;
}

.icon-shape {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-header {
    background: var(--portal-gradient);
}

.rank-badge {
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.stat-widget {
    transition: transform 0.3s ease;
}

.stat-widget:hover {
    transform: translateY(-5px);
}

.standing-rank {
    padding: 2px 10px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.9rem;
}

.rank-1 { background: #fef3c7; color: #92400e; }
.rank-2 { background: #f3f4f6; color: #374151; }
.rank-3 { background: #ffedd5; color: #9a3412; }

.nominee-avatar {
    position: relative;
}

.online-indicator {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 12px;
    height: 12px;
    background: #10b981;
    border: 2px solid white;
    border-radius: 50%;
}

.btn-modern {
    border-radius: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(99, 102, 241, 0.3);
}

.login-card {
    animation: slideUp 0.6s ease-out;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.profile-card, .standings-card {
    transition: all 0.3s ease;
}

.profile-card:hover, .standings-card:hover {
    box-shadow: 0 15px 30px rgba(0,0,0,0.08) !important;
}

/* Custom Table Polish */
.table > :not(caption) > * > * {
    padding: 1.2rem 0.75rem;
}

code {
    font-size: 0.9em;
}
</style>

<?php include 'includes/footer.php'; ?>

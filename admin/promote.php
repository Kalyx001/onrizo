<?php
session_start();
include '../db_config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$admin_id = (int)($_SESSION['admin_id'] ?? 0);

// fetch products for dropdown
$stmt = $conn->prepare("SELECT id, name, price FROM products WHERE admin_id = ? ORDER BY id DESC");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$res = $stmt->get_result();
$products = [];
if ($res) while($r = $res->fetch_assoc()) $products[] = $r;

// csrf token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf_token'];
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Promote Product - Onrizo Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{font-family:Arial,Helvetica,sans-serif;background:#f7f8fb;padding:18px}
.card{border-radius:10px}
.promote-hero{background:linear-gradient(90deg,#667eea,#764ba2); color:white; padding:18px; border-radius:8px}
</style>
</head>
<body>
  <div id="pageLoader" class="active">
    <div>
      <div class="spinner"></div>
      <div class="loader-text">Loading...</div>
    </div>
  </div>
<div class="container" style="max-width:900px">
    <div class="promote-hero mb-3">
        <h3>Promote Your Product</h3>
        <p style="opacity:0.9;">Create a campaign to showcase your product to more buyers. Choose budget, duration and a short message.</p>
    </div>

    <div class="card p-3 mb-3">
        <!-- Plan cards: choose a ready-made plan -->
        <div class="plans" id="plans">
            <div class="plan-card" data-budget="500" data-duration="3" data-title="Bronze Boost" data-plan="bronze">
                <div class="plan-title">Bronze Boost</div>
                <div class="plan-price">KES 500</div>
                <div class="plan-duration">3 days</div>
                <div class="plan-features">Basic visibility • Small reach</div>
            </div>
            <div class="plan-card" data-budget="1500" data-duration="7" data-title="Silver Surge" data-plan="silver">
                <div class="plan-title">Silver Surge</div>
                <div class="plan-price">KES 1,500</div>
                <div class="plan-duration">7 days</div>
                <div class="plan-features">Better reach • Featured placement</div>
            </div>
            <div class="plan-card" data-budget="4000" data-duration="14" data-title="Gold Spotlight" data-plan="gold">
                <div class="plan-title">Gold Spotlight</div>
                <div class="plan-price">KES 4,000</div>
                <div class="plan-duration">14 days</div>
                <div class="plan-features">High priority • Prominent display</div>
            </div>
        </div>

        <!-- Modals for each plan -->
        <div id="modalsContainer">
            <!-- Bronze Modal -->
            <div class="modal fade" id="modal-bronze" tabindex="-1" aria-hidden="true" style="display:none;">
                <div class="modal-dialog">
                    <div class="modal-content p-3">
                        <h5>Bronze Boost — KES 500 (3 days)</h5>
                        <p>Pay KES 500 to boost your product for 3 days.</p>
                        <div style="display:flex; gap:8px;">
                            <button class="btn btn-primary" onclick="initiateStk('bronze')">Pay with M-Pesa</button>
                            <button class="btn btn-secondary" onclick="closeModal('bronze')">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Silver Modal -->
            <div class="modal fade" id="modal-silver" tabindex="-1" aria-hidden="true" style="display:none;">
                <div class="modal-dialog">
                    <div class="modal-content p-3">
                        <h5>Silver Surge — KES 1,500 (7 days)</h5>
                        <p>Pay KES 1,500 to boost your product for 7 days with featured placement.</p>
                        <div style="display:flex; gap:8px;">
                            <button class="btn btn-primary" onclick="initiateStk('silver')">Pay with M-Pesa</button>
                            <button class="btn btn-secondary" onclick="closeModal('silver')">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Gold Modal -->
            <div class="modal fade" id="modal-gold" tabindex="-1" aria-hidden="true" style="display:none;">
                <div class="modal-dialog">
                    <div class="modal-content p-3">
                        <h5>Gold Spotlight — KES 4,000 (14 days)</h5>
                        <p>Pay KES 4,000 to boost your product for 14 days with top priority.</p>
                        <div style="display:flex; gap:8px;">
                            <button class="btn btn-primary" onclick="initiateStk('gold')">Pay with M-Pesa</button>
                            <button class="btn btn-secondary" onclick="closeModal('gold')">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form id="promoteForm">
            <div class="mb-3">
                <label class="form-label"><strong>1. Select Product</strong></label>
                <select id="productId" class="form-select" required>
                    <option value="">-- Choose a product --</option>
                    <?php foreach($products as $p): ?>
                        <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>"><?= htmlspecialchars($p['name']) ?> — KES <?= number_format($p['price'],0) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Budget will be calculated as 4% of product price for 7-day promotion.</small>
            </div>

            <div class="mb-3">
                <label class="form-label"><strong>2. Your M-Pesa Phone</strong></label>
                <input id="adminPhone" type="text" class="form-control" placeholder="Enter phone for M-Pesa STK push (07XXXXXXXX)" pattern="0[0-9]{9}" required />
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Budget (KES) — Auto-calculated</label>
                    <input id="budget" type="number" class="form-control" value="0" min="100" step="100" readonly />
                    <small class="text-muted">4% of product price × 7 days</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Duration (days)</label>
                    <input id="duration" type="number" class="form-control" value="7" min="1" max="30">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Campaign Title</label>
                <input id="title" type="text" class="form-control" placeholder="Short title (e.g., 'Weekend Boost')">
            </div>
            <div class="mb-3">
                <label class="form-label">Message (optional)</label>
                <textarea id="message" class="form-control" rows="3" placeholder="A short message for the campaign"></textarea>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="btn btn-primary" onclick="submitPromotion()">Create Campaign</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card p-3">
        <h5>Tips for Effective Promotions</h5>
        <ul>
            <li>Use a clear title and short message.</li>
            <li>Choose a budget that matches the expected reach—higher budgets reach more shoppers.</li>
            <li>Short campaigns (3-7 days) often perform well for flash sales.</li>
        </ul>
    </div>
</div>

<script>
const csrfToken = '<?= $csrf ?>';

// Auto-calculate budget when product is selected
function updateBudget() {
    const productSelect = document.getElementById('productId');
    const budgetInput = document.getElementById('budget');
    const durationInput = document.getElementById('duration');
    
    const selectedOption = productSelect.options[productSelect.selectedIndex];
    const productPrice = parseFloat(selectedOption.getAttribute('data-price') || 0);
    const duration = parseInt(durationInput.value || 7, 10);
    
    if (productPrice > 0) {
        // Budget = 4% of product price (base rate)
        const baseBudget = productPrice * 0.04;
        // Scale by duration (rough scaling: if 7 days = 4%, then 3 days ≈ 2%, 14 days ≈ 8%)
        const scaledBudget = Math.round((baseBudget / 7) * duration);
        budgetInput.value = Math.max(100, scaledBudget); // minimum 100 KES
    } else {
        budgetInput.value = 0;
    }
}

// Listeners for product and duration changes
document.addEventListener('DOMContentLoaded', function(){
    const productSelect = document.getElementById('productId');
    const durationInput = document.getElementById('duration');
    
    productSelect.addEventListener('change', updateBudget);
    durationInput.addEventListener('change', updateBudget);
    
    // Plan card selection
    const plans = document.querySelectorAll('.plan-card');
    plans.forEach(p => p.addEventListener('click', function(){
        plans.forEach(x=>x.classList.remove('selected'));
        p.classList.add('selected');
        // fill form but respect product selection first
        const productId = document.getElementById('productId').value;
        if (!productId) {
            alert('⚠️ Please select a product first!');
            p.classList.remove('selected');
            return;
        }
        document.getElementById('budget').value = p.getAttribute('data-budget');
        document.getElementById('duration').value = p.getAttribute('data-duration');
        // scroll to form
        document.getElementById('budget').scrollIntoView({behavior:'smooth', block:'center'});
    }));

    // double-click a plan to open modal
    document.querySelectorAll('.plan-card').forEach(pc => {
        pc.addEventListener('dblclick', function(){
            const plan = pc.getAttribute('data-plan');
            openModal(plan);
        });
    });
});

function openModal(plan){
    const id = 'modal-' + plan;
    const m = document.getElementById(id);
    if(m) m.style.display = 'block';
}
function closeModal(plan){
    const id = 'modal-' + plan;
    const m = document.getElementById(id);
    if(m) m.style.display = 'none';
}

async function submitPromotion(){
    const productId = document.getElementById('productId').value;
    const adminPhone = document.getElementById('adminPhone').value.trim();
    const budget = parseFloat(document.getElementById('budget').value || 0);
    const duration = parseInt(document.getElementById('duration').value || 0, 10);
    const title = document.getElementById('title').value.trim();
    
    // Enforce sequence: product → phone → budget
    if(!productId){ alert('❌ Please select a product first'); return; }
    if(!adminPhone){ alert('❌ Please enter your M-Pesa phone number'); return; }
    if(budget < 100){ alert('❌ Budget must be at least 100 KES'); return; }
    if(duration < 1){ alert('❌ Duration must be at least 1 day'); return; }
    
    try{
        const res = await fetch('save_promotion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ product_id: productId, budget: budget, duration: duration, title: title })
        });
        const data = await res.json();
        if(data.success){ alert('✅ Promotion created'); location.href='promote.php'; }
        else alert('❌ Error: ' + (data.message||'failed'));
    } catch(e){ console.error(e); alert('Network error'); }
}

// STK push triggered for a selected plan modal
async function initiateStk(planKey){
    const productId = document.getElementById('productId').value;
    const phoneVal = document.getElementById('adminPhone').value.trim();
    
    // Enforce sequence
    if(!productId){ alert('❌ Select a product to promote first'); return; }
    if(!phoneVal){ alert('❌ Enter your M-Pesa phone number'); return; }
    
    // map plan to values
    const planMap = {
        'bronze': { amount: 500, duration: 3, title: 'Bronze Boost' },
        'silver': { amount: 1500, duration: 7, title: 'Silver Surge' },
        'gold': { amount: 4000, duration: 14, title: 'Gold Spotlight' }
    };
    const plan = planMap[planKey];
    if(!plan){ alert('Unknown plan'); return; }

    // ask for confirmation before sending STK
    if(!confirm(`Proceed to pay KES ${plan.amount} via M-Pesa for ${plan.title}?`)) return;

    // Prepare form data for stk_push.php
    const fd = new FormData();
    fd.append('phone', phoneVal);
    fd.append('amount', plan.amount);
    fd.append('promotion', '1');
    fd.append('product_id', productId);
    fd.append('title', plan.title);
    fd.append('duration', plan.duration);

    try{
        // send to stk_push.php which will trigger STK push and (on success) create the promotion record
        const res = await fetch('../stk_push.php', { method: 'POST', body: fd });
        const text = await res.text();
        // very simple success check: look for the success string in HTML response
        if(text.indexOf('Payment request sent successfully') !== -1 || text.indexOf('✅') !== -1){
            alert('✅ STK Push sent.\n\nCheck your phone to complete payment.\n\nPromotion will be activated after payment confirmation.');
            closeModal(planKey);
            return;
        }
        alert('❌ Failed to send payment request. See console for details.');
        console.log(text);
    } catch(e){ console.error(e); alert('Network error'); }
}
</script>
  <script src="../loader.js"></script>
</body>
</html>

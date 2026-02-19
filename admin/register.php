<?php
// register.php - multi-step registration frontend for admin users
// Server-side actions are handled in admin/register_step.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register Admin - Orizo Shop</title>
  <link rel="stylesheet" href="admin_style.css">
  <style>
    .login-body { font-family: Arial, sans-serif; }
    .login-container { max-width:420px; margin:40px auto; padding:20px; background:#fff; border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,0.08); }
    .login-container h2 { margin:0 0 12px }
    .login-container input, .login-container select { width:100%; padding:10px; margin:8px 0; border-radius:6px; border:1px solid #ccc }
    .login-container button { padding:10px 14px; border:none; background:#4a47d5; color:#fff; border-radius:6px; cursor:pointer }
    .error{ color:#b00020; padding:8px 0 }
    .success{ color:#0a7a07; padding:8px 0 }
    .location-group{ margin-top:10px }
  </style>
</head>
<body class="login-body">
  <div id="pageLoader" class="active">
    <div>
      <div class="spinner"></div>
      <div class="loader-text">Loading...</div>
    </div>
  </div>
  <div class="login-container">
    <h2>🧑‍💼 Create Profile</h2>

    <div id="messages"></div>

    <!-- Step 1 -->
    <div id="step1" class="reg-step">
      <h3>Step 1 — Basic Info</h3>
      <input type="text" id="name" placeholder="Full Name" required>
      <input type="email" id="email" placeholder="Email Address" required>
      <button id="btnStep1">Next</button>
    </div>

    <!-- Step 2 -->
    <div id="step2" class="reg-step" style="display:none;">
      <h3>Step 2 — Phone</h3>
      <input type="text" id="phone" placeholder="Phone (07..., +254..., 254...)" required>
      <button id="btnSendCode">Send Verification Code</button>
    </div>

    <!-- Step 2b -->
    <div id="step2b" class="reg-step" style="display:none;">
      <h3>Enter verification code</h3>
      <p>We sent a 6-digit code to your phone and email. Check the file admin/sms_log.txt if running locally.</p>
      <input type="text" id="code" placeholder="123456" required>
      <button id="btnVerify">Verify Code</button>
    </div>

    <!-- Step 3 -->
    <div id="step3" class="reg-step" style="display:none;">
      <h3>Step 3 — Location</h3>
      <div class="location-group">This will assist getting customers from your area
        <label>County:</label>
        <select id="county" name="county">
          <option value="">Select County</option>
        </select>

        <label>Sub-County:</label>
        <select id="subcounty" name="subcounty">
          <option value="">Select Sub-County</option>
        </select>
      </div>
      <button id="btnStep3">Next</button>
    </div>

    <!-- Step 4 -->
    <div id="step4" class="reg-step" style="display:none;">
      <h3>Step 4 — Choose Credentials</h3>
      <input type="text" id="username" placeholder="Choose a Username" required>
      <input type="password" id="password" placeholder="Password" required>
      <input type="password" id="confirm_password" placeholder="Confirm Password" required>
      <button id="btnFinalize">Create Account</button>
    </div>

    <div id="done" style="display:none;">
      <h3>Registration complete</h3>
      <p id="doneMsg"></p>
      <a href="login.php">Go to login</a>
    </div>

    <p style="margin-top:10px;">Already have an account? <a href="login.php">Login here</a></p>
  </div>

  <script>
  document.addEventListener("DOMContentLoaded", () => {
    let locationsData = [];
    const countySelects = document.querySelectorAll('#county');

    fetch('kenya_locations.json')
      .then(res => res.json())
      .then(data => {
        locationsData = data;
        countySelects.forEach(cs => {
          data.forEach(item => {
            const opt = document.createElement('option'); opt.value = item.name; opt.textContent = item.name; cs.appendChild(opt);
          });
          cs.addEventListener('change', (e) => {
            const parent = e.target.closest('.reg-step');
            const sub = parent.querySelector('#subcounty');
            sub.innerHTML = '<option value="">Select Sub-County</option>';
            const selected = data.find(c => c.name === cs.value);
            if (selected) { selected.sub_counties.forEach(sv => { const o = document.createElement('option'); o.value = sv; o.textContent = sv; sub.appendChild(o); }); }
          });
        });
      }).catch(err => console.error('Error loading Kenya data:', err));

    function showMessage(msg, isError){
      const m = document.getElementById('messages');
      m.innerHTML = '<p class="' + (isError? 'error':'success') + '">' + msg + '</p>';
    }

    document.getElementById('btnStep1').addEventListener('click', () => {
      const name = document.getElementById('name').value.trim();
      const email = document.getElementById('email').value.trim();
      fetch('register_step.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({action:'step1', name, email})})
        .then(r=>r.json()).then(j=>{
          if (j.success) { document.getElementById('step1').style.display='none'; document.getElementById('step2').style.display='block'; showMessage('Step 1 saved'); }
          else showMessage(j.message || 'Error', true);
        });
    });

    document.getElementById('btnSendCode').addEventListener('click', () => {
      const phone = document.getElementById('phone').value.trim();
      fetch('register_step.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({action:'send_code', phone})})
        .then(r=>r.json()).then(j=>{
          if (j.success) { document.getElementById('step2').style.display='none'; document.getElementById('step2b').style.display='block'; showMessage(j.message); }
          else showMessage(j.message || 'Error', true);
        });
    });

    document.getElementById('btnVerify').addEventListener('click', () => {
      const code = document.getElementById('code').value.trim();
      fetch('register_step.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({action:'verify_code', code})})
        .then(r=>r.json()).then(j=>{
          if (j.success) { document.getElementById('step2b').style.display='none'; document.getElementById('step3').style.display='block'; showMessage('Phone verified'); }
          else showMessage(j.message || 'Error', true);
        });
    });

    document.getElementById('btnStep3').addEventListener('click', () => {
      const county = document.querySelector('#step3 #county').value;
      const subcounty = document.querySelector('#step3 #subcounty').value;
      fetch('register_step.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({action:'step3', county, subcounty})})
        .then(r=>r.json()).then(j=>{
          if (j.success) { document.getElementById('step3').style.display='none'; document.getElementById('step4').style.display='block'; showMessage('Location saved'); }
          else showMessage(j.message || 'Error', true);
        });
    });

    document.getElementById('btnFinalize').addEventListener('click', () => {
      const username = document.getElementById('username').value.trim();
      const password = document.getElementById('password').value;
      const confirm = document.getElementById('confirm_password').value;
      if (password !== confirm) { showMessage('Passwords do not match', true); return; }
      fetch('register_step.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({action:'finalize', username, password})})
        .then(r=>r.json()).then(j=>{
          if (j.success) { document.getElementById('step4').style.display='none'; document.getElementById('done').style.display='block'; document.getElementById('doneMsg').textContent = j.message || 'Done'; showMessage(''); }
          else showMessage(j.message || 'Error', true);
        });
    });

  });
  </script>
  <script src="../loader.js"></script>
</body>
</html>

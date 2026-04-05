<?php
require_once 'config.php';
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>LocalLink – Login & Register</title>
  <link rel="stylesheet" href="style.css"/>
  <style>body{padding-top:0!important;}</style>
</head>
<body>
<div class="auth-wrapper">
  <div style="position:absolute;top:60px;left:30px;width:80px;height:80px;border-radius:50%;background:rgba(255,200,55,0.2);z-index:0;"></div>
  <div style="position:absolute;bottom:80px;right:40px;width:60px;height:60px;border-radius:50%;background:rgba(142,84,233,0.2);z-index:0;"></div>

  <div class="auth-card">
    <div class="auth-header">
      <div class="auth-logo">Local<span>Link</span></div>
      <p class="auth-tagline">🛒 Your neighbourhood, one tap away</p>
    </div>
    <div class="auth-body">
      <div id="auth-alert" style="display:none;"></div>
      <div class="auth-tabs">
        <div class="auth-tab active" onclick="switchTab('login')"  id="tab-login">Sign In</div>
        <div class="auth-tab"        onclick="switchTab('register')" id="tab-register">Register</div>
      </div>

      <!-- LOGIN -->
      <form class="auth-form active" id="form-login" onsubmit="handleLogin(event)">
        <div class="form-group-ll">
          <label class="form-label-ll">Email Address</label>
          <div class="input-group-ll"><span class="input-icon">📧</span>
            <input class="form-control-ll" type="email" id="login-email" placeholder="you@example.com" required autocomplete="email"/>
          </div>
        </div>
        <div class="form-group-ll">
          <label class="form-label-ll">Password</label>
          <div class="input-group-ll"><span class="input-icon">🔒</span>
            <input class="form-control-ll" type="password" id="login-password" placeholder="Enter your password" required/>
            <button type="button" class="input-toggle" onclick="togglePwd('login-password',this)">👁</button>
          </div>
        </div>
        <div style="text-align:right;margin-bottom:20px;">
          <a href="#" style="font-size:0.82rem;color:var(--primary);font-weight:600;">Forgot Password?</a>
        </div>
        <button type="submit" class="btn-ll btn-primary-ll btn-full" id="login-btn"><span>🚀</span> Sign In</button>
        <div class="divider-text">or</div>
        <button type="button" class="btn-ll btn-outline-ll btn-full" onclick="switchTab('register')">Create a new account</button>
      </form>

      <!-- REGISTER -->
      <form class="auth-form" id="form-register" onsubmit="handleRegister(event)">
        <div class="form-group-ll">
          <label class="form-label-ll">Full Name</label>
          <div class="input-group-ll"><span class="input-icon">👤</span>
            <input class="form-control-ll" type="text" id="reg-name" placeholder="Your full name" required/>
          </div>
        </div>
        <div class="form-group-ll">
          <label class="form-label-ll">Email Address</label>
          <div class="input-group-ll"><span class="input-icon">📧</span>
            <input class="form-control-ll" type="email" id="reg-email" placeholder="you@example.com" required/>
          </div>
        </div>
        <div class="form-group-ll">
          <label class="form-label-ll">Phone Number</label>
          <div class="input-group-ll"><span class="input-icon">📱</span>
            <input class="form-control-ll" type="tel" id="reg-phone" placeholder="10-digit mobile" maxlength="10"/>
          </div>
        </div>
        <div class="form-group-ll">
          <label class="form-label-ll">Password</label>
          <div class="input-group-ll"><span class="input-icon">🔒</span>
            <input class="form-control-ll" type="password" id="reg-password" placeholder="Min 6 characters" required minlength="6"/>
            <button type="button" class="input-toggle" onclick="togglePwd('reg-password',this)">👁</button>
          </div>
        </div>
        <div class="form-group-ll">
          <label class="form-label-ll">I want to join as</label>
          <div class="role-selector">
            <div class="role-option">
              <input type="radio" name="role" id="role-customer" value="customer" checked/>
              <label class="role-label" for="role-customer">
                <span class="role-icon">🛒</span><span class="role-name">Customer</span>
                <span class="role-desc">Browse &amp; order from local shops</span>
              </label>
            </div>
            <div class="role-option">
              <input type="radio" name="role" id="role-owner" value="shop_owner"/>
              <label class="role-label" for="role-owner">
                <span class="role-icon">🏪</span><span class="role-name">Shop Owner</span>
                <span class="role-desc">List your shop &amp; receive orders</span>
              </label>
            </div>
          </div>
        </div>
        <button type="submit" class="btn-ll btn-primary-ll btn-full" id="register-btn"><span>✨</span> Create Account</button>
        <div class="divider-text">or</div>
        <button type="button" class="btn-ll btn-outline-ll btn-full" onclick="switchTab('login')">Already have an account? Sign In</button>
      </form>
    </div>
  </div>
</div>

<script>
function switchTab(tab){document.querySelectorAll('.auth-tab').forEach(t=>t.classList.remove('active'));document.querySelectorAll('.auth-form').forEach(f=>f.classList.remove('active'));document.getElementById('tab-'+tab).classList.add('active');document.getElementById('form-'+tab).classList.add('active');clearAlert();}
function togglePwd(id,btn){const i=document.getElementById(id);i.type=i.type==='password'?'text':'password';btn.textContent=i.type==='password'?'👁':'🙈';}
function showAlert(msg,type='danger'){const el=document.getElementById('auth-alert');el.className='alert-ll '+type;el.innerHTML=(type==='danger'?'⚠️ ':'✅ ')+msg;el.style.display='flex';}
function clearAlert(){document.getElementById('auth-alert').style.display='none';}
async function handleLogin(e){e.preventDefault();clearAlert();const btn=document.getElementById('login-btn');btn.innerHTML='⏳ Signing in...';btn.disabled=true;const res=await fetch('api/login.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:document.getElementById('login-email').value,password:document.getElementById('login-password').value})});const data=await res.json();if(data.success){showAlert('Welcome back! Redirecting...','success');setTimeout(()=>window.location.href=data.redirect||'dashboard.php',900);}else{showAlert(data.message||'Invalid credentials');btn.innerHTML='🚀 Sign In';btn.disabled=false;}}
async function handleRegister(e){e.preventDefault();clearAlert();const btn=document.getElementById('register-btn');btn.innerHTML='⏳ Creating...';btn.disabled=true;const role=document.querySelector('input[name="role"]:checked').value;const res=await fetch('api/register.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:document.getElementById('reg-name').value,email:document.getElementById('reg-email').value,phone:document.getElementById('reg-phone').value,password:document.getElementById('reg-password').value,role})});const data=await res.json();if(data.success){showAlert('Account created! Signing you in...','success');setTimeout(()=>window.location.href=data.redirect||'dashboard.php',900);}else{showAlert(data.message||'Registration failed');btn.innerHTML='✨ Create Account';btn.disabled=false;}}
</script>
</body>
</html>

<?php /** @var ?string $error  @var string $username */ ?>
<form method="post" class="auth-card" action="<?= e(url('admin/login')) ?>">
  <?= csrf_field() ?>
  <h1>Sign in</h1>
  <p class="auth-sub">Enter your admin details to continue.</p>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="field">
    <label for="username">Username or email</label>
    <input id="username" name="username" value="<?= e($username) ?>" required autofocus
           autocomplete="username">
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input id="password" type="password" name="password" required autocomplete="current-password">
  </div>

  <button class="btn btn-primary btn-block btn-lg" type="submit">Sign in</button>
</form>

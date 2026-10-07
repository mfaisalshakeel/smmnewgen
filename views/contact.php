<?php
/** @var array $errors  @var array $input */
$wa    = preg_replace('/\D/', '', (string) setting('whatsapp_number', ''));
$email = setting('support_email', '');
?>
<section class="page-head">
  <div class="wrap">
    <h1>Contact us</h1>
    <p>Questions before you order, or something wrong with one you placed? Write to us here,
       or message us on WhatsApp for the fastest reply.</p>
  </div>
</section>

<section class="section" style="padding-top:24px">
  <div class="wrap" style="max-width:760px">
    <?php if ($wa !== '' || $email !== ''): ?>
      <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin-bottom:28px">
        <?php if ($wa !== ''): ?>
          <a class="btn btn-wa btn-lg" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">
            <svg class="icon"><use href="#i-whatsapp"></use></svg> WhatsApp us
          </a>
        <?php endif; ?>
        <?php if ($email !== ''): ?>
          <a class="btn btn-ghost btn-lg" href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('contact')) ?>" class="track-card">
      <?= csrf_field() ?>
      <div class="hp" aria-hidden="true">
        <label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <div class="field">
        <label for="c-name">Your name</label>
        <input id="c-name" name="name" value="<?= e($input['name']) ?>" required
               <?= isset($errors['name']) ? 'aria-invalid="true"' : '' ?>>
        <?php if (isset($errors['name'])): ?><span class="err"><?= e($errors['name']) ?></span><?php endif; ?>
      </div>

      <div class="field">
        <label for="c-email">Email</label>
        <input id="c-email" type="email" name="email" value="<?= e($input['email']) ?>"
               <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>>
        <?php if (isset($errors['email'])): ?>
          <span class="err"><?= e($errors['email']) ?></span>
        <?php else: ?><small>Or leave this and give a WhatsApp number instead.</small><?php endif; ?>
      </div>

      <div class="field">
        <label for="c-wa">WhatsApp number</label>
        <input id="c-wa" name="whatsapp" value="<?= e($input['whatsapp']) ?>" placeholder="+92 300 1234567">
      </div>

      <div class="field">
        <label for="c-body">Message</label>
        <textarea id="c-body" name="body" rows="6" required
                  style="width:100%;border:1.5px solid var(--line);border-radius:13px;padding:13px 15px;
                         font:inherit;font-size:15px;background:var(--bg-soft);outline:none"
                  <?= isset($errors['body']) ? 'aria-invalid="true"' : '' ?>><?= e($input['body']) ?></textarea>
        <?php if (isset($errors['body'])): ?><span class="err"><?= e($errors['body']) ?></span><?php endif; ?>
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="submit">Send message</button>
    </form>
  </div>
</section>

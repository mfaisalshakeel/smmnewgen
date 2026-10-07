<?php /** Shown when no platform is set up yet - better than a blank shop. */ ?>
<section class="section">
  <div class="wrap">
    <div class="empty-state" style="max-width:560px;margin:8vh auto">
      <b>This shop is not set up yet</b>
      Add a platform and a few services in the admin panel and they will appear here.
      <p style="margin-top:18px">
        <a class="btn btn-primary" href="<?= e(url('admin')) ?>">Open the admin panel</a>
      </p>
    </div>
  </div>
</section>

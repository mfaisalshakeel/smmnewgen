<?php /** @var array $faqs */ ?>
<section class="page-head">
  <div class="wrap">
    <h1>Frequently asked questions</h1>
    <p>If your question is not here, message us and we will answer it.</p>
  </div>
</section>

<section class="section" style="padding-top:24px">
  <div class="wrap">
    <?php if (!$faqs): ?>
      <div class="empty-state"><b>Nothing here yet</b>Questions will appear here soon.</div>
    <?php else: ?>
      <div class="faq">
        <?php foreach ($faqs as $i => $faq): ?>
          <div class="fitem<?= $i === 0 ? ' open' : '' ?>">
            <button class="fq" type="button" data-faq>
              <?= e($faq['question']) ?>
              <?php if ($faq['platform_name']): ?>
                <span class="eyebrow" style="margin:0 0 0 8px;padding:3px 9px;font-size:10.5px">
                  <?= e($faq['platform_name']) ?></span>
              <?php endif; ?>
              <i>+</i>
            </button>
            <div class="fa"<?= $i === 0 ? ' style="max-height:400px"' : '' ?>>
              <p><?= e($faq['answer']) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

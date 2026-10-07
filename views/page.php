<?php /** A content page. @var array $page */ ?>
<section class="page-head">
  <div class="wrap"><h1><?= e($page['title']) ?></h1></div>
</section>
<section class="section" style="padding-top:20px">
  <div class="wrap">
    <div class="prose"><?= $page['content'] /* trusted: written by an admin */ ?></div>
  </div>
</section>

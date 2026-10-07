<?php /** @var array $page */ ?>
<div class="wrap" style="max-width:760px;padding:8vh 20px">
  <h1 style="font-size:clamp(26px,4.4vw,38px);margin-bottom:20px"><?= e($page['title']) ?></h1>
  <div class="prose"><?= $page['content'] /* trusted: written by an admin */ ?></div>
</div>

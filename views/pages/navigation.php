<?php
$activities = $navData['activities'] ?? [];
$newProducts = $navData['new_products'] ?? [];
$courses = $navData['recommended_courses'] ?? [];
?>
<section class="space-y-8 animate-rise">
    <div class="rounded-[2rem] border border-bronze/20 bg-white/75 p-5 shadow-glow backdrop-blur sm:p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <p class="text-sm uppercase tracking-[0.35em] text-bronze/70">Magic Entry</p>
                <h1 class="mt-2 font-display text-4xl text-bronze sm:text-5xl">欢迎来到奇妙世界入口</h1>
            </div>
            <div class="hidden h-20 w-20 rounded-full border border-bronze/20 bg-parchment/80 sm:flex sm:items-center sm:justify-center sm:text-bronze sm:shadow-card">妙</div>
        </div>
        <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-4">
            <a href="/mall" class="group rounded-[1.6rem] border border-bronze/25 bg-parchment/80 p-4 shadow-card transition duration-300 hover:-translate-y-1 hover:border-bronze hover:shadow-glow sm:p-5">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-bronze/10 text-2xl text-bronze">市</div>
                <h2 class="font-display text-xl text-ink sm:text-2xl">神奇喵喵屋</h2>
                <p class="mt-2 text-sm text-ink/65">进入电商商城，浏览商品、课程、会员折扣与购物流程。</p>
            </a>
            <a href="https://magic.lyccc.xyz/login" target="_blank" rel="noreferrer" class="group rounded-[1.6rem] border border-teal/25 bg-white/85 p-4 shadow-card transition duration-300 hover:-translate-y-1 hover:border-teal hover:shadow-glow sm:p-5">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-teal/10 text-2xl text-teal">课</div>
                <h2 class="font-display text-xl text-ink sm:text-2xl">知识星球</h2>
                <p class="mt-2 text-sm text-ink/65">跳转到课程网站，继续学习与会员内容相关的扩展知识。</p>
            </a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-display text-2xl text-bronze">热门活动</h2>
                <span class="text-xs uppercase tracking-[0.3em] text-bronze/60">Hot</span>
            </div>
            <div class="space-y-4">
                <?php foreach ($activities as $activity): ?>
                    <a href="/mall/activities/<?= (int) $activity['id'] ?>" class="flex items-center gap-3 rounded-[1.4rem] border border-bronze/10 bg-parchment/70 p-4 transition hover:border-bronze/20 hover:bg-parchment/85">
                        <div class="h-20 w-20 rounded-[1.2rem] bg-cover bg-center"<?= !empty($activity['thumbnail_image']) ? ' style="background-image:url(\'' . htmlspecialchars($activity['thumbnail_image'], ENT_QUOTES, 'UTF-8') . '\')"' : '' ?>>
                            <?php if (!empty($activity['thumbnail_image'])): ?>
                                <span class="sr-only"><?= htmlspecialchars($activity['title'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <div class="flex h-full w-full items-center justify-center text-xs text-ink/40">暂无缩略图</div>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-ink"><?= htmlspecialchars($activity['title'], ENT_QUOTES, 'UTF-8') ?></div>
                            <p class="mt-2 line-clamp-2 text-sm leading-6 text-ink/65"><?= htmlspecialchars($activity['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rounded-[1.8rem] border border-sage/15 bg-white/80 p-5 shadow-card">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-display text-2xl text-sage">商品上新</h2>
                <a href="/mall" class="text-xs uppercase tracking-[0.3em] text-sage/70">进入商城</a>
            </div>
            <div class="space-y-4">
                <?php foreach ($newProducts as $product): ?>
                    <a href="/mall/products/<?= (int) $product['id'] ?>" class="flex items-center gap-3 rounded-[1.4rem] border border-sage/10 bg-sage/5 p-4 transition hover:bg-sage/10">
                        <div class="h-16 w-16 rounded-2xl bg-cover bg-center" style="background-image:url('<?= htmlspecialchars($product['cover_image'], ENT_QUOTES, 'UTF-8') ?>')"></div>
                        <div class="min-w-0">
                            <div class="truncate font-medium text-ink"><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="mt-1 text-sm text-ink/60">¥<?= money_format_cn($product['price']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rounded-[1.8rem] border border-teal/15 bg-white/80 p-5 shadow-card">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-display text-2xl text-teal">推荐课程</h2>
                <span class="text-xs uppercase tracking-[0.3em] text-teal/70">Course</span>
            </div>
            <div class="space-y-4">
                <?php foreach ($courses as $course): ?>
                    <a href="/mall/products/<?= (int) $course['id'] ?>" class="flex items-center gap-3 rounded-[1.4rem] border border-teal/10 bg-teal/5 p-4 transition hover:bg-teal/10">
                        <div class="h-20 w-20 rounded-[1.2rem] bg-cover bg-center" style="background-image:url('<?= htmlspecialchars($course['cover_image'], ENT_QUOTES, 'UTF-8') ?>')"></div>
                        <div class="min-w-0">
                            <div class="font-medium text-ink"><?= htmlspecialchars($course['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <p class="mt-2 line-clamp-2 text-sm leading-6 text-ink/60"><?= htmlspecialchars($course['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</section>
